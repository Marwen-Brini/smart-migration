<?php

use Flux\Database\DatabaseAdapter;
use Flux\Safety\SafeMigrator;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->mockRepository = \Mockery::mock(MigrationRepositoryInterface::class);
    $this->mockFilesystem = \Mockery::mock(Filesystem::class);
    $this->mockAdapter = \Mockery::mock(DatabaseAdapter::class);
    $this->mockAdapterFactory = \Mockery::mock(\Flux\Database\DatabaseAdapterFactoryInterface::class);

    $this->migrator = \Mockery::mock(SafeMigrator::class, [$this->mockRepository, resolve('db'), $this->mockFilesystem])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
});

afterEach(function () {
    \Mockery::close();
});

describe('pretendToRunMigration method', function () {
    it('calls parent pretendToRun method', function () {
        $migration = \Mockery::mock();

        $this->migrator->shouldReceive('pretendToRun')->once()->with($migration, 'up');

        $this->migrator->pretendToRunMigration($migration, 'up');
    });
});

describe('runSafe method', function () {
    it('runs migration in pretend mode', function () {
        $file = '/path/to/migration.php';
        $migration = \Mockery::mock();

        $this->migrator->shouldReceive('getMigrationName')->once()->with($file)->andReturn('test_migration');
        $this->migrator->shouldReceive('resolveMigration')->once()->with($file)->andReturn($migration);
        $this->migrator->shouldReceive('pretendToRun')->once()->with($migration, 'up');

        $this->migrator->runSafe($file, 1, true);
    });

    it('runs migration with safety features', function () {
        $file = '/path/to/migration.php';
        $migration = \Mockery::mock();
        $migrationName = 'test_migration';

        $this->migrator->shouldReceive('getMigrationName')->once()->with($file)->andReturn($migrationName);
        $this->migrator->shouldReceive('resolveMigration')->once()->with($file)->andReturn($migration);
        $this->migrator->shouldReceive('write')->twice();
        $this->migrator->shouldReceive('analyzeAndBackup')->once()->with($file);
        $this->migrator->shouldReceive('runMigration')->once()->with($migration, 'up');
        $this->mockRepository->shouldReceive('log')->once()->with($migrationName, 1);

        $this->migrator->runSafe($file, 1, false);
    });

    it('restores backups when migration fails', function () {
        $file = '/path/to/migration.php';
        $migration = \Mockery::mock();
        $migrationName = 'test_migration';
        $exception = new Exception('Migration failed');

        $this->migrator->shouldReceive('getMigrationName')->once()->with($file)->andReturn($migrationName);
        $this->migrator->shouldReceive('resolveMigration')->once()->with($file)->andReturn($migration);
        $this->migrator->shouldReceive('write')->twice();
        $this->migrator->shouldReceive('analyzeAndBackup')->once()->with($file);
        $this->migrator->shouldReceive('runMigration')->once()->with($migration, 'up')->andThrow($exception);
        $this->migrator->shouldReceive('restoreBackups')->once();

        expect(fn () => $this->migrator->runSafe($file, 1, false))->toThrow(Exception::class);
    });
});

describe('undoSafe method', function () {
    it('performs safe rollback successfully', function () {
        $file = '/path/to/migration.php';
        $migration = \Mockery::mock();
        $migrationName = 'test_migration';

        $this->migrator->shouldReceive('getMigrationName')->once()->with($file)->andReturn($migrationName);
        $this->migrator->shouldReceive('resolveMigration')->once()->with($file)->andReturn($migration);
        $this->migrator->shouldReceive('write')->twice();
        $this->migrator->shouldReceive('safeRollback')->once()->with($file);
        $this->mockRepository->shouldReceive('delete')->once()->with(\Mockery::type('object'));

        $result = $this->migrator->undoSafe($file);

        expect($result)->toBe(true);
    });

    it('handles rollback failure', function () {
        $file = '/path/to/migration.php';
        $migration = \Mockery::mock();
        $migrationName = 'test_migration';
        $exception = new Exception('Rollback failed');

        $this->migrator->shouldReceive('getMigrationName')->once()->with($file)->andReturn($migrationName);
        $this->migrator->shouldReceive('resolveMigration')->once()->with($file)->andReturn($migration);
        $this->migrator->shouldReceive('write')->twice();
        $this->migrator->shouldReceive('safeRollback')->once()->with($file)->andThrow($exception);

        expect(fn () => $this->migrator->undoSafe($file))->toThrow(Exception::class);
    });
});

