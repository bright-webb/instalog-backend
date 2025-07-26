<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Products;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'products_id',
        'image_url',
        'image_meta',
        'image_name',
        'image_size'
    ];

    protected $casts = [
        'image_meta' => 'array'
    ];

    protected $appends = [
        'is_primary',
        'sort_order',
        'full_url'
    ];

    /**
     * Get the product that owns the image
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Products::class);
    }

    public function productView(){
        return $this->belongsTo(ProductView::class, 'products_id');
    }

    /**
     * Get is primary attribute
     */
    public function getIsPrimaryAttribute(): bool
    {
        return $this->image_meta['is_primary'] ?? false;
    }

    /**
     * Get sort order attribute
     */
    public function getSortOrderAttribute(): int
    {
        return $this->image_meta['sort_order'] ?? 0;
    }

    /**
     * Get full URL attribute
     */
    public function getFullUrlAttribute(): string
    {
        if (str_starts_with($this->image_url, 'http')) {
            return $this->image_url;
        }
        
        return url($this->image_url);
    }

    /**
     * Scope for primary images
     */
    public function scopePrimary($query)
    {
        return $query->whereRaw("JSON_EXTRACT(image_meta, '$.is_primary') = true");
    }

    /**
     * Scope for ordered images
     */
    public function scopeOrdered($query)
    {
        return $query->orderByRaw("JSON_EXTRACT(image_meta, '$.sort_order') ASC");
    }
}