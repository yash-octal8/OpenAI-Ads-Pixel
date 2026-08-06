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
            Log::info("AfterAuthenticateJob completed for {$this->shop->name}");
        } catch (\Exception $e) {
            Log::error("AfterAuthenticateJob failed for {$this->shop->name}: " . $e->getMessage());
        }
    }
}
