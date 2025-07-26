<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Models\Products;
use App\Models\ProductView;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use App\Models\ProductRating;
use App\Models\Stores as Store;
use Illuminate\Support\Facades\DB;
use App\Services\IpLocationService;
use App\Models\IpLocation;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProductController extends Controller
{
    public function store(Request $request)
    {
        
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'category' => 'nullable|string|max:255',
                'store_id' => 'required|exists:stores,id',
                'is_active' => 'string',
                'sort_order' => 'integer|min:0',
                'images' => 'required|min:1|max:10',
                'images.*.file' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5048',
                'images.*.name' => 'required|string',
                'images.*.isPrimary' => 'required|boolean'
            ]);

           $user = auth()->user();

            // Check if user is not premium
            if (!$user->is_premium) {
                // Count the number of products they already have for this store
                $productCount = Products::where('stores_id', $validated['store_id'])->count();

                if ($productCount >= 5) {
                    return response()->json([
                        'success' => false,
                        'message' => "You've reached your limits of 5 products.",
                        "error_code" => 600
                    ], 400);
                }
            }


            $hasPrimaryImage = collect($validated['images'])->contains('isPrimary', true);
            if (!$hasPrimaryImage) {
                // Set first image as primary if none selected
                $validated['images'][0]['isPrimary'] = true;
            }
            // Get store details
            $store = Store::where('id', $validated['store_id'])->first();
            $primaryCount = collect($validated['images'])->where('isPrimary', true)->count();
            if ($primaryCount > 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only one image can be set as primary',
                    'errors' => ['images' => 'Only one image can be set as primary']
                ], 422);
            }

            DB::beginTransaction();

            // Generate unique slug
            $slug = $this->generateSlug($validated['name']);
            $product = Products::create([
                'stores_id' => $validated['store_id'],
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'],
                'price' => $validated['price'],
                'category' => $validated['category'],
                'is_active' => $validated['is_active'] ?? true,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            // Proccess and store images
            $uploadedImages = [];
            foreach ($validated['images'] as $index => $imageData) {
                $file = $imageData['file'];

                $filename = time() . '_' . $index . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('product-images', $filename, 'public');

                // Get file size
                $fileSize = $file->getSize();

                $productImage = ProductImage::create([
                    'products_id' => $product->id,
                    'image_url' => Storage::url($path),
                    'image_name' => $imageData['name'],
                    'image_meta' => json_encode([
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'is_primary' => $imageData['isPrimary'],
                        'sort_order' => $index
                    ]),
                    'image_size' => $this->formatFileSize($fileSize)
                ]);

                $uploadedImages[] = [
                    'id' => $productImage->id,
                    'url' => $productImage->image_url,
                    'name' => $productImage->image_name,
                    'is_primary' => $imageData['isPrimary'],
                    'size' => $productImage->image_size
                ];
            }

            DB::commit();

            $product->refresh();
            $product->load('images');

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => [
                    'product' => $product,
                    'images' => $uploadedImages,
                    'shareable_url' => url("/{$store->slug}/{$product->slug}"),
                    'admin_url' => url("/admin/products/{$product->id}")
                ]
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            DB::rollBack();

            // Clean up uploaded files if any
            if (isset($uploadedImages)) {
                foreach ($uploadedImages as $image) {
                    $this->deleteImageFile($image['url']);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to create product: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getTrace() : null
            ], 500);
        }
    }
    /*

    */
    private function generateSlug($name, $id = null)
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while ($this->slugExists($slug, $id)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }


    /*

    */
    private function slugExists($slug, $excluded = null)
    {
        $query = Products::where('slug', $slug);
        if ($excluded) {
            $query->where('id', '!=', $excluded);
        }

        return $query->exists();
    }

    private function formatFileSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    private function deleteImageFile($imageUrl)
    {
        try {
            $path = str_replace('/storage/', '', parse_url($imageUrl, PHP_URL_PATH));
            Storage::disk('public')->delete($path);
        } catch (Exception $e) {
            \Log::error('Failed to delete image file: ' . $e->getMessage());
        }
    }

   public function index($store = null)
{
    try {
        $perPage = request()->input('per_page', 12);
        $page = request()->input('page', 1);
        
        $fingerprint = request()->input('fingerprint');
        
        // Build query
        $query = Products::where('is_active', true)
            ->with(['images', 'store', 'reviews']);
    
        if ($store) {
            $query->whereHas('store', function($q) use ($store) {
                $q->where('slug', $store);
            });
        }
        
        if (request()->has('category')) {
            $query->where('category_id', request()->input('category'));
        }
        
        if (request()->has('search')) {
            $searchTerm = request()->input('search');
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }
        
        $query->orderBy('created_at', 'desc');
        
        $products = $query->paginate($perPage, ['*'], 'page', $page);
        
        // Process each product
        $productData = $products->getCollection()->map(function($product) use ($fingerprint) {
            // if ($fingerprint) {
            //     $this->trackUniqueView($product, $fingerprint);
            //     $this->recordProductView(request(), $product->id);
            // }
            
            $data = is_object($product) && method_exists($product, 'toArray')
                ? $product->toArray()
                : (array) $product;
            $data['views'] = $product->views_count;
            
            return $data;
        });
        
        return response()->json([
            'success' => true,
            'products' => $productData,
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'has_more' => $products->hasMorePages(),
                'next_page_url' => $products->nextPageUrl(),
            ]
        ]);
        
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to load products',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function recordProductView(Request $request, $productId)
{
    $agent = new Agent();
    $ip = $request->ip();
    $fingerprint = $request->input('fingerprint');
    $referer = $request->headers->get('referer') ?? $request->input('referer');

    if(ProductView::where('fingerprint', $fingerprint)->exists()){
        return response()->json([
            'success' => false
        ]);
    }
    // Create the view record
    ProductView::firstOrCreate([
        'products_id' => $productId,
        'fingerprint' => $fingerprint,
    ], [
         'ip' => $ip,
        'device' => $request->userAgent(),
        'referrer' => $referer,
         'meta' => [
            'browser' => $agent->browser(),
            'platform' => $agent->platform(),
            'is_mobile' => $agent->isMobile(),
            'is_tablet' => $agent->isTablet(),
            'is_desktop' => $agent->isDesktop()
        ]
    ]);
    
    if ($ip && !IpLocation::where('ip_address', $ip)->exists()) {
        $locationService = new IpLocationService();
        $locationService->getLocation($ip);
    }
    
    return response()->json(['success' => true]);
}

public function show($store, $product)
{
    try {
        $p = Products::where('slug', $product)
            ->where('is_active', true)
            ->with(['images', 'store', 
                   'reviews' => function($query) {
                       $query->where('rating', '>', 0);
                   }])
            ->firstOrFail();

        $fingerprint = request()->input('fingerprint');

        // if ($fingerprint && $p instanceof Products) {
        //     $this->recordProductView(request(), $p->id);
        // }

        $productData = $p->toArray();
        $productData['views'] = $p->views_count; 
        $store = $productData['store'];
        unset($productData['store']);

        return response()->json([
            'success' => true,
            'product' => $productData,
            'store' => $store
        ]);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Product not found',
            'error' => $e->getMessage()
        ], 404);
    }
}

