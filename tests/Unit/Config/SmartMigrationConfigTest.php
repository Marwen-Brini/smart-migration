<?php

namespace Flux\Tests\Unit\Config;

use Flux\Config\SmartMigrationConfig;
use Flux\Tests\TestCase;

class SmartMigrationConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set default config values
        config(['smart-migration' => require __DIR__ . '/../../../config/smart-migration.php']);
    }

    public function test_safety_configuration_methods()
    {
        // Test with default values
        $this->assertTrue(SmartMigrationConfig::autoBackupEnabled());
        $this->assertTrue(SmartMigrationConfig::safeRollbackEnabled());
        $this->assertTrue(SmartMigrationConfig::requireConfirmation());
        $this->assertEquals('high', SmartMigrationConfig::getRiskThreshold());

        // Test with custom values
        config(['smart-migration.safety.auto_backup' => false]);
        config(['smart-migration.safety.safe_rollback' => false]);
        config(['smart-migration.safety.require_confirmation' => false]);
        config(['smart-migration.safety.risk_threshold' => 'medium']);

        $this->assertFalse(SmartMigrationConfig::autoBackupEnabled());
        $this->assertFalse(SmartMigrationConfig::safeRollbackEnabled());
        $this->assertFalse(SmartMigrationConfig::requireConfirmation());
        $this->assertEquals('medium', SmartMigrationConfig::getRiskThreshold());
    }

    public function test_backup_configuration_methods()
    {
        $this->assertEquals('smart_migration_backups', SmartMigrationConfig::getBackupPrefix());
        $this->assertEquals('sql', SmartMigrationConfig::getBackupFormat());
        $this->assertEquals(storage_path('backups/migrations'), SmartMigrationConfig::getBackupLocation());
        $this->assertTrue(SmartMigrationConfig::compressBackups());
        $this->assertEquals(30, SmartMigrationConfig::getBackupRetentionDays());
    }

    public function test_archive_configuration_methods()
    {
        $this->assertTrue(SmartMigrationConfig::archiveOldData());
        $this->assertEquals('_archived_', SmartMigrationConfig::getArchiveTablePrefix());
        $this->assertEquals('__backup_', SmartMigrationConfig::getArchiveColumnPrefix());
        $this->assertEquals(7, SmartMigrationConfig::getArchiveRetentionDays());
        $this->assertFalse(SmartMigrationConfig::autoCleanupEnabled());
        $this->assertEquals('0 2 * * *', SmartMigrationConfig::getCleanupSchedule());
    }

    public function test_risk_configuration_methods()
    {
        $destructive = SmartMigrationConfig::getDestructiveOperations();
        $this->assertIsArray($destructive);
        $this->assertContains('dropTable', $destructive);
        $this->assertContains('dropColumn', $destructive);

        $risky = SmartMigrationConfig::getRiskyOperations();
        $this->assertIsArray($risky);
        $this->assertContains('renameTable', $risky);

        $safe = SmartMigrationConfig::getSafeOperations();
        $this->assertIsArray($safe);
        $this->assertContains('create', $safe);
    }

    public function test_display_configuration_methods()
    {
        $this->assertTrue(SmartMigrationConfig::showProgress());
        $this->assertTrue(SmartMigrationConfig::detailedOutput());
        $this->assertTrue(SmartMigrationConfig::coloredOutput());
        $this->assertEquals('normal', SmartMigrationConfig::getDefaultVerbosity());
    }

    public function test_database_driver_configuration()
    {
        // Test that driver config method works
        $mysqlConfig = SmartMigrationConfig::getDriverConfig('mysql');
        $this->assertIsArray($mysqlConfig);

        $pgsqlConfig = SmartMigrationConfig::getDriverConfig('pgsql');
        $this->assertIsArray($pgsqlConfig);

        $sqliteConfig = SmartMigrationConfig::getDriverConfig('sqlite');
        $this->assertIsArray($sqliteConfig);

        // Test non-existent driver
        $nonExistentConfig = SmartMigrationConfig::getDriverConfig('mongodb');
        $this->assertIsArray($nonExistentConfig);
        $this->assertEmpty($nonExistentConfig);
    }

    public function test_snapshot_configuration_methods()
    {
        $this->assertFalse(SmartMigrationConfig::autoSnapshotEnabled());
        $this->assertEquals(storage_path('migrations/snapshots'), SmartMigrationConfig::getSnapshotLocation());
        $this->assertEquals('json', SmartMigrationConfig::getSnapshotFormat());
        $this->assertTrue(SmartMigrationConfig::compressSnapshots());
        $this->assertEquals(10, SmartMigrationConfig::getMaxSnapshots());
        $this->assertFalse(SmartMigrationConfig::includeDataInSnapshots());
    }

    public function test_drift_detection_configuration()
    {
        $this->assertTrue(SmartMigrationConfig::checkOnMigrate());
        $this->assertFalse(SmartMigrationConfig::autoFixDrift());

        $ignored = SmartMigrationConfig::getIgnoredTables();
        $this->assertIsArray($ignored);
        // Check if the ignored tables array contains expected values or is empty
        if (!empty($ignored)) {
            $this->assertContains('migrations', $ignored);
        }
    }

    public function test_logging_configuration()
    {
        $this->assertTrue(SmartMigrationConfig::loggingEnabled());
        $this->assertEquals('stack', SmartMigrationConfig::getLogChannel());
        $this->assertEquals('info', SmartMigrationConfig::getLogLevel());
    }

    public function test_notification_configuration()
    {
        $this->assertFalse(SmartMigrationConfig::notificationsEnabled());
        $this->assertEquals(['mail'], SmartMigrationConfig::getNotificationChannels());

        $events = SmartMigrationConfig::getNotificationEvents();
        $this->assertIsArray($events);

        // Since we load the actual config file, events are stored as boolean values
        // The method should return the actual config structure from the config file
        $this->assertArrayHasKey('migration_failed', $events);
        $this->assertArrayHasKey('migration_completed', $events);
        $this->assertArrayHasKey('drift_detected', $events);

        // Test specific event values
        $this->assertTrue($events['migration_failed']);
        $this->assertTrue($events['migration_completed']);
        $this->assertTrue($events['drift_detected']);
    }

    public function test_get_method_with_nested_keys()
    {
        $value = SmartMigrationConfig::get('safety.auto_backup');
        $this->assertTrue($value);

        // Test database driver config if it exists
        $mysqlConfig = SmartMigrationConfig::getDriverConfig('mysql');
        if (!empty($mysqlConfig) && isset($mysqlConfig['quote_identifier'])) {
            $this->assertEquals('`', $mysqlConfig['quote_identifier']);
        }

        // Test with default value
        $value = SmartMigrationConfig::get('non.existent.key', 'default');
        $this->assertEquals('default', $value);
    }

    public function test_all_method_returns_full_config()
    {
        $config = SmartMigrationConfig::all();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('safety', $config);
        $this->assertArrayHasKey('backup', $config);
        $this->assertArrayHasKey('archive', $config);
        $this->assertArrayHasKey('risk', $config);
        $this->assertArrayHasKey('display', $config);
        $this->assertArrayHasKey('drivers', $config);
        $this->assertArrayHasKey('snapshots', $config);
        $this->assertArrayHasKey('drift', $config);
    }
}