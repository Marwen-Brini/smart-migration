<?php

use Flux\Database\Adapters\MySQLAdapter;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->adapter = new MySQLAdapter;
});

afterEach(function () {
    Mockery::close();
});

describe('getDriverName method', function () {
    it('returns mysql as driver name', function () {
        expect($this->adapter->getDriverName())->toBe('mysql');
    });
});

describe('getTableStructure method', function () {
    it('returns CREATE TABLE statement for existing table', function () {
        $createTableResult = [(object) ['Create Table' => 'CREATE TABLE `users` (id INT PRIMARY KEY)']];

        DB::shouldReceive('select')->once()->with('SHOW CREATE TABLE `users`')->andReturn($createTableResult);

        $result = $this->adapter->getTableStructure('users');

        expect($result)->toBe('CREATE TABLE `users` (id INT PRIMARY KEY)');
    });

    it('returns empty string when table does not exist', function () {
        DB::shouldReceive('select')->once()->with('SHOW CREATE TABLE `non_existing`')->andReturn([]);

        $result = $this->adapter->getTableStructure('non_existing');

        expect($result)->toBe('');
    });

    it('returns empty string when result has no Create Table key', function () {
        $createTableResult = [(object) ['Invalid' => 'value']];

        DB::shouldReceive('select')->once()->with('SHOW CREATE TABLE `users`')->andReturn($createTableResult);

        $result = $this->adapter->getTableStructure('users');

        expect($result)->toBe('');
    });
});

describe('getTableData method', function () {
    it('returns table data as array', function () {
        $tableData = collect([['id' => 1, 'name' => 'John'], ['id' => 2, 'name' => 'Jane']]);

        $tableMock = Mockery::mock();
        $tableMock->shouldReceive('get')->once()->andReturn($tableData);
        DB::shouldReceive('table')->once()->with('users')->andReturn($tableMock);

        $result = $this->adapter->getTableData('users');

        expect($result)->toBe($tableData->toArray());
    });
});

describe('getTableRowCount method', function () {
    it('returns correct row count', function () {
        $tableMock = Mockery::mock();
        $tableMock->shouldReceive('count')->once()->andReturn(5);
        DB::shouldReceive('table')->once()->with('users')->andReturn($tableMock);

        $result = $this->adapter->getTableRowCount('users');

        expect($result)->toBe(5);
    });
});

describe('archiveTable method', function () {
    it('renames table successfully', function () {
        $adapter = Mockery::mock(MySQLAdapter::class)->makePartial();
        $adapter->shouldReceive('execute')->once()->with('RENAME TABLE `users` TO `archived_users`')->andReturn(true);

        $result = $adapter->archiveTable('users', 'archived_users');

        expect($result)->toBe(true);
    });

    it('returns false when rename fails', function () {
        $adapter = Mockery::mock(MySQLAdapter::class)->makePartial();
        $adapter->shouldReceive('execute')->once()->with('RENAME TABLE `users` TO `archived_users`')->andReturn(false);

        $result = $adapter->archiveTable('users', 'archived_users');

        expect($result)->toBe(false);
    });
});

describe('archiveColumn method', function () {
    it('renames column successfully with all attributes', function () {
        $columnInfo = [(object) [
            'Type' => 'VARCHAR(255)',
            'Null' => 'YES',
            'Default' => 'default_value',
        ]];

        DB::shouldReceive('select')->once()->with('SHOW COLUMNS FROM `users` WHERE Field = ?', ['email'])->andReturn($columnInfo);

        $adapter = Mockery::mock(MySQLAdapter::class)->makePartial();
        $adapter->shouldReceive('execute')->once()->with("ALTER TABLE `users` CHANGE COLUMN `email` `archived_email` VARCHAR(255) NULL DEFAULT 'default_value'")->andReturn(true);

        $result = $adapter->archiveColumn('users', 'email', 'archived_email');

        expect($result)->toBe(true);
    });

    it('renames column successfully with NOT NULL and no default', function () {
        $columnInfo = [(object) [
            'Type' => 'INT',
            'Null' => 'NO',
            'Default' => null,
        ]];

        DB::shouldReceive('select')->once()->with('SHOW COLUMNS FROM `users` WHERE Field = ?', ['id'])->andReturn($columnInfo);

        $adapter = Mockery::mock(MySQLAdapter::class)->makePartial();
        $adapter->shouldReceive('execute')->once()->with('ALTER TABLE `users` CHANGE COLUMN `id` `archived_id` INT NOT NULL ')->andReturn(true);

        $result = $adapter->archiveColumn('users', 'id', 'archived_id');

        expect($result)->toBe(true);
    });

    it('returns false when column does not exist', function () {
        DB::shouldReceive('select')->once()->with('SHOW COLUMNS FROM `users` WHERE Field = ?', ['non_existing'])->andReturn([]);

        $result = $this->adapter->archiveColumn('users', 'non_existing', 'archived_non_existing');

        expect($result)->toBe(false);
    });
});

