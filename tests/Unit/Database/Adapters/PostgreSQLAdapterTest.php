<?php

use Flux\Database\Adapters\PostgreSQLAdapter;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->adapter = new PostgreSQLAdapter;

    // Mock DB facade for tests that need it
    DB::partialMock();
});

afterEach(function () {
    \Mockery::close();
});

describe('getDriverName method', function () {
    it('returns postgresql driver name', function () {
        expect($this->adapter->getDriverName())->toBe('pgsql');
    });
});

describe('getAllTables method', function () {
    it('returns list of all tables from pg_catalog', function () {
        $mockTables = [
            (object) ['tablename' => 'users'],
            (object) ['tablename' => 'posts'],
            (object) ['tablename' => 'comments'],
        ];

        DB::shouldReceive('select')
            ->once()
            ->with("
            SELECT tablename
            FROM pg_catalog.pg_tables
            WHERE schemaname = 'public'
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
                'column_name' => 'id',
                'data_type' => 'integer',
                'character_maximum_length' => null,
                'numeric_precision' => null,
                'numeric_scale' => null,
                'is_nullable' => 'NO',
                'column_default' => 'nextval(\'users_id_seq\'::regclass)',
            ],
            (object) [
                'column_name' => 'name',
                'data_type' => 'character varying',
                'character_maximum_length' => 255,
                'numeric_precision' => null,
                'numeric_scale' => null,
                'is_nullable' => 'YES',
                'column_default' => null,
            ],
        ];

        DB::shouldReceive('select')
            ->once()
            ->with(\Mockery::type('string'), ['users'])
            ->andReturn($mockColumns);

        $result = $this->adapter->getTableColumns('users');

        expect($result)->toHaveCount(2);
        expect($result[0])->toEqual([
            'name' => 'id',
            'type' => 'integer',
            'nullable' => false,
            'default' => null,
            'auto_increment' => true,
            'unsigned' => false,
            'key' => '',
            'extra' => '',
        ]);
        expect($result[1])->toEqual([
            'name' => 'name',
            'type' => 'character varying(255)',
            'nullable' => true,
            'default' => null,
            'auto_increment' => false,
            'unsigned' => false,
            'key' => '',
            'extra' => '',
        ]);
    });

    it('handles columns without character length', function () {
        $mockColumns = [
            (object) [
                'column_name' => 'created_at',
                'data_type' => 'timestamp',
                'character_maximum_length' => null,
                'is_nullable' => 'NO',
                'column_default' => 'CURRENT_TIMESTAMP',
            ],
        ];

        DB::shouldReceive('select')
            ->once()
            ->andReturn($mockColumns);

        $result = $this->adapter->getTableColumns('users');

        expect($result[0]['type'])->toBe('timestamp');
    });

    it('handles numeric and decimal columns with precision and scale', function () {
        $mockColumns = [
            (object) [
                'column_name' => 'price',
                'data_type' => 'numeric',
                'character_maximum_length' => null,
                'numeric_precision' => 10,
                'numeric_scale' => 2,
                'is_nullable' => 'YES',
                'column_default' => null,
            ],
            (object) [
                'column_name' => 'amount',
                'data_type' => 'decimal',
                'character_maximum_length' => null,
                'numeric_precision' => 15,
                'numeric_scale' => 4,
                'is_nullable' => 'NO',
                'column_default' => '0',
            ],
        ];

        DB::shouldReceive('select')
            ->once()
            ->andReturn($mockColumns);

        $result = $this->adapter->getTableColumns('products');

        expect($result[0]['type'])->toBe('numeric(10,2)');
        expect($result[1]['type'])->toBe('decimal(15,4)');
    });
});

describe('getTableIndexes method', function () {
    it('returns formatted index information', function () {
        $mockIndexes = [
            (object) [
                'index_name' => 'users_pkey',
                'column_name' => 'id',
                'is_primary' => true,
                'is_unique' => true,
                'index_type' => 'btree',
            ],
            (object) [
                'index_name' => 'users_email_unique',
                'column_name' => 'email',
                'is_primary' => false,
                'is_unique' => true,
                'index_type' => 'btree',
            ],
        ];

        DB::shouldReceive('select')
            ->once()
            ->with(\Mockery::type('string'), ['users'])
            ->andReturn($mockIndexes);

        $result = $this->adapter->getTableIndexes('users');

        expect($result)->toHaveCount(2);
        expect($result[0])->toEqual([
            'name' => 'users_pkey',
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
                'index_name' => 'users_name_email_idx',
                'column_name' => 'name',
                'is_primary' => false,
                'is_unique' => false,
                'index_type' => 'btree',
            ],
            (object) [
                'index_name' => 'users_name_email_idx',
                'column_name' => 'email',
                'is_primary' => false,
                'is_unique' => false,
                'index_type' => 'btree',
            ],
        ];

        DB::shouldReceive('select')
            ->once()
            ->andReturn($mockIndexes);

        $result = $this->adapter->getTableIndexes('users');

        expect($result)->toHaveCount(1);
        expect($result[0]['columns'])->toBe(['name', 'email']);
    });
});

