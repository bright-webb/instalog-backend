<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Mail;
use App\Mail\EmailVerification;
use App\Models\VerificationCode;
use App\Models\Products;
use App\Models\Stores;
use App\Services\LocationService;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Exception;

class AuthController extends Controller
{

    protected AuthService $authService; 
    public function __construct(AuthService $authService){
        $this->authService = $authService;
    }
    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'email' => $request->email,
        ]);

        $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        VerificationCode::create([
            'email' => $request->email,
            'code' => $verificationCode,
            'expires_at' => now()->addMinutes(10),
            'type' => 'email_verification'
        ]);

        Mail::to($user->email)->send(new EmailVerification($user, $verificationCode));

        // $token = $user->createToken('auth_token')->plainTextToken;
        // Insert location data
        $geo = new LocationService;
        $ip = get_client_ip();
        $locationData = $geo->getLocation($ip);
        if ($locationData) {
        \App\Models\Location::create([
            'user_id' => $user->id,
            'country' => $geo->extractLocationField($locationData, 'country'),
            'region' => $geo->extractLocationField($locationData, 'region'),
            'city' => $geo->extractLocationField($locationData, 'city'),
            'ip_address' => $ip,
        ]);
    }


        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'user' => $user,
                // 'token' => $token,
                'email_sent' => true
                // 'token_type' => 'Bearer'
            ]
        ], 201);
    }

    public function verifyEmail(Request $request)
{
    $validator = Validator::make($request->all(), [
        'email' => 'required|email|exists:users,email',
        'code' => 'required|string|size:6',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation errors',
            'errors' => $validator->errors()
        ], 422);
    }

    $email = $request->email;
    $code = $request->code;

    // Check if user exists and is not already verified
    $user = User::where('email', $email)->first();
    
    if ($user->email_verified_at) {
        return response()->json([
            'success' => false,
            'message' => 'Email is already verified'
        ], 400);
    }

    // Verify the code
    if (VerificationCode::verify($email, $code)) {
        // Mark user as verified
        User::where('email', $email)->update(['email_verified_at' => now()]);

        // Generate access token (shorter expiry)
        $accessToken = $user->createToken('access_token', ['*'], now()->addDays(30))->plainTextToken;
        
        // Generate refresh token (longer expiry)
        $refreshToken = $user->createToken('refresh_token', ['refresh'], now()->addDays(90))->plainTextToken;
        
        $secure = app()->environment('production');
        
        // Set access token cookie
        $accessCookie = cookie(
            'access_token',
            $accessToken,
            60 * 24 * 30,
            '/',
            null,
            $secure,  
            true, 
            false,
            'Lax'
        );
        
        // Set refresh token cookie
        $refreshCookie = cookie(
            'refresh_token',
            $refreshToken,
            60 * 24 * 30,
            '/',
            null,
            $secure,  
            true, 
            false,
            'Lax'
        );
        
        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully',
            'data' => [
                'user' => $user,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'access_token_expires_in' => 15 * 60,
                'refresh_token_expires_in' => 30 * 24 * 60 * 60,
            ]
        ])->withCookie($accessCookie)->withCookie($refreshCookie);
    }

    return response()->json([
        'success' => false,
        'message' => 'Invalid or expired verification code'
    ], 400);
}

    /**
     * Verify email redirected from verification link
     */

    public function verifyWebEmail(Request $request){
        $frontendUrl = env('FRONTEND_URL');
       
        if(!isset($_GET['code']) && !isset($_GET['email'])){
            return redirect()->away("{$frontendUrl}/error?message=Something went wrong");
        }

        $email = $request->get('email');
        $code = $request->get('code');

        // Check if user exists and is not already verified
        $user = User::where('email', $email)->first();
        
        if ($user->email_verified_at) {
            return redirect()->away("{$frontendUrl}/home?ref=email verification");
        }

        // Check if code has expired
        $verification = VerificationCode::where('email', $email)
            ->where('code', $code)
            ->where('type', 'email_verification')
            ->first();

        if (!$verification || $verification->expires_at < now()) {
            $frontendUrl = env('FRONTEND_URL');
            return redirect()->away("{$frontendUrl}/error?message=Verification code is invalid or expired");
        }

        if (VerificationCode::verify($email, $code)) {
            // Mark user as verified
            $user->update([
                'email_verified_at' => now()
            ]);

            // Generate auth token
            $token = $user->createToken('auth_token')->plainTextToken;

            return redirect()->away("{$frontendUrl}/email/verification?u={$user->id}&session={$token}");
        }

        
    }

    /**
     * Resend verification code
     */
    public function resendVerificationCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if ($user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Email is already verified'
            ], 400);
        }

        // Check if there's a recent code (prevent spam)
        $recentCode = VerificationCode::where('email', $request->email)
            ->where('type', 'email_verification')
            ->where('created_at', '>', now()->subMinutes(1))
            ->first();

        if ($recentCode) {
            return response()->json([
                'success' => false,
                'message' => 'Please wait before requesting a new code',
                'wait_time' => 60 - now()->diffInSeconds($recentCode->created_at)
            ], 429);
        }
        $verificationCode = VerificationCode::generate($user->email);

        // Send verification email
        try {
            Mail::to($user->email)->send(new EmailVerification($user, $verificationCode->code));
            
            return response()->json([
                'success' => true,
                'message' => 'Verification code sent successfully',
                'data' => [
                    'expires_at' => $verificationCode->expires_at
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification email'
            ], 500);
        }
    }

    /**
     * Login user 
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Check if email is verified
        if (!$user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email before logging in',
                'requires_verification' => true,
                'user' => [
                        'email' => $user->email
                ]
            ], 403);
        }
        $user->tokens()->delete();

        $accessToken = $user->createToken('auth_token', ['*'], now()->addDays(30))->plainTextToken;
        $refreshToken = $user->createToken('refresh_token', ['*'], now()->addDays(90))->plainTextToken;
        $secure = app()->environment('production');

        // Check if user has reached max products limit for freemium
        $productsLeft = 5;
        if(!$user->premium) {
           $storeData = DB::table('stores')
            ->leftJoin('products', 'stores.id', '=', 'products.stores_id')
            ->where('stores.user_id', $user->id)
            ->select([
                'stores.id as store_id',
                'stores.user_id',
                DB::raw('COUNT(products.id) as product_count'),
                DB::raw('GREATEST(0, COUNT(products.id) - 5) as products_over_limit')
            ])
            ->groupBy('stores.id', 'stores.user_id')
            ->first();

        $productsLeft = $storeData ? max(0, 5 - $storeData->product_count) : 5;
        }

         $accessCookie = cookie(
            'access_token',
            $accessToken,
            60 * 24 * 30,
            '/',
            null,
            $secure,  
            true, 
            false,
            'Lax'
        );
        
        // Set refresh token cookie
        $refreshCookie = cookie(
            'refresh_token',
            $refreshToken,
            60 * 24 * 30,
            '/',
            null,
            $secure,  
            true, 
            false,
            'Lax'
        );

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'access_token_expires_in' => 30 * 24 * 30,
                'refresh_token_expires_in' => 30 * 24 * 60 * 60,
                'free_product_remaining' => $productsLeft
            ]
        ])->withCookie($accessCookie)->withCookie($refreshCookie);
    }

    /**
     * Check verification status
     */
    public function checkVerificationStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();
        $latestCode = VerificationCode::getLatest($request->email);

        return response()->json([
            'success' => true,
            'data' => [
                'is_verified' => !is_null($user->email_verified_at),
                'verified_at' => $user->email_verified_at,
                'has_pending_code' => !is_null($latestCode),
                'code_expires_at' => $latestCode?->expires_at
            ]
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Get user profile
     */
    public function profile(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $request->user()->load('stores')
            ]
        ]);
    }

    /**
     * Logout from all devices
     */
    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out from all devices successfully'
        ]);
    }

    public function refreshToken(Request $request)
{
    $validator = Validator::make($request->all(), [
        'refresh_token' => 'required|string',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation errors',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        $result = $this->authService->refreshToken($request->refresh_token);
        
        $secure = app()->environment('production');
        
        // Set new access token cookie
        $accessCookie = cookie(
            'access_token',
            $result['access_token'],
            60 * 24,
            '/',
            null,
            $secure,
            true,
            false,
            'Lax'
        );
    
        $refreshCookie = cookie(
            'refresh_token',
            $result['refresh_token'],
            60 * 24 * 30,
            '/',
            null,
            $secure,
            true,
            false,
            'Lax'
        );

        return response()->json([
            'success' => true,
            'message' => 'Tokens refreshed successfully',
            'data' => [
                'user' => $result['user'],
                'access_token' => $result['access_token'],
                'refresh_token' => $result['refresh_token'],
                'token_type' => $result['token_type'],
                'access_token_expires_in' => $result['access_token_expires_in'],
                'refresh_token_expires_in' => $result['refresh_token_expires_in'],
            ]
        ])->withCookie($accessCookie)->withCookie($refreshCookie);

    } catch (ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid refresh token',
            'errors' => $e->errors()
        ], 401);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Token refresh failed',
            'error' => $e->getMessage()
        ], 500);
    }
}

// Create password
public function createPassword(Request $request)
{
    $user = $request->user();

    $validator = Validator::make($request->all(), [
        'password' => 'required|min:8'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    try{
        $validated = $validator->validated();
        $response = $this->authService->createPassword($user, $validated['password']);

        return response()->json($response);
    } catch(Exception $e){
        return response()->json([
            'success' => false,
            'message' => 'Something went wrong',
            'error' => $e->getMessage()
        ]);
    }
}

}