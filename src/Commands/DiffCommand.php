<?php

namespace Flux\Commands;

use Flux\Database\DatabaseAdapterFactoryInterface;
use Flux\Generators\MigrationBuilder;
use Flux\Generators\SchemaComparator;
use Flux\Snapshots\SnapshotManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DiffCommand extends BaseSmartMigrationCommand
{
    protected $signature = 'migrate:diff
                          {--name= : Custom migration name}
                          {--dry-run : Preview differences without generating migration}
                          {--force : Generate without confirmation}
                          {--tables=* : Specific tables to check}
                          {--ignore-version-mismatch : Suppress snapshot format version warnings}';

    protected $description = 'Auto-generate migration from database differences';

    protected SchemaComparator $comparator;

    protected MigrationBuilder $builder;

    protected SnapshotManager $snapshots;

    protected DatabaseAdapterFactoryInterface $adapterFactory;

    public function __construct(
        SchemaComparator $comparator,
        MigrationBuilder $builder,
        SnapshotManager $snapshots,
        DatabaseAdapterFactoryInterface $adapterFactory
    ) {
        parent::__construct();

        $this->comparator = $comparator;
        $this->builder = $builder;
        $this->snapshots = $snapshots;
        $this->adapterFactory = $adapterFactory;
    }

    public function handle(): int
    {
        $this->displayHeader();

        // 1. Build schema from migration history
        $this->line('Building schema from migration history...');
        $migrationSchema = $this->buildSchemaFromMigrations();

        // 2. Capture current database schema
        $this->line('Capturing current database schema...');
        $databaseSchema = $this->captureCurrentSchema();

        // 3. Generate differences
        $this->newLine();
        $this->infoWithEmoji('Analyzing database changes...', 'mag');
        $differences = $this->comparator->compare($migrationSchema, $databaseSchema);

        // 4. Display differences
        if (! $this->hasDifferences($differences)) {
            $this->newLine();
            $this->infoWithEmoji('No differences detected! Database is in sync with migrations.', 'white_check_mark');

            return Command::SUCCESS;
        }

        $this->displayDifferences($differences);

        // 5. If dry-run, exit here
        if ($this->option('dry-run')) {
            $this->newLine();
            $this->commentWithEmoji('Dry-run mode - no migration file generated.', 'eyes');

            return Command::SUCCESS;
        }

        // 6. Confirm generation
        if (! $this->confirmGeneration()) {
            $this->warnWithEmoji('Migration generation cancelled.', 'x');

            return Command::SUCCESS;
        }

        // 7. Build migration file
        $migrationName = $this->getMigrationName();
        $this->newLine();
        $this->infoWithEmoji('Creating migration file...', 'hammer_and_wrench');

        $migrationCode = $this->builder->build($differences, $migrationName);

        // 8. Write migration file
        $filepath = $this->writeMigration($migrationName, $migrationCode);

        // 9. Display success
        $this->displaySuccess($filepath, $migrationCode);

        return Command::SUCCESS;
    }

    /**
     * Display command header
     */
    protected function displayHeader(): void
    {
        $this->newLine();
        $this->line('<fg=cyan>🔍 Smart Migration - Auto Diff Generator</>');
        $this->line(str_repeat('─', 60));
    }

    /**
     * Build schema from applied migrations
     */
    protected function buildSchemaFromMigrations(): array
    {
        // Get the latest snapshot as baseline
        $snapshot = $this->snapshots->getLatest();

        if ($snapshot) {
            $this->line("<fg=gray>  Using snapshot: {$snapshot['name']} (v{$snapshot['version']})</>");

            // Check for format version mismatch
            if (! $this->option('ignore-version-mismatch') && $this->snapshots->hasFormatVersionMismatch($snapshot)) {
                $this->newLine();
                $this->warn($this->snapshots->getFormatVersionWarning($snapshot));
                $this->newLine();
            }

            return $snapshot['schema'] ?? ['tables' => []];
        }

        // No snapshot available - use empty schema
        $this->line('<fg=gray>  No snapshot available, using empty baseline</>');

        return ['tables' => []];
    }

    /**
     * Capture current database schema
     */
    protected function captureCurrentSchema(): array
    {
        $adapter = $this->adapterFactory->create();
        $driver = $adapter->getDriverName();
        $database = config('database.connections.'.config('database.default').'.database');

        $this->line("<fg=gray>  Database: {$driver}:{$database}</>");

        $schema = ['tables' => []];

        $tables = $adapter->getAllTables();

        // Filter tables if --tables option provided
        if ($this->option('tables')) {
            $tables = array_intersect($tables, $this->option('tables'));
        }

        // Filter ignored tables
        $tables = $this->filterIgnoredTables($tables);

        foreach ($tables as $table) {
            $schema['tables'][$table] = [
                'columns' => $adapter->getTableColumns($table),
                'indexes' => $adapter->getTableIndexes($table),
                'foreign_keys' => $adapter->getTableForeignKeys($table),
            ];
        }

        return $schema;
    }

    /**
     * Check if there are any differences
     */
    protected function hasDifferences(array $differences): bool
    {
        return ! empty($differences['tables_to_create'])
            || ! empty($differences['tables_to_drop'])
            || ! empty($differences['tables_to_modify']);
    }

    /**
     * Display detected differences
     */
    protected function displayDifferences(array $differences): void
    {
        $this->newLine();
        $this->line('Found differences:');
        $this->line(str_repeat('─', 60));

        $changeCount = 0;

        // Tables to create
        if (! empty($differences['tables_to_create'])) {
            $this->newLine();
            $this->line('<fg=yellow>📦 New Tables (not in migrations):</>');
            foreach ($differences['tables_to_create'] as $tableName => $structure) {
                $changeCount++;
                $columnCount = count($structure['columns'] ?? []);
                $this->line("  {$changeCount}. <fg=green>+</> Table '{$tableName}' ({$columnCount} columns)");
            }
        }

        // Tables to drop
        if (! empty($differences['tables_to_drop'])) {
            $this->newLine();
            $this->line('<fg=red>🗑️  Dropped Tables (in migrations but not in DB):</>');
            foreach ($differences['tables_to_drop'] as $tableName) {
                $changeCount++;
                $this->line("  {$changeCount}. <fg=red>-</> Table '{$tableName}'");
            }
        }

        // Tables to modify
        if (! empty($differences['tables_to_modify'])) {
            $this->newLine();
            $this->line('<fg=yellow>🔧 Modified Tables:</>');
            foreach ($differences['tables_to_modify'] as $tableName => $modifications) {
                $this->displayTableModifications($tableName, $modifications, $changeCount);
            }
        }

        $this->newLine();
        $this->line(str_repeat('─', 60));
        $this->line("<fg=cyan>Total changes: {$changeCount}</>");
    }

    /**
     * Display modifications for a specific table
     */
    protected function displayTableModifications(string $tableName, array $modifications, int &$changeCount): void
    {
        $this->newLine();
        $this->line("  Table: <fg=cyan>{$tableName}</>");

        // Columns to rename
        foreach ($modifications['columns_to_rename'] ?? [] as $rename) {
            $changeCount++;
            $this->line("    {$changeCount}. <fg=blue>↻</> Column '{$rename['from']}' → '{$rename['to']}' (rename detected)");
        }

        // Columns to add
        foreach ($modifications['columns_to_add'] ?? [] as $column) {
            $changeCount++;
            $type = $column['type'];
            $nullable = ($column['nullable'] ?? false) ? ' nullable' : '';
            $this->line("    {$changeCount}. <fg=green>+</> Column '{$column['name']}' ({$type}{$nullable})");
        }

        // Columns to modify
        foreach ($modifications['columns_to_modify'] ?? [] as $change) {
            $changeCount++;
            $from = $change['from']['type'];
            $to = $change['to']['type'];
            $this->line("    {$changeCount}. <fg=yellow>~</> Column '{$change['name']}' changed from {$from} to {$to}");
        }

        // Columns to drop
        foreach ($modifications['columns_to_drop'] ?? [] as $columnName) {
            $changeCount++;
            $this->line("    {$changeCount}. <fg=red>-</> Column '{$columnName}'");
        }

        // Indexes to add
        foreach ($modifications['indexes_to_add'] ?? [] as $index) {
            $changeCount++;
            $columns = implode(', ', $index['columns'] ?? []);
            $this->line("    {$changeCount}. <fg=green>+</> Index '{$index['name']}' ({$columns})");
        }

        // Indexes to drop
        foreach ($modifications['indexes_to_drop'] ?? [] as $indexName) {
            $changeCount++;
            $this->line("    {$changeCount}. <fg=red>-</> Index '{$indexName}'");
        }

        // Foreign keys to add
        foreach ($modifications['foreign_keys_to_add'] ?? [] as $fk) {
            $changeCount++;
            $this->line("    {$changeCount}. <fg=green>+</> Foreign key '{$fk['name']}'");
        }

        // Foreign keys to drop
        foreach ($modifications['foreign_keys_to_drop'] ?? [] as $fkName) {
            $changeCount++;
            $this->line("    {$changeCount}. <fg=red>-</> Foreign key '{$fkName}'");
        }
    }

    /**
     * Confirm migration generation
     */
    protected function confirmGeneration(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        $this->newLine();

        return $this->confirm('Generate migration to sync these changes?', false);
    }

    /**
     * Get migration name
     */
    protected function getMigrationName(): string
    {
        if ($this->option('name')) {
            return $this->option('name');
        }

        return 'sync_schema_changes';
    }

    /**
     * Write migration file
     */
    protected function writeMigration(string $name, string $content): string
    {
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_{$name}.php";
        $filepath = database_path('migrations/'.$filename);

        File::put($filepath, $content);

        return $filepath;
    }

    /**
     * Display success message and preview
     */
    protected function displaySuccess(string $filepath, string $migrationCode): void
    {
        $this->newLine();
        $this->infoWithEmoji('Migration created successfully!', 'white_check_mark');
        $this->newLine();
        $this->line("<fg=green>Generated:</> {$filepath}");

        // Show migration preview
        $this->newLine();
        $this->line('<fg=cyan>Migration Preview:</>');
        $this->line(str_repeat('─', 60));

        // Extract and display just the up() method
        $preview = $this->extractUpMethodPreview($migrationCode);
        $this->line($preview);

        $this->line(str_repeat('─', 60));

        // Next steps
        $this->newLine();
        $this->line('<fg=cyan>Next steps:</>');
        $this->line('  1. Review the generated migration file');
        $this->line('  2. Run: <fg=yellow>php artisan migrate:plan</> to preview');
        $this->line('  3. Run: <fg=yellow>php artisan migrate:safe</> to apply');
    }

    /**
     * Extract up() method for preview
     */
    protected function extractUpMethodPreview(string $migrationCode): string
    {
        // Extract lines between "public function up()" and "public function down()"
        $lines = explode("\n", $migrationCode);
        $preview = [];
        $capturing = false;

        foreach ($lines as $line) {
            if (str_contains($line, 'public function up()')) {
                $capturing = true;
                $preview[] = $line;

                continue;
            }

            if (str_contains($line, 'public function down()')) {
                break;
            }

            if ($capturing) {
                $preview[] = $line;
            }
        }

        return implode("\n", $preview);
    }

    /**
     * Filter out ignored tables based on configuration
     */
    protected function filterIgnoredTables(array $tables): array
    {
        $ignoredPatterns = config('smart-migration.drift.ignored_tables', []);

        return array_filter($tables, function ($table) use ($ignoredPatterns) {
            foreach ($ignoredPatterns as $pattern) {
                // Support wildcard patterns
                if (fnmatch($pattern, $table)) {
                    return false;
                }
            }

            return true;
        });
    }
}
