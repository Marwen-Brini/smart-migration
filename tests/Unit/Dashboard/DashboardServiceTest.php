<?php

use Flux\Dashboard\DashboardService;
use Flux\Database\DatabaseAdapterFactory;
use Flux\Snapshots\SnapshotManager;
use Flux\Generators\SchemaComparator;
use Flux\Support\ArtisanRunner;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Flux\Database\DatabaseAdapter;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;

beforeEach(function () {
    $this->mockMigrator = Mockery::mock(Migrator::class);
    $this->mockAdapterFactory = Mockery::mock(DatabaseAdapterFactory::class);
    $this->mockSnapshotManager = Mockery::mock(SnapshotManager::class);
    $this->mockSchemaComparator = Mockery::mock(SchemaComparator::class);
    $this->mockDb = Mockery::mock(DatabaseManager::class);
    $this->mockApp = Mockery::mock(Application::class);
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockArtisan = Mockery::mock(ArtisanRunner::class);
    $this->mockAdapter = Mockery::mock(DatabaseAdapter::class);
    $this->mockRepository = Mockery::mock(MigrationRepositoryInterface::class);

    $this->service = new DashboardService(
        $this->mockMigrator,
        $this->mockAdapterFactory,
        $this->mockSnapshotManager,
        $this->mockSchemaComparator,
        $this->mockDb,
        $this->mockApp,
        $this->mockConfig,
        $this->mockArtisan,
        '/test/migrations'
    );
});

afterEach(function () {
    Mockery::close();
});

describe('constructor', function () {
    it('uses database_path when migrationsPath is empty', function () {
        $service = new DashboardService(
            $this->mockMigrator,
            $this->mockAdapterFactory,
            $this->mockSnapshotManager,
            $this->mockSchemaComparator,
            $this->mockDb,
            $this->mockApp,
            $this->mockConfig,
            $this->mockArtisan,
            '' // empty path
        );

        // Access the protected property via reflection
        $reflection = new ReflectionClass($service);
        $property = $reflection->getProperty('migrationsPath');
        $property->setAccessible(true);

        expect($property->getValue($service))->toBe(database_path('migrations'));
    });
});

