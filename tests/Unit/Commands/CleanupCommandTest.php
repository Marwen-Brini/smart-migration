<?php

use Flux\Cleanup\ArchiveCleanupService;
use Flux\Commands\CleanupCommand;

beforeEach(function () {
    $this->mockCleanupService = Mockery::mock(ArchiveCleanupService::class);
    $this->command = Mockery::mock(CleanupCommand::class)->makePartial()->shouldAllowMockingProtectedMethods();

    // Set the cleanup service on the command
    $reflection = new ReflectionClass($this->command);
    $property = $reflection->getProperty('cleanupService');
    $property->setAccessible(true);
    $property->setValue($this->command, $this->mockCleanupService);
});

afterEach(function () {
    Mockery::close();
});

describe('handle method', function () {
    it('shows statistics when stats option is provided', function () {
        $this->command->shouldReceive('option')->with('stats')->once()->andReturn(true);
        $this->command->shouldReceive('showStatistics')->once()->andReturn(CleanupCommand::SUCCESS);

        $result = $this->command->handle();

        expect($result)->toBe(CleanupCommand::SUCCESS);
    });

    it('shows disabled message when cleanup is disabled', function () {
        $this->command->shouldReceive('option')->with('stats')->once()->andReturn(false);
        $this->command->shouldReceive('info')->once()->with('🧹 Smart Migration - Archive Cleanup');
        $this->command->shouldReceive('comment')->once();
        $this->command->shouldReceive('newLine')->once();
        $this->command->shouldReceive('option')->with('dry-run')->once()->andReturn(false);

        $this->mockCleanupService->shouldReceive('cleanup')->once()->with(false)->andReturn([
            'status' => 'disabled',
            'message' => 'Auto cleanup is disabled in configuration',
        ]);

        $this->command->shouldReceive('warn')->once()->with('⚠️  Auto cleanup is disabled in configuration');
        $this->command->shouldReceive('comment')->once()->with('Enable auto_cleanup in config/smart-migration.php to use this feature.');

        $result = $this->command->handle();

        expect($result)->toBe(CleanupCommand::FAILURE);
    });

    it('shows skipped message when retention is set to keep forever', function () {
        $this->command->shouldReceive('option')->with('stats')->once()->andReturn(false);
        $this->command->shouldReceive('info')->once()->with('🧹 Smart Migration - Archive Cleanup');
        $this->command->shouldReceive('comment')->once();
        $this->command->shouldReceive('newLine')->once();
        $this->command->shouldReceive('option')->with('dry-run')->once()->andReturn(false);

        $this->mockCleanupService->shouldReceive('cleanup')->once()->with(false)->andReturn([
            'status' => 'skipped',
            'message' => 'Archive retention is set to keep forever',
        ]);

        $this->command->shouldReceive('info')->once()->with('ℹ️  Archive retention is set to keep forever');

        $result = $this->command->handle();

        expect($result)->toBe(CleanupCommand::SUCCESS);
    });

    it('performs cleanup and displays results', function () {
        $cleanupResult = [
            'status' => 'success',
            'dry_run' => false,
            'tables_cleaned' => [
                ['name' => 'archived_users_20240101_120000', 'archived_date' => '2024-01-01T12:00:00+00:00', 'rows' => 100],
            ],
            'columns_cleaned' => [
                ['table' => 'users', 'column' => 'archived_email_20240101_120000', 'archived_date' => '2024-01-01T12:00:00+00:00'],
            ],
            'total_rows_deleted' => 100,
            'retention_days' => 30,
            'cutoff_date' => '2024-01-01T12:00:00+00:00',
        ];

        $this->command->shouldReceive('option')->with('stats')->once()->andReturn(false);
        $this->command->shouldReceive('info')->once()->with('🧹 Smart Migration - Archive Cleanup');
        $this->command->shouldReceive('comment')->once();
        $this->command->shouldReceive('newLine')->twice();
        $this->command->shouldReceive('option')->with('dry-run')->once()->andReturn(false);

        $this->mockCleanupService->shouldReceive('cleanup')->once()->with(false)->andReturn($cleanupResult);

        $this->command->shouldReceive('displayCleanupResults')->once()->with($cleanupResult);
        $this->command->shouldReceive('info')->once()->with('✅ Cleanup completed successfully!');

        $result = $this->command->handle();

        expect($result)->toBe(CleanupCommand::SUCCESS);
    });

    it('shows dry run warning when dry-run option is provided', function () {
        $cleanupResult = [
            'status' => 'success',
            'dry_run' => true,
            'tables_cleaned' => [],
            'columns_cleaned' => [],
            'total_rows_deleted' => 0,
            'retention_days' => 30,
            'cutoff_date' => '2024-01-01T12:00:00+00:00',
        ];

        $this->command->shouldReceive('option')->with('stats')->once()->andReturn(false);
        $this->command->shouldReceive('info')->once()->with('🧹 Smart Migration - Archive Cleanup');
        $this->command->shouldReceive('comment')->once();
        $this->command->shouldReceive('newLine')->twice();
        $this->command->shouldReceive('option')->with('dry-run')->once()->andReturn(true);
        $this->command->shouldReceive('warn')->once()->with('DRY RUN MODE - No data will be deleted');

        $this->mockCleanupService->shouldReceive('cleanup')->once()->with(true)->andReturn($cleanupResult);

        $this->command->shouldReceive('displayCleanupResults')->once()->with($cleanupResult);

        $result = $this->command->handle();

        expect($result)->toBe(CleanupCommand::SUCCESS);
    });

    it('does not show success message when no data was cleaned', function () {
        $cleanupResult = [
            'status' => 'success',
            'dry_run' => false,
            'tables_cleaned' => [],
            'columns_cleaned' => [],
            'total_rows_deleted' => 0,
            'retention_days' => 30,
            'cutoff_date' => '2024-01-01T12:00:00+00:00',
        ];

        $this->command->shouldReceive('option')->with('stats')->once()->andReturn(false);
        $this->command->shouldReceive('info')->once()->with('🧹 Smart Migration - Archive Cleanup');
        $this->command->shouldReceive('comment')->once();
        $this->command->shouldReceive('newLine')->once();
        $this->command->shouldReceive('option')->with('dry-run')->once()->andReturn(false);

        $this->mockCleanupService->shouldReceive('cleanup')->once()->with(false)->andReturn($cleanupResult);

        $this->command->shouldReceive('displayCleanupResults')->once()->with($cleanupResult);
        $this->command->shouldNotReceive('info')->with('✅ Cleanup completed successfully!');

        $result = $this->command->handle();

        expect($result)->toBe(CleanupCommand::SUCCESS);
    });
});

