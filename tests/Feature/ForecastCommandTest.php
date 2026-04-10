<?php

use Fr3on\Forecast\MigrationRunner;
use Illuminate\Support\Facades\Artisan;

it('runs the forecast command comfortably', function () {
    $this->mock(MigrationRunner::class, function ($mock) {
        $mock->shouldReceive('getPendingMigrations')->once()->andReturn([
            '2024_01_01_000000_create_users_table' => 'database/migrations/2024_01_01_000000_create_users_table.php',
        ]);
        $mock->shouldReceive('getSqlForMigration')->once()->andReturn([
            ['query' => 'create table "users" ("id" integer primary key autoincrement not null)', 'bindings' => [], 'time' => 0.01],
        ]);
    });

    $exitCode = Artisan::call('migrate:forecast');

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('Laravel Forecast');
    expect(Artisan::output())->toContain('SAFE');
});

it('exists with code 1 in CI when danger is detected', function () {
    $this->mock(MigrationRunner::class, function ($mock) {
        $mock->shouldReceive('getPendingMigrations')->once()->andReturn([
            '2024_01_01_000000_drop_users_table' => 'database/migrations/2024_01_01_000000_drop_users_table.php',
        ]);
        $mock->shouldReceive('getSqlForMigration')->once()->andReturn([
            ['query' => 'drop table "users"', 'bindings' => [], 'time' => 0.01],
        ]);
    });

    $exitCode = Artisan::call('migrate:forecast', ['--ci' => true]);

    expect($exitCode)->toBe(1);
    expect(Artisan::output())->toContain('DANGER');
    expect(Artisan::output())->toContain('Exiting with code 1');
});
