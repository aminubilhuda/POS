<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    protected $fillable = [
        'branch_id',
        'tax_rate',
        'tax_inclusive',
        'receipt_prefix',
        'printer_name',
        'paper_width',
        'chars_per_line',
        'auto_open_drawer',
        'receipt_header',
        'receipt_footer',
    ];

    protected function casts(): array
    {
        return [
            'tax_rate' => 'integer',
            'tax_inclusive' => 'boolean',
            'paper_width' => 'integer',
            'chars_per_line' => 'integer',
            'auto_open_drawer' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
