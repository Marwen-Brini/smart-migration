<?php

use Flux\Commands\SnapshotCommand;
use Flux\Snapshots\SnapshotManager;

beforeEach(function () {
    $this->mockSnapshotManager = Mockery::mock(SnapshotManager::class);

    // Create command mock with partial mocking to allow console output mocking
    $this->command = Mockery::mock(SnapshotCommand::class)->makePartial()->shouldAllowMockingProtectedMethods();

    // Set the snapshot manager dependency using reflection
    $reflection = new ReflectionClass($this->command);
    $snapshotProperty = $reflection->getProperty('snapshotManager');
    $snapshotProperty->setAccessible(true);
    $snapshotProperty->setValue($this->command, $this->mockSnapshotManager);
});

afterEach(function () {
    Mockery::close();
});

describe('constructor', function () {
    it('initializes snapshot manager from container', function () {
        // Create a real command instance to test constructor
        $realCommand = new SnapshotCommand;

        // Use reflection to check the snapshotManager property was set
        $reflection = new ReflectionClass($realCommand);
        $snapshotProperty = $reflection->getProperty('snapshotManager');
        $snapshotProperty->setAccessible(true);
        $snapshotManager = $snapshotProperty->getValue($realCommand);

        expect($snapshotManager)->toBeInstanceOf(SnapshotManager::class);
    });
});

describe('handle method', function () {
    it('handles create action', function () {
        $this->command->shouldReceive('argument')->with('action')->andReturn('create');
        $this->command->shouldReceive('createSnapshot')->once()->andReturn(0);

        $result = $this->command->handle();

        expect($result)->toBe(0);
    });

    it('handles list action', function () {
        $this->command->shouldReceive('argument')->with('action')->andReturn('list');
        $this->command->shouldReceive('listSnapshots')->once()->andReturn(0);

        $result = $this->command->handle();

        expect($result)->toBe(0);
    });

    it('handles show action', function () {
        $this->command->shouldReceive('argument')->with('action')->andReturn('show');
        $this->command->shouldReceive('showSnapshot')->once()->andReturn(0);

        $result = $this->command->handle();

        expect($result)->toBe(0);
    });

    it('handles compare action', function () {
        $this->command->shouldReceive('argument')->with('action')->andReturn('compare');
        $this->command->shouldReceive('compareSnapshots')->once()->andReturn(0);

        $result = $this->command->handle();

        expect($result)->toBe(0);
    });

    it('handles delete action', function () {
        $this->command->shouldReceive('argument')->with('action')->andReturn('delete');
        $this->command->shouldReceive('deleteSnapshot')->once()->andReturn(0);

        $result = $this->command->handle();

        expect($result)->toBe(0);
    });

    it('handles invalid action', function () {
        $this->command->shouldReceive('argument')->with('action')->andReturn('invalid');
        $this->command->shouldReceive('invalidAction')->with('invalid')->once()->andReturn(1);

        $result = $this->command->handle();

        expect($result)->toBe(1);
    });
});

