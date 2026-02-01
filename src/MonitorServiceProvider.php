<?php

namespace BinaryHype\Monitoring;

use BinaryHype\Monitoring\Commands\TestCommand;
use BinaryHype\Monitoring\Handlers\ExceptionHandler;
use BinaryHype\Monitoring\Handlers\LogHandler;
use BinaryHype\Monitoring\Http\Controllers\HeartbeatController;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MonitorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/monitor.php', 'monitor');

        $this->app->singleton('monitor', function ($app) {
            return new Monitor($app['config']->get('monitor', []));
        });

        $this->app->alias('monitor', Monitor::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/monitor.php' => config_path('monitor.php'),
        ], 'monitor-config');

        $this->registerExceptionHandler();
        $this->registerLogChannel();
        $this->registerRoutes();
        $this->registerCommands();
    }

    protected function registerExceptionHandler(): void
    {
        if (! $this->app['config']->get('monitor.enabled', true)) {
            return;
        }

        $this->app->extend(ExceptionHandlerContract::class, function ($handler, $app) {
            return new ExceptionHandler($handler, $app->make('monitor'));
        });
    }

    protected function registerLogChannel(): void
    {
        $this->app->afterResolving(LogManager::class, function (LogManager $logManager) {
            $logManager->extend('monitor', function ($app, array $config) {
                $handler = new LogHandler(
                    $app->make('monitor'),
                    $config['level'] ?? 'error'
                );

                return new \Monolog\Logger('monitor', [$handler]);
            });
        });
    }

    protected function registerRoutes(): void
    {
        if (! $this->app['config']->get('monitor.heartbeat.enabled', true)) {
            return;
        }

        $route = $this->app['config']->get('monitor.heartbeat.route', '/_monitor/health');
        $middleware = $this->app['config']->get('monitor.heartbeat.middleware', []);

        Route::middleware($middleware)
            ->get($route, HeartbeatController::class)
            ->name('monitor.health');
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                TestCommand::class,
            ]);
        }
    }

    public function provides(): array
    {
        return [
            'monitor',
            Monitor::class,
        ];
    }
}
