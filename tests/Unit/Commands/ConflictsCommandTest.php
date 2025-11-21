<?php

use Flux\Commands\ConflictsCommand;
use Flux\Analyzers\MigrationAnalyzer;

beforeEach(function () {
    $this->mockAnalyzer = Mockery::mock(MigrationAnalyzer::class);

    $this->command = Mockery::mock(ConflictsCommand::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    // Inject the mock analyzer
    $reflection = new ReflectionClass($this->command);
    $property = $reflection->getProperty('analyzer');
    $property->setAccessible(true);
    $property->setValue($this->command, $this->mockAnalyzer);

    // Mock output methods
    $this->command->shouldReceive('info')->andReturnNull()->byDefault();
    $this->command->shouldReceive('comment')->andReturnNull()->byDefault();
    $this->command->shouldReceive('warn')->andReturnNull()->byDefault();
    $this->command->shouldReceive('error')->andReturnNull()->byDefault();
    $this->command->shouldReceive('line')->andReturnNull()->byDefault();
    $this->command->shouldReceive('newLine')->andReturnNull()->byDefault();
});

afterEach(function () {
    Mockery::close();
});

describe('extractTimestamp method', function () {
    it('extracts timestamp from valid migration name', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('extractTimestamp');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, '2024_01_15_143022_create_users_table');

        expect($result)->toBe(20240115143022);
    });

    it('returns 0 for invalid migration name', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('extractTimestamp');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'invalid_migration_name');

        expect($result)->toBe(0);
    });
});

describe('extractOperations method', function () {
    it('extracts create table operations', function () {
        \Illuminate\Support\Facades\File::shouldReceive('exists')
            ->with('/path/to/migration.php')
            ->andReturn(true);

        \Illuminate\Support\Facades\File::shouldReceive('get')
            ->with('/path/to/migration.php')
            ->andReturn('<?php Schema::create(\'users\', function (Blueprint $table) {});');

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('extractOperations');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, '/path/to/migration.php');

        expect($result)->toBeArray()
            ->and(count($result))->toBeGreaterThan(0)
            ->and($result[0]['type'])->toBe('create')
            ->and($result[0]['table'])->toBe('users');
    });

    it('extracts table modification operations', function () {
        \Illuminate\Support\Facades\File::shouldReceive('exists')
            ->with('/path/to/migration.php')
            ->andReturn(true);

        \Illuminate\Support\Facades\File::shouldReceive('get')
            ->with('/path/to/migration.php')
            ->andReturn('<?php Schema::table(\'users\', function (Blueprint $table) {});');

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('extractOperations');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, '/path/to/migration.php');

        expect($result)->toBeArray()
            ->and($result[0]['type'])->toBe('table')
            ->and($result[0]['table'])->toBe('users');
    });

    it('extracts drop table operations', function () {
        \Illuminate\Support\Facades\File::shouldReceive('exists')
            ->with('/path/to/migration.php')
            ->andReturn(true);

        \Illuminate\Support\Facades\File::shouldReceive('get')
            ->with('/path/to/migration.php')
            ->andReturn('<?php Schema::dropIfExists(\'users\');');

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('extractOperations');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, '/path/to/migration.php');

        expect($result)->toBeArray()
            ->and($result[0]['type'])->toBe('drop')
            ->and($result[0]['table'])->toBe('users');
    });

    it('extracts column operations', function () {
        \Illuminate\Support\Facades\File::shouldReceive('exists')
            ->with('/path/to/migration.php')
            ->andReturn(true);

        \Illuminate\Support\Facades\File::shouldReceive('get')
            ->with('/path/to/migration.php')
            ->andReturn('<?php $table->string(\'email\'); $table->dropColumn(\'old_column\');');

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('extractOperations');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, '/path/to/migration.php');

        expect($result)->toBeArray()
            ->and(count($result))->toBe(2);
    });

    it('returns empty array when file does not exist', function () {
        \Illuminate\Support\Facades\File::shouldReceive('exists')
            ->with('/path/to/nonexistent.php')
            ->andReturn(false);

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('extractOperations');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, '/path/to/nonexistent.php');

        expect($result)->toBe([]);
    });
});

