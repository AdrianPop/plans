[![Build Status](https://travis-ci.org/rennokki/plans.svg?branch=master)](https://travis-ci.org/rennokki/plans)
[![codecov](https://codecov.io/gh/rennokki/plans/branch/master/graph/badge.svg)](https://codecov.io/gh/rennokki/plans/branch/master)
[![StyleCI](https://github.styleci.io/repos/138162161/shield?branch=master)](https://github.styleci.io/repos/138162161)
[![Latest Stable Version](https://poser.pugx.org/rennokki/plans/v/stable)](https://packagist.org/packages/rennokki/plans)
[![Total Downloads](https://poser.pugx.org/rennokki/plans/downloads)](https://packagist.org/packages/rennokki/plans)
[![Monthly Downloads](https://poser.pugx.org/rennokki/plans/d/monthly)](https://packagist.org/packages/rennokki/plans)
[![License](https://poser.pugx.org/rennokki/plans/license)](https://packagist.org/packages/rennokki/plans)

[![PayPal](https://img.shields.io/badge/PayPal-donate-blue.svg)](https://paypal.me/rennokki)

# Laravel Plans
Laravel Plans is a package for SaaS-like apps that need easy management over plans, features, event-driven updates on plans or even payment tracking. If you plan selling your service with subscriptions, you're in the right place!

# Installation
Install the package:
```bash
$ composer require rennokki/plans
```

If your Laravel version does not support package discovery, add this line in the `providers` array in your `config/app.php` file:
```php
Rennokki\Plans\PlansServiceProvider::class,
```

Publish the config file & migration files:
```bash
$ php artisan vendor:publish
```

Migrate the database:
```bash
$ php artisan migrate
```

Add the `HasPlans` trait to your Eloquent model:
```php
use Rennokki\Plans\Traits\HasPlans;

class User extends Model {
    use HasPlans;
    ...
}
```

**Note: In case you plan to use your own models for the tables that this package implements, don't forget to extend from the original ones and put your models in `plans.php`**

# Creating plans
The basic unit of the subscription-like system is a plan. You can create it using `Rennokki\Plans\Models\PlanModel` or your model, if you have implemented your own.

### For the sake of the examples, all models will be `Plan`, `Subscription`, `Usage` and `Feature`.

```php
$plan = Plan::create([
    'name' => 'My awesome plan',
    'description' => 'One of the best plans out here.',
    'price' => 9.99,
    'currency' => 'EUR',
    'duration' => 30, // in days; doesn't have an utility yet; it is stored as information.
]);
```

# Plan features
Each plan has features. They can be either limited, unlimited, or there just to store the information of a certain permission.

Marking a feature type can be done using:
* `feature`, is a single string, that do not needs counting. For example, you can store permissions.
* `limit`, is a number. For this kind of feature, the `limit` field will be filled. It is meant to measure how many of that feature the user has consumed, from this subscription. For example, how many build minutes has consumed during the month (or during the Cycle, which is 30 days in this example)

**Note: For unlimited feature, the `limit` field will be set to any negative value.**

To attach features to your plan, you can use relatinship `features()`.
```php
$plan->features()->saveMany([
    new Feature([
        'name' => 'Vault access',
        'code' => 'vault.access',
        'description' => 'Offering access to the vault.',
        'type' => 'feature',
    ]),
    new Feature([
        'name' => 'Build minutes',
        'code' => 'build.minutes',
        'description' => 'Build minutes used for CI/CD.',
        'type' => 'limit',
        'limit' => 2000,
    ]),
    new Feature([
        'name' => 'Users amount',
        'code' => 'users.amount',
        'description' => 'The maximum amount of users that can use the app at the same time.',
        'type' => 'limit',
        'limit' => -1, // or any negative value treats this feature as unlimited
    ]),
    ...
]);
```

# Subscribing to plans
Your users can be subscribed to plans for a certain amount of days or until a certain date.
You can pass a third parameter to mark this subscription as recurring or non-recurring. Set it to `false` to disable recurrency.
For more info, check the **Payments** section at the end of the readme.
```php
$susbscription = $user->subscribeTo($plan, 30, true);

$subscription->remainingDays(); // 29; this is because it is 29 days, 23 hours, and so on.
```

If you plan to subscribe your users until a certain date, you can do so with dates. By default, the subscription is recurring.
```php
$user->subscribeToUntil($plan, '2018-12-21');
$user->subscribeToUntil($plan, '2018-12-21 16:54:11');
$user->subscribeToUntil($plan, Carbon::create(2018, 12, 21, 16, 54, 11));

$user->subscribeToUntil($plan, '2018-12-21', false); // recurrency deactivated
```

**Note: If the user is already subscribed, the `subscribeTo()` will return false. To avoid this, use `upgradeCurrentPlanTo()`, `upgradeCurrentPlanToUntil()`, `extendWith()` or `extendWithUntil()` methods to either upgrade or extend the subscription period with a certain amount of days or until a certain date.**

# Upgrading to other plans
```php
$user->upgradeCurrentPlanTo($anotherPlan, 60, true); // this will extend the current subscription with 60 days
$user->upgradeCurrentPlanTo($anotherPlan, 60, false); // this will start a new subscription at the end of the current one
```

**Note: The third parameter is `startFromNow`. If it is set to true, it will extend the current subscription. If not, a new subscription will be created. If set to true, it will return the current subscription, modified. If set to false, it will return the new subscription instance.**

Like `subscribeTo()`, you can also use dates:
```php
$user->upgradeCurrentPlanToUntil($anotherPlan, '2018-12-21', true);
$user->upgradeCurrentPlanToUntil($anotherPlan, '2018-12-21 16:54:11', true);
$user->upgradeCurrentPlanToUntil($anotherPlan, Carbon::create(2018, 12, 21, 16, 54, 11), true);
```

For convenience, if the user is not subscribed to any plan, it will be automatically subscribed using this method, either using dates or duration in days.

# Extending subscriptions
The upgrade method is inherited from extending method (this one), and what it does is actually extending the current subscription with a certain amount of days, either starting from now (extending the current subscription), or creating a new one that starts when the current one ends.
```php
$user->extendCurrentSubscriptionWith(60, true); // 60 days, starts now
```

Extending also works with dates:
```php
$user->extendCurrentSubscriptionUntil('2018-12-21', true);
$user->extendCurrentSubscriptionUntil('2018-12-21 16:54:11', true);
$user->extendCurrentSubscriptionUntil(Carbon::create(2018, 12, 21, 16, 54, 11), true);
```

# Cancelling subscriptions
You can cancel subscriptions. If a subscription is not finished yet (it is not expired), it will be marked as `pending cancellation`. It will be fully cancelled when the expiration dates passes the current time.
```php
$user->cancelCurrentSubscription(); // false if there is not an active subscription
```

# Checking status for subscriptions
You can check the status of a subscription using the following methods:
```php
$subscription->isCancelled(); // cancelled
$subscription->isPendingCancellation(); // cancelled, but did not expire yet
$subscription->isActive(); // if it started and has not expired
$subscription->hasStarted();
$subscription->hasExpired();
$subscription->remainingDays();
```

# Consuming features that has limits
To consume the `limit` type feature, you have to call the `consumeFeature()` method within a subscription instance.

To retrieve a subscription instance, you can call `activeSubscription()` method within the user that implements the trait. As a pre-check, don't forget to call `hasActiveSubscription()` from the user instance to make sure it is subscribed to it.

```php
if ($user->hasActiveSubscription()) {

    $subscription = $user->activeSubscription();
    $subscription->consumeFeature('build.minutes', 10); // consumed 10 minutes.

    $subscription->getUsageOf('build.minutes'); // 10
    $subscription->getRemainingOf('build.minutes'); // 1990

}
```

The `consumeFeature()` method will return:
* `false` if the feature does not exist, the feature is not a `limit` or the amount is exceeding the current feature allowance
* `true` if the consumption was done successfully

```php
// Note: The remaining of build.minutes is now 1990

$subscription->consumeFeature('build.minutes', 1991); // false
$subscription->consumeFeature('build.hours', 1); // false
$subscription->consumeFeature('build.minutes', 30); // true

$subscription->getUsageOf('build.minutes'); // 40
$subscription->getRemainingOf('build.minutes'); // 1960
```

If `consumeFeature()` meets an unlimited feature, it will consume it and it will also track usage just like a normal record in the database.

The remaining will always be `-1` for unlimited features.

The revering method for `consumeFeature()` method is `unconsumeFeature()`. This works just the same, but in the reverse:
```php
// Note: The remaining of build.minutes is 1960

$subscription->consumeFeature('build.minutes', 60); // true

$subscription->getUsageOf('build.minutes'); // 100
$subscription->getRemainingOf('build.minutes'); // 1900

$subscription->unconsumeFeature('build.minutes', 100); // true
$subscription->unconsumeFeature('build.hours', 1); // false

$subscription->getUsageOf('build.minutes'); // 0
$subscription->getRemainingOf('build.minutes'); // 2000
```

Using the `unconsumeFeature()` method on unlimited features will also reduce usage, but it will never reach negative values.

# Payments
By default, the app comes with a Stripe integration. You can keep using this package without Stripe if you already use your own method for payment. Be aware that there are some changes on the main migrations, so make sure you keep in touch with them.

To keep it classy over Laravel Cashier, you have to configure your `config/services.php` file by adding Stripe:
```php
'stripe' => [
    'key' => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
],
```

# Usage of Stripe
If you were familiar with subscribing, extending, upgrading or cancelling subscriptions without actively passing a payment method, there are some minor differences:
* Prices for plans can be fetched from your declared plans within the table or can be changed mid-process, so don't worry.
* Extending or Upgrading won't charge your users, only the subscribing actions will do this automatically. You want to charge your users from the moment their subscription starts, so you have to parse through all subscribers and check if their subscription expired and renew it automatically.
* You have to pass the Stripe token before the main actions (subscribe, extend, upgrade, cancelling). This package comes with a `stripe_customers` table, and will retrieve the local one, if it exists.
* Two new events were added to support the transactions' success & failure statuses.
* Each subscription keeps more data about the subscription, like the amount charged or days for recurrency.

To subscribe your users with a Stripe Token, do the following:
```php
$user->withStripe()->withStripeToken('tok_...')->subscribeTo($plan, 53);
$user->withStripe()->withStripeToken('tok_...')->subscribeToUntil($plan, Carbon::now()->addDays(90));
```

Both of them will subscribe the user to a plan. By default, the subscriptions are marked as recurrent.
For `subscribeTo()` and `subscribeToUntil()`, the third parameter is the recurrency, so in this case, the first one will be recurrent every 53 days while the second one, every 90 days.

If you do not want it to be recurrent (only for one-time subscription), pass `false` as third parameter.
```php
$user->withStripe()->withStripeToken('tok_...')->subscribeTo($plan, 53, false);
$user->withStripe()->withStripeToken('tok_...')->subscribeToUntil($plan, Carbon::now()->addDays(90), false);
```

If your plans already have prices set, you can change them mid-process, so you can have better control over the payment amounts:
```php
$user->withStripe()->withStropeToken('tok_...')->setChargingPriceTo(10, 'USD')->subscribeTo($plan, 30);
```

As you can see, the charging price will be $10, no matter what the plan's price is.

The same trick works with `extendCurrentSubscriptionWith()`, `extendCurrentSubscriptionUntil()`, `upgradeupgradeCurrentPlanTo()`, and `upgradeCurrentPlanToUntil()`.
**Please note that for these 4 methods, passing recurrency boolean is on the fourth parameter, not on third.**

Each time you are charging someone, it will check if there is a local Stripe Customer to retrieve the token and will use it if it exists. If your customer has a local token stored, you won't need to call `withStripetoken()` method anymore:
```php
$user->withStripe()->subscribeTo($plan, 30);
```

To update the Stripe Token of the user on-demand, you can use the `updateStripeToken()` method. Your user needs to have a valid Stripe Customer stored.
```php
$user->updateStripeToken('visa_...');
```

On-demand, you can also delete the stored Stripe Customer.
```php
$user->deleteStripeCustomer();
$user->isStripeCustomer(); // false
```

# Handling recurrencies
Since this package does not support built-in Stripe Plans due to the fact that anyone is free to set its recurrency time to any number of days, not just weekly, monthly or yearly, you have to parse your own subscribers and charge them.

You can simply call `canBeChargedForNewSubscription()` within the subscriber and then just try to charge for the last active subscription's data (since it's the last one the subscriber had) so we can renew it.

Using this check method, if the subscriber does not have an active subscription, it means it expired, so we can get the last active subscription data (if it was recurrent, of course), and we can create a new subscription:
```php
foreach(User:all() as $user) {
    if(!$user->canBeChargedForNewSubscription()) {
        continue;
    }
    
    $lastActiveSubscription = $user->lastActiveSubscription();
    $lastActiveSubscription->load(['plan']);
    
    $plan = $lastActiveSubscription->plan;
    $recurringEachDays = $lastActiveSubscription->recurring_each_days;
    $chargingPrice = $lastActiveSubscription->charging_price;
    $chargingCurrency = $lastActiveSubscription->charging_currency;

    $user->withStripe()->setChargingPriceTo($chargingPrice, $chargingCurrency)->subscribeTo($plan, $recurringEachDays);
}
```

# Events
When using subscription plans, you want to listen for events to automatically run code that might do changes for your app.

For example, if an user automatically extends its period before the subscription ends, you can give it free bonus days for loyality. Or you can check when a payment has failed or not.

Events are easy to use. If you are not familiar, you can check [Laravel's Official Documentation on Events](https://laravel.com/docs/5.6/events).

All you have to do is to implement the following Events in your `EventServiceProvider.php` file. Each event will have it's own members than can be accessed through the `$event` variable within the `handle()` method in your listener.

```php
$listen = [
    ...
    \Rennokki\Plans\Events\CancelSubscription::class => [
        // $event->model = The model that cancelled the subscription.
        // $event->subscription = The subscription that was cancelled.
    ],
    \Rennokki\Plans\Events\NewSubscription::class => [
        // $event->model = The model that was subscribed.
        // $event->subscription = The subscription that was created.
    ],
     \Rennokki\Plans\Events\NewSubscriptionUntil::class => [
        // $event->model = The model that was subscribed.
        // $event->subscription = The subscription that was created.
    ],
    \Rennokki\Plans\Events\ExtendSubscription::class => [
        // $event->model = The model that extended the subscription.
        // $event->subscription = The subscription that was extended.
        // $event->startFromNow = If the subscription is exteded now or is created a new subscription, in the future.
        // $event->newSubscription = If the startFromNow is false, here will be sent the new subscription that starts after the current one ends.
    ],
    \Rennokki\Plans\Events\ExtendSubscriptionUntil::class => [
        // $event->model = The model that extended the subscription.
        // $event->subscription = The subscription that was extended.
        // $event->expiresOn = The Carbon instance of the date when the subscription will expire.
        // $event->startFromNow = If the subscription is exteded now or is created a new subscription, in the future.
        // $event->newSubscription = If the startFromNow is false, here will be sent the new subscription that starts after the current one ends.
    ],
    \Rennokki\Plans\Events\UpgradeSubscription::class => [
        // $event->model = The model that upgraded the subscription.
        // $event->subscription = The current subscription.
        // $event->startFromNow = If the subscription is upgraded now or is created a new subscription, in the future.
        // $event->oldPlan = Here lies the current (which is now old) plan.
        // $event->newPlan = Here lies the new plan. If it's the same plan, it will match with the $event->oldPlan
    ],
    \Rennokki\Plans\Events\UpgradeSubscriptionUntil::class => [
        // $event->model = The model that upgraded the subscription.
        // $event->subscription = The current subscription.
        // $event->expiresOn = The Carbon instance of the date when the subscription will expire.
        // $event->startFromNow = If the subscription is upgraded now or is created a new subscription, in the future.
        // $event->oldPlan = Here lies the current (which is now old) plan.
        // $event->newPlan = Here lies the new plan. If it's the same plan, it will match with the $event->oldPlan
    ],
    \Rennokki\Plans\Events\FeatureConsumed::class => [
        // $event->subscription = The current subscription.
        // $event->feature = The feature that was used.
        // $event->used = The amount used.
        // $event->remaining = The total amount remaining. If the feature is unlimited, will return -1
    ],
     \Rennokki\Plans\Events\FeatureUnconsumed::class => [
        // $event->subscription = The current subscription.
        // $event->feature = The feature that was used.
        // $event->used = The amount reverted.
        // $event->remaining = The total amount remaining. If the feature is unlimited, will return -1
    ],
    \Rennokki\Plans\Events\Stripe\ChargeFailed::class => [
        // $event->model = The model for which the payment failed.
        // $event->subscription = The subscription.
        // $event->exception = The exception thrown by the Stripe API wrapper.
    ],
    \Rennokki\Plans\Events\Stripe\ChargeSuccessful::class => [
        // $event->model = The model for which the payment succeded.
        // $event->subscription = The subscription which was updated as paid.
        // $event->stripeCharge = The response coming from the Stripe API wrapper.
    ],
];
```
