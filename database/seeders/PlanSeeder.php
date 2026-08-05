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
                'name' => 'Smart Upload',
                'slug' => 'smart-upload',
                'type' => 'text',
                'display_order' => 1,
                'hidden_feature' => false,
            ],
            [
                'name' => 'Bulk Delete',
                'slug' => 'bulk-delete',
                'type' => 'text',
                'display_order' => 2,
                'hidden_feature' => false,
            ],
            [
                'name' => 'Bulk Export',
                'slug' => 'bulk-export',
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
                'name' => 'Priority Background Processing',
                'slug' => 'priority-background-processing',
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
                'terms' => 'Free Plan',
                'trial_days' => 0,
                'test' => false,
                'on_install' => true,
            ],
            [
                'type' => 'RECURRING',
                'name' => 'Premium',
                'price' => 14.99,
                'interval' => 'EVERY_30_DAYS',
                'capped_amount' => 14.99,
                'terms' => 'Premium Plan',
                'trial_days' => 7,
                'test' => false,
                'on_install' => false,
            ],
        ];

        $planFeatureMap = [
            'Free' => [
                ['slug' => 'smart-upload', 'value' => 'Up to 25 images'],
                ['slug' => 'bulk-delete', 'value' => 'Up to 50 items'],
                ['slug' => 'bulk-export', 'value' => 'Up to 50 images'],
                ['slug' => 'support', 'value' => 'Community support'],
            ],
            'Premium' => [
                ['slug' => 'smart-upload', 'value' => 'Unlimited uploads'],
                ['slug' => 'bulk-delete', 'value' => 'Unlimited deletions'],
                ['slug' => 'bulk-export', 'value' => 'Unlimited exports'],
                ['slug' => 'priority-background-processing', 'value' => 1],
                ['slug' => 'support', 'value' => 'Premium support'],
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
