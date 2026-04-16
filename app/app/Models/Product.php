<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


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
