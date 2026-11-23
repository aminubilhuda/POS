<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'client_uuid',
        'branch_id',
        'cash_shift_id',
        'user_id',
        'customer_id',
        'invoice_number',
        'status',
        'subtotal',
        'item_discount_total',
        'trx_discount_type',
        'trx_discount_value',
        'trx_discount_amount',
        'tax_rate',
        'tax_amount',
        'grand_total',
        'paid_total',
        'change_amount',
        'voided_by',
        'void_reason',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'item_discount_total' => 'integer',
            'trx_discount_value' => 'integer',
            'trx_discount_amount' => 'integer',
            'tax_rate' => 'integer',
            'tax_amount' => 'integer',
            'grand_total' => 'integer',
            'paid_total' => 'integer',
            'change_amount' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class, 'cash_shift_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }
}
