<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        $orders = Order::with('items.product')->get()
                       ->map(fn (Order $o) => $this->formatOrder($o));

        return $this->ok($orders);
    }

    /**
     * GET /orders/{order}
     */
    public function show(Order $order): JsonResponse
    {
        $order->load('items.product');

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
                    'aufDat'    => $validated['aufDat'],
                    'fKdNr'     => $validated['fKdNr'],
                    'aufTermin' => $validated['aufTermin'],
                ]);

                foreach ($validated['items'] as $item) {
                    $product = Product::where('pArtikelNr', $item['fArtikelNr'])
                                      ->lockForUpdate()
                                      ->firstOrFail();

                    $this->ensureSufficientStock($product, $item['aufMenge']);

                    $order->items()->create([
                        'fArtikelNr' => $product->pArtikelNr,
                        'aufMenge'   => $item['aufMenge'],
                        'kaufPreis'  => $product->vkPreis,
                    ]);

                    $product->decrement('bestand', $item['aufMenge']);
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
                    'aufDat'    => $validated['aufDat'],
                    'fKdNr'     => $validated['fKdNr'],
                    'aufTermin' => $validated['aufTermin'],
                ]);

                $currentItems    = $order->items->keyBy('pAufPosNr');
                $submittedPosNrs = [];

                foreach ($validated['items'] as $submittedItem) {
                    $artikelNr = (int) $submittedItem['fArtikelNr'];
                    $newMenge  = (int) $submittedItem['aufMenge'];

                    if (!empty($submittedItem['pAufPosNr'])) {
                        // ── Existing line: adjust quantity ──────────────────
                        $posNr             = (int) $submittedItem['pAufPosNr'];
                        $submittedPosNrs[] = $posNr;

                        $existingItem = $currentItems->get($posNr);

                        if (!$existingItem) {
                            // Business-logic error, not a user input error — 422
                            throw ValidationException::withMessages([
                                'items' => "Order item pAufPosNr={$posNr} does not belong to order {$order->pAufNr}.",
                            ]);
                        }

                        if ((int) $existingItem->fArtikelNr !== $artikelNr) {
                            throw ValidationException::withMessages([
                                'items' => "Cannot change fArtikelNr on pAufPosNr={$posNr}. " .
                                           "Remove the item and re-add it with the new product.",
                            ]);
                        }

                        $diff = $newMenge - (int) $existingItem->aufMenge;

                        if ($diff !== 0) {
                            $product = Product::withTrashed()
                                              ->where('pArtikelNr', $artikelNr)
                                              ->lockForUpdate()
                                              ->firstOrFail();

                            if ($product->trashed()) {
                                throw ValidationException::withMessages([
                                    'items' => "Cannot alter the quantity of product {$product->pArtikelNr} ({$product->bezeichnung}) because it has been discontinued.",
                                ]);
                            }

                            if ($diff > 0) {
                                $this->ensureSufficientStock($product, $diff);
                                $product->decrement('bestand', $diff);
                            } else {
                                $product->increment('bestand', abs($diff));
                            }
                        }

                        $existingItem->update(['aufMenge' => $newMenge]);

                    } else {
                        // ── New line ────────────────────────────────────────
                        $product = Product::where('pArtikelNr', $artikelNr)
                                          ->lockForUpdate()
                                          ->firstOrFail();

                        $this->ensureSufficientStock($product, $newMenge);

                        $order->items()->create([
                            'fArtikelNr' => $artikelNr,
                            'aufMenge'   => $newMenge,
                            'kaufPreis'  => $product->vkPreis,
                        ]);

                        $product->decrement('bestand', $newMenge);
                    }
                }

                // ── Remove lines omitted from the request ───────────────────
                foreach ($currentItems as $posNr => $item) {
                    if (!in_array($posNr, $submittedPosNrs, true)) {
                        Product::withTrashed()
                               ->where('pArtikelNr', $item->fArtikelNr)
                               ->increment('bestand', $item->aufMenge);
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
                           ->where('pArtikelNr', $item->fArtikelNr)
                           ->increment('bestand', $item->aufMenge);
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
            'aufDat'              => 'required|date',
            'fKdNr'               => ['required', 'integer', Rule::exists('kunden', 'pKdNr')->whereNull('deleted_at')],
            'aufTermin'           => 'required|date|after_or_equal:aufDat',
            'items'               => 'required|array|min:1',
            'items.*.fArtikelNr'  => [
                'required', 'integer',
                Rule::exists('artikel', 'pArtikelNr')->whereNull('deleted_at'),
            ],

            'items.*.aufMenge'    => 'required|integer|min:1',
        ];
    }

    private function updateRules(): array
    {
        return [
            'aufDat'                  => 'required|date',
            'fKdNr'                   => ['required', 'integer', Rule::exists('kunden', 'pKdNr')->whereNull('deleted_at')],
            'aufTermin'               => 'required|date|after_or_equal:aufDat',
            'items'                   => 'required|array|min:1',
            'items.*.pAufPosNr'       => 'nullable|integer',
            'items.*.fArtikelNr'      => 'required|integer|exists:artikel,pArtikelNr',
            'items.*.aufMenge'        => 'required|integer|min:1',
        ];
    }

    private function customMessages(): array
    {
        return [
            'fKdNr.exists'               => 'The selected customer does not exist.',
            'aufTermin.after_or_equal'   => 'The delivery date must be on or after the order date.',
            'items.required'             => 'At least one order item is required.',
            'items.min'                  => 'At least one order item is required.',
            'items.*.fArtikelNr.exists'  => 'One or more selected products do not exist.',
            'items.*.aufMenge.min'       => 'Each item quantity must be at least 1.',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Response shape:
     * {
     *   "order_info":  { pAufNr, aufDat, aufTermin, fKdNr },
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

        $orderTotal = $items->sum('aufMenge');
        $preisTotal = $items->sum(
            fn (OrderItem $item) => (float) $item->kaufPreis * (int) $item->aufMenge
        );

        return [
            'order_info' => [
                'pAufNr'    => $order->pAufNr,
                'aufDat'    => $order->aufDat,
                'aufTermin' => $order->aufTermin,
                'fKdNr'     => $order->fKdNr,
            ],
            'items' => $items->map(fn (OrderItem $item) => [
                'pAufPosNr'       => $item->pAufPosNr,
                'fArtikelNr'      => $item->fArtikelNr,
                'bezeichnung'     => $item->product?->bezeichnung,
                'aufMenge'        => $item->aufMenge,
                'kaufPreis'       => $item->kaufPreis,
                'line_total'      => round((float) $item->kaufPreis * $item->aufMenge, 2),
                'is_discontinued' => $item->product?->trashed() ?? false,
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
        if ($product->bestand < $requested) {
            throw ValidationException::withMessages([
                'items' => sprintf(
                    'Insufficient stock for product %s (%s). Available: %d, requested: %d.',
                    $product->pArtikelNr,
                    $product->bezeichnung,
                    $product->bestand,
                    $requested
                ),
            ]);
        }
    }
}