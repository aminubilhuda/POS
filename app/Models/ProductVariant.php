<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'barcode',
        'cost_price',
        'sell_price',
        'promo_discount',
        'promo_label',
        'min_stock',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'integer',
            'sell_price' => 'integer',
            'promo_discount' => 'integer',
            'min_stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
