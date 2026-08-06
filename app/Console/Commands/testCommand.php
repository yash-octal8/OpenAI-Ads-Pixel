<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ShopifyService;
use Illuminate\Console\Command;

class testCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:testing {shop? : The myshopify domain} {--register : Register configured webhooks}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List or register Shopify webhooks for a shop';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $shopDomain = $this->argument('shop');

        $user = $shopDomain
            ? User::where('name', $shopDomain)->first()
            : User::first();

        if (!$user) {
            $this->error('No shop found in database.');
            return 1;
        }

        $this->ListWebhookRegister($user);
    }

    public function ListWebhookRegister($user)
    {
        $shopifyService = new ShopifyService($user);

        $this->info("Fetching webhooks for shop: {$user->name}");

        $query = '
        {
          webhookSubscriptions(first: 50) {
            edges {
              node {
                id
                topic
                endpoint {
                  __typename
                  ... on WebhookHttpEndpoint {
                    callbackUrl
                  }
                  ... on WebhookEventBridgeEndpoint {
                    arn
                  }
                  ... on WebhookPubSubEndpoint {
                    pubSubProject
                    pubSubTopic
                  }
                }
              }
            }
          }
        }
        ';

        try {
            $response = $shopifyService->execute($query);

            if (isset($response['errors']) && $response['errors'] === true) {
                $this->error("Shopify API Error: " . ($response['body'] ?? 'Unknown error'));
                $this->line("Tip: Reload/reopen your app in Shopify Admin to refresh the offline access token.");
                return 1;
            }

            $edges = $response['body']['data']['webhookSubscriptions']['edges'] ?? [];

            $tableData = [];
            foreach ($edges as $edge) {
                $node = $edge['node'];
                $endpoint = $node['endpoint']['callbackUrl'] ?? ($node['endpoint']['arn'] ?? 'N/A');
                $tableData[] = [
                    'ID' => $node['id'],
                    'Topic' => $node['topic'],
                    'Endpoint' => $endpoint,
                ];
            }

            if (!empty($tableData)) {
                $this->table(['GraphQL ID', 'Topic', 'Callback URL'], $tableData);
            } else {
                $this->warn('No webhooks currently registered on Shopify.');
                $this->line('Tip: Run `php artisan app:testing --register` to register the APP_UNINSTALLED webhook.');
            }
        } catch (\Throwable $e) {
            $this->error('Error fetching webhooks: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
