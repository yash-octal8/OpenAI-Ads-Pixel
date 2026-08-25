<?php

namespace App\Jobs;

use App\Models\Plan;
use App\Models\User;
use App\Models\ShopSetting;
use App\Services\ShopifyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AfterAuthenticateJob implements ShouldQueue
{
    use Queueable;

    public $shop;

    /**
     * Create a new job instance.
     */
    public function __construct(User $shop)
    {
        $this->shop = $shop;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // 1. Initialize shop settings and auto-generate pixel ID if empty
            $defaultPixelId = 'oai_px_' . substr(md5($this->shop->name . '_' . $this->shop->id), 0, 12);
            $setting = ShopSetting::where('user_id', $this->shop->id)->first();

            if (!$setting) {
                $setting = ShopSetting::create([
                    'user_id' => $this->shop->id,
                    'pixel_id' => $defaultPixelId,
                    'capi_key' => '',
                    'advertiser_key' => '',
                    'tracking_enabled' => true,
                    'pixel_helper_enabled' => true,
                ]);
            } elseif (empty($setting->pixel_id)) {
                $setting->pixel_id = $defaultPixelId;
                $setting->save();
            }

            // 2. Ensure default active pixel record in pixels table
            \App\Models\Pixel::firstOrCreate(
                ['user_id' => $this->shop->id, 'pixel_id' => $setting->pixel_id],
                [
                    'name' => 'OpenAI Pixel — Main Store',
                    'capi_key' => $setting->capi_key ?? '',
                    'status' => 'active',
                    'coverage_type' => 'entire_store',
                ]
            );

            // 3. Automatically register Web Pixel extension and APP_UNINSTALLED webhook in Shopify
            try {
                $shopifyService = new ShopifyService($this->shop);
                $shopifyService->syncWebPixel($setting->pixel_id);
                Log::info("Automatically registered Customer Event Web Pixel for {$this->shop->name} with Pixel ID: {$setting->pixel_id}");
            } catch (\Throwable $e) {
                Log::error("Failed to register Web Pixel or Webhook on install for {$this->shop->name}: " . $e->getMessage());
            }

            // 4. Automatically assign Free plan on app installation
            if (!$this->shop->plan_id) {
                $freePlan = Plan::where('name', 'Free')->first();
                if ($freePlan) {
                    $this->shop->plan_id = $freePlan->id;
                    $this->shop->shopify_freemium = true;
                    $this->shop->save();

                    DB::table('charges')->updateOrInsert(
                        ['user_id' => $this->shop->id, 'plan_id' => $freePlan->id],
                        [
                            'charge_id' => 0,
                            'type' => 'RECURRING',
                            'status' => 'ACTIVE',
                            'name' => $freePlan->name,
                            'price' => 0.00,
                            'interval' => 'EVERY_30_DAYS',
                            'test' => true,
                            'activated_on' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );

                    Log::info("Automatic Free Plan activated for {$this->shop->name}");
                }
            }

            Log::info("AfterAuthenticateJob completed for {$this->shop->name}");
        } catch (\Exception $e) {
            Log::error("AfterAuthenticateJob failed for {$this->shop->name}: " . $e->getMessage());
        }
    }
}
