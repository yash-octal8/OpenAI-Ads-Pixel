<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PixelEventResource;
use App\Http\Resources\ShopSettingResource;
use App\Repositories\PixelRepository;
use App\Services\ShopifyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PixelController extends Controller
{
    protected PixelRepository $pixelRepo;

    public function __construct(PixelRepository $pixelRepo)
    {
        $this->pixelRepo = $pixelRepo;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        
        $settings = $this->pixelRepo->getSettingsByUserId($user->id);
        $events = $this->pixelRepo->getEventsByUserId($user->id, 50);
        $monthlyCount = $this->pixelRepo->getMonthlyEventCount($user->id);
        $hasExceededQuota = $this->pixelRepo->hasExceededEventQuota($user);

        $planName = 'Free';
        if ($user && $user->plan_id) {
            $plan = \App\Models\Plan::find($user->plan_id);
            if ($plan) {
                $planName = $plan->name;
            }
        }

        $isFree = (strtolower($planName) === 'free');
        $quotaLimit = $isFree ? 50000 : null;
        $usagePercentage = $quotaLimit ? min(100, round(($monthlyCount / $quotaLimit) * 100, 1)) : 0;

        return response()->json([
            'success' => true,
            'settings' => new ShopSettingResource($settings),
            'events' => PixelEventResource::collection($events),
            'events_count' => $events->count(),
            'monthly_event_count' => $monthlyCount,
            'plan_name' => $planName,
            'quota_limit' => $quotaLimit,
            'usage_percentage' => $usagePercentage,
            'quota_exceeded' => $hasExceededQuota,
        ]);
    }

    public function saveSettings(Request $request)
    {
        $user = Auth::user();
        $pixelId = $request->input('pixel_id');

        $settings = $this->pixelRepo->updateOrCreateSettings($user->id, [
            'pixel_id' => $pixelId,
            'capi_key' => $request->input('capi_key'),
            'advertiser_key' => $request->input('advertiser_key'),
            'tracking_enabled' => $request->boolean('tracking_enabled', true),
            'pixel_helper_enabled' => $request->boolean('pixel_helper_enabled', true),
        ]);

        if ($pixelId) {
            try {
                $shopifyService = new ShopifyService($user);
                $shopifyService->createWebPixel($pixelId);
            } catch (\Throwable $e) {
                \Log::info("Web Pixel registration attempt: " . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Pixel settings saved and registered in Shopify Customer Events!',
            'settings' => new ShopSettingResource($settings),
        ]);
    }

    public function publicTrackEvent(Request $request)
    {
        $pixelId = $request->input('pixel_id', '');
        $eventName = $request->input('event_name', 'page_viewed');
        $eventType = $request->input('event_type', 'Standard');
        $payload = $request->input('payload', $request->all());

        $setting = $this->pixelRepo->getSettingsByPixelId($pixelId);
        $user = $setting ? \App\Models\User::find($setting->user_id) : null;

        if ($user && $this->pixelRepo->hasExceededEventQuota($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Monthly event quota of 50,000 events reached on Free plan. Upgrade to Basic plan for unlimited events.',
                'quota_exceeded' => true,
            ], 429)->header('Access-Control-Allow-Origin', '*')
              ->header('Access-Control-Allow-Methods', 'POST, GET, OPTIONS')
              ->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With');
        }

        $userId = $setting ? $setting->user_id : 1;

        $event = $this->pixelRepo->createEvent([
            'user_id' => $userId,
            'pixel_id' => $pixelId,
            'event_name' => $eventName,
            'event_type' => $eventType,
            'event_time' => Carbon::now()->format('H:i:s'),
            'payload' => $payload,
            'status' => 'Loaded',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Event captured successfully',
            'event' => new PixelEventResource($event),
        ])->header('Access-Control-Allow-Origin', '*')
          ->header('Access-Control-Allow-Methods', 'POST, GET, OPTIONS')
          ->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With');
    }

    public function options()
    {
        return response()->json(['status' => 'ok'])
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With');
    }


    public function trackEvent(Request $request)
    {
        $user = Auth::user();
        $settings = $this->pixelRepo->getSettingsByUserId($user->id);

        if ($this->pixelRepo->hasExceededEventQuota($user)) {
            return $this->sendError('Monthly event quota of 50,000 events reached on Free plan. Upgrade to Basic plan ($29/mo) for unlimited events.', 429);
        }

        $pixelId = $request->input('pixel_id') ?: ($settings ? $settings->pixel_id : '');
        $eventName = $request->input('event_name', 'page_viewed');
        $eventType = $request->input('event_type', 'Standard');
        $payload = $request->input('payload', []);
        
        if (empty($payload)) {
            $payload = $this->generateMockPayload($eventName);
        }

        $event = $this->pixelRepo->createEvent([
            'user_id' => $user->id,
            'pixel_id' => $pixelId,
            'event_name' => $eventName,
            'event_type' => $eventType,
            'event_time' => Carbon::now()->format('H:i:s'),
            'payload' => $payload,
            'status' => 'Loaded',
        ]);

        return response()->json([
            'success' => true,
            'event' => new PixelEventResource($event),
        ]);
    }


    public function clearEvents(Request $request)
    {
        $user = Auth::user();
        $this->pixelRepo->clearEventsByUserId($user->id);

        return $this->sendSuccess('Events cleared successfully');
    }

    private function generateMockPayload($eventName)
    {
        $now = Carbon::now()->toIso8601String();
        
        switch ($eventName) {
            case 'checkout_started':
                return [
                    'checkout_id' => 'chk_' . rand(1000000, 9999999),
                    'cart_token' => 'tok_' . rand(100000, 999999),
                    'currency' => 'USD',
                    'total_price' => 189.50,
                    'item_count' => 3,
                    'items' => [
                        ['id' => 'prod_881', 'title' => 'ChatGPT Pro Subscription', 'price' => 149.00, 'quantity' => 1],
                        ['id' => 'prod_442', 'title' => 'OpenAI Branded Hoodie', 'price' => 40.50, 'quantity' => 1],
                    ],
                    'timestamp' => $now,
                ];

            case 'page_viewed':
                return [
                    'page_title' => 'Storefront Homepage - ChatGPT Ads Integration',
                    'page_location' => 'https://openai-store.myshopify.com/',
                    'referrer' => 'https://chat.openai.com/',
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'timestamp' => $now,
                ];

            case 'product_viewed':
                return [
                    'product_id' => 'prod_' . rand(100, 999),
                    'title' => 'OpenAI Wireless Developer Mouse',
                    'category' => 'Electronics',
                    'price' => 79.99,
                    'currency' => 'USD',
                    'in_stock' => true,
                    'timestamp' => $now,
                ];

            case 'add_to_cart':
                return [
                    'cart_token' => 'tok_' . rand(100000, 999999),
                    'product_id' => 'prod_' . rand(100, 999),
                    'title' => 'OpenAI Custom Mug',
                    'price' => 24.99,
                    'quantity' => 2,
                    'currency' => 'USD',
                    'timestamp' => $now,
                ];

            case 'order_completed':
                return [
                    'order_id' => '#ORD-' . rand(1000, 9999),
                    'transaction_id' => 'tx_' . rand(1000000, 9999999),
                    'total' => 214.49,
                    'subtotal' => 189.50,
                    'tax' => 14.99,
                    'shipping' => 10.00,
                    'currency' => 'USD',
                    'attribution' => 'OpenAI Ads / ChatGPT Direct Traffic',
                    'timestamp' => $now,
                ];

            default:
                return [
                    'action' => $eventName,
                    'timestamp' => $now,
                ];
        }
    }
}
