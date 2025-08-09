<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Stores as Store;
use App\Models\Rating;
use App\Models\StoreRating;
use App\Models\StoreView;
use App\Models\Inquiry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\StoreService;
use App\Services\RatingService;
use App\Services\AnalyticsService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Exception;


class StoreController extends Controller
{
    private StoreService $storeService;
    private RatingService $ratingService;
    public function __construct(StoreService $storeService, RatingService $ratingService){
        $this->storeService = $storeService;
        $this->ratingService = $ratingService;
    }
    /**
     * Display a listing of stores
     * GET /api/stores
     */
    public function index(Request $request)
    {
       try {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        $store = Store::where('user_id', $user->id)
            ->with(['products.images'])
            ->first();

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'No store found for this user',
                'data' => null
            ], 404);
        }

        // Using Laravel's built-in resource transformation
        $storeData = $store->toArray();
        
        // Ensure nested structure exists even if empty
        if (!isset($storeData['products'])) {
            $storeData['products'] = [];
        }
        
        foreach ($storeData['products'] as &$product) {
            if (!isset($product['images'])) {
                $product['images'] = [];
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Store retrieved successfully',
            'data' => $storeData
        ], 200);

    } catch (Exception $e) {
        \Log::error('Error retrieving store data: ' . $e->getMessage(), [
            'user_id' => $user->id ?? null,
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'An error occurred while retrieving store data',
            'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
        ], 500);
    }
    }

    /**
     * Store a newly created store
     * POST /api/stores
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'businessName' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'description' => 'nullable|string|min:20|max:500',
            'location' => 'required|string|max:255',
            'whatsappNumber' => [
                'required',
                'string',
                'regex:/^\+?[1-9]\d{1,14}$/',
                'unique:stores,whatsapp_number',
            ],
            'socialHandles' => 'nullable|array',
            'socialHandles.Instagram' => 'nullable|string|max:100',
            'socialHandles.Facebook' => 'nullable|string|max:100',
            'socialHandles.Twitter' => 'nullable|string|max:100',
            'socialHandles.Tiktok' => 'nullable|string|max:100',

            'deliveryOptions' => 'required|array|min:1',
            'deliveryOptions.*' => 'required|string|in:In-store pickup,Local delivery,Nationwide shipping,International shipping,Express delivery',
        ], [
            'businessName.required' => 'Business name is required.',
            'businessName.min' => 'Business name must be at least 2 characters.',
            'category.required' => 'Please select a business category.',
            'description.required' => 'Business description is required.',
            'description.min' => 'Please provide a more detailed description (at least 20 characters)',
            'descirption.max' => 'Description cannot exceed 500 characters.',
            'location.required' => 'Business location is required.',
            'whatsappNumber.required' => 'Whatsapp number is required.',
            'whatsappNumber.regex' => 'Please enter a valid phone number with country code.',
            'whatsappNumber.unique' => 'This Whatsapp number is already registered.',
            'deliveryOptions.required' => 'Please select at least one delivery options.',
            'deliveryOptions.*in' => 'Invalid delivery options select.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $ip = $request->ip();
        $token = config("services.ipinfo.token");
        
       $data = $validator->validated();

       $locationData = Http::withToken($token)->get("https://ipinfo.io/{$ip}/json")->json();

       $data['city'] = $locationData['city'] ?? null;
       $data['country'] = $locationData['country'] ?? null;

        $result = $this->storeService->createStore($data, $user);
         if ($result['success']) {
            return response()->json($result, 201);
        }

        return response()->json($result, 400);
        

    }

    /**
     * Display the specified store
     * GET /api/stores/{store}
     */
    public function show($store)
    {
       // check if store exists
       $storeData = Store::where('slug', $store)->firstOrFail();
       if(!$storeData){
        return response()->json([
            'message' => 'This store does not exists',
            'success' => false
        ]);
       }



        $storeData->load(['products.images']);

        return response()->json([
            'success' => true,
            'data' => $storeData
        ]);
    }

    /**
     * Update the specified store
     * PUT/PATCH /api/stores/{store}
     */
    public function update(Request $request, Store $store)
    {
        // Make sure user owns this store
        if ($store->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'business_name' => 'sometimes|required|string|max:255',
            'username' => 'sometimes|required|string|unique:stores,username,' . $store->id,
            'whatsapp_number' => 'sometimes|required|string|unique:stores,whatsapp_number,' . $store->id,
            'description' => 'nullable|string',
            'theme_id' => 'nullable|string',
            'is_active' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $store->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Store updated successfully',
            'data' => $store
        ]);
    }

    /**
     * Remove the specified store
     * DELETE /api/stores/{store}
     */
    public function destroy(Store $store)
    {
        // Make sure user owns this store
        if ($store->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $store->delete();

        return response()->json([
            'success' => true,
            'message' => 'Store deleted successfully'
        ]);
    }

    // Upload logo
   public function updateLogo($slug, Request $request)
{
    $request->validate([
        'logo' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        'cropped_url' => 'nullable|string'
    ]);

    $store = Store::where('slug', $slug)->firstOrFail();

    try {
        // Delete existing logo and cropped logo (if they exist)
        if ($store->logo_url) {
            $this->deleteFileFromS3($store->logo_url);
        }
        if ($store->logo_cropped_url) {
            $this->deleteFileFromS3($store->logo_cropped_url);
        }

        // Upload new logo
        $image = $request->file('logo');
        $croppedImage = $request->input('cropped_url');

        $filename = 'logo-' . Str::uuid() . '.' . $image->getClientOriginalExtension(); // Fixed extension
        $directory = 'stores/' . $store->id . '/logos';

        $originalPath = Storage::disk('s3')->putFileAs($directory, $image, $filename, [
            'visibility' => 'public',
        ]);

        if ($croppedImage) {
            $croppedFilename = 'cropped-' . $filename;
            $croppedPath = $directory . '/' . $croppedFilename;
            $this->saveBase64Image($croppedImage, $croppedPath);
            $store->logo_url = Storage::disk('s3')->url($originalPath); 
            $store->logo_cropped_url = Storage::disk('s3')->url($croppedPath);
        } else {
            $store->logo_url = Storage::disk('s3')->url($originalPath);
            $store->logo_cropped_url = null; 
        }

        $store->save(); 

        return response()->json([
            'success' => true,
            'message' => 'Logo updated successfully',
            'logo_url' => $store->logo_url,
            'logo_cropped_url' => $store->logo_cropped_url ?? null,
        ], 200);

    } catch(Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to update logo',
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * Helper function to delete a file from S3
 */
/**
 * Helper function to delete a file from S3
 */
private function deleteFileFromS3($url)
{
    try {
        if (empty($url)) {
            return;
        }

        // Handle different URL formats
        if (str_contains($url, 'amazonaws.com')) {
            $key = ltrim(parse_url($url, PHP_URL_PATH), '/');
        } elseif (str_contains($url, '/storage/')) {
            // Local storage URL format
            $key = str_replace('/storage/', '', parse_url($url, PHP_URL_PATH));
        } else {
            // Direct path format
            $key = ltrim(parse_url($url, PHP_URL_PATH), '/');
        }

        \Log::info("Attempting to delete S3 file: " . $key);

        if (Storage::disk('s3')->exists($key)) {
            Storage::disk('s3')->delete($key);
            \Log::info("Successfully deleted: " . $key);
        } else {
            \Log::warning("File not found in S3: " . $key);
        }
    } catch (Exception $e) {
        \Log::error("S3 Deletion Error for URL '$url': " . $e->getMessage());
    }
}
/**
 * Helper function to save Base64 image to S3
 */
private function saveBase64Image($base64Image, $path)
{
    $imageData = explode(',', $base64Image)[1]; 
    $decodedImage = base64_decode($imageData);
    Storage::disk('s3')->put($path, $decodedImage, ['visibility' => 'public']);
}

 public function updateCover(Request $request, $slug){
    $request->validate([
        'file' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
        'cropped_url' => 'nullable|string',
    ]);

    $store = Store::where('slug', $slug)->firstOrFail();

    try {
        if ($store->cover_url) {
            $this->deleteFileFromS3($store->cover_url);
        }
        if ($store->cover_cropped_url) {
            $this->deleteFileFromS3($store->cover_cropped_url);
        }

        $image = $request->file('file');
        $croppedImage = $request->input('cropped_url');

        $filename = 'cover-' . Str::uuid() . '.' . $image->getClientOriginalExtension();
        $directory = 'stores/' . $store->slug . '/covers';
        $originalPath = Storage::disk('s3')->putFileAs($directory, $image, $filename, [
            'visibility' => 'public',
        ]);

        if($croppedImage) {
            $croppedFileName = 'cropped-' . $filename; 
            $croppedPath = $directory . '/' . $croppedFileName;
            $this->saveBase64Image($croppedImage, $croppedPath);

            $store->cover_url = Storage::disk('s3')->url($originalPath); 
            $store->cover_cropped_url = Storage::disk('s3')->url($croppedPath); 
        } else {
            $store->cover_url = Storage::disk('s3')->url($originalPath); 
            $store->cover_cropped_url = null; 
        }

        $store->save();
        return response()->json([
            'success' => true,
            'message' => 'Cover photo uploaded successfully',
            'cover_url' => $store->cover_url,
            'cover_cropped_url' => $store->cover_cropped_url ?? null
        ], 200);
    } catch(Exception $e){
        return response()->json([
            'success' => false,
            'message' => 'Failed to upload cover photo',
            'error' => $e->getMessage()
        ], 500);
    }
}

 // Rate store
public function rateStore(Request $request, $storeSlug)
    {
        try {
            $store = Store::where('slug', $storeSlug)->firstOrFail();
            $request->merge(['stores_id' => $store->id]);
            return $this->ratingService->rating($request, $store->id);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit rating',
                'error' => $e->getMessage()
            ], 500);
        }
    }
public function hasRated(Request $request, $storeSlug){
    $fingerprint = $request->input('fingerprint');
    $store = Store::where('slug', $storeSlug)->first();
    $rating = StoreRating::where('stores_id', $store->id)
                         ->where('fingerprint', $fingerprint)
                        ->first();
    return response()->json([
        'success' => true,
        'has_rated' => $rating !== null,
        'rating' => $rating ? $rating->rating : null
    ]);
}
    /**
     * Check if user has rated a store
     * GET /api/stores/{store}/has-rated
     */
    // StoreController.php

public function analytics($storeSlug)
{
    try {
        $store = Store::where('slug', $storeSlug)->firstOrFail();
        
        $ratings = $store->ratings()
            ->orderBy('created_at', 'desc')
            ->get();
            
        $views = $store->views()
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => [
                'store' => $store,
                'ratings' => $ratings,
                'views' => $views,
                'stats' => [
                    'rating_distribution' => [
                        '1_star' => $store->ratings()->where('rating', 1)->count(),
                        '2_star' => $store->ratings()->where('rating', 2)->count(),
                        '3_star' => $store->ratings()->where('rating', 3)->count(),
                        '4_star' => $store->ratings()->where('rating', 4)->count(),
                        '5_star' => $store->ratings()->where('rating', 5)->count(),
                    ],
                    'daily_views' => $store->views()
                        ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                        ->groupBy('date')
                        ->orderBy('date')
                        ->get()
                ]
            ]
        ]);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to load analytics'
        ], 500);
    }
}

