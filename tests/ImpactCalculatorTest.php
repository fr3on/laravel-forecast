<?php

use Fr3on\Forecast\ImpactCalculator;

beforeEach(function () {
    $this->calc = new ImpactCalculator;
});

it('returns instant for DROP TABLE', function () {
    expect($this->calc->estimateLockTime('DROP TABLE', 1_000_000))->toBe('instant');
});

it('returns instant for CREATE TABLE', function () {
    expect($this->calc->estimateLockTime('CREATE TABLE', 500_000))->toBe('instant');
});

it('returns instant for RENAME TABLE', function () {
    expect($this->calc->estimateLockTime('RENAME TABLE', 2_000_000))->toBe('instant');
});

it('returns online for ADD COLUMN on non-empty table', function () {
    expect($this->calc->estimateLockTime('ADD COLUMN', 5_000_000))->toBe('< 1s (online)');
});

it('returns instant for ADD COLUMN on empty table', function () {
    expect($this->calc->estimateLockTime('ADD COLUMN', 0))->toBe('instant');
});

it('returns dash when row count is null', function () {
    expect($this->calc->estimateLockTime('DROP COLUMN', null))->toBe('—');
});

it('returns sub-second estimate for small DROP COLUMN', function () {
    // 5,000 rows × 10ms/1000 = 50ms → < 1s
    expect($this->calc->estimateLockTime('DROP COLUMN', 5_000))->toBe('< 1s (estimated)');
});

it('returns seconds estimate for medium CREATE INDEX', function () {
    // 500,000 rows × 10ms/1000 = 5,000ms = 5s
    expect($this->calc->estimateLockTime('CREATE INDEX', 500_000))->toBe('~5s (estimated)');
});

it('returns minutes estimate for large ALTER COLUMN', function () {
    // 10,000,000 rows × 15ms/1000 = 150,000ms = 2.5m → ~3m
    expect($this->calc->estimateLockTime('ALTER COLUMN', 10_000_000))->toBe('~3m (estimated)');
});
