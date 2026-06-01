<?php

namespace App\Models\Suppliers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\PurchaseOrders\PurchaseOrder;

/**
 * Represents a row in the lieferanten (supplier) table.
 *
 * @property int                    $pLiefNr     PK — Auto-incrementing unique supplier entity number
 * @property string                 $name        Company or wholesale vendor title name
 * @property string|null            $strasse     Street name and facility building address details
 * @property int|null               $plz         Postal code identifier (Postleitzahl)
 * @property string|null            $ort         City or region location designation
 * @property string|null            $email       Primary B2B contact email address
 *
 * Note: the lieferanten table has no created_at/updated_at columns (only softDeletes),
 * so timestamps are disabled below.
 */

class Supplier extends Model
{
    use SoftDeletes;

    const TABLE       = 'lieferanten';
    const COL_ID      = 'pLiefNr';
    const COL_NAME    = 'name';
    const COL_STRASSE = 'strasse';
    const COL_PLZ     = 'plz';
    const COL_ORT     = 'ort';
    const COL_EMAIL   = 'email';

    protected $table      = self::TABLE;
    protected $primaryKey = self::COL_ID;

    // lieferanten has no created_at/updated_at columns (matches the other legacy tables).
    public $timestamps = false;

    protected $hidden = [
        'deleted_at'
    ];

    protected $fillable = [
        self::COL_NAME,
        self::COL_STRASSE,
        self::COL_PLZ,
        self::COL_ORT,
        self::COL_EMAIL,
    ];

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, PurchaseOrder::COL_F_LIEF_NR, self::COL_ID);
    }
}
