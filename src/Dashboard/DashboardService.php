<?php

namespace Flux\Dashboard;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Flux\Database\DatabaseAdapterFactory;
use Flux\Snapshots\SnapshotManager;
use Flux\Generators\SchemaComparator;
use Flux\Support\ArtisanRunner;

class DashboardService
{
    public function __construct(
        protected Migrator $migrator,
        protected DatabaseAdapterFactory $adapterFactory,
        protected SnapshotManager $snapshotManager,
        protected SchemaComparator $schemaComparator,
        protected DatabaseManager $db,
        protected Application $app,
        protected ConfigRepository $config,
        protected ArtisanRunner $artisan,
        protected string $migrationsPath = ''
    ) {
        if (empty($this->migrationsPath)) {
            $this->migrationsPath = database_path('migrations');
        }
    }

    /**
     * Get overall dashboard status
     */
    public function getStatus(): array
    {
        $adapter = $this->adapterFactory->create();
        $ran = $this->migrator->getRepository()->getRan();
        $paths = [$this->migrationsPath];
        $pending = $this->migrator->getMigrationFiles($paths) ?? [];
        $pending = array_diff(array_keys($pending), $ran);

        // Check for drift
        $hasDrift = false;
        try {
            $snapshot = $this->snapshotManager->getLatest();
            if ($snapshot) {
                $currentSchema = $this->extractCurrentSchema($adapter);
                $differences = $this->schemaComparator->compare($snapshot['schema'], $currentSchema);
                $hasDrift = !empty($differences);
            }
        } catch (\Exception $e) {
            // Ignore drift check errors
        }

        return [
            'environment' => $this->app->environment(),
            'pending_count' => count($pending),
            'applied_count' => count($ran),
            'table_count' => count($adapter->getAllTables()),
            'drift_detected' => $hasDrift,
            'database_driver' => $this->config->get('database.default'),
            'laravel_version' => $this->app->version(),
        ];
    }

    /**
     * Get all migrations (pending and applied)
     */
    public function getMigrations(): array
    {
        $ran = $this->migrator->getRepository()->getRan();
        $paths = [$this->migrationsPath];
        $files = $this->migrator->getMigrationFiles($paths) ?? [];

        $migrations = [];
        foreach ($files as $name => $path) {
            $isApplied = in_array($name, $ran);

            $migrations[] = [
                'name' => $name,
                'path' => $path,
                'status' => $isApplied ? 'applied' : 'pending',
                'applied_at' => $isApplied ? $this->getAppliedDate($name) : null,
                'risk' => $this->estimateRisk($name),
                'estimated_time' => $this->estimateTime($name),
            ];
        }

        return $migrations;
    }

