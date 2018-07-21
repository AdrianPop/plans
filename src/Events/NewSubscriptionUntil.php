<?php

namespace Rennokki\Plans\Events;

use Illuminate\Queue\SerializesModels;

class NewSubscriptionUntil
{
    use SerializesModels;

    public $model;
    public $subscription;
    public $expiresOn;

    public function __construct($model, $subscription, $expiresOn)
    {
        $this->model = $model;
        $this->subscription = $subscription;
        $this->expiresOn = $expiresOn;
    }
}