public function moderateReview(Request $request, $storeSlug, $reviewId)
{
    try {
        $store = Store::where('slug', $storeSlug)->firstOrFail();
        $review = $store->ratings()->findOrFail($reviewId);
        
        $action = $request->input('action');
        
        switch ($action) {
            case 'approve':
                $review->update(['is_approved' => true]);
                break;
            case 'reject':
                $review->update(['is_approved' => false]);
                break;
            case 'delete':
                $review->delete();
                break;
            case 'reply':
                $review->update(['reply' => $request->input('reply')]);
                break;
            default:
                throw new \Exception('Invalid action');
        }
        
        // Update store stats
        // $this->updateStoreRatings($store);
        
        return response()->json(['success' => true]);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Get store ratings
     * GET /api/stores/{store}/ratings
     */
    // In StoreController.php

/**
 * Get store ratings with statistics
 * GET /api/stores/{store}/ratings
 */
public function getRatings(Request $request, $storeSlug)
{
    try {
        $store = Store::where('slug', $storeSlug)->firstOrFail();
        
        $ratings = Rating::where('store_id', $store->id)
            ->orderBy('created_at', 'desc')
            ->paginate(5); // Paginate with 5 reviews per page

        $averageRating = Rating::where('store_id', $store->id)->avg('rating');
        $totalRatings = Rating::where('store_id', $store->id)->count();

        // Get rating distribution (1-5 stars)
        $distribution = Rating::where('store_id', $store->id)
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->orderBy('rating', 'desc')
            ->pluck('count', 'rating')
            ->toArray();

        return response()->json([
            'success' => true,
            'average_rating' => round($averageRating, 1),
            'total_ratings' => $totalRatings,
            'ratings' => $ratings,
            'distribution' => $distribution,
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to get ratings',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function recordView(Request $request, $storeSlug)
{
    $request->validate([
        'fingerprint' => 'required|string',
        'ip' => 'nullable|ip',
        'device' => 'nullable|string',
    ]);

    $store = Store::where('slug', $storeSlug)->firstOrFail();

    // Record unique store view
    $view = StoreView::firstOrCreate([
        'stores_id' => $store->id,
        'fingerprint' => $request->fingerprint,
    ], [
        'ip' => $request->ip(),
        'device' => $request->device,
        'meta' => json_encode([
            'user_agent' => $request->userAgent(),
            'referrer' => $request->header('referer'),
        ]),
    ]);

    return response()->json([
        'success' => true,
        'message' => 'View recorded',
    ]);
}

public function recordInquiry(Request $request, $storeSlug)
{
    $request->validate([
        'fingerprint' => 'required|string',
        'product_clicked' => 'required|string',
    ]);

    $store = Store::where('slug', $storeSlug)->firstOrFail();

    // Record inquiry
    $inquiry = Inquiry::create([
        'stores_id' => $store->id,
        'product_clicked' => $request->product_clicked,
        'unique_visitor' => $request->fingerprint,
        'label' => $request->label ?? null,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Inquiry recorded',
    ]);
}
 

public function storeAnalytics(Request $request)
{
    $user = $request->user();
    $storeData = Store::where('user_id', $user->id)->first();
    
    try {
        $store = Store::where('id', $storeData->id)->firstOrFail();
        
        $timeRange = $request->input('time_range', '7days');
        $analyticsService = new AnalyticsService();
        
        $data = [
            'store' => $store->load(['products.images', 'products.views']),
            'analytics' => $analyticsService->getStoreAnalytics($store, $timeRange),
            'premium_metrics' => $this->getPremiumMetrics($store, $timeRange),
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);

    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to load analytics',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}

protected function getPremiumMetrics(Store $store, string $timeRange)
{
    if (!auth()->user() || !auth()->user()->is_premium) {
        return [
            'is_premium' => false,
            'message' => 'Upgrade to premium to unlock advanced analytics',
            'metrics' => [
                'conversion_rate' => null,
                'customer_acquisition_cost' => null,
                'repeat_customers' => null,
            ]
        ];
    }

    return [
        'is_premium' => true,
        'metrics' => [
            'conversion_rate' => $this->calculateConversionRate($store, $timeRange),
            'customer_acquisition_cost' => $this->calculateCAC($store, $timeRange),
            'repeat_customers' => $this->calculateRepeatCustomers($store, $timeRange),
        ]
    ];
}

protected function calculateConversionRate(Store $store, string $timeRange): float
{
    $dateRange = (new AnalyticsService())->getDateRange($timeRange);
    
    $views = StoreView::where('stores_id', $store->id)
        ->whereBetween('created_at', $dateRange)
        ->count();

    $inquiries = Inquiry::where('stores_id', $store->id)
        ->whereBetween('created_at', $dateRange)
        ->count();

    return $views > 0 ? round(($inquiries / $views) * 100, 2) : 0;
}

protected function calculateCAC(Store $store, string $timeRange): ?float
{
    // Todo: Implement marketing spend data
    return null;
}

protected function calculateRepeatCustomers(Store $store, string $timeRange): int
{
    $dateRange = (new AnalyticsService())->getDateRange($timeRange);
    
    return Inquiry::where('stores_id', $store->id)
        ->whereBetween('created_at', $dateRange)
        ->select('fingerprint')
        ->groupBy('fingerprint')
        ->havingRaw('COUNT(*) > 1')
        ->count();
}
}