describe('getAllTables method', function () {
    it('returns list of all tables', function () {
        $tables = [
            (object) ['Tables_in_test_db' => 'users'],
            (object) ['Tables_in_test_db' => 'posts'],
            (object) ['Tables_in_test_db' => 'comments'],
        ];

        DB::shouldReceive('select')->once()->with('SHOW TABLES')->andReturn($tables);
        DB::shouldReceive('getDatabaseName')->once()->andReturn('test_db');

        $result = $this->adapter->getAllTables();

        expect($result)->toBe(['users', 'posts', 'comments']);
    });
});

describe('getTableColumns method', function () {
    it('returns column information with all attributes', function () {
        $columns = [
            (object) [
                'Field' => 'id',
                'Type' => 'INT',
                'Null' => 'NO',
                'Default' => null,
                'Key' => 'PRI',
                'Extra' => 'auto_increment',
            ],
            (object) [
                'Field' => 'email',
                'Type' => 'VARCHAR(255)',
                'Null' => 'YES',
                'Default' => 'test@example.com',
                'Key' => 'UNI',
                'Extra' => '',
            ],
        ];

        DB::shouldReceive('select')->once()->with('SHOW COLUMNS FROM `users`')->andReturn($columns);

        $result = $this->adapter->getTableColumns('users');

        expect($result)->toBe([
            [
                'name' => 'id',
                'type' => 'INT',
                'nullable' => false,
                'default' => null,
                'key' => 'PRI',
                'extra' => 'auto_increment',
            ],
            [
                'name' => 'email',
                'type' => 'VARCHAR(255)',
                'nullable' => true,
                'default' => 'test@example.com',
                'key' => 'UNI',
                'extra' => '',
            ],
        ]);
    });
});

describe('getTableIndexes method', function () {
    it('returns index information grouped by index name', function () {
        $indexes = [
            (object) ['Key_name' => 'PRIMARY', 'Column_name' => 'id', 'Non_unique' => '0', 'Index_type' => 'BTREE'],
            (object) ['Key_name' => 'email_index', 'Column_name' => 'email', 'Non_unique' => '1', 'Index_type' => 'BTREE'],
            (object) ['Key_name' => 'name_email_index', 'Column_name' => 'name', 'Non_unique' => '0', 'Index_type' => 'BTREE'],
            (object) ['Key_name' => 'name_email_index', 'Column_name' => 'email', 'Non_unique' => '0', 'Index_type' => 'BTREE'],
        ];

        DB::shouldReceive('select')->once()->with('SHOW INDEX FROM `users`')->andReturn($indexes);

        $result = $this->adapter->getTableIndexes('users');

        expect($result)->toHaveCount(3);
        expect($result[0])->toEqual([
            'name' => 'PRIMARY',
            'columns' => ['id'],
            'unique' => true,
            'primary' => true,
            'type' => 'BTREE',
        ]);
        expect($result[2])->toEqual([
            'name' => 'name_email_index',
            'columns' => ['name', 'email'],
            'unique' => true,
            'primary' => false,
            'type' => 'BTREE',
        ]);
    });
});

describe('getTableForeignKeys method', function () {
    it('returns foreign key information', function () {
        $foreignKeys = [
            (object) [
                'name' => 'user_posts_fk',
                'column_name' => 'user_id',
                'foreign_table' => 'users',
                'foreign_column' => 'id',
            ],
        ];

        DB::shouldReceive('getDatabaseName')->once()->andReturn('test_db');
        DB::shouldReceive('select')->once()->with(
            Mockery::type('string'),
            ['test_db', 'posts']
        )->andReturn($foreignKeys);

        $result = $this->adapter->getTableForeignKeys('posts');

        expect($result)->toBe([
            [
                'name' => 'user_posts_fk',
                'column' => 'user_id',
                'foreign_table' => 'users',
                'foreign_column' => 'id',
            ],
        ]);
    });
});

