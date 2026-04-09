<?php

namespace LaravelForecast;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;

class MigrationRunner
{
    /** @var array<string, Migration> */
    private array $resolved = [];

    public function __construct(
        protected Migrator   $migrator,
        protected Filesystem $files,
    ) {}

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Returns pending migrations as [name => absolute_path], sorted by name.
     *
     * @return array<string, string>
     */
    public function getPendingMigrations(): array
    {
        $allFiles = $this->migrator->getMigrationFiles($this->getMigrationPaths());

        if (! $this->migrator->repositoryExists()) {
            // Migrations table doesn't exist yet — every file is pending.
            return $allFiles;
        }

        $ran = $this->migrator->getRepository()->getRan();

        // Keep the [name => path] structure (pendingMigrations() drops keys with values()).
        return array_filter(
            $allFiles,
            fn (string $path, string $name) => ! in_array($name, $ran, true),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * Returns the SQL queries a migration would execute, without running them.
     *
     * Each item: ['query' => string, 'bindings' => array, 'time' => float]
     *
     * @return array<int, array{query: string, bindings: array<mixed>, time: float}>
     */
    public function getSqlForMigration(string $migrationName, string $migrationPath): array
    {
        $migration = $this->resolveMigration($migrationName, $migrationPath);

        if (! $migration) {
            return [];
        }

        $connectionName = $this->getConnectionName($migration);

        try {
            $connection = app('db')->connection($connectionName);

            return $connection->pretend(function () use ($migration): void {
                if (method_exists($migration, 'up')) {
                    $migration->up();
                }
            });
        } catch (\Throwable) {
            return [];
        }
    }

    // ─── Internal helpers ─────────────────────────────────────────────────────

    protected function resolveMigration(string $migrationName, string $migrationPath): ?Migration
    {
        if (isset($this->resolved[$migrationName])) {
            return $this->resolved[$migrationName];
        }

        $realPath     = realpath($migrationPath) ?: $migrationPath;
        $alreadyLoaded = in_array($realPath, get_included_files(), true);

        if (! $alreadyLoaded) {
            // Require the file once and capture the return value.
            // Anonymous-class migrations return a Migration instance directly.
            $result = require $realPath;

            if ($result instanceof Migration) {
                return $this->resolved[$migrationName] = $result;
            }
            // Named-class migration — the class is now defined, fall through.
        }

        // Named class — resolve via the migrator (handles class-name derivation).
        try {
            $migration = $this->migrator->resolve($migrationName);

            return $this->resolved[$migrationName] = $migration;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function getConnectionName(Migration $migration): string
    {
        if (property_exists($migration, 'connection') && $migration->connection) {
            return $migration->connection;
        }

        return config('database.default', 'mysql');
    }

    protected function getMigrationPaths(): array
    {
        // Paths registered by packages via loadMigrationsFrom()
        $paths = $this->migrator->paths();

        // Default application path
        if (function_exists('database_path')) {
            array_unshift($paths, database_path('migrations'));
        }

        // Config-defined extra paths
        $extra = config('forecast.migration_paths', []);

        return array_values(array_unique(array_merge($paths, $extra)));
    }
}

