<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class RefreshToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return !$this->isExpired();
    }

    // Generate a new refresh token
    public static function generateForUser(User $user): self
    {
        // Remove old refresh tokens for this user
        static::where('user_id', $user->id)->delete();

        return static::create([
            'user_id' => $user->id,
            'token' => bin2hex(random_bytes(64)),
            'expires_at' => Carbon::now()->addDays(30),
        ]);
    }

    // Clean up expired tokens
    public static function cleanupExpired(): int
    {
        return static::where('expires_at', '<', Carbon::now())->delete();
    }
}
