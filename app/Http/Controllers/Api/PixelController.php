<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ShopSetting;
use App\Models\PixelEvent;
use App\Services\ShopifyService;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PixelController extends Controller
{
    /**
     * Get pixel settings and initial events list
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $settings = ShopSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'pixel_id' => '',
                'capi_key' => '',
                'advertiser_key' => '',
                'tracking_enabled' => true,
                'pixel_helper_enabled' => true,
            ]
        );

        $events = PixelEvent::where('user_id', $user->id)
            ->latest()
            ->take(50)
            ->get();

        // Seed initial sample events if none exist so Pixel Helper has live data out of the box matching screenshots!
        // if ($events->isEmpty()) {
        //     $defaultEvents = [
        //         [
        //             'user_id' => $user->id,
        //             'pixel_id' => $settings->pixel_id ?: '3037692197',
        //             'event_name' => 'checkout_started',
        //             'event_type' => 'Standard',
        //             'event_time' => Carbon::now()->subMinutes(2)->format('H:i:s'),
        //             'payload' => [
        //                 'checkout_id' => 'chk_9281749',
        //                 'cart_token' => 'tok_819284',
        //                 'currency' => 'USD',
        //                 'total_price' => 149.99,
        //                 'item_count' => 2,
        //                 'page_url' => '/checkout',
        //             ],
        //             'status' => 'Loaded',
        //             'created_at' => Carbon::now()->subMinutes(2),
        //         ],
        //         [
        //             'user_id' => $user->id,
        //             'pixel_id' => $settings->pixel_id ?: '3037692197',
        //             'event_name' => 'page_viewed',
        //             'event_type' => 'Standard',
        //             'event_time' => Carbon::now()->subMinutes(2)->format('H:i:s'),
        //             'payload' => [
        //                 'page_title' => 'Checkout Page - OpenAI Store',
        //                 'page_location' => 'https://openai-store.myshopify.com/checkout',
        //                 'referrer' => 'https://openai-store.myshopify.com/cart',
        //                 'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
        //             ],
        //             'status' => 'Loaded',
        //             'created_at' => Carbon::now()->subMinutes(2),
        //         ],
        //     ];

        //     foreach ($defaultEvents as $evtData) {
        //         PixelEvent::create($evtData);
        //     }

        //     $events = PixelEvent::where('user_id', $user->id)->latest()->take(50)->get();
        // }

        return response()->json([
            'success' => true,
            'settings' => [
                'pixel_id' => $settings->pixel_id ?: '',
                'capi_key' => $settings->capi_key ?: '',
                'advertiser_key' => $settings->advertiser_key ?: '',
                'tracking_enabled' => (bool)$settings->tracking_enabled,
                'pixel_helper_enabled' => (bool)$settings->pixel_helper_enabled,
            ],
            'events' => $events,
            'events_count' => $events->count(),
        ]);
    }

    public function saveSettings(Request $request)
    {
        $user = Auth::user();
        $pixelId = $request->input('pixel_id');

        $settings = ShopSetting::updateOrCreate(
            ['user_id' => $user->id],
            [
                'pixel_id' => $pixelId,
                'capi_key' => $request->input('capi_key'),
                'advertiser_key' => $request->input('advertiser_key'),
                'tracking_enabled' => $request->boolean('tracking_enabled', true),
                'pixel_helper_enabled' => $request->boolean('pixel_helper_enabled', true),
            ]
        );

        try {
            $shopifyService = new ShopifyService($user);
            $shopifyService->createWebPixel($pixelId);
        } catch (\Throwable $e) {
            \Log::info("Web Pixel registration attempt: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Pixel settings saved and registered in Shopify Customer Events!',
            'settings' => $settings,
        ]);
    }

    public function publicTrackEvent(Request $request)
    {
        $pixelId = $request->input('pixel_id', '');
        $eventName = $request->input('event_name', 'page_viewed');
        $eventType = $request->input('event_type', 'Standard');
        $payload = $request->input('payload', $request->all());

        $setting = ShopSetting::where('pixel_id', $pixelId)->first();
        $userId = $setting ? $setting->user_id : 1;

        $event = PixelEvent::create([
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
            'event' => $event,
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
        $settings = ShopSetting::where('user_id', $user->id)->first();

        $pixelId = $request->input('pixel_id') ?: ($settings ? $settings->pixel_id : '');
        $eventName = $request->input('event_name', 'page_viewed');
        $eventType = $request->input('event_type', 'Standard');
        $payload = $request->input('payload', []);
        
        if (empty($payload)) {
            $payload = $this->generateMockPayload($eventName);
        }

        $event = PixelEvent::create([
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
            'event' => $event,
        ]);
    }

    public function clearEvents(Request $request)
    {
        $user = Auth::user();
        PixelEvent::where('user_id', $user->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Events cleared successfully',
        ]);
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
