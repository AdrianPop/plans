<?php

namespace Rennokki\Plans\Traits;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTime;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Rennokki\Plans\Models\PlanModel;
use Rennokki\Plans\Models\PlanSubscriptionModel;

/**
 * @property $chargingPrice
 * @property $chargingCurrency
 */
trait HasPlans
{
    /**
     * Get Subscriptions relatinship.
     *
     * @return MorphMany
     */
    public function subscriptions()
    {
        return $this->morphMany(config('plans.models.subscription'), 'model');
    }

    /**
     * Return the current subscription relatinship.
     *
     * @param string $tag
     *
     * @return MorphMany
     */
    public function currentSubscription($tag = 'default')
    {
        return $this
            ->subscriptions()
            ->when($tag, fn ($q) => $q->whereHas('plan', fn ($query) => $query->where('tag', $tag)))
            ->where('starts_on', '<', Carbon::now())
            ->where('grace_until', '>', Carbon::now())
            ->orderByDesc('starts_on');
    }

    public function currentUnpaidSubscription($tag = 'default')
    {
        return $this
            ->subscriptions()
            ->unpaid()
            ->when($tag, fn ($q) => $q->whereHas('plan', fn ($query) => $query->where('tag', $tag)))
            ->where('starts_on', '<', Carbon::now())
            ->where('grace_until', '>', Carbon::now())
            ->orderByDesc('starts_on');
    }

    public function getRemainingOfByTagAndFeature($tag, $feature): float
    {
        $total = 0;

        $this->currentSubscription('sms')
            ->get()
            ->each(function (PlanSubscriptionModel $s) use(&$total) {
                $total += $s->getRemainingOf('sms');
            });

        return $total;
    }

    public function activeSubscriptionWithRemainingFeatures($tag, $feature): ?PlanSubscriptionModel
    {
        $total = 0;

        $x = $this
            ->subscriptions()
            ->whereHas('plan', fn ($query) => $query->where('tag', $tag))
            ->where('starts_on', '<', Carbon::now())
            ->where('expires_on', '>', Carbon::now())
            ->with(['usages', 'features'])
            ->orderBy('id')
            ->notCancelled()
            ->get();

        $chosenPlan = null;

        /** @var PlanSubscriptionModel $a */
        foreach ($x as $a) {
            if ($a->getRemainingOf($feature) > 0) {
                return $a;
            }
        }

        return null;
    }

    /**
     * Return the current active subscription.
     *
     * @return bool|PlanSubscriptionModel The PlanSubscriptionModel model instance.
     */
    public function activeSubscription($tag = 'default')
    {
        return $this->currentSubscription($tag)
//            ->paid()
            ->notCancelled()
            ->with(['usages', 'features'])
            ->first();
    }

    /**
     * Get the last active subscription.
     *
     * @return null|PlanSubscriptionModel The PlanSubscriptionModel model instance.
     */
    public function lastActiveSubscription($tag = 'default')
    {
        if (! $this->hasSubscriptions()) {
            return;
        }

        if ($this->hasActiveSubscription($tag)) {
            return $this->activeSubscription($tag);
        }

        return $this->subscriptions()
            ->when($tag, fn($q) => $q->whereHas('plan', fn($query) => $query->where('tag', $tag)))
            ->where('starts_on', '<', Carbon::now())
            ->where('grace_until', '>', Carbon::now())
            ->paid()
            ->notCancelled()
            ->first();
    }

    /**
     * Get the last subscription.
     *
     * @return null|PlanSubscriptionModel Either null or the last subscription.
     */
    public function lastSubscription($tag = 'default')
    {
        if (! $this->hasSubscriptions()) {
            return;
        }

        if ($this->hasActiveSubscription($tag)) {
            return $this->activeSubscription($tag);
        }

        return $this->subscriptions()
            ->when($tag, fn($q) => $q->whereHas('plan', fn($query) => $query->where('tag', $tag)))
            ->latest('starts_on')
            ->latest('id')
            ->first();
    }

