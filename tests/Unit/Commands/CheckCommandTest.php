<?php

use Flux\Commands\CheckCommand;
use Flux\Database\DatabaseAdapter;
use Flux\Database\DatabaseAdapterFactoryInterface;
use Flux\Snapshots\SnapshotManager;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->mockAdapter = Mockery::mock(DatabaseAdapter::class);
    $this->mockFactory = Mockery::mock(DatabaseAdapterFactoryInterface::class);
    $this->mockSnapshotManager = Mockery::mock(SnapshotManager::class);

    // Setup factory to return adapter
    $this->mockFactory->shouldReceive('create')->andReturn($this->mockAdapter)->byDefault();

    // Create command mock
    $this->command = Mockery::mock(CheckCommand::class)->makePartial()->shouldAllowMockingProtectedMethods();

    // Set the dependencies on the command using reflection
    $reflection = new ReflectionClass($this->command);

    $snapshotProperty = $reflection->getProperty('snapshotManager');
    $snapshotProperty->setAccessible(true);
    $snapshotProperty->setValue($this->command, $this->mockSnapshotManager);

    $factoryProperty = $reflection->getProperty('adapterFactory');
    $factoryProperty->setAccessible(true);
    $factoryProperty->setValue($this->command, $this->mockFactory);

    // Mock the ignore-version-mismatch option by default
    $this->command->shouldReceive('option')->with('ignore-version-mismatch')->andReturn(false)->byDefault();

    // Mock format version mismatch check by default (no mismatch)
    $this->mockSnapshotManager->shouldReceive('hasFormatVersionMismatch')->andReturn(false)->byDefault();
});

afterEach(function () {
    Mockery::close();
});

