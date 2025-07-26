<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Rating extends Model
{
    protected $fillable = [
        'store_id',
        'rating',
        'review',
        'ip',
        'device',
        'meta',
        'footprint'
    ];

    protected $casts = [
        'meta' => 'array',
        'rating' => 'float'
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Stores::class);
    }

    // Scope for getting ratings by footprint
    public function scopeByFootprint($query, string $footprint)
    {
        return $query->where('footprint', $footprint);
    }

    // Scope for getting recent ratings
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}
