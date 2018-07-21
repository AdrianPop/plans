<?php

namespace Rennokki\Plans\Events;

use Illuminate\Queue\SerializesModels;

class NewSubscription
{
    use SerializesModels;

    public $model;
    public $subscription;
    public $duration;

    public function __construct($model, $subscription, $duration)
    {
        $this->model = $model;
        $this->subscription = $subscription;
        $this->duration = $duration;
    }
}
