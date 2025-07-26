<?php
namespace App\Services;
use Exception;
use App\Models\Rating;
use App\Models\StoreRating;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;
use Illuminate\Http\Request;
use App\Models\Stores as Store;
use Illuminate\Support\Facades\DB;

class RatingService
{
  public function rating($request, $store_id)
    {
        $request->validate([
            'rating' => 'required|numeric|min:1|max:5',
            'review' => 'nullable|string|max:1000',
            'fingerprint' => 'required|string'
        ]);

        $agent = new Agent();
        $fingerprint = $request->fingerprint;

        $existingRating = StoreRating::where('stores_id', $store_id)
            ->where('fingerprint', $fingerprint)
            ->first();

        $meta = [
            'browser' => $agent->browser(),
            'platform' => $agent->platform(),
            'device' => $agent->device(),
            'is_mobile' => $agent->isMobile(),
            'is_tablet' => $agent->isTablet(),
            'is_desktop' => $agent->isDesktop(),
            'user_agent' => $request->userAgent()
        ];

        if ($existingRating) {
            // Update existing rating
            $existingRating->update([
                'rating' => $request->rating,
                'review' => $request->review,
                'meta' => $meta
            ]);
        } else {
            // Create new rating
            $existingRating = StoreRating::create([
                'stores_id' => $store_id,
                'rating' => $request->rating,
                'review' => $request->review,
                'fingerprint' => $fingerprint,
                'ip' => $request->ip(),
                'device' => $agent->device(),
                'meta' => $meta
            ]);
        }

        // Update store rating stats
        $this->updateStoreRatings($store_id);

        return response()->json([
            'success' => true,
            'message' => 'Rating submitted successfully',
            'data' => [
                'rating' => $existingRating->rating,
                'review' => $existingRating->review
            ]
        ]);
    }

    protected function updateStoreRatings($storeId)
    {
        // $store = Store::find($storeId);
        // $ratings = $store->ratings()->whereNotNull('rating')->get();

        // $store->update([
        //     'average_rating' => $ratings->avg('rating'),
        //     'total_ratings' => $ratings->count()
        // ]);
    }

    // Check if user has already rated a store
 public function hasRated($storeSlug, $fingerprint)
    {
        $store = Store::where('slug', $storeSlug)->firstOrFail();

        $rating = StoreRating::where('stores_id', $store->id)
            ->where('fingerprint', $fingerprint)
            ->first();

        return response()->json([
            'has_rated' => $rating !== null,
            'rating' => $rating ? $rating->rating : null
        ]);
    }

    // Private helper methods
    private function generateUserFootprint($request): string
    {
        // Create a unique but anonymous fingerprint
        $components = [
            $request->ip(),
            $request->header('User-Agent'),
            $request->header('Accept-Language'),
            $request->header('Accept-Encoding'),
            $request->header('DNT'),
            $request->header('Connection'),
            date('Y-m-d'),
        ];

        // Remove null values and create hash
        $components = array_filter($components);
        $fingerprint = implode('|', $components);

        return hash('sha256', $fingerprint);
    }

    private function getDeviceInfo($request): string
    {
        $userAgent = $request->header('User-Agent');

        // Simple device detection
        if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
            return 'Mobile';
        } elseif (preg_match('/Tablet|iPad/', $userAgent)) {
            return 'Tablet';
        } else {
            return 'Desktop';
        }
    }

    private function getMetaData($request): array
    {
        return [
            'user_agent' => $request->header('User-Agent'),
            'accept_language' => $request->header('Accept-Language'),
            'referrer' => $request->header('Referer'),
            'screen_resolution' => $request->input('screen_resolution'),
            'timezone' => $request->input('timezone'),
            'timestamp' => now()->toISOString(),
        ];
    }

    private function updateStoreAverageRating(int $storeId): void
    {
        $averageRating = Rating::where('store_id', $storeId)->avg('rating');
        $totalRatings = Rating::where('store_id', $storeId)->count();

        DB::table('stores')
            ->where('id', $storeId)
            ->update([
                'average_rating' => round($averageRating, 2),
                'total_ratings' => $totalRatings,
                'updated_at' => now()
            ]);
    }

    // Get store ratings with pagination
    public function getStoreRatings($request, int $storeId)
    {
        $ratings = Rating::where('store_id', $storeId)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $averageRating = Rating::where('store_id', $storeId)->avg('rating');
        $totalRatings = Rating::where('store_id', $storeId)->count();

        return response()->json([
            'ratings' => $ratings,
            'average_rating' => round($averageRating, 2),
            'total_ratings' => $totalRatings,
            'rating_distribution' => $this->getRatingDistribution($storeId)
        ]);
    }

    private function getRatingDistribution(int $storeId): array
    {
        $distribution = Rating::where('store_id', $storeId)
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->orderBy('rating', 'desc')
            ->pluck('count', 'rating')
            ->toArray();

        $fullDistribution = [];
        for ($i = 5; $i >= 1; $i--) {
            $fullDistribution[$i] = $distribution[$i] ?? 0;
        }

        return $fullDistribution;
    }
}
?>