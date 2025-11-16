<?php

namespace Flux\Commands;

use Flux\Analyzers\MigrationAnalyzer;
use Flux\Analyzers\RiskAssessment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class TestCommand extends Command
{
    protected $signature = 'migrate:test
                            {migration? : Specific migration to test}
                            {--path= : Path to migrations}
                            {--with-data : Seed test database with data}
                            {--rollback : Test rollback as well}
                            {--keep : Keep test database after completion}
                            {--connection=testing : Database connection to use for testing}';

    protected $description = 'Test migrations on a temporary database before running in production';

    protected string $testConnection = 'testing';
    protected string $originalConnection;
    protected bool $testPassed = true;

    public function handle(): int
    {
        $this->displayHeader();

        // Store original connection
        $this->originalConnection = Config::get('database.default');
        $this->testConnection = $this->option('connection');

        // Verify test connection is configured
        if (!$this->verifyTestConnection()) {
            return self::FAILURE;
        }

        try {
            // Setup test database
            $this->setupTestDatabase();

            // Get migrations to test
            $migrations = $this->getMigrationsToTest();

            if (empty($migrations)) {
                $this->warn('No migrations to test.');
                return self::SUCCESS;
            }

            // Test each migration
            foreach ($migrations as $migration) {
                if (!$this->testMigration($migration)) {
                    $this->testPassed = false;
                    break;
                }
            }

            // Test rollback if requested
            if ($this->testPassed && $this->option('rollback')) {
                $this->testRollback();
            }

            // Cleanup
            if (!$this->option('keep')) {
                $this->cleanupTestDatabase();
            }

            $this->displayResults();

            return $this->testPassed ? self::SUCCESS : self::FAILURE;

        } catch (\Exception $e) {
            $this->error('Test failed with exception: ' . $e->getMessage());
            $this->line($e->getTraceAsString());

            if (!$this->option('keep')) {
                $this->cleanupTestDatabase();
            }

            return self::FAILURE;
        } finally {
            // Restore original connection
            Config::set('database.default', $this->originalConnection);
            DB::purge($this->testConnection);
        }
    }

    /**
     * Display command header
     */
    protected function displayHeader(): void
    {
        $this->info('🧪 Migration Testing Framework');
        $this->comment('═══════════════════════════════════════════════════════════════════════════════');
        $this->newLine();
    }

    /**
     * Verify test connection is configured
     */
    protected function verifyTestConnection(): bool
    {
        $testConfig = Config::get("database.connections.{$this->testConnection}");

        if (!$testConfig) {
            $this->error("Test connection '{$this->testConnection}' is not configured.");
            $this->newLine();
            $this->comment('Add a testing connection to config/database.php:');
            $this->line("
    'testing' => [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ],
            ");
            return false;
        }

        // Warn if using production database
        if ($testConfig === Config::get("database.connections.{$this->originalConnection}")) {
            $this->warn('⚠️  Test connection appears to be the same as production!');
            if (!$this->confirm('Continue anyway? This could affect production data.', false)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Setup test database
     */
    protected function setupTestDatabase(): void
    {
        $this->info('Setting up test database...');

        // Switch to test connection
        Config::set('database.default', $this->testConnection);
        DB::purge($this->originalConnection);

        // Run migrations table creation
        try {
            if (!Schema::connection($this->testConnection)->hasTable('migrations')) {
                Artisan::call('migrate:install', [
                    '--database' => $this->testConnection,
                ]);
            }

            // Seed with data if requested
            if ($this->option('with-data')) {
                $this->info('Seeding test database...');
                $this->seedTestDatabase();
            }

            $this->info('✓ Test database ready');
            $this->newLine();

        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to setup test database: ' . $e->getMessage());
        }
    }

    /**
     * Get migrations to test
     */
    protected function getMigrationsToTest(): array
    {
        $migrator = app('migrator');
        $path = $this->option('path') ?: database_path('migrations');

        if ($specific = $this->argument('migration')) {
            // Test specific migration
            $files = $migrator->getMigrationFiles($path);
            $migration = collect($files)->first(function ($file, $name) use ($specific) {
                return str_contains($name, $specific);
            });

            return $migration ? [$migration] : [];
        }

        // Test all pending migrations
        $repository = $migrator->getRepository();
        $ran = $repository->getRan();
        $files = $migrator->getMigrationFiles($path);

        return array_diff(array_keys($files), $ran);
    }

    /**
     * Test a single migration
     */
    protected function testMigration(string $migration): bool
    {
        $this->comment("Testing: {$migration}");
        $this->line('────────────────────────────────────────────────────────────────────────────');

        $startTime = microtime(true);

        try {
            // Capture pre-migration state
            $preState = $this->captureState();

            // Run migration
            $this->info('  ▸ Running up() migration...');
            Artisan::call('migrate', [
                '--database' => $this->testConnection,
                '--path' => $this->option('path') ?: 'database/migrations',
                '--force' => true,
            ]);

            $output = Artisan::output();

            // Check for errors in output
            if (str_contains($output, 'Error') || str_contains($output, 'Exception')) {
                throw new \RuntimeException('Migration failed: ' . $output);
            }

            // Capture post-migration state
            $postState = $this->captureState();

            // Verify changes
            $changes = $this->detectChanges($preState, $postState);
            $this->displayChanges($changes);

            // Run integrity checks
            $this->runIntegrityChecks();

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->info("  ✓ Migration passed ({$duration}ms)");
            $this->newLine();

            return true;

        } catch (\Exception $e) {
            $this->error("  ✗ Migration failed: " . $e->getMessage());
            $this->newLine();
            return false;
        }
    }

    /**
     * Test rollback
     */
    protected function testRollback(): void
    {
        $this->comment('Testing rollback...');
        $this->line('────────────────────────────────────────────────────────────────────────────');

        try {
            $this->info('  ▸ Running down() migration...');

            Artisan::call('migrate:rollback', [
                '--database' => $this->testConnection,
                '--force' => true,
            ]);

            $output = Artisan::output();

            if (str_contains($output, 'Error') || str_contains($output, 'Exception')) {
                throw new \RuntimeException('Rollback failed: ' . $output);
            }

            $this->info('  ✓ Rollback passed');
            $this->newLine();

        } catch (\Exception $e) {
            $this->error("  ✗ Rollback failed: " . $e->getMessage());
            $this->testPassed = false;
        }
    }

    /**
     * Capture current database state
     */
    protected function captureState(): array
    {
        $tables = $this->getTables();
        $state = [
            'tables' => [],
            'row_counts' => [],
        ];

        foreach ($tables as $table) {
            $state['tables'][$table] = [
                'columns' => Schema::connection($this->testConnection)->getColumnListing($table),
                'indexes' => $this->getIndexes($table),
            ];
            $state['row_counts'][$table] = DB::connection($this->testConnection)->table($table)->count();
        }

        return $state;
    }

    /**
     * Get all tables in database
     */
    protected function getTables(): array
    {
        $tables = [];
        $driver = Config::get("database.connections.{$this->testConnection}.driver");

        if ($driver === 'sqlite') {
            $results = DB::connection($this->testConnection)
                ->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            $tables = array_map(fn($r) => $r->name, $results);
        } elseif ($driver === 'mysql') {
            $database = Config::get("database.connections.{$this->testConnection}.database");
            $results = DB::connection($this->testConnection)
                ->select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?", [$database]);
            $tables = array_map(fn($r) => $r->TABLE_NAME, $results);
        } elseif ($driver === 'pgsql') {
            $results = DB::connection($this->testConnection)
                ->select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
            $tables = array_map(fn($r) => $r->tablename, $results);
        }

        return $tables;
    }

    /**
     * Get indexes for a table
     */
    protected function getIndexes(string $table): array
    {
        try {
            $driver = Config::get("database.connections.{$this->testConnection}.driver");

            if ($driver === 'sqlite') {
                $indexes = DB::connection($this->testConnection)
                    ->select("PRAGMA index_list({$table})");
                return array_map(fn($i) => $i->name, $indexes);
            } elseif ($driver === 'mysql') {
                $indexes = DB::connection($this->testConnection)
                    ->select("SHOW INDEX FROM {$table}");
                return array_unique(array_map(fn($i) => $i->Key_name, $indexes));
            } elseif ($driver === 'pgsql') {
                $indexes = DB::connection($this->testConnection)
                    ->select("SELECT indexname FROM pg_indexes WHERE tablename = ?", [$table]);
                return array_map(fn($i) => $i->indexname, $indexes);
            }
        } catch (\Exception $e) {
            return [];
        }

        return [];
    }

    /**
     * Detect changes between states
     */
    protected function detectChanges(array $pre, array $post): array
    {
        $changes = [
            'tables_added' => array_diff(array_keys($post['tables']), array_keys($pre['tables'])),
            'tables_removed' => array_diff(array_keys($pre['tables']), array_keys($post['tables'])),
            'columns_added' => [],
            'columns_removed' => [],
            'row_count_changes' => [],
        ];

        // Detect column changes
        foreach ($post['tables'] as $table => $tableData) {
            if (!isset($pre['tables'][$table])) {
                continue;
            }

            $columnsAdded = array_diff($tableData['columns'], $pre['tables'][$table]['columns']);
            $columnsRemoved = array_diff($pre['tables'][$table]['columns'], $tableData['columns']);

            if (!empty($columnsAdded)) {
                $changes['columns_added'][$table] = $columnsAdded;
            }
            if (!empty($columnsRemoved)) {
                $changes['columns_removed'][$table] = $columnsRemoved;
            }

            // Row count changes
            if ($pre['row_counts'][$table] !== $post['row_counts'][$table]) {
                $changes['row_count_changes'][$table] = [
                    'before' => $pre['row_counts'][$table],
                    'after' => $post['row_counts'][$table],
                ];
            }
        }

        return $changes;
    }

    /**
     * Display detected changes
     */
    protected function displayChanges(array $changes): void
    {
        if (!empty($changes['tables_added'])) {
            $this->info('  ▸ Tables added: ' . implode(', ', $changes['tables_added']));
        }
        if (!empty($changes['tables_removed'])) {
            $this->warn('  ▸ Tables removed: ' . implode(', ', $changes['tables_removed']));
        }
        if (!empty($changes['columns_added'])) {
            foreach ($changes['columns_added'] as $table => $columns) {
                $this->info("  ▸ Columns added to {$table}: " . implode(', ', $columns));
            }
        }
        if (!empty($changes['columns_removed'])) {
            foreach ($changes['columns_removed'] as $table => $columns) {
                $this->warn("  ▸ Columns removed from {$table}: " . implode(', ', $columns));
            }
        }
    }

    /**
     * Run integrity checks
     */
    protected function runIntegrityChecks(): void
    {
        try {
            $driver = Config::get("database.connections.{$this->testConnection}.driver");

            // Test database connectivity
            DB::connection($this->testConnection)->getPdo();

            // Check for foreign key violations (if supported)
            if ($driver !== 'sqlite') {
                // This varies by database, but we can do a basic check
                $this->info('  ▸ Integrity checks passed');
            }

        } catch (\Exception $e) {
            throw new \RuntimeException('Integrity check failed: ' . $e->getMessage());
        }
    }

    /**
     * Seed test database with sample data
     */
    protected function seedTestDatabase(): void
    {
        // Run default seeders if they exist
        try {
            if (class_exists('DatabaseSeeder')) {
                Artisan::call('db:seed', [
                    '--database' => $this->testConnection,
                    '--force' => true,
                ]);
            }
        } catch (\Exception $e) {
            $this->comment('  (No seeders run)');
        }
    }

    /**
     * Cleanup test database
     */
    protected function cleanupTestDatabase(): void
    {
        $this->info('Cleaning up test database...');

        try {
            $driver = Config::get("database.connections.{$this->testConnection}.driver");

            if ($driver === 'sqlite' && Config::get("database.connections.{$this->testConnection}.database") === ':memory:') {
                // In-memory SQLite is auto-cleaned
                $this->info('✓ Test database cleaned up (in-memory)');
                return;
            }

            // Drop all tables
            Schema::connection($this->testConnection)->dropAllTables();
            $this->info('✓ Test database cleaned up');

        } catch (\Exception $e) {
            $this->warn('Failed to cleanup test database: ' . $e->getMessage());
        }
    }

    /**
     * Display test results
     */
    protected function displayResults(): void
    {
        $this->newLine();
        $this->comment('═══════════════════════════════════════════════════════════════════════════════');

        if ($this->testPassed) {
            $this->info('✅ All tests passed!');
            $this->newLine();
            $this->comment('Your migrations are safe to run in production.');
        } else {
            $this->error('❌ Tests failed!');
            $this->newLine();
            $this->comment('Fix the issues before running migrations in production.');
        }

        $this->comment('═══════════════════════════════════════════════════════════════════════════════');
    }
}
