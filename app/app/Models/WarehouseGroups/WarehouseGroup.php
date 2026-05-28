<?php

namespace App\Models\WarehouseGroups;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Products\Product;
use Dom\Text;

/**
 * Represents a row in the warengruppe (warehouse group) table.
 *
 * @property int         $pWgNr       PK
 * @property string|null $warengruppe Name/description of the warehouse group
 */
class WarehouseGroup extends Model
{
    use HasFactory;

    const TABLE    = 'warengruppe';
    const COL_ID   = 'pWgNr';
    const COL_NAME = 'warengruppe';

    protected $table      = self::TABLE;
    protected $primaryKey = self::COL_ID;

    public $timestamps = false;

    protected $fillable = [
        self::COL_NAME,
    ];


    public function products()
    {
        return $this->hasMany(Product::class, Product::COL_WG_ID, self::COL_ID);
    }
}