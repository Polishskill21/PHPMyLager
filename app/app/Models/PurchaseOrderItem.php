<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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