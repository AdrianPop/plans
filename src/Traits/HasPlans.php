<?php

namespace Rennokki\Plans\Traits;

use Carbon\Carbon;

trait HasPlans
{
    use CanPayWithStripe;

    /**
     * Get Subscriptions relatinship.
     *
     * @return morphMany Relatinship.
     */
    public function subscriptions()
    {
        return $this->morphMany(config('plans.models.subscription'), 'model');
    }

    /**
     * Return the current subscription relatinship.
     *
     * @return morphMany Relatinship.
     */
    public function currentSubscription()
    {
        return $this->subscriptions()
                    ->where('starts_on', '<', Carbon::now())
                    ->where('expires_on', '>', Carbon::now());
    }

    /**
     * Return the current active subscription.
     *
     * @return PlanSubscriptionModel The PlanSubscription model instance.
     */
    public function activeSubscription()
    {
        return $this->currentSubscription()->paid()->notCancelled()->first();
    }

    /**
     * Get the last active subscription.
     *
     * @return null|PlanSubscriptionModel The PlanSubscription model instance.
     */
    public function lastActiveSubscription()
    {
        if (! $this->hasSubscriptions()) {
            return;
        }

        if ($this->hasActiveSubscription()) {
            return $this->activeSubscription();
        }

        return $this->subscriptions()->latest('expires_on')->first();
    }

    /**
     * Check if the model has subscriptions.
     *
     * @return bool Wether the binded model has subscriptions or not.
     */
    public function hasSubscriptions()
    {
        return (bool) ($this->subscriptions()->count() > 0);
    }

    /**
     * Check if the model has an active subscription right now.
     *
     * @return bool Wether the binded model has an active subscription or not.
     */
    public function hasActiveSubscription()
    {
        return (bool) $this->activeSubscription();
    }

    /**
     * Subscribe the binded model to a plan. Returns false if it has an active subscription already.
     *
     * @param PlanModel $plan The Plan model instance.
     * @param int $duration The duration, in days, for the subscription.
     * @param bool $isRecurring Wether the subscription should auto renew every $duration days.
     * @return PlanSubscription The PlanSubscription model instance.
     */
    public function subscribeTo($plan, $duration = 30, $isRecurring = true)
    {
        $subscriptionModel = config('plans.models.subscription');

        if ($duration < 1 || $this->hasActiveSubscription()) {
            return false;
        }

        $subscription = $this->subscriptions()->save(new $subscriptionModel([
            'plan_id' => $plan->id,
            'starts_on' => Carbon::now(),
            'expires_on' => Carbon::now()->addDays($duration),
            'cancelled_on' => null,
            'payment_method' => ($this->subscriptionPaymentMethod) ?: null,
            'is_paid' => ($this->subscriptionPaymentMethod) ? false : true,
            'charging_price' => ($this->chargingPrice) ?: $plan->price,
            'charging_currency' => ($this->chargingCurrency) ?: $plan->currency,
            'is_recurring' => $isRecurring,
            'recurring_each_days' => $duration,
        ]));

        if ($this->subscriptionPaymentMethod == 'stripe') {
            try {
                $stripeCharge = $this->chargeWithStripe(($this->chargingPrice) ?: $plan->price, ($this->chargingCurrency) ?: $plan->currency);

                $subscription->update([
                    'is_paid' => true,
                ]);

                event(new \Rennokki\Plans\Events\Stripe\ChargeSuccessful($this, $subscription, $stripeCharge));
            } catch (\Exception $exception) {
                event(new \Rennokki\Plans\Events\Stripe\ChargeFailed($this, $subscription, $exception));
            }
        }

        event(new \Rennokki\Plans\Events\NewSubscription($this, $subscription));

        return $subscription;
    }