describe('handle method', function () {
    it('shows warning when no baseline schema is found', function () {
        // Set up expectations
        $this->mockFactory->shouldReceive('create')->twice()->andReturn($this->mockAdapter);

        // Mock console output
        $this->command->shouldReceive('info')->with('🔍 Smart Migration - Drift Detection')->once();
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════')->once();
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('warn')->with('⚠️  No baseline schema found. Run migrate:snapshot to create one.')->once();
        $this->command->shouldReceive('option')->with('snapshot')->andReturn(false);

        // Mock getCurrentDatabaseSchema
        $currentSchema = ['tables' => ['users' => ['columns' => []]]];
        $this->mockAdapter->shouldReceive('getAllTables')->andReturn(['users']);
        $this->mockAdapter->shouldReceive('getTableColumns')->with('users')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableIndexes')->with('users')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableForeignKeys')->with('users')->andReturn([]);

        // Mock getSnapshotSchema returns null (no snapshot)
        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn(null);

        // Mock DB::table for migration check
        $mockBuilder = Mockery::mock();
        $mockBuilder->shouldReceive('orderBy')->andReturn($mockBuilder);
        $mockBuilder->shouldReceive('get')->andReturn(collect());
        DB::shouldReceive('table')->with('migrations')->andReturn($mockBuilder);

        // Execute
        $result = $this->command->handle();

        // Assert
        expect($result)->toBe(1); // FAILURE
    });

    it('shows success when no drift is detected', function () {
        // Set up expectations
        $this->mockFactory->shouldReceive('create')->twice()->andReturn($this->mockAdapter);

        // Mock console output
        $this->command->shouldReceive('info')->with('🔍 Smart Migration - Drift Detection')->once();
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════')->once();
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('info')->with('✅ No schema drift detected! Database matches expected state.')->once();
        $this->command->shouldReceive('option')->with('snapshot')->andReturn(false);

        $schema = ['tables' => ['users' => [
            'columns' => [['name' => 'id', 'type' => 'integer']],
            'indexes' => [],
            'foreign_keys' => [],
        ]]];

        // Mock getCurrentDatabaseSchema
        $this->mockAdapter->shouldReceive('getAllTables')->andReturn(['users']);
        $this->mockAdapter->shouldReceive('getTableColumns')->with('users')->andReturn([['name' => 'id', 'type' => 'integer']]);
        $this->mockAdapter->shouldReceive('getTableIndexes')->with('users')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableForeignKeys')->with('users')->andReturn([]);

        // Mock getSnapshotSchema returns same schema
        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn([
            'schema' => $schema,
        ]);

        // Execute
        $result = $this->command->handle();

        // Assert
        expect($result)->toBe(0); // SUCCESS
    });

    it('detects missing tables', function () {
        // Set up expectations
        $this->mockFactory->shouldReceive('create')->twice()->andReturn($this->mockAdapter);

        // Mock console output
        $this->command->shouldReceive('info')->with('🔍 Smart Migration - Drift Detection')->once();
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════')->once();
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('error')->with('⚠️  Schema Drift Detected!')->once();
        $this->command->shouldReceive('warn')->with('📋 Missing Tables (exist in migrations but not in database):')->once();
        $this->command->shouldReceive('line')->with('   - users')->once();
        $this->command->shouldReceive('warn')->with('⚠️  Run with --fix to generate a migration that resolves the drift.')->once();
        $this->command->shouldReceive('option')->with('snapshot')->andReturn(false);
        $this->command->shouldReceive('option')->with('fix')->andReturn(false);

        // Current schema has no tables
        $this->mockAdapter->shouldReceive('getAllTables')->andReturn([]);

        // Expected schema has users table
        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn([
            'schema' => [
                'tables' => [
                    'users' => [
                        'columns' => [['name' => 'id', 'type' => 'integer']],
                        'indexes' => [],
                        'foreign_keys' => [],
                    ],
                ],
            ],
        ]);

        // Execute
        $result = $this->command->handle();

        // Assert drift detected
        expect($result)->toBe(1); // FAILURE
    });

    it('detects extra tables', function () {
        // Set up expectations
        $this->mockFactory->shouldReceive('create')->twice()->andReturn($this->mockAdapter);

        // Mock console output
        $this->command->shouldReceive('info')->with('🔍 Smart Migration - Drift Detection')->once();
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════')->once();
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('error')->with('⚠️  Schema Drift Detected!')->once();
        $this->command->shouldReceive('warn')->with('📋 Extra Tables (exist in database but not in migrations):')->once();
        $this->command->shouldReceive('line')->with('   - posts')->once();
        $this->command->shouldReceive('warn')->with('⚠️  Run with --fix to generate a migration that resolves the drift.')->once();
        $this->command->shouldReceive('option')->with('snapshot')->andReturn(false);
        $this->command->shouldReceive('option')->with('fix')->andReturn(false);

        // Current schema has extra table
        $this->mockAdapter->shouldReceive('getAllTables')->andReturn(['users', 'posts']);
        $this->mockAdapter->shouldReceive('getTableColumns')->with('users')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableIndexes')->with('users')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableForeignKeys')->with('users')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableColumns')->with('posts')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableIndexes')->with('posts')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableForeignKeys')->with('posts')->andReturn([]);

        // Expected schema has only users table
        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn([
            'schema' => [
                'tables' => [
                    'users' => [
                        'columns' => [],
                        'indexes' => [],
                        'foreign_keys' => [],
                    ],
                ],
            ],
        ]);

        // Execute
        $result = $this->command->handle();

        // Assert drift detected
        expect($result)->toBe(1); // FAILURE
    });

    it('generates fix migration when --fix option is used', function () {
        // Set up expectations
        $this->mockFactory->shouldReceive('create')->twice()->andReturn($this->mockAdapter);

        // Mock console output
        $this->command->shouldReceive('info')->with('🔍 Smart Migration - Drift Detection')->once();
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════')->once();
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('error')->with('⚠️  Schema Drift Detected!')->once();
        $this->command->shouldReceive('warn')->with('📋 Missing Tables (exist in migrations but not in database):')->once();
        $this->command->shouldReceive('line')->with('   - users')->once();
        $this->command->shouldReceive('info')->with('🔧 Generating migration to fix drift...')->once();
        $this->command->shouldReceive('info')->with(Mockery::pattern('/✅ Created migration: .*\.php/'))->once();
        $this->command->shouldReceive('comment')->with('Review the generated migration and run migrate:safe to apply it.')->once();

        // Current schema is empty
        $this->mockAdapter->shouldReceive('getAllTables')->andReturn([]);

        // Expected schema has users table
        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn([
            'schema' => [
                'tables' => [
                    'users' => [
                        'columns' => [['name' => 'id', 'type' => 'integer']],
                        'indexes' => [],
                        'foreign_keys' => [],
                    ],
                ],
            ],
        ]);

        // Mock file operations for migration generation
        $migrationPath = database_path('migrations');
        if (! is_dir($migrationPath)) {
            mkdir($migrationPath, 0755, true);
        }

        // Set the fix option
        $this->command->shouldReceive('option')->with('fix')->andReturn(true);
        $this->command->shouldReceive('option')->with('snapshot')->andReturn(false);
        $this->command->shouldReceive('option')->with('details')->andReturn(false);

        // Execute
        $result = $this->command->handle();

        // Assert success (fix migration generated)
        expect($result)->toBe(0);

        // Clean up generated migration files
        $files = glob($migrationPath.'/*_fix_schema_drift.php');
        foreach ($files as $file) {
            unlink($file);
        }
    });

    it('ignores tables based on config', function () {
        // Set up expectations
        $this->mockFactory->shouldReceive('create')->twice()->andReturn($this->mockAdapter);

        // Mock console output
        $this->command->shouldReceive('info')->with('🔍 Smart Migration - Drift Detection')->once();
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════')->once();
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('info')->with('✅ No schema drift detected! Database matches expected state.')->once();
        $this->command->shouldReceive('option')->with('snapshot')->andReturn(false);

        // Mock config to ignore migrations table
        config(['smart-migration.drift.ignored_tables' => ['migrations']]);

        // Current schema has users and migrations tables
        $this->mockAdapter->shouldReceive('getAllTables')->andReturn(['users', 'migrations']);
        $this->mockAdapter->shouldReceive('getTableColumns')->with('users')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableIndexes')->with('users')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableForeignKeys')->with('users')->andReturn([]);
        // migrations table should be skipped

        // Expected schema has only users table
        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn([
            'schema' => [
                'tables' => [
                    'users' => [
                        'columns' => [],
                        'indexes' => [],
                        'foreign_keys' => [],
                    ],
                ],
            ],
        ]);

        // Execute
        $result = $this->command->handle();

        // Assert no drift (migrations table ignored)
        expect($result)->toBe(0);
    });

    it('ignores columns based on config', function () {
        // Set up expectations
        $this->mockFactory->shouldReceive('create')->twice()->andReturn($this->mockAdapter);

        // Mock console output
        $this->command->shouldReceive('info')->with('🔍 Smart Migration - Drift Detection')->once();
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════')->once();
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('info')->with('✅ No schema drift detected! Database matches expected state.')->once();
        $this->command->shouldReceive('option')->with('snapshot')->andReturn(false);

        // Mock config to ignore timestamps
        config(['smart-migration.drift.ignored_columns' => ['created_at', 'updated_at']]);

        // Current schema has extra timestamp columns
        $this->mockAdapter->shouldReceive('getAllTables')->andReturn(['users']);
        $this->mockAdapter->shouldReceive('getTableColumns')->with('users')->andReturn([
            ['name' => 'id', 'type' => 'integer'],
            ['name' => 'name', 'type' => 'string'],
            ['name' => 'created_at', 'type' => 'timestamp'],
            ['name' => 'updated_at', 'type' => 'timestamp'],
        ]);
        $this->mockAdapter->shouldReceive('getTableIndexes')->with('users')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableForeignKeys')->with('users')->andReturn([]);

        // Expected schema doesn't have timestamps
        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn([
            'schema' => [
                'tables' => [
                    'users' => [
                        'columns' => [
                            ['name' => 'id', 'type' => 'integer'],
                            ['name' => 'name', 'type' => 'string'],
                        ],
                        'indexes' => [],
                        'foreign_keys' => [],
                    ],
                ],
            ],
        ]);

        // Execute
        $result = $this->command->handle();

        // Assert no drift (timestamps ignored)
        expect($result)->toBe(0);
    });
});

