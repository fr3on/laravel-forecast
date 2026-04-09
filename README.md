# Laravel Forecast

[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/fr3on/laravel-forecast/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/fr3on/laravel-forecast/actions/workflows/tests.yml)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/fr3on/laravel-forecast/pint.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/fr3on/laravel-forecast/actions/workflows/pint.yml)
[![PHP Version](https://img.shields.io/badge/php-^8.2-777bb4.svg?style=flat-square&logo=php)](https://packagist.org/packages/fr3on/laravel-forecast)
[![Laravel Version](https://img.shields.io/badge/laravel-^10.0%20%7C%20^11.0%20%7C%20^12.0-ff2d20.svg?style=flat-square&logo=laravel)](https://packagist.org/packages/fr3on/laravel-forecast)
[![License](https://img.shields.io/badge/license-MIT-428f7e.svg?style=flat-square)](https://github.com/fr3on/laravel-forecast/blob/main/LICENSE)

> `php artisan migrate:forecast` — see exactly what your pending migrations will do, how many rows are at risk, and how long the table lock might last, **before** you type `migrate`.

---

## The problem

`migrate --pretend` gives you raw SQL. The confirmation prompt gives you nothing. Neither tells you:

- *"This ALTER TABLE touches a 12M-row table — expect ~8 minutes of locking"*
- *"This DROP COLUMN still has data in 4,200 rows"*
- *"This NOT NULL column has no DEFAULT — it will fail on existing rows"*

Teams find this out the hard way, mid-deploy, at 2am.

---

## Installation

```bash
composer require fr3on/laravel-forecast
```

The service provider is auto-discovered. No configuration is required to start.

Optionally publish the config:

```bash
php artisan vendor:publish --tag=forecast-config
```

---

## Usage

```bash
php artisan migrate:forecast
```

**Example output:**

```
  Laravel Forecast  |  3 pending migrations

  2024_08_01_add_status_to_orders
  ┌──────────────────────────────────────┬─────────┬──────────┬──────────────────┐
  │ Operation                            │ Risk    │ Rows     │ Est. lock        │
  ├──────────────────────────────────────┼─────────┼──────────┼──────────────────┤
  │ ADD COLUMN orders                    │ SAFE    │ 4.2M     │ < 1s (online)    │
  └──────────────────────────────────────┴─────────┴──────────┴──────────────────┘

  2024_08_02_drop_legacy_payments_table
  ┌──────────────────────────────────────┬─────────┬──────────┬──────────────────┐
  │ Operation                            │ Risk    │ Rows     │ Est. lock        │
  ├──────────────────────────────────────┼─────────┼──────────┼──────────────────┤
  │ DROP TABLE legacy_payments           │ DANGER  │ 38,441   │ instant          │
  └──────────────────────────────────────┴─────────┴──────────┴──────────────────┘
  ⚠  Entire table and all its data will be permanently deleted.
  ⚠  38,441 rows will be permanently deleted.

  2024_08_03_index_orders_user_id
  ┌──────────────────────────────────────┬─────────┬──────────┬──────────────────┐
  │ Operation                            │ Risk    │ Rows     │ Est. lock        │
  ├──────────────────────────────────────┼─────────┼──────────┼──────────────────┤
  │ CREATE INDEX orders                  │ CAUTION │ 4.2M     │ ~42s (estimated) │
  └──────────────────────────────────────┴─────────┴──────────┴──────────────────┘
  ℹ  May lock the table during index creation on older engines.
     Consider ALGORITHM=INPLACE, LOCK=NONE for zero-downtime.

  SAFE: 1   CAUTION: 1   DANGER: 1
  Run with --ci to exit code 1 on any DANGER operation.
```

---

### CI / CD integration

```bash
php artisan migrate:forecast --ci
# exits 1 if any DANGER operation is detected — blocks the pipeline automatically
```

---

## How it works

1. **Collects pending migrations** — reads the migrations repository to find which files haven't been run yet.
2. **Captures SQL without executing** — uses Laravel's built-in `connection->pretend()` to get the exact SQL each migration would run.
3. **Classifies each statement** — regex-based SQL analysis extracts the operation type and table name.
4. **Queries row counts** — runs `SELECT COUNT(*) FROM <table>` against your live database.
5. **Estimates lock time** — applies heuristics based on operation type and row count.

No AST parsing. No reflection hacks. Just SQL string analysis + a `COUNT` query.

---

## Risk classification

| Operation | Risk | Reason |
|---|---|---|
| `CREATE TABLE`, `ADD COLUMN` with `DEFAULT` or nullable | **SAFE** | Additive; online DDL on MySQL 8+ |
| `ADD COLUMN NOT NULL` without `DEFAULT` | **DANGER** | Will fail on non-empty tables |
| `DROP TABLE`, `DROP COLUMN` | **DANGER** | Irreversible data loss |
| `CREATE INDEX` | **CAUTION** | May lock table depending on engine version |
| `RENAME COLUMN` / `RENAME TABLE` | **CAUTION** | May break queries and views |
| `ALTER COLUMN` (type/constraint change) | **CAUTION** | Risk of data truncation |
| `NOT NULL` constraint on existing column | **DANGER** | Fails if any row contains NULL |

---

## Configuration

After publishing `config/forecast.php` you can tune:

```php
return [
    // Row count above which extra warnings are shown
    'large_table_threshold'  => env('FORECAST_LARGE_TABLE_THRESHOLD', 1_000_000),
    'medium_table_threshold' => env('FORECAST_MEDIUM_TABLE_THRESHOLD', 100_000),

    // Lock-time heuristics (milliseconds per 1,000 rows)
    'ms_per_thousand_rows' => [
        'drop_column'  => 10,
        'create_index' => 10,
        'alter_column' => 15,
        'add_column'   => 5,
    ],

    // Extra migration paths to scan (in addition to database/migrations)
    'migration_paths' => [],
];
```

---

## What it is NOT

- Not a replacement for `migrate` — it only informs before you commit.
- Not `migrate --pretend` — that shows SQL; this shows *impact*.
- Not `enlightn` — that checks code security; this checks schema risk.
- No SaaS, no agents, no database tables. Install and run immediately.

---

## License

MIT

