<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\InventoryLog;
use Illuminate\Support\Facades\Auth;
use App\Models\PurchaseOrderItem;

class ProductController extends Controller
{
    /**
     * Lagerplatz format: A01-03B
     *   [A-Z]   – Zone         (one uppercase letter)
     *   [0-9]{2} – Regal       (shelf unit, zero-padded, e.g. 01–99)
     *   -        – separator
     *   [0-9]{2} – Fach        (bay/compartment, zero-padded, e.g. 01–99)
     *   [A-E]    – Ebene       (level A = floor … E = top shelf)
     *
     * Valid examples : A01-03B, B12-04C, Z99-99E
     * Invalid examples: a01-03b (lowercase), A1-03B (missing leading zero), A01-3B
     */
    private const LAGERPLATZ_REGEX  = '/^[A-Z]\d{2}-\d{2}[A-E]$/';
    private const LAGERPLATZ_RULE   = 'nullable|string|regex:' . self::LAGERPLATZ_REGEX;
    private const LAGERPLATZ_MSG    = 'The lagerplatz format must be A01-03B — Zone(A-Z), Regal(01-99), Fach(01-99), Ebene(A-E).';

    // ─────────────────────────────────────────────────────────────────────────
    // READ
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /products
     * Returns all non-deleted products.
     */
    public function index(): JsonResponse
    {
        return $this->ok(Product::all(), 'Products retrieved successfully.');
    }

    /**
     * GET /products/{product}
     * Returns a single product by its primary key.
     */
    public function show(Product $product): JsonResponse
    {
        return $this->ok($product);
    }

    /** 
     * GET /products/{product}/stock-history 
     */
    public function stockHistory(Product $product): JsonResponse
    {
        $history = $product->inventoryLogs()->with('user:id,name')->get();
        return $this->ok($history);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREATE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * POST /products
     * All fields required on product creation.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(
            $this->storeRules(),
            $this->customMessages()
        );

        try {
            $product = DB::transaction(fn () => Product::create($validated));

            return $this->created($product, 'Product created successfully.');
        } catch (\Exception $e) {
            report($e);
            return $this->serverError();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UPDATE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * PUT /products/{product}
     * All fields required on a full update; PATCH-style partial updates are
     * handled by the 'sometimes' qualifier.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate(
            $this->updateRules(),
            $this->customMessages()
        );

        try {
            $product = DB::transaction(function () use ($product, $validated) {
                $product->update($validated);
                return $product->fresh();
            });

            return $this->ok($product, 'Product updated successfully.');
        } catch (\Exception $e) {
            report($e);
            return $this->serverError();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PATCH
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * PATCH /products/{product}/adjust-stock
     * Explicit stock adjustment endpoint requiring an intentional reason.
     */
    public function adjustStock(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'bestand' => 'required|integer|min:0',
            'reason'  => 'required|string|min:5|max:255',
        ]);

        try {
            $product = DB::transaction(function () use ($product, $validated) {
                $oldBestand = $product->bestand;

                // 1. Update the stock
                $product->update([
                    'bestand' => $validated['bestand']
                ]);
                
                // 2. Create the immutable audit trail row
                InventoryLog::create([
                    'fArtikelNr'  => $product->pArtikelNr,
                    'user_id'     => Auth::id(),
                    'old_bestand' => $oldBestand,
                    'new_bestand' => $validated['bestand'],
                    'reason'      => $validated['reason'],
                ]);

                return $product->fresh();
            });

            return $this->ok($product, 'Product stock level manually adjusted successfully.');
        } catch (\Exception $e) {
            report($e);
            return $this->serverError();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * DELETE /products/{product}
     *
     * Soft-deletes the product. Returns 409 if it is still referenced by
     * an open purchase order lines.
     * 
     */
    public function destroy(Product $product): JsonResponse
    {
        // Block if referenced by any purchase order line that has not yet been
        // fully received (gelieferteMenge < bestMenge). Fully delivered lines
        // are historical records and do not block deletion.
        $hasOpenPurchaseLines = PurchaseOrderItem::where('fArtikelNr', $product->pArtikelNr)
            ->whereColumn('gelieferteMenge', '<', 'bestMenge')
            ->exists();
 
        if ($hasOpenPurchaseLines) {
            return $this->conflict(
                'This product cannot be deleted because it has pending quantities on one or more purchase orders.'
            );
        }
 
        try {
            $id = $product->pArtikelNr;
            DB::transaction(fn () => $product->delete());
 
            return $this->ok(null, "Product {$id} deleted successfully.");
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
            'bezeichnung' => 'required|string|max:35',
            'fWgNr'       => 'required|integer|exists:warengruppe,pWgNr',
            'ekPreis'     => 'required|numeric|min:0|max:999999.99',
            'vkPreis'     => 'required|numeric|min:0|max:999999.99',
            'bestand'     => 'required|integer|min:0',
            'meldeBest'   => 'required|integer|min:0',
            'lagerplatz'  => self::LAGERPLATZ_RULE,
        ];
    }

    private function updateRules(): array
    {
        return [
            'bezeichnung' => 'sometimes|required|string|max:35',
            'fWgNr'       => 'sometimes|required|integer|exists:warengruppe,pWgNr',
            'ekPreis'     => 'sometimes|required|numeric|min:0|max:999999.99',
            'vkPreis'     => 'sometimes|required|numeric|min:0|max:999999.99',
            'meldeBest'   => 'sometimes|required|integer|min:0',
            'lagerplatz'  => self::LAGERPLATZ_RULE,
        ];
    }

    /** Human-readable overrides for the generic Laravel messages. */
    private function customMessages(): array
    {
        return [
            'fWgNr.exists'         => 'The selected warehouse group does not exist.',
            'ekPreis.max'          => 'The purchase price may not exceed 999,999.99.',
            'vkPreis.max'          => 'The selling price may not exceed 999,999.99.',
            'lagerplatz.regex'     => self::LAGERPLATZ_MSG,
        ];
    }
}