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
    }

    public function testStripeCustomer()
    {
        $this->assertFalse($this->user->isStripeCustomer());
        $this->assertFalse($this->user->deleteStripeCustomer());

        $this->assertNotNull($this->user->createStripeCustomer());
        $this->assertTrue($this->user->isStripeCustomer());
        $this->assertNotNull($this->user->createStripeCustomer());

        $this->assertTrue($this->user->deleteStripeCustomer());
        $this->assertFalse($this->user->deleteStripeCustomer());
    }

    public function testUpdateStripeToken()
    {
        $this->assertFalse($this->user->updateStripeToken('stripe_token_here'));
        $this->assertNotNull($this->user->createStripeCustomer());

        $customer = $this->user->getStripeCustomer();
        $this->assertNull($customer->stripe_token);

        $this->assertTrue($this->user->updateStripeToken('stripe_token_here'));

        $customer = $this->user->getStripeCustomer();
        $this->assertEquals($customer->stripe_token, 'stripe_token_here');
    }

    public function testChargeOnSubscribeTo()
    {
        $customer = $this->user->createStripeCustomer();
        $subscription = $this->user->withStripe()->withStripeToken($this->getTestStripeToken())->subscribeTo($this->plan, 53);
        sleep(1);

        $this->assertTrue($subscription->is_paid);
        $this->assertEquals($subscription->recurring_each_days, 53);
        $this->assertEquals($subscription->charging_price, $this->plan->price);
        $this->assertEquals($subscription->charging_currency, $this->plan->currency);
    }

    public function testChargeOnSubscribeToUntil()
    {
        $subscription = $this->user->withStripe()->withStripeToken($this->getTestStripeToken())->subscribeToUntil($this->plan, Carbon::now()->addDays(53));
        sleep(1);

        $this->assertTrue($subscription->is_paid);
        $this->assertEquals($subscription->recurring_each_days, 53);
        $this->assertEquals($subscription->charging_price, $this->plan->price);
        $this->assertEquals($subscription->charging_currency, $this->plan->currency);
    }

    public function testChargeOnSubscribeToWithDifferentPrice()
    {
        $customer = $this->user->createStripeCustomer();
        $subscription = $this->user->withStripe()->setChargingPriceTo(10, 'USD')->withStripeToken($this->getTestStripeToken())->subscribeTo($this->plan, 53);
        sleep(1);

        $this->assertTrue($subscription->is_paid);
        $this->assertEquals($subscription->recurring_each_days, 53);
        $this->assertEquals($subscription->charging_price, '10');
        $this->assertEquals($subscription->charging_currency, 'USD');
    }

    public function testChargeOnSubscribeToUntilWithDifferentPrice()
    {
        $subscription = $this->user->withStripe()->setChargingPriceTo(100, 'JPY')->withStripeToken($this->getTestStripeToken())->subscribeToUntil($this->plan, Carbon::now()->addDays(53));
        sleep(1);

        $this->assertTrue($subscription->is_paid);
        $this->assertEquals($subscription->recurring_each_days, 53);
        $this->assertEquals($subscription->charging_price, 100);
        $this->assertEquals($subscription->charging_currency, 'JPY');
    }

    public function testChargeWithStoredStripeToken()
    {
        $customer = $this->user->createStripeCustomer();
        $this->user->updateStripeToken($this->getTestStripeToken());

        $subscription = $this->user->withStripe()->subscribeTo($this->plan, 53);
        sleep(1);

        $this->assertTrue($subscription->is_paid);
        $this->assertEquals($subscription->recurring_each_days, 53);
        $this->assertEquals($subscription->charging_price, $this->plan->price);
        $this->assertEquals($subscription->charging_currency, $this->plan->currency);
    }
}
