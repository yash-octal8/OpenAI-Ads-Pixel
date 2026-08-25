<?php

namespace App\Repositories;

use App\Models\ShopSetting;
use App\Models\PixelEvent;
use App\Models\Pixel;
use App\Models\Attribution;
use App\Models\User;
use Carbon\Carbon;

class PixelRepository
{
    public function getSettingsByUserId(int $userId): ShopSetting
    {
        $user = User::find($userId);
        $defaultPixelId = $user ? ('oai_px_' . substr(md5($user->name . '_' . $user->id), 0, 12)) : ('oai_px_' . rand(100000, 999999));

        $setting = ShopSetting::firstOrCreate(
            ['user_id' => $userId],
            [
                'pixel_id' => $defaultPixelId,
                'capi_key' => '',
                'advertiser_key' => '',
                'tracking_enabled' => true,
                'pixel_helper_enabled' => true,
            ]
        );

        if (empty($setting->pixel_id)) {
            $setting->pixel_id = $defaultPixelId;
            $setting->save();
        }

        return $setting;
    }

    public function updateOrCreateSettings(int $userId, array $data): ShopSetting
    {
        $settings = ShopSetting::updateOrCreate(
            ['user_id' => $userId],
            [
                'pixel_id' => $data['pixel_id'] ?? '',
                'capi_key' => $data['capi_key'] ?? '',
                'advertiser_key' => $data['advertiser_key'] ?? '',
                'tracking_enabled' => $data['tracking_enabled'] ?? true,
                'pixel_helper_enabled' => $data['pixel_helper_enabled'] ?? true,
            ]
        );

        // Sync with primary Pixel in pixels table
        if (!empty($data['pixel_id'])) {
            Pixel::updateOrCreate(
                ['user_id' => $userId, 'pixel_id' => $data['pixel_id']],
                [
                    'name' => 'GPT Pixel — Main Store',
                    'capi_key' => $data['capi_key'] ?? '',
                    'status' => 'active',
                    'coverage_type' => 'entire_store',
                ]
            );
        }

        return $settings;
    }

    /**
     * Get settings by Pixel ID.
     */
    public function getSettingsByPixelId(string $pixelId): ?ShopSetting
    {
        return ShopSetting::where('pixel_id', $pixelId)->first();
    }

    /**
     * Resolve User ID by matching Pixel ID across settings & multi-pixels tables.
     */
    public function findUserIdByPixelId(string $pixelId): int
    {
        if (!empty($pixelId)) {
            $setting = ShopSetting::where('pixel_id', $pixelId)->first();
            if ($setting) {
                return $setting->user_id;
            }

            $pixel = Pixel::where('pixel_id', $pixelId)->first();
            if ($pixel) {
                return $pixel->user_id;
            }
        }

        $firstUser = User::first();
        return $firstUser ? $firstUser->id : 1;
    }

    public function getPixelsByUserId(int $userId)
    {
        $pixels = Pixel::where('user_id', $userId)->latest()->get();

        if ($pixels->isEmpty()) {
            $setting = $this->getSettingsByUserId($userId);
            if (!empty($setting->pixel_id)) {
                Pixel::create([
                    'user_id' => $userId,
                    'name' => 'OpenAI Pixel — Main Store',
                    'pixel_id' => $setting->pixel_id,
                    'capi_key' => $setting->capi_key,
                    'status' => 'active',
                    'coverage_type' => 'entire_store',
                ]);
                $pixels = Pixel::where('user_id', $userId)->latest()->get();
            }
        }

        return $pixels;
    }

    /**
     * Create a pixel for a user
     */
    public function createPixel(int $userId, array $data): Pixel
    {
        $pixel = Pixel::create([
            'user_id' => $userId,
            'name' => $data['name'] ?? 'GPT Pixel',
            'pixel_id' => $data['pixel_id'],
            'capi_key' => $data['capi_key'] ?? null,
            'status' => $data['status'] ?? 'active',
            'test_mode' => $data['test_mode'] ?? false,
            'coverage_type' => $data['coverage_type'] ?? 'entire_store',
            'target_selection' => $data['target_selection'] ?? null,
        ]);

        if (!empty($data['pixel_id'])) {
            ShopSetting::updateOrCreate(
                ['user_id' => $userId],
                [
                    'pixel_id' => $data['pixel_id'],
                    'capi_key' => $data['capi_key'] ?? '',
                ]
            );
        }

        return $pixel;
    }

    /**
     * Update a pixel
     */
    public function updatePixel(int $pixelDbId, int $userId, array $data): Pixel
    {
        $pixel = Pixel::where('id', $pixelDbId)->where('user_id', $userId)->firstOrFail();
        $pixel->update($data);

        // Sync with ShopSetting if pixel_id or capi_key updated
        if (!empty($data['pixel_id'])) {
            ShopSetting::updateOrCreate(
                ['user_id' => $userId],
                [
                    'pixel_id' => $data['pixel_id'],
                    'capi_key' => $data['capi_key'] ?? '',
                ]
            );
        }

        return $pixel;
    }