describe('analyzeConflicts method', function () {
    it('analyzes migrations for conflicts', function () {
        $this->command->shouldReceive('option')->with('verbose')->andReturn(false);

        $migrations = collect([
            [
                'name' => '2024_01_01_000000_create_users_table',
                'operations' => [
                    ['type' => 'create', 'table' => 'users'],
                ],
                'timestamp' => 20240101000000,
            ],
            [
                'name' => '2024_01_02_000000_create_users_table_again',
                'operations' => [
                    ['type' => 'create', 'table' => 'users'],
                ],
                'timestamp' => 20240102000000,
            ],
        ]);

        $reflection = new ReflectionClass($this->command);

        $conflictsProperty = $reflection->getProperty('conflicts');
        $conflictsProperty->setAccessible(true);
        $conflictsProperty->setValue($this->command, []);

        $method = $reflection->getMethod('analyzeConflicts');
        $method->setAccessible(true);

        $method->invoke($this->command, $migrations);

        $conflicts = $conflictsProperty->getValue($this->command);

        expect($conflicts)->toHaveCount(1)
            ->and($conflicts[0]['type'])->toBe('duplicate_create');
    });

    it('skips operations without table', function () {
        $this->command->shouldReceive('option')->with('verbose')->andReturn(false);

        $migrations = collect([
            [
                'name' => '2024_01_01_000000_test_migration',
                'operations' => [
                    ['type' => 'add_column', 'column' => 'email'], // No table key
                ],
                'timestamp' => 20240101000000,
            ],
        ]);

        $reflection = new ReflectionClass($this->command);

        $conflictsProperty = $reflection->getProperty('conflicts');
        $conflictsProperty->setAccessible(true);
        $conflictsProperty->setValue($this->command, []);

        $method = $reflection->getMethod('analyzeConflicts');
        $method->setAccessible(true);

        $method->invoke($this->command, $migrations);

        $conflicts = $conflictsProperty->getValue($this->command);

        expect($conflicts)->toHaveCount(0);
    });

    it('displays verbose output when option is set', function () {
        $this->command->shouldReceive('option')->with('verbose')->andReturn(true);
        $this->command->shouldReceive('comment')->once();
        $this->command->shouldReceive('line')->once();

        $migrations = collect([
            [
                'name' => '2024_01_01_000000_create_users_table',
                'operations' => [
                    ['type' => 'create', 'table' => 'users'],
                ],
                'timestamp' => 20240101000000,
            ],
        ]);

        $reflection = new ReflectionClass($this->command);
        $conflictsProperty = $reflection->getProperty('conflicts');
        $conflictsProperty->setAccessible(true);
        $conflictsProperty->setValue($this->command, []);

        $method = $reflection->getMethod('analyzeConflicts');
        $method->setAccessible(true);

        $method->invoke($this->command, $migrations);

        expect(true)->toBeTrue();
    });
});

describe('detectTableConflicts method', function () {
    it('detects duplicate create conflicts', function () {
        $operations = [
            [
                'migration' => 'first_create',
                'timestamp' => 20240101000000,
                'operation' => ['type' => 'create', 'table' => 'users'],
            ],
            [
                'migration' => 'second_create',
                'timestamp' => 20240102000000,
                'operation' => ['type' => 'create', 'table' => 'users'],
            ],
        ];

        $reflection = new ReflectionClass($this->command);

        $conflictsProperty = $reflection->getProperty('conflicts');
        $conflictsProperty->setAccessible(true);
        $conflictsProperty->setValue($this->command, []);

        $method = $reflection->getMethod('detectTableConflicts');
        $method->setAccessible(true);

        $method->invoke($this->command, 'users', $operations);

        $conflicts = $conflictsProperty->getValue($this->command);

        expect($conflicts)->toHaveCount(1)
            ->and($conflicts[0]['type'])->toBe('duplicate_create');
    });

    it('detects modify before create conflicts', function () {
        $operations = [
            [
                'migration' => 'modify_migration',
                'timestamp' => 20240101000000,
                'operation' => ['type' => 'table', 'table' => 'users'],
            ],
            [
                'migration' => 'create_migration',
                'timestamp' => 20240102000000,
                'operation' => ['type' => 'create', 'table' => 'users'],
            ],
        ];

        $reflection = new ReflectionClass($this->command);

        $conflictsProperty = $reflection->getProperty('conflicts');
        $conflictsProperty->setAccessible(true);
        $conflictsProperty->setValue($this->command, []);

        $method = $reflection->getMethod('detectTableConflicts');
        $method->setAccessible(true);

        $method->invoke($this->command, 'users', $operations);

        $conflicts = $conflictsProperty->getValue($this->command);

        expect($conflicts)->not->toBeEmpty()
            ->and($conflicts[0]['type'])->toBe('modify_before_create');
    });

    it('detects create after drop conflicts', function () {
        $operations = [
            [
                'migration' => 'drop_migration',
                'timestamp' => 20240101000000,
                'operation' => ['type' => 'drop', 'table' => 'users'],
            ],
            [
                'migration' => 'create_migration',
                'timestamp' => 20240102000000,
                'operation' => ['type' => 'create', 'table' => 'users'],
            ],
        ];

        $reflection = new ReflectionClass($this->command);

        $conflictsProperty = $reflection->getProperty('conflicts');
        $conflictsProperty->setAccessible(true);
        $conflictsProperty->setValue($this->command, []);

        $method = $reflection->getMethod('detectTableConflicts');
        $method->setAccessible(true);

        $method->invoke($this->command, 'users', $operations);

        $conflicts = $conflictsProperty->getValue($this->command);

        expect($conflicts)->not->toBeEmpty()
            ->and($conflicts[0]['type'])->toBe('create_after_drop');
    });

    it('detects concurrent modifications conflicts', function () {
        $operations = [
            [
                'migration' => 'first_modify',
                'timestamp' => 20240101000000,
                'operation' => ['type' => 'table', 'table' => 'users'],
            ],
            [
                'migration' => 'second_modify',
                'timestamp' => 20240101000010, // Within 100 timestamp units
                'operation' => ['type' => 'table', 'table' => 'users'],
            ],
        ];

        $reflection = new ReflectionClass($this->command);

        $conflictsProperty = $reflection->getProperty('conflicts');
        $conflictsProperty->setAccessible(true);
        $conflictsProperty->setValue($this->command, []);

        // First set up create to avoid modify_before_create
        $operationsWithCreate = array_merge([
            [
                'migration' => 'create_migration',
                'timestamp' => 20240100000000,
                'operation' => ['type' => 'create', 'table' => 'users'],
            ],
        ], $operations);

        $method = $reflection->getMethod('detectTableConflicts');
        $method->setAccessible(true);

        $method->invoke($this->command, 'users', $operationsWithCreate);

        $conflicts = $conflictsProperty->getValue($this->command);

        $concurrentConflict = array_filter($conflicts, fn($c) => $c['type'] === 'concurrent_modifications');
        expect($concurrentConflict)->not->toBeEmpty();
    });
});