    /**
     * Subscribe the binded model to a plan. Returns false if it has an active subscription already.
     *
     * @param PlanModel $plan The Plan model instance.
     * @param DateTme|string $date The date (either DateTime, date or Carbon instance) until the subscription will be extended until.
     * @param bool $isRecurring Wether the subscription should auto renew. The renewal period (in days) is the difference between now and the set date.
     * @return PlanSubscription The PlanSubscription model instance.
     */
    public function subscribeToUntil($plan, $date, $isRecurring = true)
    {
        $subscriptionModel = config('plans.models.subscription');

        $date = Carbon::parse($date);

        if ($date->lessThanOrEqualTo(Carbon::now()) || $this->hasActiveSubscription()) {
            return false;
        }

        $subscription = $this->subscriptions()->save(new $subscriptionModel([
            'plan_id' => $plan->id,
            'starts_on' => Carbon::now(),
            'expires_on' => $date,
            'cancelled_on' => null,
            'payment_method' => ($this->subscriptionPaymentMethod) ?: null,
            'is_paid' => ($this->subscriptionPaymentMethod) ? false : true,
            'charging_price' => ($this->chargingPrice) ?: $plan->price,
            'charging_currency' => ($this->chargingCurrency) ?: $plan->currency,
            'is_recurring' => $isRecurring,
            'recurring_each_days' => Carbon::now()->diffInDays($date),
        ]));

        if ($this->subscriptionPaymentMethod == 'stripe') {
            try {
                $stripeCharge = $this->chargeWithStripe(($this->chargingPrice) ?: $plan->price, ($this->chargingCurrency) ?: $plan->currency);

                $subscription->update([
                    'is_paid' => true,
                ]);

                event(new \Rennokki\Plans\Events\Stripe\ChargeSuccessful($this, $subscription, $stripeCharge));
            } catch (\Exception $exception) {
                event(new \Rennokki\Plans\Events\Stripe\ChargeFailed($this, $subscription, $exception));
            }
        }

        event(new \Rennokki\Plans\Events\NewSubscriptionUntil($this, $subscription, $date));

        return $subscription;
    }

    /**
     * Upgrade the binded model's plan. If it is the same plan, it just extends it.
     *
     * @param PlanModel $newPlan The new Plan model instance.
     * @param int $duration The duration, in days, for the new subscription.
     * @param bool $startFromNow Wether the subscription will start from now, extending the current plan, or a new subscription will be created to extend the current one.
     * @param bool $isRecurring Wether the subscription should auto renew. The renewal period (in days) is the difference between now and the set date.
     * @return PlanSubscription The PlanSubscription model instance with the new plan or the current one, extended.
     */
    public function upgradeCurrentPlanTo($newPlan, $duration = 30, $startFromNow = true, $isRecurring = true)
    {
        if (! $this->hasActiveSubscription()) {
            return $this->subscribeTo($newPlan, $duration, $isRecurring);
        }

        if ($duration < 1) {
            return false;
        }

        $activeSubscription = $this->activeSubscription();
        $activeSubscription->load(['plan']);

        $subscription = $this->extendCurrentSubscriptionWith($duration, $startFromNow, $isRecurring);
        $oldPlan = $activeSubscription->plan;

        if ($subscription->plan_id != $newPlan->id) {
            $subscription->update([
                'plan_id' => $newPlan->id,
            ]);
        }

        event(new \Rennokki\Plans\Events\UpgradeSubscription($this, $subscription, $startFromNow, $oldPlan, $newPlan));

        return $subscription;
    }

    /**
     * Upgrade the binded model's plan. If it is the same plan, it just extends it.
     *
     * @param PlanModel $newPlan The new Plan model instance.
     * @param DateTme|string $date The date (either DateTime, date or Carbon instance) until the subscription will be extended until.
     * @param bool $startFromNow Wether the subscription will start from now, extending the current plan, or a new subscription will be created to extend the current one.
     * @param bool $isRecurring Wether the subscription should auto renew. The renewal period (in days) is the difference between now and the set date.
     * @return PlanSubscription The PlanSubscription model instance with the new plan or the current one, extended.
     */
    public function upgradeCurrentPlanToUntil($newPlan, $date, $startFromNow = true, $isRecurring = true)
    {
        if (! $this->hasActiveSubscription()) {
            return $this->subscribeToUntil($newPlan, $date, $isRecurring);
        }

        $activeSubscription = $this->activeSubscription();
        $activeSubscription->load(['plan']);

        $subscription = $this->extendCurrentSubscriptionUntil($date, $startFromNow, $isRecurring);
        $oldPlan = $activeSubscription->plan;

        $date = Carbon::parse($date);

        if ($startFromNow) {
            if ($date->lessThanOrEqualTo(Carbon::now())) {
                return false;
            }
        }

        if (Carbon::parse($subscription->expires_on)->greaterThan($date)) {
            return false;
        }

        if ($subscription->plan_id != $newPlan->id) {
            $subscription->update([
                'plan_id' => $newPlan->id,
            ]);
        }

        event(new \Rennokki\Plans\Events\UpgradeSubscriptionUntil($this, $subscription, $date, $startFromNow, $oldPlan, $newPlan));

        return $subscription;
    }

