<?php

namespace App\Http\Controllers\Orders;

use App\Models\Orders\Order;
use App\Models\Orders\OrderItem;
use App\Models\Products\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;
use App\Models\Customers\Customer;


class OrderController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // READ
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /orders
     */
    public function index(): JsonResponse
    {
        $orders = Order::with(['items.product', 'customer'])->get() 
                       ->map(fn (Order $o) => $this->formatOrder($o));

        return $this->ok($orders);
    }

    /**
     * GET /orders/{order}
     */
    public function show(Order $order): JsonResponse
    {
        $order->load(['items.product', 'customer']);

        return $this->ok($this->formatOrder($order));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREATE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * POST /orders
     * Creates the order header, attaches all line-items, snapshots each
     * product's current selling price and decrements stock.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(
            $this->storeRules(),
            $this->customMessages()
        );

        try {
            $order = DB::transaction(function () use ($validated): Order {
                $order = Order::create([
                    Order::COL_AUF_DAT    => $validated[Order::COL_AUF_DAT],
                    Order::COL_F_KD_NR    => $validated[Order::COL_F_KD_NR],
                    Order::COL_AUF_TERMIN => $validated[Order::COL_AUF_TERMIN],
                ]);

                foreach ($validated['items'] as $item) {
                    $product = Product::where(Product::COL_ID, $item[OrderItem::COL_F_ARTIKEL_NR])
                                      ->lockForUpdate()
                                      ->firstOrFail();

                    $this->ensureSufficientStock($product, $item[OrderItem::COL_AUF_MENGE]);

                    $order->items()->create([
                        OrderItem::COL_F_ARTIKEL_NR => $product->{Product::COL_ID},
                        OrderItem::COL_AUF_MENGE    => $item[OrderItem::COL_AUF_MENGE],
                        OrderItem::COL_KAUF_PREIS   => $product->{Product::COL_VK_PREIS},
                    ]);

                    $product->decrement(Product::COL_BESTAND, $item[OrderItem::COL_AUF_MENGE]);
                }

                return $order->load('items.product');
            });

            return $this->created($this->formatOrder($order), 'Order created successfully.');

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            report($e);
            return $this->serverError();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UPDATE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * PUT /orders/{order}
     * Full replacement: send the complete list of items; omitted items are
     * deleted and their stock is restored.
     */
    public function update(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate(
            $this->updateRules(),
            $this->customMessages()
        );

        try {
            $order = DB::transaction(function () use ($validated, $order): Order {
                $order->update([
                    Order::COL_AUF_DAT    => $validated[Order::COL_AUF_DAT],
                    Order::COL_F_KD_NR    => $validated[Order::COL_F_KD_NR],
                    Order::COL_AUF_TERMIN => $validated[Order::COL_AUF_TERMIN],
                ]);

                $currentItems    = $order->items->keyBy(OrderItem::COL_ID);
                $submittedPosNrs = [];

                foreach ($validated['items'] as $submittedItem) {
                    $artikelNr = (int) $submittedItem[OrderItem::COL_F_ARTIKEL_NR];
                    $newMenge  = (int) $submittedItem[OrderItem::COL_AUF_MENGE];

                    if (!empty($submittedItem['pAufPosNr'])) {
                        // ── Existing line: adjust quantity ──────────────────
                        $posNr             = (int) $submittedItem[OrderItem::COL_ID];
                        $submittedPosNrs[] = $posNr;

                        $existingItem = $currentItems->get($posNr);

                        if (!$existingItem) {
                            // Business-logic error, not a user input error — 422
                            throw ValidationException::withMessages([
                                'items' => "Order item {$posNr} does not belong to order {$order->{Order::COL_ID}}.",
                            ]);
                        }

                        if ((int) $existingItem->{OrderItem::COL_F_ARTIKEL_NR} !== $artikelNr) {
                            throw ValidationException::withMessages([
                                'items' => "Cannot change Product ID on {$posNr}. Remove the item and re-add it with the new product.",
                            ]);
                        }

                        $diff = $newMenge - (int) $existingItem->{OrderItem::COL_AUF_MENGE};

                        if ($diff !== 0) {
                            $product = Product::withTrashed()
                                              ->where(Product::COL_ID, $artikelNr)
                                              ->lockForUpdate()
                                              ->firstOrFail();

                            if ($product->trashed()) {
                                throw ValidationException::withMessages([
                                    'items' => "Cannot alter the quantity of product {$product->{Product::COL_ID}} ({$product->{Product::COL_NAME}}) because it has been discontinued.",
                                ]);
                            }

                            if ($diff > 0) {
                                $this->ensureSufficientStock($product, $diff);
                                $product->decrement(Product::COL_BESTAND, $diff);
                            } else {
                                $product->increment(Product::COL_BESTAND, abs($diff));
                            }
                        }

                        $existingItem->update([OrderItem::COL_AUF_MENGE => $newMenge]);

                    } else {
                        // ── New line ────────────────────────────────────────
                        $product = Product::where(Product::COL_ID, $artikelNr)
                                          ->lockForUpdate()
                                          ->firstOrFail();

                        $this->ensureSufficientStock($product, $newMenge);

                        $order->items()->create([
                            OrderItem::COL_F_ARTIKEL_NR => $artikelNr,
                            OrderItem::COL_AUF_MENGE    => $newMenge,
                            OrderItem::COL_KAUF_PREIS   => $product->{Product::COL_VK_PREIS},
                        ]);

                        $product->decrement(Product::COL_BESTAND, $newMenge);
                    }
                }

                // ── Remove lines omitted from the request ───────────────────
                foreach ($currentItems as $posNr => $item) {
                    if (!in_array($posNr, $submittedPosNrs, true)) {
                        Product::withTrashed()
                               ->where(Product::COL_ID, $item->{OrderItem::COL_F_ARTIKEL_NR})
                               ->increment(Product::COL_BESTAND, $item->{OrderItem::COL_AUF_MENGE});
                        $item->delete();
                    }
                }

                return $order->fresh(['items.product']);
            });

            return $this->ok($this->formatOrder($order), 'Order updated successfully.');

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            report($e);
            return $this->serverError();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * DELETE /orders/{order}
     * Restores stock for every line-item, then removes all positions and the
     * order header.
     */
    public function destroy(Order $order): JsonResponse
    {
        try {
            DB::transaction(function () use ($order): void {
                $order->load('items');

                foreach ($order->items as $item) {
                    Product::withTrashed()
                           ->where(Product::COL_ID, $item->{OrderItem::COL_F_ARTIKEL_NR})
                           ->increment(Product::COL_BESTAND, $item->{OrderItem::COL_AUF_MENGE});
                }

                $order->items()->delete();
                $order->delete();
            });

            return $this->noContent();
        } catch (\Exception $e) {
            report($e);
            return $this->serverError();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Validation rule sets
    // ─────────────────────────────────────────────────────────────────────────

    private function storeRules(): array
    {
        return [
            Order::COL_AUF_DAT                     => 'required|date',
            Order::COL_F_KD_NR                     => ['required', 'integer', Rule::exists(Customer::TABLE, Customer::COL_ID)->whereNull('deleted_at')],
            Order::COL_AUF_TERMIN                  => 'required|date|after_or_equal:' . Order::COL_AUF_DAT,
            'items'                                => 'required|array|min:1',
            'items.*.' . OrderItem::COL_F_ARTIKEL_NR => ['required', 'integer', Rule::exists(Product::TABLE, Product::COL_ID)->whereNull('deleted_at')],
            'items.*.' . OrderItem::COL_AUF_MENGE    => 'required|integer|min:1',
        ];
    }

    private function updateRules(): array
    {
        return [
            Order::COL_AUF_DAT                       => 'required|date',
            Order::COL_F_KD_NR                       => ['required', 'integer', Rule::exists(Customer::TABLE, Customer::COL_ID)->whereNull('deleted_at')],
            Order::COL_AUF_TERMIN                    => 'required|date|after_or_equal:' . Order::COL_AUF_DAT,
            'items'                                  => 'required|array|min:1',
            'items.*.' . OrderItem::COL_ID           => 'nullable|integer',
            'items.*.' . OrderItem::COL_F_ARTIKEL_NR => 'required|integer|exists:' . Product::TABLE . ',' . Product::COL_ID,
            'items.*.' . OrderItem::COL_AUF_MENGE    => 'required|integer|min:1',
        ];
    }

    private function customMessages(): array
    {
        return [
            Order::COL_F_KD_NR . '.exists'                       => 'The selected customer does not exist.',
            Order::COL_AUF_TERMIN . '.after_or_equal'            => 'The delivery date must be on or after the order date.',
            'items.required'                                     => 'At least one order item is required.',
            'items.min'                                          => 'At least one order item is required.',
            'items.*.' . OrderItem::COL_F_ARTIKEL_NR . '.exists' => 'The product selected is invalid or has been discontinued.',
            'items.*.' . OrderItem::COL_AUF_MENGE . '.min'       => 'The quantity for the item must be at least 1.',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Response shape:
     * {
     *   "order_info":  { pAufNr, aufDat, aufTermin, fKdNr, customer_name, is_customer_deleted },
     *   "items":       [ { pAufPosNr, fArtikelNr, bezeichnung, aufMenge,
     *                      kaufPreis, line_total, is_discontinued } ],
     *   "order_total": <total units>,
     *   "preis_total": <sum kaufPreis × aufMenge>
     * }
     */
    private function formatOrder(Order $order): array
    {
        $items = $order->items;
        $customer = $order->customer;

        $orderTotal = $items->sum(OrderItem::COL_AUF_MENGE);
        $preisTotal = $items->sum(
            fn (OrderItem $item) => (float) $item->{OrderItem::COL_KAUF_PREIS} * (int) $item->{OrderItem::COL_AUF_MENGE}
        );

        return [
            'order_info' => [
                Order::COL_ID         => $order->{Order::COL_ID},
                Order::COL_AUF_DAT    => $order->{Order::COL_AUF_DAT},
                Order::COL_AUF_TERMIN => $order->{Order::COL_AUF_TERMIN},
                Order::COL_F_KD_NR    => $order->{Order::COL_F_KD_NR},
                'customer_name'       => $customer?->{Customer::COL_NAME},
                'is_customer_deleted' => $customer?->trashed() ?? false,
            ],
            'items' => $items->map(fn (OrderItem $item) => [
                OrderItem::COL_ID           => $item->{OrderItem::COL_ID},
                OrderItem::COL_F_ARTIKEL_NR => $item->{OrderItem::COL_F_ARTIKEL_NR},
                Product::COL_NAME           => $item->product?->{Product::COL_NAME},
                OrderItem::COL_AUF_MENGE    => $item->{OrderItem::COL_AUF_MENGE},
                OrderItem::COL_KAUF_PREIS   => $item->{OrderItem::COL_KAUF_PREIS},
                'line_total'                => round((float) $item->{OrderItem::COL_KAUF_PREIS} * $item->{OrderItem::COL_AUF_MENGE}, 2),
                'is_discontinued'           => $item->product?->trashed() ?? false,
            ])->values(),
            'order_total' => $orderTotal,
            'preis_total' => round($preisTotal, 2),
        ];
    }

    /**
     * Throws a ValidationException (HTTP 422) when bestand < requested qty.
     * ValidationException is re-thrown by callers so Laravel renders it
     * with the standard errors envelope.
     */
    private function ensureSufficientStock(Product $product, int $requested): void
    {
        if ($product->{Product::COL_BESTAND} < $requested) {
            throw ValidationException::withMessages([
                'items' => sprintf(
                    'Insufficient stock for product %s (%s). Available: %d, requested: %d.',
                    $product->{Product::COL_ID},
                    $product->{Product::COL_NAME},
                    $product->{Product::COL_BESTAND},
                    $requested
                ),
            ]);
        }
    }
}