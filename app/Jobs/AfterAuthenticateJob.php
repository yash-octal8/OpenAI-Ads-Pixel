<?php

namespace App\Jobs;

use App\Models\Plan;
use App\Models\User;
use App\Models\ShopSetting;
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
            // 1. Initialize shop settings if they don't exist
            ShopSetting::firstOrCreate(
                ['user_id' => $this->shop->id],
                [
                    'pixel_id' => '',
                    'capi_key' => '',
                    'advertiser_key' => '',
                    'tracking_enabled' => true,
                    'pixel_helper_enabled' => true,
                ]
            );

            // 2. Automatically assign Free plan on app installation
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
