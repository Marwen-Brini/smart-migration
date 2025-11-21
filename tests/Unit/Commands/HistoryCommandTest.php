<?php

use Flux\Commands\HistoryCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->command = Mockery::mock(HistoryCommand::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    // Mock output methods
    $this->command->shouldReceive('info')->andReturnNull()->byDefault();
    $this->command->shouldReceive('comment')->andReturnNull()->byDefault();
    $this->command->shouldReceive('warn')->andReturnNull()->byDefault();
    $this->command->shouldReceive('error')->andReturnNull()->byDefault();
    $this->command->shouldReceive('line')->andReturnNull()->byDefault();
    $this->command->shouldReceive('newLine')->andReturnNull()->byDefault();
    $this->command->shouldReceive('table')->andReturnNull()->byDefault();
});

afterEach(function () {
    Mockery::close();
});

describe('handle method', function () {
    it('displays warning when no migrations found', function () {
        $this->command->shouldReceive('option')->with('json')->andReturn(false);
        $this->command->shouldReceive('option')->with('limit')->andReturn(20);
        $this->command->shouldReceive('option')->with('reverse')->andReturn(false);

        // Mock empty migrations
        $mockBuilder = Mockery::mock();
        $mockBuilder->shouldReceive('orderBy')->andReturnSelf();
        $mockBuilder->shouldReceive('get')->andReturn(collect());
        DB::shouldReceive('table')->with('migrations')->andReturn($mockBuilder);

        // Mock migrator for pending migrations
        $mockRepository = Mockery::mock();
        $mockRepository->shouldReceive('getRan')->andReturn([]);

        $mockMigrator = Mockery::mock();
        $mockMigrator->shouldReceive('getRepository')->andReturn($mockRepository);
        $mockMigrator->shouldReceive('getMigrationFiles')->andReturn([]);

        app()->instance('migrator', $mockMigrator);

        $this->command->shouldReceive('warn')
            ->with('No migrations found.')
            ->once();

        $result = $this->command->handle();

        expect($result)->toBe(0);
    });

    it('outputs JSON when --json option is provided', function () {
        $this->command->shouldReceive('option')->with('json')->andReturn(true);
        $this->command->shouldReceive('option')->with('limit')->andReturn(20);
        $this->command->shouldReceive('option')->with('reverse')->andReturn(false);

        // Mock migrations data
        $mockBuilder = Mockery::mock();
        $mockBuilder->shouldReceive('orderBy')->andReturnSelf();
        $mockBuilder->shouldReceive('get')->andReturn(collect([
            (object) [
                'id' => 1,
                'migration' => '2024_01_01_000000_create_users_table',
                'batch' => 1,
            ],
        ]));
        DB::shouldReceive('table')->with('migrations')->andReturn($mockBuilder);

        // Mock migrator
        $mockRepository = Mockery::mock();
        $mockRepository->shouldReceive('getRan')->andReturn(['2024_01_01_000000_create_users_table']);

        $mockMigrator = Mockery::mock();
        $mockMigrator->shouldReceive('getRepository')->andReturn($mockRepository);
        $mockMigrator->shouldReceive('getMigrationFiles')->andReturn([]);

        app()->instance('migrator', $mockMigrator);

        $this->command->shouldReceive('line')
            ->with(Mockery::type('string'))
            ->once();

        $result = $this->command->handle();

        expect($result)->toBe(0);
    });

    it('applies limit to migrations', function () {
        $this->command->shouldReceive('option')->with('json')->andReturn(false);
        $this->command->shouldReceive('option')->with('limit')->andReturn(1);
        $this->command->shouldReceive('option')->with('reverse')->andReturn(false);

        // Mock migrations data with multiple entries
        $mockBuilder = Mockery::mock();
        $mockBuilder->shouldReceive('orderBy')->andReturnSelf();
        $mockBuilder->shouldReceive('get')->andReturn(collect([
            (object) [
                'id' => 1,
                'migration' => '2024_01_01_000000_create_users_table',
                'batch' => 1,
            ],
            (object) [
                'id' => 2,
                'migration' => '2024_01_02_000000_create_posts_table',
                'batch' => 1,
            ],
        ]));
        DB::shouldReceive('table')->with('migrations')->andReturn($mockBuilder);

        // Mock migrator
        $mockRepository = Mockery::mock();
        $mockRepository->shouldReceive('getRan')->andReturn([
            '2024_01_01_000000_create_users_table',
            '2024_01_02_000000_create_posts_table',
        ]);

        $mockMigrator = Mockery::mock();
        $mockMigrator->shouldReceive('getRepository')->andReturn($mockRepository);
        $mockMigrator->shouldReceive('getMigrationFiles')->andReturn([]);

        app()->instance('migrator', $mockMigrator);

        $result = $this->command->handle();

        expect($result)->toBe(0);
    });
});

