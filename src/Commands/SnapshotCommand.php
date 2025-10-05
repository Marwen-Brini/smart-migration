<?php

namespace Flux\Commands;

use Flux\Snapshots\SnapshotManager;
use Illuminate\Console\Command;

class SnapshotCommand extends Command
{
    protected $signature = 'migrate:snapshot
                            {action=create : Action to perform: create, list, show, compare, delete}
                            {name? : Snapshot name}
                            {--with-data : Include table data in snapshot}
                            {--compare-with= : Second snapshot for comparison}';

    protected $description = 'Create and manage database schema snapshots';

    protected SnapshotManager $snapshotManager;

    public function __construct()
    {
        parent::__construct();
        $this->snapshotManager = app(SnapshotManager::class);
    }

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'create' => $this->createSnapshot(),
            'list' => $this->listSnapshots(),
            'show' => $this->showSnapshot(),
            'compare' => $this->compareSnapshots(),
            'delete' => $this->deleteSnapshot(),
            default => $this->invalidAction($action),
        };
    }

    /**
     * Create a new snapshot
     */
    protected function createSnapshot(): int
    {
        $this->info('📸 Creating Schema Snapshot');
        $this->comment('═══════════════════════════════════════════════════════════════════════════════');
        $this->newLine();

        $name = $this->argument('name');

        try {
            $this->info('Analyzing database schema...');
            $snapshot = $this->snapshotManager->create($name);

            $this->newLine();
            $this->info('✅ Snapshot created successfully!');
            $this->newLine();

            $this->table(
                ['Property', 'Value'],
                [
                    ['Name', $snapshot['name']],
                    ['Version', $snapshot['version']],
                    ['Timestamp', $snapshot['timestamp']],
                    ['Environment', $snapshot['environment']],
                    ['Database', $snapshot['database']['driver']],
                    ['Tables', count($snapshot['schema']['tables'] ?? [])],
                ]
            );

            // Show auto-snapshot reminder
            if (! config('smart-migration.snapshots.auto_snapshot')) {
                $this->newLine();
                $this->comment('💡 Tip: Enable auto_snapshot in config to automatically create snapshots after migrations.');
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Failed to create snapshot: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * List all snapshots
     */
    protected function listSnapshots(): int
    {
        $this->info('📋 Schema Snapshots');
        $this->comment('═══════════════════════════════════════════════════════════════════════════════');
        $this->newLine();

        $snapshots = $this->snapshotManager->list();

        if (empty($snapshots)) {
            $this->warn('No snapshots found. Run "php artisan migrate:snapshot create" to create one.');

            return self::SUCCESS;
        }

        $rows = array_map(function ($snapshot) {
            return [
                $snapshot['name'],
                $snapshot['version'],
                $snapshot['timestamp'],
                $snapshot['environment'],
                $this->formatFileSize($snapshot['size']),
            ];
        }, $snapshots);

        $this->table(
            ['Name', 'Version', 'Created At', 'Environment', 'Size'],
            $rows
        );

        $this->newLine();
        $this->info('Total snapshots: '.count($snapshots));

        return self::SUCCESS;
    }

    /**
     * Show details of a specific snapshot
     */
    protected function showSnapshot(): int
    {
        $name = $this->argument('name');

        if (! $name) {
            $this->error('Please provide a snapshot name.');

            return self::FAILURE;
        }

        $this->info("📸 Snapshot: {$name}");
        $this->comment('═══════════════════════════════════════════════════════════════════════════════');
        $this->newLine();

        $snapshot = $this->snapshotManager->get($name);

        if (! $snapshot) {
            $this->error("Snapshot '{$name}' not found.");

            return self::FAILURE;
        }

        // Display snapshot metadata
        $this->info('Metadata:');
        $this->table(
            ['Property', 'Value'],
            [
                ['Name', $snapshot['name']],
                ['Version', $snapshot['version']],
                ['Timestamp', $snapshot['timestamp']],
                ['Environment', $snapshot['environment']],
                ['Database Driver', $snapshot['database']['driver']],
                ['Database Name', $snapshot['database']['name']],
            ]
        );

        // Display schema summary
        $this->newLine();
        $this->info('Schema Summary:');

        $tables = $snapshot['schema']['tables'] ?? [];
        $totalColumns = 0;
        $totalIndexes = 0;
        $totalForeignKeys = 0;
        $totalRows = 0;

        foreach ($tables as $table) {
            $totalColumns += count($table['columns'] ?? []);
            $totalIndexes += count($table['indexes'] ?? []);
            $totalForeignKeys += count($table['foreign_keys'] ?? []);
            $totalRows += $table['row_count'] ?? 0;
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Tables', count($tables)],
                ['Columns', $totalColumns],
                ['Indexes', $totalIndexes],
                ['Foreign Keys', $totalForeignKeys],
                ['Total Rows', number_format($totalRows)],
            ]
        );

        // List tables
        $this->newLine();
        $this->info('Tables:');

        $tableRows = [];
        foreach ($tables as $tableName => $table) {
            $tableRows[] = [
                $tableName,
                count($table['columns'] ?? []),
                count($table['indexes'] ?? []),
                number_format($table['row_count'] ?? 0),
            ];
        }

        $this->table(
            ['Table', 'Columns', 'Indexes', 'Rows'],
            $tableRows
        );

        return self::SUCCESS;
    }

    /**
     * Compare two snapshots
     */
    protected function compareSnapshots(): int
    {
        $name1 = $this->argument('name');
        $name2 = $this->option('compare-with');

        if (! $name1 || ! $name2) {
            $this->error('Please provide two snapshot names to compare.');
            $this->line('Usage: php artisan migrate:snapshot compare <snapshot1> --compare-with=<snapshot2>');

            return self::FAILURE;
        }

        $this->info("🔍 Comparing Snapshots: {$name1} vs {$name2}");
        $this->comment('═══════════════════════════════════════════════════════════════════════════════');
        $this->newLine();

        try {
            $differences = $this->snapshotManager->compare($name1, $name2);

            if (empty($differences['added_tables']) &&
                empty($differences['removed_tables']) &&
                empty($differences['modified_tables'])) {
                $this->info('✅ Snapshots are identical!');

                return self::SUCCESS;
            }

            // Display differences
            if (! empty($differences['added_tables'])) {
                $this->info('➕ Added Tables:');
                foreach ($differences['added_tables'] as $table) {
                    $this->line("   - {$table}");
                }
                $this->newLine();
            }

            if (! empty($differences['removed_tables'])) {
                $this->warn('➖ Removed Tables:');
                foreach ($differences['removed_tables'] as $table) {
                    $this->line("   - {$table}");
                }
                $this->newLine();
            }

            if (! empty($differences['modified_tables'])) {
                $this->comment('✏️  Modified Tables:');
                foreach ($differences['modified_tables'] as $table) {
                    $this->line("   - {$table}");
                }
                $this->newLine();
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to compare snapshots: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Delete a snapshot
     */
    protected function deleteSnapshot(): int
    {
        $name = $this->argument('name');

        if (! $name) {
            $this->error('Please provide a snapshot name to delete.');

            return self::FAILURE;
        }

        if (! $this->confirm("Are you sure you want to delete snapshot '{$name}'?")) {
            $this->comment('Deletion cancelled.');

            return self::SUCCESS;
        }

        if ($this->snapshotManager->delete($name)) {
            $this->info("✅ Snapshot '{$name}' deleted successfully.");

            return self::SUCCESS;
        } else {
            $this->error("❌ Failed to delete snapshot '{$name}'.");

            return self::FAILURE;
        }
    }

    /**
     * Handle invalid action
     */
    protected function invalidAction(string $action): int
    {
        $this->error("Invalid action: {$action}");
        $this->line('Valid actions: create, list, show, compare, delete');

        return self::FAILURE;
    }

    /**
     * Format file size for display
     */
    protected function formatFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 2).' KB';
        } else {
            return round($bytes / 1048576, 2).' MB';
        }
    }
}
