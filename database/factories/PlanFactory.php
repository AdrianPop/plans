<?php

declare(strict_types=1);
/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| This directory should contain each of the model factory definitions for
| your application. Factories provide a convenient way to generate new
| model instances for testing / seeding your application's database.
|
*/

$factory->define(\Rennokki\Plans\Models\PlanModel::class, function ($faker, $attributes = []) {
    return [
        'name' => 'Testing Plan '.\Illuminate\Support\Str::random(7),
        'tag' => $attributes['tag'] ?? 'default',
        'code' => $attributes['code'] ?? 'free',
        'description' => 'This is a testing plan.',
        'price' => (float) random_int(10, 200),
        'currency' => 'EUR',
        'duration' => 30,
    ];
});
