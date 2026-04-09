<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Large Table Thresholds
    |--------------------------------------------------------------------------
    |
    | Row count thresholds used when escalating risk levels. Operations on
    | tables larger than the "large" threshold will display extra warnings
    | in the output.
    |
    */

    'large_table_threshold' => env('FORECAST_LARGE_TABLE_THRESHOLD', 1_000_000),
    'medium_table_threshold' => env('FORECAST_MEDIUM_TABLE_THRESHOLD', 100_000),

    /*
    |--------------------------------------------------------------------------
    | Lock-Time Heuristics (ms per 1,000 rows)
    |--------------------------------------------------------------------------
    |
    | These values drive the estimated lock-time column. They are intentionally
    | conservative. Tune them to match your hardware and database engine.
    |
    */

    'ms_per_thousand_rows' => [
        'drop_column' => 10,   // ~10 ms per 1,000 rows
        'create_index' => 10,   // ~10 ms per 1,000 rows
        'alter_column' => 15,   // ~15 ms per 1,000 rows
        'add_column' => 5,    // ~5  ms per 1,000 rows (older engines without INSTANT DDL)
    ],

    /*
    |--------------------------------------------------------------------------
    | Additional Migration Paths
    |--------------------------------------------------------------------------
    |
    | By default, Forecast scans database/migrations plus any paths registered
    | via loadMigrationsFrom(). Add extra absolute paths here if needed.
    |
    */

    'migration_paths' => [],

];
