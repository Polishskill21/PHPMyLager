<?php

namespace App\Models\Products;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\WarehouseGroups\WarehouseGroup;
use App\Models\Orders\OrderItem;

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

    const TABLE          = 'artikel';
    const COL_ID         = 'pArtikelNr';
    const COL_NAME       = 'bezeichnung';
    const COL_WG_ID      = 'fWgNr';
    const COL_EK_PREIS   = 'ekPreis';
    const COL_VK_PREIS   = 'vkPreis';
    const COL_BESTAND    = 'bestand';
    const COL_MELDE_BEST = 'meldeBest';
    const COL_LAGERPLATZ = 'lagerplatz';

    protected $table      = self::TABLE;
    protected $primaryKey = self::COL_ID;

    public    $timestamps = false;

    protected $hidden = [
        'deleted_at',
        'warengruppe'
    ];

    protected $fillable = [
        self::COL_NAME,
        self::COL_WG_ID,
        self::COL_EK_PREIS,
        self::COL_VK_PREIS,
        self::COL_BESTAND,
        self::COL_MELDE_BEST,
        self::COL_LAGERPLATZ,   
    ];

    protected $casts = [
        self::COL_EK_PREIS   => 'float',
        self::COL_VK_PREIS   => 'float',
        self::COL_BESTAND    => 'integer',
        self::COL_MELDE_BEST => 'integer',
    ];

    protected $appends = [
        'has_stock_history',
        'warengruppe_name'
    ];


    // ── Relationships ─────────────────────────────────────────────────────────

    public function warengruppe(): BelongsTo
    {
        return $this->belongsTo(WarehouseGroup::class, self::COL_WG_ID, WarehouseGroup::COL_ID);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, OrderItem::COL_F_ARTIKEL_NR, self::COL_ID);
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class, InventoryLog::COL_F_ARTIKEL_NR, self::COL_ID)->latest();
    }


    public function getHasStockHistoryAttribute(): bool
    {
        if (array_key_exists('inventory_logs_exists', $this->attributes)) {
            return (bool) $this->attributes['inventory_logs_exists'];
        }

        return $this->inventoryLogs()->exists();
    }

    public function getWarengruppeNameAttribute(): ?string
    {
        return $this->warengruppe?->{WarehouseGroup::COL_NAME};
    }
}