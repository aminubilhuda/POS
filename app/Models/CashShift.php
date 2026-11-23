<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashShift extends Model
{
    protected $fillable = [
        'branch_id',
        'user_id',
        'shift_code',
        'status',
        'opening_cash',
        'expected_cash',
        'closing_cash',
        'difference',
        'notes',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'opening_cash' => 'integer',
            'expected_cash' => 'integer',
            'closing_cash' => 'integer',
            'difference' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
