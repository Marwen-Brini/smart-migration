<?php

use Flux\Database\Adapters\SQLiteAdapter;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->adapter = new SQLiteAdapter;

    // Mock DB facade for tests that need it
    DB::partialMock();
});

afterEach(function () {
    \Mockery::close();
});

describe('getDriverName method', function () {
    it('returns sqlite driver name', function () {
        expect($this->adapter->getDriverName())->toBe('sqlite');
    });
});

describe('getAllTables method', function () {
    it('returns list of all tables from sqlite_master', function () {
        $mockTables = [
            (object) ['name' => 'users'],
            (object) ['name' => 'posts'],
            (object) ['name' => 'comments'],
        ];

        DB::shouldReceive('select')
            ->once()
            ->with("
            SELECT name FROM sqlite_master
            WHERE type='table'
                AND name NOT LIKE 'sqlite_%'
            ORDER BY name
        ")
            ->andReturn($mockTables);

        $result = $this->adapter->getAllTables();

        expect($result)->toBe(['users', 'posts', 'comments']);
    });

    it('returns empty array when no tables exist', function () {
        DB::shouldReceive('select')
            ->once()
            ->andReturn([]);

        $result = $this->adapter->getAllTables();

        expect($result)->toBe([]);
    });
});

describe('getTableColumns method', function () {
    it('returns formatted column information', function () {
        $mockColumns = [
            (object) [
                'name' => 'id',
                'type' => 'INTEGER',
                'notnull' => 1,
                'dflt_value' => null,
                'pk' => 1,
            ],
            (object) [
                'name' => 'name',
                'type' => 'TEXT',
                'notnull' => 0,
                'dflt_value' => null,
                'pk' => 0,
            ],
            (object) [
                'name' => 'email',
                'type' => 'TEXT',
                'notnull' => 1,
                'dflt_value' => "'default@example.com'",
                'pk' => 0,
            ],
        ];

        DB::shouldReceive('select')
            ->once()
            ->with('PRAGMA table_info("users")')
            ->andReturn($mockColumns);

        $result = $this->adapter->getTableColumns('users');

        expect($result)->toHaveCount(3);
        expect($result[0])->toEqual([
            'name' => 'id',
            'type' => 'INTEGER',
            'nullable' => false,
            'default' => null,
            'key' => 'PRI',
            'extra' => '',
        ]);
        expect($result[1])->toEqual([
            'name' => 'name',
            'type' => 'TEXT',
            'nullable' => true,
            'default' => null,
            'key' => '',
            'extra' => '',
        ]);
        expect($result[2])->toEqual([
            'name' => 'email',
            'type' => 'TEXT',
            'nullable' => false,
            'default' => "'default@example.com'",
            'key' => '',
            'extra' => '',
        ]);
    });
});

describe('getTableIndexes method', function () {
    it('returns formatted index information', function () {
        $mockIndexes = [
            (object) [
                'name' => 'sqlite_autoindex_users_1',
                'unique' => 1,
            ],
            (object) [
                'name' => 'users_email_unique',
                'unique' => 1,
            ],
        ];

        $mockPrimaryIndexInfo = [
            (object) ['name' => 'id'],
        ];

        $mockEmailIndexInfo = [
            (object) ['name' => 'email'],
        ];

        DB::shouldReceive('select')
            ->once()
            ->with('PRAGMA index_list("users")')
            ->andReturn($mockIndexes);

        DB::shouldReceive('select')
            ->once()
            ->with('PRAGMA index_info("sqlite_autoindex_users_1")')
            ->andReturn($mockPrimaryIndexInfo);

        DB::shouldReceive('select')
            ->once()
            ->with('PRAGMA index_info("users_email_unique")')
            ->andReturn($mockEmailIndexInfo);

        $result = $this->adapter->getTableIndexes('users');

        expect($result)->toHaveCount(2);
        expect($result[0])->toEqual([
            'name' => 'sqlite_autoindex_users_1',
            'columns' => ['id'],
            'unique' => true,
            'primary' => true,
            'type' => 'BTREE',
        ]);
        expect($result[1])->toEqual([
            'name' => 'users_email_unique',
            'columns' => ['email'],
            'unique' => true,
            'primary' => false,
            'type' => 'BTREE',
        ]);
    });

    it('handles composite indexes', function () {
        $mockIndexes = [
            (object) [
                'name' => 'users_name_email_idx',
                'unique' => 0,
            ],
        ];

        $mockIndexInfo = [
            (object) ['name' => 'name'],
            (object) ['name' => 'email'],
        ];

        DB::shouldReceive('select')
            ->once()
            ->with('PRAGMA index_list("users")')
            ->andReturn($mockIndexes);

        DB::shouldReceive('select')
            ->once()
            ->with('PRAGMA index_info("users_name_email_idx")')
            ->andReturn($mockIndexInfo);

        $result = $this->adapter->getTableIndexes('users');

        expect($result)->toHaveCount(1);
        expect($result[0]['columns'])->toBe(['name', 'email']);
        expect($result[0]['unique'])->toBe(false);
    });

    it('returns empty array when no indexes exist', function () {
        DB::shouldReceive('select')
            ->once()
            ->andReturn([]);

        $result = $this->adapter->getTableIndexes('users');

        expect($result)->toBe([]);
    });
});

describe('getTableForeignKeys method', function () {
    it('returns formatted foreign key information', function () {
        $mockForeignKeys = [
            (object) [
                'id' => 0,
                'from' => 'user_id',
                'table' => 'users',
                'to' => 'id',
            ],
        ];

        DB::shouldReceive('select')
            ->once()
            ->with('PRAGMA foreign_key_list("posts")')
            ->andReturn($mockForeignKeys);

        $result = $this->adapter->getTableForeignKeys('posts');

        expect($result)->toHaveCount(1);
        expect($result[0])->toEqual([
            'name' => 'fk_0',
            'column' => 'user_id',
            'foreign_table' => 'users',
            'foreign_column' => 'id',
        ]);
    });

    it('returns empty array when no foreign keys exist', function () {
        DB::shouldReceive('select')
            ->once()
            ->andReturn([]);

        $result = $this->adapter->getTableForeignKeys('users');

        expect($result)->toBe([]);
    });
});

describe('getTableStructure method', function () {
    it('returns CREATE TABLE statement from sqlite_master', function () {
        $mockResult = [
            (object) ['sql' => 'CREATE TABLE "users" (id INTEGER PRIMARY KEY, name TEXT)'],
        ];

        DB::shouldReceive('select')
            ->once()
            ->with("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", ['users'])
            ->andReturn($mockResult);

        $result = $this->adapter->getTableStructure('users');

        expect($result)->toBe('CREATE TABLE "users" (id INTEGER PRIMARY KEY, name TEXT)');
    });

    it('returns empty string when table does not exist', function () {
        DB::shouldReceive('select')
            ->once()
            ->andReturn([]);

        $result = $this->adapter->getTableStructure('nonexistent');

        expect($result)->toBe('');
    });
});

describe('getTableData method', function () {
    it('returns all table data as array', function () {
        $mockData = [
            (object) ['id' => 1, 'name' => 'John'],
            (object) ['id' => 2, 'name' => 'Jane'],
        ];

        $queryBuilder = \Mockery::mock();
        $queryBuilder->shouldReceive('get')->once()->andReturn(collect($mockData));

        DB::shouldReceive('table')->once()->with('users')->andReturn($queryBuilder);

        $result = $this->adapter->getTableData('users');

        expect($result)->toEqual([$mockData[0], $mockData[1]]);
    });
});

describe('getTableRowCount method', function () {
    it('returns table row count', function () {
        $queryBuilder = \Mockery::mock();
        $queryBuilder->shouldReceive('count')->once()->andReturn(42);

        DB::shouldReceive('table')->once()->with('users')->andReturn($queryBuilder);

        $result = $this->adapter->getTableRowCount('users');

        expect($result)->toBe(42);
    });
});

describe('archiveTable method', function () {
    it('renames table using ALTER TABLE statement', function () {
        $adapter = \Mockery::mock(SQLiteAdapter::class)->makePartial();
        $adapter->shouldReceive('execute')
            ->once()
            ->with('ALTER TABLE "users" RENAME TO "archived_users_20240101"')
            ->andReturn(true);

        $result = $adapter->archiveTable('users', 'archived_users_20240101');

        expect($result)->toBe(true);
    });
});

describe('archiveColumn method', function () {
    it('recreates table with renamed column', function () {
        $adapter = \Mockery::mock(SQLiteAdapter::class)->makePartial();

        $mockStructure = 'CREATE TABLE "users" (id INTEGER, old_email TEXT)';
        $mockData = [
            (object) ['id' => 1, 'old_email' => 'john@example.com'],
        ];
        $mockColumns = [
            ['name' => 'id'],
            ['name' => 'old_email'],
        ];

        $adapter->shouldReceive('getTableStructure')
            ->once()
            ->with('users')
            ->andReturn($mockStructure);

        $adapter->shouldReceive('getTableData')
            ->once()
            ->with('users')
            ->andReturn($mockData);

        $adapter->shouldReceive('getTableColumns')
            ->once()
            ->with('users')
            ->andReturn($mockColumns);

        // Mock the sequence of SQL executions
        $adapter->shouldReceive('execute')
            ->times(4)
            ->andReturn(true);

        $result = $adapter->archiveColumn('users', 'old_email', 'archived_old_email_20240101');

        expect($result)->toBe(true);
    });

    it('handles empty table data during column rename', function () {
        $adapter = \Mockery::mock(SQLiteAdapter::class)->makePartial();

        $adapter->shouldReceive('getTableStructure')
            ->once()
            ->andReturn('CREATE TABLE "users" (id INTEGER)');

        $adapter->shouldReceive('getTableData')
            ->once()
            ->andReturn([]);

        // Should only execute create temp table, drop original, and rename operations
        $adapter->shouldReceive('execute')
            ->times(3)
            ->andReturn(true);

        $result = $adapter->archiveColumn('users', 'old_column', 'new_column');

        expect($result)->toBe(true);
    });
});

describe('SQL generation methods', function () {
    it('getRenameTableSQL generates correct SQLite syntax', function () {
        $result = $this->adapter->getRenameTableSQL('old_table', 'new_table');
        expect($result)->toBe('ALTER TABLE "old_table" RENAME TO "new_table"');
    });

    it('getAddColumnSQL generates correct SQLite syntax', function () {
        $result = $this->adapter->getAddColumnSQL('users', 'email', 'TEXT');
        expect($result)->toBe('ALTER TABLE "users" ADD COLUMN "email" TEXT');
    });

    it('getDropColumnSQL returns comment about table recreation', function () {
        $result = $this->adapter->getDropColumnSQL('users', 'old_column');
        expect($result)->toBe('-- SQLite: DROP COLUMN requires table recreation');
    });

    it('getRenameColumnSQL generates correct SQLite syntax', function () {
        $result = $this->adapter->getRenameColumnSQL('users', 'old_name', 'new_name');
        expect($result)->toBe('ALTER TABLE "users" RENAME COLUMN "old_name" TO "new_name"');
    });

    it('getCreateIndexSQL generates correct SQLite syntax', function () {
        $result = $this->adapter->getCreateIndexSQL('users', 'idx_email', ['email']);
        expect($result)->toBe('CREATE INDEX "idx_email" ON "users" ("email")');
    });

    it('getCreateIndexSQL handles multiple columns', function () {
        $result = $this->adapter->getCreateIndexSQL('users', 'idx_name_email', ['name', 'email']);
        expect($result)->toBe('CREATE INDEX "idx_name_email" ON "users" ("name", "email")');
    });

    it('getDropIndexSQL generates correct SQLite syntax', function () {
        $result = $this->adapter->getDropIndexSQL('users', 'idx_email');
        expect($result)->toBe('DROP INDEX "idx_email"');
    });

    it('quoteIdentifier wraps identifier in double quotes', function () {
        expect($this->adapter->quoteIdentifier('table_name'))->toBe('"table_name"');
        expect($this->adapter->quoteIdentifier('column_name'))->toBe('"column_name"');
    });
});

describe('getCreateTableSQL method', function () {
    it('calls getTableStructure method', function () {
        $adapter = \Mockery::mock(SQLiteAdapter::class)->makePartial();
        $adapter->shouldReceive('getTableStructure')
            ->once()
            ->with('users')
            ->andReturn('CREATE TABLE "users" (id INTEGER)');

        $result = $adapter->getCreateTableSQL('users', []);

        expect($result)->toBe('CREATE TABLE "users" (id INTEGER)');
    });
});
