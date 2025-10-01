<?php

namespace Flux\Config;

use Illuminate\Support\Facades\Config;

class SmartMigrationConfig
{
    /**
     * Get a configuration value
     */
    public static function get(string $key, $default = null)
    {
        return Config::get("smart-migration.{$key}", $default);
    }

    /**
     * Check if automatic backups are enabled
     */
    public static function autoBackupEnabled(): bool
    {
        return self::get('safety.auto_backup', true);
    }

    /**
     * Check if safe rollback is enabled
     */
    public static function safeRollbackEnabled(): bool
    {
        return self::get('safety.safe_rollback', true);
    }

    /**
     * Check if confirmation is required
     */
    public static function requiresConfirmation(): bool
    {
        return self::get('safety.require_confirmation', true);
    }

    /**
     * Alias for requiresConfirmation for backward compatibility
     */
    public static function requireConfirmation(): bool
    {
        return self::requiresConfirmation();
    }

    /**
     * Check if destructive operations are allowed in production
     */
    public static function allowDestructiveInProduction(): bool
    {
        return self::get('safety.allow_destructive_in_production', false);
    }

    /**
     * Get backup path
     */
    public static function getBackupPath(): string
    {
        return self::get('backup.path', 'smart-migration-backups');
    }

    /**
     * Get backup format
     */
    public static function getBackupFormat(): string
    {
        return self::get('backup.format', 'sql');
    }

    /**
     * Check if backups should be compressed
     */
    public static function shouldCompressBackups(): bool
    {
        return self::get('backup.compress', true);
    }

    /**
     * Get backup retention days
     */
    public static function getBackupRetentionDays(): int
    {
        return self::get('backup.retention_days', 30);
    }

    /**
     * Get archive table prefix
     */
    public static function getArchiveTablePrefix(): string
    {
        return self::get('archive.table_prefix', '_archived_');
    }

    /**
     * Get archive column prefix
     */
    public static function getArchiveColumnPrefix(): string
    {
        return self::get('archive.column_prefix', '__backup_');
    }

    /**
     * Check if timestamp should be included in archive names
     */
    public static function includeTimestampInArchive(): bool
    {
        return self::get('archive.include_timestamp', true);
    }

    /**
     * Get archive retention days
     */
    public static function getArchiveRetentionDays(): int
    {
        return self::get('archive.retention_days', 7);
    }

    /**
     * Check if auto cleanup is enabled
     */
    public static function autoCleanupEnabled(): bool
    {
        return self::get('archive.auto_cleanup', false);
    }

    /**
     * Get cleanup schedule
     */
    public static function getCleanupSchedule(): string
    {
        return self::get('archive.cleanup_schedule', '0 2 * * *');
    }

    /**
     * Get risk level for an operation
     */
    public static function getOperationRisk(string $operation): string
    {
        return self::get("risk.operations.{$operation}", 'warning');
    }

    /**
     * Check if dangerous operations should be blocked in production
     */
    public static function blockDangerInProduction(): bool
    {
        return self::get('risk.block_danger_in_production', false);
    }

    /**
     * Check if colors are enabled
     */
    public static function colorsEnabled(): bool
    {
        return self::get('display.colors', true);
    }

    /**
     * Check if emojis are enabled
     */
    public static function emojisEnabled(): bool
    {
        return self::get('display.emojis', true);
    }

    /**
     * Check if progress bars are enabled
     */
    public static function progressBarsEnabled(): bool
    {
        return self::get('display.progress_bars', true);
    }

    /**
     * Get verbosity level
     */
    public static function getVerbosity(): string
    {
        return self::get('display.verbosity', 'normal');
    }

    /**
     * Check if SQL should be shown
     */
    public static function showSql(): bool
    {
        return self::get('display.show_sql', true);
    }

    /**
     * Check if timing should be shown
     */
    public static function showTiming(): bool
    {
        return self::get('display.show_timing', true);
    }

    /**
     * Get database driver settings
     */
    public static function getDriverSettings(string $driver): array
    {
        return self::get("drivers.{$driver}", []);
    }

    /**
     * Check if a driver is enabled
     */
    public static function isDriverEnabled(string $driver): bool
    {
        return self::get("drivers.{$driver}.enabled", false);
    }

    /**
     * Get snapshot path
     */
    public static function getSnapshotPath(): string
    {
        return self::get('snapshots.path', 'snapshots');
    }

    /**
     * Get snapshot format
     */
    public static function getSnapshotFormat(): string
    {
        return self::get('snapshots.format', 'json');
    }

    /**
     * Check if auto snapshot is enabled
     */
    public static function autoSnapshotEnabled(): bool
    {
        return self::get('snapshots.auto_snapshot', false);
    }

    /**
     * Get maximum number of snapshots
     */
    public static function getMaxSnapshots(): int
    {
        return self::get('snapshots.max_snapshots', 10);
    }

    /**
     * Check if drift auto-detection is enabled
     */
    public static function autoDriftDetection(): bool
    {
        return self::get('drift.auto_detect', true);
    }

    /**
     * Check if drift should be checked before migrations
     */
    public static function checkDriftBeforeMigrate(): bool
    {
        return self::get('drift.check_before_migrate', true);
    }

