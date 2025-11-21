<?php

namespace Flux\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class HistoryCommand extends Command
{
    protected $signature = 'migrate:history
                            {--json : Output as JSON}
                            {--limit=20 : Number of migrations to show}
                            {--reverse : Show oldest first}';

    protected $description = 'Show migration history timeline with metadata';

    public function handle(): int
    {
        $this->displayHeader();

        // Get all migrations from the database
        $appliedMigrations = $this->getAppliedMigrations();

        // Get all pending migrations
        $pendingMigrations = $this->getPendingMigrations();

        // Combine and sort
        $allMigrations = $this->combineAndSortMigrations($appliedMigrations, $pendingMigrations);

        // Apply limit to final combined list
        $limit = $this->option('limit');
        if ($limit && count($allMigrations) > $limit) {
            $allMigrations = array_slice($allMigrations, 0, $limit);
        }

        if ($this->option('json')) {
            $this->outputJson($allMigrations);
            return self::SUCCESS;
        }

        if (empty($allMigrations)) {
            $this->warn('No migrations found.');
            return self::SUCCESS;
        }

        $this->displayTimeline($allMigrations);
        $this->displaySummary($appliedMigrations, $pendingMigrations);

        return self::SUCCESS;
    }

    /**
     * Display command header
     */
    protected function displayHeader(): void
    {
        $this->info('📜 Migration History Timeline');
        $this->comment('═══════════════════════════════════════════════════════════════════════════════');
        $this->newLine();
    }

    /**
     * Get applied migrations from database
     */
    protected function getAppliedMigrations(): array
    {
        try {
            // Get migrations table name (usually 'migrations')
            $tableName = 'migrations';

            $migrations = DB::table($tableName)
                ->orderBy('id', $this->option('reverse') ? 'asc' : 'desc')
                ->get()
                ->map(function ($migration) {
                    return [
                        'id' => $migration->id,
                        'migration' => $migration->migration,
                        'batch' => $migration->batch,
                        'status' => 'applied',
                        'ran_at' => $this->extractTimestampFromName($migration->migration),
                        'name' => $this->formatMigrationName($migration->migration),
                    ];
                })
                ->toArray();

            return $migrations;
        } catch (\Exception $e) {
            $this->error('Failed to fetch migration history: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get pending migrations
     */
    protected function getPendingMigrations(): array
    {
        $migrator = app('migrator');
        $repository = $migrator->getRepository();

        // Get all migration files
        $files = $migrator->getMigrationFiles(database_path('migrations'));

        // Get already ran migrations
        $ran = $repository->getRan();

        // Filter pending
        $pending = array_diff(array_keys($files), $ran);

        return collect($pending)
            ->map(function ($migration) {
                return [
                    'id' => null,
                    'migration' => $migration,
                    'batch' => null,
                    'status' => 'pending',
                    'ran_at' => $this->extractTimestampFromName($migration),
                    'name' => $this->formatMigrationName($migration),
                ];
            })
            ->toArray();
    }

    /**
     * Combine and sort migrations
     */
    protected function combineAndSortMigrations(array $applied, array $pending): array
    {
        $all = array_merge($applied, $pending);

        // Sort by migration name (which includes timestamp)
        usort($all, function ($a, $b) {
            if ($this->option('reverse')) {
                return strcmp($a['migration'], $b['migration']);
            }
            return strcmp($b['migration'], $a['migration']);
        });

        return $all;
    }

    /**
     * Extract timestamp from migration name
     */
    protected function extractTimestampFromName(string $migration): ?string
    {
        // Match migration timestamp pattern: YYYY_MM_DD_HHMMSS
        if (preg_match('/^(\d{4})_(\d{2})_(\d{2})_(\d{6})_/', $migration, $matches)) {
            try {
                $year = $matches[1];
                $month = $matches[2];
                $day = $matches[3];
                $time = $matches[4];

                $hour = substr($time, 0, 2);
                $minute = substr($time, 2, 2);
                $second = substr($time, 4, 2);

                return Carbon::create($year, $month, $day, $hour, $minute, $second)
                    ->format('Y-m-d H:i:s');
            } catch (\Exception $e) { // @codeCoverageIgnore
                return null; // @codeCoverageIgnore
            }
        }

        return null;
    }

    /**
     * Format migration name for display
     */
    protected function formatMigrationName(string $migration): string
    {
        // Remove timestamp prefix
        $name = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $migration);

        // Convert to title case
        return str_replace('_', ' ', ucwords($name, '_'));
    }

    /**
     * Display timeline view
     */
    protected function displayTimeline(array $migrations): void
    {
        $rows = [];

        foreach ($migrations as $migration) {
            $status = $this->getStatusBadge($migration['status']);
            $batch = $migration['batch'] ? "Batch {$migration['batch']}" : '';
            $timestamp = $migration['ran_at'] ?? 'N/A';

            // Try to extract version/tag from migration path if exists
            $version = $this->extractVersion($migration['migration']);

            $rows[] = [
                $timestamp,
                $migration['name'],
                $status,
                $batch,
                $version,
            ];
        }

        $this->table(
            ['Timestamp', 'Migration', 'Status', 'Batch', 'Version/Tag'],
            $rows
        );
    }

    /**
     * Get colored status badge
     */
    protected function getStatusBadge(string $status): string
    {
        return match ($status) {
            'applied' => '<info>✓ Applied</info>',
            'pending' => '<comment>⏳ Pending</comment>',
            'failed' => '<error>✗ Failed</error>',
            default => $status,
        };
    }

    /**
     * Extract version/tag from migration (if any)
     */
    protected function extractVersion(string $migration): string
    {
        // Check if migration file has version comment
        $path = database_path('migrations/' . $migration . '.php');

        if (File::exists($path)) {
            $content = File::get($path);

            // Look for @version or @tag in docblock
            if (preg_match('/@version\s+([^\s\n]+)/', $content, $matches)) {
                return $matches[1];
            }
            if (preg_match('/@tag\s+([^\s\n]+)/', $content, $matches)) {
                return $matches[1];
            }
        }

        return '';
    }

    /**
     * Display summary statistics
     */
    protected function displaySummary(array $applied, array $pending): void
    {
        $this->newLine();
        $this->comment('──────────────────────────────────────────────────────────────────────────────');

        $totalApplied = count($applied);
        $totalPending = count($pending);
        $total = $totalApplied + $totalPending;

        $appliedBatches = collect($applied)->pluck('batch')->unique()->count();

        $this->line("  <info>Total Migrations:</info> {$total}");
        $this->line("  <info>Applied:</info> {$totalApplied} (in {$appliedBatches} batches)");
        $this->line("  <comment>Pending:</comment> {$totalPending}");

        if ($totalPending > 0) {
            $this->newLine();
            $this->comment('💡 Tip: Run "php artisan migrate:plan" to preview pending migrations.');
        }

        $this->comment('──────────────────────────────────────────────────────────────────────────────');
    }

    /**
     * Output as JSON
     */
    protected function outputJson(array $migrations): void
    {
        $this->line(json_encode([
            'migrations' => $migrations,
            'summary' => [
                'total' => count($migrations),
                'applied' => count(array_filter($migrations, fn($m) => $m['status'] === 'applied')),
                'pending' => count(array_filter($migrations, fn($m) => $m['status'] === 'pending')),
            ],
        ], JSON_PRETTY_PRINT));
    }
}
