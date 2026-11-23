<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'unit_id',
        'name',
        'description',
        'is_bundle',
        'thumb_code',
        'thumb_color',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_bundle' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function bundleItems(): HasMany
    {
        return $this->hasMany(BundleItem::class, 'bundle_product_id');
    }
}