describe('constructor', function () {
    it('properly injects dependencies (covers lines 27-29)', function () {
        // Create a real command instance to test constructor
        $command = new CheckCommand;

        // Verify the dependencies were injected using reflection
        $reflection = new ReflectionClass($command);

        $snapshotProperty = $reflection->getProperty('snapshotManager');
        $snapshotProperty->setAccessible(true);
        $snapshotManager = $snapshotProperty->getValue($command);

        $factoryProperty = $reflection->getProperty('adapterFactory');
        $factoryProperty->setAccessible(true);
        $adapterFactory = $factoryProperty->getValue($command);

        expect($snapshotManager)->toBeInstanceOf(SnapshotManager::class);
        expect($adapterFactory)->toBeInstanceOf(DatabaseAdapterFactoryInterface::class);
    });
});

describe('snapshot option', function () {
    it('uses snapshot when --snapshot option is provided (covers line 45)', function () {
        // Set up expectations
        $this->mockFactory->shouldReceive('create')->twice()->andReturn($this->mockAdapter);

        // Mock console output
        $this->command->shouldReceive('info')->with('🔍 Smart Migration - Drift Detection')->once();
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════')->once();
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('info')->with('✅ No schema drift detected! Database matches expected state.')->once();

        // Mock --snapshot option to true (covers line 45)
        $this->command->shouldReceive('option')->with('snapshot')->andReturn(true);

        $schema = ['tables' => ['users' => [
            'columns' => [['name' => 'id', 'type' => 'integer']],
            'indexes' => [],
            'foreign_keys' => [],
        ]]];

        // Mock getCurrentDatabaseSchema
        $this->mockAdapter->shouldReceive('getAllTables')->andReturn(['users']);
        $this->mockAdapter->shouldReceive('getTableColumns')->with('users')->andReturn([['name' => 'id', 'type' => 'integer']]);
        $this->mockAdapter->shouldReceive('getTableIndexes')->with('users')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableForeignKeys')->with('users')->andReturn([]);

        // Mock getSnapshotSchema returns same schema (covers lines 117-119)
        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn([
            'schema' => $schema,
        ]);

        // Execute
        $result = $this->command->handle();

        // Assert
        expect($result)->toBe(0); // SUCCESS
    });
});

