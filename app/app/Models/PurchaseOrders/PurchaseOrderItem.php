<?php

namespace App\Models\PurchaseOrders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Products\Product;

/**
 * Represents a row in the bestellpositionen (purchase order line items) table.
 *
 * @property int            $pBestPosNr       PK — Unique auto-incrementing line item identifier
 * @property int            $fBestNr          FK → bestellkoepfe.pBestNr
 * @property int            $fArtikelNr       FK → artikel.pArtikelNr (accessible even if soft-deleted)
 * @property int            $bestMenge        The quantity of items originally requested from the supplier
 * @property int            $gelieferteMenge  The quantity of items received at the warehouse (supports partial delivery)
 * @property float|null     $ekPreis          The agreed purchase price (Einkaufspreis) for this batch item row
 */

class PurchaseOrderItem extends Model
{
    const TABLE                  = 'bestellpositionen';
    const COL_ID                 = 'pBestPosNr';
    const COL_F_BEST_NR          = 'fBestNr';
    const COL_F_ARTIKEL_NR       = 'fArtikelNr';
    const COL_BEST_MENGE         = 'bestMenge';
    const COL_GELIEFERTE_MENGE   = 'gelieferteMenge';
    const COL_EK_PREIS           = 'ekPreis';

    protected $table      = self::TABLE;
    protected $primaryKey = self::COL_ID;
    public    $timestamps = false;

    protected $fillable = [
        self::COL_F_BEST_NR,
        self::COL_F_ARTIKEL_NR,
        self::COL_BEST_MENGE,
        self::COL_GELIEFERTE_MENGE,
        self::COL_EK_PREIS,
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, self::COL_F_BEST_NR, PurchaseOrder::COL_ID);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, self::COL_F_ARTIKEL_NR, Product::COL_ID)
                    ->withTrashed(); // show even soft-deleted products
    }
}