describe('createSnapshot method', function () {
    it('creates snapshot successfully', function () {
        $snapshotData = [
            'name' => 'test_snapshot',
            'version' => 'abc123',
            'timestamp' => '2023-01-01T12:00:00+00:00',
            'environment' => 'testing',
            'database' => ['driver' => 'mysql'],
            'schema' => ['tables' => ['users' => [], 'posts' => []]],
        ];

        // Mock console output
        $this->command->shouldReceive('info')->with('📸 Creating Schema Snapshot')->once();
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════')->once();
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('info')->with('Analyzing database schema...')->once();
        $this->command->shouldReceive('info')->with('✅ Snapshot created successfully!')->once();
        $this->command->shouldReceive('table')->once();
        $this->command->shouldReceive('comment')->with('💡 Tip: Enable auto_snapshot in config to automatically create snapshots after migrations.')->once();

        // Mock arguments and config
        $this->command->shouldReceive('argument')->with('name')->andReturn('test_snapshot');

        // Mock config call
        $originalConfig = config('smart-migration.snapshots.auto_snapshot');
        config(['smart-migration.snapshots.auto_snapshot' => false]);

        // Mock snapshot manager
        $this->mockSnapshotManager->shouldReceive('create')->with('test_snapshot')->andReturn($snapshotData);

        $result = $this->command->createSnapshot();

        expect($result)->toBe(0);

        // Restore config
        config(['smart-migration.snapshots.auto_snapshot' => $originalConfig]);
    });

    it('creates snapshot successfully with auto_snapshot enabled', function () {
        $snapshotData = [
            'name' => 'test_snapshot',
            'version' => 'abc123',
            'timestamp' => '2023-01-01T12:00:00+00:00',
            'environment' => 'testing',
            'database' => ['driver' => 'mysql'],
            'schema' => ['tables' => ['users' => [], 'posts' => []]],
        ];

        // Mock console output (no tip message when auto_snapshot is enabled)
        $this->command->shouldReceive('info')->with('📸 Creating Schema Snapshot')->once();
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════')->once();
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('info')->with('Analyzing database schema...')->once();
        $this->command->shouldReceive('info')->with('✅ Snapshot created successfully!')->once();
        $this->command->shouldReceive('table')->once();

        // Mock arguments and config
        $this->command->shouldReceive('argument')->with('name')->andReturn('test_snapshot');

        // Mock config call
        $originalConfig = config('smart-migration.snapshots.auto_snapshot');
        config(['smart-migration.snapshots.auto_snapshot' => true]);

        // Mock snapshot manager
        $this->mockSnapshotManager->shouldReceive('create')->with('test_snapshot')->andReturn($snapshotData);

        $result = $this->command->createSnapshot();

        expect($result)->toBe(0);

        // Restore config
        config(['smart-migration.snapshots.auto_snapshot' => $originalConfig]);
    });

    it('handles snapshot creation failure', function () {
        // Mock console output
        $this->command->shouldReceive('info')->with('📸 Creating Schema Snapshot')->once();
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════')->once();
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('info')->with('Analyzing database schema...')->once();
        $this->command->shouldReceive('error')->with('❌ Failed to create snapshot: Test error')->once();

        // Mock arguments
        $this->command->shouldReceive('argument')->with('name')->andReturn('test_snapshot');

        // Mock snapshot manager to throw exception
        $this->mockSnapshotManager->shouldReceive('create')
            ->with('test_snapshot')
            ->andThrow(new \Exception('Test error'));

        $result = $this->command->createSnapshot();

        expect($result)->toBe(1);
    });
});

describe('listSnapshots method', function () {
    it('displays snapshots when available', function () {
        $snapshots = [
            [
                'name' => 'snapshot1',
                'version' => 'abc123',
                'timestamp' => '2023-01-01T12:00:00+00:00',
                'environment' => 'testing',
                'size' => 1024,
            ],
            [
                'name' => 'snapshot2',
                'version' => 'def456',
                'timestamp' => '2023-01-02T12:00:00+00:00',
                'environment' => 'production',
                'size' => 2048,
            ],
        ];

        // Mock console output
        $this->command->shouldReceive('info')->with('📋 Schema Snapshots')->once();
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════')->once();
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('table')->once();
        $this->command->shouldReceive('info')->with('Total snapshots: 2')->once();

        // Mock snapshot manager
        $this->mockSnapshotManager->shouldReceive('list')->andReturn($snapshots);

        $result = $this->command->listSnapshots();

        expect($result)->toBe(0);
    });

    it('displays warning when no snapshots found', function () {
        // Mock console output
        $this->command->shouldReceive('info')->with('📋 Schema Snapshots')->once();
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════')->once();
        $this->command->shouldReceive('newLine')->once();
        $this->command->shouldReceive('warn')->with('No snapshots found. Run "php artisan migrate:snapshot create" to create one.')->once();

        // Mock snapshot manager
        $this->mockSnapshotManager->shouldReceive('list')->andReturn([]);

        $result = $this->command->listSnapshots();

        expect($result)->toBe(0);
    });
});

