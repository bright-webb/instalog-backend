<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PageView;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PageViewController extends Controller
{
    public function store(Request $request): JsonResponse {
        $validator = Validator::make($request->all(), [
            'url' => 'required|string|max:2048',
            'referrer' => 'nullable|string|max:2048',
            'user_agent' => 'nullable|string',
            'timestamp' => 'required|date',
            'page_title' => 'nullable|string|max:500',
            'viewport_width' => 'nullable|integer|min:0',
            'viewport_height' => 'nullable|integer|min:0',
            'session_duration' => 'nullable|integer|min:0'
        ]);

        if($validator->fails()){
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $pageView = PageView::create([
                'url' => $request->url,
                'referrer' => $request->referrer,
                'user_agent' => $request->user_agent,
                'ip_address' => $request->ip(),
                'user_id' => auth('sanctum')->id(),
                'session_id' => $request->session()->getId() ? : 'api-sesion-' . uniqid(),
                'page_title' => $request->page_title,
                'viewport_width' => $request->viewport_width,
                'viewport_height' => $request->viewport_height,
                'session_duration' => $request->session_duration,
                'viewed_at' => $request->timestamp
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Page view tracked successfully',
                'data' => $pageView
            ]);
        } catch(\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to track page view',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function batchStore(Request $request): JsonResponse {
        $validator = Validator::make($request->all(), [
            'page_views' => 'requred|array|max:100',
            'page_views.*.url' => 'required|string|max:2048',
            'page_views.*.referrer' => 'nullable|string|max:2048',
            'page_views.*.user_agent' => 'nullable|string',
            'page_views.*.timestamp' => 'required|date',
            'page_views.*.page_title' => 'nullable|string|max:500',
            'page_views.*.viewport_width' => 'nullable|integer|min:0',
            'page_views.*.viewport_height' => 'nullable|integer|min:0',
            'page_views.*.session_duration' => 'nullable|integer|min:0'
        ]);

        if($validator->fails()){
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $pageViewsData = collect($request->page_views)->map(function($pageView) use ($request) {
                return [
                    'url' => $pageView['url'],
                    'referrer' => $pageView['referrer'] ?? null,
                    'user_agent' => $pageView['user_agent'] ?? null,
                    'ip_address' => $request->ip(),
                    'user_id' => auth('sanctum')->id(),
                    'session_id' => $request->session()->getId() ? : 'api-session-' . uniqid(),
                    'page_title' => $pageView['page_title'] ?? null,
                    'viewport_width' => $pageView['viewport_width'] ?? null,
                    'viewport_height' => $pageView['viewport_height'] ?? null,
                    'session_duration' => $pageView['session_duration'] ?? null,
                    'viewed_at' => $pageView['timestamp'],
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            })->toArray();


            PageView::insert($pageViewsData);

            return response()->json([
                'success' => true,
                'message' => 'Page views tracked successfully',
                'count' => count($pageViewsData)
            ]);
        } catch(\Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Failed to track page views',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function analytics(Request $request): JsonResponse {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'url' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:1000'
        ]);

        if($validator->fails()){
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = PageView::query();

            if($request->start_date){
                $query->where('viewed_at', '>=', $request->start_date);
            }

            if($request->end_date){
                $query->where('viewed_at', '<=', $request->end_date);
            }

            if($request->url){
                $query->where('url', 'like', '%' . $request->url . '%');
            }

            $analytics = $query->select([
                'url',
                DB::raw('COUNT(*) as views'),
                DB::raw('COUNT(DISTINCT session_id) as avg_session_duration'),
                DB::raw('AVG(session_duration) as avg_session_duration'),
                DB::raw('MAX(viewed_at) as last_viewed')
            ])->groupBy('url')
             ->orderByDesc('views')
             ->limit($request->limit ?: 50)
             ->get();

             return response()->json([
                'success' => true,
                'data' => $analytics
             ], 200);
        } catch(\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function popularPages(Request $request): JsonResponse {
        try {
            $days = $request->input('days', 30);
            $startDate = Carbon::now()->subDays($days);

            $popularPages = PageView::where('viewed_at', '>=', $startDate)->select([
                'url',
                'page_title',
                DB::raw('COUNT(*) as views'),
                DB::raw('COUNT(DISTINCT session_id) as unique_visitors')
            ])->groupBy(['url', 'page_title'])
              ->orderByDesc('views')
              ->limit(20)
              ->get();

              return response()->json([
                'success' => true,
                'data' => $popularPages
              ]);
        }catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch popular pages',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
