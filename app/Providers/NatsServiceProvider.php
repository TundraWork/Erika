<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class NatsServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('App\Service\NatsServiceInterface', 'App\Service\NatsService');
    }
}
