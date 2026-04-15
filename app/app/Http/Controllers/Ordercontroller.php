<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{

    /**
     * Returns all orders, each formatted with their items and totals.
     */
    public function index(): JsonResponse
    {
        $orders = Order::with('items.product')->get();

        return response()->json(
            $orders->map(fn (Order $order) => $this->formatOrder($order))
        );
    }

    /**
     * Returns a single order with all its line-items and calculated totals.
     */
    public function show(Order $order): JsonResponse
    {
        $order->load('items.product');

        return response()->json($this->formatOrder($order));
    }


    /**
     * Creates the order header, attaches all line-items, snapshots each
     * product's current selling price into kaufPreis, and
     * decrements stock – all inside a single transaction.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'aufDat'                 => 'required|date',
            'fKdNr'                  => 'required|integer|exists:kunden,pKdNr',
            'aufTermin'              => 'required|date',
            'items'                  => 'required|array|min:1',
            'items.*.fArtikelNr'     => 'required|integer|exists:artikel,pArtikelNr',
            'items.*.aufMenge'       => 'required|integer|min:1',
        ]);

        $order = DB::transaction(function () use ($validated): Order {
            // Create the order header
            $order = Order::create([
                'aufDat'    => $validated['aufDat'],
                'fKdNr'     => $validated['fKdNr'],
                'aufTermin' => $validated['aufTermin'],
            ]);

            // Process each line-item
            foreach ($validated['items'] as $item) {
                $product = Product::where('pArtikelNr', $item['fArtikelNr'])
                                  ->lockForUpdate()
                                  ->firstOrFail();

                $this->ensureSufficientStock($product, $item['aufMenge']);

                $order->items()->create([
                    'fArtikelNr'        => $product->pArtikelNr,
                    'aufMenge'          => $item['aufMenge'],
                    'kaufPreis' => $product->vkPreis,
                ]);

                $product->decrement('bestand', $item['aufMenge']);
            }

            return $order->load('items.product');
        });

        return response()->json($this->formatOrder($order), 201);
    }


    /**
     * Updates the alreayd existing order, need to pass the whole order with all of
     * items, otherwise it will delete from the order
     */
    public function update(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'aufDat'                  => 'required|date',
            'fKdNr'                   => 'required|integer|exists:kunden,pKdNr',
            'aufTermin'               => 'required|date',
            'items'                   => 'required|array|min:1',
            'items.*.pAufPosNr'       => 'nullable|integer',
            'items.*.fArtikelNr'      => 'required|integer',
            'items.*.aufMenge'        => 'required|integer|min:1',
        ]);

        $order = DB::transaction(function () use ($validated, $order): Order {

            // ─── Update order header ───
            $order->update([
                'aufDat'    => $validated['aufDat'],
                'fKdNr'     => $validated['fKdNr'],
                'aufTermin' => $validated['aufTermin'],
            ]);

            // ─── Load current DB items, keyed by PK ───
            $currentItems   = $order->items->keyBy('pAufPosNr');
            $submittedPosNrs = [];

            // ─── Process submitted items ───
            foreach ($validated['items'] as $index => $submittedItem) {
                $artikelNr = (int) $submittedItem['fArtikelNr'];
                $newMenge  = (int) $submittedItem['aufMenge'];

                if (!empty($submittedItem['pAufPosNr'])) {
                    $posNr = (int) $submittedItem['pAufPosNr'];
                    $submittedPosNrs[] = $posNr;

                    $existingItem = $currentItems->get($posNr);
                    if (!$existingItem) {
                        throw new \Exception(
                            "Order item pAufPosNr={$posNr} does not belong to order {$order->pAufNr}."
                        );
                    }

                    // Guard: changing the product on an existing position
                    // is not allowed – delete it and re-add as a new item
                    if ((int) $existingItem->fArtikelNr !== $artikelNr) {
                        throw new \Exception(
                            "Cannot change fArtikelNr on pAufPosNr={$posNr}. " .
                            "Remove the item and add it again with the new product."
                        );
                    }

                    $diff = $newMenge - (int) $existingItem->aufMenge;

                    if ($diff !== 0) {
                        $product = Product::where('pArtikelNr', $artikelNr)
                                          ->lockForUpdate()
                                          ->firstOrFail();

                        if ($diff > 0) {
                            $this->ensureSufficientStock($product, $diff);
                            $product->decrement('bestand', $diff);
                        } else {
                            $product->increment('bestand', abs($diff));
                        }
                    }

                    $existingItem->update(['aufMenge' => $newMenge]);

                } else {
                    // ─── brand-new line-item ───
                    $product = Product::where('pArtikelNr', $artikelNr)
                                      ->lockForUpdate()
                                      ->firstOrFail();

                    $this->ensureSufficientStock($product, $newMenge);

                    $order->items()->create([
                        'fArtikelNr'        => $artikelNr,
                        'aufMenge'          => $newMenge,
                        'kaufPreis' => $product->vkPreis,
                    ]);

                    $product->decrement('bestand', $newMenge);
                }
            }

            // ─── Delete items that were omitted from the request ────
            foreach ($currentItems as $posNr => $item) {
                if (!in_array($posNr, $submittedPosNrs, true)) {
                    Product::where('pArtikelNr', $item->fArtikelNr)
                           ->increment('bestand', $item->aufMenge);

                    $item->delete();
                }
            }

            return $order->fresh(['items.product']);
        });

        return response()->json($this->formatOrder($order));
    }


    /**
     * Cancels the order:
     *  1. Restores stock for every line-item.
     *  2. Removes all auftragspositionen rows.
     *  3. Removes the auftragskoepfe row.
     *
     * Products and customers are NOT affected (they use soft deletes
     * managed via their own endpoints).
     */
    public function destroy(Order $order): JsonResponse
    {
        DB::transaction(function () use ($order): void {
            $order->load('items');

            foreach ($order->items as $item) {
                Product::where('pArtikelNr', $item->fArtikelNr)
                       ->increment('bestand', $item->aufMenge);
            }

            // Delete positions first to satisfy the FK constraint, then the header
            $order->items()->delete();
            $order->delete();
        });

        return response()->json(null, 204);
    }

    /**
     * Shape:
     * {
     *   "order_info":  { pAufNr, aufDat, aufTermin, fKdNr },
     *   "items":       [ { pAufPosNr, fArtikelNr, bezeichnung, aufMenge, kaufPreis } ],
     *   "order_total": <sum of quantities>,
     *   "preis_total": <sum of kaufPreis × aufMenge>
     * }
     */
    private function formatOrder(Order $order): array
    {
        $items = $order->items;

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
                'pAufPosNr'         => $item->pAufPosNr,
                'fArtikelNr'        => $item->fArtikelNr,
                'bezeichnung'       => $item->product?->bezeichnung,
                'aufMenge'          => $item->aufMenge,
                'kaufPreis' => $item->kaufPreis,
                'line_total'        => round((float) $item->kaufPreis * $item->aufMenge, 2),
            ])->values(),
            'order_total' => $orderTotal,
            'preis_total' => round($preisTotal, 2),
        ];
    }

    /**
     * Throws a ValidationException (HTTP 422) when bestand < requested quantity.
     */
    private function ensureSufficientStock(Product $product, int $requested): void
    {
        if ($product->bestand < $requested) {
            throw ValidationException::withMessages([
                'items' => "Insufficient stock for product {$product->pArtikelNr} " .
                           "({$product->bezeichnung}). " .
                           "Available: {$product->bestand}, requested: {$requested}.",
            ]);
        }
    }
}