<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('feature_plan')->truncate();
        DB::table('features')->truncate();
        DB::table('plans')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $featuresData = [
            [
                'name' => 'Monthly Events Quota',
                'slug' => 'monthly-events-quota',
                'type' => 'text',
                'display_order' => 1,
                'hidden_feature' => false,
            ],
            [
                'name' => 'Web Pixel Tracking',
                'slug' => 'web-pixel-tracking',
                'type' => 'text',
                'display_order' => 2,
                'hidden_feature' => false,
            ],
            [
                'name' => 'Conversions API Integration',
                'slug' => 'conversions-api',
                'type' => 'text',
                'display_order' => 3,
                'hidden_feature' => false,
            ],
            [
                'name' => 'Support',
                'slug' => 'support',
                'type' => 'text',
                'display_order' => 4,
                'hidden_feature' => false,
            ],
            [
                'name' => 'Priority Server-Side Processing',
                'slug' => 'priority-processing',
                'type' => 'bool',
                'display_order' => 5,
                'hidden_feature' => false,
            ],
        ];

        $featureModels = [];
        foreach ($featuresData as $featureData) {
            $feature = Feature::create($featureData);
            $featureModels[$feature->slug] = $feature;
        }

        $plansData = [
            [
                'type' => 'RECURRING',
                'name' => 'Free',
                'price' => 0.00,
                'interval' => 'EVERY_30_DAYS',
                'capped_amount' => 0.00,
                'terms' => 'Free Plan - Up to 50,000 events / month',
                'trial_days' => 0,
                'test' => true,
                'on_install' => true,
            ],
            [
                'type' => 'RECURRING',
                'name' => 'Basic',
                'price' => 29.00,
                'interval' => 'EVERY_30_DAYS',
                'capped_amount' => 29.00,
                'terms' => 'Basic Plan - Unlimited events / month',
                'trial_days' => 7,
                'test' => true,
                'on_install' => false,
            ],
        ];

        $planFeatureMap = [
            'Free' => [
                ['slug' => 'monthly-events-quota', 'value' => 'Up to 50,000 events / month'],
                ['slug' => 'web-pixel-tracking', 'value' => 'Standard Web Pixel'],
                ['slug' => 'conversions-api', 'value' => 'Conversions API Included'],
                ['slug' => 'support', 'value' => 'Community Support'],
            ],
            'Basic' => [
                ['slug' => 'monthly-events-quota', 'value' => 'Unlimited events / month'],
                ['slug' => 'web-pixel-tracking', 'value' => 'Standard & Custom Web Pixel'],
                ['slug' => 'conversions-api', 'value' => 'Conversions API Included'],
                ['slug' => 'priority-processing', 'value' => 1],
                ['slug' => 'support', 'value' => '24/7 Priority Support'],
            ],
        ];

        foreach ($plansData as $planData) {
            $plan = Plan::create($planData);

            foreach ($planFeatureMap[$plan->name] ?? [] as $featureData) {
                $feature = $featureModels[$featureData['slug']] ?? null;

                if (!$feature) {
                    continue;
                }

                $plan->features()->attach($feature->id, ['value' => $featureData['value']]);
            }
        }
    }
}