describe('displayCleanupResults method', function () {
    it('displays all result information correctly', function () {
        $result = [
            'retention_days' => 30,
            'cutoff_date' => '2024-01-01T12:00:00+00:00',
            'tables_cleaned' => [
                ['name' => 'archived_users_20240101_120000', 'archived_date' => '2024-01-01T12:00:00+00:00', 'rows' => 1500],
                ['name' => 'archived_posts_20240101_120000', 'archived_date' => '2024-01-01T12:00:00+00:00', 'rows' => 500],
            ],
            'columns_cleaned' => [
                ['table' => 'users', 'column' => 'archived_email_20240101_120000', 'archived_date' => '2024-01-01T12:00:00+00:00'],
                ['table' => 'users', 'column' => 'archived_phone_20240101_120000', 'archived_date' => '2024-01-01T12:00:00+00:00'],
            ],
            'total_rows_deleted' => 2000,
            'dry_run' => false,
        ];

        $this->command->shouldReceive('info')->times(4);
        $this->command->shouldReceive('line')->times(5);
        $this->command->shouldReceive('newLine')->times(3);
        $this->command->shouldReceive('table')->twice();

        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('displayCleanupResults');
        $method->setAccessible(true);

        $method->invoke($this->command, $result);
    });

    it('displays message when no data needs cleaning', function () {
        $result = [
            'retention_days' => 30,
            'cutoff_date' => '2024-01-01T12:00:00+00:00',
            'tables_cleaned' => [],
            'columns_cleaned' => [],
            'total_rows_deleted' => 0,
            'dry_run' => false,
        ];

        $this->command->shouldReceive('info')->twice();
        $this->command->shouldReceive('line')->twice();
        $this->command->shouldReceive('newLine')->once();
        $this->command->shouldNotReceive('table');

        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('displayCleanupResults');
        $method->setAccessible(true);

        $method->invoke($this->command, $result);
    });

    it('shows dry run message when in dry run mode', function () {
        $result = [
            'retention_days' => 30,
            'cutoff_date' => '2024-01-01T12:00:00+00:00',
            'tables_cleaned' => [
                ['name' => 'archived_users_20240101_120000', 'archived_date' => '2024-01-01T12:00:00+00:00', 'rows' => 100],
            ],
            'columns_cleaned' => [],
            'total_rows_deleted' => 100,
            'dry_run' => true,
        ];

        $this->command->shouldReceive('info')->times(3);
        $this->command->shouldReceive('line')->times(5);
        $this->command->shouldReceive('newLine')->times(3);
        $this->command->shouldReceive('table')->once();
        $this->command->shouldReceive('comment')->once()->with('Run without --dry-run to perform the actual cleanup.');

        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('displayCleanupResults');
        $method->setAccessible(true);

        $method->invoke($this->command, $result);
    });

    it('formats numbers correctly in tables', function () {
        $result = [
            'retention_days' => 30,
            'cutoff_date' => '2024-01-01T12:00:00+00:00',
            'tables_cleaned' => [
                ['name' => 'archived_users_20240101_120000', 'archived_date' => '2024-01-01T12:00:00+00:00', 'rows' => 123456],
            ],
            'columns_cleaned' => [],
            'total_rows_deleted' => 123456,
            'dry_run' => false,
        ];

        $this->command->shouldReceive('info')->times(3);
        $this->command->shouldReceive('line')->times(5);
        $this->command->shouldReceive('newLine')->times(2);
        $this->command->shouldReceive('table')->once()->withArgs(function ($headers, $rows) {
            return $rows[0][2] === '123,456'; // Check that number is formatted
        });

        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('displayCleanupResults');
        $method->setAccessible(true);

        $method->invoke($this->command, $result);
    });
});

