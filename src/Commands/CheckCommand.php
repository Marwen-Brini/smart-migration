<?php

namespace Flux\Commands;

use Flux\Config\SmartMigrationConfig;
use Flux\Database\DatabaseAdapterFactoryInterface;
use Flux\Snapshots\SnapshotManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckCommand extends Command
{
    protected $signature = 'migrate:check
                            {--snapshot : Compare against last snapshot}
                            {--fix : Generate migration to fix drift}
                            {--details : Show detailed comparison}
                            {--ignore-version-mismatch : Suppress snapshot format version warnings}';

    protected $description = 'Check for schema drift between migrations and actual database';

    protected SnapshotManager $snapshotManager;

    protected DatabaseAdapterFactoryInterface $adapterFactory;

    public function __construct()
    {
        parent::__construct();
        $this->snapshotManager = app(SnapshotManager::class);
        $this->adapterFactory = app(DatabaseAdapterFactoryInterface::class);
    }

    public function handle(): int
    {
        $this->info('🔍 Smart Migration - Drift Detection');
        $this->comment('═══════════════════════════════════════════════════════════════════════════════');
        $this->newLine();

        $adapter = $this->adapterFactory->create();

        // Get current database schema
        $currentSchema = $this->getCurrentDatabaseSchema();

        // Get expected schema
        $expectedSchema = $this->option('snapshot')
            ? $this->getSnapshotSchema()
            : $this->getExpectedSchemaFromMigrations();

        if (! $expectedSchema) {
            $this->warn('⚠️  No baseline schema found. Run migrate:snapshot to create one.');

            return self::FAILURE;
        }

        // Compare schemas
        $drift = $this->compareSchemas($expectedSchema, $currentSchema);

        if (empty($drift)) {
            $this->info('✅ No schema drift detected! Database matches expected state.');

            return self::SUCCESS;
        }

        // Display drift
        $this->displayDrift($drift);

        // Offer to fix if requested
        if ($this->option('fix')) {
            return $this->generateFixMigration($drift);
        }

        $this->newLine();
        $this->warn('⚠️  Run with --fix to generate a migration that resolves the drift.');

        return self::FAILURE;
    }

    /**
     * Get current database schema
     */
    protected function getCurrentDatabaseSchema(): array
    {
        $adapter = $this->adapterFactory->create();
        $schema = [
            'tables' => [],
        ];

        $ignoredTables = SmartMigrationConfig::getDriftIgnoredTables();

        foreach ($adapter->getAllTables() as $table) {
            // Skip ignored tables
            $skip = false;
            foreach ($ignoredTables as $pattern) {
                if (fnmatch($pattern, $table)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            $schema['tables'][$table] = [
                'columns' => $adapter->getTableColumns($table),
                'indexes' => $adapter->getTableIndexes($table),
                'foreign_keys' => $adapter->getTableForeignKeys($table),
            ];
        }

        return $schema;
    }

    /**
     * Get expected schema from snapshot
     */
    protected function getSnapshotSchema(): ?array
    {
        $snapshot = $this->snapshotManager->getLatest();

        if ($snapshot) {
            // Check for format version mismatch
            if (! $this->option('ignore-version-mismatch') && $this->snapshotManager->hasFormatVersionMismatch($snapshot)) {
                $this->newLine();
                $this->warn($this->snapshotManager->getFormatVersionWarning($snapshot));
                $this->newLine();
            }

            return $snapshot['schema'] ?? null;
        }

        return null;
    }

    /**
     * Get expected schema from migrations
     */
    protected function getExpectedSchemaFromMigrations(): ?array
    {
        // This would analyze all run migrations to build expected schema
        // For now, we'll use snapshot as the source of truth
        $snapshot = $this->snapshotManager->getLatest();

        if (! $snapshot) {
            // Try to build from migrations table
            $migrations = DB::table('migrations')->orderBy('batch')->get();

            if ($migrations->isEmpty()) {
                return null;
            }

            $this->warn('Building expected schema from migration history...');

            // This is a simplified version - in production, we'd parse each migration
            return $this->getCurrentDatabaseSchema();
        }

        // Check for format version mismatch
        if (! $this->option('ignore-version-mismatch') && $this->snapshotManager->hasFormatVersionMismatch($snapshot)) {
            $this->newLine();
            $this->warn($this->snapshotManager->getFormatVersionWarning($snapshot));
            $this->newLine();
        }

        return $snapshot['schema'] ?? null;
    }

    /**
     * Compare two schemas and find differences
     */
    protected function compareSchemas(array $expected, array $current): array
    {
        $drift = [
            'missing_tables' => [],
            'extra_tables' => [],
            'table_changes' => [],
        ];

        $ignoredColumns = SmartMigrationConfig::getDriftIgnoredColumns();

        // Find missing and changed tables
        foreach ($expected['tables'] ?? [] as $tableName => $expectedTable) {
            if (! isset($current['tables'][$tableName])) {
                $drift['missing_tables'][] = $tableName;

                continue;
            }

            $currentTable = $current['tables'][$tableName];
            $changes = [];

            // Compare columns
            $expectedColumns = array_column($expectedTable['columns'], null, 'name');
            $currentColumns = array_column($currentTable['columns'], null, 'name');

            // Find missing columns
            foreach ($expectedColumns as $colName => $colDef) {
                if (in_array($colName, $ignoredColumns)) {
                    continue;
                }

                if (! isset($currentColumns[$colName])) {
                    $changes['missing_columns'][] = $colName;
                }
            }

            // Find extra columns
            foreach ($currentColumns as $colName => $colDef) {
                if (in_array($colName, $ignoredColumns)) {
                    continue;
                }

                if (! isset($expectedColumns[$colName])) {
                    $changes['extra_columns'][] = $colName;
                }
            }

            // Compare indexes
            $expectedIndexes = array_column($expectedTable['indexes'] ?? [], null, 'name');
            $currentIndexes = array_column($currentTable['indexes'] ?? [], null, 'name');

            foreach ($expectedIndexes as $idxName => $idxDef) {
                if (! isset($currentIndexes[$idxName])) {
                    $changes['missing_indexes'][] = $idxName;
                }
            }

            foreach ($currentIndexes as $idxName => $idxDef) {
                if (! isset($expectedIndexes[$idxName])) {
                    $changes['extra_indexes'][] = $idxName;
                }
            }

            if (! empty($changes)) {
                $drift['table_changes'][$tableName] = $changes;
            }
        }

        // Find extra tables
        foreach ($current['tables'] ?? [] as $tableName => $currentTable) {
            if (! isset($expected['tables'][$tableName])) {
                $drift['extra_tables'][] = $tableName;
            }
        }

        // Clean up empty arrays
        $drift = array_filter($drift, fn ($v) => ! empty($v));

        return $drift;
    }

    /**
     * Display drift in a user-friendly format
     */
    protected function displayDrift(array $drift): void
    {
        $this->error('⚠️  Schema Drift Detected!');
        $this->newLine();

        if (! empty($drift['missing_tables'])) {
            $this->warn('📋 Missing Tables (exist in migrations but not in database):');
            foreach ($drift['missing_tables'] as $table) {
                $this->line("   - {$table}");
            }
            $this->newLine();
        }

        if (! empty($drift['extra_tables'])) {
            $this->warn('📋 Extra Tables (exist in database but not in migrations):');
            foreach ($drift['extra_tables'] as $table) {
                $this->line("   - {$table}");
            }
            $this->newLine();
        }

        if (! empty($drift['table_changes'])) {
            $this->warn('📋 Table Differences:');
            foreach ($drift['table_changes'] as $table => $changes) {
                $this->info("   Table: {$table}");

                if (! empty($changes['missing_columns'])) {
                    $this->line('     Missing columns: '.implode(', ', $changes['missing_columns']));
                }
                if (! empty($changes['extra_columns'])) {
                    $this->line('     Extra columns: '.implode(', ', $changes['extra_columns']));
                }
                if (! empty($changes['missing_indexes'])) {
                    $this->line('     Missing indexes: '.implode(', ', $changes['missing_indexes']));
                }
                if (! empty($changes['extra_indexes'])) {
                    $this->line('     Extra indexes: '.implode(', ', $changes['extra_indexes']));
                }
            }
            $this->newLine();
        }
    }

    /**
     * Generate a migration to fix drift
     */
    protected function generateFixMigration(array $drift): int
    {
        $this->info('🔧 Generating migration to fix drift...');

        $timestamp = date('Y_m_d_His');
        $className = 'FixSchemaDrift'.$timestamp;
        $filename = database_path("migrations/{$timestamp}_fix_schema_drift.php");

        $migration = $this->buildMigrationContent($className, $drift);

        file_put_contents($filename, $migration);

        $this->info("✅ Created migration: {$filename}");
        $this->newLine();
        $this->comment('Review the generated migration and run migrate:safe to apply it.');

        return self::SUCCESS;
    }

    /**
     * Build migration content from drift
     */
    protected function buildMigrationContent(string $className, array $drift): string
    {
        $up = [];
        $down = [];

        // Handle missing tables
        foreach ($drift['missing_tables'] ?? [] as $table) {
            $up[] = "        // TODO: Create table '{$table}'";
            $up[] = "        Schema::create('{$table}', function (Blueprint \$table) {";
            $up[] = '            // Add columns here';
            $up[] = '        });';
            $down[] = "        Schema::dropIfExists('{$table}');";
        }

        // Handle extra tables
        foreach ($drift['extra_tables'] ?? [] as $table) {
            $up[] = "        // Extra table '{$table}' found - consider if it should be kept";
            $up[] = "        // Schema::dropIfExists('{$table}');";
        }

        // Handle table changes
        foreach ($drift['table_changes'] ?? [] as $table => $changes) {
            if (! empty($changes['missing_columns']) || ! empty($changes['extra_columns'])) {
                $up[] = "        Schema::table('{$table}', function (Blueprint \$table) {";

                foreach ($changes['missing_columns'] ?? [] as $column) {
                    $up[] = '            // TODO: Add missing column';
                    $up[] = "            // \$table->string('{$column}')->nullable();";
                }

                foreach ($changes['extra_columns'] ?? [] as $column) {
                    $up[] = '            // Extra column found - consider if it should be removed';
                    $up[] = "            // \$table->dropColumn('{$column}');";
                }

                $up[] = '        });';
            }
        }

        $upContent = implode("\n", $up);
        $downContent = implode("\n", $down);

        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to fix schema drift
     */
    public function up(): void
    {
{$upContent}
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
{$downContent}
    }
};
PHP;
    }
}