// Optimized version using cursor-based pagination (better for infinite scroll performance)
public function indexWithCursor($store = null)
{
    try {
        $perPage = request()->input('per_page', 12);
        $cursor = request()->input('cursor');
        
        $fingerprint = request()->input('fingerprint');
        
        $query = Products::where('is_active', true)
            ->with(['images', 'store', 'reviews']);
        
        // Filter by store if provided
        if ($store) {
            $query->whereHas('store', function($q) use ($store) {
                $q->where('slug', $store);
            });
        }
        
        if ($cursor) {
            $query->where('id', '<', $cursor);
        }
        
        $query->orderBy('id', 'desc');
        
        $products = $query->limit($perPage + 1)->get(); // Get one extra to check if there are more
        
        $hasMore = $products->count() > $perPage;
        if ($hasMore) {
            $products->pop(); 
        }
        
        $nextCursor = $hasMore ? $products->last()->id : null;
        
        $productData = $products->map(function($product) use ($fingerprint) {
            // if ($fingerprint) {
            //     $this->recordProductView($product->id, $fingerprint);
            // }
            
            $data = $product->toArray();
            $data['views'] = $product->views_count;
            return $data;
        });
        
        return response()->json([
            'success' => true,
            'products' => $productData,
            'pagination' => [
                'has_more' => $hasMore,
                'next_cursor' => $nextCursor,
                'per_page' => $perPage,
            ]
        ]);
        
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to load products',
            'error' => $e->getMessage()
        ], 500);
    }
}

