<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class LocationService
{
    public function getLocation(string $ip): ?array
    {
        $location = $this->fromIpInfo($ip);

        if (! $location) {
            $location = $this->fromIpGeolocation($ip);
        }

        return $location;
    }

    protected function fromIpInfo(string $ip): ?array
    {
        try {
            $token = config('services.ipinfo.token');
            $response = Http::get("https://ipinfo.io/{$ip}?token={$token}");

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable $e) {}

        return null;
    }

    protected function fromIpGeolocation(string $ip): ?array
    {
        try {
            $apiKey = config('services.ipgeolocation.key');
            $response = Http::get("https://api.ipgeolocation.io/ipgeo", [
                'apiKey' => $apiKey,
                'ip' => $ip,
            ]);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable $e) {}

        return null;
    }
    public function extractLocationField(array $locationData, string $field): ?string
    {
        // Handle different API response formats
        switch ($field) {
            case 'country':
                return $locationData['country'] ?? $locationData['country_name'] ?? null;
            case 'region':
                return $locationData['region'] ?? $locationData['state_prov'] ?? null;
            case 'city':
                return $locationData['city'] ?? null;
            default:
                return null;
        }
    }
}
