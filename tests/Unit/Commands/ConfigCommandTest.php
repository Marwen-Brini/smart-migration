<?php

use Flux\Commands\ConfigCommand;

beforeEach(function () {
    $this->command = Mockery::mock(ConfigCommand::class)->makePartial();

    // Mock the output interface to avoid null reference errors
    $output = Mockery::mock('\Symfony\Component\Console\Output\OutputInterface');
    $output->shouldReceive('writeln')->byDefault();

    // Set the output property directly
    $reflection = new \ReflectionClass($this->command);
    $outputProperty = $reflection->getParentClass()->getProperty('output');
    $outputProperty->setAccessible(true);
    $outputProperty->setValue($this->command, $output);
});

afterEach(function () {
    Mockery::close();
});

describe('handle method', function () {
    it('displays complete configuration information', function () {
        // Set up configuration values
        config([
            'smart-migration.safety.auto_backup' => true,
            'smart-migration.safety.safe_rollback' => true,
            'smart-migration.safety.require_confirmation' => false,
            'smart-migration.display.colors' => true,
            'smart-migration.display.emojis' => false,
            'smart-migration.display.progress_bars' => true,
            'smart-migration.display.show_sql' => false,
            'smart-migration.display.show_timing' => true,
            'smart-migration.archive.table_prefix' => 'archived_',
            'smart-migration.archive.column_prefix' => 'archived_',
            'smart-migration.archive.include_timestamp' => true,
            'smart-migration.archive.retention_days' => 30,
            'smart-migration.cleanup.auto_enabled' => true,
            'smart-migration.drivers.mysql' => true,
            'smart-migration.drivers.pgsql' => true,
            'smart-migration.drivers.sqlite' => false,
            'smart-migration.drivers.sqlsrv' => false,
            'smart-migration.snapshots.path' => 'database/snapshots',
            'smart-migration.snapshots.format' => 'json',
            'smart-migration.snapshots.auto_enabled' => false,
            'smart-migration.drift.auto_detect' => true,
            'smart-migration.drift.check_before_migrate' => true,
            'smart-migration.drift.warn_only' => false,
        ]);

        // Mock console output expectations
        $this->command->shouldReceive('info')->with('Smart Migration Configuration');
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════');
        $this->command->shouldReceive('newLine')->times(7);

        // Safety Features section
        $this->command->shouldReceive('info')->with('Safety Features:');
        $this->command->shouldReceive('line')->with('  Auto Backup: Enabled');
        $this->command->shouldReceive('line')->with('  Safe Rollback: Enabled');
        $this->command->shouldReceive('line')->with('  Require Confirmation: No');

        // Display Settings section
        $this->command->shouldReceive('info')->with('Display Settings:');
        $this->command->shouldReceive('line')->with('  Colors: Enabled');
        $this->command->shouldReceive('line')->with('  Emojis: Disabled');
        $this->command->shouldReceive('line')->with('  Progress Bars: Enabled');
        $this->command->shouldReceive('line')->with('  Show SQL: No');
        $this->command->shouldReceive('line')->with('  Show Timing: Yes');

        // Archive Settings section
        $this->command->shouldReceive('info')->with('Archive Settings:');
        $this->command->shouldReceive('line')->with('  Table Prefix: archived_');
        $this->command->shouldReceive('line')->with('  Column Prefix: archived_');
        $this->command->shouldReceive('line')->with('  Include Timestamp: Yes');
        $this->command->shouldReceive('line')->with('  Retention Days: 30');
        $this->command->shouldReceive('line')->with('  Auto Cleanup: Enabled');

        // Database Drivers section
        $this->command->shouldReceive('info')->with('Database Drivers:');
        $this->command->shouldReceive('line')->with('  Mysql: Enabled');
        $this->command->shouldReceive('line')->with('  Pgsql: Enabled');
        $this->command->shouldReceive('line')->with('  Sqlite: Disabled');
        $this->command->shouldReceive('line')->with('  Sqlsrv: Disabled');

        // Snapshot Settings section
        $this->command->shouldReceive('info')->with('Snapshot Settings:');
        $this->command->shouldReceive('line')->with('  Path: snapshots');
        $this->command->shouldReceive('line')->with('  Format: json');
        $this->command->shouldReceive('line')->with('  Auto Snapshot: Disabled');

        // Drift Detection section
        $this->command->shouldReceive('info')->with('Drift Detection:');
        $this->command->shouldReceive('line')->with('  Auto Detect: Enabled');
        $this->command->shouldReceive('line')->with('  Check Before Migrate: Yes');
        $this->command->shouldReceive('line')->with('  Warn Only: No');

        $result = $this->command->handle();

        expect($result)->toBe(ConfigCommand::SUCCESS);
    });

    it('displays configuration when all features are disabled', function () {
        // Set up configuration values with disabled features
        config([
            'smart-migration.safety.auto_backup' => false,
            'smart-migration.safety.safe_rollback' => false,
            'smart-migration.safety.require_confirmation' => true,
            'smart-migration.display.colors' => false,
            'smart-migration.display.emojis' => false,
            'smart-migration.display.progress_bars' => false,
            'smart-migration.display.show_sql' => false,
            'smart-migration.display.show_timing' => false,
            'smart-migration.archive.table_prefix' => 'arch_',
            'smart-migration.archive.column_prefix' => 'arch_',
            'smart-migration.archive.include_timestamp' => false,
            'smart-migration.archive.retention_days' => 0,
            'smart-migration.cleanup.auto_enabled' => false,
            'smart-migration.drivers.mysql' => false,
            'smart-migration.drivers.pgsql' => false,
            'smart-migration.drivers.sqlite' => false,
            'smart-migration.drivers.sqlsrv' => false,
            'smart-migration.snapshots.path' => 'custom/path',
            'smart-migration.snapshots.format' => 'yaml',
            'smart-migration.snapshots.auto_enabled' => false,
            'smart-migration.drift.auto_detect' => false,
            'smart-migration.drift.check_before_migrate' => false,
            'smart-migration.drift.warn_only' => true,
        ]);

        // Mock console output expectations
        $this->command->shouldReceive('info')->with('Smart Migration Configuration');
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════');
        $this->command->shouldReceive('newLine')->times(7);

        // Safety Features section
        $this->command->shouldReceive('info')->with('Safety Features:');
        $this->command->shouldReceive('line')->with('  Auto Backup: Disabled');
        $this->command->shouldReceive('line')->with('  Safe Rollback: Disabled');
        $this->command->shouldReceive('line')->with('  Require Confirmation: Yes');

        // Display Settings section
        $this->command->shouldReceive('info')->with('Display Settings:');
        $this->command->shouldReceive('line')->with('  Colors: Disabled');
        $this->command->shouldReceive('line')->with('  Emojis: Disabled');
        $this->command->shouldReceive('line')->with('  Progress Bars: Disabled');
        $this->command->shouldReceive('line')->with('  Show SQL: No');
        $this->command->shouldReceive('line')->with('  Show Timing: No');

        // Archive Settings section
        $this->command->shouldReceive('info')->with('Archive Settings:');
        $this->command->shouldReceive('line')->with('  Table Prefix: arch_');
        $this->command->shouldReceive('line')->with('  Column Prefix: arch_');
        $this->command->shouldReceive('line')->with('  Include Timestamp: No');
        $this->command->shouldReceive('line')->with('  Retention Days: 0');
        $this->command->shouldReceive('line')->with('  Auto Cleanup: Disabled');

        // Database Drivers section
        $this->command->shouldReceive('info')->with('Database Drivers:');
        $this->command->shouldReceive('line')->with('  Mysql: Disabled');
        $this->command->shouldReceive('line')->with('  Pgsql: Disabled');
        $this->command->shouldReceive('line')->with('  Sqlite: Disabled');
        $this->command->shouldReceive('line')->with('  Sqlsrv: Disabled');

        // Snapshot Settings section
        $this->command->shouldReceive('info')->with('Snapshot Settings:');
        $this->command->shouldReceive('line')->with('  Path: database/custom/path');
        $this->command->shouldReceive('line')->with('  Format: yaml');
        $this->command->shouldReceive('line')->with('  Auto Snapshot: Disabled');

        // Drift Detection section
        $this->command->shouldReceive('info')->with('Drift Detection:');
        $this->command->shouldReceive('line')->with('  Auto Detect: Disabled');
        $this->command->shouldReceive('line')->with('  Check Before Migrate: No');
        $this->command->shouldReceive('line')->with('  Warn Only: Yes');

        $result = $this->command->handle();

        expect($result)->toBe(ConfigCommand::SUCCESS);
    });

    it('displays mixed configuration with some enabled and some disabled features', function () {
        // Set up mixed configuration values
        config([
            'smart-migration.safety.auto_backup' => true,
            'smart-migration.safety.safe_rollback' => false,
            'smart-migration.safety.require_confirmation' => true,
            'smart-migration.display.colors' => true,
            'smart-migration.display.emojis' => true,
            'smart-migration.display.progress_bars' => false,
            'smart-migration.display.show_sql' => true,
            'smart-migration.display.show_timing' => false,
            'smart-migration.archive.table_prefix' => 'backup_',
            'smart-migration.archive.column_prefix' => 'old_',
            'smart-migration.archive.include_timestamp' => false,
            'smart-migration.archive.retention_days' => 90,
            'smart-migration.cleanup.auto_enabled' => true,
            'smart-migration.drivers.mysql' => true,
            'smart-migration.drivers.pgsql' => false,
            'smart-migration.drivers.sqlite' => true,
            'smart-migration.drivers.sqlsrv' => false,
            'smart-migration.snapshots.path' => 'backups/snapshots',
            'smart-migration.snapshots.format' => 'json',
            'smart-migration.snapshots.auto_enabled' => true,
            'smart-migration.drift.auto_detect' => false,
            'smart-migration.drift.check_before_migrate' => true,
            'smart-migration.drift.warn_only' => true,
        ]);

        // Mock console output expectations
        $this->command->shouldReceive('info')->with('Smart Migration Configuration');
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════');
        $this->command->shouldReceive('newLine')->times(7);

        // Safety Features section
        $this->command->shouldReceive('info')->with('Safety Features:');
        $this->command->shouldReceive('line')->with('  Auto Backup: Enabled');
        $this->command->shouldReceive('line')->with('  Safe Rollback: Disabled');
        $this->command->shouldReceive('line')->with('  Require Confirmation: Yes');

        // Display Settings section
        $this->command->shouldReceive('info')->with('Display Settings:');
        $this->command->shouldReceive('line')->with('  Colors: Enabled');
        $this->command->shouldReceive('line')->with('  Emojis: Enabled');
        $this->command->shouldReceive('line')->with('  Progress Bars: Disabled');
        $this->command->shouldReceive('line')->with('  Show SQL: Yes');
        $this->command->shouldReceive('line')->with('  Show Timing: No');

        // Archive Settings section
        $this->command->shouldReceive('info')->with('Archive Settings:');
        $this->command->shouldReceive('line')->with('  Table Prefix: backup_');
        $this->command->shouldReceive('line')->with('  Column Prefix: old_');
        $this->command->shouldReceive('line')->with('  Include Timestamp: No');
        $this->command->shouldReceive('line')->with('  Retention Days: 90');
        $this->command->shouldReceive('line')->with('  Auto Cleanup: Enabled');

        // Database Drivers section
        $this->command->shouldReceive('info')->with('Database Drivers:');
        $this->command->shouldReceive('line')->with('  Mysql: Enabled');
        $this->command->shouldReceive('line')->with('  Pgsql: Disabled');
        $this->command->shouldReceive('line')->with('  Sqlite: Enabled');
        $this->command->shouldReceive('line')->with('  Sqlsrv: Disabled');

        // Snapshot Settings section
        $this->command->shouldReceive('info')->with('Snapshot Settings:');
        $this->command->shouldReceive('line')->with('  Path: database/backups/snapshots');
        $this->command->shouldReceive('line')->with('  Format: json');
        $this->command->shouldReceive('line')->with('  Auto Snapshot: Enabled');

        // Drift Detection section
        $this->command->shouldReceive('info')->with('Drift Detection:');
        $this->command->shouldReceive('line')->with('  Auto Detect: Disabled');
        $this->command->shouldReceive('line')->with('  Check Before Migrate: Yes');
        $this->command->shouldReceive('line')->with('  Warn Only: Yes');

        $result = $this->command->handle();

        expect($result)->toBe(ConfigCommand::SUCCESS);
    });

    it('handles numeric values in archive settings correctly', function () {
        // Set up configuration values with numeric archive settings
        config([
            'smart-migration.safety.auto_backup' => true,
            'smart-migration.safety.safe_rollback' => true,
            'smart-migration.safety.require_confirmation' => false,
            'smart-migration.display.colors' => true,
            'smart-migration.display.emojis' => true,
            'smart-migration.display.progress_bars' => true,
            'smart-migration.display.show_sql' => true,
            'smart-migration.display.show_timing' => true,
            'smart-migration.archive.table_prefix' => 'archived_',
            'smart-migration.archive.column_prefix' => 'archived_',
            'smart-migration.archive.include_timestamp' => true,
            'smart-migration.archive.retention_days' => 365,
            'smart-migration.cleanup.auto_enabled' => true,
            'smart-migration.drivers.mysql' => true,
            'smart-migration.drivers.pgsql' => true,
            'smart-migration.drivers.sqlite' => true,
            'smart-migration.drivers.sqlsrv' => true,
            'smart-migration.snapshots.path' => 'snapshots',
            'smart-migration.snapshots.format' => 'json',
            'smart-migration.snapshots.auto_enabled' => true,
            'smart-migration.drift.auto_detect' => true,
            'smart-migration.drift.check_before_migrate' => true,
            'smart-migration.drift.warn_only' => false,
        ]);

        // Key expectation for this test
        $this->command->shouldReceive('line')->with('  Retention Days: 365');

        // Other expected calls
        $this->command->shouldReceive('info')->times(7);
        $this->command->shouldReceive('comment')->once();
        $this->command->shouldReceive('newLine')->times(7);
        $this->command->shouldReceive('line')->atLeast(20); // Allow for all the other line calls

        $result = $this->command->handle();

        expect($result)->toBe(ConfigCommand::SUCCESS);
    });

    it('handles different snapshot formats correctly', function () {
        // Set up configuration values with different snapshot format
        config([
            'smart-migration.safety.auto_backup' => true,
            'smart-migration.safety.safe_rollback' => true,
            'smart-migration.safety.require_confirmation' => false,
            'smart-migration.display.colors' => true,
            'smart-migration.display.emojis' => true,
            'smart-migration.display.progress_bars' => true,
            'smart-migration.display.show_sql' => true,
            'smart-migration.display.show_timing' => true,
            'smart-migration.archive.table_prefix' => 'archived_',
            'smart-migration.archive.column_prefix' => 'archived_',
            'smart-migration.archive.include_timestamp' => true,
            'smart-migration.archive.retention_days' => 30,
            'smart-migration.cleanup.auto_enabled' => true,
            'smart-migration.drivers.mysql' => true,
            'smart-migration.drivers.pgsql' => true,
            'smart-migration.drivers.sqlite' => true,
            'smart-migration.drivers.sqlsrv' => true,
            'smart-migration.snapshots.path' => 'snapshots',
            'smart-migration.snapshots.format' => 'yaml',
            'smart-migration.snapshots.auto_enabled' => true,
            'smart-migration.drift.auto_detect' => true,
            'smart-migration.drift.check_before_migrate' => true,
            'smart-migration.drift.warn_only' => false,
        ]);

        // Key expectation for this test
        $this->command->shouldReceive('line')->with('  Format: yaml');

        // Other expected calls
        $this->command->shouldReceive('info')->times(7);
        $this->command->shouldReceive('comment')->once();
        $this->command->shouldReceive('newLine')->times(7);
        $this->command->shouldReceive('line')->atLeast(20);

        $result = $this->command->handle();

        expect($result)->toBe(ConfigCommand::SUCCESS);
    });
});
