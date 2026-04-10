<?php

use Fr3on\Forecast\MigrationRunner;

it('runs the forecast command comfortably', function () {
    $this->mock(MigrationRunner::class, function ($mock) {
        $mock->shouldReceive('getPendingMigrations')->andReturn([
            '2024_01_01_000000_create_users_table' => 'database/migrations/2024_01_01_000000_create_users_table.php',
        ]);
        $mock->shouldReceive('getSqlForMigration')->andReturn([
            ['query' => 'create table "users" ("id" integer primary key autoincrement not null)', 'bindings' => [], 'time' => 0.01],
        ]);
    });

    $this->artisan('migrate:forecast')
        ->expectsOutputToContain('Laravel Forecast')
        ->expectsOutputToContain('SAFE')
        ->assertExitCode(0);
});

it('exists with code 1 in CI when danger is detected', function () {
    $this->mock(MigrationRunner::class, function ($mock) {
        $mock->shouldReceive('getPendingMigrations')->andReturn([
            '2024_01_01_000000_drop_users_table' => 'database/migrations/2024_01_01_000000_drop_users_table.php',
        ]);
        $mock->shouldReceive('getSqlForMigration')->andReturn([
            ['query' => 'drop table users', 'bindings' => [], 'time' => 0.01],
        ]);
    });

    $this->artisan('migrate:forecast', ['--ci' => true])
        ->expectsOutputToContain('DANGER')
        ->assertExitCode(1);
});
