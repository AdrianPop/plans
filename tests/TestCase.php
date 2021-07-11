<?php

declare(strict_types=1);

namespace Rennokki\Plans\Test;

use Orchestra\Testbench\TestCase as Orchestra;
use Rennokki\Plans\Models\PlanFeatureModel;
use Rennokki\Plans\Models\PlanModel;
use Rennokki\Plans\Models\PlanSubscriptionModel;
use Rennokki\Plans\Models\PlanSubscriptionUsageModel;
use Rennokki\Plans\Test\Models\User;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetDatabase();

        $this->loadLaravelMigrations(['--database' => 'sqlite']);
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->withFactories(__DIR__.'/../database/factories');

        $this->artisan('migrate', ['--database' => 'sqlite']);
    }

    protected function getPackageProviders($app)
    {
        return [
            \Rennokki\Plans\PlansServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => __DIR__.'/database.sqlite',
            'prefix' => '',
        ]);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('app.key', 'wslxrEFGWY6GfGhvN9L3wH3KSRJQQpBD');
        $app['config']->set('plans.models.plan', PlanModel::class);
        $app['config']->set('plans.models.feature', PlanFeatureModel::class);
        $app['config']->set('plans.models.subscription', PlanSubscriptionModel::class);
        $app['config']->set('plans.models.usage', PlanSubscriptionUsageModel::class);
    }

    protected function resetDatabase(): void
    {
        file_put_contents(__DIR__.'/database.sqlite', null);
    }
}
