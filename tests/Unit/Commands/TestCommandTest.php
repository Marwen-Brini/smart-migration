<?php

use Flux\Commands\TestCommand;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->command = Mockery::mock(TestCommand::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

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

// Note: verifyTestConnection() is skipped due to complex Config facade mocking requirements
// in test environment. It's tested in integration tests instead.

describe('getTables method', function () {
    it('returns tables for SQLite database', function () {
        Config::shouldReceive('get')->byDefault()->andReturn(null);
        Config::shouldReceive('set')->byDefault()->andReturnNull();
        Config::shouldReceive('offsetGet')->byDefault()->andReturn(null);
        Config::shouldReceive('get')
            ->with('database.connections.testing.driver')
            ->andReturn('sqlite');

        $mockResults = [
            (object) ['name' => 'users'],
            (object) ['name' => 'posts'],
        ];

        DB::shouldReceive('connection')
            ->with('testing')
            ->andReturnSelf();
        DB::shouldReceive('select')
            ->with(Mockery::type('string'))
            ->andReturn($mockResults);

        $reflection = new ReflectionClass($this->command);

        // Set testConnection
        $property = $reflection->getProperty('testConnection');
        $property->setAccessible(true);
        $property->setValue($this->command, 'testing');

        $method = $reflection->getMethod('getTables');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        expect($result)->toBeArray()
            ->and($result)->toContain('users')
            ->and($result)->toContain('posts');
    });

    it('returns tables for MySQL database', function () {
        Config::shouldReceive('get')->byDefault()->andReturn(null);
        Config::shouldReceive('set')->byDefault()->andReturnNull();
        Config::shouldReceive('offsetGet')->byDefault()->andReturn(null);
        Config::shouldReceive('get')
            ->with('database.connections.testing.driver')
            ->andReturn('mysql');

        Config::shouldReceive('get')
            ->with('database.connections.testing.database')
            ->andReturn('test_db');

        $mockResults = [
            (object) ['TABLE_NAME' => 'users'],
            (object) ['TABLE_NAME' => 'posts'],
        ];

        DB::shouldReceive('connection')
            ->with('testing')
            ->andReturnSelf();
        DB::shouldReceive('select')
            ->with(Mockery::type('string'), ['test_db'])
            ->andReturn($mockResults);

        $reflection = new ReflectionClass($this->command);

        $property = $reflection->getProperty('testConnection');
        $property->setAccessible(true);
        $property->setValue($this->command, 'testing');

        $method = $reflection->getMethod('getTables');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        expect($result)->toBeArray()
            ->and($result)->toContain('users')
            ->and($result)->toContain('posts');
    });

    it('returns tables for PostgreSQL database', function () {
        Config::shouldReceive('get')->byDefault()->andReturn(null);
        Config::shouldReceive('set')->byDefault()->andReturnNull();
        Config::shouldReceive('offsetGet')->byDefault()->andReturn(null);
        Config::shouldReceive('get')
            ->with('database.connections.testing.driver')
            ->andReturn('pgsql');

        $mockResults = [
            (object) ['tablename' => 'users'],
            (object) ['tablename' => 'posts'],
        ];

        DB::shouldReceive('connection')
            ->with('testing')
            ->andReturnSelf();
        DB::shouldReceive('select')
            ->with(Mockery::type('string'))
            ->andReturn($mockResults);

        $reflection = new ReflectionClass($this->command);

        $property = $reflection->getProperty('testConnection');
        $property->setAccessible(true);
        $property->setValue($this->command, 'testing');

        $method = $reflection->getMethod('getTables');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        expect($result)->toBeArray()
            ->and($result)->toContain('users')
            ->and($result)->toContain('posts');
    });
});

describe('getIndexes method', function () {
    it('returns empty array on exception', function () {
        Config::shouldReceive('get')->byDefault()->andReturn(null);
        Config::shouldReceive('set')->byDefault()->andReturnNull();
        Config::shouldReceive('offsetGet')->byDefault()->andReturn(null);
        Config::shouldReceive('get')
            ->with('database.connections.testing.driver')
            ->andReturn('sqlite');

        DB::shouldReceive('connection')
            ->andThrow(new \Exception('Database error'));

        $reflection = new ReflectionClass($this->command);

        $property = $reflection->getProperty('testConnection');
        $property->setAccessible(true);
        $property->setValue($this->command, 'testing');

        $method = $reflection->getMethod('getIndexes');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'users');

        expect($result)->toBe([]);
    });

    it('returns indexes for SQLite database', function () {
        Config::shouldReceive('get')->byDefault()->andReturn(null);
        Config::shouldReceive('set')->byDefault()->andReturnNull();
        Config::shouldReceive('offsetGet')->byDefault()->andReturn(null);
        Config::shouldReceive('get')
            ->with('database.connections.testing.driver')
            ->andReturn('sqlite');

        $mockResults = [
            (object) ['name' => 'idx_email'],
            (object) ['name' => 'idx_name'],
        ];

        DB::shouldReceive('connection')
            ->with('testing')
            ->andReturnSelf();
        DB::shouldReceive('select')
            ->with(Mockery::type('string'))
            ->andReturn($mockResults);

        $reflection = new ReflectionClass($this->command);

        $property = $reflection->getProperty('testConnection');
        $property->setAccessible(true);
        $property->setValue($this->command, 'testing');

        $method = $reflection->getMethod('getIndexes');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'users');

        expect($result)->toBeArray()
            ->and($result)->toContain('idx_email');
    });

    it('returns indexes for MySQL database', function () {
        Config::shouldReceive('get')->byDefault()->andReturn(null);
        Config::shouldReceive('set')->byDefault()->andReturnNull();
        Config::shouldReceive('offsetGet')->byDefault()->andReturn(null);
        Config::shouldReceive('get')
            ->with('database.connections.testing.driver')
            ->andReturn('mysql');

        $mockResults = [
            (object) ['Key_name' => 'PRIMARY'],
            (object) ['Key_name' => 'idx_email'],
            (object) ['Key_name' => 'idx_email'], // Duplicate to test unique filtering
        ];

        DB::shouldReceive('connection')
            ->with('testing')
            ->andReturnSelf();
        DB::shouldReceive('select')
            ->with(Mockery::type('string'))
            ->andReturn($mockResults);

        $reflection = new ReflectionClass($this->command);

        $property = $reflection->getProperty('testConnection');
        $property->setAccessible(true);
        $property->setValue($this->command, 'testing');

        $method = $reflection->getMethod('getIndexes');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'users');

        expect($result)->toBeArray()
            ->and($result)->toContain('PRIMARY')
            ->and($result)->toContain('idx_email');
    });

    it('returns indexes for PostgreSQL database', function () {
        Config::shouldReceive('get')->byDefault()->andReturn(null);
        Config::shouldReceive('set')->byDefault()->andReturnNull();
        Config::shouldReceive('offsetGet')->byDefault()->andReturn(null);
        Config::shouldReceive('get')
            ->with('database.connections.testing.driver')
            ->andReturn('pgsql');

        $mockResults = [
            (object) ['indexname' => 'users_pkey'],
            (object) ['indexname' => 'users_email_idx'],
        ];

        DB::shouldReceive('connection')
            ->with('testing')
            ->andReturnSelf();
        DB::shouldReceive('select')
            ->with(Mockery::type('string'), Mockery::type('array'))
            ->andReturn($mockResults);

        $reflection = new ReflectionClass($this->command);

        $property = $reflection->getProperty('testConnection');
        $property->setAccessible(true);
        $property->setValue($this->command, 'testing');

        $method = $reflection->getMethod('getIndexes');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'users');

        expect($result)->toBeArray()
            ->and($result)->toContain('users_pkey')
            ->and($result)->toContain('users_email_idx');
    });

    it('returns empty array for unknown driver', function () {
        Config::shouldReceive('get')->byDefault()->andReturn(null);
        Config::shouldReceive('set')->byDefault()->andReturnNull();
        Config::shouldReceive('offsetGet')->byDefault()->andReturn(null);
        Config::shouldReceive('get')
            ->with('database.connections.testing.driver')
            ->andReturn('unknown');

        $reflection = new ReflectionClass($this->command);

        $property = $reflection->getProperty('testConnection');
        $property->setAccessible(true);
        $property->setValue($this->command, 'testing');

        $method = $reflection->getMethod('getIndexes');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'users');

        expect($result)->toBe([]);
    });
});

