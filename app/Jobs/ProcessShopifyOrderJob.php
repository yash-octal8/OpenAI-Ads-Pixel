<?php

namespace App\Jobs;

use App\Models\User;
use App\Repositories\PixelRepository;
use App\Services\OpenAI\OpenAIConversionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessShopifyOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $orderData;
    protected string $shopDomain;

    public function __construct(string $shopDomain, array $orderData)
    {
        $this->shopDomain = $shopDomain;
        $this->orderData = $orderData;
    }

    public function handle(PixelRepository $pixelRepo): void
    {
        $user = User::where('name', $this->shopDomain)->first();
        if (!$user) {
            Log::warning("Order webhook received for unknown shop domain: {$this->shopDomain}");
            return;
        }

        $settings = $pixelRepo->getSettingsByUserId($user->id);
        if (!$settings || empty($settings->pixel_id) || empty($settings->capi_key)) {
            Log::info("Server CAPI skipped: Pixel ID or CAPI key not configured for user {$user->id}");
            return;
        }

        $orderId = $this->orderData['id'] ?? $this->orderData['order_number'] ?? rand(1000, 9999);
        $totalPrice = $this->orderData['total_price'] ?? 0.00;
        $currency = $this->orderData['currency'] ?? 'USD';

        // Extract oppref if attached to note_attributes
        $oppref = null;
        if (!empty($this->orderData['note_attributes'])) {
            foreach ($this->orderData['note_attributes'] as $attr) {
                if (isset($attr['name']) && in_array(strtolower($attr['name']), ['oppref', 'openai_click_ref'])) {
                    $oppref = $attr['value'];
                    break;
                }
            }
        }

        // Deterministic event ID for deduplication with browser pixel
        $eventId = "purchase_{$user->id}_{$orderId}";

        $payload = [
            'order_id' => "#ORD-{$orderId}",
            'total_price' => $totalPrice,
            'currency' => $currency,
            'email' => $this->orderData['email'] ?? null,
            'phone' => $this->orderData['phone'] ?? null,
            'oppref' => $oppref,
            'source' => 'Server CAPI Webhook',
        ];

        // Send to OpenAI Conversions API
        $capiService = new OpenAIConversionService($settings->pixel_id, $settings->capi_key);
        $capiResult = $capiService->sendEvent('Purchase', $eventId, $payload, $oppref);

        // Record Server Event in database
        $pixelRepo->createEvent([
            'user_id' => $user->id,
            'pixel_id' => $settings->pixel_id,
            'event_id' => $eventId,
            'event_name' => 'order_completed',
            'event_type' => 'Standard',
            'source' => 'Server',
            'oppref' => $oppref,
            'order_id' => "#ORD-{$orderId}",
            'payload' => $payload,
            'response_code' => $capiResult['response_code'] ?? 200,
            'response_body' => $capiResult['response_body'] ?? null,
            'status' => $capiResult['success'] ? 'Delivered' : 'Failed',
        ]);

        Log::info("Shopify Order #{$orderId} processed via Server CAPI with Event ID {$eventId}");
    }
}
