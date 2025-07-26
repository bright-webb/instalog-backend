<?php
namespace App\Services;

use App\Models\Stores as Store;
use App\Models\StoreView;
use App\Models\StoreRating;
use App\Models\ProductView;
use App\Models\ProductImage;
use App\Models\Products;
use App\Models\ProductRating;
use App\Models\Inquiry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
   public function getStoreAnalytics(Store $store, string $timeRange = '7days')
    {
        $dateRange = $this->getDateRange($timeRange);

        return [
            'summary' => $this->getSummaryStats($store, $dateRange),
            'views' => $this->getViewAnalytics($store, $dateRange),
            'ratings' => $this->getRatingAnalytics($store),
            'products' => $this->getProductAnalytics($store, $dateRange),
            'inquiries' => $this->getInquiryAnalytics($store, $dateRange),
            'traffic_sources' => $this->getTrafficSources($store, $dateRange),
            'devices' => $this->getDeviceAnalytics($store, $dateRange),
            'locations' => $this->getLocationAnalytics($store, $dateRange),
        ];
    }

    public function getDateRange(string $timeRange)
    {
        switch ($timeRange) {
            case '24hours':
                return [Carbon::now()->subDay(), Carbon::now()];
            case '7days':
                return [Carbon::now()->subDays(7), Carbon::now()];
            case '30days':
                return [Carbon::now()->subDays(30), Carbon::now()];
            case '90days':
                return [Carbon::now()->subDays(90), Carbon::now()];
            default:
                return [Carbon::createFromTimestamp(0), Carbon::now()];
        }
    }

    protected function getSummaryStats(Store $store, array $dateRange): array
    {
        $viewsCount = StoreView::where('stores_id', $store->id)
            ->whereBetween('created_at', $dateRange)
            ->count();
            
        $uniqueVisitors = StoreView::where('stores_id', $store->id)
            ->whereBetween('created_at', $dateRange)
            ->distinct('fingerprint')
            ->count('fingerprint');
            
        // Returning visitors are visitors who have visited more than once
        $returningVisitors = StoreView::where('stores_id', $store->id)
            ->whereBetween('created_at', $dateRange)
            ->select('fingerprint')
            ->groupBy('fingerprint')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        $inquiriesCount = Inquiry::where('stores_id', $store->id)
            ->whereBetween('created_at', $dateRange)
            ->count();
            
        $averageRating = StoreRating::where('stores_id', $store->id)
            ->avg('rating');

        return [
            'total_views' => $viewsCount,
            'unique_visitors' => $uniqueVisitors,
            'returning_visitors' => $returningVisitors,
            'inquiries' => $inquiriesCount,
            'average_rating' => round($averageRating, 1),
            'products_count' => $store->products()->count()
        ];
    }

    protected function getViewAnalytics(Store $store, array $dateRange): array
    {
        $dailyViews = StoreView::where('stores_id', $store->id)
            ->whereBetween('created_at', $dateRange)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'count' => $item->count,
                ];
            });

        $viewsByHour = [];
        if ($dateRange[0]->diffInHours($dateRange[1]) <= 24) {
            $viewsByHour = StoreView::where('stores_id', $store->id)
                ->whereBetween('created_at', $dateRange)
                ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
                ->groupBy('hour')
                ->orderBy('hour')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->hour => $item->count];
                })
                ->toArray();
        }

        return [
            'daily' => $dailyViews,
            'hourly' => $viewsByHour
        ];
    }

    protected function getRatingAnalytics(Store $store): array
    {
        $ratingDistribution = StoreRating::where('stores_id', $store->id)->selectRaw('FLOOR(rating) as star, COUNT(*) as count')->groupBy('star')->orderBy('star', 'desc')->get()->mapWithKeys(function ($item) {
            return [$item->star . '_star' => $item->count];
        });

        $recentRatings = StoreRating::where('stores_id', $store->id)
            ->with(['store'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return [
            'distribution' => $ratingDistribution,
            'recent' => $recentRatings,
            'average' => round($store->ratings()->avg('rating'), 1),
            'total' => $store->ratings()->count(),
        ];
    }

    protected function getProductAnalytics(Store $store, array $dateRange): array
    {
        $topProducts = ProductView::whereHas('product', function ($query) use ($store) {
            $query->where('stores_id', $store->id);
        })
            ->whereBetween('created_at', $dateRange)
            ->selectRaw('products_id, COUNT(*) as views')
            ->groupBy('products_id')
            ->orderByDesc('views')
            ->limit(5)
            ->with([
                'product' => function ($query) {
                    $query->with('images');
                }
            ])
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                    'views' => $item->views,
                    'slug' => $item->product->slug,
                    'image' => $item->product->images->first()?->url ?? '/default-product-image.png',
                    'all_images' => $item->product->images->pluck('image_url')->toArray()
                ];
            });

        $productTrafficSources = ProductView::whereHas('product', function ($query) use ($store) {
            $query->where('stores_id', $store->id);
        })
            ->whereBetween('created_at', $dateRange)
            ->selectRaw('products_id, 
            SUM(CASE WHEN utm_source = "facebook" THEN 1 ELSE 0 END) as facebook_views,
            SUM(CASE WHEN utm_source = "instagram" THEN 1 ELSE 0 END) as instagram_views,
            SUM(CASE WHEN utm_source = "twitter" THEN 1 ELSE 0 END) as twitter_views,
            SUM(CASE WHEN utm_source IS NULL AND referrer LIKE "%google%" THEN 1 ELSE 0 END) as google_views,
            SUM(CASE WHEN utm_source IS NULL AND referrer IS NULL THEN 1 ELSE 0 END) as direct_views,
            SUM(CASE WHEN utm_source IS NULL AND referrer NOT LIKE "%google%" AND referrer IS NOT NULL THEN 1 ELSE 0 END) as other_views')
            ->groupBy('products_id')
            ->get()
            ->keyBy('products_id');

        $topProducts = $topProducts->map(function ($product) use ($productTrafficSources) {
            $sources = $productTrafficSources->get($product['id']);
            if ($sources) {
                $product['traffic_sources'] = [
                    'facebook' => $sources->facebook_views,
                    'instagram' => $sources->instagram_views,
                    'twitter' => $sources->twitter_views,
                    'google' => $sources->google_views,
                    'direct' => $sources->direct_views,
                    'other' => $sources->other_views
                ];
            }
            return $product;
        });


        $productRatings = ProductRating::whereHas('product', function ($query) use ($store) {
            $query->where('stores_id', $store->id);
        })
            ->selectRaw('products_id, AVG(rating) as avg_rating, COUNT(*) as count')
            ->groupBy('products_id')
            ->orderByDesc('avg_rating')
            ->limit(5)
            ->with([
                'product' => function ($query) {
                    $query->with('images');
                }
            ])
            ->get()
            ->map(function ($item) {
                return [
                    'products_id' => $item->products_id,
                    'avg_rating' => (float) $item->avg_rating,
                    'count' => $item->count,
                    'product' => [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'description' => $item->product->description,
                        'price' => 'N' . number_format($item->product->price),
                        'slug' => $item->product->slug,
                        'image' => $item->product->images->first()?->url ?? '/default-product-image.png',
                        'images' => $item->product->images->map(function ($image) {
                            return ['url' => $image->image_url];
                        })->toArray()
                    ],
                    'recent_reviews' => $item->product->ratings()
                        ->select('rating', 'review', 'created_at')
                        ->orderByDesc('created_at')
                        ->limit(2)
                        ->get()
                        ->toArray()
                ];
            });

        return [
            'top_viewed' => $topProducts,
            'top_rated' => $productRatings,
            'traffic_sources' => $this->getProductTrafficSources($store, $dateRange)
        ];
    }

    protected function getInquiryAnalytics(Store $store, array $dateRange): array
    {
        $inquiries = Inquiry::where('stores_id', $store->id)->whereBetween('created_at', $dateRange)->orderBy('created_at', 'desc')->limit(10)->get();
        $inquiriesByDay = Inquiry::where('stores_id', $store->id)->whereBetween('created_at', $dateRange)->selectRaw('DATE(created_at) as date, COUNT(*) as count')->groupBy('date')->orderBy('date')->get();

        return [
            'recent' => $inquiries,
            'trend' => $inquiriesByDay,
            'total' => $inquiries->count(),
        ];
    }

    protected function getTrafficSources(Store $store, array $dateRange): array
    {
        $query = DB::table('product_views')
            ->where('products_id', $store->id);

        // Apply date range if provided
        if (isset($dateRange['start'])) {
            $query->where('created_at', '>=', $dateRange['start']);
        }
        if (isset($dateRange['end'])) {
            $query->where('created_at', '<=', $dateRange['end']);
        }

        $results = $query->select(
            DB::raw('COUNT(*) as count'),
            'referrer',
            'utm_source',
            'device' 
        )
            ->groupBy('referrer', 'utm_source', 'device')
            ->get();

        $traffic = [
            'direct' => 0,
            'facebook' => 0,
            'instagram' => 0,
            'x' => 0,
            'tiktok' => 0,
            'linkedin' => 0,
            'whatsapp' => 0,
            'telegram' => 0,
            'google' => 0,
            'other' => 0
        ];


        foreach ($results as $result) {
            $referrer = strtolower($result->referrer ?? '');
            $device = strtolower($result->device ?? '');
            $isMobileApp = false;

            // Check for mobile app patterns
            if (str_starts_with($referrer, 'android-app://')) {
                $isMobileApp = true;
                $packageName = str_replace('android-app://', '', $referrer);

                if (str_contains($packageName, 'com.facebook.katana')) {
                    $traffic['facebook'] += $result->count;
                } elseif (str_contains($packageName, 'com.instagram.android')) {
                    $traffic['instagram'] += $result->count;
                } elseif (str_contains($packageName, 'com.whatsapp')) {
                    $traffic['whatsapp'] += $result->count;
                } elseif (str_contains($packageName, 'com.twitter.android')) {
                    $traffic['x'] += $result->count;
                } else if(str_contains($packageName, 'com.x.android')){
                    $traffic['x'] += $result->count;
                } elseif (str_contains($packageName, 'com.telegram.messenger')) {
                    $traffic['telegram'] += $result->count;
                } elseif (str_contains($packageName, 'com.tiktok')) {
                    $traffic['tiktok'] += $result->count;
                }
                else {
                    $traffic['other_apps'] += $result->count;
                }
                continue;
            }

           
            if (empty($referrer) && str_contains($device, 'mobile')) {
                if (str_contains($device, 'fbios') || str_contains($device, 'facebook')) {
                    $traffic['facebook'] += $result->count;
                } elseif (str_contains($device, 'instagram')) {
                    $traffic['instagram'] += $result->count;
                } elseif (str_contains($device, 'whatsapp')) {
                    $traffic['whatsapp'] += $result->count;
                } elseif (str_contains($device, 'x')) {
                    $traffic['x'] += $result->count;
                } elseif(str_contains($device, 'telegram')) {
                    $traffic['telegram'] += $result->count;
                } elseif (str_contains($device, 'tiktok')) {
                    $traffic['tiktok'] += $result->count;
                } else {
                    $traffic['other'] += $result->count;
                }
                continue;
            }

            if (empty($result->referrer)) {
                $traffic['direct'] += $result->count;
                continue;
            }

            $domain = strtolower(parse_url($result->referrer, PHP_URL_HOST) ?? '');

            switch (true) {
                // Social Platforms
                case str_contains($domain, 'facebook.com'):
                    $traffic['facebook'] += $result->count;
                    break;
                case str_contains($domain, 'instagram.com'):
                    $traffic['instagram'] += $result->count;
                    break;
                case str_contains($domain, 'twitter.com') || str_contains($domain, 't.co'):
                    $traffic['twitter'] += $result->count;
                    break;
                case str_contains($domain, 'whatsapp.com'):
                    $traffic['whatsapp'] += $result->count;
                    break;
                case str_contains($domain, 'telegram.org') || str_contains($domain, 't.me'):
                    $traffic['telegram'] += $result->count;
                    break;
                case str_contains($domain, 'linkedin.com'):
                    $traffic['linkedin'] += $result->count;
                    break;
                case str_contains($domain, 'tiktok.com'):
                    $traffic['tiktok'] += $result->count;
                    break;

                // Search Engines
                case str_contains($domain, 'google.com') || str_contains($domain, 'google.'):
                    $traffic['google'] += $result->count;
                    break;
    

                default:
                    $traffic['other_websites'] += $result->count;
            }
        }

        return $traffic;
    }

    protected function getDeviceAnalytics(Store $store, array $dateRange): array
    {
        $devices = StoreView::where('stores_id', $store->id)
            ->whereBetween('created_at', $dateRange)
            ->select('device')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('device')
            ->orderByDesc('count')
            ->get()
            ->map(function ($item) {
                return [
                    'device' => $item->device ?? 'Unknown',
                    'count' => $item->count,
                ];
            });

        return [
            'devices' => $devices,
            'mobile_percentage' => round($devices->whereIn('device', ['Mobile', 'iPhone', 'Android'])->sum('count') / max(1, $devices->sum('count')) * 100),
        ];
    }

    protected function getLocationAnalytics(Store $store, array $dateRange): array
    {
        $storeCity = $store->city;
        $storeCountry = $store->country;

        $query = DB::table('product_views')
            ->join('ip_locations', 'product_views.ip', '=', 'ip_locations.ip_address')
            ->where('product_views.products_id', $store->id);

        if (isset($dateRange['start'])) {
            $query->where('product_views.created_at', '>=', $dateRange['start']);
        }
        if (isset($dateRange['end'])) {
            $query->where('product_views.created_at', '<=', $dateRange['end']);
        }

        $views = $query->get(['ip_locations.city', 'ip_locations.country']);

        $local = 0;
        $national = 0;
        $international = 0;

        foreach ($views as $view) {
            if ($view->city === $storeCity && $view->country === $storeCountry) {
                $local++;
            } elseif ($view->country === $storeCountry) {
                $national++;
            } else {
                $international++;
            }
        }

        return [
            'local' => $local,
            'national' => $national,
            'international' => $international,
        ];
    }

    public function getProductViewsOverTime($productId, string $timeRange = '7days')
    {
        $dateRange = $this->getDateRange($timeRange);

        return ProductView::where('products_id', $productId)
            ->whereBetween('created_at', $dateRange)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    protected function getProductTrafficSources(Store $store, array $dateRange): array
    {
        $sources = ProductView::whereHas('product', function ($query) use ($store) {
            $query->where('stores_id', $store->id);
        })
            ->whereBetween('created_at', $dateRange)
            ->selectRaw('
            SUM(CASE WHEN utm_source = "facebook" THEN 1 ELSE 0 END) as facebook,
            SUM(CASE WHEN utm_source = "instagram" THEN 1 ELSE 0 END) as instagram,
            SUM(CASE WHEN utm_source = "twitter" THEN 1 ELSE 0 END) as twitter,
            SUM(CASE WHEN utm_source IS NULL AND referrer LIKE "%google%" THEN 1 ELSE 0 END) as google,
            SUM(CASE WHEN utm_source IS NULL AND referrer IS NULL THEN 1 ELSE 0 END) as direct,
            SUM(CASE WHEN utm_source IS NULL AND referrer NOT LIKE "%google%" AND referrer IS NOT NULL THEN 1 ELSE 0 END) as other')
            ->first();

        return [
            'facebook' => $sources->facebook ?? 0,
            'instagram' => $sources->instagram ?? 0,
            'twitter' => $sources->twitter ?? 0,
            'google' => $sources->google ?? 0,
            'direct' => $sources->direct ?? 0,
            'other' => $sources->other ?? 0
        ];
    }
}
