<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    protected $fillable = [
        'branch_id',
        'sale_id',
        'user_id',
        'authorized_by',
        'return_number',
        'total_refund',
        'reason',
        'returned_at',
    ];

    protected function casts(): array
    {
        return [
            'total_refund' => 'integer',
            'returned_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }
}
