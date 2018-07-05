<?php

namespace Rennokki\Plans\Events;

use Illuminate\Queue\SerializesModels;

class UpgradeSubscriptionUntil
{
    use SerializesModels;

    public $subscription;
    public $expiresOn;
    public $startFromNow;
    public $oldPlan;
    public $newPlan;

    public function __construct($subscription, $expiresOn, $startFromNow, $oldPlan, $newPlan)
    {
        $this->subscription = $subscription;
        $this->expiresOn = $expiresOn;
        $this->startFromNow = $startFromNow;
        $this->oldPlan = $oldPlan;
        $this->newPlan = $newPlan;
    }
}
