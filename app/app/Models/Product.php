<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
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

    // public function warehouseGroup()
    // {
    //     return $this->belongsTo(WarehouseGroup::class, 'fWgNr', 'pWgNr');
    // }

    // public function orderPositions()
    // {
    //     return $this->hasMany(OrderPosition::class, 'fArtikelNr', 'pArtikelNr');
    // }
}
