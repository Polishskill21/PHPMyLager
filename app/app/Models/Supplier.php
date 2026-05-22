<?php

// ─── app/Models/Supplier.php ──────────────────────────────────────────────────

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
