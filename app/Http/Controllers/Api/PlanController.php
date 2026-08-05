<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Osiset\ShopifyApp\Storage\Models\Charge;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        
        $dbPlans = \App\Models\Plan::with(['features' => function($q) {
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

        $currentPlanName = 'Free';
        if ($user && $user->plan_id) {
            $activePlan = \App\Models\Plan::find($user->plan_id);
            if ($activePlan) {
                $currentPlanName = $activePlan->name;
            }
        }

        return response()->json([
            'currentPlan' => $currentPlanName,
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

        $charge = Charge::where('user_id', $user->id)->where('status', 'ACTIVE')->first();
        if ($charge) {
            try {
                $chargeUrl = '/admin/api/2024-01/recurring_application_charges/' . $charge->charge_id . '.json';
                $response = $user->api()->rest('DELETE', $chargeUrl);

                if (empty($response['errors']) && isset($response['status']) && $response['status'] === 200) {
                    $charge->status = 'CANCELLED';
                    $charge->save();
                }
            } catch (\Exception $exception) {
                Log::error('Error canceling plan: ' . $exception->getMessage());
            }
        }

        $freePlan = \App\Models\Plan::where('name', 'Free')->first();
        if ($freePlan) {
            $user->plan_id = $freePlan->id;
            $user->save();

            DB::table('charges')->insert([
                'user_id' => $user->id,
                'charge_id' => 0,
                'test' => false,
                'status' => 'ACTIVE',
                'name' => 'Free',
                'terms' => 'Free Plan',
                'type' => 'RECURRING',
                'price' => 0.00,
                'capped_amount' => 0.00,
                'plan_id' => $freePlan->id,
                'activated_on' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Downgraded to Free Plan successfully!']);
    }
}
