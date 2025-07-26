<?php
namespace App\Services;

use App\Models\User;
use App\Models\RefreshToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class AuthService
{
    public function login(string $email, string $password, bool $remember = false): array
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Revoke existing tokens if needed
        $user->tokens()->delete();
        $accessToken = $user->createAccessToken('auth_token', 60);

        // Create refresh token
        $refreshToken = RefreshToken::generateForUser($user);

        return [
            'user' => $user,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken->token,
            'token_type' => 'Bearer',
            'expires_in' => 3600, 
        ];
    }

public function refreshToken(string $refreshToken): array
{
    $tokenRecord = RefreshToken::where('token', $refreshToken)->first();

    if (!$tokenRecord || $tokenRecord->isExpired()) {
        throw ValidationException::withMessages([
            'refresh_token' => ['Invalid or expired refresh token.'],
        ]);
    }

    $user = $tokenRecord->user()->first();

    // Revoke old access tokens
    $user->tokens()->delete();
    $accessToken = $user->createAccessToken('auth_token', 60);
    $newRefreshToken = RefreshToken::generateForUser($user);

    return [
        'user' => $user,
        'access_token' => $accessToken,
        'refresh_token' => $newRefreshToken->token,
        'token_type' => 'Bearer',
        'access_token_expires_in' => 3600, 
        'refresh_token_expires_in' => 2592000, 
    ];
}
    public function logout(User $user, string $refreshToken = null): void
    {
        // Revoke all access tokens
        $user->tokens()->delete();

        // Revoke specific refresh token or all refresh tokens
        if ($refreshToken) {
            RefreshToken::where('user_id', $user->id)
                ->where('token', $refreshToken)
                ->delete();
        } else {
            $user->refreshTokens()->delete();
        }
    }

    public function logoutFromAllDevices(User $user): void
    {
        $user->revokeAllTokens();
    }

    public function createPassword(User $user, string $password): array {
       $user->password = Hash::make($password);
       $user->save();

        return [
            'success' => true
        ];
    }
}