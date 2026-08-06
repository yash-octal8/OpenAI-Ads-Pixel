<?php

namespace App\Actions\Billing;

use App\Models\Plan;
use App\Services\ShopifyService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Osiset\ShopifyApp\Contracts\Commands\Charge as IChargeCommand;
use Osiset\ShopifyApp\Contracts\Commands\Shop as IShopCommand;
use Osiset\ShopifyApp\Contracts\Objects\Values\PlanId;
use Osiset\ShopifyApp\Contracts\Queries\Plan as IPlanQuery;
use Osiset\ShopifyApp\Contracts\Queries\Shop as IShopQuery;
use Osiset\ShopifyApp\Messaging\Events\PlanActivatedEvent;
use Osiset\ShopifyApp\Objects\Enums\ChargeStatus;
use Osiset\ShopifyApp\Objects\Enums\ChargeType;
use Osiset\ShopifyApp\Objects\Enums\PlanType;
use Osiset\ShopifyApp\Objects\Transfers\Charge as ChargeTransfer;
use Osiset\ShopifyApp\Objects\Values\ChargeId;
use Osiset\ShopifyApp\Objects\Values\ChargeReference;
use Osiset\ShopifyApp\Objects\Values\ShopId;
use Osiset\ShopifyApp\Services\ChargeHelper;

class ActivatePlan extends \Osiset\ShopifyApp\Actions\ActivatePlan
{
    public function __construct(
        callable $cancelCurrentPlanAction,
        ChargeHelper $chargeHelper,
        IShopQuery $shopQuery,
        IPlanQuery $planQuery,
        IChargeCommand $chargeCommand,
        IShopCommand $shopCommand
    ) {
        parent::__construct(
            $cancelCurrentPlanAction,
            $chargeHelper,
            $shopQuery,
            $planQuery,
            $chargeCommand,
            $shopCommand
        );
    }

    public function __invoke(ShopId $shopId, PlanId $planId, ChargeReference $chargeRef, string $host): ChargeId
    {
        $shop = $this->shopQuery->getById($shopId);

        /** @var Plan $plan */
        $plan = $this->planQuery->getById($planId);

        // Free plan activation (no charge)
        if ($plan->price == 0) {

            // Cancel current paid subscription on Shopify
            $activeCharge = $shop->activeCharge();
            if ($activeCharge) {
                $chargeId = $activeCharge->charge_id;
                $gid = str_contains((string) $chargeId, 'gid://') ? $chargeId : "gid://shopify/AppSubscription/{$chargeId}";

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
                    $shopifyService = new ShopifyService($shop);
                    $shopifyService->execute($query, ['id' => $gid]);
                } catch (\Exception $e) {
                    Log::error("Failed to cancel Shopify subscription: " . $e->getMessage());
                }
            }

            call_user_func($this->cancelCurrentPlan, $shopId);
            $this->chargeCommand->delete($chargeRef, $shopId);

            // Mark all existing ACTIVE charges in DB as CANCELLED
            \Osiset\ShopifyApp\Storage\Models\Charge::where('user_id', $shop->getId()->toNative())
                ->where('status', 'ACTIVE')
                ->update([
                    'status' => 'CANCELLED',
                    'cancelled_on' => now(),
                    'expires_on' => now(),
                ]);

            $transfer = new ChargeTransfer;
            $transfer->shopId = $shopId;
            $transfer->planId = $planId;
            $transfer->chargeReference = $chargeRef;
            $transfer->chargeType = ChargeType::RECURRING();
            $transfer->chargeStatus = ChargeStatus::ACTIVE();
            $transfer->activatedOn = Carbon::today();
            $transfer->billingOn = null;
            $transfer->trialEndsOn = null;
            $transfer->planDetails = $this->chargeHelper->details($plan, $shop, $host);

            $charge = $this->chargeCommand->make($transfer);
            $this->shopCommand->setToPlan($shopId, $planId);

            event(new PlanActivatedEvent($shop, $plan, $charge));

            return $charge;
        }

        // Normal plan activation (same as package)
        $chargeType = ChargeType::fromNative($plan->getType()->toNative()); // @phpstan-ignore-line
        $response = $shop->apiHelper()->activateCharge($chargeType, $chargeRef);
        call_user_func($this->cancelCurrentPlan, $shopId);
        $this->chargeCommand->delete($chargeRef, $shopId);

        // Mark all existing ACTIVE charges in DB as CANCELLED before creating the new active charge
        \Osiset\ShopifyApp\Storage\Models\Charge::where('user_id', $shop->getId()->toNative())
            ->where('status', 'ACTIVE')
            ->update([
                'status' => 'CANCELLED',
                'cancelled_on' => now(),
                'expires_on' => now(),
            ]);

        $transfer = new ChargeTransfer;
        $transfer->shopId = $shopId;
        $transfer->planId = $planId;
        $transfer->chargeReference = $chargeRef;
        $transfer->chargeType = $chargeType;
        $transfer->chargeStatus = ChargeStatus::fromNative(strtoupper($response['status'])); // @phpstan-ignore-line

        if ($plan->isType(PlanType::RECURRING())) {
            $transfer->activatedOn = new Carbon($response['activated_on']);
            $transfer->billingOn = new Carbon($response['billing_on']);
            $transfer->trialEndsOn = new Carbon($response['trial_ends_on']);
        } else {
            $transfer->activatedOn = Carbon::today();
        }

        $transfer->planDetails = $this->chargeHelper->details($plan, $shop, $host);
        $charge = $this->chargeCommand->make($transfer);
        $this->shopCommand->setToPlan($shopId, $planId);

        event(new PlanActivatedEvent($shop, $plan, $charge));

        return $charge;
    }
}
