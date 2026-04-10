<?php
 
namespace Fr3on\Forecast\Tests;
 
use Fr3on\Forecast\ForecastServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
 
class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            ForecastServiceProvider::class,
        ];
    }
 
    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
    }
}
