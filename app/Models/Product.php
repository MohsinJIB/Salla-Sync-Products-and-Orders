<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = [
    'id',
    'created_at',
    'updated_at',
];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'quantity' => 'integer',
            'is_available' => 'boolean',
            'payload' => 'array',
            'synced_at' => 'datetime',
        ];
    }
}