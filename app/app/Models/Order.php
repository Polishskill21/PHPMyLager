<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    protected $table      = 'auftragskoepfe';
    protected $primaryKey = 'pAufNr';

    public $timestamps = false;

    protected $fillable = [
        'aufDat',
        'fKdNr',
        'aufTermin',
    ];


    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'fAufNr', 'pAufNr');
    }

    // public function customer(): BelongsTo
    // {
    //     return $this->belongsTo(Customer::class, 'fKdNr', 'pKdNr');
    // }
}