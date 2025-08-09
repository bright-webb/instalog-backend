<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Str;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class PaymentController extends Controller
{
    private $client;
    public function __construct(Client $client) {
        $this->client = $client;
    }


   public function getPaymentPlan(Request $request)
{
    try {
        $user = auth()->user();
        $cacheKey = 'user_plan_' . $user->id . '_' . $user->updated_at->timestamp;

        $plan = cache()->remember($cacheKey, now()->addHours(1), function() use ($user) {
            $planData = DB::table('plans')->where('user_id', $user->id)->first();
            
            if ($planData) {
                $planData->isPremium = (bool) DB::table('users')
                    ->where('id', $user->id)
                    ->value('is_premium');

                $planData->name = $planData->isPremium ? 'Premium Plan' : 'Free Plan';
                $planData->email = $user->email;
            }
            
            return $planData;
        });

        if ($plan && $plan->is_active) {
            return response()->json([
                'success' => true,
                'message' => 'User has an active plan',
                'plan' => $plan
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No active plan found'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error retrieving payment plan',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function createPlan(Request $request)
{
    $request->validate([
        'name' => 'required|string',
        'amount' => 'required|numeric',
        'interval' => 'required|in:daily,weekly,monthly,yearly',
    ]);

    $user = auth()->user();
    
    try {
      
        $data = [
                'user_id' => $user->id,
                'name' => $request->name,
                'plan_id' => $request->plan_id,
                'amount' => $request->amount,
                'interval' => $request->interval,
                'integration' => $request->integration,
                'plan_code' => $request->plan_id,
                'created_at' => now(),
                'updated_at' => now()
            ];
            
        $query = DB::table('plans')->insert($data);
        if ($query) {
           
           

            return response()->json([
                'success' => true,
                'message' => 'Plan created successfully',
                'data' => [
                    'name' => $request->input('name'),
                    'amount' => $request->input('amount'),
                    'interval' => $request->input('interval'),
                ]
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to create plan: ' . $response->json()['message']
        ], 400);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'An error occurred: ' . $e->getMessage()
        ], 500);
    }
}

public function verifyPayment(Request $request){
    $amount = $request->amount;
    $ref = $request->tx_ref;
    $txID = $request->transaction_id;
    
    $user = auth()->user();
    
    $data = [
        'user_id' => $user->id,
        'transaction_id' => $txID,
        'amount' => $amount,
        'currency' => 'NGN',
        'status' => 'completed',
        'paid_at' => now(),
        'metadata' => json_encode([
            'plan' => 'Pro Plan',
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'created_at' => now(),
            'updated_at' => now()
        ]),
        'reference_id' => $ref,
        'created_at' => now(),
        'updated_at' => now()
    ];
    
    $query = DB::table('subscription_payments')->insert($data);
    if($query){
        $this->activateSubscription($user, $request->payment_type, $amount, $request);
        return response()->json([
            'success' => true,
            'message' => 'Payment successful',
            'data' => $data
        ], 201);
    } else {
        return response()->json([
            'success' => false,
            'message' => 'Failed to create payment record'
        ], 500);
    }
}

// Create bank transfer payment record
public function createTransferPayment(Request $request){
    $user = auth()->user();
    $amount = $request->input('amount');
    $plan = $request->input('plan');

    $referenceId = Str::random(32);

    $data = [
        'user_id' => $user->id,
        'amount' => $amount,
        'currency' => 'NGN',
        'status' => 'pending',
        'paid_at' => null,
        'metadata' => json_encode([
            'plan' => $plan,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'created_at' => now(),
            'updated_at' => now()
        ]),
        'reference_id' => $referenceId,
        'created_at' => now(),
        'updated_at' => now()
    ];

    $query = DB::table('subscription_payments')->insert($data);
    if($query){
        return response()->json([
            'success' => true,
            'message' => 'Payment record created successfully',
            'data' => $data,
        ]);
    } else {
        return response()->json([
            'success' => false,
            'message' => 'Failed to create payment record'
        ], 500);
    }
}


// Activate subscriptions
private function activateSubscription($user, $payment_type, $amount, Request $request) {
    $data = [
        'user_id' => $user->id,
        'plan' => 'pro plan',
        'status' => 'active',
        'starts_at' => now(),
        'ends_at' => now()->addDays(30),
        'current_period_ends_at' => now()->addDays(30),
        'payment_method' => $payment_type,
        'amount' => $amount,
        'currency' => 'NGN',
        'metadata' => json_encode([
            'plan' => 'Pro Plan',
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'created_at' => now(),
            'updated_at' => now()
        ]),
        'created_at' => now(),
        'updated_at' => now()
    ];
    
    DB::table('subscriptions')->insert($data);
}


}