    /**
     * Delete a pixel
     */
    public function deletePixel(int $pixelDbId, int $userId): bool
    {
        $pixel = Pixel::where('id', $pixelDbId)->where('user_id', $userId)->first();
        if (!$pixel) {
            return false;
        }

        $deletedPixelId = $pixel->pixel_id;
        $deleted = $pixel->delete();

        if ($deleted) {
            $setting = ShopSetting::where('user_id', $userId)->where('pixel_id', $deletedPixelId)->first();
            if ($setting) {
                $nextPixel = Pixel::where('user_id', $userId)->where('status', 'active')->first();
                $setting->pixel_id = $nextPixel ? $nextPixel->pixel_id : '';
                $setting->capi_key = $nextPixel ? ($nextPixel->capi_key ?? '') : '';
                $setting->save();
            }
        }

        return $deleted;
    }

    /**
     * Get latest events for a user with optional filtering & pagination
     */
    public function getEventsByUserId(int $userId, int $limit = 50, ?string $search = null, ?string $eventFilter = null, ?string $sourceFilter = null)
    {
        $query = PixelEvent::where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhereNull('user_id');
        });

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('event_name', 'like', "%{$search}%")
                  ->orWhere('event_id', 'like', "%{$search}%")
                  ->orWhere('pixel_id', 'like', "%{$search}%")
                  ->orWhere('oppref', 'like', "%{$search}%");
            });
        }

        if ($eventFilter && $eventFilter !== 'all') {
            $query->where('event_name', $eventFilter);
        }

        if ($sourceFilter && $sourceFilter !== 'all') {
            $query->where('source', $sourceFilter);
        }

        return $query->latest()->take($limit)->get();
    }

    /**
     * Create a new pixel event and record attribution if applicable
     */
    public function createEvent(array $data): PixelEvent
    {
        $eventId = $data['event_id'] ?? ('evt_' . time() . '_' . rand(1000, 9999));
        $eventName = $data['event_name'] ?? 'page_viewed';
        $payload = $data['payload'] ?? [];
        $rawOppref = $data['oppref'] ?? $payload['oppref'] ?? null;
        $oppref = is_string($rawOppref) ? $rawOppref : (is_scalar($rawOppref) ? (string)$rawOppref : null);
        $orderId = $data['order_id'] ?? $payload['order_id'] ?? null;
        $userId = $data['user_id'] ?? 1;

        $event = PixelEvent::create([
            'user_id' => $userId,
            'pixel_id' => $data['pixel_id'] ?? '',
            'event_id' => $eventId,
            'event_name' => $eventName,
            'event_type' => $data['event_type'] ?? 'Standard',
            'source' => $data['source'] ?? 'Browser',
            'oppref' => $oppref,
            'order_id' => $orderId,
            'event_time' => $data['event_time'] ?? Carbon::now()->format('H:i:s'),
            'payload' => $payload,
            'response_code' => $data['response_code'] ?? 200,
            'response_body' => $data['response_body'] ?? null,
            'status' => $data['status'] ?? 'Loaded',
        ]);

        // Record attribution if purchase event
        if (in_array(strtolower($eventName), ['checkout_completed', 'order_completed', 'purchase']) && $userId) {
            $revenue = floatval($payload['total_price'] ?? $payload['total'] ?? $payload['price'] ?? 0.00);
            Attribution::create([
                'user_id' => $userId,
                'pixel_id' => null,
                'shopify_order_id' => $orderId ?? ('#ORD-' . rand(1000, 9999)),
                'order_number' => $orderId ?? ('#ORD-' . rand(1000, 9999)),
                'event_id' => $eventId,
                'oppref' => $oppref ?? ('oppref_' . rand(100000, 999999)),
                'revenue' => $revenue,
                'currency' => $payload['currency'] ?? 'USD',
                'event_time' => Carbon::now(),
            ]);
        }

        return $event;
    }

    /**
     * Clear events for a user.
     */
    public function clearEventsByUserId(int $userId): bool
    {
        return PixelEvent::where('user_id', $userId)->orWhereNull('user_id')->delete();
    }


    public function getMonthlyEventCount(int $userId): int
    {
        return PixelEvent::where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhereNull('user_id');
        })
        ->whereMonth('created_at', Carbon::now()->month)
        ->whereYear('created_at', Carbon::now()->year)
        ->count();
    }

    /**
     * Check if user has exceeded monthly event limit on Free plan (50,000 events)
     */
    public function hasExceededEventQuota($user): bool
    {
        if (!$user) {
            return false;
        }

        $plan = null;
        if ($user->plan_id) {
            $plan = \App\Models\Plan::find($user->plan_id);
        }

        $isFreePlan = !$plan || strtolower($plan->name) === 'free' || (float)$plan->price == 0.00;

        if ($isFreePlan) {
            $count = $this->getMonthlyEventCount($user->id);
            return $count >= 50000;
        }

        return false;
    }
}
