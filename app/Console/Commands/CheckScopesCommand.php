<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ShopifyGraphQLService;
use Illuminate\Console\Command;

class CheckScopesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-scopes {shop? : The domain of the shop to check scopes for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get and list the current approved access scopes from Shopify for a store';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $shopName = $this->argument('shop');

        if ($shopName) {
            $shops = User::where('name', 'like', "%{$shopName}%")->get();
            if ($shops->isEmpty()) {
                $this->error("No shop found matching: {$shopName}");
                return 1;
            }
        } else {
            $shops = User::all();
            if ($shops->isEmpty()) {
                $this->info("No shops registered in the local database.");
                return 0;
            }
        }

        foreach ($shops as $shop) {
            $this->newLine();
            $this->info("==================================================");
            $this->info("Shop: {$shop->name} (ID: {$shop->id})");
            $this->info("==================================================");

            if (empty($shop->password)) {
                $this->warn("Access token is currently empty for {$shop->name}.");
                $this->line("Action Required: Open/launch the app in Shopify Admin (https://admin.shopify.com/store/{$shop->name}/apps) to complete OAuth authentication and receive a fresh access token!");
                continue;
            }

            $this->line("Querying Shopify GraphQL API for approved scopes...");
            try {
                $shopifyService = new ShopifyGraphQLService($shop->name, $shop->password);
                $query = '
                    query {
                      currentAppInstallation {
                        accessScopes {
                          handle
                        }
                      }
                    }
                ';

                $response = $shopifyService->query($query);
                $scopes = $response['currentAppInstallation']['accessScopes'] ?? [];

                if (empty($scopes)) {
                    $this->warn("No approved scopes found for this app on Shopify.");
                } else {
                    $this->info("Approved Scopes:");
                    $rows = [];
                    foreach ($scopes as $scope) {
                        $rows[] = [$scope['handle']];
                    }
                    $this->table(['Scope Handle'], $rows);
                }
            } catch (\Exception $e) {
                $this->error("An error occurred while fetching from Shopify: " . $e->getMessage());
            }
        }

        return 0;
    }
}