describe('getConflictTitle method', function () {
    it('returns correct title for duplicate_create', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getConflictTitle');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'duplicate_create');

        expect($result)->toBe('Duplicate Table Creation');
    });

    it('returns correct title for modify_before_create', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getConflictTitle');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'modify_before_create');

        expect($result)->toBe('Modifying Table Before Creation');
    });

    it('returns correct title for create_after_drop', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getConflictTitle');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'create_after_drop');

        expect($result)->toBe('Creating Table After Drop');
    });

    it('returns correct title for concurrent_modifications', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getConflictTitle');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'concurrent_modifications');

        expect($result)->toBe('Concurrent Table Modifications');
    });

    it('returns unknown conflict for invalid type', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getConflictTitle');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'unknown_type');

        expect($result)->toBe('Unknown Conflict');
    });
});

describe('getConflictImpact method', function () {
    it('returns correct impact for duplicate_create', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getConflictImpact');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'duplicate_create');

        expect($result)->toContain('fail');
    });

    it('returns correct impact for modify_before_create', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getConflictImpact');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'modify_before_create');

        expect($result)->toContain('does not exist');
    });

    it('returns correct impact for create_after_drop', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getConflictImpact');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'create_after_drop');

        expect($result)->toContain('data loss');
    });

    it('returns correct impact for concurrent_modifications', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getConflictImpact');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'concurrent_modifications');

        expect($result)->toContain('interfere');
    });

    it('returns unknown impact for unknown type', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getConflictImpact');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'unknown');

        expect($result)->toBe('Unknown impact');
    });
});

describe('getConflictRecommendation method', function () {
    it('returns correct recommendation for duplicate_create', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getConflictRecommendation');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'duplicate_create');

        expect($result)->toContain('dropIfExists');
    });

    it('returns correct recommendation for modify_before_create', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getConflictRecommendation');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'modify_before_create');

        expect($result)->toContain('Reorder');
    });

    it('returns correct recommendation for create_after_drop', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getConflictRecommendation');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'create_after_drop');

        expect($result)->toContain('Combine');
    });

    it('returns correct recommendation for concurrent_modifications', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getConflictRecommendation');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'concurrent_modifications');

        expect($result)->toContain('Merge');
    });

    it('returns default recommendation for unknown type', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getConflictRecommendation');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'unknown');

        expect($result)->toBe('Review migration order');
    });
});

describe('addConflict method', function () {
    it('adds conflict to conflicts array', function () {
        $reflection = new ReflectionClass($this->command);

        $conflictsProperty = $reflection->getProperty('conflicts');
        $conflictsProperty->setAccessible(true);
        $conflictsProperty->setValue($this->command, []);

        $method = $reflection->getMethod('addConflict');
        $method->setAccessible(true);

        $operation = [
            'migration' => 'test_migration',
            'operation' => ['type' => 'create', 'table' => 'users'],
        ];

        $method->invoke($this->command, 'duplicate_create', 'users', $operation, []);

        $conflicts = $conflictsProperty->getValue($this->command);

        expect($conflicts)->toHaveCount(1)
            ->and($conflicts[0]['type'])->toBe('duplicate_create')
            ->and($conflicts[0]['table'])->toBe('users');
    });
});

