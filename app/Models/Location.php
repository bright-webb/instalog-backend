<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
     protected $fillable = [
        'user_id',
        'country',
        'region',
        'city',
        'ip_address',
    ];
}
