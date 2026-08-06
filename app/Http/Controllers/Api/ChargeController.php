<?php

namespace App\Http\Controllers\Api;

use App\Models\Plan;
use App\Models\User;
use App\Services\ShopifyService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Osiset\ShopifyApp\Services\ChargeHelper;

class ChargeController extends Controller
{
    protected $chargeHelper;

    public function __construct(ChargeHelper $chargeHelper)
    {
        $this->chargeHelper = $chargeHelper;
    }

    public function index(Request $request, $planId)
    {
        $shop = User::where('name', $request->query('shop'))->first();
        if (!$shop) {
            $shop = $request->user();
        }

        $host = urldecode($request->get('host'));

        if (!$shop) {
            return $this->sendError('Shop not found', 404);
        }

        $plan = Plan::find($planId);
        if (!$plan) {
            return $this->sendError('Plan not found', 404);
        }

        $chargeData = $this->getPlanUrl($shop, $plan, $host);

        $url = $chargeData['confirmationUrl'] ?? null;

        // Upgrade the old myshopify.com URL to the new admin.shopify.com URL to avoid 431 Cookie Too Large errors
        if ($url && strpos($url, $shop->name) !== false) {
            $shopName = explode('.', $shop->name)[0];
            $url = str_replace(
                "https://{$shop->name}/admin",
                "https://admin.shopify.com/store/{$shopName}",
                $url
            );
        }

        return response()->json([
            'url' => $url,
            'errors' => $chargeData['userErrors'] ?? []
        ]);
    }

    public function getPlanUrl($shop, $plan, $host)
    {
        $planDetails = $this->chargeHelper->details($plan, $shop, $host);

        if ($planDetails) {
            $planDetails = is_array($planDetails) ? $planDetails : $planDetails->toArray();
        } else {
            // Fallback just in case
            $planDetails = [
                'name' => $plan->name,
                'price' => $plan->price,
                'return_url' => "https://admin.shopify.com/store/" . explode('.', $shop->name)[0] . "/apps/openai-ads-pixel/plan",
                'test' => $plan->test,
                'trial_days' => $plan->trial_days,
            ];
        }

        $planDetails['interval'] = $plan->interval ?? 'EVERY_30_DAYS';

        if (isset($plan->capped_amount)) {
            $planDetails['capped_amount'] = $plan->capped_amount;
        }

        if (isset($plan->terms)) {
            $planDetails['terms'] = $plan->terms;
        }

        if (isset($plan->discount) && $plan->discount) {
            $planDetails['discount'] = is_array($plan->discount) ? $plan->discount['amount'] : $plan->discount;
        }

        return $this->createChargeQuery($shop, $planDetails);
    }

    public function createChargeQuery($shop, $payload)
    {
        $query = '
        mutation appSubscriptionCreate(
            $name: String!,
            $returnUrl: URL!,
            $trialDays: Int,
            $test: Boolean,
            $lineItems: [AppSubscriptionLineItemInput!]!
        ) {
            appSubscriptionCreate(
                name: $name,
                returnUrl: $returnUrl,
                trialDays: $trialDays,
                test: $test,
                lineItems: $lineItems
            ) {
                appSubscription {
                    id
                }
                confirmationUrl
                userErrors {
                    field
                    message
                }
            }
        }
        ';

        $interval = !empty($payload['interval']) ? $payload['interval'] : 'EVERY_30_DAYS';

        $variables = [
            'name' => $payload['name'],
            'returnUrl' => $payload['return_url'],
            'trialDays' => $payload['trial_days'] ?? 0,
            'test' => $payload['test'] ?? true,
            'lineItems' => [
                [
                    'plan' => [
                        'appRecurringPricingDetails' => [
                            'price' => [
                                'amount' => (float) $payload['price'],
                                'currencyCode' => 'USD',
                            ],
                            'interval' => $interval,
                        ]
                    ],
                ]
            ],
        ];

        if (!empty($payload['discount']) && $payload['discount']) {
            $variables['lineItems'][0]['plan']['appRecurringPricingDetails']['discount'] = [
                'value' => [
                    'amount' => (float) $payload['discount'],
                ],
            ];
        }

        if (!empty($payload['capped_amount']) && $payload['capped_amount']) {
            $variables['lineItems'][] = [
                'plan' => [
                    'appUsagePricingDetails' => [
                        'cappedAmount' => [
                            'amount' => (float) $payload['capped_amount'],
                            'currencyCode' => 'USD',
                        ],
                        'terms' => $payload['terms'] ?? 'Usage charges',
                    ]
                ],
            ];
        }

        $shopifyService = new ShopifyService($shop);
        $response = $shopifyService->execute($query, $variables);

        if (isset($response['errors']) && $response['errors'] === true) {
            return ['confirmationUrl' => null, 'userErrors' => [['message' => $response['body'] ?? 'Unknown error']]];
        }

        if (isset($response['body']['data']['appSubscriptionCreate']['userErrors']) && count($response['body']['data']['appSubscriptionCreate']['userErrors']) > 0) {
            return ['confirmationUrl' => null, 'userErrors' => $response['body']['data']['appSubscriptionCreate']['userErrors']];
        }

        return $response['body']['data']['appSubscriptionCreate'] ?? ['confirmationUrl' => null];
    }
}
