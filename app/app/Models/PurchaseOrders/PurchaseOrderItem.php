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
    protected $table      = 'bestellpositionen';
    protected $primaryKey = 'pBestPosNr';
    public    $timestamps = false;

    protected $fillable = [
        'fBestNr',
        'fArtikelNr',
        'bestMenge',
        'gelieferteMenge',
        'ekPreis',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'fBestNr', 'pBestNr');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'fArtikelNr', 'pArtikelNr')
                    ->withTrashed(); // show even soft-deleted products
    }
}