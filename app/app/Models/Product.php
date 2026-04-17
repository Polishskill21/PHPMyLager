<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a row in the artikel (product) table.
 *
 * @property int                             $pArtikelNr  PK
 * @property string|null                     $bezeichnung Product name/description
 * @property int                             $fWgNr       FK → warengruppe.pWgNr
 * @property float|null                      $ekPreis     Purchase price (Einkaufspreis)
 * @property float|null                      $vkPreis     Selling price (Verkaufspreis)
 * @property int|null                        $bestand     Current stock quantity
 * @property int|null                        $meldeBest   Reorder level (Meldebestand)
 */

class Product extends Model
{
    use SoftDeletes;
    
    protected $table = 'artikel';
    protected $primaryKey = 'pArtikelNr';
    public $timestamps = false;
    
    protected $fillable = [
        'bezeichnung', 
        'fWgNr', 
        'ekPreis', 
        'vkPreis', 
        'bestand', 
        'meldeBest'
    ];

    protected $casts = [
        'ekPreis'   => 'float',
        'vkPreis'   => 'float',
        'bestand'   => 'integer',
        'meldeBest' => 'integer',
    ];

    public function warengruppe(): BelongsTo
    {
        return $this->belongsTo(WarehouseGroup::class, 'fWgNr', 'pWgNr');
    }
 

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'fArtikelNr', 'pArtikelNr');
    }
}
