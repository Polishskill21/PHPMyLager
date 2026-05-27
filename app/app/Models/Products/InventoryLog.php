<?php

namespace App\Models\Products;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Auth\User;

/**
 * Represents a row in the inventory_logs table used for system auditing.
 *
 * @property int                $id          PK — Big integer unique sequence identifier
 * @property int                $fArtikelNr  FK → artikel.pArtikelNr (The impacted product)
 * @property int|null           $user_id     FK → users.id (The staff member responsible, null if system automated)
 * @property int                $old_bestand Stock level snapshot before the manual correction took place
 * @property int                $new_bestand The updated stock target value overridden by the operator
 * @property string             $reason      Explicit human-readable explanation justifying the change
 * @property \Carbon\Carbon     $created_at  Timestamp precisely highlighting when the audit log was committed
 * @property \Carbon\Carbon     $updated_at  Timestamp indicating when this specific log record was last updated
 */

class InventoryLog extends Model
{
    const TABLE            = 'inventory_logs';
    const COL_ID           = 'id';
    const COL_F_ARTIKEL_NR = 'fArtikelNr';
    const COL_USER_ID      = 'user_id';
    const COL_OLD_BESTAND  = 'old_bestand';
    const COL_NEW_BESTAND  = 'new_bestand';
    const COL_REASON       = 'reason';

    protected $table = self::TABLE;

    protected $fillable = [
        self::COL_F_ARTIKEL_NR,
        self::COL_USER_ID,
        self::COL_OLD_BESTAND,
        self::COL_NEW_BESTAND,
        self::COL_REASON,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, self::COL_USER_ID, User::COL_ID);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, self::COL_F_ARTIKEL_NR, Product::COL_ID);
    }
}