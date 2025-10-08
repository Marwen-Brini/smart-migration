<?php

use Flux\Commands\DiffCommand;
use Flux\Database\DatabaseAdapter;
use Flux\Database\DatabaseAdapterFactoryInterface;
use Flux\Generators\MigrationBuilder;
use Flux\Generators\SchemaComparator;
use Flux\Snapshots\SnapshotManager;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->mockAdapter = Mockery::mock(DatabaseAdapter::class);
    $this->mockFactory = Mockery::mock(DatabaseAdapterFactoryInterface::class);
    $this->mockComparator = Mockery::mock(SchemaComparator::class);
    $this->mockBuilder = Mockery::mock(MigrationBuilder::class);
    $this->mockSnapshotManager = Mockery::mock(SnapshotManager::class);

    // Setup factory to return adapter
    $this->mockFactory->shouldReceive('create')->andReturn($this->mockAdapter)->byDefault();

    // Create command mock
    $this->command = Mockery::mock(DiffCommand::class)->makePartial()->shouldAllowMockingProtectedMethods();

    // Set the dependencies on the command using reflection
    $reflection = new ReflectionClass($this->command);

    $comparatorProperty = $reflection->getProperty('comparator');
    $comparatorProperty->setAccessible(true);
    $comparatorProperty->setValue($this->command, $this->mockComparator);

    $builderProperty = $reflection->getProperty('builder');
    $builderProperty->setAccessible(true);
    $builderProperty->setValue($this->command, $this->mockBuilder);

    $snapshotsProperty = $reflection->getProperty('snapshots');
    $snapshotsProperty->setAccessible(true);
    $snapshotsProperty->setValue($this->command, $this->mockSnapshotManager);

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
    it('shows no differences when database is in sync', function () {
        // Mock displayHeader
        $this->command->shouldReceive('displayHeader')->once();
        $this->command->shouldReceive('line')->with('Building schema from migration history...')->once();
        $this->command->shouldReceive('line')->with('Capturing current database schema...')->once();
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('infoWithEmoji')->with('Analyzing database changes...', 'mag')->once();
        $this->command->shouldReceive('infoWithEmoji')
            ->with('No differences detected! Database is in sync with migrations.', 'white_check_mark')
            ->once();

        // Mock buildSchemaFromMigrations
        $migrationSchema = ['tables' => []];
        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn(null);
        $this->command->shouldReceive('line')->with('<fg=gray>  No snapshot available, using empty baseline</>')->once();

        // Mock captureCurrentSchema
        $this->mockAdapter->shouldReceive('getDriverName')->andReturn('mysql');
        $this->mockAdapter->shouldReceive('getAllTables')->andReturn([]);
        $this->command->shouldReceive('line')->with(Mockery::pattern('/Database: mysql:/'))->once();
        $this->command->shouldReceive('option')->with('tables')->andReturn(null);

        // Mock comparator - no differences
        $this->mockComparator->shouldReceive('compare')
            ->with($migrationSchema, ['tables' => []])
            ->andReturn([
                'tables_to_create' => [],
                'tables_to_drop' => [],
                'tables_to_modify' => [],
            ]);

        // Execute
        $result = $this->command->handle();

        // Assert
        expect($result)->toBe(0); // SUCCESS
    });

    it('detects new tables and generates migration', function () {
        File::shouldReceive('put')->once();

        // Mock displayHeader
        $this->command->shouldReceive('displayHeader')->once();
        $this->command->shouldReceive('line')->atLeast()->once();
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('infoWithEmoji')->atLeast()->once();

        // Mock schema building
        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn(null);

        // Mock database capture
        $this->mockAdapter->shouldReceive('getDriverName')->andReturn('mysql');
        $this->mockAdapter->shouldReceive('getAllTables')->andReturn(['new_table']);
        $this->mockAdapter->shouldReceive('getTableColumns')->with('new_table')->andReturn([
            ['name' => 'id', 'type' => 'bigint'],
        ]);
        $this->mockAdapter->shouldReceive('getTableIndexes')->with('new_table')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableForeignKeys')->with('new_table')->andReturn([]);
        $this->command->shouldReceive('option')->with('tables')->andReturn(null);

        // Mock comparator - has differences
        $differences = [
            'tables_to_create' => [
                'new_table' => [
                    'columns' => [['name' => 'id', 'type' => 'bigint']],
                ],
            ],
            'tables_to_drop' => [],
            'tables_to_modify' => [],
        ];
        $this->mockComparator->shouldReceive('compare')->andReturn($differences);

        // Mock displayDifferences
        $this->command->shouldReceive('option')->with('dry-run')->andReturn(false);
        $this->command->shouldReceive('option')->with('force')->andReturn(true);
        $this->command->shouldReceive('option')->with('name')->andReturn('create_new_table');

        // Mock builder
        $migrationCode = '<?php migration code';
        $this->mockBuilder->shouldReceive('build')
            ->with($differences, 'create_new_table')
            ->andReturn($migrationCode);

        // Execute
        $result = $this->command->handle();

        // Assert
        expect($result)->toBe(0); // SUCCESS
    });

    it('exits in dry-run mode without generating migration', function () {
        // Mock displayHeader
        $this->command->shouldReceive('displayHeader')->once();
        $this->command->shouldReceive('line')->atLeast()->once();
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('infoWithEmoji')->atLeast()->once();
        $this->command->shouldReceive('commentWithEmoji')
            ->with('Dry-run mode - no migration file generated.', 'eyes')
            ->once();

        // Mock schema building
        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn(null);

        // Mock database capture
        $this->mockAdapter->shouldReceive('getDriverName')->andReturn('mysql');
        $this->mockAdapter->shouldReceive('getAllTables')->andReturn(['test_table']);
        $this->mockAdapter->shouldReceive('getTableColumns')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableIndexes')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableForeignKeys')->andReturn([]);
        $this->command->shouldReceive('option')->with('tables')->andReturn(null);

        // Mock comparator - has differences
        $this->mockComparator->shouldReceive('compare')->andReturn([
            'tables_to_create' => ['test_table' => ['columns' => []]],
            'tables_to_drop' => [],
            'tables_to_modify' => [],
        ]);

        // Mock dry-run option
        $this->command->shouldReceive('option')->with('dry-run')->andReturn(true);

        // Execute
        $result = $this->command->handle();

        // Assert
        expect($result)->toBe(0); // SUCCESS
    });

    it('cancels generation when user declines confirmation', function () {
        // Mock displayHeader
        $this->command->shouldReceive('displayHeader')->once();
        $this->command->shouldReceive('line')->atLeast()->once();
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('infoWithEmoji')->atLeast()->once();
        $this->command->shouldReceive('warnWithEmoji')
            ->with('Migration generation cancelled.', 'x')
            ->once();

        // Mock schema building
        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn(null);

        // Mock database capture
        $this->mockAdapter->shouldReceive('getDriverName')->andReturn('mysql');
        $this->mockAdapter->shouldReceive('getAllTables')->andReturn(['test_table']);
        $this->mockAdapter->shouldReceive('getTableColumns')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableIndexes')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableForeignKeys')->andReturn([]);
        $this->command->shouldReceive('option')->with('tables')->andReturn(null);

        // Mock comparator - has differences
        $this->mockComparator->shouldReceive('compare')->andReturn([
            'tables_to_create' => ['test_table' => ['columns' => []]],
            'tables_to_drop' => [],
            'tables_to_modify' => [],
        ]);

        // Mock options
        $this->command->shouldReceive('option')->with('dry-run')->andReturn(false);
        $this->command->shouldReceive('option')->with('force')->andReturn(false);
        $this->command->shouldReceive('confirm')->with('Generate migration to sync these changes?', false)->andReturn(false);

        // Execute
        $result = $this->command->handle();

        // Assert
        expect($result)->toBe(0); // SUCCESS
    });
});

