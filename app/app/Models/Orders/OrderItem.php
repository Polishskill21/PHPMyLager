<?php

namespace App\Models\Orders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Products\Product;

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
    const TABLE            = 'auftragspositionen';
    const COL_ID           = 'pAufPosNr';
    const COL_F_AUF_NR     = 'fAufNr';
    const COL_F_ARTIKEL_NR = 'fArtikelNr';
    const COL_AUF_MENGE    = 'aufMenge';
    const COL_KAUF_PREIS   = 'kaufPreis';

    protected $table      = self::TABLE;
    protected $primaryKey = self::COL_ID;

    public $timestamps = false;

    protected $fillable = [
        self::COL_F_AUF_NR,
        self::COL_F_ARTIKEL_NR,
        self::COL_AUF_MENGE,
        self::COL_KAUF_PREIS,
    ];

    protected $casts = [
        self::COL_KAUF_PREIS => 'float',
        self::COL_AUF_MENGE  => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, self::COL_F_AUF_NR, Order::COL_ID);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, self::COL_F_ARTIKEL_NR, Product::COL_ID)
                    ->withTrashed();
    }
}