<?php

declare(strict_types=1);

namespace Rennokki\Plans\Test;

use Carbon\Carbon;
use Rennokki\Plans\Test\Models\User;

class RecurrencyTest extends TestCase
{
    protected User $user;
    protected $plan;
    protected $newPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = factory(\Rennokki\Plans\Test\Models\User::class)->create();
        $this->plan = factory(\Rennokki\Plans\Models\PlanModel::class)->create();
        $this->newPlan = factory(\Rennokki\Plans\Models\PlanModel::class)->create();
    }

    public function testRecurrency(): void
    {
        $this->user->subscribeToUntil($this->plan, Carbon::now()->addDays(7));

        $this->user->currentSubscription()->update([
            'starts_on' => Carbon::now()->subDays(7),
            'expires_on' => Carbon::now(),
        ]);

        $this->assertFalse($this->user->hasActiveSubscription());
        $this->assertEquals($this->user->subscriptions()->count(), 1);

        $this->assertNotNull($this->user->renewSubscription());
        $this->assertTrue($this->user->hasActiveSubscription());
        $this->assertEquals($this->user->subscriptions()->count(), 2);
    }
}
