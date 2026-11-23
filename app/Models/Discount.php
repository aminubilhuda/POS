<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    protected $fillable = ['branch_id', 'code', 'name', 'type', 'value', 'scope', 'is_active'];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