describe('getAdapter method', function () {
    it('lazy initializes adapter when adapter is null', function () {
        // Set up adapter factory
        $this->migrator->setAdapterFactory($this->mockAdapterFactory);
        $this->mockAdapterFactory->shouldReceive('create')->once()->andReturn($this->mockAdapter);

        // Use reflection to access protected method
        $reflection = new ReflectionClass($this->migrator);
        $method = $reflection->getMethod('getAdapter');
        $method->setAccessible(true);

        $result = $method->invoke($this->migrator);

        expect($result)->toBe($this->mockAdapter);
    });

    it('returns existing adapter when already initialized', function () {
        // Set up adapter factory and get adapter once
        $this->migrator->setAdapterFactory($this->mockAdapterFactory);
        $this->mockAdapterFactory->shouldReceive('create')->once()->andReturn($this->mockAdapter);

        // Use reflection to access protected method
        $reflection = new ReflectionClass($this->migrator);
        $method = $reflection->getMethod('getAdapter');
        $method->setAccessible(true);

        // First call initializes
        $method->invoke($this->migrator);
        // Second call should return same adapter without calling factory again
        $result = $method->invoke($this->migrator);

        expect($result)->toBe($this->mockAdapter);
    });

    it('uses legacy support when adapterFactory is null', function () {
        // Enable sqlite driver for test
        config(['smart-migration.drivers.sqlite.enabled' => true]);

        // Mock the app container to return adapter factory
        app()->instance(\Flux\Database\DatabaseAdapterFactoryInterface::class, $this->mockAdapterFactory);
        $this->mockAdapterFactory->shouldReceive('create')->once()->andReturn($this->mockAdapter);

        // Use reflection to access protected method
        $reflection = new ReflectionClass($this->migrator);
        $method = $reflection->getMethod('getAdapter');
        $method->setAccessible(true);

        $result = $method->invoke($this->migrator);

        expect($result)->toBe($this->mockAdapter);
    });
});

describe('analyzeAndBackup method', function () {
    it('skips backup when auto backup is disabled', function () {
        config(['smart-migration.safety.auto_backup' => false]);

        $this->migrator->shouldNotReceive('backupTable');

        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->migrator);
        $method = $reflection->getMethod('analyzeAndBackup');
        $method->setAccessible(true);

        $method->invoke($this->migrator, '/path/to/migration.php');
    });

    it('analyzes migration content and backs up existing tables', function () {
        config(['smart-migration.safety.auto_backup' => true]);

        $migrationContent = '<?php
        Schema::create("new_users", function () {});
        Schema::table("existing_posts", function () {});
        Schema::drop("old_table");
        Schema::dropIfExists("maybe_table");
        ';

        $tempFile = tempnam(sys_get_temp_dir(), 'migration_test');
        file_put_contents($tempFile, $migrationContent);

        // Mock Schema facade to return true for existing tables
        Schema::shouldReceive('hasTable')->with('new_users')->once()->andReturn(false);
        Schema::shouldReceive('hasTable')->with('existing_posts')->once()->andReturn(true);
        Schema::shouldReceive('hasTable')->with('old_table')->once()->andReturn(true);
        Schema::shouldReceive('hasTable')->with('maybe_table')->once()->andReturn(false);

        // Should only backup existing tables
        $this->migrator->shouldReceive('backupTable')->with('existing_posts')->once();
        $this->migrator->shouldReceive('backupTable')->with('old_table')->once();
        $this->migrator->shouldNotReceive('backupTable')->with('new_users');
        $this->migrator->shouldNotReceive('backupTable')->with('maybe_table');

        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->migrator);
        $method = $reflection->getMethod('analyzeAndBackup');
        $method->setAccessible(true);

        $method->invoke($this->migrator, $tempFile);

        // Check affected tables were recorded
        $affectedTablesProperty = $reflection->getProperty('affectedTables');
        $affectedTablesProperty->setAccessible(true);
        $affectedTables = $affectedTablesProperty->getValue($this->migrator);

        expect($affectedTables)->toContain('existing_posts');
        expect($affectedTables)->toContain('old_table');
        expect($affectedTables)->not->toContain('new_users');
        expect($affectedTables)->not->toContain('maybe_table');

        unlink($tempFile);
    });
});

