<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator; // <-- Phải là Illuminate\Pagination

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    protected $policies = [
    \App\Models\Building::class => \App\Policies\BuildingPolicy::class,
];

}
