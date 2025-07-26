<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreRating extends Model
{
    protected $fillable = [
        'stores_id',
        'rating',
        'review',
        'fingerprint',
        'ip',
        'device',
        'meta'
    ];

    protected $casts = [
        'meta' => 'array'
    ];

    public function store()
    {
        return $this->belongsTo(Stores::class);
    }
}
