<?php

namespace Rennokki\Plans\Events;

use Illuminate\Queue\SerializesModels;

class NewSubscriptionUntil
{
    use SerializesModels;

    public $subscription;
    public $expiresOn;

    public function __construct($subscription, $expiresOn)
    {
        $this->subscription = $subscription;
        $this->expiresOn = $expiresOn;
    }
}
