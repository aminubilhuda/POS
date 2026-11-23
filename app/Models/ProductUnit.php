<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUnit extends Model
{
    protected $fillable = [
        'product_variant_id',
        'unit_id',
        'conversion_qty',
        'sell_price',
        'barcode',
    ];

    protected function casts(): array
    {
        return [
            'conversion_qty' => 'integer',
            'sell_price' => 'integer',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