describe('showSnapshot method', function () {
    it('shows snapshot details when snapshot exists', function () {
        $snapshotData = [
            'name' => 'test_snapshot',
            'version' => 'abc123',
            'timestamp' => '2023-01-01T12:00:00+00:00',
            'environment' => 'testing',
            'database' => [
                'driver' => 'mysql',
                'name' => 'test_db',
            ],
            'schema' => [
                'tables' => [
                    'users' => [
                        'columns' => [
                            ['name' => 'id', 'type' => 'integer'],
                            ['name' => 'email', 'type' => 'string'],
                        ],
                        'indexes' => [['name' => 'primary']],
                        'foreign_keys' => [],
                        'row_count' => 100,
                    ],
                    'posts' => [
                        'columns' => [['name' => 'id', 'type' => 'integer']],
                        'indexes' => [],
                        'foreign_keys' => [['name' => 'user_id_fk']],
                        'row_count' => 50,
                    ],
                ],
            ],
        ];

        // Mock console output
        $this->command->shouldReceive('info')->with('📸 Snapshot: test_snapshot')->once();
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════')->once();
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('info')->with('Metadata:')->once();
        $this->command->shouldReceive('info')->with('Schema Summary:')->once();
        $this->command->shouldReceive('info')->with('Tables:')->once();
        $this->command->shouldReceive('table')->times(3); // Metadata, Schema Summary, and Tables

        // Mock arguments
        $this->command->shouldReceive('argument')->with('name')->andReturn('test_snapshot');

        // Mock snapshot manager
        $this->mockSnapshotManager->shouldReceive('get')->with('test_snapshot')->andReturn($snapshotData);

        $result = $this->command->showSnapshot();

        expect($result)->toBe(0);
    });

    it('shows error when snapshot name not provided', function () {
        // Mock console output
        $this->command->shouldReceive('error')->with('Please provide a snapshot name.')->once();

        // Mock arguments
        $this->command->shouldReceive('argument')->with('name')->andReturn(null);

        $result = $this->command->showSnapshot();

        expect($result)->toBe(1);
    });

    it('shows error when snapshot not found', function () {
        // Mock console output
        $this->command->shouldReceive('info')->with('📸 Snapshot: test_snapshot')->once();
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════')->once();
        $this->command->shouldReceive('newLine')->once();
        $this->command->shouldReceive('error')->with("Snapshot 'test_snapshot' not found.")->once();

        // Mock arguments
        $this->command->shouldReceive('argument')->with('name')->andReturn('test_snapshot');

        // Mock snapshot manager
        $this->mockSnapshotManager->shouldReceive('get')->with('test_snapshot')->andReturn(null);

        $result = $this->command->showSnapshot();

        expect($result)->toBe(1);
    });
});

describe('compareSnapshots method', function () {
    it('compares snapshots successfully with no differences', function () {
        $differences = [
            'added_tables' => [],
            'removed_tables' => [],
            'modified_tables' => [],
        ];

        // Mock console output
        $this->command->shouldReceive('info')->with('🔍 Comparing Snapshots: snapshot1 vs snapshot2')->once();
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════')->once();
        $this->command->shouldReceive('newLine')->once();
        $this->command->shouldReceive('info')->with('✅ Snapshots are identical!')->once();

        // Mock arguments
        $this->command->shouldReceive('argument')->with('name')->andReturn('snapshot1');
        $this->command->shouldReceive('option')->with('compare-with')->andReturn('snapshot2');

        // Mock snapshot manager
        $this->mockSnapshotManager->shouldReceive('compare')->with('snapshot1', 'snapshot2')->andReturn($differences);

        $result = $this->command->compareSnapshots();

        expect($result)->toBe(0);
    });

    it('compares snapshots successfully with differences', function () {
        $differences = [
            'added_tables' => ['new_table'],
            'removed_tables' => ['old_table'],
            'modified_tables' => ['users'],
        ];

        // Mock console output
        $this->command->shouldReceive('info')->with('🔍 Comparing Snapshots: snapshot1 vs snapshot2')->once();
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════')->once();
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('info')->with('➕ Added Tables:')->once();
        $this->command->shouldReceive('line')->with('   - new_table')->once();
        $this->command->shouldReceive('warn')->with('➖ Removed Tables:')->once();
        $this->command->shouldReceive('line')->with('   - old_table')->once();
        $this->command->shouldReceive('comment')->with('✏️  Modified Tables:')->once();
        $this->command->shouldReceive('line')->with('   - users')->once();

        // Mock arguments
        $this->command->shouldReceive('argument')->with('name')->andReturn('snapshot1');
        $this->command->shouldReceive('option')->with('compare-with')->andReturn('snapshot2');

        // Mock snapshot manager
        $this->mockSnapshotManager->shouldReceive('compare')->with('snapshot1', 'snapshot2')->andReturn($differences);

        $result = $this->command->compareSnapshots();

        expect($result)->toBe(0);
    });

    it('shows error when snapshot names not provided', function () {
        // Test case 1: No first snapshot name
        $this->command->shouldReceive('argument')->with('name')->andReturn(null);
        $this->command->shouldReceive('option')->with('compare-with')->andReturn('snapshot2');
        $this->command->shouldReceive('error')->with('Please provide two snapshot names to compare.')->once();
        $this->command->shouldReceive('line')->with('Usage: php artisan migrate:snapshot compare <snapshot1> --compare-with=<snapshot2>')->once();

        $result = $this->command->compareSnapshots();

        expect($result)->toBe(1);
    });

    it('shows error when second snapshot name not provided', function () {
        // Test case 2: No second snapshot name
        $this->command->shouldReceive('argument')->with('name')->andReturn('snapshot1');
        $this->command->shouldReceive('option')->with('compare-with')->andReturn(null);
        $this->command->shouldReceive('error')->with('Please provide two snapshot names to compare.')->once();
        $this->command->shouldReceive('line')->with('Usage: php artisan migrate:snapshot compare <snapshot1> --compare-with=<snapshot2>')->once();

        $result = $this->command->compareSnapshots();

        expect($result)->toBe(1);
    });

    it('handles comparison failure', function () {
        // Mock console output
        $this->command->shouldReceive('info')->with('🔍 Comparing Snapshots: snapshot1 vs snapshot2')->once();
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════')->once();
        $this->command->shouldReceive('newLine')->once();
        $this->command->shouldReceive('error')->with('Failed to compare snapshots: Test error')->once();

        // Mock arguments
        $this->command->shouldReceive('argument')->with('name')->andReturn('snapshot1');
        $this->command->shouldReceive('option')->with('compare-with')->andReturn('snapshot2');

        // Mock snapshot manager to throw exception
        $this->mockSnapshotManager->shouldReceive('compare')
            ->with('snapshot1', 'snapshot2')
            ->andThrow(new \Exception('Test error'));

        $result = $this->command->compareSnapshots();

        expect($result)->toBe(1);
    });
});

