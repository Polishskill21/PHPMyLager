<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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