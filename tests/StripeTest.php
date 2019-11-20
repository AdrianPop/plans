<?php

namespace Rennokki\Plans\Test;

use Carbon\Carbon;

class StripeTest extends TestCase
{
    protected $user;
    protected $plan;
    protected $newPlan;

    public function setUp()
    {
        parent::setUp();

        $this->user = factory(\Rennokki\Plans\Test\Models\User::class)->create();
        $this->plan = factory(\Rennokki\Plans\Models\PlanModel::class)->create();
        $this->newPlan = factory(\Rennokki\Plans\Models\PlanModel::class)->create();

        $this->initiateStripeAPI();
    }

    public function testStripeCustomer()
    {
        if (! getenv('STRIPE_SECRET')) {
            $this->markTestSkipped();
        }

        $this->assertFalse($this->user->isStripeCustomer());
        $this->assertFalse($this->user->deleteStripeCustomer());

        $this->assertNotNull($this->user->createStripeCustomer());
        $this->assertTrue($this->user->isStripeCustomer());
        $this->assertNotNull($this->user->createStripeCustomer());

        $this->assertTrue($this->user->deleteStripeCustomer());
        $this->assertFalse($this->user->deleteStripeCustomer());
    }

    public function testChargeOnSubscribeTo()
    {
        if (! getenv('STRIPE_SECRET')) {
            $this->markTestSkipped();
        }

        $customer = $this->user->createStripeCustomer();
        $subscription = $this->user->withStripe()->withStripeToken($this->getStripeTestToken())->subscribeTo($this->plan, 53);
        sleep(1);

        $this->assertTrue($subscription->is_paid);
        $this->assertEquals($subscription->recurring_each_days, 53);
        $this->assertEquals($subscription->charging_price, $this->plan->price);
        $this->assertEquals($subscription->charging_currency, $this->plan->currency);
        $this->assertEquals($this->user->subscriptions()->stripe()->count(), 1);
    }

    public function testChargeOnSubscribeToWithInvalidToken()
    {
        if (! getenv('STRIPE_SECRET')) {
            $this->markTestSkipped();
        }

        $customer = $this->user->createStripeCustomer();
        $subscription = $this->user->withStripe()->withStripeToken($this->getInvalidStripeToken())->subscribeTo($this->plan, 53);
        sleep(1);

        $this->assertFalse($subscription->is_paid);
        $this->assertEquals($subscription->recurring_each_days, 53);
        $this->assertEquals($subscription->charging_price, $this->plan->price);
        $this->assertEquals($subscription->charging_currency, $this->plan->currency);
        $this->assertEquals($this->user->subscriptions()->stripe()->count(), 1);
        $this->assertEquals($this->user->subscriptions()->stripe()->paid()->count(), 0);
    }

    public function testChargeOnSubscribeToUntil()
    {
        if (! getenv('STRIPE_SECRET')) {
            $this->markTestSkipped();
        }

        $subscription = $this->user->withStripe()->withStripeToken($this->getStripeTestToken())->subscribeToUntil($this->plan, Carbon::now()->addDays(53));
        sleep(1);

        $this->assertTrue($subscription->is_paid);
        $this->assertEquals($subscription->charging_price, $this->plan->price);
        $this->assertEquals($subscription->charging_currency, $this->plan->currency);
        $this->assertEquals($this->user->subscriptions()->stripe()->count(), 1);
    }

    public function testChargeOnSubscribeToUntilWithInvalidToken()
    {
        if (! getenv('STRIPE_SECRET')) {
            $this->markTestSkipped();
        }

        $subscription = $this->user->withStripe()->withStripeToken($this->getInvalidStripeToken())->subscribeToUntil($this->plan, Carbon::now()->addDays(53));
        sleep(1);

        $this->assertFalse($subscription->is_paid);
        $this->assertEquals($subscription->charging_price, $this->plan->price);
        $this->assertEquals($subscription->charging_currency, $this->plan->currency);
        $this->assertEquals($this->user->subscriptions()->stripe()->count(), 1);
        $this->assertEquals($this->user->subscriptions()->stripe()->paid()->count(), 0);
    }

    public function testChargeOnSubscribeToWithDifferentPrice()
    {
        if (! getenv('STRIPE_SECRET')) {
            $this->markTestSkipped();
        }

        $customer = $this->user->createStripeCustomer();
        $subscription = $this->user->withStripe()->setChargingPriceTo(10, 'USD')->withStripeToken($this->getStripeTestToken())->subscribeTo($this->plan, 53);
        sleep(1);

        $this->assertTrue($subscription->is_paid);
        $this->assertEquals($subscription->recurring_each_days, 53);
        $this->assertEquals($subscription->charging_price, '10');
        $this->assertEquals($subscription->charging_currency, 'USD');
        $this->assertEquals($this->user->subscriptions()->stripe()->count(), 1);
    }

    public function testChargeOnSubscribeToWithDifferentPriceAndInvalidToken()
    {
        if (! getenv('STRIPE_SECRET')) {
            $this->markTestSkipped();
        }

        $customer = $this->user->createStripeCustomer();
        $subscription = $this->user->withStripe()->setChargingPriceTo(10, 'USD')->withStripeToken($this->getInvalidStripeToken())->subscribeTo($this->plan, 53);
        sleep(1);

        $this->assertFalse($subscription->is_paid);
        $this->assertEquals($subscription->recurring_each_days, 53);
        $this->assertEquals($subscription->charging_price, '10');
        $this->assertEquals($subscription->charging_currency, 'USD');
        $this->assertEquals($this->user->subscriptions()->stripe()->count(), 1);
        $this->assertEquals($this->user->subscriptions()->stripe()->paid()->count(), 0);
    }

