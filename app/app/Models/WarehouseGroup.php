<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a row in the warengruppe (warehouse group) table.
 *
 * @property int         $pWgNr       PK
 * @property string|null $warengruppe Name/description of the warehouse group
 */
class WarehouseGroup extends Model
{
    use HasFactory;

    protected $table = 'warengruppe';

    protected $primaryKey = 'pWgNr';

    public $timestamps = false;

    protected $fillable = [
        'warengruppe',
    ];


    public function products()
    {
        return $this->hasMany(Product::class, 'fWgNr', 'pWgNr');
    }
}