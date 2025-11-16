<?php

namespace Flux\Commands;

use Flux\Monitoring\PerformanceBaseline;
use Illuminate\Console\Command;

class BaselineCommand extends Command
{
    protected $signature = 'migrate:baseline
                            {action? : Action to perform (view, reset, report)}
                            {migration? : Specific migration name}
                            {--json : Output as JSON}
                            {--export= : Export baselines to file}
                            {--import= : Import baselines from file}';

    protected $description = 'Manage migration performance baselines and detect anomalies';

    protected PerformanceBaseline $baseline;

    public function __construct()
    {
        parent::__construct();
        $this->baseline = app(PerformanceBaseline::class);
    }

    public function handle(): int
    {
        $action = $this->argument('action') ?: 'view';

        // Handle export/import
        if ($this->option('export')) {
            return $this->exportBaselines();
        }

        if ($this->option('import')) {
            return $this->importBaselines();
        }

        return match ($action) {
            'view' => $this->viewBaselines(),
            'reset' => $this->resetBaselines(),
            'report' => $this->generateReport(),
            default => $this->showHelp(),
        };
    }

    /**
     * View current baselines
     */
    protected function viewBaselines(): int
    {
        $migration = $this->argument('migration');

        if ($migration) {
            return $this->viewSingleBaseline($migration);
        }

        $baselines = $this->baseline->getAll();

        if (empty($baselines)) {
            $this->info('No performance baselines recorded yet.');
            $this->newLine();
            $this->comment('Baselines are automatically created when you run migrations.');
            $this->comment('Run migrations with: php artisan migrate:safe');
            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line(json_encode($baselines, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->displayHeader();
        $this->info('Performance Baselines');
        $this->comment('───────────────────────────────────────────────────────────────────────────');
        $this->newLine();

        foreach ($baselines as $key => $data) {
            if (!isset($data['statistics'])) {
                continue;
            }

            $this->displayBaselineSummary($data);
        }

        $this->comment('───────────────────────────────────────────────────────────────────────────');
        $this->newLine();
        $this->comment('Tip: View detailed stats for a migration with:');
        $this->line('  php artisan migrate:baseline view <migration-name>');

        return self::SUCCESS;
    }

    /**
     * View single baseline
     */
    protected function viewSingleBaseline(string $migration): int
    {
        $stats = $this->baseline->getStatistics($migration);

        if (!$stats) {
            $this->warn("No baseline data found for migration: {$migration}");
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($stats, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->displayHeader();
        $this->info("Performance Baseline: {$migration}");
        $this->comment('───────────────────────────────────────────────────────────────────────────');
        $this->newLine();

        $this->displayDetailedStats($stats);

        return self::SUCCESS;
    }

    /**
     * Display baseline summary
     */
    protected function displayBaselineSummary(array $data): void
    {
        $stats = $data['statistics'];

        $this->comment($data['migration']);
        $this->line("  Runs: {$stats['total_runs']}");
        $this->line(sprintf("  Duration: avg %.2fms | min %.2fms | max %.2fms | p95 %.2fms",
            $stats['duration']['avg'],
            $stats['duration']['min'],
            $stats['duration']['max'],
            $stats['duration']['p95']
        ));
        $this->line(sprintf("  Memory: avg %.2fMB | min %.2fMB | max %.2fMB",
            $stats['memory']['avg'],
            $stats['memory']['min'],
            $stats['memory']['max']
        ));
        $this->line(sprintf("  Queries: avg %.0f | min %d | max %d",
            $stats['queries']['avg'],
            $stats['queries']['min'],
            $stats['queries']['max']
        ));
        $this->newLine();
    }

    /**
     * Display detailed statistics
     */
    protected function displayDetailedStats(array $stats): void
    {
        $this->info('Total Runs: ' . $stats['total_runs']);
        $this->newLine();

        $this->comment('Duration (ms):');
        $this->line("  Average:    " . number_format($stats['duration']['avg'], 2));
        $this->line("  Minimum:    " . number_format($stats['duration']['min'], 2));
        $this->line("  Maximum:    " . number_format($stats['duration']['max'], 2));
        $this->line("  Median:     " . number_format($stats['duration']['median'], 2));
        $this->line("  P95:        " . number_format($stats['duration']['p95'], 2));
        $this->line("  P99:        " . number_format($stats['duration']['p99'], 2));
        $this->newLine();

        $this->comment('Memory (MB):');
        $this->line("  Average:    " . number_format($stats['memory']['avg'], 2));
        $this->line("  Minimum:    " . number_format($stats['memory']['min'], 2));
        $this->line("  Maximum:    " . number_format($stats['memory']['max'], 2));
        $this->newLine();

        $this->comment('Query Count:');
        $this->line("  Average:    " . number_format($stats['queries']['avg'], 0));
        $this->line("  Minimum:    " . $stats['queries']['min']);
        $this->line("  Maximum:    " . $stats['queries']['max']);
        $this->newLine();

        $this->comment('Last Updated: ' . $stats['last_updated']);
    }

    /**
     * Reset baselines
     */
    protected function resetBaselines(): int
    {
        $migration = $this->argument('migration');

        if ($migration) {
            if (!$this->confirm("Reset baseline for migration '{$migration}'?", false)) {
                $this->info('Cancelled.');
                return self::SUCCESS;
            }

            $this->baseline->reset($migration);
            $this->info("Baseline reset for: {$migration}");
        } else {
            if (!$this->confirm('Reset ALL performance baselines?', false)) {
                $this->info('Cancelled.');
                return self::SUCCESS;
            }

            $this->baseline->resetAll();
            $this->info('All baselines have been reset.');
        }

        return self::SUCCESS;
    }

    /**
     * Generate performance report
     */
    protected function generateReport(): int
    {
        $report = $this->baseline->generateReport();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->displayHeader();
        $this->info('Migration Performance Report');
        $this->comment('───────────────────────────────────────────────────────────────────────────');
        $this->newLine();

        $this->info('Summary:');
        $this->line("  Total Migrations Tracked: {$report['total_migrations_tracked']}");
        $this->line("  Total Runs: {$report['summary']['total_runs']}");
        $this->line(sprintf("  Average Duration: %.2fms", $report['summary']['avg_duration_ms']));
        $this->newLine();

        $this->comment('Top 5 Slowest Migrations:');
        foreach ($report['summary']['slowest_migrations'] as $index => $migration) {
            $this->line(sprintf(
                "  %d. %s (avg: %.2fms, max: %.2fms)",
                $index + 1,
                $migration['migration'],
                $migration['duration']['avg'],
                $migration['duration']['max']
            ));
        }
        $this->newLine();

        $this->comment('Top 5 Most Memory Intensive:');
        foreach ($report['summary']['most_memory_intensive'] as $index => $migration) {
            $this->line(sprintf(
                "  %d. %s (avg: %.2fMB, max: %.2fMB)",
                $index + 1,
                $migration['migration'],
                $migration['memory']['avg'],
                $migration['memory']['max']
            ));
        }
        $this->newLine();

        $this->comment('───────────────────────────────────────────────────────────────────────────');
        $this->comment('Generated: ' . $report['generated_at']);

        return self::SUCCESS;
    }

    /**
     * Export baselines
     */
    protected function exportBaselines(): int
    {
        $file = $this->option('export');
        $baselines = $this->baseline->getAll();

        file_put_contents($file, json_encode($baselines, JSON_PRETTY_PRINT));

        $this->info("Baselines exported to: {$file}");
        $this->comment('Total migrations: ' . count($baselines));

        return self::SUCCESS;
    }

    /**
     * Import baselines
     */
    protected function importBaselines(): int
    {
        $file = $this->option('import');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        $json = file_get_contents($file);
        $baselines = json_decode($json, true);

        if (!is_array($baselines)) {
            $this->error('Invalid JSON file');
            return self::FAILURE;
        }

        // Note: The PerformanceBaseline class uses file-based storage,
        // so we need to manually merge the data
        $this->warn('Import functionality needs manual implementation in PerformanceBaseline class');
        $this->comment('For now, manually copy the file to: storage/app/smart-migration/performance-baseline.json');

        return self::FAILURE;
    }

    /**
     * Show help
     */
    protected function showHelp(): int
    {
        $this->displayHeader();
        $this->info('Migration Performance Baseline Management');
        $this->newLine();

        $this->comment('Usage:');
        $this->line('  php artisan migrate:baseline [action] [migration] [options]');
        $this->newLine();

        $this->comment('Actions:');
        $this->line('  view              View all baselines (default)');
        $this->line('  view <migration>  View baseline for specific migration');
        $this->line('  reset             Reset all baselines');
        $this->line('  reset <migration> Reset baseline for specific migration');
        $this->line('  report            Generate performance report');
        $this->newLine();

        $this->comment('Options:');
        $this->line('  --json            Output as JSON');
        $this->line('  --export=FILE     Export baselines to file');
        $this->line('  --import=FILE     Import baselines from file');
        $this->newLine();

        $this->comment('Examples:');
        $this->line('  php artisan migrate:baseline');
        $this->line('  php artisan migrate:baseline view 2025_01_01_000000_create_users_table');
        $this->line('  php artisan migrate:baseline report');
        $this->line('  php artisan migrate:baseline reset');
        $this->line('  php artisan migrate:baseline --export=baselines.json');

        return self::SUCCESS;
    }

    /**
     * Display command header
     */
    protected function displayHeader(): void
    {
        $this->info('📊 Migration Performance Monitoring');
        $this->comment('───────────────────────────────────────────────────────────────────────────');
        $this->newLine();
    }
}
