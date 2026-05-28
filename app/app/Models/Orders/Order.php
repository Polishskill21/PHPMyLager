<?php

namespace App\Models\Orders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Customers\Customer;

/**
 * Represents a row in auftragskoepfe (order header).
 *
 * @property int         $pAufNr
 * @property string      $aufDat      Order date
 * @property string      $aufTermin   Delivery deadline
 * @property int         $fKdNr       FK → kunden.pKdNr
 */
class Order extends Model
{
    const TABLE          = 'auftragskoepfe';
    const COL_ID         = 'pAufNr';
    const COL_AUF_DAT    = 'aufDat';
    const COL_F_KD_NR    = 'fKdNr';
    const COL_AUF_TERMIN = 'aufTermin';

    protected $table      = self::TABLE;
    protected $primaryKey = self::COL_ID;
    
    public $timestamps = false;

    protected $fillable = [
        self::COL_AUF_DAT,
        self::COL_F_KD_NR,
        self::COL_AUF_TERMIN,
    ];


    // ── Relationships ──────────────────────────────────────────────────
    
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, OrderItem::COL_F_AUF_NR, self::COL_ID);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, self::COL_F_KD_NR, Customer::COL_ID)->withTrashed();
    }
}