describe('getStatus method', function () {
    it('returns status without drift', function () {
        $this->mockAdapterFactory->shouldReceive('create')->andReturn($this->mockAdapter);
        $this->mockAdapter->shouldReceive('getAllTables')->andReturn(['users', 'posts']);

        $this->mockMigrator->shouldReceive('getRepository')->andReturn($this->mockRepository);
        $this->mockRepository->shouldReceive('getRan')->andReturn(['migration1', 'migration2']);
        $this->mockMigrator->shouldReceive('getMigrationFiles')
            ->with(['/test/migrations'])
            ->andReturn(['migration1' => '/path1', 'migration2' => '/path2', 'migration3' => '/path3']);

        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn(null);

        $this->mockApp->shouldReceive('environment')->andReturn('testing');
        $this->mockApp->shouldReceive('version')->andReturn('11.0.0');
        $this->mockConfig->shouldReceive('get')->with('database.default')->andReturn('mysql');

        $result = $this->service->getStatus();

        expect($result)->toHaveKeys(['environment', 'pending_count', 'applied_count', 'table_count', 'drift_detected', 'database_driver', 'laravel_version'])
            ->and($result['environment'])->toBe('testing')
            ->and($result['pending_count'])->toBe(1)
            ->and($result['applied_count'])->toBe(2)
            ->and($result['table_count'])->toBe(2)
            ->and($result['drift_detected'])->toBeFalse()
            ->and($result['database_driver'])->toBe('mysql')
            ->and($result['laravel_version'])->toBe('11.0.0');
    });

    it('detects drift when snapshot differs from current schema', function () {
        $this->mockAdapterFactory->shouldReceive('create')->andReturn($this->mockAdapter);
        $this->mockAdapter->shouldReceive('getAllTables')->andReturn(['users', 'posts']);
        $this->mockAdapter->shouldReceive('getTableColumns')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableIndexes')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableForeignKeys')->andReturn([]);

        $this->mockMigrator->shouldReceive('getRepository')->andReturn($this->mockRepository);
        $this->mockRepository->shouldReceive('getRan')->andReturn([]);
        $this->mockMigrator->shouldReceive('getMigrationFiles')->andReturn([]);

        $snapshot = [
            'schema' => ['tables' => ['users' => ['columns' => ['old_column']]]],
            'timestamp' => '2025-01-01',
        ];
        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn($snapshot);
        $this->mockSchemaComparator->shouldReceive('compare')->andReturn(['table_added' => ['posts']]);

        $this->mockApp->shouldReceive('environment')->andReturn('testing');
        $this->mockApp->shouldReceive('version')->andReturn('11.0.0');
        $this->mockConfig->shouldReceive('get')->with('database.default')->andReturn('mysql');

        $result = $this->service->getStatus();

        expect($result['drift_detected'])->toBeTrue();
    });

    it('handles exceptions during drift check gracefully', function () {
        $this->mockAdapterFactory->shouldReceive('create')->andReturn($this->mockAdapter);
        $this->mockAdapter->shouldReceive('getAllTables')->andReturn(['users', 'posts']);
        $this->mockAdapter->shouldReceive('getTableColumns')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableIndexes')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableForeignKeys')->andReturn([]);

        $this->mockMigrator->shouldReceive('getRepository')->andReturn($this->mockRepository);
        $this->mockRepository->shouldReceive('getRan')->andReturn([]);
        $this->mockMigrator->shouldReceive('getMigrationFiles')->andReturn([]);

        $snapshot = [
            'schema' => ['tables' => ['users' => ['columns' => ['old_column']]]],
            'timestamp' => '2025-01-01',
        ];
        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn($snapshot);
        // Throw exception during compare
        $this->mockSchemaComparator->shouldReceive('compare')->andThrow(new \Exception('Compare error'));

        $this->mockApp->shouldReceive('environment')->andReturn('testing');
        $this->mockApp->shouldReceive('version')->andReturn('11.0.0');
        $this->mockConfig->shouldReceive('get')->with('database.default')->andReturn('mysql');

        $result = $this->service->getStatus();

        // Should handle exception and return drift_detected as false
        expect($result['drift_detected'])->toBeFalse();
    });
});

describe('getMigrations method', function () {
    it('returns list of migrations with status', function () {
        $this->mockMigrator->shouldReceive('getRepository')->andReturn($this->mockRepository);
        $this->mockRepository->shouldReceive('getRan')->andReturn(['migration1']);
        $this->mockMigrator->shouldReceive('getMigrationFiles')
            ->with(['/test/migrations'])
            ->andReturn([
                'migration1' => '/path/migration1.php',
                'migration2_create_posts' => '/path/migration2.php',
            ]);

        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('where')->with('migration', 'migration1')->andReturnSelf();
        $mockBuilder->shouldReceive('first')->andReturn((object)['created_at' => '2025-01-01']);
        $this->mockDb->shouldReceive('table')->with('migrations')->andReturn($mockBuilder);

        $result = $this->service->getMigrations();

        expect($result)->toHaveCount(2)
            ->and($result[0]['name'])->toBe('migration1')
            ->and($result[0]['status'])->toBe('applied')
            ->and($result[0]['applied_at'])->toBe('2025-01-01')
            ->and($result[1]['name'])->toBe('migration2_create_posts')
            ->and($result[1]['status'])->toBe('pending')
            ->and($result[1]['applied_at'])->toBeNull()
            ->and($result[1]['risk'])->toBe('safe');
    });

    it('estimates risk levels correctly', function () {
        $this->mockMigrator->shouldReceive('getRepository')->andReturn($this->mockRepository);
        $this->mockRepository->shouldReceive('getRan')->andReturn([]);
        $this->mockMigrator->shouldReceive('getMigrationFiles')
            ->andReturn([
                '2025_01_01_drop_table' => '/path1',
                '2025_01_02_alter_table' => '/path2',
                '2025_01_03_create_table' => '/path3',
            ]);

        $result = $this->service->getMigrations();

        expect($result[0]['risk'])->toBe('danger')
            ->and($result[1]['risk'])->toBe('warning')
            ->and($result[2]['risk'])->toBe('safe');
    });
});

