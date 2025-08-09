<?php

namespace App\Services;
use App\Models\Stores as Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class StoreService {
    /**
     * Create a new store
     * 
     * @param array $data
     * @return array
     */
    public function createStore(array $data, User $user): array {
        try {
            DB::beginTransaction();
            
            if(!$user) {
                throw new Exception("User not found or authenticated");
            }

            // Check if user already has a store
            if($user->store()->exists()){
                throw new Exception('You already have a store');
            }

            // Generate unique store slug from business name
            $slug = $this->generateSlug($data['businessName']);

            // Create store
            $store = Store::create([
                'user_id' => $user->id,
                'business_name' => $data['businessName'],
                'slug' => $slug,
                'category' => $data['category'],
                'description' => $data['description'],
                'location' => $data['location'],
                'city' => $data['city'] ?? null,
                'country' => $data['country'] ?? null,
                'whatsapp_number' => $data['whatsappNumber'],
                'social_handles' => json_encode($this->processSocialHandles($data['socialHandles'] ?? [])),
                'delivery_options' => json_encode($data['deliveryOptions'] ?? []),
                'is_active' => true,
                'theme_id' => 'modern'
            ]);

            DB::commit();
             return [
                'success' => true,
                'message' => 'Store created successfully',
                'data' => [
                    'store' => $store->load('user'),
                    'store_url' => env('FRONTEND').'/'.$store->slug
                ]
            ];
 
        } catch(Exception $e) {
            DB::rollback();

            return [
                'success' => false,
                'message' => 'Something went wrong. Please try again',
                'error' => $e->getMessage(),
                'data' => null
            ];
        }
       
    } 

    /**
     * Generate a unique slug for store
     * @param string $businessName
     * @return string
    */
        private function generateSlug(string $businessName): string {
        // Ensure business name is not empty
        if (empty(trim($businessName))) {
            throw new Exception('Business name cannot be empty');
        }
        
        $baseSlug = Str::slug($businessName);
        
        // If Str::slug returns empty, create a fallback
        if (empty($baseSlug)) {
            $baseSlug = 'store-' . time();
        }
        
        $slug = $baseSlug;
        $counter = 1;

        while(Store::where('slug', $slug)->exists()){
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function processSocialHandles(array $socialHandles): array {
        
        $processedHandles = [];

        foreach($socialHandles as $platform => $handle){
            if(!empty(trim($handle))){
                // Clean and remove the @ symbol if present
                $cleanedHandle = trim($handle);

                switch(strtolower($platform)){
                    case 'instagram':
                    case 'twitter':
                    case 'tiktok':
                        // Remove the @ symbol if present
                        $cleanedHandle = ltrim($cleanedHandle, '@');
                        break;
                    case 'facebook':
                        if(!str_starts_with($cleanedHandle, 'http')){
                            $cleanedHandle = 'https://' . $cleanedHandle;
                        }
                        break;
                }
                $processedHandles[$platform] = $cleanedHandle;
            }
        }
        return $processedHandles;
    }

   /**
    * Update store information
    *
    * @param Store $store
    * @param array $data
    * @return array
    */
    public function updateStore(Store $store, array $data): array {
        try {
            DB::beginTransaction();

            $updatedData = [
                'business_name' => $data['businessName'] ?? $store->business_name,
                'category' => $data['category'] ?? $store->category,
                'description' => $data['description'] ?? $store->description,
                'location' => $data['location'] ?? $store->location,
                'business_email' => $data['businessEmail'] ?? $store->business_name,
            ];

            // Only update WhatsApp number if provided and different
            if (isset($data['whatsappNumber']) && $data['whatsappNumber'] !== $store->whatsapp_number) {
                $updateData['whatsapp_number'] = $data['whatsappNumber'];
            }

            // Update slug if business name changed
            if (isset($data['businessName']) && $data['businessName'] !== $store->business_name) {
                $updateData['slug'] = $this->generateSlug($data['businessName']);
            }

            // Update optional fields
            if (isset($data['socialHandles'])) {
                $updateData['social_handles'] = $this->processSocialHandles($data['socialHandles']);
            }

            if (isset($data['deliveryOptions'])) {
                $updateData['delivery_options'] = $data['deliveryOptions'];
            }

            $store->update($updatedData);
            DB::commit();

            return [
                'success' => true,
                'messae' => 'Store updated successfully',
                'data' => [
                    'store' => $store->fresh()->load('user')
                ]
            ];
        } catch(Exception $e){
            DB::rollback();

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }


    /**
     * Get store by slug
     * 
     * @param string $slug
     * @return void
     */
    public function getStoreBySlug(string $slug): ?Store {
        return Store::where('slug', $slug)
                    ->where('is_active', true)
                    ->with(['user', 'products'])
                    ->first();
    }
}