describe('buildSchemaFromMigrations method', function () {
    it('uses latest snapshot when available', function () {
        $snapshot = [
            'name' => 'test_snapshot',
            'version' => '1.0',
            'schema' => [
                'tables' => [
                    'users' => ['columns' => []],
                ],
            ],
        ];

        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn($snapshot);
        $this->command->shouldReceive('line')
            ->with("<fg=gray>  Using snapshot: test_snapshot (v1.0)</>")
            ->once();

        $result = $this->command->buildSchemaFromMigrations();

        expect($result)->toEqual($snapshot['schema']);
    });

    it('returns empty schema when no snapshot available', function () {
        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn(null);
        $this->command->shouldReceive('line')
            ->with('<fg=gray>  No snapshot available, using empty baseline</>')
            ->once();

        $result = $this->command->buildSchemaFromMigrations();

        expect($result)->toEqual(['tables' => []]);
    });

    it('displays warning when snapshot has format version mismatch', function () {
        $snapshot = [
            'name' => 'old_snapshot',
            'version' => '1.0',
            'format_version' => '0.9.0', // Old version
            'schema' => [
                'tables' => ['users' => ['columns' => []]],
            ],
        ];

        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn($snapshot);
        $this->mockSnapshotManager->shouldReceive('hasFormatVersionMismatch')->with($snapshot)->andReturn(true);
        $this->mockSnapshotManager->shouldReceive('getFormatVersionWarning')->with($snapshot)->andReturn('Version mismatch warning');

        $this->command->shouldReceive('line')->with("<fg=gray>  Using snapshot: old_snapshot (v1.0)</>")->once();
        $this->command->shouldReceive('newLine')->twice();
        $this->command->shouldReceive('warn')->with('Version mismatch warning')->once();

        $result = $this->command->buildSchemaFromMigrations();

        expect($result)->toEqual($snapshot['schema']);
    });

    it('suppresses warning when --ignore-version-mismatch is used', function () {
        $snapshot = [
            'name' => 'old_snapshot',
            'version' => '1.0',
            'format_version' => '0.9.0',
            'schema' => [
                'tables' => ['users' => ['columns' => []]],
            ],
        ];

        $this->command->shouldReceive('option')->with('ignore-version-mismatch')->andReturn(true);
        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn($snapshot);
        $this->command->shouldReceive('line')->with("<fg=gray>  Using snapshot: old_snapshot (v1.0)</>")->once();

        // Should NOT call warn when flag is set
        $this->command->shouldReceive('warn')->never();

        $result = $this->command->buildSchemaFromMigrations();

        expect($result)->toEqual($snapshot['schema']);
    });
});