describe('migration history logic', function () {
    it('builds schema from migration history when no snapshot exists (covers lines 139-142)', function () {
        // Set up expectations - create is called 3 times: once in handle, once in getCurrentDatabaseSchema for current, once for expected
        $this->mockFactory->shouldReceive('create')->times(3)->andReturn($this->mockAdapter);

        // Mock console output
        $this->command->shouldReceive('info')->with('🔍 Smart Migration - Drift Detection')->once();
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════')->once();
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('info')->with('✅ No schema drift detected! Database matches expected state.')->once();
        $this->command->shouldReceive('warn')->with('Building expected schema from migration history...')->once(); // Line 139
        $this->command->shouldReceive('option')->with('snapshot')->andReturn(false);

        $schema = ['tables' => ['users' => [
            'columns' => [['name' => 'id', 'type' => 'integer']],
            'indexes' => [],
            'foreign_keys' => [],
        ]]];

        // Mock getCurrentDatabaseSchema (called twice - once for current, once for expected)
        $this->mockAdapter->shouldReceive('getAllTables')->times(2)->andReturn(['users']);
        $this->mockAdapter->shouldReceive('getTableColumns')->with('users')->times(2)->andReturn([['name' => 'id', 'type' => 'integer']]);
        $this->mockAdapter->shouldReceive('getTableIndexes')->with('users')->times(2)->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableForeignKeys')->with('users')->times(2)->andReturn([]);

        // Mock getSnapshotSchema returns null initially
        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn(null);

        // Mock DB::table for migration check with non-empty migrations (covers lines 139-142)
        $mockBuilder = Mockery::mock();
        $mockBuilder->shouldReceive('orderBy')->andReturn($mockBuilder);
        $mockBuilder->shouldReceive('get')->andReturn(collect([
            (object) ['migration' => '2023_01_01_000000_create_users_table', 'batch' => 1],
        ]));
        DB::shouldReceive('table')->with('migrations')->andReturn($mockBuilder);

        // Execute
        $result = $this->command->handle();

        // Assert
        expect($result)->toBe(0); // SUCCESS
    });
});

