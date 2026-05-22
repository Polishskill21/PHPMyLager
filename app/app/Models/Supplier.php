<?php

// ─── app/Models/Supplier.php ──────────────────────────────────────────────────

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a row in the lieferanten (supplier) table.
 *
 * @property int                    $pLiefNr     PK — Auto-incrementing unique supplier entity number
 * @property string                 $name        Company or wholesale vendor title name
 * @property string|null            $strasse     Street name and facility building address details
 * @property int|null               $plz         Postal code identifier (Postleitzahl)
 * @property string|null            $ort         City or region location designation
 * @property string|null            $email       Primary B2B contact email address
 * @property string|null            $telefon     Corporate phone interaction details string
 * @property \Carbon\Carbon         $created_at  Timestamp when the supplier was registered in the database
 * @property \Carbon\Carbon         $updated_at  Timestamp when supplier records were last altered
 */

class Supplier extends Model
{
    protected $table      = 'lieferanten';
    protected $primaryKey = 'pLiefNr';

    protected $fillable = [
        'name',
        'strasse',
        'plz',
        'ort',
        'email',
        'telefon',
    ];

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'fLiefNr', 'pLiefNr');
    }
}
