<?php

namespace Flux\Commands;

use Flux\Cleanup\ArchiveCleanupService;
use Illuminate\Console\Command;

class CleanupCommand extends Command
{
    protected $signature = 'migrate:cleanup
                            {--dry-run : Preview what would be cleaned without actually deleting}
                            {--stats : Show archive statistics only}';

    protected $description = 'Clean up old archived tables and columns based on retention policy';

    protected ArchiveCleanupService $cleanupService;

    public function __construct()
    {
        parent::__construct();
        $this->cleanupService = app(ArchiveCleanupService::class);
    }

    public function handle(): int
    {
        if ($this->option('stats')) {
            return $this->showStatistics();
        }

        $this->info('🧹 Smart Migration - Archive Cleanup');
        $this->comment('═══════════════════════════════════════════════════════════════════════════════');
        $this->newLine();

        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be deleted');
            $this->newLine();
        }

        // Run cleanup
        $result = $this->cleanupService->cleanup($dryRun);

        if ($result['status'] === 'disabled') {
            $this->warn('⚠️  '.$result['message']);
            $this->comment('Enable auto_cleanup in config/smart-migration.php to use this feature.');

            return self::FAILURE;
        }

        if ($result['status'] === 'skipped') {
            $this->info('ℹ️  '.$result['message']);

            return self::SUCCESS;
        }

        // Display results
        $this->displayCleanupResults($result);

        if (! $dryRun && (count($result['tables_cleaned']) > 0 || count($result['columns_cleaned']) > 0)) {
            $this->newLine();
            $this->info('✅ Cleanup completed successfully!');
        }

        return self::SUCCESS;
    }

    /**
     * Display cleanup results
     */
    protected function displayCleanupResults(array $result): void
    {
        $this->info('Retention Policy:');
        $this->line("  Retention Days: {$result['retention_days']}");
        $this->line("  Cutoff Date: {$result['cutoff_date']}");
        $this->newLine();

        // Display cleaned tables
        if (! empty($result['tables_cleaned'])) {
            $this->info('📋 Archived Tables to Clean:');

            $rows = array_map(function ($table) {
                return [
                    $table['name'],
                    $table['archived_date'],
                    number_format($table['rows']),
                ];
            }, $result['tables_cleaned']);

            $this->table(['Table Name', 'Archived Date', 'Rows'], $rows);
            $this->newLine();
        }

        // Display cleaned columns
        if (! empty($result['columns_cleaned'])) {
            $this->info('📋 Archived Columns to Clean:');

            $rows = array_map(function ($column) {
                return [
                    $column['table'],
                    $column['column'],
                    $column['archived_date'],
                ];
            }, $result['columns_cleaned']);

            $this->table(['Table', 'Column', 'Archived Date'], $rows);
            $this->newLine();
        }

        // Summary
        if (empty($result['tables_cleaned']) && empty($result['columns_cleaned'])) {
            $this->info('✅ No archived data older than retention period found.');
        } else {
            $this->info('Summary:');
            $this->line('  Tables to clean: '.count($result['tables_cleaned']));
            $this->line('  Columns to clean: '.count($result['columns_cleaned']));
            $this->line('  Total rows to delete: '.number_format($result['total_rows_deleted']));

            if ($result['dry_run']) {
                $this->newLine();
                $this->comment('Run without --dry-run to perform the actual cleanup.');
            }
        }
    }

    /**
     * Show archive statistics
     */
    protected function showStatistics(): int
    {
        $this->info('📊 Smart Migration - Archive Statistics');
        $this->comment('═══════════════════════════════════════════════════════════════════════════════');
        $this->newLine();

        $stats = $this->cleanupService->getStatistics();

        // Display configuration
        $this->info('Configuration:');
        $this->line('  Auto Cleanup: '.($stats['auto_cleanup_enabled'] ? 'Enabled' : 'Disabled'));
        $this->line('  Retention Days: '.($stats['retention_days'] > 0 ? $stats['retention_days'] : 'Keep Forever'));
        $this->newLine();

        // Display archived tables
        if (! empty($stats['archived_tables'])) {
            $this->info('📋 Archived Tables:');

            $rows = array_map(function ($table) {
                return [
                    $table['name'],
                    $table['archived_date'],
                    number_format($table['rows']),
                ];
            }, $stats['archived_tables']);

            $this->table(['Table Name', 'Archived Date', 'Rows'], $rows);
            $this->newLine();
        }

        // Display archived columns
        if (! empty($stats['archived_columns'])) {
            $this->info('📋 Archived Columns:');

            $rows = array_map(function ($column) {
                return [
                    $column['table'],
                    $column['column'],
                    $column['archived_date'],
                ];
            }, $stats['archived_columns']);

            $this->table(['Table', 'Column', 'Archived Date'], $rows);
            $this->newLine();
        }

        // Summary
        $this->info('Summary:');
        $this->line('  Total Archived Tables: '.$stats['total_archived_tables']);
        $this->line('  Total Archived Columns: '.$stats['total_archived_columns']);
        $this->line('  Total Archived Rows: '.number_format($stats['total_archived_rows']));

        if ($stats['retention_days'] > 0) {
            $this->newLine();
            $cutoffDate = now()->subDays($stats['retention_days']);
            $this->comment("Items older than {$cutoffDate->toDateString()} will be cleaned on next cleanup.");
        }

        return self::SUCCESS;
    }
}
