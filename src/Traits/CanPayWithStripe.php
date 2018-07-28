<?php

namespace Rennokki\Plans\Traits;

use Stripe\Stripe;
use Stripe\Charge as StripeCharge;
use Stripe\Customer as StripeCustomer;
use Rennokki\Plans\Helpers\StripeHelper;

trait CanPayWithStripe
{
    protected $subscriptionPaymentMethod = null;
    protected $chargingPrice = null;
    protected $chargingCurrency = null;
    protected $stripeToken = null;

    /**
     * Get the Stripe Customer relationship.
     *
     * @return morphOne The relationship.
     */
    public function stripeCustomer()
    {
        return $this->morphOne(config('plans.models.stripeCustomer'), 'model');
    }

    /**
     * Check if the model is already stored as a customer.
     *
     * @return bool
     */
    public function isStripeCustomer()
    {
        return (bool) ($this->stripeCustomer()->count() == 1);
    }

    /**
     * Get the local Stripe Customer instance.
     *
     * @return null|StripeCustomerModel The Stripe Customer instance.
     */
    public function getStripeCustomer()
    {
        if (! $this->isStripeCustomer()) {
            return;
        }

        return $this->stripeCustomer()->first();
    }

    /**
     * Create a local Stripe Customer instance.
     *
     * @return bool|StripeCustomerModel Fresh Stripe Customer instance, or false on error.
     */
    public function createStripeCustomer()
    {
        if ($this->isStripeCustomer()) {
            return $this->getStripeCustomer();
        }

        $this->initiateStripeAPI();

        try {
            $customer = StripeCustomer::create([]);
        } catch (\Exception $e) {
            return false;
        }

        $model = config('plans.models.stripeCustomer');

        return $this->stripeCustomer()->save(new $model([
            'customer_id' => $customer->id,
        ]));
    }

    /**
     * Delete the local Stripe Customer.
     *
     * @return bool
     */
    public function deleteStripeCustomer()
    {
        if (! $this->isStripeCustomer()) {
            return false;
        }

        return (bool) $this->stripeCustomer()->delete();
    }

    /**
     * Initiate the Stripe API key.
     *
     * @return Stripe
     */
    public function initiateStripeAPI()
    {
        if (getenv('STRIPE_SECRET')) {
            return Stripe::setApiKey(getenv('STRIPE_SECRET'));
        }

        return Stripe::setApiKey((env('STRIPE_SECRET')) ?: config('services.stripe.secret'));
    }

    /**
     * Set Stripe as payment method.
     *
     * @return void
     */
    public function withStripe()
    {
        $this->subscriptionPaymentMethod = 'stripe';

        return $this;
    }

    /**
     * Set Stripe token.
     *
     * @return self
     */
    public function withStripeToken($stripeToken = null)
    {
        if (! $stripeToken && $this->isStripeCustomer()) {
            $customer = $this->getStripeCustomer();

            if ($customer->stripe_token) {
                $this->stripeToken = $customer->stripe_token;

                return $this;
            }

            return $this;
        }

        $this->stripeToken = $stripeToken;

        return $this;
    }

    /**
     * Change the price on demand for subscriptions.
     *
     * @return self
     */
    public function setChargingPriceTo($chargingPrice, $chargingCurrency)
    {
        $this->chargingPrice = $chargingPrice;
        $this->chargingCurrency = $chargingCurrency;

        return $this;
    }

    /**
     * Change the model's Stripe Customer's token.
     *
     * @return bool
     */
    public function updateStripeToken($newStripeToken)
    {
        if (! $this->isStripeCustomer()) {
            return false;
        }

        return $this->getStripeCustomer()->update([
            'stripe_token' => $newStripeToken,
        ]);
    }

    /**
     * Initiate a charge with Stripe.
     *
     * @param float $amount The amount charged.
     * @param string $currency The currency code.
     * @param string $description The description of the payment. (optional)
     * @return Stripe\Charge
     */
    public function chargeWithStripe($amount, $currency, $description = null)
    {
        if (! $this->stripeToken) {
            return false;
        }

        $customer = $this->getStripeCustomer();

        if (! $customer) {
            $customer = $this->createStripeCustomer();
        }

        $this->updateStripeToken($this->stripeToken);

        $this->initiateStripeAPI();

        return StripeCharge::create([
            'amount' => StripeHelper::fromRealAmountToStripe($amount, $currency),
            'currency' => $currency,
            'description' => $description,
            'source' => $this->stripeToken,
        ]);
    }

    /**
     * Check wether the user can be charged for a new subscription, based on last subscription's status.
     *
     * @return bool
     */
    public function canBeChargedForNewSubscription()
    {
        if (! $this->hasSubscriptions()) {
            return false;
        }

        $currentSubscription = $subscriber->currentSubscription();

        if ($currentSubscription) {
            return false;
        }

        $lastActiveSubscription = $subscriber->lastActiveSubscription();

        if (! $lastActiveSubscription->is_recurring || $lastActiveSubscription->isCancelled()) {
            return false;
        }

        if ($lastActiveSubscription->payment_method && ! $lastActiveSubscription->is_paid) {
            return false;
        }

        return true;
    }
}
