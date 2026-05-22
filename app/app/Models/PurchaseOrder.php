<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrder extends Model
{
    protected $table      = 'bestellkoepfe';
    protected $primaryKey = 'pBestNr';

    protected $fillable = [
        'fLiefNr',
        'bestDat',
        'erwLieferDat',
        'status',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'fBestNr', 'pBestNr');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'fLiefNr', 'pLiefNr');
    }
}







