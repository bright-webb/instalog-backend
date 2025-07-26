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

class PaymentController extends Controller
{
    private $client;
    public function __construct(Client $client) {
        $this->client = $client;
    }

    public function verify(){
      
    }

    public function getPaymentPlan(Request $request){
        $cacheKey = 'user_plan_' . auth()->id();
       $user = auth()->user();
       // Check if user has plan
       $plan = cache()->remember($cacheKey, now()->addHours(1), function() use ($user) {
           return DB::table('plans')->where('user_id', $user->id)->first();
       });
       if($plan && $plan->is_active) {
           return response()->json([
               'success' => true,
               'message' => 'User has an active plan',
               'plan' => $plan
           ]);
       } else {
        return response()->json([
            'success' => false
        ]);
       }
    }

public function createPlan(Request $request)
{
    $request->validate([
        'plan_name' => 'required|string',
        'amount' => 'required|numeric',
        'interval' => 'required|in:daily,weekly,monthly,yearly',
        'duration' => 'required|integer'
    ]);

    $user = auth()->user();
    
    try {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('FLUTTERWAVE_TEST_SECRET_KEY'),
            'Content-Type' => 'application/json',
        ])->post('https://api.flutterwave.com/v3/payment-plans', [
            'amount' => $request->input('amount') * 100, // Convert to kobo
            'name' => $request->input('plan_name'),
            'interval' => $request->input('interval'),
            'duration' => $request->input('duration'),
            'currency' => 'NGN'
        ]);

        if ($response->successful()) {
            $planData = $response->json();
            
            // Save plan to database
            $plan = [
                'user_id' => $user->id,
                'plan_id' => $planData['data']['id'],
                'name' => $request->input('plan_name'),
                'price' => $request->input('amount'),
                'interval' => $request->input('interval'),
                'duration' => $request->input('duration')
            ];

            DB::table('plans')->insert($plan);

            return response()->json([
                'success' => true,
                'message' => 'Plan created successfully',
                'data' => [
                    'id' => $planData['data']['id'],
                    'name' => $request->input('plan_name'),
                    'amount' => $request->input('amount'),
                    'interval' => $request->input('interval'),
                    'duration' => $request->input('duration')
                ]
            ]);
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


}