describe('detailed drift detection', function () {
    it('detects missing and extra columns and indexes (covers lines 179, 183, 194, 203-204, 209-210, 215)', function () {
        // Set up expectations
        $this->mockFactory->shouldReceive('create')->twice()->andReturn($this->mockAdapter);

        // Mock console output
        $this->command->shouldReceive('info')->with('🔍 Smart Migration - Drift Detection')->once();
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════')->once();
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('error')->with('⚠️  Schema Drift Detected!')->once();
        $this->command->shouldReceive('warn')->with('📋 Table Differences:')->once();
        $this->command->shouldReceive('info')->with('   Table: users')->once();
        $this->command->shouldReceive('line')->with('     Missing columns: email')->once();
        $this->command->shouldReceive('line')->with('     Extra columns: phone')->once();
        $this->command->shouldReceive('line')->with('     Missing indexes: idx_email')->once();
        $this->command->shouldReceive('line')->with('     Extra indexes: idx_phone')->once();
        $this->command->shouldReceive('warn')->with('⚠️  Run with --fix to generate a migration that resolves the drift.')->once();
        $this->command->shouldReceive('option')->with('snapshot')->andReturn(false);
        $this->command->shouldReceive('option')->with('fix')->andReturn(false);

        // Mock config for ignored columns (covers line 179)
        config(['smart-migration.drift.ignored_columns' => ['created_at']]);

        // Current schema has different columns and indexes
        $this->mockAdapter->shouldReceive('getAllTables')->andReturn(['users']);
        $this->mockAdapter->shouldReceive('getTableColumns')->with('users')->andReturn([
            ['name' => 'id', 'type' => 'integer'],
            ['name' => 'phone', 'type' => 'string'], // Extra column (covers line 194)
            ['name' => 'created_at', 'type' => 'timestamp'], // Should be ignored
        ]);
        $this->mockAdapter->shouldReceive('getTableIndexes')->with('users')->andReturn([
            ['name' => 'idx_phone', 'columns' => ['phone']], // Extra index (covers lines 209-210)
        ]);
        $this->mockAdapter->shouldReceive('getTableForeignKeys')->with('users')->andReturn([]);

        // Expected schema has different columns and indexes
        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn([
            'schema' => [
                'tables' => [
                    'users' => [
                        'columns' => [
                            ['name' => 'id', 'type' => 'integer'],
                            ['name' => 'email', 'type' => 'string'], // Missing column (covers line 183)
                            ['name' => 'created_at', 'type' => 'timestamp'], // Should be ignored
                        ],
                        'indexes' => [
                            ['name' => 'idx_email', 'columns' => ['email']], // Missing index (covers lines 203-204)
                        ],
                        'foreign_keys' => [],
                    ],
                ],
            ],
        ]);

        // Execute
        $result = $this->command->handle();

        // Assert drift detected (covers line 215 - table changes assignment)
        expect($result)->toBe(1); // FAILURE
    });
});

