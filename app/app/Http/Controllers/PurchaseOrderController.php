<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder; 
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

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
                'fLiefNr'      => $validated['fLiefNr'] ?? null,
                'bestDat'      => $validated['bestDat'],
                'erwLieferDat' => $validated['erwLieferDat'] ?? null,
                'status'       => 'offen',
            ]);
 
            foreach ($validated['items'] as $item) {
                $order->items()->create([
                    'fArtikelNr'      => $item['fArtikelNr'],
                    'bestMenge'       => $item['bestMenge'],
                    'gelieferteMenge' => 0,
                    'ekPreis'         => $item['ekPreis'] ?? null,
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
        if (in_array($purchaseOrder->status, ['geliefert', 'storniert'], true)) {
            return $this->unprocessable('Closed orders cannot be edited.');
        }
        
        $isLocked = $purchaseOrder->status === 'bestellt';
 
        $validated = $request->validate($this->updateRules(), $this->customMessages());
 
        $purchaseOrder = DB::transaction(function () use ($validated, $purchaseOrder, $isLocked): PurchaseOrder {
            $purchaseOrder->update([
                'fLiefNr'      => $validated['fLiefNr'] ?? null,
                'bestDat'      => $validated['bestDat'],
                'erwLieferDat' => $validated['erwLieferDat'] ?? null,
            ]);
 
            $current         = $purchaseOrder->items->keyBy('pBestPosNr');
            $submittedPosNrs = [];
 
            foreach ($validated['items'] as $item) {
                if (!empty($item['pBestPosNr'])) {
                    $posNr             = (int) $item['pBestPosNr'];
                    $submittedPosNrs[] = $posNr;
 
                    $existing = $current->get($posNr)
                        ?? throw new \Exception("pBestPosNr={$posNr} does not belong to this order.");
 
                    if ($isLocked && (int) $item['bestMenge'] < (int) $existing->gelieferteMenge) {
                        throw ValidationException::withMessages([
                            'items' => "pBestPosNr={$posNr}: bestMenge cannot be less than already delivered ({$existing->gelieferteMenge}).",
                        ]);
                    }
 
                    $existing->update([
                        'bestMenge' => $item['bestMenge'],
                        'ekPreis'   => $item['ekPreis'] ?? $existing->ekPreis,
                    ]);
                } else {
                    $purchaseOrder->items()->create([
                        'fArtikelNr'      => $item['fArtikelNr'],
                        'bestMenge'       => $item['bestMenge'],
                        'gelieferteMenge' => 0,
                        'ekPreis'         => $item['ekPreis'] ?? null,
                    ]);
                }
            }
 
            // Remove lines not in the request
            foreach ($current as $posNr => $line) {
                if (!in_array($posNr, $submittedPosNrs, true)) {
                    if ($isLocked) {
                        throw ValidationException::withMessages([
                            'items' => "pBestPosNr={$posNr}: line items cannot be removed after delivery has started.",
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
        if ($purchaseOrder->status === 'storniert') {
            return $this->unprocessable('Cannot receive a cancelled order.');
        }
        if ($purchaseOrder->status === 'geliefert') {
            return $this->unprocessable('Order already fully received.');
        }
 
        $validated = $request->validate([
            'items'                   => 'required|array|min:1',
            'items.*.pBestPosNr'      => 'required|integer',
            'items.*.gelieferteMenge' => 'required|integer|min:1',
        ]);
 
        $purchaseOrder = DB::transaction(function () use ($validated, $purchaseOrder): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::whereKey($purchaseOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();
 
            $lines = $purchaseOrder->items()->lockForUpdate()->get()->keyBy('pBestPosNr');
 
            foreach ($validated['items'] as $incoming) {
                $posNr     = (int) $incoming['pBestPosNr'];
                $newQty    = (int) $incoming['gelieferteMenge'];
                $line      = $lines->get($posNr)
                    ?? throw new \Exception("pBestPosNr={$posNr} does not belong to this order.");
 
                $remaining = $line->bestMenge - $line->gelieferteMenge;
 
                if ($newQty > $remaining) {
                    throw ValidationException::withMessages([
                        'items' => "pBestPosNr={$posNr}: cannot receive {$newQty} units, " .
                                   "only {$remaining} remaining.",
                    ]);
                }
 
                $product = Product::withTrashed()
                                  ->where('pArtikelNr', $line->fArtikelNr)
                                  ->lockForUpdate()
                                  ->firstOrFail();
 
                if ($product->trashed()) {
                    throw ValidationException::withMessages([
                        'items' => "pBestPosNr={$posNr}: Cannot receive product {$product->pArtikelNr} because it has been discontinued and removed from the catalog.",
                    ]);
                }
 
                $product->increment('bestand', $newQty);
                // Update delivered quantity on the line
                $line->increment('gelieferteMenge', $newQty);
            }
 
            // Refresh lines and check if all fully delivered
            $purchaseOrder->load('items');
            $allDelivered = $purchaseOrder->items->every(
                fn ($l) => $l->gelieferteMenge >= $l->bestMenge
            );
 
            $purchaseOrder->update([
                'status' => $allDelivered ? 'geliefert' : 'bestellt',
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
        if (in_array($purchaseOrder->status, ['geliefert', 'storniert'], true)) {
            return $this->unprocessable('Only open or ordered purchases can be cancelled.');
        }
 
        DB::transaction(function () use ($purchaseOrder): void {
            $purchaseOrder = PurchaseOrder::whereKey($purchaseOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();
 
            $lines = $purchaseOrder->items()->lockForUpdate()->get();
 
            foreach ($lines as $line) {
                if ($line->gelieferteMenge > 0) {
                    $product = Product::withTrashed()
                        ->where('pArtikelNr', $line->fArtikelNr)
                        ->lockForUpdate()
                        ->first();
 
                    if ($product) {
                        $newStock = max(0, $product->bestand - $line->gelieferteMenge);
                        $product->update(['bestand' => $newStock]);
                    }
                }
            }
 
            $purchaseOrder->update(['status' => 'storniert']);
        });
 
        return $this->ok(null, "Purchase order {$purchaseOrder->pBestNr} cancelled.");
    }


    // ──────────────────────────────────────────────────────────────────────────
    // HELPER
    // ──────────────────────────────────────────────────────────────────────────

    private function storeRules(): array
    {
        return [
            'fLiefNr' => [
                'nullable', 'integer', 
                Rule::exists('lieferanten', 'pLiefNr')->whereNull('deleted_at')
            ],
            'bestDat'            => 'required|date',
            'erwLieferDat'       => 'nullable|date|after_or_equal:bestDat',
            'items'              => 'required|array|min:1',
            'items.*.fArtikelNr' => [
                'required', 'integer', 
                Rule::exists('artikel', 'pArtikelNr')->whereNull('deleted_at')
            ],
            'items.*.bestMenge'  => 'required|integer|min:1',
            'items.*.ekPreis'    => 'nullable|numeric|min:0',
        ];
    }

    private function updateRules(): array {
        return [
            'fLiefNr' => [
                'nullable', 'integer', 
                Rule::exists('lieferanten', 'pLiefNr')->whereNull('deleted_at')
            ],
            'bestDat'           => 'required|date',
            'erwLieferDat'      => 'nullable|date|after_or_equal:bestDat',
            'items'             => 'required|array|min:1',
            'items.*.pBestPosNr'=> 'nullable|integer',
            'items.*.fArtikelNr'=> 'required|integer|exists:artikel,pArtikelNr',
            'items.*.bestMenge' => 'required|integer|min:1',
            'items.*.ekPreis'   => 'nullable|numeric|min:0',
        ];
    }

    private function customMessages(): array
    {
        return [
            'fLiefNr.exists'               => 'The selected supplier does not exist or has been deleted.',
            'erwLieferDat.after_or_equal'  => 'The expected delivery date must be on or after the order date.',
            'items.required'               => 'At least one order item is required.',
            'items.min'                    => 'At least one order item is required.',
            'items.*.fArtikelNr.exists'    => 'The product selected in row #:position is invalid or has been discontinued.',
            'items.*.bestMenge.min'        => 'The quantity for the item in row #:position must be at least 1.',
        ];
    }

    private function formatOrder(PurchaseOrder $order): array
    {
        $items = $order->items;

        $totalOrdered   = $items->sum('bestMenge');
        $totalDelivered = $items->sum('gelieferteMenge');
        $totalValue     = $items->sum(
            fn ($i) => (float) ($i->ekPreis ?? 0) * (int) $i->bestMenge
        );

        return [
            'order_info' => [
                'pBestNr'      => $order->pBestNr,
                'fLiefNr'      => $order->fLiefNr,
                'lieferant'    => $order->supplier?->name,
                'is_supplier_deleted' => $order->supplier?->trashed() ?? false,
                'bestDat'      => $order->bestDat,
                'erwLieferDat' => $order->erwLieferDat,
                'status'       => $order->status,
            ],
            'items' => $items->map(fn ($item) => [
                'pBestPosNr'      => $item->pBestPosNr,
                'fArtikelNr'      => $item->fArtikelNr,
                'bezeichnung'     => $item->product?->bezeichnung,
                'lagerplatz'      => $item->product?->lagerplatz,
                'bestMenge'       => $item->bestMenge,
                'gelieferteMenge' => $item->gelieferteMenge,
                'ekPreis'         => $item->ekPreis,
                'line_total'      => round((float) ($item->ekPreis ?? 0) * $item->bestMenge, 2),
            ])->values(),
            'total_ordered'   => $totalOrdered,
            'total_delivered' => $totalDelivered,
            'total_value'     => round($totalValue, 2),
        ];
    }
}