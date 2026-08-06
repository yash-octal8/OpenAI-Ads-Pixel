<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\Osiset\ShopifyApp\Actions\ActivatePlan::class, function ($app) {
            return new \App\Actions\Billing\ActivatePlan(
                $app->make(\Osiset\ShopifyApp\Actions\CancelCurrentPlan::class),
                $app->make(\Osiset\ShopifyApp\Services\ChargeHelper::class),
                $app->make(\Osiset\ShopifyApp\Contracts\Queries\Shop::class),
                $app->make(\Osiset\ShopifyApp\Contracts\Queries\Plan::class),
                $app->make(\Osiset\ShopifyApp\Contracts\Commands\Charge::class),
                $app->make(\Osiset\ShopifyApp\Contracts\Commands\Shop::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
