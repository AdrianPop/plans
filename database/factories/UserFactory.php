<?php
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

$factory->define(\Rennokki\Plans\Test\Models\User::class, function () {
    return [
        'name' => 'Name'.\Illuminate\Support\Str::random(5),
        'email' => \Illuminate\Support\Str::random(5).'@gmail.com',
        'password' => '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', // secret
        'remember_token' => \Illuminate\Support\Str::random(10),
    ];
});
