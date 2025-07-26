<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * 
 *
 * @property int $id
 * @property string $email
 * @property int $code
 * @property \Illuminate\Support\Carbon $expires_at
 * @property string|null $type
 * @property bool|null $is_used
 * @property \Illuminate\Support\Carbon|null $used_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerificationCode newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerificationCode newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerificationCode query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerificationCode whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerificationCode whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerificationCode whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerificationCode whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerificationCode whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerificationCode whereIsUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerificationCode whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerificationCode whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VerificationCode whereUsedAt($value)
 * @mixin \Eloquent
 */
class VerificationCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'code',
        'type',
        'expires_at',
        'is_used',
        'used_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'is_used' => 'boolean'
    ];

    /**
     * Generate a new verification code
     */
    public static function generate(string $email, string $type = 'email_verification', int $expiresInMinutes = 10): self
    {
        // Delete any existing unused codes for this email and type
        self::where('email', $email)
            ->where('type', $type)
            ->where('is_used', false)
            ->delete();

        // Generate new 6-digit code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        return self::create([
            'email' => $email,
            'code' => $code,
            'type' => $type,
            'expires_at' => now()->addMinutes($expiresInMinutes),
        ]);
    }

    /**
     * Verify a code
     */
    public static function verify(string $email, string $code, string $type = 'email_verification'): bool
    {
        $verificationCode = self::where('email', $email)
            ->where('code', $code)
            ->where('type', $type)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->first();

        if ($verificationCode) {
            $verificationCode->update([
                'is_used' => true,
                'used_at' => now()
            ]);
            return true;
        }

        return false;
    }

    /**
     * Check if code exists and is valid
     */
    public static function isValid(string $email, string $code, string $type = 'email_verification'): bool
    {
        return self::where('email', $email)
            ->where('code', $code)
            ->where('type', $type)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Get the latest code for an email and type
     */
    public static function getLatest(string $email, string $type = 'email_verification'): ?self
    {
        return self::where('email', $email)
            ->where('type', $type)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }

    /**
     * Clean up expired codes
     */
    public static function cleanup(): int
    {
        return self::where('expires_at', '<', now())->delete();
    }

    /**
     * Check if code is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Mark code as used
     */
    public function markAsUsed(): bool
    {
        return $this->update([
            'is_used' => true,
            'used_at' => now()
        ]);
    }
}