<?php

namespace LaravelForecast\Commands;

use Illuminate\Console\Command;
use LaravelForecast\ImpactCalculator;
use LaravelForecast\MigrationRunner;
use LaravelForecast\SqlAnalyzer;

class ForecastCommand extends Command
{
    protected $signature = 'migrate:forecast
                            {--ci : Exit with code 1 if any DANGER operations are found (useful in CI/CD pipelines)}';

    protected $description = 'Preview pending migration impact — row counts, lock estimates, and risk ratings — before you migrate';

    public function handle(
        MigrationRunner $runner,
        SqlAnalyzer $analyzer,
        ImpactCalculator $calculator,
    ): int {
        $pending = $runner->getPendingMigrations();

        if (empty($pending)) {
            $this->components->info('Nothing to migrate.');

            return self::SUCCESS;
        }

        $count = count($pending);

        $this->newLine();
        $this->line(
            '  <fg=blue;options=bold>Laravel Forecast</>  |  <options=bold>'.
            $count.' pending '.($count === 1 ? 'migration' : 'migrations').'</>',
        );
        $this->newLine();

        $totals = ['SAFE' => 0, 'CAUTION' => 0, 'DANGER' => 0];
        $hasDanger = false;

        foreach ($pending as $migrationName => $migrationPath) {
            $this->line("  <options=bold>{$migrationName}</>");

            $queries = $runner->getSqlForMigration($migrationName, $migrationPath);

            if (empty($queries)) {
                $this->line('  <fg=gray>  (no SQL captured — skipping)</>');
                $this->newLine();

                continue;
            }

            $rows = [];
            $notices = [];

            foreach ($queries as $queryData) {
                $result = $analyzer->analyze($queryData['query']);

                // Don't query row counts for operations that create new tables
                $rowCount = ($result['operation'] === 'CREATE TABLE')
                    ? 0
                    : $calculator->getRowCount($result['table']);

                $lockTime = $calculator->estimateLockTime($result['operation'], $rowCount);

                $totals[$result['risk']] = ($totals[$result['risk']] ?? 0) + 1;

                if ($result['risk'] === 'DANGER') {
                    $hasDanger = true;
                }

                $opLabel = $result['table'] !== ''
                    ? $result['operation'].' '.$result['table']
                    : $result['operation'];

                $rows[] = [
                    $this->truncate($opLabel, 38),
                    $this->colorizeRisk($result['risk']),
                    $this->formatRowCount($rowCount),
                    $lockTime,
                ];

                // Collect notices shown below the table
                if ($result['risk'] === 'DANGER') {
                    $notices[] = ['type' => 'danger', 'text' => $result['reason']];

                    if ($rowCount !== null && $rowCount > 0 && in_array($result['operation'], ['DROP TABLE', 'DROP COLUMN'], true)) {
                        $notices[] = ['type' => 'danger', 'text' => number_format($rowCount).' rows will be permanently deleted.'];
                    }

                    if ($result['advice']) {
                        $notices[] = ['type' => 'hint', 'text' => $result['advice']];
                    }
                } elseif ($result['risk'] === 'CAUTION') {
                    $notices[] = ['type' => 'caution', 'text' => $result['reason']];

                    if ($result['advice']) {
                        $notices[] = ['type' => 'hint', 'text' => $result['advice']];
                    }
                }

                // Extra warning when touching a very large table
                $largeThreshold = (int) config('forecast.large_table_threshold', 1_000_000);
                if ($rowCount !== null && $rowCount >= $largeThreshold && $result['risk'] !== 'DANGER') {
                    $notices[] = [
                        'type' => 'caution',
                        'text' => 'Table has '.number_format($rowCount).' rows — lock time may be significant.',
                    ];
                }
            }

            $this->table(
                ['Operation', 'Risk', 'Rows', 'Est. lock'],
                $rows,
                'box',
            );

            foreach ($notices as $notice) {
                match ($notice['type']) {
                    'danger' => $this->line("  <fg=red>⚠  {$notice['text']}</>"),
                    'caution' => $this->line("  <fg=yellow>ℹ  {$notice['text']}</>"),
                    'hint' => $this->line("  <fg=gray>   {$notice['text']}</>"),
                    default => null,
                };
            }

            $this->newLine();
        }

        // ─── Summary ──────────────────────────────────────────────────────────
        $safe = $totals['SAFE'] ?? 0;
        $caution = $totals['CAUTION'] ?? 0;
        $danger = $totals['DANGER'] ?? 0;

        $this->line(
            "  <fg=green>SAFE: {$safe}</>   <fg=yellow>CAUTION: {$caution}</>   <fg=red>DANGER: {$danger}</>",
        );

        if ($this->option('ci') && $hasDanger) {
            $this->newLine();
            $this->line('  <fg=red;options=bold>Exiting with code 1 — DANGER operations detected.</>');

            return self::FAILURE;
        }

        if (! $this->option('ci')) {
            $this->line('  <fg=gray>Run with --ci to exit code 1 on any DANGER operation.</>');
        }

        $this->newLine();

        return self::SUCCESS;
    }

    // ─── Output helpers ───────────────────────────────────────────────────────

    private function colorizeRisk(string $risk): string
    {
        return match ($risk) {
            'SAFE' => '<fg=green>SAFE</>',
            'CAUTION' => '<fg=yellow>CAUTION</>',
            'DANGER' => '<fg=red;options=bold>DANGER</>',
            default => $risk,
        };
    }

    private function formatRowCount(?int $count): string
    {
        if ($count === null) {
            return '—';
        }

        if ($count >= 1_000_000) {
            return number_format($count / 1_000_000, 1).'M';
        }

        if ($count >= 1_000) {
            return number_format($count);
        }

        return (string) $count;
    }

    private function truncate(string $text, int $maxLength): string
    {
        return mb_strlen($text) > $maxLength
            ? mb_substr($text, 0, $maxLength - 1).'…'
            : $text;
    }
}
