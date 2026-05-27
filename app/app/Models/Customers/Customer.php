<?php

namespace App\Models\Customers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Orders\Order;

/**
 * Represents a row in kunden (customer).
 *
 * SoftDeletes: same reasoning as Product – a deleted customer must not
 * erase their order history.
 *
 * @property int         $pKdNr
 * @property string      $name
 * @property string      $strasse
 * @property int         $plz
 * @property string      $ort
 * @property string      $email
 * @property string|null $deleted_at
 */
class Customer extends Model

{
    use SoftDeletes;

    protected $table      = 'kunden';
    protected $primaryKey = 'pKdNr';

    public $timestamps = false;

    protected $hidden = [
        'deleted_at'
    ];

    protected $fillable = [
        'name',
        'strasse',
        'plz',
        'ort',
        'email',
    ];

    // ── Relationships ──────────────────────────────────────────────────

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'fKdNr', 'pKdNr');
    }

}
