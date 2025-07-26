<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;
use App\Models\Product;

/**
 * 
 *
 * @property int $id
 * @property int $user_id
 * @property string $business_name
 * @property string $slug
 * @property string $business_email
 * @property string $location
 * @property array $social_handles
 * @property array $delivery_options
 * @property string $whatsapp_number
 * @property string|null $logo_url
 * @property string|null $cover_url
 * @property string $theme_id
 * @property string|null $description
 * @property string|null $category
 * @property int $is_active
 * @property int $setup_completed
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stores newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stores newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stores query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stores whereBusinessName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stores whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stores whereCoverUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stores whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stores whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stores whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stores whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stores whereLogoUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stores whereThemeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stores whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stores whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stores whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Stores whereWhatsappNumber($value)
 * @mixin \Eloquent
 */
class Stores extends Model
{
   protected $fillable = [
    'user_id',
    'business_name', 
    'slug',
    'category',
    'description',
    'location',
    'whatsapp_number',
    'business_email',
    'social_handles',
    'delivery_options',
    'is_active',
    'theme_id'
];

 protected $casts = [
        'social_handles' => 'array',
        'delivery_options' => 'array',
        'is_active' => 'boolean',
        'average_rating' => 'integer'
    ];

protected $appends = [
    'average_rating',
    'total_rating',
    'ratings'
];
    
public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

public function products(): HasMany {
    return $this->hasMany(Products::class);
}
public function ratings()
{
    return $this->hasMany(StoreRating::class);
}

public function averageRating()
{
    return $this->ratings()->avg('rating');
}

public function totalRatings()
{
    return $this->ratings()->count();
}

public function getAverageRatingAttribute(){
    return $this->ratings()->avg('rating');
}

public function getTotalRatingAttribute(){
    return $this->ratings()->count();
}

public function getRatingsAttribute(){
    return $this->ratings()->get();
}
}
