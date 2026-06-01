<?php

namespace App\Models\PurchaseOrders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Suppliers\Supplier;
use App\Enums\PurchaseOrderStatus;

/**
 * Represents a row in the bestellkoepfe (purchase order header) table.
 *
 * @property int                    $pBestNr       PK — Auto-incrementing unique purchase order number
 * @property int|null               $fLiefNr       FK → lieferanten.pLiefNr (null if supplier is deleted)
 * @property string                 $bestDat       The date and time when the order was placed with the supplier
 * @property string|null            $erwLieferDat  The expected delivery date provided by the supplier
 * @property string                 $status        Current workflow state: 'offen', 'bestellt', 'geliefert', 'storniert'
 *
 * Note: the bestellkoepfe table has no created_at/updated_at columns, so timestamps are disabled.
 */

class PurchaseOrder extends Model
{
    const TABLE            = 'bestellkoepfe';
    const COL_ID           = 'pBestNr';
    const COL_STATUS       = 'status';
    const COL_F_LIEF_NR    = 'fLiefNr';
    const COL_BEST_DAT     = 'bestDat';
    const COL_ERW_LIEF_DAT = 'erwLieferDat';

    protected $table      = self::TABLE;
    protected $primaryKey = self::COL_ID;

    // bestellkoepfe has no created_at/updated_at columns (matches the other legacy tables).
    public $timestamps = false;

    protected $fillable = [
        self::COL_F_LIEF_NR,
        self::COL_BEST_DAT,
        self::COL_ERW_LIEF_DAT,
        self::COL_STATUS,
    ];

    protected $casts = [
        // Laravel will now automatically serialize/deserialize the enum
        self::COL_STATUS => PurchaseOrderStatus::class,
    ];


    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, PurchaseOrderItem::COL_F_BEST_NR, self::COL_ID);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, self::COL_F_LIEF_NR, Supplier::COL_ID)->withTrashed();
    }
}