describe('captureCurrentSchema method', function () {
    it('captures schema from database', function () {
        $this->mockAdapter->shouldReceive('getDriverName')->andReturn('mysql');
        $this->mockAdapter->shouldReceive('getAllTables')->andReturn(['users', 'posts']);
        $this->mockAdapter->shouldReceive('getTableColumns')->with('users')->andReturn([
            ['name' => 'id', 'type' => 'bigint'],
        ]);
        $this->mockAdapter->shouldReceive('getTableColumns')->with('posts')->andReturn([
            ['name' => 'id', 'type' => 'bigint'],
        ]);
        $this->mockAdapter->shouldReceive('getTableIndexes')->twice()->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableForeignKeys')->twice()->andReturn([]);

        $this->command->shouldReceive('line')->atLeast()->once();
        $this->command->shouldReceive('option')->with('tables')->andReturn(null);

        $result = $this->command->captureCurrentSchema();

        expect($result)->toHaveKey('tables');
        expect($result['tables'])->toHaveKey('users');
        expect($result['tables'])->toHaveKey('posts');
    });

    it('filters tables when --tables option provided', function () {
        $this->mockAdapter->shouldReceive('getDriverName')->andReturn('mysql');
        $this->mockAdapter->shouldReceive('getAllTables')->andReturn(['users', 'posts', 'products']);
        $this->mockAdapter->shouldReceive('getTableColumns')->with('users')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableIndexes')->with('users')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableForeignKeys')->with('users')->andReturn([]);

        $this->command->shouldReceive('line')->atLeast()->once();
        $this->command->shouldReceive('option')->with('tables')->andReturn(['users']);

        $result = $this->command->captureCurrentSchema();

        expect($result['tables'])->toHaveKey('users');
        expect($result['tables'])->not->toHaveKey('posts');
        expect($result['tables'])->not->toHaveKey('products');
    });
});