describe('getHistory method', function () {
    it('returns migration history', function () {
        $migrations = collect([
            (object)['id' => 1, 'migration' => 'migration1', 'batch' => 1, 'created_at' => '2025-01-01'],
            (object)['id' => 2, 'migration' => 'migration2', 'batch' => 2, 'created_at' => '2025-01-02'],
        ]);

        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('orderBy')->with('id', 'desc')->andReturnSelf();
        $mockBuilder->shouldReceive('limit')->with(50)->andReturnSelf();
        $mockBuilder->shouldReceive('get')->andReturn($migrations);
        $this->mockDb->shouldReceive('table')->with('migrations')->andReturn($mockBuilder);

        $result = $this->service->getHistory();

        expect($result)->toHaveCount(2)
            ->and($result[0]['id'])->toBe(1)
            ->and($result[0]['migration'])->toBe('migration1')
            ->and($result[0]['batch'])->toBe(1);
    });
});

describe('getSchema method', function () {
    it('returns current database schema', function () {
        $this->mockAdapterFactory->shouldReceive('create')->andReturn($this->mockAdapter);
        $this->mockAdapter->shouldReceive('getAllTables')->andReturn(['users', 'posts']);
        $this->mockAdapter->shouldReceive('getTableColumns')->with('users')->andReturn(['id', 'name']);
        $this->mockAdapter->shouldReceive('getTableColumns')->with('posts')->andReturn(['id', 'title']);
        $this->mockAdapter->shouldReceive('getTableIndexes')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableForeignKeys')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableRowCount')->andReturn(10);

        $result = $this->service->getSchema();

        expect($result)->toHaveKey('tables')
            ->and($result['tables'])->toHaveCount(2)
            ->and($result['tables'][0]['name'])->toBe('users')
            ->and($result['tables'][0]['column_count'])->toBe(2)
            ->and($result['tables'][0]['row_count'])->toBe(10);
    });
});

describe('getDrift method', function () {
    it('returns no drift when no snapshot exists', function () {
        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn(null);

        $result = $this->service->getDrift();

        expect($result['has_drift'])->toBeFalse()
            ->and($result['message'])->toContain('No snapshot available');
    });

    it('detects drift when schemas differ', function () {
        $snapshot = [
            'schema' => ['tables' => ['users' => ['columns' => ['id']]]],
            'timestamp' => '2025-01-01',
        ];
        $this->mockSnapshotManager->shouldReceive('getLatest')->andReturn($snapshot);

        $this->mockAdapterFactory->shouldReceive('create')->andReturn($this->mockAdapter);
        $this->mockAdapter->shouldReceive('getAllTables')->andReturn(['users', 'posts']);
        $this->mockAdapter->shouldReceive('getTableColumns')->andReturn(['id', 'name']);
        $this->mockAdapter->shouldReceive('getTableIndexes')->andReturn([]);
        $this->mockAdapter->shouldReceive('getTableForeignKeys')->andReturn([]);

        $this->mockSchemaComparator->shouldReceive('compare')->andReturn(['table_added' => ['posts']]);

        $result = $this->service->getDrift();

        expect($result['has_drift'])->toBeTrue()
            ->and($result['differences'])->toHaveKey('table_added')
            ->and($result['snapshot_created_at'])->toBe('2025-01-01');
    });

    it('handles exceptions gracefully', function () {
        $this->mockSnapshotManager->shouldReceive('getLatest')->andThrow(new \Exception('Snapshot error'));

        $result = $this->service->getDrift();

        expect($result['has_drift'])->toBeFalse()
            ->and($result)->toHaveKey('error');
    });
});

