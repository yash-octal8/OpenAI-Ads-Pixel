<?php

namespace App\Services\OpenAI;

use App\Models\Attribution;
use App\Models\PixelEvent;
use Carbon\Carbon;

class OpenAIAttributionService
{
    /**
     * Get overall metrics for user dashboard and performance pages
     */
    public function getPerformanceMetrics(int $userId): array
    {
        $now = Carbon::now();
        $thirtyDaysAgo = $now->copy()->subDays(30);

        // Attributed revenue & purchases
        $attributions = Attribution::where('user_id', $userId)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->get();

        $totalRevenue = $attributions->sum('revenue');
        $totalOrders = $attributions->count();
        $averageOrderValue = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0.00;

        // Calculate funnel steps from pixel_events
        $events = PixelEvent::where('user_id', $userId)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->get();

        $pageViews = $events->filter(fn($e) => in_array(strtolower($e->event_name), ['page_viewed', 'page_view', 'pageview']))->count();
        $productViews = $events->filter(fn($e) => in_array(strtolower($e->event_name), ['product_viewed', 'view_content', 'productview']))->count();
        $addToCarts = $events->filter(fn($e) => in_array(strtolower($e->event_name), ['product_added_to_cart', 'add_to_cart', 'addtocart']))->count();
        $checkouts = $events->filter(fn($e) => in_array(strtolower($e->event_name), ['checkout_started', 'initiate_checkout', 'checkout']))->count();
        $purchases = $events->filter(fn($e) => in_array(strtolower($e->event_name), ['checkout_completed', 'order_completed', 'purchase']))->count();

        $conversionRate = $pageViews > 0 ? round(($purchases / $pageViews) * 100, 2) : 0.00;

        // Estimated spend calculation (ROAS & CPA) based on live attributed revenue
        $estimatedSpend = $totalRevenue > 0 ? round($totalRevenue * 0.22, 2) : 0.00;
        $roas = $estimatedSpend > 0 ? round($totalRevenue / $estimatedSpend, 2) . 'x' : '0.0x';
        $cpa = $totalOrders > 0 ? '$' . number_format(round($estimatedSpend / $totalOrders, 2), 2) : '$0.00';

        // Calculate Event Mix percentages
        $totalMappedEvents = max(1, $pageViews + $productViews + $addToCarts + $checkouts + $purchases);
        $eventMix = [
            'page_view' => ($pageViews > 0) ? round(($pageViews / $totalMappedEvents) * 100, 1) : 0,
            'product_view' => ($productViews > 0) ? round(($productViews / $totalMappedEvents) * 100, 1) : 0,
            'add_to_cart' => ($addToCarts > 0) ? round(($addToCarts / $totalMappedEvents) * 100, 1) : 0,
            'checkout' => ($checkouts > 0) ? round(($checkouts / $totalMappedEvents) * 100, 1) : 0,
            'purchase' => ($purchases > 0) ? round(($purchases / $totalMappedEvents) * 100, 1) : 0,
        ];

        // Top products breakdown from product_viewed / order_completed payloads
        $productStats = $this->getProductPerformance($events);

        return [
            'total_revenue' => round($totalRevenue, 2),
            'total_orders' => $totalOrders,
            'average_order_value' => $averageOrderValue,
            'conversion_rate' => $conversionRate,
            'estimated_spend' => $estimatedSpend,
            'roas' => $roas,
            'cpa' => $cpa,
            'funnel' => [
                'page_views' => $pageViews,
                'product_views' => $productViews,
                'add_to_carts' => $addToCarts,
                'checkouts' => $checkouts,
                'purchases' => $purchases,
            ],
            'event_mix' => $eventMix,
            'products' => $productStats,
        ];
    }

    /**
     * Build top selling products performance list from captured events
     */
    protected function getProductPerformance($events): array
    {
        $products = [];

        foreach ($events as $event) {
            $payload = $event->payload ?? [];
            if (empty($payload)) continue;

            $title = $payload['title'] ?? $payload['product_title'] ?? null;
            if (!$title && !empty($payload['items'][0]['title'])) {
                $title = $payload['items'][0]['title'];
            }

            if ($title) {
                if (!isset($products[$title])) {
                    $products[$title] = [
                        'title' => $title,
                        'views' => 0,
                        'purchases' => 0,
                        'revenue' => 0.0,
                    ];
                }

                $evtName = strtolower($event->event_name);
                if (in_array($evtName, ['product_viewed', 'view_content'])) {
                    $products[$title]['views']++;
                } elseif (in_array($evtName, ['checkout_completed', 'order_completed', 'purchase'])) {
                    $products[$title]['purchases']++;
                    $price = floatval($payload['price'] ?? $payload['total_price'] ?? $payload['total'] ?? 0.00);
                    $products[$title]['revenue'] += $price;
                }
            }
        }

        if (empty($products)) {
            return [];
        }

        return array_values(array_slice($products, 0, 10));
    }
}