describe('showStatistics method', function () {
    it('displays comprehensive statistics', function () {
        $stats = [
            'auto_cleanup_enabled' => true,
            'retention_days' => 30,
            'archived_tables' => [
                ['name' => 'archived_users_20240101_120000', 'archived_date' => '2024-01-01T12:00:00+00:00', 'rows' => 1500],
                ['name' => 'archived_posts_20240101_120000', 'archived_date' => '2024-01-01T12:00:00+00:00', 'rows' => 500],
            ],
            'archived_columns' => [
                ['table' => 'users', 'column' => 'archived_email_20240101_120000', 'archived_date' => '2024-01-01T12:00:00+00:00'],
                ['table' => 'users', 'column' => 'archived_phone_20240101_120000', 'archived_date' => '2024-01-01T12:00:00+00:00'],
            ],
            'total_archived_tables' => 2,
            'total_archived_columns' => 2,
            'total_archived_rows' => 2000,
        ];

        $this->mockCleanupService->shouldReceive('getStatistics')->once()->andReturn($stats);

        $this->command->shouldReceive('info')->times(5);
        $this->command->shouldReceive('comment')->twice();
        $this->command->shouldReceive('line')->times(5);
        $this->command->shouldReceive('newLine')->times(5);
        $this->command->shouldReceive('table')->twice();

        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('showStatistics');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        expect($result)->toBe(CleanupCommand::SUCCESS);
    });

    it('displays when auto cleanup is disabled', function () {
        $stats = [
            'auto_cleanup_enabled' => false,
            'retention_days' => 0,
            'archived_tables' => [],
            'archived_columns' => [],
            'total_archived_tables' => 0,
            'total_archived_columns' => 0,
            'total_archived_rows' => 0,
        ];

        $this->mockCleanupService->shouldReceive('getStatistics')->once()->andReturn($stats);

        $this->command->shouldReceive('info')->times(3);
        $this->command->shouldReceive('comment')->once();
        $this->command->shouldReceive('line')->times(5);
        $this->command->shouldReceive('newLine')->twice();
        $this->command->shouldNotReceive('table');

        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('showStatistics');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        expect($result)->toBe(CleanupCommand::SUCCESS);
    });

    it('shows retention policy information when enabled', function () {
        $stats = [
            'auto_cleanup_enabled' => true,
            'retention_days' => 30,
            'archived_tables' => [],
            'archived_columns' => [],
            'total_archived_tables' => 0,
            'total_archived_columns' => 0,
            'total_archived_rows' => 0,
        ];

        $this->mockCleanupService->shouldReceive('getStatistics')->once()->andReturn($stats);

        $this->command->shouldReceive('info')->times(3);
        $this->command->shouldReceive('comment')->twice();
        $this->command->shouldReceive('line')->times(5);
        $this->command->shouldReceive('newLine')->times(3);

        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('showStatistics');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        expect($result)->toBe(CleanupCommand::SUCCESS);
    });

    it('formats large numbers correctly in statistics', function () {
        $stats = [
            'auto_cleanup_enabled' => true,
            'retention_days' => 30,
            'archived_tables' => [
                ['name' => 'archived_users_20240101_120000', 'archived_date' => '2024-01-01T12:00:00+00:00', 'rows' => 1234567],
            ],
            'archived_columns' => [],
            'total_archived_tables' => 1,
            'total_archived_columns' => 0,
            'total_archived_rows' => 1234567,
        ];

        $this->mockCleanupService->shouldReceive('getStatistics')->once()->andReturn($stats);

        $this->command->shouldReceive('info')->times(4);
        $this->command->shouldReceive('comment')->twice();
        $this->command->shouldReceive('line')->times(5);
        $this->command->shouldReceive('newLine')->times(4);
        $this->command->shouldReceive('table')->once()->withArgs(function ($headers, $rows) {
            return $rows[0][2] === '1,234,567'; // Check formatted number
        });

        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('showStatistics');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        expect($result)->toBe(CleanupCommand::SUCCESS);
    });

    it('displays only archived tables when no columns are archived', function () {
        $stats = [
            'auto_cleanup_enabled' => true,
            'retention_days' => 30,
            'archived_tables' => [
                ['name' => 'archived_users_20240101_120000', 'archived_date' => '2024-01-01T12:00:00+00:00', 'rows' => 100],
            ],
            'archived_columns' => [],
            'total_archived_tables' => 1,
            'total_archived_columns' => 0,
            'total_archived_rows' => 100,
        ];

        $this->mockCleanupService->shouldReceive('getStatistics')->once()->andReturn($stats);

        $this->command->shouldReceive('info')->times(4);
        $this->command->shouldReceive('comment')->twice();
        $this->command->shouldReceive('line')->times(5);
        $this->command->shouldReceive('newLine')->times(4);
        $this->command->shouldReceive('table')->once(); // Only one table for archived tables

        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('showStatistics');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        expect($result)->toBe(CleanupCommand::SUCCESS);
    });

    it('displays only archived columns when no tables are archived', function () {
        $stats = [
            'auto_cleanup_enabled' => true,
            'retention_days' => 30,
            'archived_tables' => [],
            'archived_columns' => [
                ['table' => 'users', 'column' => 'archived_email_20240101_120000', 'archived_date' => '2024-01-01T12:00:00+00:00'],
            ],
            'total_archived_tables' => 0,
            'total_archived_columns' => 1,
            'total_archived_rows' => 0,
        ];

        $this->mockCleanupService->shouldReceive('getStatistics')->once()->andReturn($stats);

        $this->command->shouldReceive('info')->times(4);
        $this->command->shouldReceive('comment')->twice();
        $this->command->shouldReceive('line')->times(5);
        $this->command->shouldReceive('newLine')->times(4);
        $this->command->shouldReceive('table')->once(); // Only one table for archived columns

        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('showStatistics');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        expect($result)->toBe(CleanupCommand::SUCCESS);
    });
});