describe('migration generation with complex drift', function () {
    it('generates migration for extra tables and table changes (covers lines 319-320, 325-338)', function () {
        // Set up expectations
        $this->mockFactory->shouldReceive('create')->twice()->andReturn($this->mockAdapter);

        // Mock console output
        $this->command->shouldReceive('info')->with('🔍 Smart Migration - Drift Detection')->once();
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════')->once();
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('error')->with('⚠️  Schema Drift Detected!')->once();
        $this->command->shouldReceive('warn')->with('📋 Extra Tables (exist in database but not in migrations):')->once();
        $this->command->shouldReceive('line')->with('   - posts')->once();
        $this->command->shouldReceive('warn')->with('📋 Table Differences:')->once();
        $this->command->shouldReceive('info')->with('   Table: users')->once();
        $this->command->shouldReceive('line')->with('     Missing columns: email')->once();
        $this->command->shouldReceive('line')->with('     Extra columns: phone')->once();
        $this->command->shouldReceive('info')->with('🔧 Generating migration to fix drift...')->once();
        $this->command->shouldReceive('info')->with(Mockery::pattern('/✅ Created migration: .*\.php/'))->once();
        $this->command->shouldReceive('comment')->with('Review the generated migration and run migrate:safe to apply it.')->once();

        // Mock options
        $this->command->shouldReceive('option')->with('snapshot')->andReturn(false);
        $this->command->shouldReceive('option')->with('fix')->andReturn(true);

        // Current schema has extra table and column differences
        $this->mockAdapter->shouldReceive('getAllTables')->andReturn(['users', 'posts']);
        $this->mockAdapter->shouldReceive('getTableColumns')->with('users')->andReturn([
            ['name' => 'id', 'type' => 'integer'],
            ['name' => 'phone', 'type' => 'string'], // Extra column
        ]);
        $this->mockAdapter->shouldReceive('getTableIndexes')->with('users')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableForeignKeys')->with('users')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableColumns')->with('posts')->andReturn([
            ['name' => 'id', 'type' => 'integer'],
        ]);
        $this->mockAdapter->shouldReceive('getTableIndexes')->with('posts')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableForeignKeys')->with('posts')->andReturn([]);

        // Expected schema has only users table with email column
        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn([
            'schema' => [
                'tables' => [
                    'users' => [
                        'columns' => [
                            ['name' => 'id', 'type' => 'integer'],
                            ['name' => 'email', 'type' => 'string'], // Missing column
                        ],
                        'indexes' => [],
                        'foreign_keys' => [],
                    ],
                ],
            ],
        ]);

        // Mock file operations for migration generation
        $migrationPath = database_path('migrations');
        if (! is_dir($migrationPath)) {
            mkdir($migrationPath, 0755, true);
        }

        // Execute
        $result = $this->command->handle();

        // Assert success
        expect($result)->toBe(0);

        // Verify migration content includes both extra tables and table changes
        $files = glob($migrationPath.'/*_fix_schema_drift.php');
        expect(count($files))->toBeGreaterThan(0);

        $migrationContent = file_get_contents($files[0]);

        // Should contain extra table comment (lines 319-320)
        expect($migrationContent)->toContain("Extra table 'posts' found");

        // Should contain table changes (lines 325-338)
        expect($migrationContent)->toContain("Schema::table('users'");
        expect($migrationContent)->toContain('Add missing column');
        expect($migrationContent)->toContain('Extra column found');

        // Clean up generated migration files
        foreach ($files as $file) {
            unlink($file);
        }
    });
});