    /**
     * Extend the current subscription with an amount of days.
     *
     * @param int $duration The duration, in days, for the extension.
     * @param bool $startFromNow Wether the subscription will be extended from now, extending to the current plan, or a new subscription will be created to extend the current one.
     * @param bool $isRecurring Wether the subscription should auto renew. The renewal period (in days) equivalent with $duration.
     * @return PlanSubscription The PlanSubscription model instance of the extended subscription.
     */
    public function extendCurrentSubscriptionWith($duration = 30, $startFromNow = true, $isRecurring = true)
    {
        if (! $this->hasActiveSubscription()) {
            if ($this->hasSubscriptions()) {
                $lastActiveSubscription = $this->lastActiveSubscription();
                $lastActiveSubscription->load(['plan']);

                return $this->subscribeTo($lastActiveSubscription->plan, $duration, $isRecurring);
            }

            return $this->subscribeTo(config('plans.models.plan')::first(), $duration, $isRecurring);
        }

        if ($duration < 1) {
            return false;
        }

        $activeSubscription = $this->activeSubscription();

        if ($startFromNow) {
            $activeSubscription->update([
                'expires_on' => Carbon::parse($activeSubscription->expires_on)->addDays($duration),
            ]);

            event(new \Rennokki\Plans\Events\ExtendSubscription($this, $activeSubscription, $startFromNow, null));

            return $activeSubscription;
        }

        $subscription = config('plans.models.subscription')::create([
            'plan_id' => $activeSubscription->id,
            'model_id' => $activeSubscription->model_id,
            'model_type' => $activeSubscription->model_type,
            'starts_on' => Carbon::parse($activeSubscription->expires_on),
            'expires_on' => Carbon::parse($activeSubscription->expires_on)->addDays($duration),
            'cancelled_on' => null,
            'payment_method' => ($this->subscriptionPaymentMethod) ?: null,
            'is_recurring' => $isRecurring,
            'recurring_each_days' => $duration,
        ]);

        event(new \Rennokki\Plans\Events\ExtendSubscription($this, $activeSubscription, $startFromNow, $subscription));

        return $subscription;
    }

    /**
     * Extend the subscription until a certain date.
     *
     * @param DateTme|string $date The date (either DateTime, date or Carbon instance) until the subscription will be extended until.
     * @param bool $startFromNow Wether the subscription will be extended from now, extending to the current plan, or a new subscription will be created to extend the current one.
     * @param bool $isRecurring Wether the subscription should auto renew. The renewal period (in days) is the difference between now and the set date.
     * @return PlanSubscription The PlanSubscription model instance of the extended subscription.
     */
    public function extendCurrentSubscriptionUntil($date, $startFromNow = true, $isRecurring = true)
    {
        if (! $this->hasActiveSubscription()) {
            if ($this->hasSubscriptions()) {
                $lastActiveSubscription = $this->lastActiveSubscription();
                $lastActiveSubscription->load(['plan']);

                return $this->subscribeToUntil($lastActiveSubscription->plan, $date, $isRecurring);
            }

            return $this->subscribeToUntil(config('plans.models.plan')::first(), $date, $isRecurring);
        }

        $date = Carbon::parse($date);
        $activeSubscription = $this->activeSubscription();

        if ($startFromNow) {
            if ($date->lessThanOrEqualTo(Carbon::now())) {
                return false;
            }

            $activeSubscription->update([
                'expires_on' => $date,
            ]);

            event(new \Rennokki\Plans\Events\ExtendSubscriptionUntil($this, $activeSubscription, $date, $startFromNow, null));

            return $activeSubscription;
        }

        if (Carbon::parse($activeSubscription->expires_on)->greaterThan($date)) {
            return false;
        }

        $subscription = config('plans.models.subscription')::create([
            'plan_id' => $activeSubscription->id,
            'model_id' => $activeSubscription->model_id,
            'model_type' => $activeSubscription->model_type,
            'starts_on' => Carbon::parse($activeSubscription->expires_on),
            'expires_on' => $date,
            'cancelled_on' => null,
            'payment_method' => ($this->subscriptionPaymentMethod) ?: null,
            'is_recurring' => $isRecurring,
            'recurring_each_days' => Carbon::now()->diffInDays($date),
        ]);

        event(new \Rennokki\Plans\Events\ExtendSubscriptionUntil($this, $activeSubscription, $date, $startFromNow, $subscription));

        return $subscription;
    }

    /**
     * Cancel the current subscription.
     *
     * @return bool Wether the subscription was cancelled or not.
     */
    public function cancelCurrentSubscription()
    {
        if (! $this->hasActiveSubscription()) {
            return false;
        }

        $activeSubscription = $this->activeSubscription();

        if ($activeSubscription->isCancelled() || $activeSubscription->isPendingCancellation()) {
            return false;
        }

        $activeSubscription->update([
            'cancelled_on' => Carbon::now(),
            'is_recurring' => false,
        ]);

        event(new \Rennokki\Plans\Events\CancelSubscription($this, $activeSubscription));

        return $activeSubscription;
    }
}