describe('getSnapshots method', function () {
    it('returns list of snapshots', function () {
        $snapshots = [
            ['name' => 'snapshot1', 'created_at' => '2025-01-01'],
            ['name' => 'snapshot2', 'created_at' => '2025-01-02'],
        ];
        $this->mockSnapshotManager->shouldReceive('list')->andReturn($snapshots);

        $result = $this->service->getSnapshots();

        expect($result)->toBe($snapshots);
    });

    it('returns empty array on exception', function () {
        $this->mockSnapshotManager->shouldReceive('list')->andThrow(new \Exception('Error'));

        $result = $this->service->getSnapshots();

        expect($result)->toBe([]);
    });
});

describe('getMetrics method', function () {
    it('returns metrics data', function () {
        $this->mockAdapterFactory->shouldReceive('create')->andReturn($this->mockAdapter);
        $this->mockAdapter->shouldReceive('getAllTables')->andReturn(['users', '_archived_posts']);
        $this->mockAdapter->shouldReceive('getTableRowCount')->with('users')->andReturn(100);
        $this->mockAdapter->shouldReceive('getTableRowCount')->with('_archived_posts')->andReturn(50);

        $this->mockMigrator->shouldReceive('getMigrationFiles')->andReturn([
            '2025_01_01_create_users' => '/path1',
            '2025_01_02_drop_posts' => '/path2',
        ]);

        $migrations = collect([
            (object)['migration' => 'migration1'],
            (object)['migration' => 'migration2'],
        ]);
        $mockBuilder = Mockery::mock(Builder::class);
        $mockBuilder->shouldReceive('orderBy')->andReturnSelf();
        $mockBuilder->shouldReceive('limit')->andReturnSelf();
        $mockBuilder->shouldReceive('get')->andReturn($migrations);
        $this->mockDb->shouldReceive('table')->andReturn($mockBuilder);

        $result = $this->service->getMetrics();

        expect($result)->toHaveKeys(['risk_distribution', 'execution_times', 'table_sizes', 'archive_sizes'])
            ->and($result['risk_distribution']['safe'])->toBe(1)
            ->and($result['risk_distribution']['danger'])->toBe(1)
            ->and($result['table_sizes'])->toHaveCount(2)
            ->and($result['archive_sizes'])->toHaveCount(1)
            ->and($result['archive_sizes'][0]['name'])->toBe('_archived_posts');
    });
});

describe('generateFixMigration method', function () {
    it('calls artisan command and returns success', function () {
        $this->mockArtisan->shouldReceive('call')->with('migrate:check', ['--fix' => true])->once();
        $this->mockArtisan->shouldReceive('output')->andReturn('Fix migration generated');

        $result = $this->service->generateFixMigration();

        expect($result['success'])->toBeTrue()
            ->and($result['message'])->toContain('Fix migration generated successfully')
            ->and($result['output'])->toBe('Fix migration generated');
    });

    it('handles exceptions', function () {
        $this->mockArtisan->shouldReceive('call')->andThrow(new \Exception('Command failed'));

        $result = $this->service->generateFixMigration();

        expect($result['success'])->toBeFalse()
            ->and($result['message'])->toContain('Failed to generate fix migration');
    });
});

describe('createSnapshot method', function () {
    it('creates snapshot with auto-generated name', function () {
        $this->mockArtisan->shouldReceive('call')
            ->with('migrate:snapshot', Mockery::on(function ($params) {
                return isset($params['command']) && $params['command'] === 'create' &&
                       isset($params['name']) && str_contains($params['name'], 'dashboard_snapshot_');
            }))
            ->once();
        $this->mockArtisan->shouldReceive('output')->andReturn('Snapshot created');

        $result = $this->service->createSnapshot();

        expect($result['success'])->toBeTrue()
            ->and($result['name'])->toContain('dashboard_snapshot_');
    });

    it('creates snapshot with custom name', function () {
        $this->mockArtisan->shouldReceive('call')
            ->with('migrate:snapshot', ['command' => 'create', 'name' => 'custom_snapshot'])
            ->once();
        $this->mockArtisan->shouldReceive('output')->andReturn('Snapshot created');

        $result = $this->service->createSnapshot('custom_snapshot');

        expect($result['success'])->toBeTrue()
            ->and($result['name'])->toBe('custom_snapshot');
    });

    it('handles exceptions when creating snapshot', function () {
        $this->mockArtisan->shouldReceive('call')->andThrow(new \Exception('Snapshot creation failed'));

        $result = $this->service->createSnapshot('test_snapshot');

        expect($result['success'])->toBeFalse()
            ->and($result['message'])->toContain('Failed to create snapshot');
    });
});

