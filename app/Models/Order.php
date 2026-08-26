<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'merchant_id' => 'integer',
            'salla_id' => 'integer',
            'reference_id' => 'integer',
            'customer_id' => 'integer',
            'total' => 'decimal:2',
            'ordered_at' => 'datetime',
            'payload' => 'array',
            'synced_at' => 'datetime',
        ];
    }
}