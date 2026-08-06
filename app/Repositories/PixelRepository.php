<?php

namespace App\Repositories;

use App\Models\ShopSetting;
use App\Models\PixelEvent;
use Carbon\Carbon;

class PixelRepository
{
    public function getSettingsByUserId(int $userId): ShopSetting
    {
        return ShopSetting::firstOrCreate(
            ['user_id' => $userId],
            [
                'pixel_id' => '',
                'capi_key' => '',
                'advertiser_key' => '',
                'tracking_enabled' => true,
                'pixel_helper_enabled' => true,
            ]
        );
    }

    public function updateOrCreateSettings(int $userId, array $data): ShopSetting
    {
        return ShopSetting::updateOrCreate(
            ['user_id' => $userId],
            [
                'pixel_id' => $data['pixel_id'] ?? '',
                'capi_key' => $data['capi_key'] ?? '',
                'advertiser_key' => $data['advertiser_key'] ?? '',
                'tracking_enabled' => $data['tracking_enabled'] ?? true,
                'pixel_helper_enabled' => $data['pixel_helper_enabled'] ?? true,
            ]
        );
    }

    public function getSettingsByPixelId(string $pixelId): ?ShopSetting
    {
        return ShopSetting::where('pixel_id', $pixelId)->first();
    }

    public function getEventsByUserId(int $userId, int $limit = 50)
    {
        return PixelEvent::where('user_id', $userId)
            ->latest()
            ->take($limit)
            ->get();
    }


    public function createEvent(array $data): PixelEvent
    {
        return PixelEvent::create([
            'user_id' => $data['user_id'] ?? null,
            'pixel_id' => $data['pixel_id'] ?? '',
            'event_name' => $data['event_name'] ?? 'page_viewed',
            'event_type' => $data['event_type'] ?? 'Standard',
            'event_time' => $data['event_time'] ?? Carbon::now()->format('H:i:s'),
            'payload' => $data['payload'] ?? [],
            'status' => $data['status'] ?? 'Loaded',
        ]);
    }

    public function clearEventsByUserId(int $userId): bool
    {
        return PixelEvent::where('user_id', $userId)->delete();
    }


    public function getMonthlyEventCount(int $userId): int
    {
        return PixelEvent::where('user_id', $userId)
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();
    }


    public function hasExceededEventQuota($user): bool
    {
        if (!$user) {
            return false;
        }

        $plan = null;
        if ($user->plan_id) {
            $plan = \App\Models\Plan::find($user->plan_id);
        }

        // Free plan logic: if no plan set, or plan is Free, or price is 0
        $isFreePlan = !$plan || strtolower($plan->name) === 'free' || (float)$plan->price == 0.00;

        if ($isFreePlan) {
            $count = $this->getMonthlyEventCount($user->id);
            return $count >= 50000;
        }

        return false;
    }
}
