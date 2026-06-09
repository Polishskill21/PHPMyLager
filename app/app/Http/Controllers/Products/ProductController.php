<?php

namespace App\Http\Controllers\Products;

use App\Models\Products\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Products\InventoryLog;
use Illuminate\Support\Facades\Auth;
use App\Models\PurchaseOrders\PurchaseOrderItem;
use App\Http\Controllers\Controller;

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
        $history = $product->inventoryLogs()->get();
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
            Product::COL_BESTAND => 'required|integer|min:0',
            InventoryLog::COL_REASON  => 'required|string|min:5|max:255',
        ]);

        try {
            $product = DB::transaction(function () use ($product, $validated) {
                $oldBestand = $product->{Product::COL_BESTAND};

                // 1. Update the stock
                $product->update([
                    Product::COL_BESTAND => $validated[Product::COL_BESTAND]
                ]);
                
                // 2. Create the immutable audit trail row
                InventoryLog::create([
                    InventoryLog::COL_F_ARTIKEL_NR => $product->{Product::COL_ID},
                    InventoryLog::COL_USER_ID      => Auth::id(),
                    InventoryLog::COL_OLD_BESTAND  => $oldBestand,
                    InventoryLog::COL_NEW_BESTAND  => $validated[Product::COL_BESTAND],
                    InventoryLog::COL_REASON       => $validated[InventoryLog::COL_REASON],
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
        $hasOpenPurchaseLines = PurchaseOrderItem::where(PurchaseOrderItem::COL_F_ARTIKEL_NR, $product->{Product::COL_ID})
            ->whereColumn(PurchaseOrderItem::COL_GELIEFERTE_MENGE, '<', PurchaseOrderItem::COL_BEST_MENGE)
            ->exists();
 
        if ($hasOpenPurchaseLines) {
            return $this->conflict(
                'This product cannot be deleted because it has pending quantities on one or more purchase orders.'
            );
        }
 
        try {
            DB::transaction(fn () => $product->delete());
 
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
            Product::COL_NAME       => 'required|string|max:35',
            Product::COL_WG_ID      => 'required|integer|exists:warengruppe,pWgNr',
            Product::COL_EK_PREIS   => 'required|numeric|min:0|max:999999.99',
            Product::COL_VK_PREIS   => 'required|numeric|min:0|max:999999.99',
            Product::COL_BESTAND    => 'required|integer|min:0',
            Product::COL_MELDE_BEST => 'required|integer|min:0',
            Product::COL_LAGERPLATZ => self::LAGERPLATZ_RULE,
        ];
    }

    private function updateRules(): array
    {
        return [
            Product::COL_NAME       => 'sometimes|required|string|max:35',
            Product::COL_WG_ID      => 'sometimes|required|integer|exists:warengruppe,pWgNr',
            Product::COL_EK_PREIS   => 'sometimes|required|numeric|min:0|max:999999.99',
            Product::COL_VK_PREIS   => 'sometimes|required|numeric|min:0|max:999999.99',
            Product::COL_MELDE_BEST => 'sometimes|required|integer|min:0',
            Product::COL_LAGERPLATZ => self::LAGERPLATZ_RULE,
        ];
    }

    /** Human-readable overrides for the generic Laravel messages. */
    private function customMessages(): array
    {
        return [
            Product::COL_WG_ID.'.exists'      => 'The selected warehouse group does not exist.',
            Product::COL_EK_PREIS.'.max'      => 'The purchase price may not exceed 999,999.99.',
            Product::COL_VK_PREIS.'.max'      => 'The selling price may not exceed 999,999.99.',
            Product::COL_LAGERPLATZ.'.regex'  => self::LAGERPLATZ_MSG,
        ];
    }
}