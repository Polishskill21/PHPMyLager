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
 * @property string|null $plz
 * @property string      $ort
 * @property string      $email
 * @property string|null $deleted_at
 */
class Customer extends Model

{
    use SoftDeletes;

    const TABLE       = 'kunden';
    const COL_ID      = 'pKdNr';
    const COL_NAME    = 'name';
    const COL_STRASSE = 'strasse';
    const COL_PLZ     = 'plz';
    const COL_ORT     = 'ort';
    const COL_EMAIL   = 'email';

    protected $table      = self::TABLE;
    protected $primaryKey = self::COL_ID;
    
    public $timestamps = false;

    protected $casts = [
        self::COL_PLZ => 'string',
    ];

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

    // ── Relationships ──────────────────────────────────────────────────

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, Order::COL_F_KD_NR, self::COL_ID);
    }

}