describe('getTableForeignKeys method', function () {
    it('returns formatted foreign key information', function () {
        $mockForeignKeys = [
            (object) [
                'name' => 'posts_user_id_foreign',
                'column_name' => 'user_id',
                'foreign_table' => 'users',
                'foreign_column' => 'id',
            ],
        ];

        DB::shouldReceive('select')
            ->once()
            ->with(\Mockery::type('string'), ['posts'])
            ->andReturn($mockForeignKeys);

        $result = $this->adapter->getTableForeignKeys('posts');

        expect($result)->toHaveCount(1);
        expect($result[0])->toEqual([
            'name' => 'posts_user_id_foreign',
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
    it('builds CREATE TABLE statement with columns and primary key', function () {
        $adapter = \Mockery::mock(PostgreSQLAdapter::class)->makePartial();

        $mockColumns = [
            [
                'name' => 'id',
                'type' => 'integer',
                'nullable' => false,
                'default' => 'nextval(\'users_id_seq\'::regclass)',
            ],
            [
                'name' => 'name',
                'type' => 'character varying(255)',
                'nullable' => true,
                'default' => null,
            ],
        ];

        $mockIndexes = [
            [
                'name' => 'users_pkey',
                'columns' => ['id'],
                'unique' => true,
                'primary' => true,
            ],
        ];

        $adapter->shouldReceive('getTableColumns')->once()->with('users')->andReturn($mockColumns);
        $adapter->shouldReceive('getTableIndexes')->once()->with('users')->andReturn($mockIndexes);
        $adapter->shouldReceive('getTableForeignKeys')->once()->with('users')->andReturn([]);

        $result = $adapter->getTableStructure('users');

        expect($result)->toContain('CREATE TABLE "users"');
        expect($result)->toContain('"id" integer NOT NULL DEFAULT nextval(\'users_id_seq\'::regclass)');
        expect($result)->toContain('"name" character varying(255)');
        expect($result)->toContain('PRIMARY KEY ("id")');
    });

    it('handles tables without primary key', function () {
        $adapter = \Mockery::mock(PostgreSQLAdapter::class)->makePartial();

        $adapter->shouldReceive('getTableColumns')->once()->andReturn([
            [
                'name' => 'data',
                'type' => 'text',
                'nullable' => true,
                'default' => null,
            ],
        ]);
        $adapter->shouldReceive('getTableIndexes')->once()->andReturn([]);
        $adapter->shouldReceive('getTableForeignKeys')->once()->andReturn([]);

        $result = $adapter->getTableStructure('logs');

        expect($result)->toContain('CREATE TABLE "logs"');
        expect($result)->not->toContain('PRIMARY KEY');
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
        $adapter = \Mockery::mock(PostgreSQLAdapter::class)->makePartial();
        $adapter->shouldReceive('execute')
            ->once()
            ->with('ALTER TABLE "users" RENAME TO "archived_users_20240101"')
            ->andReturn(true);

        $result = $adapter->archiveTable('users', 'archived_users_20240101');

        expect($result)->toBe(true);
    });
});

describe('archiveColumn method', function () {
    it('renames column using ALTER TABLE statement', function () {
        $adapter = \Mockery::mock(PostgreSQLAdapter::class)->makePartial();
        $adapter->shouldReceive('execute')
            ->once()
            ->with('ALTER TABLE "users" RENAME COLUMN "old_email" TO "archived_old_email_20240101"')
            ->andReturn(true);

        $result = $adapter->archiveColumn('users', 'old_email', 'archived_old_email_20240101');

        expect($result)->toBe(true);
    });
});

describe('SQL generation methods', function () {
    it('getRenameTableSQL generates correct PostgreSQL syntax', function () {
        $result = $this->adapter->getRenameTableSQL('old_table', 'new_table');
        expect($result)->toBe('ALTER TABLE "old_table" RENAME TO "new_table"');
    });

    it('getAddColumnSQL generates correct PostgreSQL syntax', function () {
        $result = $this->adapter->getAddColumnSQL('users', 'email', 'VARCHAR(255)');
        expect($result)->toBe('ALTER TABLE "users" ADD COLUMN "email" VARCHAR(255)');
    });

    it('getDropColumnSQL generates correct PostgreSQL syntax', function () {
        $result = $this->adapter->getDropColumnSQL('users', 'old_column');
        expect($result)->toBe('ALTER TABLE "users" DROP COLUMN "old_column"');
    });

    it('getRenameColumnSQL generates correct PostgreSQL syntax', function () {
        $result = $this->adapter->getRenameColumnSQL('users', 'old_name', 'new_name');
        expect($result)->toBe('ALTER TABLE "users" RENAME COLUMN "old_name" TO "new_name"');
    });

    it('getCreateIndexSQL generates correct PostgreSQL syntax', function () {
        $result = $this->adapter->getCreateIndexSQL('users', 'idx_email', ['email']);
        expect($result)->toBe('CREATE INDEX "idx_email" ON "users" ("email")');
    });

    it('getCreateIndexSQL handles multiple columns', function () {
        $result = $this->adapter->getCreateIndexSQL('users', 'idx_name_email', ['name', 'email']);
        expect($result)->toBe('CREATE INDEX "idx_name_email" ON "users" ("name", "email")');
    });

    it('getDropIndexSQL generates correct PostgreSQL syntax', function () {
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
        $adapter = \Mockery::mock(PostgreSQLAdapter::class)->makePartial();
        $adapter->shouldReceive('getTableStructure')
            ->once()
            ->with('users')
            ->andReturn('CREATE TABLE "users" (id integer)');

        $result = $adapter->getCreateTableSQL('users', []);

        expect($result)->toBe('CREATE TABLE "users" (id integer)');
    });
});
