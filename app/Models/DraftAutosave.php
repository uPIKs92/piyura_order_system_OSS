<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DraftAutosave extends Model
{
    protected $fillable = ['user_id', 'draft_data', 'is_restored'];

    protected function casts(): array
    {
        return [
            'draft_data' => 'array',
            'is_restored' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