describe('getCreateTableSQL method', function () {
    it('delegates to getTableStructure method', function () {
        $adapter = Mockery::mock(MySQLAdapter::class)->makePartial();
        $adapter->shouldReceive('getTableStructure')->once()->with('users')->andReturn('CREATE TABLE `users` (id INT)');

        $result = $adapter->getCreateTableSQL('users', []);

        expect($result)->toBe('CREATE TABLE `users` (id INT)');
    });
});

describe('getRenameTableSQL method', function () {
    it('generates correct rename table SQL', function () {
        $sql = $this->adapter->getRenameTableSQL('old_table', 'new_table');

        expect($sql)->toBe('RENAME TABLE `old_table` TO `new_table`');
    });
});

describe('getAddColumnSQL method', function () {
    it('generates correct add column SQL', function () {
        $sql = $this->adapter->getAddColumnSQL('users', 'phone', 'VARCHAR(20)');

        expect($sql)->toBe('ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(20)');
    });
});

describe('getDropColumnSQL method', function () {
    it('generates correct drop column SQL', function () {
        $sql = $this->adapter->getDropColumnSQL('users', 'phone');

        expect($sql)->toBe('ALTER TABLE `users` DROP COLUMN `phone`');
    });
});

describe('getRenameColumnSQL method', function () {
    it('generates correct rename column SQL with full definition', function () {
        $columns = [
            ['name' => 'email', 'type' => 'VARCHAR(255)', 'nullable' => true, 'default' => 'test@example.com'],
        ];

        $adapter = Mockery::mock(MySQLAdapter::class)->makePartial();
        $adapter->shouldReceive('getTableColumns')->once()->with('users')->andReturn($columns);

        $sql = $adapter->getRenameColumnSQL('users', 'email', 'email_address');

        expect($sql)->toBe("ALTER TABLE `users` CHANGE COLUMN `email` `email_address` VARCHAR(255) NULL DEFAULT 'test@example.com'");
    });

    it('generates correct rename column SQL without default', function () {
        $columns = [
            ['name' => 'id', 'type' => 'INT', 'nullable' => false, 'default' => null],
        ];

        $adapter = Mockery::mock(MySQLAdapter::class)->makePartial();
        $adapter->shouldReceive('getTableColumns')->once()->with('users')->andReturn($columns);

        $sql = $adapter->getRenameColumnSQL('users', 'id', 'user_id');

        expect($sql)->toBe('ALTER TABLE `users` CHANGE COLUMN `id` `user_id` INT NOT NULL ');
    });

    it('throws exception when column is not found', function () {
        $adapter = Mockery::mock(MySQLAdapter::class)->makePartial();
        $adapter->shouldReceive('getTableColumns')->once()->with('users')->andReturn([]);

        expect(fn () => $adapter->getRenameColumnSQL('users', 'non_existing', 'new_name'))
            ->toThrow(Exception::class, 'Column non_existing not found in table users');
    });
});

describe('getCreateIndexSQL method', function () {
    it('generates correct create index SQL for single column', function () {
        $sql = $this->adapter->getCreateIndexSQL('users', 'email_index', ['email']);

        expect($sql)->toBe('CREATE INDEX `email_index` ON `users` (`email`)');
    });

    it('generates correct create index SQL for multiple columns', function () {
        $sql = $this->adapter->getCreateIndexSQL('users', 'name_email_index', ['name', 'email']);

        expect($sql)->toBe('CREATE INDEX `name_email_index` ON `users` (`name`, `email`)');
    });
});

describe('getDropIndexSQL method', function () {
    it('generates correct drop index SQL', function () {
        $sql = $this->adapter->getDropIndexSQL('users', 'email_index');

        expect($sql)->toBe('DROP INDEX `email_index` ON `users`');
    });
});

describe('quoteIdentifier method', function () {
    it('quotes identifier with backticks', function () {
        $quoted = $this->adapter->quoteIdentifier('table_name');

        expect($quoted)->toBe('`table_name`');
    });
});