    /**
     * Check if the user has an upgrade or a downgrade after this subscription
     *
     * @param  string  $tag
     *
     * @return null|PlanSubscriptionModel
     */
    public function nextSubscription($tag = 'default')
    {
        if (!$this->hasSubscriptions() || !$this->hasActiveSubscription($tag)) {
            return;
        }

        $subscription = $this->activeSubscription($tag);

        return $this->subscriptions()
            ->when($tag, fn($q) => $q->whereHas('plan', fn($query) => $query->where('tag', $tag)))
            ->where('starts_on', '>=', $subscription->expires_on)
            ->first();
    }

    /**
     * Check if the user has an upgrade or a downgrade after this subscription
     *
     * @param  string  $tag
     *
     * @return bool
     */
    public function hasNextSubscription($tag = 'default')
    {
        return (bool) $this->nextSubscription($tag);
    }

    /**
     * Get the last unpaid subscription, if any.
     *
     * @return bool|PlanSubscriptionModel
     */
    public function lastUnpaidSubscription($tag = 'default')
    {
        return $this->subscriptions()
            ->when($tag, fn($q) => $q->whereHas('plan', fn($query) => $query->where('tag', $tag)))
            ->latest('starts_on')
            ->notCancelled()
            ->unpaid()
            ->first();
    }

    public function lastShouldPaySubcription($tag = 'default')
    {
        return $this->subscriptions()
            ->when($tag, fn($q) => $q->whereHas('plan', fn($query) => $query->where('tag', $tag)))
            ->latest('starts_on')
            ->notCancelled() // cancelled = null
            ->unpaid()  // is_paid = 0
            ->shouldBePaid()    // charging_price > 0
            ->first();
    }

    public function lastCancelledAndUnpaidSubscription($tag = 'default')
    {
        return $this->subscriptions()
            ->when($tag, fn($q) => $q->whereHas('plan', fn($query) => $query->where('tag', $tag)))
            ->latest('starts_on')
            ->cancelled() // cancelled = null
            ->unpaid()  // is_paid = 0
            ->shouldBePaid()    // charging_price > 0
            ->premiumPlan()
            ->hasProforma()
            ->first();
    }