describe('backupTable method', function () {
    it('backs up table structure and data', function () {
        $table = 'users';
        $structure = 'CREATE TABLE users...';
        $data = [['id' => 1, 'name' => 'John']];
        $count = 1;

        $this->mockAdapter->shouldReceive('getTableStructure')->once()->with($table)->andReturn($structure);
        $this->mockAdapter->shouldReceive('getTableData')->once()->with($table)->andReturn($data);
        $this->mockAdapter->shouldReceive('getTableRowCount')->once()->with($table)->andReturn($count);

        $this->migrator->shouldReceive('write')->once();
        $this->migrator->shouldReceive('getAdapter')->once()->andReturn($this->mockAdapter);

        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->migrator);
        $method = $reflection->getMethod('backupTable');
        $method->setAccessible(true);

        $method->invoke($this->migrator, $table);

        // Check that backup data was stored
        $backupDataProperty = $reflection->getProperty('backupData');
        $backupDataProperty->setAccessible(true);
        $backupData = $backupDataProperty->getValue($this->migrator);

        expect($backupData[$table])->toEqual([
            'structure' => $structure,
            'data' => $data,
            'count' => $count,
        ]);
    });
});

describe('restoreBackups method', function () {
    it('restores backed up tables', function () {
        $table = 'users';
        $backupData = [
            'structure' => 'CREATE TABLE users...',
            'data' => [['id' => 1, 'name' => 'John']],
            'count' => 1,
        ];

        // Set backup data using reflection
        $reflection = new ReflectionClass($this->migrator);
        $backupDataProperty = $reflection->getProperty('backupData');
        $backupDataProperty->setAccessible(true);
        $backupDataProperty->setValue($this->migrator, [$table => $backupData]);

        $this->migrator->shouldReceive('write')->twice();
        $this->migrator->shouldReceive('getAdapter')->once()->andReturn($this->mockAdapter);

        Schema::shouldReceive('dropIfExists')->once()->with($table);

        $this->mockAdapter->shouldReceive('execute')->once()->with($backupData['structure']);

        $tableMock = \Mockery::mock();
        $tableMock->shouldReceive('insert')->once()->with($backupData['data']);
        DB::shouldReceive('table')->once()->with($table)->andReturn($tableMock);

        // Use reflection to test protected method
        $method = $reflection->getMethod('restoreBackups');
        $method->setAccessible(true);

        $method->invoke($this->migrator);
    });

    it('handles restore failures gracefully', function () {
        $table = 'users';
        $backupData = [
            'structure' => 'CREATE TABLE users...',
            'data' => [['id' => 1, 'name' => 'John']],
            'count' => 1,
        ];

        // Set backup data using reflection
        $reflection = new ReflectionClass($this->migrator);
        $backupDataProperty = $reflection->getProperty('backupData');
        $backupDataProperty->setAccessible(true);
        $backupDataProperty->setValue($this->migrator, [$table => $backupData]);

        $this->migrator->shouldReceive('write')
            ->once()
            ->with("<comment>🔄 Attempting to restore table:</comment> <info>{$table}</info>");
        $this->migrator->shouldReceive('write')
            ->once()
            ->with("<error>❌ Failed to restore table:</error> <comment>{$table}</comment>");

        Schema::shouldReceive('dropIfExists')->once()->with($table)->andThrow(new Exception('Drop failed'));

        // Use reflection to test protected method
        $method = $reflection->getMethod('restoreBackups');
        $method->setAccessible(true);

        // Should not throw exception, just log the failure
        $method->invoke($this->migrator);
    });
});

