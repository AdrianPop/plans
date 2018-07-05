<?php

namespace Rennokki\Plans\Events;

use Illuminate\Queue\SerializesModels;

class ExtendSubscriptionUntil
{
    use SerializesModels;

    public $subscription;
    public $expiresOn;
    public $startFromNow;
    public $newSubscription;

    public function __construct($subscription, $expiresOn, $startFromNow, $newSubscription)
    {
        $this->subscription = $subscription;
        $this->expiresOn = $expiresOn;
        $this->startFromNow = $startFromNow;
        $this->newSubscription = $newSubscription;
    }
}
