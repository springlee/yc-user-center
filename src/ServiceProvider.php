<?php

namespace Yc\UserCenter;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    protected $defer = true;

    public function register()
    {
        $this->app->singleton(Authorization::class, function () {
            return new Authorization(config('yc_user_center'));
        });

        $this->app->alias(Authorization::class, 'authorization');
    }

    public function provides()
    {
        return [Authorization::class, 'authorization'];
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/yc_user_center.php' => config_path('yc_user_center.php'),
        ], 'config');
    }
}
