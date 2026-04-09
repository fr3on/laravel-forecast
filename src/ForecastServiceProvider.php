<?php

namespace LaravelForecast;

use Illuminate\Support\ServiceProvider;
use LaravelForecast\Commands\ForecastCommand;

class ForecastServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/forecast.php', 'forecast');

        $this->app->bind(MigrationRunner::class, function ($app) {
            return new MigrationRunner(
                $app['migrator'],
                $app['files'],
            );
        });

        $this->app->bind(SqlAnalyzer::class);
        $this->app->bind(ImpactCalculator::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ForecastCommand::class]);

            $this->publishes([
                __DIR__.'/../config/forecast.php' => config_path('forecast.php'),
            ], 'forecast-config');
        }
    }
}
