<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = ['code', 'name', 'is_active', 'opens_drawer', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'opens_drawer' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
