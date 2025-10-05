<?php

use Flux\Config\SmartMigrationConfig;

beforeEach(function () {
    // Set default config values
    config(['smart-migration' => require __DIR__.'/../../../config/smart-migration.php']);
});

describe('safety configuration methods', function () {
    it('returns correct default values', function () {
        expect(SmartMigrationConfig::autoBackupEnabled())->toBeTrue();
        expect(SmartMigrationConfig::safeRollbackEnabled())->toBeTrue();
        expect(SmartMigrationConfig::requireConfirmation())->toBeTrue();
        expect(SmartMigrationConfig::getRiskThreshold())->toBe('high');
        expect(SmartMigrationConfig::allowDestructiveInProduction())->toBeFalse();
    });

    it('returns correct custom values', function () {
        config([
            'smart-migration.safety.auto_backup' => false,
            'smart-migration.safety.safe_rollback' => false,
            'smart-migration.safety.require_confirmation' => false,
            'smart-migration.safety.risk_threshold' => 'medium',
            'smart-migration.safety.allow_destructive_in_production' => true,
        ]);

        expect(SmartMigrationConfig::autoBackupEnabled())->toBeFalse();
        expect(SmartMigrationConfig::safeRollbackEnabled())->toBeFalse();
        expect(SmartMigrationConfig::requireConfirmation())->toBeFalse();
        expect(SmartMigrationConfig::getRiskThreshold())->toBe('medium');
        expect(SmartMigrationConfig::allowDestructiveInProduction())->toBeTrue();
    });
});

describe('backup configuration methods', function () {
    it('returns correct backup configuration', function () {
        expect(SmartMigrationConfig::getBackupPrefix())->toBe('smart_migration_backups');
        expect(SmartMigrationConfig::getBackupFormat())->toBe('sql');
        expect(SmartMigrationConfig::getBackupLocation())->toBe(storage_path('backups/migrations'));
        expect(SmartMigrationConfig::compressBackups())->toBeTrue();
        expect(SmartMigrationConfig::getBackupRetentionDays())->toBe(30);
        expect(SmartMigrationConfig::getBackupPath())->toBe('smart-migration-backups');
    });

    it('returns custom backup path', function () {
        config(['smart-migration.backup.path' => 'custom/backup/path']);
        expect(SmartMigrationConfig::getBackupPath())->toBe('custom/backup/path');
    });
});

describe('archive configuration methods', function () {
    it('returns correct archive configuration', function () {
        expect(SmartMigrationConfig::archiveOldData())->toBeTrue();
        expect(SmartMigrationConfig::getArchiveTablePrefix())->toBe('_archived_');
        expect(SmartMigrationConfig::getArchiveColumnPrefix())->toBe('__backup_');
        expect(SmartMigrationConfig::getArchiveRetentionDays())->toBe(7);
        expect(SmartMigrationConfig::autoCleanupEnabled())->toBeFalse();
        expect(SmartMigrationConfig::getCleanupSchedule())->toBe('0 2 * * *');
        expect(SmartMigrationConfig::includeTimestampInArchive())->toBeTrue();
    });

    it('returns custom timestamp setting', function () {
        config(['smart-migration.archive.include_timestamp' => false]);
        expect(SmartMigrationConfig::includeTimestampInArchive())->toBeFalse();
    });
});

describe('risk configuration methods', function () {
    it('returns destructive operations', function () {
        $destructive = SmartMigrationConfig::getDestructiveOperations();
        expect($destructive)->toBeArray();
        expect($destructive)->toContain('dropTable');
        expect($destructive)->toContain('dropColumn');
    });

    it('returns risky operations', function () {
        $risky = SmartMigrationConfig::getRiskyOperations();
        expect($risky)->toBeArray();
        expect($risky)->toContain('renameTable');
    });

    it('returns safe operations', function () {
        $safe = SmartMigrationConfig::getSafeOperations();
        expect($safe)->toBeArray();
        expect($safe)->toContain('create');
    });

    it('returns danger blocking settings', function () {
        expect(SmartMigrationConfig::blockDangerInProduction())->toBeFalse();

        config(['smart-migration.risk.block_danger_in_production' => true]);
        expect(SmartMigrationConfig::blockDangerInProduction())->toBeTrue();
    });

    it('returns operation risk levels', function () {
        expect(SmartMigrationConfig::getOperationRisk('dropTable'))->toBe('warning');
        expect(SmartMigrationConfig::getOperationRisk('unknownOperation'))->toBe('warning');

        config(['smart-migration.risk.operations.dropTable' => 'critical']);
        expect(SmartMigrationConfig::getOperationRisk('dropTable'))->toBe('critical');
    });
});

