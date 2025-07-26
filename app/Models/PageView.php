<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class PageView extends Model
{
    protected $fillable = [
         'url',
        'referrer',
        'user_agent',
        'ip_address',
        'user_id',
        'session_id',
        'page_title',
        'viewport_width',
        'viewport_height',
        'session_duration',
        'viewed_at'
    ];

 protected $casts = [
    'viewed_at' => 'datetime',
    'viewport_width' => 'integer',
    'viewport_height' => 'integer',
    'session_duration' => 'integer'
 ];

 public function user(): BelongsTo {
    return $this->belongsTo(User::class);
 }

 public function scopeForUrl($query, string $url){
    return $query->where('url', $url);
 }

 public function scopeForUser($query, int $userId){
    return $query->where('user_id', $userId);
 }

 public function scopeInDateRange($query, $startDate, $endDate){
    return $query->whereBetween('viewed_at', [$startDate, $endDate]);
 }

 public function scopeUniqueBySession($query){
    return $query->distinct('session_id');
 }
}
