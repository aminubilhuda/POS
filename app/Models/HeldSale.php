<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeldSale extends Model
{
    protected $fillable = [
        'branch_id',
        'user_id',
        'customer_id',
        'hold_code',
        'payload',
        'total_items',
        'estimated_total',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'total_items' => 'integer',
            'estimated_total' => 'integer',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
