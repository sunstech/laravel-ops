<?php

namespace Sunstech\LaravelOps;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Sunstech\LaravelOps\Console\HeartbeatCommand;
use Sunstech\LaravelOps\Console\SummaryCommand;
use Sunstech\LaravelOps\Http\HealthController;
use Sunstech\LaravelOps\Http\SummaryController;

class OpsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ops.php', 'ops');
        $this->app->singleton(SystemHealth::class);
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/ops.php' => config_path('ops.php')], 'ops-config');

        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([HeartbeatCommand::class, SummaryCommand::class]);
        }

        // Zamanlayıcı nabzı: her dakika, ayrı süreç açmadan.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            if (config('ops.scheduler')) {
                $schedule->call(fn () => Heartbeat::record())->everyMinute()->name('ops:heartbeat');
            }
        });
    }

    private function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::prefix((string) config('ops.route_prefix'))->group(function () {
            Route::get(trim((string) config('ops.summary_path'), '/'), SummaryController::class)->name('ops.summary');
            if (config('ops.health_route')) {
                Route::get('health', HealthController::class)->name('ops.health');
            }
        });
    }
}
