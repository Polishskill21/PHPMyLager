<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a row in the artikel (product) table.
 *
 * @property int         $pArtikelNr   PK
 * @property string|null $bezeichnung  Product name/description
 * @property int         $fWgNr        FK → warengruppe.pWgNr
 * @property float|null  $ekPreis      Purchase price  (Einkaufspreis)
 * @property float|null  $vkPreis      Selling price   (Verkaufspreis)
 * @property int|null    $bestand      Current stock quantity
 * @property int|null    $meldeBest    Reorder level   (Meldebestand)
 * @property string|null $lagerplatz   Physical storage location — format: A01-03B
 *                                     [Zone A-Z][Regal 01-99]-[Fach 01-99][Ebene A-E]
 */
class Product extends Model
{
    use SoftDeletes;

    protected $table      = 'artikel';
    protected $primaryKey = 'pArtikelNr';
    public    $timestamps = false;

    protected $hidden = [
        'deleted_at'
    ];

    protected $fillable = [
        'bezeichnung',
        'fWgNr',
        'ekPreis',
        'vkPreis',
        'bestand',
        'meldeBest',
        'lagerplatz',   
    ];

    protected $casts = [
        'ekPreis'   => 'float',
        'vkPreis'   => 'float',
        'bestand'   => 'integer',
        'meldeBest' => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function warengruppe(): BelongsTo
    {
        return $this->belongsTo(WarehouseGroup::class, 'fWgNr', 'pWgNr');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'fArtikelNr', 'pArtikelNr');
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class, 'fArtikelNr', 'pArtikelNr')->latest();
    }
}