describe('ignored column handling', function () {
    it('properly handles ignored columns in drift detection (covers line 179)', function () {
        // Set up expectations
        $this->mockFactory->shouldReceive('create')->twice()->andReturn($this->mockAdapter);

        // Mock console output for no drift detected
        $this->command->shouldReceive('info')->with('🔍 Smart Migration - Drift Detection')->once();
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════')->once();
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('info')->with('✅ No schema drift detected! Database matches expected state.')->once();
        $this->command->shouldReceive('option')->with('snapshot')->andReturn(false);

        // Mock config to ignore specific columns
        config(['smart-migration.drift.ignored_columns' => ['created_at', 'updated_at']]);

        // Current schema has the ignored columns as extra
        $this->mockAdapter->shouldReceive('getAllTables')->andReturn(['users']);
        $this->mockAdapter->shouldReceive('getTableColumns')->with('users')->andReturn([
            ['name' => 'id', 'type' => 'integer'],
            ['name' => 'created_at', 'type' => 'timestamp'], // Should be ignored
            ['name' => 'updated_at', 'type' => 'timestamp'],  // Should be ignored
        ]);
        $this->mockAdapter->shouldReceive('getTableIndexes')->with('users')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableForeignKeys')->with('users')->andReturn([]);

        // Expected schema doesn't have the ignored columns
        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn([
            'schema' => [
                'tables' => [
                    'users' => [
                        'columns' => [
                            ['name' => 'id', 'type' => 'integer'],
                        ],
                        'indexes' => [],
                        'foreign_keys' => [],
                    ],
                ],
            ],
        ]);

        // Execute
        $result = $this->command->handle();

        // Assert no drift detected due to ignored columns (covers line 179 continue)
        expect($result)->toBe(0);
    });
});

describe('displayDrift table changes coverage', function () {
    it('covers all table changes display logic (lines 257-274)', function () {
        // Set up expectations
        $this->mockFactory->shouldReceive('create')->twice()->andReturn($this->mockAdapter);

        // Mock console output - need to cover ALL the specific lines in displayDrift
        $this->command->shouldReceive('info')->with('🔍 Smart Migration - Drift Detection')->once();
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════')->once();
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('error')->with('⚠️  Schema Drift Detected!')->once();
        $this->command->shouldReceive('warn')->with('📋 Table Differences:')->once(); // Line 257
        $this->command->shouldReceive('info')->with('   Table: users')->once(); // Line 259
        $this->command->shouldReceive('line')->with('     Missing columns: email, username')->once(); // Line 262
        $this->command->shouldReceive('line')->with('     Extra columns: phone, address')->once(); // Line 265
        $this->command->shouldReceive('line')->with('     Missing indexes: idx_email, idx_username')->once(); // Line 268
        $this->command->shouldReceive('line')->with('     Extra indexes: idx_phone, idx_address')->once(); // Line 271
        $this->command->shouldReceive('warn')->with('⚠️  Run with --fix to generate a migration that resolves the drift.')->once();
        $this->command->shouldReceive('option')->with('snapshot')->andReturn(false);
        $this->command->shouldReceive('option')->with('fix')->andReturn(false);

        // Current schema with multiple differences
        $this->mockAdapter->shouldReceive('getAllTables')->andReturn(['users']);
        $this->mockAdapter->shouldReceive('getTableColumns')->with('users')->andReturn([
            ['name' => 'id', 'type' => 'integer'],
            ['name' => 'phone', 'type' => 'string'],    // Extra
            ['name' => 'address', 'type' => 'string'],   // Extra
        ]);
        $this->mockAdapter->shouldReceive('getTableIndexes')->with('users')->andReturn([
            ['name' => 'idx_phone', 'columns' => ['phone']],     // Extra
            ['name' => 'idx_address', 'columns' => ['address']],  // Extra
        ]);
        $this->mockAdapter->shouldReceive('getTableForeignKeys')->with('users')->andReturn([]);

        // Expected schema with different columns and indexes
        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn([
            'schema' => [
                'tables' => [
                    'users' => [
                        'columns' => [
                            ['name' => 'id', 'type' => 'integer'],
                            ['name' => 'email', 'type' => 'string'],     // Missing
                            ['name' => 'username', 'type' => 'string'],   // Missing
                        ],
                        'indexes' => [
                            ['name' => 'idx_email', 'columns' => ['email']],         // Missing
                            ['name' => 'idx_username', 'columns' => ['username']],    // Missing
                        ],
                        'foreign_keys' => [],
                    ],
                ],
            ],
        ]);

        // Execute
        $result = $this->command->handle();

        // Assert drift detected
        expect($result)->toBe(1); // FAILURE
    });
});

