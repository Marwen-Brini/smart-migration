<?php

namespace Flux\Cleanup;

use Flux\Config\SmartMigrationConfig;
use Flux\Database\DatabaseAdapterFactoryInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ArchiveCleanupService
{
    protected array $cleanedTables = [];

    protected array $cleanedColumns = [];

    protected int $totalRowsDeleted = 0;

    protected DatabaseAdapterFactoryInterface $adapterFactory;

    public function __construct(?DatabaseAdapterFactoryInterface $adapterFactory = null)
    {
        // Support both DI and legacy instantiation
        $this->adapterFactory = $adapterFactory ?? app(DatabaseAdapterFactoryInterface::class);
    }

    /**
     * Clean up old archived tables and columns
     */
    public function cleanup(bool $dryRun = false): array
    {
        // Check if auto cleanup is enabled
        if (! SmartMigrationConfig::autoCleanupEnabled()) {
            return [
                'status' => 'disabled',
                'message' => 'Auto cleanup is disabled in configuration',
                'dry_run' => $dryRun,
                'tables_cleaned' => [],
                'columns_cleaned' => [],
                'total_rows_deleted' => 0,
                'retention_days' => SmartMigrationConfig::getArchiveRetentionDays(),
            ];
        }

        $retentionDays = SmartMigrationConfig::getArchiveRetentionDays();

        if ($retentionDays <= 0) {
            return [
                'status' => 'skipped',
                'message' => 'Archive retention is set to keep forever',
                'dry_run' => $dryRun,
                'tables_cleaned' => [],
                'columns_cleaned' => [],
                'total_rows_deleted' => 0,
                'retention_days' => $retentionDays,
            ];
        }

        $cutoffDate = now()->subDays($retentionDays);

        // Clean up archived tables
        $this->cleanupArchivedTables($cutoffDate, $dryRun);

        // Clean up archived columns
        $this->cleanupArchivedColumns($cutoffDate, $dryRun);

        // Log the cleanup
        if (! $dryRun && SmartMigrationConfig::loggingEnabled()) {
            $this->logCleanup();
        }

        return [
            'status' => 'success',
            'dry_run' => $dryRun,
            'tables_cleaned' => $this->cleanedTables,
            'columns_cleaned' => $this->cleanedColumns,
            'total_rows_deleted' => $this->totalRowsDeleted,
            'retention_days' => $retentionDays,
            'cutoff_date' => $cutoffDate->format('c'),
        ];
    }

    /**
     * Clean up archived tables older than retention period
     */
    protected function cleanupArchivedTables(\DateTime $cutoffDate, bool $dryRun): void
    {
        $adapter = $this->adapterFactory->create();
        $tablePrefix = SmartMigrationConfig::getArchiveTablePrefix();
        $tables = $adapter->getAllTables();

        foreach ($tables as $table) {
            // Check if this is an archived table
            if (! str_starts_with($table, $tablePrefix)) {
                continue;
            }

            // Extract timestamp from table name
            $timestamp = $this->extractTimestamp($table);

            if ($timestamp && $timestamp < $cutoffDate) {
                $rowCount = 0;

                if (! $dryRun) {
                    // Count rows before deletion
                    $rowCount = $adapter->getTableRowCount($table);
                    $this->totalRowsDeleted += $rowCount;

                    // Drop the table
                    Schema::dropIfExists($table);
                }

                $this->cleanedTables[] = [
                    'name' => $table,
                    'archived_date' => $timestamp->format('c'),
                    'rows' => $rowCount,
                ];
            }
        }
    }

    /**
     * Clean up archived columns older than retention period
     */
    protected function cleanupArchivedColumns(\DateTime $cutoffDate, bool $dryRun): void
    {
        $adapter = $this->adapterFactory->create();
        $columnPrefix = SmartMigrationConfig::getArchiveColumnPrefix();
        $tables = $adapter->getAllTables();

        foreach ($tables as $table) {
            // Skip archived tables (they'll be cleaned up entirely)
            if (str_starts_with($table, SmartMigrationConfig::getArchiveTablePrefix())) {
                continue;
            }

            $columns = $adapter->getTableColumns($table);

            foreach ($columns as $column) {
                $columnName = $column['name'];

                // Check if this is an archived column
                if (! str_starts_with($columnName, $columnPrefix)) {
                    continue;
                }

                // Extract timestamp from column name
                $timestamp = $this->extractTimestamp($columnName);

                if ($timestamp && $timestamp < $cutoffDate) {
                    if (! $dryRun) {
                        // Drop the column
                        Schema::table($table, function ($tableSchema) use ($columnName) {
                            $tableSchema->dropColumn($columnName);
                        });
                    }

                    $this->cleanedColumns[] = [
                        'table' => $table,
                        'column' => $columnName,
                        'archived_date' => $timestamp->format('c'),
                    ];
                }
            }
        }
    }

    /**
     * Extract timestamp from archived name
     */
    protected function extractTimestamp(string $name): ?\DateTime
    {
        // Look for timestamp pattern YYYYMMDD_HHMMSS
        if (preg_match('/(\d{8}_\d{6})/', $name, $matches)) {
            $timestampStr = $matches[1];

            try {
                $date = \DateTime::createFromFormat('Ymd_His', $timestampStr);

                return $date !== false ? $date : null;
                // @codeCoverageIgnoreStart
            } catch (\Exception $e) {
                return null;
            }
            // @codeCoverageIgnoreEnd
        }

        return null;
    }

    /**
     * Log cleanup activity
     */
    protected function logCleanup(): void
    {
        $message = sprintf(
            'Archive cleanup completed: %d tables cleaned, %d columns cleaned, %d total rows deleted',
            count($this->cleanedTables),
            count($this->cleanedColumns),
            $this->totalRowsDeleted
        );

        Log::channel(SmartMigrationConfig::getLogChannel())->info($message, [
            'tables' => $this->cleanedTables,
            'columns' => $this->cleanedColumns,
            'rows_deleted' => $this->totalRowsDeleted,
        ]);
    }

    /**
     * Get cleanup statistics
     */
    public function getStatistics(): array
    {
        $adapter = $this->adapterFactory->create();
        $tablePrefix = SmartMigrationConfig::getArchiveTablePrefix();
        $columnPrefix = SmartMigrationConfig::getArchiveColumnPrefix();

        $archivedTables = [];
        $archivedColumns = [];
        $totalArchivedRows = 0;
        $totalArchivedSize = 0;

        $tables = $adapter->getAllTables();

        foreach ($tables as $table) {
            // Check for archived tables
            if (str_starts_with($table, $tablePrefix)) {
                $rowCount = $adapter->getTableRowCount($table);
                $timestamp = $this->extractTimestamp($table);

                $archivedTables[] = [
                    'name' => $table,
                    'rows' => $rowCount,
                    'archived_date' => $timestamp ? $timestamp->format('c') : 'unknown',
                ];

                $totalArchivedRows += $rowCount;
            } else {
                // Check for archived columns in regular tables
                $columns = $adapter->getTableColumns($table);

                foreach ($columns as $column) {
                    if (str_starts_with($column['name'], $columnPrefix)) {
                        $timestamp = $this->extractTimestamp($column['name']);

                        $archivedColumns[] = [
                            'table' => $table,
                            'column' => $column['name'],
                            'archived_date' => $timestamp ? $timestamp->format('c') : 'unknown',
                        ];
                    }
                }
            }
        }

        return [
            'archived_tables' => $archivedTables,
            'archived_columns' => $archivedColumns,
            'total_archived_tables' => count($archivedTables),
            'total_archived_columns' => count($archivedColumns),
            'total_archived_rows' => $totalArchivedRows,
            'retention_days' => SmartMigrationConfig::getArchiveRetentionDays(),
            'auto_cleanup_enabled' => SmartMigrationConfig::autoCleanupEnabled(),
        ];
    }
}