describe('detectChanges method', function () {
    it('detects tables added', function () {
        $pre = [
            'tables' => [
                'users' => ['columns' => ['id', 'name']],
            ],
            'row_counts' => [
                'users' => 0,
            ],
        ];

        $post = [
            'tables' => [
                'users' => ['columns' => ['id', 'name']],
                'posts' => ['columns' => ['id', 'title']],
            ],
            'row_counts' => [
                'users' => 0,
                'posts' => 0,
            ],
        ];

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('detectChanges');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, $pre, $post);

        expect($result['tables_added'])->toContain('posts');
    });

    it('detects columns added', function () {
        $pre = [
            'tables' => [
                'users' => ['columns' => ['id', 'name']],
            ],
            'row_counts' => [
                'users' => 0,
            ],
        ];

        $post = [
            'tables' => [
                'users' => ['columns' => ['id', 'name', 'email']],
            ],
            'row_counts' => [
                'users' => 0,
            ],
        ];

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('detectChanges');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, $pre, $post);

        expect($result['columns_added'])->toHaveKey('users')
            ->and($result['columns_added']['users'])->toContain('email');
    });

    it('detects row count changes', function () {
        $pre = [
            'tables' => [
                'users' => ['columns' => ['id', 'name']],
            ],
            'row_counts' => [
                'users' => 0,
            ],
        ];

        $post = [
            'tables' => [
                'users' => ['columns' => ['id', 'name']],
            ],
            'row_counts' => [
                'users' => 10,
            ],
        ];

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('detectChanges');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, $pre, $post);

        expect($result['row_count_changes'])->toHaveKey('users')
            ->and($result['row_count_changes']['users']['before'])->toBe(0)
            ->and($result['row_count_changes']['users']['after'])->toBe(10);
    });

    it('detects tables removed', function () {
        $pre = [
            'tables' => [
                'users' => ['columns' => ['id', 'name']],
                'posts' => ['columns' => ['id', 'title']],
            ],
            'row_counts' => [
                'users' => 0,
                'posts' => 0,
            ],
        ];

        $post = [
            'tables' => [
                'users' => ['columns' => ['id', 'name']],
            ],
            'row_counts' => [
                'users' => 0,
            ],
        ];

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('detectChanges');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, $pre, $post);

        expect($result['tables_removed'])->toContain('posts');
    });

    it('detects columns removed', function () {
        $pre = [
            'tables' => [
                'users' => ['columns' => ['id', 'name', 'email']],
            ],
            'row_counts' => [
                'users' => 0,
            ],
        ];

        $post = [
            'tables' => [
                'users' => ['columns' => ['id', 'name']],
            ],
            'row_counts' => [
                'users' => 0,
            ],
        ];

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('detectChanges');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, $pre, $post);

        expect($result['columns_removed'])->toHaveKey('users')
            ->and($result['columns_removed']['users'])->toContain('email');
    });
});