describe('displayDifferences method', function () {
    it('displays tables to create', function () {
        $differences = [
            'tables_to_create' => [
                'new_table' => [
                    'columns' => [
                        ['name' => 'id'],
                        ['name' => 'name'],
                    ],
                ],
            ],
            'tables_to_drop' => [],
            'tables_to_modify' => [],
        ];

        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('line')->with('Found differences:')->once();
        $this->command->shouldReceive('line')->with(str_repeat('─', 60))->twice();
        $this->command->shouldReceive('line')->with('<fg=yellow>📦 New Tables (not in migrations):</>')->once();
        $this->command->shouldReceive('line')->with("  1. <fg=green>+</> Table 'new_table' (2 columns)")->once();
        $this->command->shouldReceive('line')->with('<fg=cyan>Total changes: 1</>')->once();

        $this->command->displayDifferences($differences);
    });

    it('displays tables to drop', function () {
        $differences = [
            'tables_to_create' => [],
            'tables_to_drop' => ['old_table'],
            'tables_to_modify' => [],
        ];

        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('line')->with('Found differences:')->once();
        $this->command->shouldReceive('line')->with(str_repeat('─', 60))->twice();
        $this->command->shouldReceive('line')->with('<fg=red>🗑️  Dropped Tables (in migrations but not in DB):</>')->once();
        $this->command->shouldReceive('line')->with("  1. <fg=red>-</> Table 'old_table'")->once();
        $this->command->shouldReceive('line')->with('<fg=cyan>Total changes: 1</>')->once();

        $this->command->displayDifferences($differences);
    });

    it('displays table modifications', function () {
        $differences = [
            'tables_to_create' => [],
            'tables_to_drop' => [],
            'tables_to_modify' => [
                'users' => [
                    'columns_to_add' => [
                        ['name' => 'email', 'type' => 'varchar(255)', 'nullable' => true],
                    ],
                    'columns_to_drop' => ['old_field'],
                    'columns_to_rename' => [
                        ['from' => 'name', 'to' => 'full_name'],
                    ],
                    'columns_to_modify' => [
                        ['name' => 'age', 'from' => ['type' => 'int'], 'to' => ['type' => 'bigint']],
                    ],
                    'indexes_to_add' => [
                        ['name' => 'idx_email', 'columns' => ['email']],
                    ],
                    'indexes_to_drop' => ['idx_old'],
                    'foreign_keys_to_add' => [
                        ['name' => 'fk_user_id'],
                    ],
                    'foreign_keys_to_drop' => ['fk_old'],
                ],
            ],
        ];

        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('line')->atLeast()->once();

        $this->command->displayDifferences($differences);
    });
});

describe('filterIgnoredTables method', function () {
    it('filters tables based on config patterns', function () {
        config(['smart-migration.drift.ignored_tables' => ['migrations', '_*']]);

        $tables = ['users', 'posts', 'migrations', '_archived_users'];

        $result = $this->command->filterIgnoredTables($tables);

        expect($result)->toContain('users');
        expect($result)->toContain('posts');
        expect($result)->not->toContain('migrations');
        expect($result)->not->toContain('_archived_users');
    });
});

describe('getMigrationName method', function () {
    it('returns custom name when --name option provided', function () {
        $this->command->shouldReceive('option')->with('name')->andReturn('custom_migration');

        $result = $this->command->getMigrationName();

        expect($result)->toBe('custom_migration');
    });

    it('returns default name when no --name option', function () {
        $this->command->shouldReceive('option')->with('name')->andReturn(null);

        $result = $this->command->getMigrationName();

        expect($result)->toBe('sync_schema_changes');
    });
});

describe('confirmGeneration method', function () {
    it('returns true when --force option is used', function () {
        $this->command->shouldReceive('option')->with('force')->andReturn(true);

        $result = $this->command->confirmGeneration();

        expect($result)->toBe(true);
    });

    it('prompts user when --force is not used', function () {
        $this->command->shouldReceive('option')->with('force')->andReturn(false);
        $this->command->shouldReceive('newLine')->once();
        $this->command->shouldReceive('confirm')
            ->with('Generate migration to sync these changes?', false)
            ->andReturn(true);

        $result = $this->command->confirmGeneration();

        expect($result)->toBe(true);
    });
});

describe('extractUpMethodPreview method', function () {
    it('extracts up method from migration code', function () {
        $migrationCode = <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('test', function() {});
    }

    public function down(): void
    {
        Schema::dropIfExists('test');
    }
};
PHP;

        $result = $this->command->extractUpMethodPreview($migrationCode);

        expect($result)->toContain('public function up()');
        expect($result)->toContain('Schema::create');
        expect($result)->not->toContain('public function down()');
    });
});

describe('displayHeader method', function () {
    it('displays command header', function () {
        $this->command->shouldReceive('newLine')->once();
        $this->command->shouldReceive('line')->with('<fg=cyan>🔍 Smart Migration - Auto Diff Generator</>')->once();
        $this->command->shouldReceive('line')->with(str_repeat('─', 60))->once();

        $this->command->displayHeader();
    });
});