describe('getAppliedMigrations method', function () {
    it('returns applied migrations from database', function () {
        $this->command->shouldReceive('option')->with('reverse')->andReturn(false);

        // Mock DB query
        $mockBuilder = Mockery::mock();
        $mockBuilder->shouldReceive('orderBy')->with('id', 'desc')->andReturnSelf();
        $mockBuilder->shouldReceive('get')->andReturn(collect([
            (object) [
                'id' => 1,
                'migration' => '2024_01_01_000000_create_users_table',
                'batch' => 1,
            ],
        ]));
        DB::shouldReceive('table')->with('migrations')->andReturn($mockBuilder);

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getAppliedMigrations');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        expect($result)->toBeArray()
            ->and($result)->toHaveCount(1)
            ->and($result[0])->toHaveKey('status')
            ->and($result[0]['status'])->toBe('applied');
    });

    it('handles database exceptions gracefully', function () {
        $this->command->shouldReceive('option')->with('reverse')->andReturn(false);

        DB::shouldReceive('table')->andThrow(new \Exception('Database error'));

        $this->command->shouldReceive('error')
            ->with(Mockery::type('string'))
            ->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getAppliedMigrations');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        expect($result)->toBe([]);
    });

    it('orders migrations in reverse when --reverse option is set', function () {
        $this->command->shouldReceive('option')->with('reverse')->andReturn(true);

        $mockBuilder = Mockery::mock();
        $mockBuilder->shouldReceive('orderBy')->with('id', 'asc')->andReturnSelf();
        $mockBuilder->shouldReceive('get')->andReturn(collect());
        DB::shouldReceive('table')->with('migrations')->andReturn($mockBuilder);

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getAppliedMigrations');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        expect($result)->toBeArray();
    });
});

describe('getPendingMigrations method', function () {
    it('returns pending migrations', function () {
        // Mock migrator
        $mockRepository = Mockery::mock();
        $mockRepository->shouldReceive('getRan')->andReturn(['2024_01_01_000000_create_users_table']);

        $mockMigrator = Mockery::mock();
        $mockMigrator->shouldReceive('getRepository')->andReturn($mockRepository);
        $mockMigrator->shouldReceive('getMigrationFiles')->andReturn([
            '2024_01_01_000000_create_users_table' => 'path1',
            '2024_01_02_000000_create_posts_table' => 'path2',
        ]);

        app()->instance('migrator', $mockMigrator);

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getPendingMigrations');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        // Just verify it returns an array with the correct count
        // The exact structure depends on how array_diff and collect work together
        expect($result)->toBeArray()
            ->and($result)->toHaveCount(1);
    });
});

describe('extractTimestampFromName method', function () {
    it('extracts timestamp from migration name', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('extractTimestampFromName');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, '2024_01_15_143022_create_users_table');

        expect($result)->toBeString()
            ->and($result)->toContain('2024-01-15');
    });

    it('returns null for invalid timestamp', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('extractTimestampFromName');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'invalid_migration_name');

        expect($result)->toBeNull();
    });

});

describe('formatMigrationName method', function () {
    it('formats migration name for display', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('formatMigrationName');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, '2024_01_01_000000_create_users_table');

        expect($result)->toBe('Create Users Table');
    });
});

describe('getStatusBadge method', function () {
    it('returns correct badge for applied status', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getStatusBadge');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'applied');

        expect($result)->toContain('Applied');
    });

    it('returns correct badge for pending status', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getStatusBadge');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'pending');

        expect($result)->toContain('Pending');
    });

    it('returns correct badge for failed status', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getStatusBadge');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'failed');

        expect($result)->toContain('Failed');
    });
});

