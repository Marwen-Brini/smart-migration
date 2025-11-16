<?php

namespace Flux\Safety;

use Flux\Config\SmartMigrationConfig;
use Flux\Database\DatabaseAdapter;
use Flux\Database\DatabaseAdapterFactoryInterface;
use Flux\Monitoring\AnomalyDetector;
use Flux\Monitoring\PerformanceBaseline;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SafeMigrator extends Migrator
{
    protected array $backupData = [];

    protected array $affectedTables = [];

    protected ?DatabaseAdapter $adapter = null;

    protected ?DatabaseAdapterFactoryInterface $adapterFactory = null;

    protected ?PerformanceBaseline $performanceBaseline = null;

    protected ?AnomalyDetector $anomalyDetector = null;

    /**
     * Set the adapter factory (for dependency injection)
     */
    public function setAdapterFactory(DatabaseAdapterFactoryInterface $factory): void
    {
        $this->adapterFactory = $factory;
    }

    /**
     * Get database adapter
     */
    protected function getAdapter(): DatabaseAdapter
    {
        if ($this->adapter === null) {
            if ($this->adapterFactory === null) {
                // Support legacy usage
                $this->adapterFactory = app(DatabaseAdapterFactoryInterface::class);
            }
            $this->adapter = $this->adapterFactory->create();
        }

        return $this->adapter;
    }

    /**
     * Public wrapper for pretendToRun method
     */
    public function pretendToRunMigration($migration, string $method): void
    {
        $this->pretendToRun($migration, $method);
    }

    /**
     * Run migration with safety features
     */
    public function runSafe(string $file, int $batch, bool $pretend = false): void
    {
        $name = $this->getMigrationName($file);

        // Load the migration file and get the instance
        $migration = $this->resolveMigration($file);

        if ($pretend) {
            $this->pretendToRun($migration, 'up');

            return;
        }

        $this->write("<comment>🔄 Migrating (SAFE):</comment> <info>{$name}</info>");

        // Start performance tracking
        $startTime = microtime(true);
        $startMemory = memory_get_usage() / 1024 / 1024; // MB
        DB::enableQueryLog();
        $initialQueryCount = count(DB::getQueryLog());

        try {
            // Analyze and backup affected tables
            $this->analyzeAndBackup($file);

            // Run the migration
            $this->runMigration($migration, 'up');

            // Record migration in database
            $this->repository->log($name, $batch);

            // Collect performance metrics
            $durationMs = (microtime(true) - $startTime) * 1000;
            $memoryUsed = (memory_get_usage() / 1024 / 1024) - $startMemory;
            $queryCount = count(DB::getQueryLog()) - $initialQueryCount;

            // Record performance baseline
            $this->recordPerformance($name, [
                'duration_ms' => $durationMs,
                'memory_mb' => $memoryUsed,
                'query_count' => $queryCount,
            ]);

            $this->write("<info>✅ Migrated (SAFE):</info>  <comment>{$name}</comment>");

        } catch (\Exception $e) {
            // Attempt to restore backups if needed
            $this->restoreBackups();

            $this->write("<error>❌ Migration failed and rolled back:</error> <comment>{$name}</comment>");
            throw $e;
        }
    }

    /**
     * Undo migration without data loss
     */
    public function undoSafe(string $file): bool
    {
        $name = $this->getMigrationName($file);

        // Load the migration file and get the instance
        $migration = $this->resolveMigration($file);

        $this->write("<comment>↩️ Rolling back (SAFE):</comment> <info>{$name}</info>");

        try {
            // Instead of running down(), we'll rename/archive
            $this->safeRollback($file);

            // Remove from migration log
            $this->repository->delete((object) ['migration' => $name]);

            $this->write("<info>✅ Rolled back (SAFE):</info> <comment>{$name}</comment>");

            return true;

        } catch (\Exception $e) {
            $this->write("<error>❌ Rollback failed:</error> <comment>{$name}</comment>");
            throw $e;
        }
    }

    /**
     * Analyze migration and backup affected tables
     */
    protected function analyzeAndBackup(string $file): void
    {
        // Skip backup if disabled in config
        if (! SmartMigrationConfig::autoBackupEnabled()) {
            return;
        }

        $content = file_get_contents($file);

        // Find tables that will be affected
        preg_match_all('/Schema::(table|create|drop|dropIfExists)\([\'"](\w+)[\'"]/', $content, $matches);

        foreach ($matches[2] as $table) {
            if (Schema::hasTable($table)) {
                $this->backupTable($table);
                $this->affectedTables[] = $table;
            }
        }
    }

    /**
     * Backup a table's structure and data
     */
    protected function backupTable(string $table): void
    {
        $adapter = $this->getAdapter();
        $this->write("<comment>📦 Backing up table:</comment> <info>{$table}</info>");

        // Store structure and data using adapter
        $this->backupData[$table] = [
            'structure' => $adapter->getTableStructure($table),
            'data' => $adapter->getTableData($table),
            'count' => $adapter->getTableRowCount($table),
        ];
    }

    /**
     * Restore backed up tables if needed
     */
    protected function restoreBackups(): void
    {
        foreach ($this->backupData as $table => $backup) {
            $this->write("<comment>🔄 Attempting to restore table:</comment> <info>{$table}</info>");

            try {
                // Drop the potentially corrupted table
                Schema::dropIfExists($table);

                // Recreate table structure
                $this->getAdapter()->execute($backup['structure']);

                // Restore data
                if (! empty($backup['data'])) {
                    DB::table($table)->insert($backup['data']);
                }

                $this->write("<info>✅ Restored table:</info> <comment>{$table}</comment>");

            } catch (\Exception $e) {
                $this->write("<error>❌ Failed to restore table:</error> <comment>{$table}</comment>");
            }
        }
    }

    /**
     * Safe rollback that preserves data
     */
    protected function safeRollback(string $file): void
    {
        // Skip safe rollback if disabled in config
        if (! SmartMigrationConfig::safeRollbackEnabled()) {
            // Fall back to regular rollback
            $migration = $this->resolveMigration($file);
            $this->runMigration($migration, 'down');

            return;
        }

        $content = file_get_contents($file);
        $timestamp = SmartMigrationConfig::includeTimestampInArchive() ? now()->format('Ymd_His') : '';

        // Handle dropped columns by renaming instead
        preg_match_all('/\$table->dropColumn\([\'"](\w+)[\'"]/', $content, $dropColumns);
        foreach ($dropColumns[1] as $column) {
            preg_match('/Schema::table\([\'"](\w+)[\'"].*?'.preg_quote($column).'/s', $content, $tableMatch);
            if (isset($tableMatch[1])) {
                $table = $tableMatch[1];
                $this->archiveColumn($table, $column, $timestamp);
            }
        }

        // Handle dropped tables by renaming instead
        preg_match_all('/Schema::drop(?:IfExists)?\([\'"](\w+)[\'"]/', $content, $dropTables);
        foreach ($dropTables[1] as $table) {
            $this->archiveTable($table, $timestamp);
        }
    }

    /**
     * Archive a column instead of dropping it
     */
    protected function archiveColumn(string $table, string $column, string $timestamp): void
    {
        $adapter = $this->getAdapter();

        if ($adapter->columnExists($table, $column)) {
            $prefix = SmartMigrationConfig::getArchiveColumnPrefix();
            $newName = $timestamp ? "{$prefix}{$column}_{$timestamp}" : "{$prefix}{$column}";

            $this->write("<comment>📦 Archiving column:</comment> <info>{$table}.{$column}</info> <comment>-></comment> <question>{$newName}</question>");

            $adapter->archiveColumn($table, $column, $newName);
        }
    }

    /**
     * Archive a table instead of dropping it
     */
    protected function archiveTable(string $table, string $timestamp): void
    {
        $adapter = $this->getAdapter();

        if ($adapter->tableExists($table)) {
            $prefix = SmartMigrationConfig::getArchiveTablePrefix();
            $newName = $timestamp ? "{$prefix}{$table}_{$timestamp}" : "{$prefix}{$table}";

            $this->write("<comment>📦 Archiving table:</comment> <info>{$table}</info> <comment>-></comment> <question>{$newName}</question>");

            $adapter->archiveTable($table, $newName);
        }
    }

    /**
     * Get tables that would be affected by a migration
     */
    public function getAffectedTables(string $file): array
    {
        $content = file_get_contents($file);
        $tables = [];

        preg_match_all('/Schema::(table|create|drop|dropIfExists|rename)\([\'"](\w+)[\'"]/', $content, $matches);

        foreach ($matches[2] as $table) {
            $tables[] = $table;
        }

        return array_unique($tables);
    }

    /**
     * Estimate the data that would be lost
     */
    public function estimateDataLoss(string $file): array
    {
        $content = file_get_contents($file);
        $loss = [];

        // Check for drop operations
        preg_match_all('/Schema::drop(?:IfExists)?\([\'"](\w+)[\'"]/', $content, $dropTables);
        foreach ($dropTables[1] as $table) {
            if (Schema::hasTable($table)) {
                $loss[] = [
                    'type' => 'table',
                    'name' => $table,
                    'rows' => DB::table($table)->count(),
                ];
            }
        }

        // Check for column drops
        preg_match_all('/\$table->dropColumn\([\'"](\w+)[\'"]/', $content, $dropColumns);
        foreach ($dropColumns[1] as $column) {
            preg_match('/Schema::table\([\'"](\w+)[\'"].*?'.preg_quote($column).'/s', $content, $tableMatch);
            if (isset($tableMatch[1])) {
                $table = $tableMatch[1];
                if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                    $nonNullCount = DB::table($table)->whereNotNull($column)->count();
                    if ($nonNullCount > 0) {
                        $loss[] = [
                            'type' => 'column',
                            'table' => $table,
                            'name' => $column,
                            'rows' => $nonNullCount,
                        ];
                    }
                }
            }
        }

        return $loss;
    }

    /**
     * Resolve a migration instance from a file
     * Handles both named classes and anonymous classes (Laravel 9+)
     */
    public function resolveMigration(string $file)
    {
        $migration = $this->files->getRequire($file);

        // Check if it's an anonymous class (Laravel 9+)
        if (is_object($migration)) {
            return $migration;
        }

        // Fall back to the old way for named classes
        $class = $this->getMigrationClass(Str::studly(implode('_', array_slice(explode('_', basename($file, '.php')), 4))));

        return new $class;
    }

    /**
     * Record performance metrics and check for anomalies
     */
    protected function recordPerformance(string $migration, array $metrics): void
    {
        try {
            // Get or create performance baseline instance
            if ($this->performanceBaseline === null) {
                $this->performanceBaseline = app(PerformanceBaseline::class);
            }

            if ($this->anomalyDetector === null) {
                $this->anomalyDetector = app(AnomalyDetector::class);
            }

            // Check for anomalies before recording
            $anomalyResult = $this->anomalyDetector->detect($migration, $metrics);

            // Record the metrics in baseline
            $this->performanceBaseline->record($migration, $metrics);

            // Warn if anomalies detected
            if ($anomalyResult['has_anomalies']) {
                $this->write("<comment>⚠️  Performance anomalies detected:</comment>");

                foreach ($anomalyResult['anomalies'] as $anomaly) {
                    $severityColor = match($anomaly['severity']) {
                        'critical' => 'error',
                        'high' => 'error',
                        'medium' => 'comment',
                        default => 'info',
                    };

                    $this->write("<{$severityColor}>  [{$anomaly['severity']}] {$anomaly['message']}</{$severityColor}>");
                }
            }

        } catch (\Exception $e) {
            // Don't fail the migration if performance tracking fails
            // Just log it silently or optionally warn
            if (config('smart-migration.monitoring.verbose_errors', false)) {
                $this->write("<comment>Performance tracking error: {$e->getMessage()}</comment>");
            }
        }
    }
}