describe('deleteSnapshot method', function () {
    it('deletes snapshot successfully', function () {
        $this->mockArtisan->shouldReceive('call')
            ->with('migrate:snapshot', ['command' => 'delete', 'name' => 'snapshot1'])
            ->once();
        $this->mockArtisan->shouldReceive('output')->andReturn('Snapshot deleted');

        $result = $this->service->deleteSnapshot('snapshot1');

        expect($result['success'])->toBeTrue()
            ->and($result['message'])->toContain('Snapshot deleted successfully');
    });

    it('handles exceptions when deleting snapshot', function () {
        $this->mockArtisan->shouldReceive('call')->andThrow(new \Exception('Snapshot deletion failed'));

        $result = $this->service->deleteSnapshot('test_snapshot');

        expect($result['success'])->toBeFalse()
            ->and($result['message'])->toContain('Failed to delete snapshot');
    });
});

describe('runMigrations method', function () {
    it('runs migrations with options', function () {
        $this->mockArtisan->shouldReceive('call')
            ->with('migrate:safe', ['--path' => 'custom/path', '--force' => true])
            ->once();
        $this->mockArtisan->shouldReceive('output')->andReturn('Migrations completed');

        $result = $this->service->runMigrations(['path' => 'custom/path', 'force' => true]);

        expect($result['success'])->toBeTrue()
            ->and($result['message'])->toContain('Migrations ran successfully');
    });

    it('runs migrations without options', function () {
        $this->mockArtisan->shouldReceive('call')
            ->with('migrate:safe', [])
            ->once();
        $this->mockArtisan->shouldReceive('output')->andReturn('Migrations completed');

        $result = $this->service->runMigrations();

        expect($result['success'])->toBeTrue();
    });

    it('handles exceptions when running migrations', function () {
        $this->mockArtisan->shouldReceive('call')->andThrow(new \Exception('Migration failed'));

        $result = $this->service->runMigrations();

        expect($result['success'])->toBeFalse()
            ->and($result['message'])->toContain('Failed to run migrations');
    });
});

describe('rollbackMigrations method', function () {
    it('rollbacks migrations with step option', function () {
        $this->mockArtisan->shouldReceive('call')
            ->with('migrate:undo', ['--step' => 2])
            ->once();
        $this->mockArtisan->shouldReceive('output')->andReturn('Rollback completed');

        $result = $this->service->rollbackMigrations(['step' => 2]);

        expect($result['success'])->toBeTrue()
            ->and($result['message'])->toContain('Rollback completed successfully');
    });

    it('rollbacks migrations with batch option', function () {
        $this->mockArtisan->shouldReceive('call')
            ->with('migrate:undo', ['--batch' => 5])
            ->once();
        $this->mockArtisan->shouldReceive('output')->andReturn('Rollback completed');

        $result = $this->service->rollbackMigrations(['batch' => 5]);

        expect($result['success'])->toBeTrue();
    });

    it('handles exceptions when rolling back migrations', function () {
        $this->mockArtisan->shouldReceive('call')->andThrow(new \Exception('Rollback failed'));

        $result = $this->service->rollbackMigrations();

        expect($result['success'])->toBeFalse()
            ->and($result['message'])->toContain('Failed to rollback migrations');
    });
});
