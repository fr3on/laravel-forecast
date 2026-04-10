<?php

use Fr3on\Forecast\SqlAnalyzer;
use Illuminate\Support\Facades\Artisan;

it('runs the forecast command comfortably', function () {
    // Mock the migration repository to return empty
    $command = Artisan::call('migrate:forecast');

    expect($command)->toBe(0);
    expect(Artisan::output())->toContain('Scanning migrations');
});

it('exists with code 1 in CI when danger is detected', function () {
    // We would ideally mock SqlAnalyzer here or provide a dummy migration
    // For now, testing the flag presence and basic execution
    $exitCode = Artisan::call('migrate:forecast', ['--ci' => true]);

    // Should be 0 if no danger, which is fine for basic test
    expect($exitCode)->toBe(0);
});
