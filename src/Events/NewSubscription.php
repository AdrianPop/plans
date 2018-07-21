<?php

namespace Rennokki\Plans\Events;

use Illuminate\Queue\SerializesModels;

class NewSubscription
{
    use SerializesModels;

    public $model;
    public $subscription;
    public $duration;

    /**
     * @param Model $model The model that subscribed.
     * @param SubscriptionModel $subscription Subscription the model has subscribed to.
     * @param int $duration The duration, in days, of the subscription extension.
     * @return void
     */
    public function __construct($model, $subscription, $duration)
    {
        $this->model = $model;
        $this->subscription = $subscription;
        $this->duration = $duration;
    }
}