describe('safeRollback method', function () {
    it('falls back to regular rollback when safe rollback is disabled', function () {
        $file = '/path/to/migration.php';
        $migration = \Mockery::mock();

        config(['smart-migration.safety.safe_rollback' => false]);

        $this->migrator->shouldReceive('resolveMigration')->once()->with($file)->andReturn($migration);
        $this->migrator->shouldReceive('runMigration')->once()->with($migration, 'down');

        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->migrator);
        $method = $reflection->getMethod('safeRollback');
        $method->setAccessible(true);

        $method->invoke($this->migrator, $file);
    });

    it('archives dropped columns when safe rollback is enabled', function () {
        $migrationContent = '<?php
        Schema::table("users", function ($table) {
            $table->dropColumn("old_email");
            $table->dropColumn("temp_field");
        });
        ';

        $tempFile = tempnam(sys_get_temp_dir(), 'migration_test');
        file_put_contents($tempFile, $migrationContent);

        config([
            'smart-migration.safety.safe_rollback' => true,
            'smart-migration.archive.include_timestamp' => true,
            'smart-migration.drivers.sqlite.enabled' => true, // Enable sqlite for test
        ]);

        // Mock the now() function result for consistent timestamp
        $timestamp = '20240101_120000';
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::createFromFormat('Ymd_His', $timestamp));

        $this->migrator->shouldReceive('archiveColumn')->with('users', 'old_email', $timestamp)->once();
        $this->migrator->shouldReceive('archiveColumn')->with('users', 'temp_field', $timestamp)->once();

        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->migrator);
        $method = $reflection->getMethod('safeRollback');
        $method->setAccessible(true);

        $method->invoke($this->migrator, $tempFile);

        unlink($tempFile);
        \Carbon\Carbon::setTestNow(); // Reset
    });

    it('archives dropped tables when safe rollback is enabled', function () {
        $migrationContent = '<?php
        Schema::drop("old_users");
        Schema::dropIfExists("temp_posts");
        ';

        $tempFile = tempnam(sys_get_temp_dir(), 'migration_test');
        file_put_contents($tempFile, $migrationContent);

        config([
            'smart-migration.safety.safe_rollback' => true,
            'smart-migration.archive.include_timestamp' => false,
            'smart-migration.drivers.sqlite.enabled' => true, // Enable sqlite for test
        ]);

        $this->migrator->shouldReceive('archiveTable')->with('old_users', '')->once();
        $this->migrator->shouldReceive('archiveTable')->with('temp_posts', '')->once();

        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->migrator);
        $method = $reflection->getMethod('safeRollback');
        $method->setAccessible(true);

        $method->invoke($this->migrator, $tempFile);

        unlink($tempFile);
    });

    it('handles complex migration content with mixed operations', function () {
        // The regex parsing looks for dropColumn within the same Schema::table block
        $migrationContent = '<?php
        Schema::table("users", function ($table) {
            $table->dropColumn("old_email");
            $table->dropColumn("legacy_field");
        });
        Schema::drop("old_table");
        Schema::dropIfExists("maybe_table");
        ';

        $tempFile = tempnam(sys_get_temp_dir(), 'migration_test');
        file_put_contents($tempFile, $migrationContent);

        config([
            'smart-migration.safety.safe_rollback' => true,
            'smart-migration.archive.include_timestamp' => true,
            'smart-migration.drivers.sqlite.enabled' => true, // Enable sqlite for test
        ]);

        $timestamp = '20240101_120000';
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::createFromFormat('Ymd_His', $timestamp));

        // Should archive columns (both found in the same Schema::table block)
        $this->migrator->shouldReceive('archiveColumn')->with('users', 'old_email', $timestamp)->once();
        $this->migrator->shouldReceive('archiveColumn')->with('users', 'legacy_field', $timestamp)->once();

        // Should archive tables
        $this->migrator->shouldReceive('archiveTable')->with('old_table', $timestamp)->once();
        $this->migrator->shouldReceive('archiveTable')->with('maybe_table', $timestamp)->once();

        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->migrator);
        $method = $reflection->getMethod('safeRollback');
        $method->setAccessible(true);

        $method->invoke($this->migrator, $tempFile);

        unlink($tempFile);
        \Carbon\Carbon::setTestNow(); // Reset
    });
});

