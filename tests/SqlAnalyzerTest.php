<?php

use LaravelForecast\SqlAnalyzer;

beforeEach(function () {
    $this->analyzer = new SqlAnalyzer;
});

// ─── CREATE TABLE ─────────────────────────────────────────────────────────────

it('classifies CREATE TABLE as SAFE', function () {
    $result = $this->analyzer->analyze('create table `orders` (`id` bigint unsigned not null)');

    expect($result['risk'])->toBe('SAFE')
        ->and($result['operation'])->toBe('CREATE TABLE')
        ->and($result['table'])->toBe('orders');
});

// ─── DROP TABLE ───────────────────────────────────────────────────────────────

it('classifies DROP TABLE as DANGER', function () {
    $result = $this->analyzer->analyze('drop table `legacy_payments`');

    expect($result['risk'])->toBe('DANGER')
        ->and($result['operation'])->toBe('DROP TABLE')
        ->and($result['table'])->toBe('legacy_payments');
});

it('classifies DROP TABLE IF EXISTS as DANGER', function () {
    $result = $this->analyzer->analyze('drop table if exists `old_logs`');

    expect($result['risk'])->toBe('DANGER')
        ->and($result['table'])->toBe('old_logs');
});

// ─── ADD COLUMN ───────────────────────────────────────────────────────────────

it('classifies ADD COLUMN with DEFAULT as SAFE', function () {
    $result = $this->analyzer->analyze(
        "alter table `orders` add `status` varchar(255) not null default 'pending'"
    );

    expect($result['risk'])->toBe('SAFE')
        ->and($result['operation'])->toBe('ADD COLUMN')
        ->and($result['table'])->toBe('orders');
});

it('classifies ADD COLUMN nullable as SAFE', function () {
    $result = $this->analyzer->analyze(
        'alter table `orders` add `notes` text null'
    );

    expect($result['risk'])->toBe('SAFE')
        ->and($result['operation'])->toBe('ADD COLUMN');
});

it('classifies ADD COLUMN NOT NULL without DEFAULT as DANGER', function () {
    $result = $this->analyzer->analyze(
        'alter table `orders` add `required_field` varchar(255) not null'
    );

    expect($result['risk'])->toBe('DANGER')
        ->and($result['operation'])->toBe('ADD COLUMN');
});

// ─── DROP COLUMN ──────────────────────────────────────────────────────────────

it('classifies DROP COLUMN as DANGER', function () {
    $result = $this->analyzer->analyze(
        'alter table `users` drop column `legacy_token`'
    );

    expect($result['risk'])->toBe('DANGER')
        ->and($result['operation'])->toBe('DROP COLUMN')
        ->and($result['table'])->toBe('users');
});

// ─── CREATE INDEX ─────────────────────────────────────────────────────────────

it('classifies CREATE INDEX as CAUTION', function () {
    $result = $this->analyzer->analyze(
        'create index `idx_orders_user_id` on `orders` (`user_id`)'
    );

    expect($result['risk'])->toBe('CAUTION')
        ->and($result['operation'])->toBe('CREATE INDEX')
        ->and($result['table'])->toBe('orders');
});

it('classifies CREATE UNIQUE INDEX as CAUTION', function () {
    $result = $this->analyzer->analyze(
        'create unique index `uq_users_email` on `users` (`email`)'
    );

    expect($result['risk'])->toBe('CAUTION')
        ->and($result['operation'])->toBe('CREATE INDEX');
});

// ─── RENAME ───────────────────────────────────────────────────────────────────

it('classifies RENAME TABLE as CAUTION', function () {
    $result = $this->analyzer->analyze(
        'alter table `old_name` rename to `new_name`'
    );

    expect($result['risk'])->toBe('CAUTION')
        ->and($result['operation'])->toBe('RENAME TABLE');
});

it('classifies RENAME COLUMN as CAUTION', function () {
    $result = $this->analyzer->analyze(
        'alter table `users` rename column `name` to `full_name`'
    );

    expect($result['risk'])->toBe('CAUTION')
        ->and($result['operation'])->toBe('RENAME COLUMN');
});

// ─── ALTER COLUMN ─────────────────────────────────────────────────────────────

it('classifies MODIFY COLUMN NOT NULL as DANGER (may fail if NULLs exist)', function () {
    // MODIFY … NOT NULL can fail on non-empty tables if any row has NULL.
    $result = $this->analyzer->analyze(
        'alter table `products` modify `price` decimal(10,4) not null'
    );

    expect($result['risk'])->toBe('DANGER')
        ->and($result['operation'])->toBe('ADD NOT NULL');
});

it('classifies MODIFY COLUMN without NOT NULL as CAUTION', function () {
    $result = $this->analyzer->analyze(
        'alter table `products` modify `price` decimal(10,4) null'
    );

    expect($result['risk'])->toBe('CAUTION')
        ->and($result['operation'])->toBe('ALTER COLUMN');
});

// ─── Table name extraction ────────────────────────────────────────────────────

it('extracts table names with double-quote delimiters (PostgreSQL)', function () {
    $result = $this->analyzer->analyze('drop table "legacy_payments"');

    expect($result['table'])->toBe('legacy_payments');
});

it('extracts table names without any delimiter', function () {
    $result = $this->analyzer->analyze('drop table legacy_payments');

    expect($result['table'])->toBe('legacy_payments');
});
