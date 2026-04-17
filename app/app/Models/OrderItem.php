<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a row in auftragspositionen (order line-item).
 *
 * @property int        $pAufPosNr         PK
 * @property int        $fAufNr            FK → auftragskoepfe.pAufNr
 * @property int        $fArtikelNr        FK → artikel.pArtikelNr
 * @property int        $aufMenge          Ordered quantity
 * @property float|null $kaufPreis Snapshot of vkPreis at sale time
 */
class OrderItem extends Model
{
    protected $table      = 'auftragspositionen';
    protected $primaryKey = 'pAufPosNr';

    public $timestamps = false;

    protected $fillable = [
        'fAufNr',
        'fArtikelNr',
        'aufMenge',
        'kaufPreis',
    ];

    protected $casts = [
        'kaufPreis'   => 'float',
        'aufMenge'    => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'fAufNr', 'pAufNr');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'fArtikelNr', 'pArtikelNr')
                    ->withTrashed();
    }
}