describe('getSnapshotSchema method', function () {
    it('displays warning when snapshot has format version mismatch', function () {
        $snapshot = [
            'name' => 'old_snapshot',
            'version' => '1.0',
            'format_version' => '0.9.0', // Old version
            'schema' => ['tables' => ['users' => ['columns' => []]]],
        ];

        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn($snapshot);
        $this->mockSnapshotManager->shouldReceive('hasFormatVersionMismatch')->with($snapshot)->andReturn(true);
        $this->mockSnapshotManager->shouldReceive('getFormatVersionWarning')->with($snapshot)->andReturn('Version mismatch warning');

        $this->command->shouldReceive('newLine')->twice();
        $this->command->shouldReceive('warn')->with('Version mismatch warning')->once();

        $result = $this->command->getSnapshotSchema();

        expect($result)->toEqual($snapshot['schema']);
    });

    it('suppresses warning when --ignore-version-mismatch is used', function () {
        $snapshot = [
            'name' => 'old_snapshot',
            'version' => '1.0',
            'format_version' => '0.9.0',
            'schema' => ['tables' => ['users' => ['columns' => []]]],
        ];

        $this->command->shouldReceive('option')->with('ignore-version-mismatch')->andReturn(true);
        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn($snapshot);

        // Should NOT call warn when flag is set
        $this->command->shouldReceive('warn')->never();

        $result = $this->command->getSnapshotSchema();

        expect($result)->toEqual($snapshot['schema']);
    });

    it('returns null when no snapshot exists', function () {
        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn(null);

        $result = $this->command->getSnapshotSchema();

        expect($result)->toBeNull();
    });
});

describe('getExpectedSchemaFromMigrations method', function () {
    it('displays warning when snapshot has format version mismatch', function () {
        $snapshot = [
            'name' => 'old_snapshot',
            'version' => '1.0',
            'format_version' => '0.9.0', // Old version
            'schema' => ['tables' => ['users' => ['columns' => []]]],
        ];

        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn($snapshot);
        $this->mockSnapshotManager->shouldReceive('hasFormatVersionMismatch')->with($snapshot)->andReturn(true);
        $this->mockSnapshotManager->shouldReceive('getFormatVersionWarning')->with($snapshot)->andReturn('Version mismatch warning');

        $this->command->shouldReceive('newLine')->twice();
        $this->command->shouldReceive('warn')->with('Version mismatch warning')->once();

        $result = $this->command->getExpectedSchemaFromMigrations();

        expect($result)->toEqual($snapshot['schema']);
    });

    it('suppresses warning when --ignore-version-mismatch is used', function () {
        $snapshot = [
            'name' => 'old_snapshot',
            'version' => '1.0',
            'format_version' => '0.9.0',
            'schema' => ['tables' => ['users' => ['columns' => []]]],
        ];

        $this->command->shouldReceive('option')->with('ignore-version-mismatch')->andReturn(true);
        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn($snapshot);

        // Should NOT call warn when flag is set
        $this->command->shouldReceive('warn')->never();

        $result = $this->command->getExpectedSchemaFromMigrations();

        expect($result)->toEqual($snapshot['schema']);
    });
});
