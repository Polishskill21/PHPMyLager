<?php

namespace App\Http\Controllers\PurchaseOrders;

use App\Models\PurchaseOrders\PurchaseOrder; 
use App\Models\PurchaseOrders\PurchaseOrderItem;
use App\Models\Products\Product;
use App\Models\Suppliers\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Enums\PurchaseOrderStatus;


class PurchaseOrderController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    // READ
    // ──────────────────────────────────────────────────────────────────────────

    /** GET /purchase-orders */
    public function index(): JsonResponse
    {
        $orders = PurchaseOrder::with('items.product', 'supplier')->get();
 
        return $this->ok(
            $orders->map(fn (PurchaseOrder $o) => $this->formatOrder($o))
        );
    }

    /** GET /purchase-orders/{order} */
    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->load('items.product', 'supplier');
 
        return $this->ok($this->formatOrder($purchaseOrder));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // CREATE
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * POST /purchase-orders
     *
     * Creates a new purchase order with status "offen".
     * Stock is NOT touched yet — that happens on receive.
     */
        public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->storeRules(), $this->customMessages());
 
        $order = DB::transaction(function () use ($validated): PurchaseOrder {
            $order = PurchaseOrder::create([
                PurchaseOrder::COL_F_LIEF_NR    => $validated[PurchaseOrder::COL_F_LIEF_NR] ?? null,
                PurchaseOrder::COL_BEST_DAT     => $validated[PurchaseOrder::COL_BEST_DAT],
                PurchaseOrder::COL_ERW_LIEF_DAT => $validated[PurchaseOrder::COL_ERW_LIEF_DAT] ?? null,
                PurchaseOrder::COL_STATUS       => PurchaseOrderStatus::Open,
            ]);
 
            foreach ($validated['items'] as $item) {
                $order->items()->create([
                    PurchaseOrderItem::COL_F_ARTIKEL_NR     => $item[PurchaseOrderItem::COL_F_ARTIKEL_NR],
                    PurchaseOrderItem::COL_BEST_MENGE       => $item[PurchaseOrderItem::COL_BEST_MENGE],
                    PurchaseOrderItem::COL_GELIEFERTE_MENGE => 0,
                    PurchaseOrderItem::COL_EK_PREIS         => $item[PurchaseOrderItem::COL_EK_PREIS] ?? null,
                ]);
            }
 
            return $order->load('items.product', 'supplier');
        });
 
        return $this->created($this->formatOrder($order), 'Purchase order created successfully.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // UPDATE HEADER / LINES (only while status = offen | bestellt)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * PUT /purchase-orders/{order}
     *
     * Updates header fields and line items.
     * Blocked once status is "geliefert" or "storniert".
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (in_array($purchaseOrder->{PurchaseOrder::COL_STATUS}, [PurchaseOrderStatus::Delivered, PurchaseOrderStatus::Cancelled], true)) {
            return $this->unprocessable('Closed orders cannot be edited.');
        }
        
        $isLocked = $purchaseOrder->{PurchaseOrder::COL_STATUS} === PurchaseOrderStatus::Ordered;
 
        $validated = $request->validate($this->updateRules(), $this->customMessages());
 
        $purchaseOrder = DB::transaction(function () use ($validated, $purchaseOrder, $isLocked): PurchaseOrder {
            $purchaseOrder->update([
                PurchaseOrder::COL_F_LIEF_NR    => $validated[PurchaseOrder::COL_F_LIEF_NR] ?? null,
                PurchaseOrder::COL_BEST_DAT     => $validated[PurchaseOrder::COL_BEST_DAT],
                PurchaseOrder::COL_ERW_LIEF_DAT => $validated[PurchaseOrder::COL_ERW_LIEF_DAT] ?? null,
            ]);
 
            $current         = $purchaseOrder->items->keyBy(PurchaseOrderItem::COL_ID);
            $submittedPosNrs = [];
 
            foreach ($validated['items'] as $item) {
                if (!empty($item[PurchaseOrderItem::COL_ID])) {
                    $posNr             = (int) $item[PurchaseOrderItem::COL_ID];
                    $submittedPosNrs[] = $posNr;
 
                    $existing = $current->get($posNr)
                        ?? throw new \Exception(PurchaseOrderItem::COL_ID . "={$posNr} does not belong to this order.");
 
                    if ($isLocked && (int) $item[PurchaseOrderItem::COL_BEST_MENGE] < (int) $existing->{PurchaseOrderItem::COL_GELIEFERTE_MENGE}) {
                        throw ValidationException::withMessages([
                            'items' => "Row #{$posNr}: bestMenge cannot be less than already delivered ({$existing->{PurchaseOrderItem::COL_GELIEFERTE_MENGE}}).",
                        ]);
                    }
 
                    $existing->update([
                        PurchaseOrderItem::COL_BEST_MENGE => $item[PurchaseOrderItem::COL_BEST_MENGE],
                        PurchaseOrderItem::COL_EK_PREIS   => $item[PurchaseOrderItem::COL_EK_PREIS] ?? $existing->{PurchaseOrderItem::COL_EK_PREIS},
                    ]);
                } else {
                    $purchaseOrder->items()->create([
                        PurchaseOrderItem::COL_F_ARTIKEL_NR     => $item[PurchaseOrderItem::COL_F_ARTIKEL_NR],
                        PurchaseOrderItem::COL_BEST_MENGE       => $item[PurchaseOrderItem::COL_BEST_MENGE],
                        PurchaseOrderItem::COL_GELIEFERTE_MENGE => 0,
                        PurchaseOrderItem::COL_EK_PREIS         => $item[PurchaseOrderItem::COL_EK_PREIS] ?? null,
                    ]);
                }
            }
 
            // Remove lines not in the request
            foreach ($current as $posNr => $line) {
                if (!in_array($posNr, $submittedPosNrs, true)) {
                    if ($isLocked) {
                        throw ValidationException::withMessages([
                            'items' => "Row #{$posNr}: line items cannot be removed after delivery has started.",
                        ]);
                    }
                    $line->delete();
                }
            }
 
            return $purchaseOrder->fresh(['items.product', 'supplier']);
        });
 
        return $this->ok($this->formatOrder($purchaseOrder), 'Purchase order updated successfully.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // RECEIVE DELIVERY  ← the key action that increases stock
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * PATCH /purchase-orders/{order}/receive
     *
     * Marks goods as received and increments product stock.
     * Supports partial delivery: pass gelieferteMenge per line.
     * If all lines are fully delivered the order status → "geliefert".
     */
    public function receiveDelivery(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->{PurchaseOrder::COL_STATUS} === PurchaseOrderStatus::Cancelled) {
            return $this->unprocessable('Cannot receive a cancelled order.');
        }
        if ($purchaseOrder->{PurchaseOrder::COL_STATUS} === PurchaseOrderStatus::Delivered) {
            return $this->unprocessable('Order already fully received.');
        }
 
        $validated = $request->validate([
            'items'                                            => 'required|array|min:1',
            'items.*.'.PurchaseOrderItem::COL_ID               => 'required|integer',
            'items.*.'.PurchaseOrderItem::COL_GELIEFERTE_MENGE => 'required|integer|min:1',
        ]);
 
        $purchaseOrder = DB::transaction(function () use ($validated, $purchaseOrder): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::whereKey($purchaseOrder->getKey())->lockForUpdate()->firstOrFail();
 
            $lines = $purchaseOrder->items()->lockForUpdate()->get()->keyBy(PurchaseOrderItem::COL_ID);
 
            foreach ($validated['items'] as $incoming) {
                $posNr  = (int) $incoming[PurchaseOrderItem::COL_ID];
                $newQty = (int) $incoming[PurchaseOrderItem::COL_GELIEFERTE_MENGE];
                $line   = $lines->get($posNr) ?? throw new \Exception("Row #{$posNr} does not belong to this order.");
 
                $remaining = $line->{PurchaseOrderItem::COL_BEST_MENGE} - $line->{PurchaseOrderItem::COL_GELIEFERTE_MENGE};
 
                if ($newQty > $remaining) {
                    throw ValidationException::withMessages([
                        'items' => "Row #{$posNr}: cannot receive {$newQty} units, only {$remaining} remaining.",
                    ]);
                }
 
                $product = Product::withTrashed()
                                  ->where(Product::COL_ID, $line->{PurchaseOrderItem::COL_F_ARTIKEL_NR})
                                  ->lockForUpdate()
                                  ->firstOrFail();
 
                if ($product->trashed()) {
                    throw ValidationException::withMessages([
                        'items' => "Row #{$posNr}: Cannot receive product {$product->{Product::COL_ID}} because it has been discontinued.",
                    ]);
                }

                $product->increment(Product::COL_BESTAND, $newQty);
                // Update delivered quantity on the line
                $line->increment(PurchaseOrderItem::COL_GELIEFERTE_MENGE, $newQty);
            }
 
            // Refresh lines and check if all fully delivered
            $purchaseOrder->load('items');
            $allDelivered = $purchaseOrder->items->every(
                fn ($l) => $l->{PurchaseOrderItem::COL_GELIEFERTE_MENGE} >= $l->{PurchaseOrderItem::COL_BEST_MENGE}
            );
 
            $purchaseOrder->update([
                PurchaseOrder::COL_STATUS => $allDelivered ? PurchaseOrderStatus::Delivered : PurchaseOrderStatus::Ordered,
            ]);
 
            return $purchaseOrder->fresh(['items.product', 'supplier']);
        });
 
        return $this->ok($this->formatOrder($purchaseOrder), 'Delivery received successfully.');
    }
    

    // ──────────────────────────────────────────────────────────────────────────
    // CANCEL
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * DELETE /purchase-orders/{order}
     *
     * Cancels the order (sets status = storniert).
     * Only possible when status is "offen" or "bestellt".
     */
    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (in_array($purchaseOrder->{PurchaseOrder::COL_STATUS}, [PurchaseOrderStatus::Delivered, PurchaseOrderStatus::Cancelled], true)) {
            return $this->unprocessable('Only open or ordered purchases can be cancelled.');
        }
 
        try {
            DB::transaction(function () use ($purchaseOrder): void {
                $purchaseOrder = PurchaseOrder::whereKey($purchaseOrder->getKey())->lockForUpdate()->firstOrFail();
                
                $lines = $purchaseOrder->items()->lockForUpdate()->get();
 
                foreach ($lines as $line) {
                    if ($line->{PurchaseOrderItem::COL_GELIEFERTE_MENGE} > 0) {
                        $product = Product::withTrashed()
                            ->where(Product::COL_ID, $line->{PurchaseOrderItem::COL_F_ARTIKEL_NR})
                            ->lockForUpdate()
                            ->first();
 
                        if ($product) {
                            // Check if removing this delivery will cause an inventory deficit
                            if ($product->{Product::COL_BESTAND} < $line->{PurchaseOrderItem::COL_GELIEFERTE_MENGE}) {
                                throw ValidationException::withMessages([
                                    'items' => sprintf(
                                        'Cannot cancel purchase order. Product %s (%s) has already been allocated to customer orders. Current stock: %d, trying to remove: %d.',
                                        $product->{Product::COL_ID},
                                        $product->{Product::COL_NAME},
                                        $product->{Product::COL_BESTAND},
                                        $line->{PurchaseOrderItem::COL_GELIEFERTE_MENGE}
                                    ),
                                ]);
                            }

                            $newStock = max(0, $product->{Product::COL_BESTAND} - $line->{PurchaseOrderItem::COL_GELIEFERTE_MENGE});
                            $product->update([Product::COL_BESTAND => $newStock]);
                        }
                    }
                }
 
                $purchaseOrder->update([PurchaseOrder::COL_STATUS => PurchaseOrderStatus::Cancelled]);
            });
 
            return $this->noContent();

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            report($e);
            return $this->serverError();
        }
    }


    // ──────────────────────────────────────────────────────────────────────────
    // HELPER
    // ──────────────────────────────────────────────────────────────────────────

    private function storeRules(): array
    {
        return [
            PurchaseOrder::COL_F_LIEF_NR                     => [
                'nullable', 'integer', 
                Rule::exists(Supplier::TABLE, Supplier::COL_ID)->whereNull('deleted_at')
            ],
            PurchaseOrder::COL_BEST_DAT                      => 'required|date',
            PurchaseOrder::COL_ERW_LIEF_DAT                  => 'nullable|date|after_or_equal:'.PurchaseOrder::COL_BEST_DAT,
            'items'                                          => 'required|array|min:1',
            'items.*.' . PurchaseOrderItem::COL_F_ARTIKEL_NR => [
                'required', 'integer', 
                Rule::exists(Product::TABLE, Product::COL_ID)->whereNull('deleted_at')
            ],
            'items.*.' . PurchaseOrderItem::COL_BEST_MENGE   => 'required|integer|min:1',
            'items.*.' . PurchaseOrderItem::COL_EK_PREIS     => 'nullable|numeric|min:0',
        ];
    }

    private function updateRules(): array {
        return [
            PurchaseOrder::COL_F_LIEF_NR => [
                'nullable', 'integer', 
                Rule::exists(Supplier::TABLE, Supplier::COL_ID)->whereNull('deleted_at')
            ],
            PurchaseOrder::COL_BEST_DAT                      => 'required|date',
            PurchaseOrder::COL_ERW_LIEF_DAT                  => 'nullable|date|after_or_equal:' . PurchaseOrder::COL_BEST_DAT,
            'items'                                          => 'required|array|min:1',
            'items.*.' . PurchaseOrderItem::COL_ID           => 'nullable|integer',
            'items.*.' . PurchaseOrderItem::COL_F_ARTIKEL_NR => 'required|integer|exists:'.Product::TABLE.','.Product::COL_ID,
            'items.*.' . PurchaseOrderItem::COL_BEST_MENGE   => 'required|integer|min:1',
            'items.*.' . PurchaseOrderItem::COL_EK_PREIS     => 'nullable|numeric|min:0',
        ];
    }

    private function customMessages(): array
    {
        return [
            PurchaseOrder::COL_F_LIEF_NR.'.exists'                      => 'The selected supplier does not exist or has been deleted.',
            PurchaseOrder::COL_ERW_LIEF_DAT.'.after_or_equal'           => 'The expected delivery date must be on or after the order date.',
            'items.required'                                            => 'At least one order item is required.',
            'items.min'                                                 => 'At least one order item is required.',
            'items.*.'.PurchaseOrderItem::COL_F_ARTIKEL_NR.'.exists'    => 'The product selected in row #:position is invalid or has been discontinued.',
            'items.*.'.PurchaseOrderItem::COL_BEST_MENGE.'.min'         => 'The quantity for the item in row #:position must be at least 1.',
        ];
    }

    private function formatOrder(PurchaseOrder $order): array
    {
        $items = $order->items;

        $totalOrdered   = $items->sum(PurchaseOrderItem::COL_BEST_MENGE);
        $totalDelivered = $items->sum(PurchaseOrderItem::COL_GELIEFERTE_MENGE);
        $totalValue     = $items->sum(
            fn ($i) => (float) ($i->{PurchaseOrderItem::COL_EK_PREIS} ?? 0) * (int) $i->{PurchaseOrderItem::COL_BEST_MENGE}
        );

        return [
            'order_info' => [
                PurchaseOrder::COL_ID           => $order->{PurchaseOrder::COL_ID},
                PurchaseOrder::COL_F_LIEF_NR    => $order->{PurchaseOrder::COL_F_LIEF_NR},
                'lieferant'                     => $order->supplier?->{Supplier::COL_NAME},
                'is_supplier_deleted'           => $order->supplier?->trashed() ?? false,
                PurchaseOrder::COL_BEST_DAT     => $order->{PurchaseOrder::COL_BEST_DAT},
                PurchaseOrder::COL_ERW_LIEF_DAT => $order->{PurchaseOrder::COL_ERW_LIEF_DAT},
                PurchaseOrder::COL_STATUS       => $order->{PurchaseOrder::COL_STATUS},
            ],
            'items' => $items->map(fn ($item) => [
                PurchaseOrderItem::COL_ID                 => $item->{PurchaseOrderItem::COL_ID},
                PurchaseOrderItem::COL_F_ARTIKEL_NR       => $item->{PurchaseOrderItem::COL_F_ARTIKEL_NR},
                Product::COL_NAME                         => $item->product?->{Product::COL_NAME},
                Product::COL_LAGERPLATZ                   => $item->product?->{Product::COL_LAGERPLATZ},
                PurchaseOrderItem::COL_BEST_MENGE         => $item->{PurchaseOrderItem::COL_BEST_MENGE},
                PurchaseOrderItem::COL_GELIEFERTE_MENGE   => $item->{PurchaseOrderItem::COL_GELIEFERTE_MENGE},
                PurchaseOrderItem::COL_EK_PREIS           => $item->{PurchaseOrderItem::COL_EK_PREIS},
                'line_total'                              => round((float) ($item->{PurchaseOrderItem::COL_EK_PREIS} ?? 0) * $item->{PurchaseOrderItem::COL_BEST_MENGE}, 2),
            ])->values(),
            'total_ordered'   => $totalOrdered,
            'total_delivered' => $totalDelivered,
            'total_value'     => round($totalValue, 2),
        ];
    }
}