    public function testChargeOnSubscribeToUntilWithDifferentPrice()
    {
        if (! getenv('STRIPE_SECRET')) {
            $this->markTestSkipped();
        }

        $subscription = $this->user->withStripe()->setChargingPriceTo(100, 'JPY')->withStripeToken($this->getStripeTestToken())->subscribeToUntil($this->plan, Carbon::now()->addDays(53));
        sleep(1);

        $this->assertTrue($subscription->is_paid);
        $this->assertEquals($subscription->recurring_each_days, 53);
        $this->assertEquals($subscription->charging_price, 100);
        $this->assertEquals($subscription->charging_currency, 'JPY');
        $this->assertEquals($this->user->subscriptions()->stripe()->count(), 1);
    }

    public function testChargeOnSubscribeToUntilWithDifferentPriceAndInvalidStripeToken()
    {
        if (! getenv('STRIPE_SECRET')) {
            $this->markTestSkipped();
        }

        $subscription = $this->user->withStripe()->withStripeToken($this->getInvalidStripeToken())->setChargingPriceTo(100, 'JPY')->subscribeToUntil($this->plan, Carbon::now()->addDays(53));
        sleep(1);

        $this->assertFalse($subscription->is_paid);
        $this->assertEquals($subscription->recurring_each_days, 53);
        $this->assertEquals($subscription->charging_price, 100);
        $this->assertEquals($subscription->charging_currency, 'JPY');
        $this->assertEquals($this->user->subscriptions()->stripe()->count(), 1);
        $this->assertEquals($this->user->subscriptions()->stripe()->paid()->count(), 0);
    }

    public function testChargeForLastDueSubscriptionWithStripe()
    {
        if (! getenv('STRIPE_SECRET')) {
            $this->markTestSkipped();
        }

        $subscription = $this->user->withStripe()->withStripeToken($this->getInvalidStripeToken())->subscribeTo($this->plan, 53);
        sleep(1);

        $this->assertFalse($subscription->is_paid);

        $subscription = $this->user->withStripe()->withStripeToken($this->getStripeTestToken())->chargeForLastDueSubscription();
        sleep(1);

        $this->assertTrue($subscription->is_paid);
        $this->assertEquals($this->user->subscriptions()->count(), 1);

        $this->assertTrue($this->user->hasActiveSubscription());

        $subscription = $this->user->withStripe()->withStripeToken($this->getStripeTestToken())->chargeForLastDueSubscription();
        $this->assertFalse($subscription);

        $subscription = $this->user->withStripe()->withStripeToken($this->getInvalidStripeToken())->chargeForLastDueSubscription();
        $this->assertFalse($subscription);
    }

    public function testChargeForLastDueSubscriptionWithInvalidStripeToken()
    {
        if (! getenv('STRIPE_SECRET')) {
            $this->markTestSkipped();
        }

        $subscription = $this->user->withStripe()->withStripeToken($this->getInvalidStripeToken())->subscribeTo($this->plan, 53);
        sleep(1);

        $this->assertFalse($subscription->is_paid);

        $subscription = $this->user->withStripe()->withStripeToken($this->getInvalidStripeToken())->chargeForLastDueSubscription();
        sleep(1);

        $this->assertFalse($subscription);
        $this->assertEquals($this->user->subscriptions()->count(), 1);
        $this->assertEquals($this->user->subscriptions()->stripe()->paid()->count(), 0);

        $this->assertFalse($this->user->hasActiveSubscription());
    }

    public function testSubscribeWhenHavingDueSubscription()
    {
        if (! getenv('STRIPE_SECRET')) {
            $this->markTestSkipped();
        }

        $subscription = $this->user->withStripe()->withStripeToken($this->getInvalidStripeToken())->subscribeTo($this->plan, 53);
        sleep(1);

        $this->assertFalse($subscription->is_paid);
        $this->assertEquals($this->user->subscriptions()->count(), 1);

        $subscription = $this->user->withStripe()->withStripeToken($this->getInvalidStripeToken())->subscribeTo($this->plan, 53);
        sleep(1);

        $this->assertFalse($subscription->is_paid);
        $this->assertEquals($this->user->subscriptions()->count(), 1);

        $newSubscription = $this->user->withStripe()->withStripeToken($this->getStripeTestToken())->subscribeTo($this->plan, 53);
        sleep(1);

        $this->assertTrue($newSubscription->is_paid);
        $this->assertEquals($this->user->subscriptions()->count(), 1);

        $this->assertFalse($this->user->hasDueSubscription());
        $this->assertTrue($this->user->hasActiveSubscription());
    }

    public function testSubscribeUntilWhenHavingDueSubscription()
    {
        if (! getenv('STRIPE_SECRET')) {
            $this->markTestSkipped();
        }

        $subscription = $this->user->withStripe()->withStripeToken($this->getInvalidStripeToken())->subscribeToUntil($this->plan, Carbon::now()->addDays(53));
        sleep(1);

        $this->assertFalse($subscription->is_paid);
        $this->assertEquals($this->user->subscriptions()->count(), 1);

        $subscription = $this->user->withStripe()->withStripeToken($this->getInvalidStripeToken())->subscribeToUntil($this->plan, Carbon::now()->addDays(53));
        sleep(1);

        $this->assertFalse($subscription->is_paid);
        $this->assertEquals($this->user->subscriptions()->count(), 1);

        $newSubscription = $this->user->withStripe()->withStripeToken($this->getStripeTestToken())->subscribeToUntil($this->plan, Carbon::now()->addDays(53));
        sleep(1);

        $this->assertTrue($newSubscription->is_paid);
        $this->assertEquals($this->user->subscriptions()->count(), 1);

        $this->assertFalse($this->user->hasDueSubscription());
        $this->assertTrue($this->user->hasActiveSubscription());
    }
}