describe('displayNoConflicts method', function () {
    it('displays success message', function () {
        $this->command->shouldReceive('info')
            ->with(Mockery::pattern('/No migration conflicts/'))
            ->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('displayNoConflicts');
        $method->setAccessible(true);

        $method->invoke($this->command);

        expect(true)->toBeTrue();
    });
});

describe('displayConflicts method', function () {
    it('displays conflicts count', function () {
        $reflection = new ReflectionClass($this->command);

        $conflictsProperty = $reflection->getProperty('conflicts');
        $conflictsProperty->setAccessible(true);
        $conflictsProperty->setValue($this->command, [
            [
                'type' => 'duplicate_create',
                'table' => 'users',
                'migration' => 'test_migration',
                'operation' => ['type' => 'create', 'table' => 'users'],
                'related_operations' => [],
            ],
        ]);

        $this->command->shouldReceive('warn')
            ->with(Mockery::pattern('/Detected 1/'))
            ->once();

        $method = $reflection->getMethod('displayConflicts');
        $method->setAccessible(true);

        $method->invoke($this->command);

        expect(true)->toBeTrue();
    });
});

describe('displayConflict method', function () {
    it('displays conflict details', function () {
        $conflict = [
            'type' => 'duplicate_create',
            'table' => 'users',
            'migration' => 'test_migration',
            'operation' => ['type' => 'create', 'table' => 'users'],
            'related_operations' => [
                [
                    'migration' => 'other_migration',
                    'operation' => ['type' => 'create'],
                ],
            ],
        ];

        $this->command->shouldReceive('comment')
            ->with(Mockery::type('string'))
            ->atLeast()->once();

        $this->command->shouldReceive('line')
            ->with(Mockery::type('string'))
            ->atLeast()->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('displayConflict');
        $method->setAccessible(true);

        $method->invoke($this->command, 1, $conflict);

        expect(true)->toBeTrue();
    });
});

describe('displayResolutionOptions method', function () {
    it('displays resolution options', function () {
        $this->command->shouldReceive('comment')
            ->with(Mockery::pattern('/Resolution Options/'))
            ->once();

        $this->command->shouldReceive('line')
            ->with(Mockery::type('string'))
            ->atLeast()->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('displayResolutionOptions');
        $method->setAccessible(true);

        $method->invoke($this->command);

        expect(true)->toBeTrue();
    });
});

describe('attemptAutoResolve method', function () {
    it('displays warning when no conflicts resolved', function () {
        $reflection = new ReflectionClass($this->command);

        $conflictsProperty = $reflection->getProperty('conflicts');
        $conflictsProperty->setAccessible(true);
        $conflictsProperty->setValue($this->command, [
            [
                'type' => 'duplicate_create',
                'table' => 'users',
            ],
        ]);

        $this->command->shouldReceive('info')
            ->with(Mockery::pattern('/Attempting/'))
            ->once();

        $this->command->shouldReceive('warn')
            ->with(Mockery::pattern('/Could not automatically/'))
            ->once();

        $method = $reflection->getMethod('attemptAutoResolve');
        $method->setAccessible(true);

        $method->invoke($this->command);

        expect(true)->toBeTrue();
    });
});

describe('resolveConflict method', function () {
    it('returns false for unresolvable conflicts', function () {
        $conflict = [
            'type' => 'duplicate_create',
            'table' => 'users',
        ];

        $this->command->shouldReceive('comment')
            ->with(Mockery::type('string'))
            ->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('resolveConflict');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, $conflict);

        expect($result)->toBeFalse();
    });
});

describe('outputJson method', function () {
    it('outputs JSON with conflicts', function () {
        $reflection = new ReflectionClass($this->command);

        $conflictsProperty = $reflection->getProperty('conflicts');
        $conflictsProperty->setAccessible(true);
        $conflictsProperty->setValue($this->command, [
            ['type' => 'duplicate_create', 'table' => 'users'],
        ]);

        $this->command->shouldReceive('line')
            ->with(Mockery::type('string'))
            ->once();

        $method = $reflection->getMethod('outputJson');
        $method->setAccessible(true);

        $method->invoke($this->command);

        expect(true)->toBeTrue();
    });
});

describe('displayHeader method', function () {
    it('displays command header', function () {
        $this->command->shouldReceive('info')
            ->with(Mockery::pattern('/Migration Conflict Detection/'))
            ->once();

        $this->command->shouldReceive('comment')
            ->with(Mockery::type('string'))
            ->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('displayHeader');
        $method->setAccessible(true);

        $method->invoke($this->command);

        expect(true)->toBeTrue();
    });
});

// Note: getMigrations(), extractOperations(), analyzeConflicts(), detectTableConflicts(), and handle()
// are skipped due to extensive File facade and migrator usage which require complex mocking.
// These are tested in integration tests instead.
