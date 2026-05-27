<?php

namespace App\Models\Products;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Auth\User;

/**
 * Represents a row in the inventory_logs table used for system auditing.
 *
 * @property int                             $id          PK — Big integer unique sequence identifier
 * @property int                             $fArtikelNr  FK → artikel.pArtikelNr (The impacted product)
 * @property int|null                        $user_id     FK → users.id (The staff member responsible, null if system automated)
 * @property int                             $old_bestand Stock level snapshot before the manual correction took place
 * @property int                             $new_bestand The updated stock target value overridden by the operator
 * @property string                          $reason      Explicit human-readable explanation justifying the change
 * @property \Carbon\Carbon                  $created_at  Timestamp precisely highlighting when the audit log was committed
 * @property \Carbon\Carbon                  $updated_at  Timestamp indicating when this specific log record was last updated
 */

class InventoryLog extends Model
{
    protected $table = 'inventory_logs';

    protected $fillable = [
        'fArtikelNr',
        'user_id',
        'old_bestand',
        'new_bestand',
        'reason',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}