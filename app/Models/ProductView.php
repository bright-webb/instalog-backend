<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductView extends Model
{
     protected $fillable = [
        'products_id',
        'fingerprint',
        'ip',
        'device',
        'meta'
    ];

    protected $casts = [
        'meta' => 'array'
    ];
    
    public function product(): BelongsTo{
        return $this->belongsTo(Products::class, 'products_id');
    }

    public function images(){
        return $this->belongsTo(ProductImage::class, 'products_id');
    }
}