describe('extractVersion method', function () {
    it('returns empty string when file does not exist', function () {
        File::shouldReceive('exists')->andReturn(false);

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('extractVersion');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, '2024_01_01_000000_create_users_table');

        expect($result)->toBe('');
    });

    it('extracts version from migration file', function () {
        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('get')->andReturn('<?php /** @version 1.0.0 */ class CreateUsersTable {}');

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('extractVersion');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, '2024_01_01_000000_create_users_table');

        expect($result)->toBe('1.0.0');
    });

    it('extracts tag from migration file', function () {
        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('get')->andReturn('<?php /** @tag v2.0 */ class CreateUsersTable {}');

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('extractVersion');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, '2024_01_01_000000_create_users_table');

        expect($result)->toBe('v2.0');
    });
});

describe('combineAndSortMigrations method', function () {
    it('combines and sorts migrations in descending order', function () {
        $this->command->shouldReceive('option')->with('reverse')->andReturn(false);

        $applied = [
            ['migration' => '2024_01_01_000000_create_users_table'],
        ];
        $pending = [
            ['migration' => '2024_01_02_000000_create_posts_table'],
        ];

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('combineAndSortMigrations');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, $applied, $pending);

        expect($result)->toHaveCount(2)
            ->and($result[0]['migration'])->toBe('2024_01_02_000000_create_posts_table');
    });

    it('combines and sorts migrations in ascending order when --reverse is set', function () {
        $this->command->shouldReceive('option')->with('reverse')->andReturn(true);

        $applied = [
            ['migration' => '2024_01_02_000000_create_posts_table'],
        ];
        $pending = [
            ['migration' => '2024_01_01_000000_create_users_table'],
        ];

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('combineAndSortMigrations');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, $applied, $pending);

        expect($result)->toHaveCount(2)
            ->and($result[0]['migration'])->toBe('2024_01_01_000000_create_users_table');
    });
});

describe('displayTimeline method', function () {
    it('displays migration timeline table', function () {
        File::shouldReceive('exists')->andReturn(false);

        $migrations = [
            [
                'migration' => '2024_01_01_000000_create_users_table',
                'name' => 'Create Users Table',
                'status' => 'applied',
                'batch' => 1,
                'ran_at' => '2024-01-01 00:00:00',
            ],
        ];

        $this->command->shouldReceive('table')
            ->with(Mockery::type('array'), Mockery::type('array'))
            ->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('displayTimeline');
        $method->setAccessible(true);

        $method->invoke($this->command, $migrations);

        expect(true)->toBeTrue();
    });
});

describe('displaySummary method', function () {
    it('displays summary statistics', function () {
        $applied = [
            ['batch' => 1],
            ['batch' => 1],
            ['batch' => 2],
        ];
        $pending = [
            ['status' => 'pending'],
        ];

        $this->command->shouldReceive('line')
            ->with(Mockery::type('string'))
            ->times(3);

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('displaySummary');
        $method->setAccessible(true);

        $method->invoke($this->command, $applied, $pending);

        expect(true)->toBeTrue();
    });

    it('shows tip when there are pending migrations', function () {
        $applied = [];
        $pending = [['status' => 'pending']];

        $this->command->shouldReceive('comment')
            ->with(Mockery::pattern('/php artisan migrate:plan/'))
            ->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('displaySummary');
        $method->setAccessible(true);

        $method->invoke($this->command, $applied, $pending);

        expect(true)->toBeTrue();
    });
});

describe('outputJson method', function () {
    it('outputs migrations as JSON', function () {
        $migrations = [
            [
                'migration' => '2024_01_01_000000_create_users_table',
                'status' => 'applied',
            ],
            [
                'migration' => '2024_01_02_000000_create_posts_table',
                'status' => 'pending',
            ],
        ];

        $this->command->shouldReceive('line')
            ->with(Mockery::type('string'))
            ->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('outputJson');
        $method->setAccessible(true);

        $method->invoke($this->command, $migrations);

        expect(true)->toBeTrue();
    });
});
