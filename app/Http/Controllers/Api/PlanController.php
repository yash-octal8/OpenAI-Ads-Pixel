<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Osiset\ShopifyApp\Messaging\Events\PlanActivatedEvent;
use Osiset\ShopifyApp\Objects\Values\ChargeId;
use Osiset\ShopifyApp\Storage\Models\Charge;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // Automatically assign Free Plan if user has no plan assigned yet
        if ($user && !$user->plan_id) {
            $freePlan = Plan::where('name', 'Free')->first();
            if ($freePlan) {
                $user->plan_id = $freePlan->id;
                $user->shopify_freemium = true;
                $user->save();

                DB::table('charges')->updateOrInsert(
                    ['user_id' => $user->id, 'plan_id' => $freePlan->id],
                    [
                        'charge_id' => 0,
                        'type' => 'RECURRING',
                        'status' => 'ACTIVE',
                        'name' => $freePlan->name,
                        'price' => 0.00,
                        'interval' => 'EVERY_30_DAYS',
                        'test' => true,
                        'activated_on' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
        
        $dbPlans = Plan::with(['features' => function($q) {
            $q->orderBy('display_order', 'asc');
        }])->get();

        $formattedPlans = [];

        foreach ($dbPlans as $plan) {
            $features = $plan->features->map(function ($feature) {
                if ($feature->type === 'bool') {
                    return $feature->name;
                }
                return $feature->name . ': ' . $feature->pivot->value;
            })->toArray();

            $formattedPlans[] = [
                'id'       => $plan->id,
                'name'     => $plan->name,
                'price'    => number_format((float) $plan->price, 2, '.', ''),
                'interval' => 'monthly',
                'features' => $features,
            ];
        }

        $currentPlanName = null;
        if ($user && $user->plan_id) {
            $activePlan = Plan::find($user->plan_id);
            if ($activePlan) {
                $currentPlanName = $activePlan->name;
            }
        }

        return response()->json([
            'success'     => true,
            'currentPlan' => $currentPlanName ?? 'Free',
            'shop'        => $user ? $user->name : '',
            'plans'       => $formattedPlans,
        ]);
    }

    public function chooseFreePlan(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $freePlan = Plan::where('name', 'Free')->first();
        if (!$freePlan) {
            return response()->json(['success' => false, 'message' => 'Free Plan not found'], 404);
        }

        try {
            DB::transaction(function () use ($user, $freePlan) {
                $this->deactivateCurrentPlan($user);

                $user->plan_id = $freePlan->id;
                $user->shopify_freemium = true;
                $user->save();

                DB::table('charges')->updateOrInsert(
                    ['user_id' => $user->id, 'plan_id' => $freePlan->id],
                    [
                        'charge_id' => 0,
                        'type' => 'RECURRING',
                        'status' => 'ACTIVE',
                        'name' => $freePlan->name,
                        'price' => 0.00,
                        'interval' => 'EVERY_30_DAYS',
                        'test' => true,
                        'activated_on' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            });

            // Dispatch PlanActivatedEvent for free plan manually to trigger notification / event listeners
            try {
                event(new PlanActivatedEvent($user, $freePlan, new ChargeId(0)));
            } catch (\Throwable $e) {
                Log::info('PlanActivatedEvent error: ' . $e->getMessage());
            }

            Log::info('Free Plan Subscribed Successfully', [$user]);

            return response()->json(['success' => true, 'message' => 'Free Plan Subscribed Successfully']);
        } catch (\Throwable $e) {
            Log::error('chooseFreePlan Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to subscribe to Free plan'], 500);
        }
    }

    public function deactivateCurrentPlan($user = null)
    {
        $user = $user ?: auth()->user();
        if (!$user) {
            return;
        }

        $activeCharges = Charge::where('user_id', $user->id)->where('status', 'ACTIVE')->get();

        foreach ($activeCharges as $charge) {
            if ($charge->charge_id) {
                $gid = str_contains((string) $charge->charge_id, 'gid://') 
                    ? $charge->charge_id 
                    : "gid://shopify/AppSubscription/{$charge->charge_id}";

                $query = '
                mutation appSubscriptionCancel($id: ID!) {
                  appSubscriptionCancel(id: $id) {
                    appSubscription {
                      id
                      status
                    }
                    userErrors {
                      field
                      message
                    }
                  }
                }
                ';

                try {
                    $shopifyService = new \App\Services\ShopifyService($user);
                    $shopifyService->execute($query, ['id' => $gid]);
                } catch (\Throwable $e) {
                    Log::info('deactivateCurrentPlan Shopify GraphQL error: ' . $e->getMessage());
                }
            }

            $charge->status = 'CANCELLED';
            $charge->cancelled_on = now();
            $charge->expires_on = now();
            $charge->save();
        }
    }
}