describe('displayChanges method', function () {
    it('displays tables added', function () {
        $changes = [
            'tables_added' => ['posts'],
            'tables_removed' => [],
            'columns_added' => [],
            'columns_removed' => [],
        ];

        $this->command->shouldReceive('info')
            ->with(Mockery::pattern('/Tables added.*posts/'))
            ->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('displayChanges');
        $method->setAccessible(true);

        $method->invoke($this->command, $changes);

        expect(true)->toBeTrue();
    });

    it('displays columns added', function () {
        $changes = [
            'tables_added' => [],
            'tables_removed' => [],
            'columns_added' => [
                'users' => ['email', 'phone'],
            ],
            'columns_removed' => [],
        ];

        $this->command->shouldReceive('info')
            ->with(Mockery::pattern('/Columns added to users/'))
            ->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('displayChanges');
        $method->setAccessible(true);

        $method->invoke($this->command, $changes);

        expect(true)->toBeTrue();
    });

    it('displays tables removed', function () {
        $changes = [
            'tables_added' => [],
            'tables_removed' => ['posts'],
            'columns_added' => [],
            'columns_removed' => [],
        ];

        $this->command->shouldReceive('warn')
            ->with(Mockery::pattern('/Tables removed.*posts/'))
            ->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('displayChanges');
        $method->setAccessible(true);

        $method->invoke($this->command, $changes);

        expect(true)->toBeTrue();
    });

    it('displays columns removed', function () {
        $changes = [
            'tables_added' => [],
            'tables_removed' => [],
            'columns_added' => [],
            'columns_removed' => [
                'users' => ['email', 'phone'],
            ],
        ];

        $this->command->shouldReceive('warn')
            ->with(Mockery::pattern('/Columns removed from users/'))
            ->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('displayChanges');
        $method->setAccessible(true);

        $method->invoke($this->command, $changes);

        expect(true)->toBeTrue();
    });
});

describe('displayResults method', function () {
    it('displays success message when tests pass', function () {
        $this->command->shouldReceive('info')
            ->with(Mockery::pattern('/All tests passed/'))
            ->once();

        $reflection = new ReflectionClass($this->command);

        $property = $reflection->getProperty('testPassed');
        $property->setAccessible(true);
        $property->setValue($this->command, true);

        $method = $reflection->getMethod('displayResults');
        $method->setAccessible(true);

        $method->invoke($this->command);

        expect(true)->toBeTrue();
    });

    it('displays failure message when tests fail', function () {
        $this->command->shouldReceive('error')
            ->with(Mockery::pattern('/Tests failed/'))
            ->once();

        $reflection = new ReflectionClass($this->command);

        $property = $reflection->getProperty('testPassed');
        $property->setAccessible(true);
        $property->setValue($this->command, false);

        $method = $reflection->getMethod('displayResults');
        $method->setAccessible(true);

        $method->invoke($this->command);

        expect(true)->toBeTrue();
    });
});

