<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Osiset\ShopifyApp\Actions\CancelCurrentPlan;
use Osiset\ShopifyApp\Contracts\Commands\Shop as IShopCommand;
use Osiset\ShopifyApp\Contracts\Queries\Shop as IShopQuery;
use Osiset\ShopifyApp\Messaging\Events\AppUninstalledEvent;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;

class AppUninstalledJob extends \Osiset\ShopifyApp\Messaging\Jobs\AppUninstalledJob
{
    /**
     * Execute the job.
     *
     * @param IShopCommand      $shopCommand             The commands for shops.
     * @param IShopQuery        $shopQuery               The querier for shops.
     * @param CancelCurrentPlan $cancelCurrentPlanAction The action for cancelling the current plan.
     *
     * @return bool
     */
    public function handle(
        IShopCommand $shopCommand,
        IShopQuery $shopQuery,
        CancelCurrentPlan $cancelCurrentPlanAction
    ): bool {
        // Convert the domain
        $domain = ShopDomain::fromNative($this->domain);
        $shopDomain = $domain->toNative();

        Log::info("App uninstalled for shop: {$shopDomain}. Cleaning access token and data.");

        // Get the shop (including trashed if previously soft deleted)
        $shop = $shopQuery->getByDomain($domain, [], true);
        
        // Find user model
        $shopModel = User::withTrashed()->where('name', $shopDomain)->first();

        if ($shopModel) {
            $shopIdNative = $shopModel->id;

            // 1. Delete Charges, Attributions, Pixels, Pixel Events, and Settings explicitly from database
            try {
                \DB::table('charges')->where('user_id', $shopIdNative)->delete();

                $shopModel->attributions()->delete();
                $shopModel->pixels()->delete();
                $shopModel->pixelEvents()->delete();
                $shopModel->setting()->delete();

                Log::info("Cleaned up attributions, pixels, pixel_events, shop_settings, and charges for {$shopDomain}");
            } catch (\Throwable $e) {
                Log::error("Failed to clean up database tables for {$shopDomain}: " . $e->getMessage());
            }
            

            // 3. Clear shop cache
            \Illuminate\Support\Facades\Cache::forget("shop_{$shopDomain}");

            // 4. Cancel plan and clean access token BEFORE soft deleting
            if ($shop) {
                $shopId = $shop->getId();
                try {
                    $cancelCurrentPlanAction($shopId);
                    $shopCommand->clean($shopId);
                } catch (\Throwable $e) {
                    Log::error("Package cleanup failed for {$shopDomain}: " . $e->getMessage());
                }

                // 5. Soft delete the shop via package command
                try {
                    $shopCommand->softDelete($shopId);
                } catch (\Throwable $e) {
                    Log::error("Package softDelete failed for {$shopDomain}: " . $e->getMessage());
                }
            }

            // 6. Trigger uninstalled event
            if ($shop) {
                try {
                    event(new AppUninstalledEvent($shop));
                } catch (\Throwable $e) {
                    Log::error("Failed to trigger AppUninstalledEvent for {$shopDomain}: " . $e->getMessage());
                }
            }
        }

        return true;
    }
}