describe('deleteSnapshot method', function () {
    it('deletes snapshot successfully when confirmed', function () {
        // Mock console output
        $this->command->shouldReceive('confirm')->with("Are you sure you want to delete snapshot 'test_snapshot'?")->andReturn(true);
        $this->command->shouldReceive('info')->with("✅ Snapshot 'test_snapshot' deleted successfully.")->once();

        // Mock arguments
        $this->command->shouldReceive('argument')->with('name')->andReturn('test_snapshot');

        // Mock snapshot manager
        $this->mockSnapshotManager->shouldReceive('delete')->with('test_snapshot')->andReturn(true);

        $result = $this->command->deleteSnapshot();

        expect($result)->toBe(0);
    });

    it('cancels deletion when not confirmed', function () {
        // Mock console output
        $this->command->shouldReceive('confirm')->with("Are you sure you want to delete snapshot 'test_snapshot'?")->andReturn(false);
        $this->command->shouldReceive('comment')->with('Deletion cancelled.')->once();

        // Mock arguments
        $this->command->shouldReceive('argument')->with('name')->andReturn('test_snapshot');

        $result = $this->command->deleteSnapshot();

        expect($result)->toBe(0);
    });

    it('shows error when snapshot name not provided', function () {
        // Mock console output
        $this->command->shouldReceive('error')->with('Please provide a snapshot name to delete.')->once();

        // Mock arguments
        $this->command->shouldReceive('argument')->with('name')->andReturn(null);

        $result = $this->command->deleteSnapshot();

        expect($result)->toBe(1);
    });

    it('shows error when deletion fails', function () {
        // Mock console output
        $this->command->shouldReceive('confirm')->with("Are you sure you want to delete snapshot 'test_snapshot'?")->andReturn(true);
        $this->command->shouldReceive('error')->with("❌ Failed to delete snapshot 'test_snapshot'.")->once();

        // Mock arguments
        $this->command->shouldReceive('argument')->with('name')->andReturn('test_snapshot');

        // Mock snapshot manager
        $this->mockSnapshotManager->shouldReceive('delete')->with('test_snapshot')->andReturn(false);

        $result = $this->command->deleteSnapshot();

        expect($result)->toBe(1);
    });
});

describe('invalidAction method', function () {
    it('shows error for invalid action', function () {
        // Mock console output
        $this->command->shouldReceive('error')->with('Invalid action: invalid_action')->once();
        $this->command->shouldReceive('line')->with('Valid actions: create, list, show, compare, delete')->once();

        $result = $this->command->invalidAction('invalid_action');

        expect($result)->toBe(1);
    });
});

describe('formatFileSize method', function () {
    it('formats bytes correctly', function () {
        // Use reflection to access protected method
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('formatFileSize');
        $method->setAccessible(true);

        expect($method->invoke($this->command, 512))->toBe('512 B');
        expect($method->invoke($this->command, 1024))->toBe('1 KB');
        expect($method->invoke($this->command, 1536))->toBe('1.5 KB');
        expect($method->invoke($this->command, 1048576))->toBe('1 MB');
        expect($method->invoke($this->command, 1572864))->toBe('1.5 MB');
    });
});
