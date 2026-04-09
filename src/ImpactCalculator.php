<?php

namespace LaravelForecast;

use Illuminate\Support\Facades\DB;

class ImpactCalculator
{
    /** Fallback heuristics (ms per 1,000 rows) used when the Laravel config is unavailable. */
    private const DEFAULTS = [
        'drop_column'  => 10,
        'create_index' => 10,
        'alter_column' => 15,
        'add_column'   => 5,
    ];

    // ─── Row counts ───────────────────────────────────────────────────────────

    /**
     * Returns the current number of rows in a table, or null if the table
     * doesn't exist or cannot be queried (e.g. CREATE TABLE migrations).
     */
    public function getRowCount(string $table): ?int
    {
        if ($table === '') {
            return null;
        }

        try {
            return (int) DB::table($table)->count();
        } catch (\Throwable) {
            return null;
        }
    }

    // ─── Lock-time estimation ─────────────────────────────────────────────────

    /**
     * Returns a human-readable estimated lock duration for the given operation
     * and row count. Values are heuristic and MySQL-8-oriented.
     */
    public function estimateLockTime(string $operation, ?int $rowCount): string
    {
        if ($rowCount === null) {
            return '—';
        }

        return match (true) {
            // Metadata-only — essentially instant regardless of table size
            in_array($operation, ['DROP TABLE', 'CREATE TABLE', 'RENAME TABLE', 'RENAME COLUMN'], true)
                => 'instant',

            // MySQL 8+ INSTANT DDL; minimal lock even on huge tables
            $operation === 'ADD COLUMN'
                => $rowCount === 0
                    ? 'instant'
                    : '< 1s (online)',

            $operation === 'DROP COLUMN'
                => $this->timeFromRows($rowCount, $this->ms('drop_column')),

            $operation === 'CREATE INDEX'
                => $this->timeFromRows($rowCount, $this->ms('create_index')),

            in_array($operation, ['ALTER COLUMN', 'ADD NOT NULL', 'ALTER TABLE'], true)
                => $this->timeFromRows($rowCount, $this->ms('alter_column')),

            default => '—',
        };
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function timeFromRows(int $rowCount, int $msPerThousand): string
    {
        if ($rowCount === 0) {
            return 'instant';
        }

        $ms = ($rowCount / 1_000) * $msPerThousand;

        if ($ms < 1_000) {
            return '< 1s (estimated)';
        }

        if ($ms < 60_000) {
            return '~'.(int) round($ms / 1_000).'s (estimated)';
        }

        return '~'.(int) round($ms / 60_000).'m (estimated)';
    }

    private function ms(string $key): int
    {
        try {
            return (int) config('forecast.ms_per_thousand_rows.'.$key, self::DEFAULTS[$key]);
        } catch (\Throwable) {
            return self::DEFAULTS[$key];
        }
    }
}