protected function trackUniqueView(Products $product, string $fingerprint)
{
    $agent = new Agent();
    
    ProductView::firstOrCreate([
        'products_id' => $product->id,
        'fingerprint' => $fingerprint
    ], [
        'ip' => request()->ip(),
        'device' => $agent->device(),
        'meta' => [
            'browser' => $agent->browser(),
            'platform' => $agent->platform(),
            'is_mobile' => $agent->isMobile(),
            'is_tablet' => $agent->isTablet(),
            'is_desktop' => $agent->isDesktop()
        ]
    ]);
}

    // public function index($slug)
    // {
    //     try {
    //         $store = Store::where('slug', $slug)->first();
    //         $products = Products::where('stores_id', $store->id)
    //             ->where('is_active', true)
    //             ->orderBy('created_at', 'desc')
    //             ->limit(10)
    //             ->with(['images'])
    //             ->get();


    //         foreach ($products as $product) {
    //             $product->amount = (float) number_format($product->price, 2);
    //         }

    //         return response()->json([
    //             'success' => true,
    //             'data' => $products
    //         ]);
    //     } catch (Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Product not found'
    //         ], 404);
    //     }
    // }

    public function update($store, $product, Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'category' => 'nullable|string|max:255',
                'is_active' => 'boolean',
                'sort_order' => 'integer|min:0',
                'images' => 'array|max:10',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
                'image_names' => 'array',
                'image_names.*' => 'string',
                'primary_image_index' => 'integer|min:0',
                'images_to_delete' => 'array',
                'images_to_delete.*' => 'integer|exists:product_images,id',
            ]);

            $storeData = Store::where('slug', $store)->firstOrFail();

            $productData = Products::where('stores_id', $storeData->id)
                ->where('slug', $product)
                ->firstOrFail();

            if ($storeData->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Validate primary image logic if new images are being uploaded
            if (!empty($validated['images'])) {
                $primaryImageIndex = $validated['primary_image_index'] ?? 0;
                $imageCount = count($validated['images']);

                if ($primaryImageIndex >= $imageCount) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid primary image index',
                        'errors' => ['primary_image_index' => 'Primary image index is out of range']
                    ], 422);
                }
            }

            DB::beginTransaction();

            // Generate new slug if name changed
            if ($productData->name !== $validated['name']) {
                $validated['slug'] = $this->generateSlug($validated['name'], $productData->id);
            }

            // Update product
            $productData->update($validated);

            // Handle image deletions
            $deletedImages = [];
            if (!empty($validated['images_to_delete'])) {
                $imagesToDelete = ProductImage::where('products_id', $productData->id)
                    ->whereIn('id', $validated['images_to_delete'])
                    ->get();

                foreach ($imagesToDelete as $image) {
                    // Store for potential cleanup
                    $deletedImages[] = $image->image_url;

                    // Delete physical files
                    $this->deleteImageFile($image->image_url);

                    // Delete database record
                    $image->delete();
                }
            }

            // Handle new image uploads
            $uploadedImages = [];
            if (!empty($validated['images'])) {
                $primaryImageIndex = $validated['primary_image_index'] ?? 0;
                $imageNames = $validated['image_names'] ?? [];

                foreach ($validated['images'] as $index => $file) {
                    $isPrimary = ($index === $primaryImageIndex);
                    $imageName = $imageNames[$index] ?? 'Image ' . ($index + 1);

                    $filename = time() . '_' . $index . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('product-images', $filename, 'public');

                    // Get file size
                    $fileSize = $file->getSize();

                    $productImage = ProductImage::create([
                        'products_id' => $productData->id,
                        'image_url' => Storage::url($path),
                        'image_name' => $imageName,
                        'image_meta' => json_encode([
                            'original_name' => $file->getClientOriginalName(),
                            'mime_type' => $file->getMimeType(),
                            'is_primary' => $isPrimary,
                            'sort_order' => $index
                        ]),
                        'image_size' => $this->formatFileSize($fileSize)
                    ]);

                    $uploadedImages[] = [
                        'id' => $productImage->id,
                        'url' => $productImage->image_url,
                        'name' => $productImage->image_name,
                        'is_primary' => $isPrimary,
                        'size' => $productImage->image_size
                    ];
                }
            }

            DB::commit();

            // Load fresh data with relationships
            $productData = $productData->fresh(['images']);

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => [
                    'product' => $productData,
                    'images' => $uploadedImages,
                    'shareable_url' => env("FRONTEND_URL").$store . "/{$productData->slug}",
                    'admin_url' => url("/admin/products/{$productData->id}")
                ]
            ]);

        } catch (ValidationException $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (ModelNotFoundException $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Store or product not found'
            ], 404);

        } catch (Exception $e) {
            DB::rollback();

            // Clean up uploaded files if any
            if (isset($uploadedImages)) {
                foreach ($uploadedImages as $image) {
                    $this->deleteImageFile($image['url']);
                }
            }

            Log::error('Product update failed', [
                'store' => $store,
                'product' => $product,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update product: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getTrace() : null
            ], 500);
        }
    }

    private function generateThumbnail($imageFile, $productId)
    {
        $thumbnailPath = 'products/' . $productId . '/thumbnails/' . $imageFile->hashName();

        // $img = Image::make($imageFile)->fit(300, 300);
        // Storage::put($thumbnailPath, $img->encode());

        // For now, just store the same image as thumbnail
        $imageFile->storeAs('products/' . $productId . '/thumbnails', $imageFile->hashName(), 'public');

        return $thumbnailPath;
    }
    // Delete product
    public function destroy($store, $product, Request $request)
    {
        $user = $request->user();
        $store_id = Store::where('slug', $store)->value('id');
        $productData = Products::where('slug', $product)
            ->where('stores_id', $store_id)
            ->first();


        if (!$productData) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        // Delete and product images
        try {
            DB::beginTransaction();
            $productData->images()->each(function ($image) {
                $this->deleteImageFile($image->image_url);
                $image->delete();
            });
            $productData->delete();
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully'
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product: ' . $e->getMessage()
            ], 500);
        }
    }

    public function likeProduct($product_id, Request $request)
{
    $request->validate([
        'liked' => 'required|string',
        'fingerprint' => 'required|string'
    ]);

    $product = Products::findOrFail($product_id);
    $fingerprint = $request->fingerprint;
    $liked = $request->input('liked');

    // Check if this user already liked the product
    $existingRating = ProductRating::where('products_id', $product_id)
        ->where('fingerprint', $fingerprint)
        ->first();

    if ($existingRating) {
        // Update existing like status
        $existingRating->update(['liked' => $liked]);
    } else {
        // Create new record just for the like
        $existingRating = ProductRating::create([
            'products_id' => $product_id,
            'liked' => $request->boolean('liked') ? 1 : 0,
            'fingerprint' => $fingerprint,
            'ip' => $request->ip(),
            'device' => (new Agent())->device(),
            'meta' => [
                'browser' => (new Agent())->browser(),
                'platform' => (new Agent())->platform(),
            ]
        ]);
    }

    // Update product likes count
    $product->update([
        'likes' => $product->ratings()->where('liked', true)->count()
    ]);

    return response()->json([
        'success' => true,
        'likes_count' => $product->fresh()->likes,
        'user_liked' => $existingRating->liked
    ]);
}

    // Rate product
   public function rateProduct($product_id, Request $request)
{
    $request->validate([
        'rating' => 'required|numeric|min:1|max:5',
        'review' => 'nullable|string|max:1000',
        'name' => 'nullable|string|max:100',
        'fingerprint' => 'required|string'
    ]);

    $product = Products::findOrFail($product_id);
    $fingerprint = $request->fingerprint;

    // Check if this user already rated the product
    $existingRating = ProductRating::where('products_id', $product_id)
        ->where('fingerprint', $fingerprint)
        ->first();

    $agent = new Agent();
    $meta = [
        'browser' => $agent->browser(),
        'platform' => $agent->platform(),
        'name' => $request->name // Store the optional name
    ];

    if ($existingRating) {
        // Update existing rating
        $ratingDelta = $request->rating - ($existingRating->rating ?? 0);
        $existingRating->update([
            'rating' => $request->rating,
            'review' => $request->review ?? $existingRating->review,
            'meta' => array_merge($existingRating->meta ?? [], $meta)
        ]);
    } else {
        // Create new rating
        $existingRating = ProductRating::create([
            'products_id' => $product_id,
            'rating' => $request->rating,
            'review' => $request->review,
            'fingerprint' => $fingerprint,
            'ip' => $request->ip(),
            'device' => $agent->device(),
            'meta' => $meta
        ]);
        $ratingDelta = $request->rating;
    }

    // Update product rating stats
    $totalRatings = $product->ratings()->whereNotNull('rating')->count();
    $currentAverage = $product->average_rating ?? 0;
    $newAverage = ($currentAverage * ($totalRatings - 1) + $ratingDelta) / $totalRatings;
    
    $product->update([
        'average_rating' => $newAverage,
        'ratings_count' => $totalRatings
    ]);

    return response()->json([
        'success' => true,
        'data' => [
            'average_rating' => $newAverage,
            'rating_count' => $totalRatings,
            'user_rating' => $existingRating->rating
        ]
    ]);
}
    protected function generateFingerprint(Request $request)
    {
        // Create a more persistent fingerprint than just IP
        $agent = new Agent();
        $components = [
            $request->ip(),
            $agent->device(),
            $agent->browser(),
            $agent->platform(),
            $request->header('User-Agent'),
            $request->cookie('laravel_session')
        ];

        return md5(implode('|', array_filter($components)));
    }

    protected function getFootprintingData(Request $request)
    {
        $agent = new Agent();

        return [
            'user_agent' => $request->header('User-Agent'),
            'languages' => $request->getLanguages(),
            'accept' => $request->header('Accept'),
            'encoding' => $request->header('Accept-Encoding'),
            'connection' => $request->header('Connection'),
            'screen_resolution' => $request->input('screen_resolution'), 
            'timezone' => $request->input('timezone'), 
            'device_memory' => $request->input('device_memory'), 
            'hardware_concurrency' => $request->input('hardware_concurrency'),
        ];
    }

    protected function updateProductStats(Products $product, $ratingDelta, $liked)
    {
        // Update average rating
        $totalRatings = $product->ratings()->count();
        $currentAverage = $product->average_rating ?? 0;
        $newAverage = ($currentAverage * ($totalRatings - 1) + $ratingDelta) / $totalRatings;

        
        $product->update([
            'average_rating' => $newAverage,
            'ratings_count' => $totalRatings
        ]);
    }

    public function checkRating($product_id, Request $request)
{
    $request->validate([
        'fingerprint' => 'required|string'
    ]);

    $rating = ProductRating::where('products_id', $product_id)
        ->where('fingerprint', $request->fingerprint)
        ->first();

    return response()->json([
        'success' => true,
        'hasRated' => $rating !== null,
        'rating' => $rating ? $rating->rating : 0,
        'liked' => $rating ? $rating->liked : false,
        'review' => $rating ? $rating->review : null
    ]);
}

public function deleteReview(Request $request, $product_id, $review_id) {

    $rating = ProductRating::where('products_id', $product_id)
        ->where('id', $review_id)
        ->first();

    if (!$rating) {
        return response()->json([
            'success' => false,
            'message' => 'Review not found or you do not have permission to delete it.'
        ], 404);
    }

    // Delete the review
    $rating->delete();

    // Update product stats
    $product = Products::findOrFail($product_id);
    $this->updateProductStats($product, 0, false);

    return response()->json([
        'success' => true,
        'message' => 'Review deleted successfully'
    ]);
}

}
