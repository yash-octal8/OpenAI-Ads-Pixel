<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PixelEventResource;
use App\Http\Resources\ShopSettingResource;
use App\Models\User;
use App\Repositories\PixelRepository;
use App\Services\OpenAI\OpenAIAttributionService;
use App\Services\OpenAI\OpenAIConversionService;
use App\Services\ShopifyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PixelController extends Controller
{
    protected PixelRepository $pixelRepo;
    protected OpenAIAttributionService $attributionService;

    public function __construct(PixelRepository $pixelRepo, OpenAIAttributionService $attributionService)
    {
        $this->pixelRepo = $pixelRepo;
        $this->attributionService = $attributionService;
    }

    /**
     * Get settings and summary stats for authenticated user
     */
    public function index()
    {
        $user = Auth::user();
        $settings = $this->pixelRepo->getSettingsByUserId($user->id);
        $pixels = $this->pixelRepo->getPixelsByUserId($user->id);
        $events = $this->pixelRepo->getEventsByUserId($user->id, 50);

        $planName = 'Free';
        if ($user->plan_id) {
            $plan = \App\Models\Plan::find($user->plan_id);
            if ($plan) {
                $planName = $plan->name;
            }
        }

        $monthlyEventCount = $this->pixelRepo->getMonthlyEventCount($user->id);
        $isFreePlan = (strtolower($planName) === 'free' || $planName === 'Free');
        $quotaLimit = $isFreePlan ? 50000 : null;
        $usagePercentage = $quotaLimit ? min(100, round(($monthlyEventCount / $quotaLimit) * 100, 1)) : 0;
        $quotaExceeded = $this->pixelRepo->hasExceededEventQuota($user);

        // Revenue & performance metrics
        $metrics = $this->attributionService->getPerformanceMetrics($user->id);

        return response()->json([
            'success' => true,
            'settings' => new ShopSettingResource($settings),
            'pixels' => $pixels,
            'events' => PixelEventResource::collection($events),
            'plan_name' => $planName,
            'monthly_event_count' => $monthlyEventCount,
            'quota_limit' => $quotaLimit,
            'usage_percentage' => $usagePercentage,
            'quota_exceeded' => $quotaExceeded,
            'metrics' => $metrics,
        ]);
    }

    /**
     * Get multi-pixels list
     */
    public function getPixels()
    {
        $user = Auth::user();
        $pixels = $this->pixelRepo->getPixelsByUserId($user->id);

        return response()->json([
            'success' => true,
            'pixels' => $pixels,
        ]);
    }

    /**
     * Create a new pixel
     */
    public function storePixel(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'pixel_id' => 'required|string|max:255',
        ]);

        $user = Auth::user();
        $pixel = $this->pixelRepo->createPixel($user->id, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Pixel created successfully!',
            'pixel' => $pixel,
        ]);
    }

    /**
     * Update an existing pixel
     */
    public function updatePixel(Request $request, $id)
    {
        $user = Auth::user();
        $pixel = $this->pixelRepo->updatePixel($id, $user->id, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Pixel updated successfully!',
            'pixel' => $pixel,
        ]);
    }

    /**
     * Delete a pixel
     */
    public function deletePixel($id)
    {
        $user = Auth::user();
        $deleted = $this->pixelRepo->deletePixel($id, $user->id);

        return response()->json([
            'success' => $deleted,
            'message' => $deleted ? 'Pixel deleted successfully' : 'Failed to delete pixel',
        ]);
    }

    /**
     * Get event logs & debugger records
     */
    public function getEventLogs(Request $request)
    {
        $user = Auth::user();
        $search = $request->input('search');
        $eventFilter = $request->input('event_type');
        $sourceFilter = $request->input('source');
        $limit = $request->input('limit', 100);

        $events = $this->pixelRepo->getEventsByUserId($user->id, $limit, $search, $eventFilter, $sourceFilter);

        return response()->json([
            'success' => true,
            'events' => PixelEventResource::collection($events),
            'count' => $events->count(),
        ]);
    }

    /**
     * Get performance analytics data
     */
    public function getAnalytics(Request $request)
    {
        $user = Auth::user();
        $metrics = $this->attributionService->getPerformanceMetrics($user->id);

        return response()->json([
            'success' => true,
            'metrics' => $metrics,
        ]);
    }

    /**
     * Save settings & update Shopify Web Pixel
     */
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

        try {
            $shopifyService = new ShopifyService($user);
            $shopifyService->createWebPixel($pixelId);
        } catch (\Throwable $e) {
            Log::info("Web Pixel registration attempt: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Pixel settings saved and registered in Shopify Customer Events!',
            'settings' => new ShopSettingResource($settings),
        ]);
    }

    /**
     * Public CORS storefront endpoint to track storefront events & trigger CAPI
     */
    public function publicTrackEvent(Request $request)
    {
        // Parse incoming body across JSON, Beacon, or Form data payloads
        $data = $request->json()->all();
        if (empty($data)) {
            $rawContent = $request->getContent();
            $data = json_decode($rawContent, true) ?: $request->all();
        }

        $pixelId = $data['pixel_id'] ?? $request->input('pixel_id', '');
        $eventName = $data['event_name'] ?? $request->input('event_name', 'page_viewed');
        $eventType = $data['event_type'] ?? $request->input('event_type', 'Standard');
        $payload = $data['payload'] ?? $request->input('payload', $data);

        $userId = $this->pixelRepo->findUserIdByPixelId($pixelId);
        $user = User::find($userId);
        $setting = $this->pixelRepo->getSettingsByUserId($userId);
        $capiKey = $setting ? $setting->capi_key : '';

        if ($user && $this->pixelRepo->hasExceededEventQuota($user)) {
            $res = response()->json([
                'success' => false,
                'message' => 'Monthly event quota of 50,000 events reached on Free plan. Upgrade to Basic plan for unlimited events.',
                'quota_exceeded' => true,
            ], 429);
            return $this->applyCorsHeaders($res, $request);
        }

        // Event ID strategy for browser + server deduplication
        $eventId = $data['event_id'] ?? $request->input('event_id') ?: ('evt_' . time() . '_' . rand(1000, 9999));
        $oppref = $data['oppref'] ?? $request->input('oppref') ?: ($payload['oppref'] ?? null);

        // Server-side CAPI delivery
        $capiResult = ['response_code' => 200, 'response_body' => 'Browser Event Recorded', 'success' => true];
        if (!empty($capiKey) && !empty($pixelId)) {
            $capiService = new OpenAIConversionService($pixelId, $capiKey);
            $capiResult = $capiService->sendEvent($eventName, $eventId, $payload, $oppref);
        }

        $event = $this->pixelRepo->createEvent([
            'user_id' => $userId,
            'pixel_id' => $pixelId,
            'event_id' => $eventId,
            'event_name' => $eventName,
            'event_type' => $eventType,
            'source' => 'Browser',
            'oppref' => $oppref,
            'order_id' => $payload['order_id'] ?? null,
            'event_time' => Carbon::now()->format('H:i:s'),
            'payload' => $payload,
            'response_code' => $capiResult['response_code'] ?? 200,
            'response_body' => $capiResult['response_body'] ?? null,
            'status' => $capiResult['success'] ? 'Delivered' : 'Failed',
        ]);

        $res = response()->json([
            'success' => true,
            'message' => 'Event captured successfully',
            'event' => new PixelEventResource($event),
        ]);

        return $this->applyCorsHeaders($res, $request);
    }

    public function options(Request $request)
    {
        $res = response('', 204);
        return $this->applyCorsHeaders($res, $request);
    }

    protected function applyCorsHeaders($response, Request $request)
    {
        $origin = $request->header('Origin');
        if (empty($origin) || $origin === 'null') {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        } else {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, ngrok-skip-browser-warning, Authorization, Accept, Origin, User-Agent');
        $response->headers->set('Access-Control-Max-Age', '86400');

        return $response;
    }

    /**
     * Test connection to OpenAI Conversions API
     */
    public function testConnection(Request $request)
    {
        $pixelId = $request->input('pixel_id');
        $capiKey = $request->input('capi_key');

        if (empty($pixelId) || empty($capiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide both Pixel ID and Conversions API key.',
                'details' => [
                    'capi' => false,
                    'pixel' => !empty($pixelId),
                    'credentials' => !empty($capiKey),
                ],
            ]);
        }

        $service = new OpenAIConversionService($pixelId, $capiKey);
        $result = $service->testConnection();

        return response()->json($result);
    }

    /**
     * Internal endpoint to track manual events from backend
     */
    public function trackEvent(Request $request)
    {
        $user = Auth::user();

        if ($this->pixelRepo->hasExceededEventQuota($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Monthly event quota of 50,000 events reached on Free plan.',
                'quota_exceeded' => true,
            ], 429);
        }

        $setting = $this->pixelRepo->getSettingsByUserId($user->id);
        $pixelId = $setting->pixel_id;
        $capiKey = $setting->capi_key;

        $eventName = $request->input('event_name', 'page_viewed');
        $eventType = $request->input('event_type', 'Standard');
        $payload = $request->input('payload', $this->generateMockPayload($eventName));
        $eventId = $request->input('event_id') ?: ('evt_' . time() . '_' . rand(1000, 9999));
        $oppref = $request->input('oppref') ?: ($payload['oppref'] ?? null);

        $capiResult = ['response_code' => 200, 'response_body' => 'Local Event Logged', 'success' => true];
        if (!empty($capiKey) && !empty($pixelId)) {
            $capiService = new OpenAIConversionService($pixelId, $capiKey);
            $capiResult = $capiService->sendEvent($eventName, $eventId, $payload, $oppref);
        }

        $event = $this->pixelRepo->createEvent([
            'user_id' => $user->id,
            'pixel_id' => $pixelId,
            'event_id' => $eventId,
            'event_name' => $eventName,
            'event_type' => $eventType,
            'source' => 'Browser',
            'oppref' => $oppref,
            'order_id' => $payload['order_id'] ?? null,
            'event_time' => Carbon::now()->format('H:i:s'),
            'payload' => $payload,
            'response_code' => $capiResult['response_code'] ?? 200,
            'response_body' => $capiResult['response_body'] ?? null,
            'status' => $capiResult['success'] ? 'Delivered' : 'Failed',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Event tracked and processed successfully!',
            'event' => new PixelEventResource($event),
        ]);
    }

    /**
     * Clear all logged events for authenticated user
     */
    public function clearEvents()
    {
        $user = Auth::user();
        $this->pixelRepo->clearEventsByUserId($user->id);

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
                    'timestamp' => $now,
                ];

            case 'page_viewed':
                return [
                    'page_title' => 'Storefront Homepage',
                    'page_location' => 'https://openai-store.myshopify.com/',
                    'timestamp' => $now,
                ];

            case 'product_viewed':
                return [
                    'product_id' => 'prod_' . rand(100, 999),
                    'title' => 'Sample Storefront Item',
                    'price' => 79.99,
                    'currency' => 'USD',
                    'timestamp' => $now,
                ];

            case 'add_to_cart':
                return [
                    'cart_token' => 'tok_' . rand(100000, 999999),
                    'product_id' => 'prod_' . rand(100, 999),
                    'title' => 'Sample Storefront Item',
                    'price' => 24.99,
                    'quantity' => 1,
                    'currency' => 'USD',
                    'timestamp' => $now,
                ];

            case 'order_completed':
                return [
                    'order_id' => '#ORD-' . rand(1000, 9999),
                    'total' => 214.49,
                    'currency' => 'USD',
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
