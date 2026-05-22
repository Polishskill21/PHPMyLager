<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder; 
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    // READ
    // ──────────────────────────────────────────────────────────────────────────

    /** GET /purchase-orders */
    public function index(): JsonResponse
    {
        $orders = PurchaseOrder::with('items.product', 'supplier')->get();

        return response()->json(
            $orders->map(fn (PurchaseOrder $o) => $this->formatOrder($o))
        );
    }

    /** GET /purchase-orders/{order} */
    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->load('items.product', 'supplier');

        return response()->json($this->formatOrder($purchaseOrder));
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
        $validated = $request->validate([
            'fLiefNr'           => 'nullable|integer|exists:lieferanten,pLiefNr',
            'bestDat'           => 'required|date',
            'erwLieferDat'      => 'nullable|date|after_or_equal:bestDat',
            'items'             => 'required|array|min:1',
            'items.*.fArtikelNr'=> 'required|integer|exists:artikel,pArtikelNr',
            'items.*.bestMenge' => 'required|integer|min:1',
            'items.*.ekPreis'   => 'nullable|numeric|min:0',
        ]);

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

        return response()->json($this->formatOrder($order), 201);
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
    public function update(Request $request, PurchaseOrder $purchaseOrder): JsonResponse {
        if (in_array($purchaseOrder->status, ['geliefert', 'storniert'], true)) {
            return response()->json(['error' => 'Closed orders cannot be edited.'], 422);
        }

        $validated = $request->validate([
            'fLiefNr'           => 'nullable|integer|exists:lieferanten,pLiefNr',
            'bestDat'           => 'required|date',
            'erwLieferDat'      => 'nullable|date|after_or_equal:bestDat',
            'items'             => 'required|array|min:1',
            'items.*.pBestPosNr'=> 'nullable|integer',
            'items.*.fArtikelNr'=> 'required|integer|exists:artikel,pArtikelNr',
            'items.*.bestMenge' => 'required|integer|min:1',
            'items.*.ekPreis'   => 'nullable|numeric|min:0',
        ]);

        $purchaseOrder = DB::transaction(function () use ($validated, $purchaseOrder): PurchaseOrder {
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
                    $line->delete();
                }
            }

            return $purchaseOrder->fresh(['items.product', 'supplier']);
        });

        return response()->json($this->formatOrder($purchaseOrder));
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
            return response()->json(['error' => 'Cannot receive a cancelled order.'], 422);
        }
        if ($purchaseOrder->status === 'geliefert') {
            return response()->json(['error' => 'Order already fully received.'], 422);
        }

        $validated = $request->validate([
            'items'                       => 'required|array|min:1',
            'items.*.pBestPosNr'          => 'required|integer',
            'items.*.gelieferteMenge'     => 'required|integer|min:1',
        ]);

        $purchaseOrder = DB::transaction(function () use ($validated, $purchaseOrder): PurchaseOrder {
            $lines = $purchaseOrder->items->keyBy('pBestPosNr');

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

                // Increment stock
                Product::where('pArtikelNr', $line->fArtikelNr)
                       ->lockForUpdate()
                       ->firstOrFail()
                       ->increment('bestand', $newQty);

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

        return response()->json($this->formatOrder($purchaseOrder));
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
            return response()->json(['error' => 'Only open or ordered purchases can be cancelled.'], 422);
        }

        $purchaseOrder->update(['status' => 'storniert']);

        return response()->json(['message' => "Purchase order {$purchaseOrder->pBestNr} cancelled."]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HELPER
    // ──────────────────────────────────────────────────────────────────────────

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