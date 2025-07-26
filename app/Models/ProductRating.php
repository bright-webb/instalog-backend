<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductRating extends Model
{
     protected $casts = [
        'meta' => 'array',
        'footprinting' => 'array',
        'liked' => 'boolean'
    ];
    protected $fillable = [
        'products_id',
        'rating',
        'review',
        'ip',
        'fingerprint',
        'device',
        'liked',
        'meta',
        'footprinting'
    ];

    

    public function product(){
        return $this->belongsTo(Products::class, 'products_id');
    }

    public function images(){
        return $this->belongsTo(ProductImage::class, 'products_id');
    }
}
