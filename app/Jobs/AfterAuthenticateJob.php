<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\ShopSetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
            // Initialize shop settings if they don't exist
            ShopSetting::firstOrCreate(
                ['user_id' => $this->shop->id],
                [
                    'pixel_id' => '3037692197',
                    'capi_key' => '',
                    'advertiser_key' => '',
                    'tracking_enabled' => true,
                    'pixel_helper_enabled' => true,
                ]
            );
            // Ensure a charge record exists for the Free plan if user has no active charge
            $existingCharge = \DB::table('charges')
                ->where('user_id', $this->shop->id)
                ->where('status', 'ACTIVE')
                ->first();

            if (!$existingCharge) {
                $freePlan = \App\Models\Plan::where('name', 'Free')->first();

                if ($freePlan) {
                    if (!$this->shop->plan_id) {
                        $this->shop->plan_id = $freePlan->id;
                        $this->shop->save();
                    }

                    \DB::table('charges')->insert([
                        'user_id' => $this->shop->id,
                        'charge_id' => 0,
                        'test' => false,
                        'status' => 'ACTIVE',
                        'name' => 'Free',
                        'terms' => 'Free Plan',
                        'type' => 'RECURRING',
                        'price' => 0.00,
                        'capped_amount' => 0.00,
                        'plan_id' => $freePlan->id,
                        'activated_on' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            
            Log::info("AfterAuthenticateJob completed for {$this->shop->name}");
        } catch (\Exception $e) {
            Log::error("AfterAuthenticateJob failed for {$this->shop->name}: " . $e->getMessage());
        }
    }
}
