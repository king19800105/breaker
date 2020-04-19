<?php
namespace Anthony\Breaker\Providers;

use Anthony\Breaker\Core\ICircuitBreaker;
use Anthony\Breaker\Core\APCuCircuitBreaker;
use Anthony\Breaker\Core\RedisCircuitBreaker;
use Illuminate\Support\ServiceProvider;


class BreakerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../../config/breaker.php' => config_path('breaker.php')
        ]);
        $this->mergeConfigFrom(__DIR__ . '/../../config/breaker.php', 'breaker');
        $this->loadTranslationsFrom(__DIR__ . '/../../lang', 'breaker');
    }

    public function register()
    {
        $this->app->singleton(
            ICircuitBreaker::class,
            'apcu' === config('breaker.drive')
                ? APCuCircuitBreaker::class
                : RedisCircuitBreaker::class
        );
    }
}
