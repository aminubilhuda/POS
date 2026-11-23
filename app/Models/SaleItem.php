<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'product_variant_id',
        'product_name_snapshot',
        'variant_name_snapshot',
        'unit_name_snapshot',
        'quantity',
        'cost_price_snapshot',
        'unit_price_snapshot',
        'discount_amount',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'cost_price_snapshot' => 'integer',
            'unit_price_snapshot' => 'integer',
            'discount_amount' => 'integer',
            'subtotal' => 'integer',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
