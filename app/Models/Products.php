<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\ProductImage;
use App\Models\Stores as Store;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


/**
 *
 * @property int $id
 * @property int $store_id
 * @property string $name
 * @property string|null $description
 * @property string $price
 * @property string|null $category
 * @property int $is_active
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|products newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|products newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|products query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|products whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|products whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|products whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|products whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|products whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|products whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|products wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|products whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|products whereStoreId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|products whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Products extends Model
{
    protected $fillable = [
        'stores_id',
        'name',
        'slug',
        'description',
        'price',
        'category',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];


    protected $appends = [
        'shareable_url',
        'primary_image',
        'formatted_price',
        'average_rating',
        'ratings_count',
        'total_likes',
        'reviews',
        'views_count'
    ];
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'stores_id');
    }

    /**
     * Get all images for the product
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'products_id');
    }

    /**
     * Get the primary image
     */
    public function primaryImage()
    {
        return $this->images()->whereRaw("JSON_EXTRACT(image_meta, '$.is_primary') = true")->first();
    }

    /**
     * Get shareable URL attribute
     */
    public function getShareableUrlAttribute(): string
    {
        return url("/products/{$this->slug}");
    }

    public function getPrimaryImageAttribute()
    {
        return $this->primaryImage();
    }

    /**
     * Get formatted price attribute
     */
    public function getFormattedPriceAttribute(): string
    {
        return '₦' . number_format($this->price, 2);
    }

    /**
     * Scope for active products
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for products by category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get route key name for model binding
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function ratings()
    {
        return $this->hasMany(ProductRating::class);
    }

    public function getAverageRatingAttribute()
    {
        return $this->ratings()->avg('rating') ?? 0;
    }

    public function getRatingsCountAttribute()
    {
        return $this->ratings()->count();
    }

    public function getTotalLikesAttribute()
    {
        return $this->ratings()->sum('liked') ?? 0;
    }

    public function getReviewsAttribute()
    {
        return $this->ratings()->get();
    }

    public function reviews()
    {
        return $this->hasMany(ProductRating::class, 'products_id');
    }

    public function views()
    {
        return $this->hasMany(ProductView::class, 'products_id');
    }


    public function getViewsCountAttribute()
    {
        return $this->views()->count();
    }

}
