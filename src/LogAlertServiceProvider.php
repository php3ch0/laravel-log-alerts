<?php

namespace Seadragon\LaravelLogAlerts;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Seadragon\LaravelLogAlerts\Console\Commands\EmailCriticalLogSummary;

class LogAlertServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/log-alerts.php', 'log-alerts');
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands(array(
                EmailCriticalLogSummary::class,
            ));
        }

        $this->publishes(array(
            __DIR__.'/../config/log-alerts.php' => config_path('log-alerts.php'),
        ), 'log-alerts-config');

        $this->app->afterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('log-alerts:email')->dailyAt('08:00')->timezone('UTC');
        });
    }
}
