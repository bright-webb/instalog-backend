<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Models\IpLocation;

class IpLocationService
{
    protected $token;
    protected $cacheTtl;

    public function __construct()
    {
        $this->token = config('services.ipinfo.token');
        $this->cacheTtl = config('services.ipinfo.cache_ttl', 86400);
    }

    public function getLocation(string $ip): ?array
    {
        $cached = IpLocation::where('ip_address', $ip)->first();
        if ($cached) {
            return [
                'country' => $cached->country,
                'region' => $cached->region,
                'city' => $cached->city,
                'latitude' => $cached->latitude,
                'longitude' => $cached->longitude,
            ];
        }

        // Check cache
        $cacheKey = "ip_location_{$ip}";
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = Http::withToken($this->token)
                ->get("https://ipinfo.io/lite/{$ip}?token={$this->token}");

            if ($response->successful()) {
                $data = $response->json();
                
                // Save to database for future use
                $location = IpLocation::create([
                    'ip_address' => $ip,
                    'country' => $data['country'] ?? null,
                    'region' => $data['region'] ?? null,
                    'city' => $data['city'] ?? null,
                    'latitude' => isset($data['loc']) ? explode(',', $data['loc'])[0] : null,
                    'longitude' => isset($data['loc']) ? explode(',', $data['loc'])[1] : null,
                    'timezone' => $data['timezone'] ?? null,
                    'original_data' => $data,
                ]);

                // Cache the result
                $result = [
                    'country' => $location->country,
                    'region' => $location->region,
                    'city' => $location->city,
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                ];
                
                Cache::put($cacheKey, $result, $this->cacheTtl);
                
                return $result;
            }
        } catch (\Exception $e) {
            \Log::error("IP location lookup failed for {$ip}: " . $e->getMessage());
        }

        return null;
    }
}