describe('display configuration methods', function () {
    it('returns correct display settings', function () {
        expect(SmartMigrationConfig::showProgress())->toBeTrue();
        expect(SmartMigrationConfig::detailedOutput())->toBeTrue();
        expect(SmartMigrationConfig::coloredOutput())->toBeTrue();
        expect(SmartMigrationConfig::getDefaultVerbosity())->toBe('normal');
        expect(SmartMigrationConfig::getVerbosity())->toBe('normal');
    });

    it('returns custom verbosity setting', function () {
        config(['smart-migration.display.verbosity' => 'verbose']);
        expect(SmartMigrationConfig::getVerbosity())->toBe('verbose');
    });

    it('returns emoji and progress bar settings', function () {
        expect(SmartMigrationConfig::emojisEnabled())->toBeTrue();
        expect(SmartMigrationConfig::progressBarsEnabled())->toBeTrue();

        config([
            'smart-migration.display.emojis' => false,
            'smart-migration.display.progress_bars' => false,
        ]);

        expect(SmartMigrationConfig::emojisEnabled())->toBeFalse();
        expect(SmartMigrationConfig::progressBarsEnabled())->toBeFalse();
    });

    it('returns SQL and timing display settings', function () {
        expect(SmartMigrationConfig::showSql())->toBeTrue();
        expect(SmartMigrationConfig::showTiming())->toBeTrue();

        config([
            'smart-migration.display.show_sql' => false,
            'smart-migration.display.show_timing' => false,
        ]);

        expect(SmartMigrationConfig::showSql())->toBeFalse();
        expect(SmartMigrationConfig::showTiming())->toBeFalse();
    });
});

describe('database driver configuration', function () {
    it('returns driver config for existing drivers', function () {
        expect(SmartMigrationConfig::getDriverConfig('mysql'))->toBeArray();
        expect(SmartMigrationConfig::getDriverConfig('pgsql'))->toBeArray();
        expect(SmartMigrationConfig::getDriverConfig('sqlite'))->toBeArray();
    });

    it('returns empty array for non-existent driver', function () {
        $config = SmartMigrationConfig::getDriverConfig('mongodb');
        expect($config)->toBeArray();
        expect($config)->toBeEmpty();
    });

    it('returns driver settings', function () {
        $settings = SmartMigrationConfig::getDriverSettings('mysql');
        expect($settings)->toBeArray();

        $settings = SmartMigrationConfig::getDriverSettings('nonexistent');
        expect($settings)->toBeArray();
        expect($settings)->toBeEmpty();
    });

    it('checks if driver is enabled', function () {
        expect(SmartMigrationConfig::isDriverEnabled('mysql'))->toBeTrue(); // mysql is enabled by default
        expect(SmartMigrationConfig::isDriverEnabled('nonexistent'))->toBeFalse();

        config(['smart-migration.drivers.mysql.enabled' => false]);
        expect(SmartMigrationConfig::isDriverEnabled('mysql'))->toBeFalse();
    });
});

describe('snapshot configuration methods', function () {
    it('returns correct snapshot configuration', function () {
        expect(SmartMigrationConfig::autoSnapshotEnabled())->toBeFalse();
        expect(SmartMigrationConfig::getSnapshotLocation())->toBe(storage_path('migrations/snapshots'));
        expect(SmartMigrationConfig::getSnapshotFormat())->toBe('json');
        expect(SmartMigrationConfig::compressSnapshots())->toBeTrue();
        expect(SmartMigrationConfig::getMaxSnapshots())->toBe(10);
        expect(SmartMigrationConfig::includeDataInSnapshots())->toBeFalse();
        expect(SmartMigrationConfig::getSnapshotPath())->toBe('snapshots');
    });

    it('returns custom snapshot path', function () {
        config(['smart-migration.snapshots.path' => 'custom/snapshots']);
        expect(SmartMigrationConfig::getSnapshotPath())->toBe('custom/snapshots');
    });
});

