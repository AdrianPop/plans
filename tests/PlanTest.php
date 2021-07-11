<?php

declare(strict_types=1);

namespace Rennokki\Plans\Test;

use Carbon\Carbon;
use Rennokki\Plans\Models\PlanModel;
use Rennokki\Plans\Test\Models\User;

class PlanTest extends TestCase
{
    protected User $user;
    protected PlanModel $plan;
    protected PlanModel $upgradePlan;
    protected PlanModel $newPlan;
    protected PlanModel $smsPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = factory(\Rennokki\Plans\Test\Models\User::class)->create();
        $this->plan = factory(\Rennokki\Plans\Models\PlanModel::class)->create(['tag' => 'default']);
        $this->upgradePlan = factory(\Rennokki\Plans\Models\PlanModel::class)->create(['tag' => 'default']);
        $this->newPlan = factory(\Rennokki\Plans\Models\PlanModel::class)->create(['tag' => 'new']);
        $this->smsPlan = factory(\Rennokki\Plans\Models\PlanModel::class)->create(['tag' => 'sms']);
    }

    public function testNoSubscriptions(): void
    {
        $this->assertNull($this->user->subscriptions()->first());
        $this->assertNull($this->user->activeSubscription());
        $this->assertNull($this->user->lastActiveSubscription());
        $this->assertFalse($this->user->hasActiveSubscription());
    }

    public function testSubscribeToWithInvalidDuration(): void
    {
        $this->assertFalse($this->user->subscribeTo($this->plan, 0));
        $this->assertFalse($this->user->subscribeTo($this->plan, -1));
    }

    public function testSubscribeToWithInvalidDate(): void
    {
        $this->assertFalse($this->user->subscribeToUntil($this->plan, Carbon::yesterday()));
        $this->assertFalse($this->user->subscribeToUntil($this->plan, Carbon::yesterday()->toDateTimeString()));
        $this->assertFalse($this->user->subscribeToUntil($this->plan, Carbon::yesterday()->toDateString()));
    }

    public function testSubscribeTo(): void
    {
        $subscription = $this->user->subscribeTo($this->plan, 15);
        $this->user->subscribeTo($this->smsPlan, 365 * 10, false);
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->plan->id);
        $this->assertNotNull($this->user->subscriptions()->first());
        $this->assertEquals(2, $this->user->subscriptions()->count());
        $this->assertEquals($this->user->subscriptions()->expired()->count(), 0);
        $this->assertEquals($this->user->subscriptions()->recurring()->count(), 1);
        $this->assertEquals($this->user->subscriptions()->cancelled()->count(), 0);

        $this->assertNotNull($this->user->activeSubscription());
        $this->assertNotNull($this->user->lastActiveSubscription());
        $this->assertTrue($this->user->hasActiveSubscription());

        $this->assertNotNull($this->user->activeSubscription($this->smsPlan->tag));
        $this->assertNotNull($this->user->activeSubscription($this->smsPlan->tag));
        $this->assertTrue($this->user->hasActiveSubscription($this->smsPlan->tag));

        $this->assertNull($this->user->activeSubscription($this->newPlan->tag));

        $this->assertEquals($subscription->remainingDays(), 14);
    }

    public function testSubscribeToUntilWithCarboninstance(): void
    {
        $subscription = $this->user->subscribeToUntil($this->plan, Carbon::now()->addDays(15));
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->plan->id);
        $this->assertNotNull($this->user->subscriptions()->first());
        $this->assertEquals($this->user->subscriptions()->expired()->count(), 0);
        $this->assertEquals($this->user->subscriptions()->recurring()->count(), 1);
        $this->assertEquals($this->user->subscriptions()->cancelled()->count(), 0);
        $this->assertNotNull($this->user->activeSubscription());
        $this->assertNotNull($this->user->lastActiveSubscription());
        $this->assertTrue($this->user->hasActiveSubscription());
        $this->assertEquals($subscription->remainingDays(), 14);
    }

    public function testSubscribeToUntilWithDateTimeString(): void
    {
        $subscription = $this->user->subscribeToUntil($this->plan, Carbon::now()->addDays(15)->toDateTimeString());
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->plan->id);
        $this->assertNotNull($this->user->subscriptions()->first());
        $this->assertNotNull($this->user->activeSubscription());
        $this->assertNotNull($this->user->lastActiveSubscription());
        $this->assertTrue($this->user->hasActiveSubscription());
        $this->assertEquals($subscription->remainingDays(), 14);
    }

    public function testSubscribeToUntilWithDateString(): void
    {
        $subscription = $this->user->subscribeToUntil($this->plan, Carbon::now()->addDays(15)->toDateString());
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->plan->id);
        $this->assertNotNull($this->user->subscriptions()->first());
        $this->assertNotNull($this->user->activeSubscription());
        $this->assertNotNull($this->user->lastActiveSubscription());
        $this->assertTrue($this->user->hasActiveSubscription());
        $this->assertEquals($subscription->remainingDays(), 14);
    }

    public function testUpgradeWithWrongDuration(): void
    {
        $subscription = $this->user->subscribeTo($this->plan, 15);
        sleep(1);

        $this->assertFalse($this->user->upgradeCurrentPlanTo($this->newPlan, 0));
        $this->assertFalse($this->user->upgradeCurrentPlanTo($this->newPlan, -1));
    }

    public function testUpgradeToWithInvalidDate(): void
    {
        $subscription = $this->user->subscribeTo($this->plan, 15);
        sleep(1);

        $this->assertFalse($this->user->upgradeCurrentPlanToUntil($this->plan, Carbon::yesterday()));
        $this->assertFalse($this->user->upgradeCurrentPlanToUntil($this->plan, Carbon::yesterday()->toDateTimeString()));
        $this->assertFalse($this->user->upgradeCurrentPlanToUntil($this->plan, Carbon::yesterday()->toDateString()));
    }

    public function testUpgradeToNow(): void
    {
        $this->user->subscribeTo($this->plan, 15);
        sleep(1);

        $subscription = $this->user->upgradeCurrentPlanTo($this->upgradePlan, 30, true);

        $this->assertEquals($subscription->plan_id, $this->upgradePlan->id);
        $this->assertEquals($subscription->remainingDays(), 44);
    }

    public function testUpgradeToAnotherCycle(): void
    {
        $subscription = $this->user->subscribeTo($this->plan, 15);
        sleep(1);

        $this->user->upgradeCurrentPlanTo($this->newPlan, 30, false);

        $this->assertEquals($subscription->plan_id, $this->plan->id);
        $this->assertEquals($subscription->remainingDays(), 14);
        $this->assertEquals($this->user->subscriptions->count(), 2);
    }

    public function testUpgradeToNowWithCarbonInstance(): void
    {
        $subscription = $this->user->subscribeTo($this->plan, 15);
        sleep(1);

        $subscription = $this->user->upgradeCurrentPlanToUntil($this->newPlan, Carbon::now()->addDays(30), true);
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->newPlan->id);
        $this->assertEquals($subscription->remainingDays(), 29);
    }

    public function testUpgradeToAnotherCycleWithCarbonInstance(): void
    {
        $subscription = $this->user->subscribeTo($this->plan, 15);
        sleep(1);

        $this->user->upgradeCurrentPlanToUntil($this->newPlan, Carbon::now()->addDays(30), false);
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->plan->id);
        $this->assertEquals($subscription->remainingDays(), 14);
        $this->assertEquals($this->user->subscriptions->count(), 2);
    }

    public function testUpgradeToNowWithDateTimeString(): void
    {
        $subscription = $this->user->subscribeTo($this->plan, 15);
        sleep(1);

        $subscription = $this->user->upgradeCurrentPlanToUntil($this->newPlan, Carbon::now()->addDays(30)->toDateTimeString(), true);
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->newPlan->id);
        $this->assertEquals($subscription->remainingDays(), 29);
    }

    public function testUpgradeToAnotherCycleWithDateTimeString(): void
    {
        $subscription = $this->user->subscribeTo($this->plan, 15);
        sleep(1);

        $this->user->upgradeCurrentPlanToUntil($this->newPlan, Carbon::now()->addDays(30)->toDateTimeString(), false);
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->plan->id);
        $this->assertEquals($subscription->remainingDays(), 14);
        $this->assertEquals($this->user->subscriptions->count(), 2);
    }

    public function testUpgradeToNowWithDateString(): void
    {
        $subscription = $this->user->subscribeTo($this->plan, 15);
        sleep(1);

        $subscription = $this->user->upgradeCurrentPlanToUntil($this->newPlan, Carbon::now()->addDays(30)->toDateString(), true);
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->newPlan->id);
        $this->assertEquals($subscription->remainingDays(), 29);
    }

    public function testUpgradeToAnotherCycleWithDateString(): void
    {
        $subscription = $this->user->subscribeTo($this->plan, 15);
        sleep(1);

        $this->user->upgradeCurrentPlanToUntil($this->newPlan, Carbon::now()->addDays(30)->toDateString(), false);
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->plan->id);
        $this->assertEquals($subscription->remainingDays(), 14);
        $this->assertEquals($this->user->subscriptions->count(), 2);
    }

    public function testExtendWithWrongDuration(): void
    {
        $subscription = $this->user->subscribeTo($this->plan, 15);
        sleep(1);

        $this->assertFalse($this->user->extendCurrentSubscriptionWith(-1));
        $this->assertEquals($subscription->plan_id, $this->plan->id);
        $this->assertEquals($subscription->remainingDays(), 14);
    }

    public function testExtendNow(): void
    {
        $subscription = $this->user->subscribeTo($this->plan, 15);
        sleep(1);

        $subscription = $this->user->extendCurrentSubscriptionWith(30, true);
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->plan->id);
        $this->assertEquals($subscription->remainingDays(), 44);
    }

    public function testExtendToAnotherCycle(): void
    {
        $subscription = $this->user->subscribeTo($this->plan, 15);
        sleep(1);

        $this->user->extendCurrentSubscriptionWith(30, false);
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->plan->id);
        $this->assertEquals($subscription->remainingDays(), 14);
        $this->assertEquals($this->user->subscriptions->count(), 2);
    }

    public function testExtendNowWithCarbonInstance(): void
    {
        $subscription = $this->user->subscribeTo($this->plan, 15);
        sleep(1);

        $subscription = $this->user->extendCurrentSubscriptionUntil(Carbon::now()->addDays(30), true);
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->plan->id);
        $this->assertEquals($subscription->remainingDays(), 29);
    }

    public function testExtendToAnotherCycleWithCarbonInstance(): void
    {
        $subscription = $this->user->subscribeTo($this->plan, 15);
        sleep(1);

        $this->user->extendCurrentSubscriptionUntil(Carbon::now()->addDays(30), false);
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->plan->id);
        $this->assertEquals($subscription->remainingDays(), 14);
        $this->assertEquals($this->user->subscriptions->count(), 2);
    }

    public function testExtendNowWithDateTimeString(): void
    {
        $subscription = $this->user->subscribeTo($this->plan, 15);
        sleep(1);

        $subscription = $this->user->extendCurrentSubscriptionUntil(Carbon::now()->addDays(30)->toDateTimeString(), true);
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->plan->id);
        $this->assertEquals($subscription->remainingDays(), 29);
    }

    public function testExtendToAnotherCycleWithDateTimeString(): void
    {
        $subscription = $this->user->subscribeTo($this->plan, 15);
        sleep(1);

        $this->user->extendCurrentSubscriptionUntil(Carbon::now()->addDays(30)->toDateTimeString(), false);
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->plan->id);
        $this->assertEquals($subscription->remainingDays(), 14);
        $this->assertEquals($this->user->subscriptions->count(), 2);
    }

    public function testExtendNowWithDateString(): void
    {
        $subscription = $this->user->subscribeTo($this->plan, 15);
        sleep(1);

        $subscription = $this->user->extendCurrentSubscriptionUntil(Carbon::now()->addDays(30)->toDateString(), true);
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->plan->id);
        $this->assertEquals($subscription->remainingDays(), 29);
    }

    public function testExtendToAnotherCycleWithDateString(): void
    {
        $subscription = $this->user->subscribeTo($this->plan, 15);
        sleep(1);

        $this->user->extendCurrentSubscriptionUntil(Carbon::now()->addDays(30)->toDateString(), false);
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->plan->id);
        $this->assertEquals($subscription->remainingDays(), 14);
        $this->assertEquals($this->user->subscriptions->count(), 2);
    }

    public function testUpgradeFromUserWithoutActiveSubscription(): void
    {
        $subscription = $this->user->upgradeCurrentPlanTo($this->newPlan, 15, true);
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->newPlan->id);
        $this->assertEquals($subscription->remainingDays(), 14);
    }

    public function testUpgradeUntilFromUserWithoutActiveSubscription(): void
    {
        $subscription = $this->user->upgradeCurrentPlanToUntil($this->newPlan, Carbon::now()->addDays(15)->toDateString(), true);
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->newPlan->id);
        $this->assertEquals($subscription->remainingDays(), 14);
    }

    public function testUpgradeToFromUserNow(): void
    {
        $this->user->subscribeTo($this->smsPlan, 3650, false);
        $subscription = $this->user->subscribeTo($this->plan, 15);
        sleep(1);

        $this->assertTrue($this->user->hasActiveSubscription($this->plan->tag));
        $subscription = $this->user->upgradeCurrentPlanTo($this->upgradePlan, 15, true);
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->upgradePlan->id);

        $this->assertEquals(2, $this->user->subscriptions()->count());
        $this->assertEquals($subscription->remainingDays(), 29);
    }

    public function testUpgradeToFromUserToAnotherCycle(): void
    {
        $subscription = $this->user->subscribeTo($this->plan, 15);
        sleep(1);

        $this->user->upgradeCurrentPlanTo($this->newPlan, 30, false);
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->plan->id);
        $this->assertEquals($subscription->remainingDays(), 14);
        $this->assertEquals($this->user->subscriptions->count(), 2);
    }

    public function testExtendFromUserWithoutActiveSubscription(): void
    {
        $subscription = $this->user->extendCurrentSubscriptionWith(15, true);
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->plan->id);
        $this->assertEquals($subscription->remainingDays(), 14);
    }

    public function testExtendFromUserNow(): void
    {
        $subscription = $this->user->subscribeTo($this->plan, 15);
        sleep(1);

        $subscription = $this->user->extendCurrentSubscriptionWith(15, true);
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->plan->id);
        $this->assertEquals($subscription->remainingDays(), 29);
    }

    public function testExtendFromUserToAnotherCycle(): void
    {
        $subscription = $this->user->subscribeTo($this->plan, 15);
        sleep(1);

        $this->user->extendCurrentSubscriptionWith(15, false);
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->plan->id);
        $this->assertEquals($subscription->remainingDays(), 14);
        $this->assertEquals($this->user->subscriptions->count(), 2);
    }

    public function testCancelSubscription(): void
    {
        $subscription = $this->user->subscribeTo($this->plan, 15);
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->plan->id);
        $this->assertEquals($subscription->remainingDays(), 14);

        $subscription = $this->user->cancelCurrentSubscription();
        sleep(1);

        $this->assertNotNull($subscription);
        $this->assertTrue($subscription->isCancelled());
        $this->assertTrue($subscription->isPendingCancellation());
        $this->assertFalse($this->user->cancelCurrentSubscription());
        $this->assertEquals($this->user->subscriptions()->cancelled()->count(), 1);
    }

    public function testCancelSubscriptionFromUser(): void
    {
        $subscription = $this->user->subscribeTo($this->plan, 15);
        sleep(1);

        $this->assertEquals($subscription->plan_id, $this->plan->id);
        $this->assertEquals($subscription->remainingDays(), 14);

        $subscription = $this->user->cancelCurrentSubscription();
        sleep(1);

        $this->assertNotNull($subscription);
        $this->assertTrue($subscription->isCancelled());
        $this->assertTrue($subscription->isPendingCancellation());
        $this->assertFalse($this->user->cancelCurrentSubscription());
    }

    public function testCancelSubscriptionWithoutSubscription(): void
    {
        $this->assertFalse($this->user->cancelCurrentSubscription());
    }
}
