<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a row in the bestellkoepfe (purchase order header) table.
 *
 * @property int                    $pBestNr       PK — Auto-incrementing unique purchase order number
 * @property int|null               $fLiefNr       FK → lieferanten.pLiefNr (null if supplier is deleted)
 * @property string                 $bestDat       The date and time when the order was placed with the supplier
 * @property string|null            $erwLieferDat  The expected delivery date provided by the supplier
 * @property string                 $status        Current workflow state: 'offen', 'bestellt', 'geliefert', 'storniert'
 * @property \Carbon\Carbon         $created_at    Timestamp when the procurement record was initialized
 * @property \Carbon\Carbon         $updated_at    Timestamp when status or details were last modified
 */

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
        return $this->belongsTo(Supplier::class, 'fLiefNr', 'pLiefNr')->withTrashed();
    }
}