    /**
     * When a subscription is due, it means it was created, but not paid.
     * For example, on subscription, if your user wants to subscribe to another subscription and has a due (unpaid)
     * one, it will check for the last due, will cancel it, and will re-subscribe to it.
     *
     * @return null|PlanSubscriptionModel Null or a Plan Subscription instance.
     */
    public function lastDueSubscription($tag)
    {
        if (! $this->hasSubscriptions()) {
            return;
        }

        if ($this->hasActiveSubscription($tag)) {
            return;
        }

        $lastActiveSubscription = $this->lastActiveSubscription($tag);

        if (! $lastActiveSubscription) {
            return $this->lastUnpaidSubscription($tag);
        }

        $lastSubscription = $this->lastSubscription($tag);

        if ($lastActiveSubscription->is($lastSubscription)) {
            return;
        }

        return $this->lastUnpaidSubscription($tag);
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
     * @param string $tag
     *
     * @return bool Wether the binded model has an active subscription or not.
     */
    public function hasActiveSubscription($tag = 'default')
    {
        return (bool) $this->activeSubscription($tag);
    }

    /**
     * @param null $tag
     *
     * @return bool|PlanSubscriptionModel
     */
    public function subscription($tag = 'default')
    {
        return $this->activeSubscription($tag);
    }

    /**
     * Check if the mode has a due, unpaid subscription.
     *
     * @return bool
     */
    public function hasDueSubscription($tag = 'main')
    {
        return (bool) $this->lastDueSubscription($tag);
    }

    /**
     * Subscribe the binded model to a plan. Returns false if it has an active subscription already.
     *
     * @param PlanModel $plan The Plan model instance.
     * @param int $duration The duration, in days, for the subscription.
     * @param bool $isRecurring Wether the subscription should auto renew every $duration days.
     * @param bool $isPaid
     * @param CarbonInterface $startsOn
     * @param CarbonInterface $expiresOn
     *
     * @return PlanSubscriptionModel|bool The PlanSubscriptionModel model instance.
     */
    public function subscribeTo(
        $plan,
        int $duration = 30,
        bool $isRecurring = true,
        bool $isPaid = false,
        CarbonInterface $startsOn = null
    )
    {
        $subscriptionModel = config('plans.models.subscription');
        $tag = $plan->tag;

        if ($duration < 1 || $this->hasActiveSubscription($tag)) {
//            return false;
        }

        if ($this->hasDueSubscription($tag)) {
//            $this->lastDueSubscription($tag)->delete();
        }

        $startsOn = $startsOn ?: Carbon::now()->subSeconds(1);
        $expiresOn = $duration === 30 ? (clone $startsOn)->addMonths(1) : (clone $startsOn)->addDays($duration);

        $subscription = $this->subscriptions()->save(new $subscriptionModel([
            'plan_id' => $plan->id,
            'starts_on' => $startsOn,
            'expires_on' => $expiresOn->subSecond(),
            'grace_until' => (clone $expiresOn)->addDays($plan->grace),
            'cancelled_on' => null,
            'payment_method' => null,
            'is_paid' => $isPaid,
            'charging_price' => ($this->chargingPrice) ?: $plan->price,
            'charging_currency' => ($this->chargingCurrency) ?: $plan->currency,
            'is_recurring' => $isRecurring,
            'recurring_each_days' => $duration,
        ]));

        event(new \Rennokki\Plans\Events\NewSubscription($this, $subscription));

        return $subscription;
    }

    /**
     * Subscribe the binded model to a plan. Returns false if it has an active subscription already.
     *
     * @param PlanModel $plan The Plan model instance.
     * @param DateTime|string $date The date (either DateTime, date or Carbon instance) until the subscription will be
     *     extended until.
     * @param bool $isRecurring Wether the subscription should auto renew. The renewal period (in days) is the
     *     difference between now and the set date.
     * @return bool|PlanSubscriptionModel The PlanSubscriptionModel model instance.
     */
    public function subscribeToUntil($plan, $date, bool $isRecurring = true, bool $isPaid = false)
    {
        $subscriptionModel = config('plans.models.subscription');
        $date = Carbon::parse($date);
        $tag = $plan->tag;

        if ($date->lessThanOrEqualTo(Carbon::now()) || $this->hasActiveSubscription($tag)) {
            return false;
        }

        if ($this->hasDueSubscription($tag)) {
            $this->lastDueSubscription($tag)->delete();
        }

        $subscription = $this->subscriptions()->save(new $subscriptionModel([
            'plan_id' => $plan->id,
            'starts_on' => Carbon::now()->subSeconds(1),
            'expires_on' => $date,
            'grace_until' => (clone $date)->addDays($plan->grace),
            'cancelled_on' => null,
            'payment_method' => null,
            'is_paid' => $isPaid,
            'charging_price' => ($this->chargingPrice) ?: $plan->price,
            'charging_currency' => ($this->chargingCurrency) ?: $plan->currency,
            'is_recurring' => $isRecurring,
            'recurring_each_days' => Carbon::now()->subSeconds(1)->diffInDays($date),
        ]));

        event(new \Rennokki\Plans\Events\NewSubscriptionUntil($this, $subscription, $date));

        return $subscription;
    }

    /**
     * Upgrade the binded model's plan. If it is the same plan, it just extends it.
     *
     * @param PlanModel $newPlan The new Plan model instance.
     * @param int $duration The duration, in days, for the new subscription.
     * @param bool $startFromNow Wether the subscription will start from now, extending the current plan, or a new
     *     subscription will be created to extend the current one.
     * @param bool $isRecurring Wether the subscription should auto renew. The renewal period (in days) is the
     *     difference between now and the set date.
     * @return bool|PlanSubscriptionModel The PlanSubscriptionModel model instance with the new plan or the current
     *     one, extended.
     */
    public function upgradeCurrentPlanTo(
        $newPlan,
        int $duration = 30,
        bool $startFromNow = true,
        bool $isRecurring = true
    )
    {
        $tag = $newPlan->tag;

        if (! $this->hasActiveSubscription($tag)) {
            return $this->subscribeTo($newPlan, $duration, $isRecurring);
        }

        if ($duration < 1) {
            return false;
        }

        $activeSubscription = $this->activeSubscription($tag);
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
     * @param DateTime|string $date The date (either DateTime, date or Carbon instance) until the subscription will be
     *     extended until.
     * @param bool $startFromNow Wether the subscription will start from now, extending the current plan, or a new
     *     subscription will be created to extend the current one.
     * @param bool $isRecurring Wether the subscription should auto renew. The renewal period (in days) is the
     *     difference between now and the set date.
     * @return bool|PlanSubscriptionModel The PlanSubscriptionModel model instance with the new plan or the current
     *     one, extended.
     */
    public function upgradeCurrentPlanToUntil($newPlan, $date, bool $startFromNow = true, bool $isRecurring = true)
    {
        $tag = $newPlan->tag;
        if (! $this->hasActiveSubscription($tag)) {
            return $this->subscribeToUntil($newPlan, $date, $isRecurring);
        }

        $activeSubscription = $this->activeSubscription($tag);
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
     * @param bool $startFromNow Wether the subscription will be extended from now, extending to the current plan, or a
     *     new subscription will be created to extend the current one.
     * @param bool $isRecurring Wether the subscription should auto renew. The renewal period (in days) equivalent with
     *     $duration.
     * @param string $tag
     * @return bool|PlanSubscriptionModel The PlanSubscriptionModel model instance of the extended subscription.
     */
    public function extendCurrentSubscriptionWith(
        int $duration = 30,
        bool $startFromNow = true,
        bool $isRecurring = true,
        $tag = 'default'
    )
    {
        if (! $this->hasActiveSubscription($tag)) {
            if ($this->hasSubscriptions()) {
                $lastActiveSubscription = $this->lastActiveSubscription($tag);
                $lastActiveSubscription->load(['plan']);

                return $this->subscribeTo($lastActiveSubscription->plan, $duration, $isRecurring);
            }

            return $this->subscribeTo(config('plans.models.plan')::first(), $duration, $isRecurring);
        }

        if ($duration < 1) {
            return false;
        }

        $activeSubscription = $this->activeSubscription($tag);

        if ($startFromNow) {
            $activeSubscription->update([
                'expires_on' => Carbon::parse($activeSubscription->expires_on)->addDays($duration),
            ]);

            event(new \Rennokki\Plans\Events\ExtendSubscription($this, $activeSubscription, $startFromNow, null));

            return $activeSubscription;
        }

        $subscriptionModel = config('plans.models.subscription');
        $expiresOn = Carbon::parse($activeSubscription->expires_on)->addDays($duration);
        $subscription = $this->subscriptions()->save(new $subscriptionModel([
            'plan_id' => $activeSubscription->plan_id,
            'starts_on' => Carbon::parse($activeSubscription->expires_on),
            'expires_on' => $expiresOn,
            'grace_until' => (clone $expiresOn)->addDays($activeSubscription->plan->grace),
            'cancelled_on' => null,
            'payment_method' => null,
            'is_recurring' => $isRecurring,
            'recurring_each_days' => $duration,
        ]));

        event(new \Rennokki\Plans\Events\ExtendSubscription($this, $activeSubscription, $startFromNow, $subscription));

        return $subscription;
    }

    /**
     * Extend the subscription until a certain date.
     *
     * @param DateTime|string $date The date (either DateTime, date or Carbon instance) until the subscription will be
     *     extended until.
     * @param bool $startFromNow Wether the subscription will be extended from now, extending to the current plan, or a
     *     new subscription will be created to extend the current one.
     * @param bool $isRecurring Wether the subscription should auto renew. The renewal period (in days) is the
     *     difference between now and the set date.
     * @param string $tag
     * @return bool|PlanSubscriptionModel The PlanSubscriptionModel model instance of the extended subscription.
     */
    public function extendCurrentSubscriptionUntil(
        $date,
        bool $startFromNow = true,
        bool $isRecurring = true,
        $tag = 'default'
    )
    {
        if (! $this->hasActiveSubscription($tag)) {
            if ($this->hasSubscriptions()) {
                $lastActiveSubscription = $this->lastActiveSubscription($tag);
                $lastActiveSubscription->load(['plan']);

                return $this->subscribeToUntil($lastActiveSubscription->plan, $date, $isRecurring);
            }

            return $this->subscribeToUntil(config('plans.models.plan')::first(), $date, $isRecurring);
        }

        $date = Carbon::parse($date);
        $activeSubscription = $this->activeSubscription($tag);

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

        $subscriptionModel = config('plans.models.subscription');

        $subscription = $this->subscriptions()->save(new $subscriptionModel([
            'plan_id' => $activeSubscription->plan_id,
            'starts_on' => Carbon::parse($activeSubscription->expires_on),
            'expires_on' => $date,
            'grace_until' => (clone $date)->addDays($activeSubscription->plan->grace),
            'cancelled_on' => null,
            'payment_method' => null,
            'is_recurring' => $isRecurring,
            'recurring_each_days' => Carbon::now()->subSeconds(1)->diffInDays($date),
        ]));

        event(new \Rennokki\Plans\Events\ExtendSubscriptionUntil($this, $activeSubscription, $date, $startFromNow, $subscription));

        return $subscription;
    }

    /**
     * Cancel the current subscription.
     *
     * @return bool Wether the subscription was cancelled or not.
     */
    public function cancelCurrentSubscription($tag = 'default')
    {
        if (! $this->hasActiveSubscription($tag)) {
            return false;
        }

        $activeSubscription = $this->activeSubscription($tag);

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

    /**
     * Renew the subscription, if needed
     *
     * @param string $tag
     * @param CarbonInterface $startsOn
     *
     * @return false|PlanSubscriptionModel
     */
    public function renewSubscription($tag = 'default', CarbonInterface $startsOn = null)
    {
        if (! $this->hasSubscriptions()) {
            return false;
        }

        if ($this->hasActiveSubscription($tag)) {
//            return false;
        }

        $lastActiveSubscription = $this->lastActiveSubscription($tag);

        if (! $lastActiveSubscription) {
            return false;
        }

        if (! $lastActiveSubscription->is_recurring || $lastActiveSubscription->isCancelled()) {
            return false;
        }

        $lastActiveSubscription->load(['plan']);
        $plan = $lastActiveSubscription->plan;
        $recurringEachDays = $lastActiveSubscription->recurring_each_days;

        return $this->subscribeTo($plan, $recurringEachDays, true, false, $startsOn);
    }

    /**
     * Override all filters and renew subscription
     *
     * @param PlanSubscriptionModel $subscriptionModel
     * @param bool $useExpiresOnAsStartsOn true = sub will start from previous expires_on
     *
     * @return bool|PlanSubscriptionModel
     */
    public function renewSubscriptionFromSubscription(
        PlanSubscriptionModel $subscriptionModel,
        bool $useExpiresOnAsStartsOn = true
    )
    {
        $subscriptionModel->load(['plan']);

        $startsOn = $useExpiresOnAsStartsOn ?
            $subscriptionModel->expires_on :
            // used when an older sub needs to be renewed
            ($subscriptionModel->expires_on->isPast() ? null : $subscriptionModel->expires_on);

        return $this->subscribeTo(
            $subscriptionModel->plan,
            $subscriptionModel->recurring_each_days,
            $subscriptionModel->is_recurring,
            false,
            $startsOn
        );
    }

    /**
     * Check if the model is or was subscribed to a plan (useful to check if the client already had a trial or not)
     *
     * @param string $code
     * @param string $tag
     *
     * @return bool
     */
    public function wasSubscribedToPlan(string $code, string $tag = 'default'): bool
    {
        return $this->subscriptions()
            ->when(
                $code,
                fn($q) => $q->whereHas('plan', fn($query) => $query->where('code', $code)->where('tag', $tag))
            )
            ->where('starts_on', '<', Carbon::now())
            ->count() > 0;
    }
}