    /**
     * Check if drift detection should only warn
     */
    public static function driftWarnOnly(): bool
    {
        return self::get('drift.warn_only', true);
    }

    /**
     * Get ignored tables for drift detection
     */
    public static function getDriftIgnoredTables(): array
    {
        return self::get('drift.ignored_tables', []);
    }

    /**
     * Get ignored columns for drift detection
     */
    public static function getDriftIgnoredColumns(): array
    {
        return self::get('drift.ignored_columns', []);
    }

    /**
     * Check if notifications are enabled
     */
    public static function notificationsEnabled(): bool
    {
        return self::get('notifications.enabled', false);
    }

    /**
     * Get notification channels
     */
    public static function getNotificationChannels(): array
    {
        return self::get('notifications.channels', ['mail']);
    }

    /**
     * Check if an event should trigger notifications
     */
    public static function shouldNotifyEvent(string $event): bool
    {
        return self::get("notifications.events.{$event}", false);
    }

    /**
     * Get chunk size for batch operations
     */
    public static function getChunkSize(): int
    {
        return self::get('performance.chunk_size', 1000);
    }

    /**
     * Check if logging is enabled
     */
    public static function loggingEnabled(): bool
    {
        return self::get('logging.enabled', true);
    }

    /**
     * Get log channel
     */
    public static function getLogChannel(): string
    {
        return self::get('logging.channel', 'stack');
    }

    /**
     * Get log level
     */
    public static function getLogLevel(): string
    {
        return self::get('logging.level', 'info');
    }

    /**
     * Check if queries should be logged
     */
    public static function logQueries(): bool
    {
        return self::get('logging.log_queries', false);
    }

    /**
     * Get destructive operations list
     */
    public static function getDestructiveOperations(): array
    {
        return self::get('risk.destructive_operations', [
            'dropTable',
            'dropColumn',
            'dropForeign',
            'dropIndex',
            'truncate',
        ]);
    }

    /**
     * Get risky operations list
     */
    public static function getRiskyOperations(): array
    {
        return self::get('risk.risky_operations', [
            'renameTable',
            'renameColumn',
            'modifyColumn',
            'changeColumn',
        ]);
    }

    /**
     * Get safe operations list
     */
    public static function getSafeOperations(): array
    {
        return self::get('risk.safe_operations', [
            'create',
            'createTable',
            'addColumn',
            'index',
        ]);
    }

    /**
     * Check if auto-fix drift is enabled
     */
    public static function autoFixDrift(): bool
    {
        return self::get('drift.auto_fix', false);
    }

    /**
     * Check if drift detection on migrate is enabled
     */
    public static function checkOnMigrate(): bool
    {
        return self::get('drift.check_on_migrate', true);
    }

    /**
     * Get risk threshold
     */
    public static function getRiskThreshold(): string
    {
        return self::get('safety.risk_threshold', 'high');
    }

    /**
     * Get backup prefix
     */
    public static function getBackupPrefix(): string
    {
        return self::get('backup.prefix', 'smart_migration_backups');
    }

    /**
     * Get backup location
     */
    public static function getBackupLocation(): string
    {
        return self::get('backup.location', storage_path('backups/migrations'));
    }

    /**
     * Check if backups should be compressed
     */
    public static function compressBackups(): bool
    {
        return self::shouldCompressBackups();
    }

    /**
     * Check if old data should be archived
     */
    public static function archiveOldData(): bool
    {
        return self::get('archive.enabled', true);
    }

    /**
     * Check if progress should be shown
     */
    public static function showProgress(): bool
    {
        return self::get('display.progress', true);
    }

    /**
     * Check if detailed output is enabled
     */
    public static function detailedOutput(): bool
    {
        return self::get('display.detailed', true);
    }

    /**
     * Check if colored output is enabled
     */
    public static function coloredOutput(): bool
    {
        return self::colorsEnabled();
    }

    /**
     * Get default verbosity
     */
    public static function getDefaultVerbosity(): string
    {
        return self::get('display.verbosity', 'planning');
    }

    /**
     * Get driver configuration
     */
    public static function getDriverConfig(string $driver): array
    {
        return self::get("drivers.{$driver}", []);
    }

    /**
     * Get snapshot location
     */
    public static function getSnapshotLocation(): string
    {
        return self::get('snapshots.location', storage_path('migrations/snapshots'));
    }

    /**
     * Check if snapshots should be compressed
     */
    public static function compressSnapshots(): bool
    {
        return self::get('snapshots.compress', true);
    }

    /**
     * Check if data should be included in snapshots
     */
    public static function includeDataInSnapshots(): bool
    {
        return self::get('snapshots.include_data', false);
    }

    /**
     * Get ignored tables for drift detection
     */
    public static function getIgnoredTables(): array
    {
        return self::getDriftIgnoredTables();
    }

    /**
     * Get notification events
     */
    public static function getNotificationEvents(): array
    {
        return self::get('notifications.events', [
            'migration_failed',
            'high_risk_operation',
            'drift_detected',
            'rollback_completed',
            'archive_cleanup',
        ]);
    }

    /**
     * Get all configuration
     */
    public static function all(): array
    {
        return config('smart-migration', []);
    }
}
