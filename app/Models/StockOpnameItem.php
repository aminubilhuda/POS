<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameItem extends Model
{
    protected $fillable = [
        'stock_opname_id',
        'product_variant_id',
        'system_qty',
        'physical_qty',
        'difference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'system_qty' => 'integer',
            'physical_qty' => 'integer',
            'difference' => 'integer',
        ];
    }

    public function opname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class, 'stock_opname_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
