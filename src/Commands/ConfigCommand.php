<?php

namespace Flux\Commands;

use Flux\Config\SmartMigrationConfig;

class ConfigCommand extends BaseSmartMigrationCommand
{
    protected $signature = 'migrate:config {--show : Display current configuration}';
    protected $description = 'Display Smart Migration configuration';

    public function handle(): int
    {
        $this->info('Smart Migration Configuration');
        $this->comment('═══════════════════════════════════════════════════════════════════════════════');
        $this->newLine();

        $this->info('Safety Features:');
        $this->line('  Auto Backup: ' . (SmartMigrationConfig::autoBackupEnabled() ? 'Enabled' : 'Disabled'));
        $this->line('  Safe Rollback: ' . (SmartMigrationConfig::safeRollbackEnabled() ? 'Enabled' : 'Disabled'));
        $this->line('  Require Confirmation: ' . (SmartMigrationConfig::requiresConfirmation() ? 'Yes' : 'No'));
        $this->newLine();

        $this->info('Display Settings:');
        $this->line('  Colors: ' . (SmartMigrationConfig::colorsEnabled() ? 'Enabled' : 'Disabled'));
        $this->line('  Emojis: ' . (SmartMigrationConfig::emojisEnabled() ? 'Enabled' : 'Disabled'));
        $this->line('  Progress Bars: ' . (SmartMigrationConfig::progressBarsEnabled() ? 'Enabled' : 'Disabled'));
        $this->line('  Show SQL: ' . (SmartMigrationConfig::showSql() ? 'Yes' : 'No'));
        $this->line('  Show Timing: ' . (SmartMigrationConfig::showTiming() ? 'Yes' : 'No'));
        $this->newLine();

        $this->info('Archive Settings:');
        $this->line('  Table Prefix: ' . SmartMigrationConfig::getArchiveTablePrefix());
        $this->line('  Column Prefix: ' . SmartMigrationConfig::getArchiveColumnPrefix());
        $this->line('  Include Timestamp: ' . (SmartMigrationConfig::includeTimestampInArchive() ? 'Yes' : 'No'));
        $this->line('  Retention Days: ' . SmartMigrationConfig::getArchiveRetentionDays());
        $this->line('  Auto Cleanup: ' . (SmartMigrationConfig::autoCleanupEnabled() ? 'Enabled' : 'Disabled'));
        $this->newLine();

        $this->info('Database Drivers:');
        $drivers = ['mysql', 'pgsql', 'sqlite', 'sqlsrv'];
        foreach ($drivers as $driver) {
            $enabled = SmartMigrationConfig::isDriverEnabled($driver);
            $this->line('  ' . ucfirst($driver) . ': ' . ($enabled ? 'Enabled' : 'Disabled'));
        }
        $this->newLine();

        $this->info('Snapshot Settings:');
        $this->line('  Path: database/' . SmartMigrationConfig::getSnapshotPath());
        $this->line('  Format: ' . SmartMigrationConfig::getSnapshotFormat());
        $this->line('  Auto Snapshot: ' . (SmartMigrationConfig::autoSnapshotEnabled() ? 'Enabled' : 'Disabled'));
        $this->newLine();

        $this->info('Drift Detection:');
        $this->line('  Auto Detect: ' . (SmartMigrationConfig::autoDriftDetection() ? 'Enabled' : 'Disabled'));
        $this->line('  Check Before Migrate: ' . (SmartMigrationConfig::checkDriftBeforeMigrate() ? 'Yes' : 'No'));
        $this->line('  Warn Only: ' . (SmartMigrationConfig::driftWarnOnly() ? 'Yes' : 'No'));
        $this->newLine();

        return self::SUCCESS;
    }
}