describe('archiveColumn method', function () {
    it('archives existing column', function () {
        $table = 'users';
        $column = 'old_email';
        $timestamp = '20240101_120000';

        config(['smart-migration.archive.column_prefix' => 'archived_']);

        $this->migrator->shouldReceive('getAdapter')->once()->andReturn($this->mockAdapter);
        $this->mockAdapter->shouldReceive('columnExists')->once()->with($table, $column)->andReturn(true);
        $this->mockAdapter->shouldReceive('archiveColumn')->once()->with($table, $column, 'archived_old_email_20240101_120000');
        $this->migrator->shouldReceive('write')->once();

        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->migrator);
        $method = $reflection->getMethod('archiveColumn');
        $method->setAccessible(true);

        $method->invoke($this->migrator, $table, $column, $timestamp);
    });

    it('skips archiving non-existing column', function () {
        $table = 'users';
        $column = 'non_existing';
        $timestamp = '20240101_120000';

        $this->migrator->shouldReceive('getAdapter')->once()->andReturn($this->mockAdapter);
        $this->mockAdapter->shouldReceive('columnExists')->once()->with($table, $column)->andReturn(false);
        $this->mockAdapter->shouldNotReceive('archiveColumn');
        $this->migrator->shouldNotReceive('write');

        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->migrator);
        $method = $reflection->getMethod('archiveColumn');
        $method->setAccessible(true);

        $method->invoke($this->migrator, $table, $column, $timestamp);
    });
});

describe('archiveTable method', function () {
    it('archives existing table', function () {
        $table = 'old_table';
        $timestamp = '20240101_120000';

        config(['smart-migration.archive.table_prefix' => 'archived_']);

        $this->migrator->shouldReceive('getAdapter')->once()->andReturn($this->mockAdapter);
        $this->mockAdapter->shouldReceive('tableExists')->once()->with($table)->andReturn(true);
        $this->mockAdapter->shouldReceive('archiveTable')->once()->with($table, 'archived_old_table_20240101_120000');
        $this->migrator->shouldReceive('write')->once();

        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->migrator);
        $method = $reflection->getMethod('archiveTable');
        $method->setAccessible(true);

        $method->invoke($this->migrator, $table, $timestamp);
    });

    it('skips archiving non-existing table', function () {
        $table = 'non_existing';
        $timestamp = '20240101_120000';

        $this->migrator->shouldReceive('getAdapter')->once()->andReturn($this->mockAdapter);
        $this->mockAdapter->shouldReceive('tableExists')->once()->with($table)->andReturn(false);
        $this->mockAdapter->shouldNotReceive('archiveTable');
        $this->migrator->shouldNotReceive('write');

        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->migrator);
        $method = $reflection->getMethod('archiveTable');
        $method->setAccessible(true);

        $method->invoke($this->migrator, $table, $timestamp);
    });
});