describe('drift detection configuration', function () {
    it('returns correct drift detection settings', function () {
        expect(SmartMigrationConfig::checkOnMigrate())->toBeTrue();
        expect(SmartMigrationConfig::autoFixDrift())->toBeFalse();

        $ignored = SmartMigrationConfig::getIgnoredTables();
        expect($ignored)->toBeArray();
        if (! empty($ignored)) {
            expect($ignored)->toContain('migrations');
        }
    });

    it('returns additional drift detection methods', function () {
        expect(SmartMigrationConfig::autoDriftDetection())->toBeTrue();
        expect(SmartMigrationConfig::checkDriftBeforeMigrate())->toBeTrue();
        expect(SmartMigrationConfig::driftWarnOnly())->toBeTrue();
        expect(SmartMigrationConfig::getDriftIgnoredColumns())->toBeArray();

        config([
            'smart-migration.drift.auto_detect' => false,
            'smart-migration.drift.check_before_migrate' => false,
            'smart-migration.drift.warn_only' => false,
            'smart-migration.drift.ignored_columns' => ['created_at', 'updated_at'],
        ]);

        expect(SmartMigrationConfig::autoDriftDetection())->toBeFalse();
        expect(SmartMigrationConfig::checkDriftBeforeMigrate())->toBeFalse();
        expect(SmartMigrationConfig::driftWarnOnly())->toBeFalse();
        expect(SmartMigrationConfig::getDriftIgnoredColumns())->toBe(['created_at', 'updated_at']);
    });
});

describe('logging configuration', function () {
    it('returns correct logging settings', function () {
        expect(SmartMigrationConfig::loggingEnabled())->toBeTrue();
        expect(SmartMigrationConfig::getLogChannel())->toBe('stack');
        expect(SmartMigrationConfig::getLogLevel())->toBe('info');
        expect(SmartMigrationConfig::logQueries())->toBeFalse();
    });

    it('returns custom query logging setting', function () {
        config(['smart-migration.logging.log_queries' => true]);
        expect(SmartMigrationConfig::logQueries())->toBeTrue();
    });
});

describe('performance configuration', function () {
    it('returns correct performance settings', function () {
        expect(SmartMigrationConfig::getChunkSize())->toBe(1000);
    });

    it('returns custom chunk size', function () {
        config(['smart-migration.performance.chunk_size' => 500]);
        expect(SmartMigrationConfig::getChunkSize())->toBe(500);
    });
});

describe('notification configuration', function () {
    it('returns correct notification settings', function () {
        expect(SmartMigrationConfig::notificationsEnabled())->toBeFalse();
        expect(SmartMigrationConfig::getNotificationChannels())->toBe(['mail']);

        $events = SmartMigrationConfig::getNotificationEvents();
        expect($events)->toBeArray();
        expect($events)->toHaveKey('migration_failed');
        expect($events)->toHaveKey('migration_completed');
        expect($events)->toHaveKey('drift_detected');
        expect($events['migration_failed'])->toBeTrue();
        expect($events['migration_completed'])->toBeTrue();
        expect($events['drift_detected'])->toBeTrue();
    });

    it('checks specific notification events', function () {
        expect(SmartMigrationConfig::shouldNotifyEvent('migration_failed'))->toBeTrue(); // migration_failed is enabled by default
        expect(SmartMigrationConfig::shouldNotifyEvent('unknown_event'))->toBeFalse();

        config(['smart-migration.notifications.events.migration_failed' => false]);
        expect(SmartMigrationConfig::shouldNotifyEvent('migration_failed'))->toBeFalse();
    });
});

describe('get method', function () {
    it('retrieves nested config values', function () {
        expect(SmartMigrationConfig::get('safety.auto_backup'))->toBeTrue();
    });

    it('returns default value for non-existent key', function () {
        expect(SmartMigrationConfig::get('non.existent.key', 'default'))->toBe('default');
    });

    it('retrieves driver config values', function () {
        $mysqlConfig = SmartMigrationConfig::getDriverConfig('mysql');
        expect($mysqlConfig)->toBeArray();
    });
});

describe('all method', function () {
    it('returns full configuration array', function () {
        $config = SmartMigrationConfig::all();

        expect($config)->toBeArray();
        expect($config)->toHaveKey('safety');
        expect($config)->toHaveKey('backup');
        expect($config)->toHaveKey('archive');
        expect($config)->toHaveKey('risk');
        expect($config)->toHaveKey('display');
        expect($config)->toHaveKey('drivers');
        expect($config)->toHaveKey('snapshots');
        expect($config)->toHaveKey('drift');
    });
});
