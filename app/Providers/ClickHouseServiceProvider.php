<?php

namespace App\Providers;

use ClickHouseDB;
use Illuminate\Support\ServiceProvider;

class ClickHouseServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('App\Service\ClickHouseServiceInterface', 'App\Service\ClickHouseService');
    }
}