describe('getAffectedTables method', function () {
    it('identifies affected tables from migration content', function () {
        $migrationContent = '<?php
        Schema::create("users", function () {});
        Schema::table("posts", function () {});
        Schema::drop("old_table");
        Schema::rename("temp", "permanent");
        ';

        $tempFile = tempnam(sys_get_temp_dir(), 'migration_test');
        file_put_contents($tempFile, $migrationContent);

        $tables = $this->migrator->getAffectedTables($tempFile);

        expect($tables)->toContain('users');
        expect($tables)->toContain('posts');
        expect($tables)->toContain('old_table');
        expect($tables)->toContain('temp');

        unlink($tempFile);
    });

    it('returns unique table names', function () {
        $migrationContent = '<?php
        Schema::table("users", function () {});
        Schema::table("users", function () {});
        ';

        $tempFile = tempnam(sys_get_temp_dir(), 'migration_test');
        file_put_contents($tempFile, $migrationContent);

        $tables = $this->migrator->getAffectedTables($tempFile);

        expect($tables)->toBe(['users']);

        unlink($tempFile);
    });
});

describe('estimateDataLoss method', function () {
    it('estimates data loss from table drops', function () {
        $migrationContent = '<?php
        Schema::drop("users");
        Schema::dropIfExists("posts");
        ';

        $tempFile = tempnam(sys_get_temp_dir(), 'migration_test');
        file_put_contents($tempFile, $migrationContent);

        Schema::shouldReceive('hasTable')->with('users')->once()->andReturn(true);
        Schema::shouldReceive('hasTable')->with('posts')->once()->andReturn(true);

        $userTableMock = \Mockery::mock();
        $userTableMock->shouldReceive('count')->once()->andReturn(100);
        $postsTableMock = \Mockery::mock();
        $postsTableMock->shouldReceive('count')->once()->andReturn(50);

        DB::shouldReceive('table')->with('users')->once()->andReturn($userTableMock);
        DB::shouldReceive('table')->with('posts')->once()->andReturn($postsTableMock);

        $loss = $this->migrator->estimateDataLoss($tempFile);

        expect($loss)->toHaveCount(2);
        expect($loss[0])->toEqual(['type' => 'table', 'name' => 'users', 'rows' => 100]);
        expect($loss[1])->toEqual(['type' => 'table', 'name' => 'posts', 'rows' => 50]);

        unlink($tempFile);
    });

    it('estimates data loss from column drops', function () {
        $migrationContent = '<?php
        Schema::table("users", function ($table) {
            $table->dropColumn("old_email");
        });
        ';

        $tempFile = tempnam(sys_get_temp_dir(), 'migration_test');
        file_put_contents($tempFile, $migrationContent);

        Schema::shouldReceive('hasTable')->with('users')->once()->andReturn(true);
        Schema::shouldReceive('hasColumn')->with('users', 'old_email')->once()->andReturn(true);

        $userTableMock = \Mockery::mock();
        $userTableMock->shouldReceive('whereNotNull')->with('old_email')->once()->andReturnSelf();
        $userTableMock->shouldReceive('count')->once()->andReturn(75);

        DB::shouldReceive('table')->with('users')->once()->andReturn($userTableMock);

        $loss = $this->migrator->estimateDataLoss($tempFile);

        expect($loss)->toHaveCount(1);
        expect($loss[0])->toEqual(['type' => 'column', 'table' => 'users', 'name' => 'old_email', 'rows' => 75]);

        unlink($tempFile);
    });
});

describe('resolveMigration method', function () {
    it('returns migration object for anonymous classes', function () {
        $migration = new class {};

        $this->mockFilesystem->shouldReceive('getRequire')->once()->with('/path/to/migration.php')->andReturn($migration);

        $result = $this->migrator->resolveMigration('/path/to/migration.php');

        expect($result)->toBe($migration);
    });

    it('resolves named migration classes', function () {
        $this->mockFilesystem->shouldReceive('getRequire')->once()->with('/path/to/2024_01_01_000000_create_users_table.php')->andReturn('CreateUsersTable');

        $this->migrator->shouldReceive('getMigrationClass')->once()->with('CreateUsersTable')->andReturn('CreateUsersTable');

        // We can't easily test instantiation of named classes without actual class files
        // This test verifies the logic path for named classes
        expect(function () {
            $this->migrator->resolveMigration('/path/to/2024_01_01_000000_create_users_table.php');
        })->toThrow(Error::class); // Expected since 'CreateUsersTable' is not a real class
    });
});

