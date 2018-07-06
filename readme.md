[![Build Status](https://travis-ci.org/rennokki/plans.svg?branch=master)](https://travis-ci.org/rennokki/plans)
[![codecov](https://codecov.io/gh/rennokki/plans/branch/master/graph/badge.svg)](https://codecov.io/gh/rennokki/plans/branch/master)
[![StyleCI](https://github.styleci.io/repos/138162161/shield?branch=master)](https://github.styleci.io/repos/138162161)
[![Latest Stable Version](https://poser.pugx.org/rennokki/plans/v/stable)](https://packagist.org/packages/rennokki/plans)
[![Total Downloads](https://poser.pugx.org/rennokki/plans/downloads)](https://packagist.org/packages/rennokki/plans)
[![Monthly Downloads](https://poser.pugx.org/rennokki/plans/d/monthly)](https://packagist.org/packages/rennokki/plans)
[![License](https://poser.pugx.org/rennokki/plans/license)](https://packagist.org/packages/rennokki/plans)

[![PayPal](https://img.shields.io/badge/PayPal-donate-blue.svg)](https://paypal.me/rennokki)

# Laravel Plans
Laravel Plans is a package for SaaS-like apps that need easy management over plans, features and event-driven updates on plans. If you plan selling your service with subscription, you're in the right place!

# Why would you use that while there's Laravel Cashier?
Cashier is a great subscription-like feature for your Laravel app. However, if you don't encounter problems while using an app, you don't know what to improve to it.

Plans include features. A lot. Some of them are just permissions you can give to your users, some are just amounts of limits for that period of time. Think about permissions to use a certain feature, or a maximum amount of SMS you can give to your users to use per month. Cashier doesn't implement that. With Laravel Plans, yOu can track the usage and the remaining amounts for limited features, for example. Or implement your unlimited features that never expire throughout the subscription period.

###  However, Laravel Plans doesn't include payment methods. But it's planned for v1.5.0. Stay tuned!

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
    'duration' => 30, // in days; doesn't have an utility yet; it is stored as information.
]);
```

# Plan features
Each plan has features. They can be either limited, unlimited, or there just to store the information of a certain permission.

Marking a feature type can be done using:
* `feature`, is a single string, that do not needs counting. For example, you can store permissions.
* `limit`, is a number. For this kind of feature, the `limit` field will be filled. It is meant to measure how many of that feature the user has consumed, from this subscription. For example, how many build minutes has consumed during the month (or during the Cycle, which is 30 days in this example)

## Note: For unlimited feature, the `limit` field will be set to any negative value.

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
```php
$susbscription = $user->subscribeTo($plan, 30);

$subscription->remainingDays(); // 29; this is because it is 29 days, 23 hours, and so on.
```

If you plan to subscribe your users until a certain date, you can do so with dates.
```php
$user->subscribeToUntil($plan, '2018-12-21');
$user->subscribeToUntil($plan, '2018-12-21 16:54:11');
$user->subscribeToUntil($plan, Carbon::create(2018, 12, 21, 16, 54, 11));
```

## Note: If the user is already subscribed, the `subscribeTo()` will return false. To avoid this, use `upgradeTo()`, `upgradeToUntil()`, `extendWith()` or `extendWithUntil()` methods to either upgrade or extend the subscription period with a certain amount of days or until a certain date.

# Upgrading to other plans
```php
$user->upgradeTo($anotherPlan, 60, true); // this will extend the current subscription with 60 days
$user->upgradeTo($anotherPlan, 60, false); // this will start a new subscription at the end of the current one
```

## Note: The third parameter is `startFromNow`. If it is set to true, it will extend the current subscription. If not, a new subscription will be created. If set to true, it will return the current subscription, modified. If set to false, it will return the new subscription instance.

Like `subscribeTo()`, you can also use dates:
```php
$user->upgradeToUntil($anotherPlan, '2018-12-21', true);
$user->upgradeToUntil($anotherPlan, '2018-12-21 16:54:11', true);
$user->upgradeToUntil($anotherPlan, Carbon::create(2018, 12, 21, 16, 54, 11), true);
```

For convenience, if the user is not subscribed to any plan, it will be automatically subscribed using this method, either using dates or duration in days.

# Extending subscriptions
The upgrade method is inherited from extending method (this one), and what it does is actually extending the current subscription with a certain amount of days, either starting from now (extending the current subscription), or creating a new one that starts when the current one ends.
```php
$user->extendCurrentSubscriptionWith(60, true); // 60 days, starts now
$user->activeSubscription()->extendWith(60, true); // you can also use this
```

Extending also works with dates:
```php
$user->extendCurrentSubscriptionUntil('2018-12-21', true);
$user->extendCurrentSubscriptionUntil('2018-12-21 16:54:11', true);
$user->extendCurrentSubscriptionUntil(Carbon::create(2018, 12, 21, 16, 54, 11), true);

// As an alias, it can also be called within Subscription instance.
$subscription = $user->activeSubscription();
$subscription->extendUntil('2018-12-21', true); // you can also use this
```

# Cancelling subscriptions
You can cancel subscriptions. If a subscription is not finished yet (it is not expired), it will be marked as `pending cancellation`. It will be fully cancelled when the expiration dates passes the current time.
```php
$user->cancelCurrentSubscription(); // false if there is not active subscription

// Same thing.
$subscription = $user->activeSubscription();
$subscription->cancel();
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

# Events
When using subscription plans, you want to listen for events to automatically run code that might do changes for your app.

For example, if an user automatically extends its period before the subscription ends, you can give it free bonus days for loyality.

Events are easy to use. If you are not familiar, you can check [Laravel's Official Documentation on Events](https://laravel.com/docs/5.6/events).

All you have to do is to implement the following Events in your `EventServiceProvider.php` file. Each event will have it's own members than can be accessed through the `$event` variable within the `handle()` method in your listener.

```php
$listen = [
    ...
    \Rennokki\Plans\Events\CancelSubscription::class => [
        // $event->subscription = The subscription that was cancelled.
    ],
    \Rennokki\Plans\Events\NewSubscription::class => [
        // $event->subscription = The subscription that was created.
        // $event->duration = The duration, in days, of the subscription.
    ],
     \Rennokki\Plans\Events\NewSubscriptionUntil::class => [
        // $event->subscription = The subscription that was created.
        // $event->expiresOn = The Carbon instance when the subscription will expire.
    ],
    \Rennokki\Plans\Events\ExtendSubscription::class => [
        // $event->subscription = The subscription that was extended.
        // $event->duration = The duration, in days, of the subscription.
        // $event->startFromNow = If the subscription is exteded now or is created a new subscription, in the future.
        // $event->newSubscription = If the startFromNow is false, here will be sent the new subscription that starts after the current one ends.
    ],
    \Rennokki\Plans\Events\ExtendSubscriptionUntil::class => [
        // $event->subscription = The subscription that was extended.
        // $event->expiresOn = The Carbon instance of the date when the subscription will expire.
        // $event->startFromNow = If the subscription is exteded now or is created a new subscription, in the future.
        // $event->newSubscription = If the startFromNow is false, here will be sent the new subscription that starts after the current one ends.
    ],
    \Rennokki\Plans\Events\UpgradeSubscription::class => [
        // $event->subscription = The current subscription.
        // $event->duration = The duration, in days, of the upgraded subscription.
        // $event->startFromNow = If the subscription is upgraded now or is created a new subscription, in the future.
        // $event->oldPlan = Here lies the current (which is now old) plan.
        // $event->newPlan = Here lies the new plan. If it's the same plan, it will match with the $event->oldPlan
    ],
    \Rennokki\Plans\Events\UpgradeSubscriptionUntil::class => [
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
];
```