    /**
     * Get migration history
     */
    public function getHistory(): array
    {
        return $this->db->table('migrations')
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($migration) {
                return [
                    'id' => $migration->id,
                    'migration' => $migration->migration,
                    'batch' => $migration->batch,
                    'applied_at' => $migration->created_at ?? now(),
                    'execution_time' => null, // Can be enhanced to track this
                ];
            })
            ->toArray();
    }

    /**
     * Get current database schema
     */
    public function getSchema(): array
    {
        $adapter = $this->adapterFactory->create();
        $tables = $adapter->getAllTables();

        $schema = [
            'tables' => []
        ];

        foreach ($tables as $table) {
            $columns = $adapter->getTableColumns($table);
            $indexes = $adapter->getTableIndexes($table);
            $foreignKeys = $adapter->getTableForeignKeys($table);

            $schema['tables'][] = [
                'name' => $table,
                'columns' => $columns,
                'column_count' => count($columns),
                'indexes' => $indexes,
                'foreign_keys' => $foreignKeys,
                'row_count' => $adapter->getTableRowCount($table),
            ];
        }

        return $schema;
    }

    /**
     * Get drift information
     */
    public function getDrift(): array
    {
        try {
            $snapshot = $this->snapshotManager->getLatest();
            if (!$snapshot) {
                return [
                    'has_drift' => false,
                    'differences' => [],
                    'message' => 'No snapshot available for comparison',
                ];
            }

            $adapter = $this->adapterFactory->create();
            $currentSchema = $this->extractCurrentSchema($adapter);
            $differences = $this->schemaComparator->compare($snapshot['schema'], $currentSchema);

            return [
                'has_drift' => !empty($differences),
                'differences' => $differences,
                'snapshot_created_at' => $snapshot['timestamp'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'has_drift' => false,
                'differences' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get snapshots list
     */
    public function getSnapshots(): array
    {
        try {
            return $this->snapshotManager->list();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get metrics data
     */
    public function getMetrics(): array
    {
        $adapter = $this->adapterFactory->create();
        $tables = $adapter->getAllTables();

        // Get table sizes
        $tableSizes = [];
        foreach ($tables as $table) {
            $rowCount = $adapter->getTableRowCount($table);
            // Estimate size based on row count (rough estimation)
            $tableSizes[] = [
                'name' => $table,
                'size' => $rowCount * 1024, // Rough estimate: 1KB per row
                'rows' => $rowCount,
            ];
        }

        // Risk distribution (mock data - can be enhanced)
        $riskDistribution = [
            'safe' => 0,
            'warning' => 0,
            'danger' => 0,
        ];

        $paths = [$this->migrationsPath];
        $files = $this->migrator->getMigrationFiles($paths) ?? [];
        foreach ($files as $name => $path) {
            $risk = $this->estimateRisk($name);
            if (isset($riskDistribution[$risk])) {
                $riskDistribution[$risk]++;
            }
        }

        return [
            'risk_distribution' => $riskDistribution,
            'execution_times' => $this->getExecutionTimes(),
            'table_sizes' => $tableSizes,
            'archive_sizes' => $this->getArchiveSizes($adapter),
        ];
    }

    /**
     * Get applied date for a migration
     */
    protected function getAppliedDate(string $name): ?string
    {
        $migration = $this->db->table('migrations')
            ->where('migration', $name)
            ->first();

        return $migration->created_at ?? null;
    }

    /**
     * Estimate risk level for a migration
     */
    protected function estimateRisk(string $name): string
    {
        // Simple heuristic based on migration name
        $dangerKeywords = ['drop', 'delete', 'remove'];
        $warningKeywords = ['alter', 'modify', 'change', 'rename'];

        $lowerName = strtolower($name);

        foreach ($dangerKeywords as $keyword) {
            if (str_contains($lowerName, $keyword)) {
                return 'danger';
            }
        }

        foreach ($warningKeywords as $keyword) {
            if (str_contains($lowerName, $keyword)) {
                return 'warning';
            }
        }

        return 'safe';
    }

    /**
     * Estimate execution time for a migration
     */
    protected function estimateTime(string $name): string
    {
        // Simple estimation
        $createKeywords = ['create_table', 'create'];
        $lowerName = strtolower($name);

        foreach ($createKeywords as $keyword) {
            if (str_contains($lowerName, $keyword)) {
                return '~50ms';
            }
        }

        return '~20ms';
    }

    /**
     * Get execution times from history
     */
    protected function getExecutionTimes(): array
    {
        $history = $this->db->table('migrations')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        return $history->map(function ($migration) {
            return [
                'migration' => $migration->migration,
                'time' => rand(10, 500), // Mock data - can be enhanced with actual timing
            ];
        })->toArray();
    }

    /**
     * Get archive sizes
     */
    protected function getArchiveSizes($adapter): array
    {
        $tables = $adapter->getAllTables();
        $archives = [];

        foreach ($tables as $table) {
            if (str_starts_with($table, '_archived_')) {
                $rowCount = $adapter->getTableRowCount($table);
                $archives[] = [
                    'name' => $table,
                    'size' => $rowCount * 1024, // Rough estimate
                    'rows' => $rowCount,
                ];
            }
        }

        return $archives;
    }

    /**
     * Extract current database schema
     */
    protected function extractCurrentSchema($adapter): array
    {
        $schema = [
            'tables' => [],
        ];

        foreach ($adapter->getAllTables() as $table) {
            $schema['tables'][$table] = [
                'columns' => $adapter->getTableColumns($table),
                'indexes' => $adapter->getTableIndexes($table),
                'foreign_keys' => $adapter->getTableForeignKeys($table),
            ];
        }

        return $schema;
    }

    /**
     * Generate fix migration for drift
     */
    public function generateFixMigration(): array
    {
        try {
            // Run the migrate:check --fix command
            $this->artisan->call('migrate:check', ['--fix' => true]);
            $output = $this->artisan->output();

            return [
                'success' => true,
                'message' => 'Fix migration generated successfully',
                'output' => $output,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to generate fix migration: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create a new snapshot
     */
    public function createSnapshot(?string $name = null): array
    {
        try {
            $params = ['name' => $name ?? 'dashboard_snapshot_' . date('Y_m_d_His')];
            $this->artisan->call('migrate:snapshot', ['command' => 'create', ...$params]);
            $output = $this->artisan->output();

            return [
                'success' => true,
                'message' => 'Snapshot created successfully',
                'output' => $output,
                'name' => $params['name'],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create snapshot: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a snapshot
     */
    public function deleteSnapshot(string $name): array
    {
        try {
            $this->artisan->call('migrate:snapshot', ['command' => 'delete', 'name' => $name]);
            $output = $this->artisan->output();

            return [
                'success' => true,
                'message' => 'Snapshot deleted successfully',
                'output' => $output,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete snapshot: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Run migrations
     */
    public function runMigrations(array $options = []): array
    {
        try {
            $params = [];
            if (isset($options['path'])) {
                $params['--path'] = $options['path'];
            }
            if (isset($options['force']) && $options['force']) {
                $params['--force'] = true;
            }

            // Use migrate:safe for safe migrations with backups
            $this->artisan->call('migrate:safe', $params);
            $output = $this->artisan->output();

            return [
                'success' => true,
                'message' => 'Migrations ran successfully',
                'output' => $output,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to run migrations: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Rollback migrations
     */
    public function rollbackMigrations(array $options = []): array
    {
        try {
            $params = [];
            if (isset($options['step'])) {
                $params['--step'] = $options['step'];
            }
            if (isset($options['batch'])) {
                $params['--batch'] = $options['batch'];
            }

            // Use migrate:undo for safe rollback with data preservation
            $this->artisan->call('migrate:undo', $params);
            $output = $this->artisan->output();

            return [
                'success' => true,
                'message' => 'Rollback completed successfully',
                'output' => $output,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to rollback migrations: ' . $e->getMessage(),
            ];
        }
    }
}