describe('recordPerformance method', function () {
    it('displays anomaly warnings when anomalies are detected', function () {
        $mockAnomalyDetector = \Mockery::mock(\Flux\Monitoring\AnomalyDetector::class);
        $mockPerformanceBaseline = \Mockery::mock(\Flux\Monitoring\PerformanceBaseline::class);

        // Use reflection on the actual SafeMigrator class
        $reflection = new ReflectionClass(SafeMigrator::class);

        $anomalyDetectorProperty = $reflection->getProperty('anomalyDetector');
        $anomalyDetectorProperty->setAccessible(true);
        $anomalyDetectorProperty->setValue($this->migrator, $mockAnomalyDetector);

        $performanceBaselineProperty = $reflection->getProperty('performanceBaseline');
        $performanceBaselineProperty->setAccessible(true);
        $performanceBaselineProperty->setValue($this->migrator, $mockPerformanceBaseline);

        // Mock anomaly detection result with anomalies
        $mockAnomalyDetector->shouldReceive('detect')
            ->once()
            ->andReturn([
                'has_anomalies' => true,
                'anomalies' => [
                    ['severity' => 'critical', 'message' => 'Duration exceeds baseline by 300%'],
                    ['severity' => 'high', 'message' => 'Memory usage exceeds baseline by 200%'],
                    ['severity' => 'medium', 'message' => 'Query count exceeds baseline by 100%'],
                    ['severity' => 'low', 'message' => 'Minor deviation detected'],
                ],
            ]);

        $mockPerformanceBaseline->shouldReceive('record')->once();

        // Expect warning output for anomalies
        $this->migrator->shouldReceive('write')
            ->with(\Mockery::pattern('/Performance anomalies detected/'))
            ->once();

        // Expect output for each severity level
        $this->migrator->shouldReceive('write')
            ->with(\Mockery::pattern('/critical/'))
            ->once();

        $this->migrator->shouldReceive('write')
            ->with(\Mockery::pattern('/high/'))
            ->once();

        $this->migrator->shouldReceive('write')
            ->with(\Mockery::pattern('/medium/'))
            ->once();

        $this->migrator->shouldReceive('write')
            ->with(\Mockery::pattern('/low/'))
            ->once();

        $method = $reflection->getMethod('recordPerformance');
        $method->setAccessible(true);

        $metrics = [
            'duration_ms' => 100.0,
            'memory_mb' => 10.0,
            'query_count' => 5,
        ];

        $method->invoke($this->migrator, 'test_migration', $metrics);

        expect(true)->toBeTrue();
    });

    it('handles performance tracking exceptions gracefully', function () {
        $mockAnomalyDetector = \Mockery::mock(\Flux\Monitoring\AnomalyDetector::class);

        // Use reflection on the actual SafeMigrator class
        $reflection = new ReflectionClass(SafeMigrator::class);

        $anomalyDetectorProperty = $reflection->getProperty('anomalyDetector');
        $anomalyDetectorProperty->setAccessible(true);
        $anomalyDetectorProperty->setValue($this->migrator, $mockAnomalyDetector);

        // Mock anomaly detection to throw exception
        $mockAnomalyDetector->shouldReceive('detect')
            ->once()
            ->andThrow(new \Exception('Tracking error'));

        // Set verbose_errors config
        config(['smart-migration.monitoring.verbose_errors' => true]);

        $this->migrator->shouldReceive('write')
            ->with(\Mockery::pattern('/Performance tracking error/'))
            ->once();

        $method = $reflection->getMethod('recordPerformance');
        $method->setAccessible(true);

        $metrics = [
            'duration_ms' => 100.0,
            'memory_mb' => 10.0,
            'query_count' => 5,
        ];

        // Should not throw - just log the error
        $method->invoke($this->migrator, 'test_migration', $metrics);

        expect(true)->toBeTrue();
    });
});