describe('displayHeader method', function () {
    it('displays test header', function () {
        $this->command->shouldReceive('info')
            ->with(Mockery::type('string'))
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

describe('runIntegrityChecks method', function () {
    it('runs integrity checks successfully for SQLite', function () {
        Config::shouldReceive('get')->byDefault()->andReturn(null);
        Config::shouldReceive('set')->byDefault()->andReturnNull();
        Config::shouldReceive('offsetGet')->byDefault()->andReturn(null);
        Config::shouldReceive('get')
            ->with('database.connections.testing.driver')
            ->andReturn('sqlite');

        $mockPdo = Mockery::mock(\PDO::class);

        DB::shouldReceive('connection')
            ->with('testing')
            ->andReturnSelf();
        DB::shouldReceive('getPdo')
            ->andReturn($mockPdo);

        $reflection = new ReflectionClass($this->command);

        $property = $reflection->getProperty('testConnection');
        $property->setAccessible(true);
        $property->setValue($this->command, 'testing');

        $method = $reflection->getMethod('runIntegrityChecks');
        $method->setAccessible(true);

        // Should not throw exception
        $method->invoke($this->command);

        expect(true)->toBeTrue();
    });

    it('runs integrity checks successfully for MySQL', function () {
        Config::shouldReceive('get')->byDefault()->andReturn(null);
        Config::shouldReceive('set')->byDefault()->andReturnNull();
        Config::shouldReceive('offsetGet')->byDefault()->andReturn(null);
        Config::shouldReceive('get')
            ->with('database.connections.testing.driver')
            ->andReturn('mysql');

        $mockPdo = Mockery::mock(\PDO::class);

        DB::shouldReceive('connection')
            ->with('testing')
            ->andReturnSelf();
        DB::shouldReceive('getPdo')
            ->andReturn($mockPdo);

        $this->command->shouldReceive('info')
            ->with(Mockery::pattern('/Integrity checks passed/'))
            ->once();

        $reflection = new ReflectionClass($this->command);

        $property = $reflection->getProperty('testConnection');
        $property->setAccessible(true);
        $property->setValue($this->command, 'testing');

        $method = $reflection->getMethod('runIntegrityChecks');
        $method->setAccessible(true);

        // Should not throw exception
        $method->invoke($this->command);

        expect(true)->toBeTrue();
    });

    it('throws exception on database connection error', function () {
        Config::shouldReceive('get')->byDefault()->andReturn(null);
        Config::shouldReceive('set')->byDefault()->andReturnNull();
        Config::shouldReceive('offsetGet')->byDefault()->andReturn(null);
        Config::shouldReceive('get')
            ->with('database.connections.testing.driver')
            ->andReturn('mysql');

        DB::shouldReceive('connection')
            ->with('testing')
            ->andReturnSelf();
        DB::shouldReceive('getPdo')
            ->andThrow(new \Exception('Connection failed'));

        $reflection = new ReflectionClass($this->command);

        $property = $reflection->getProperty('testConnection');
        $property->setAccessible(true);
        $property->setValue($this->command, 'testing');

        $method = $reflection->getMethod('runIntegrityChecks');
        $method->setAccessible(true);

        expect(fn() => $method->invoke($this->command))
            ->toThrow(\RuntimeException::class, 'Integrity check failed');
    });
});

describe('cleanupTestDatabase method', function () {
    it('handles in-memory SQLite cleanup', function () {
        Config::shouldReceive('get')->byDefault()->andReturn(null);
        Config::shouldReceive('set')->byDefault()->andReturnNull();
        Config::shouldReceive('offsetGet')->byDefault()->andReturn(null);
        Config::shouldReceive('get')
            ->with('database.connections.testing.driver')
            ->andReturn('sqlite');
        Config::shouldReceive('get')
            ->with('database.connections.testing.database')
            ->andReturn(':memory:');

        $this->command->shouldReceive('info')
            ->with(Mockery::pattern('/in-memory/'))
            ->once();

        $reflection = new ReflectionClass($this->command);

        $property = $reflection->getProperty('testConnection');
        $property->setAccessible(true);
        $property->setValue($this->command, 'testing');

        $method = $reflection->getMethod('cleanupTestDatabase');
        $method->setAccessible(true);

        $method->invoke($this->command);

        expect(true)->toBeTrue();
    });

});

// Note: Testing cleanup error handling is skipped because Schema::connection()
// triggers actual database connection attempts in the test environment which are
// difficult to mock. The error handling is tested in integration tests instead.

// Note: getMigrationsToTest(), handle(), setupTestDatabase(), testMigration(), testRollback(),
// verifyTestConnection(), captureState(), seedTestDatabase() methods are skipped due to
// extensive Artisan facade, Schema facade, and migrator usage which trigger database setup
// in the test environment. These methods are tested in integration tests instead.
