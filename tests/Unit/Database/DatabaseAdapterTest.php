<?php

use Flux\Database\DatabaseAdapter;
use Illuminate\Support\Facades\DB;

// Create a concrete implementation for testing
class TestDatabaseAdapter extends DatabaseAdapter
{
    public function getDriverName(): string
    {
        return 'test';
    }

    public function getTableStructure(string $table): string
    {
        return "CREATE TABLE {$table} (id INT PRIMARY KEY)";
    }

    public function getTableData(string $table): array
    {
        return [['id' => 1], ['id' => 2]];
    }

    public function getTableRowCount(string $table): int
    {
        return 2;
    }

    public function archiveTable(string $table, string $newName): bool
    {
        return true;
    }

    public function archiveColumn(string $table, string $column, string $newName): bool
    {
        return true;
    }

    public function getAllTables(): array
    {
        return ['users', 'posts', 'comments'];
    }

    public function getTableColumns(string $table): array
    {
        return [
            ['name' => 'id', 'type' => 'integer'],
            ['name' => 'name', 'type' => 'string'],
            ['name' => 'email', 'type' => 'string'],
        ];
    }

    public function getTableIndexes(string $table): array
    {
        return [
            ['name' => 'primary', 'columns' => ['id']],
            ['name' => 'email_index', 'columns' => ['email']],
        ];
    }

    public function getTableForeignKeys(string $table): array
    {
        return [
            ['name' => 'user_id_fk', 'column' => 'user_id', 'references' => 'users.id'],
        ];
    }

    public function getCreateTableSQL(string $table, array $structure): string
    {
        return "CREATE TABLE {$table} (".implode(', ', $structure).')';
    }

    public function getRenameTableSQL(string $from, string $to): string
    {
        return "RENAME TABLE {$from} TO {$to}";
    }

    public function getAddColumnSQL(string $table, string $column, string $type): string
    {
        return "ALTER TABLE {$table} ADD COLUMN {$column} {$type}";
    }

    public function getDropColumnSQL(string $table, string $column): string
    {
        return "ALTER TABLE {$table} DROP COLUMN {$column}";
    }

    public function getRenameColumnSQL(string $table, string $from, string $to): string
    {
        return "ALTER TABLE {$table} RENAME COLUMN {$from} TO {$to}";
    }

    public function getCreateIndexSQL(string $table, string $name, array $columns): string
    {
        return "CREATE INDEX {$name} ON {$table} (".implode(', ', $columns).')';
    }

    public function getDropIndexSQL(string $table, string $name): string
    {
        return "DROP INDEX {$name} ON {$table}";
    }

    public function quoteIdentifier(string $identifier): string
    {
        return "`{$identifier}`";
    }
}

beforeEach(function () {
    $this->adapter = new TestDatabaseAdapter;
});

afterEach(function () {
    Mockery::close();
});

describe('supports method', function () {
    it('returns true when driver name matches', function () {
        $connectionMock = Mockery::mock();
        $connectionMock->shouldReceive('getDriverName')->once()->andReturn('test');

        DB::shouldReceive('connection')->once()->andReturn($connectionMock);

        $result = $this->adapter->supports();

        expect($result)->toBe(true);
    });

    it('returns false when driver name does not match', function () {
        $connectionMock = Mockery::mock();
        $connectionMock->shouldReceive('getDriverName')->once()->andReturn('mysql');

        DB::shouldReceive('connection')->once()->andReturn($connectionMock);

        $result = $this->adapter->supports();

        expect($result)->toBe(false);
    });
});

describe('tableExists method', function () {
    it('returns true when table exists', function () {
        $result = $this->adapter->tableExists('users');

        expect($result)->toBe(true);
    });

    it('returns false when table does not exist', function () {
        $result = $this->adapter->tableExists('non_existing_table');

        expect($result)->toBe(false);
    });
});

describe('columnExists method', function () {
    it('returns true when column exists', function () {
        $result = $this->adapter->columnExists('users', 'email');

        expect($result)->toBe(true);
    });

    it('returns false when column does not exist', function () {
        $result = $this->adapter->columnExists('users', 'non_existing_column');

        expect($result)->toBe(false);
    });
});

describe('estimateOperationDuration method', function () {
    it('estimates duration for create table operation', function () {
        $duration = $this->adapter->estimateOperationDuration('create_table', 'users');

        expect($duration)->toBe(10);
    });

    it('estimates duration for drop table operation', function () {
        $duration = $this->adapter->estimateOperationDuration('drop_table', 'users');

        expect($duration)->toBe(10);
    });

    it('estimates duration for add column operation based on row count', function () {
        $duration = $this->adapter->estimateOperationDuration('add_column', 'users');

        // Should be 10 + (2 * 0.001) = 10.002, rounded to 10
        expect($duration)->toBe(10);
    });

    it('estimates duration for drop column operation based on row count', function () {
        $duration = $this->adapter->estimateOperationDuration('drop_column', 'users');

        // Should be 10 + (2 * 0.001) = 10.002, rounded to 10
        expect($duration)->toBe(10);
    });

    it('estimates duration for add index operation based on row count', function () {
        $duration = $this->adapter->estimateOperationDuration('add_index', 'users');

        // Should be 50 + (2 * 0.01) = 50.02, rounded to 50
        expect($duration)->toBe(50);
    });

    it('estimates duration for drop index operation', function () {
        $duration = $this->adapter->estimateOperationDuration('drop_index', 'users');

        expect($duration)->toBe(20);
    });

    it('estimates duration for modify column operation based on row count', function () {
        $duration = $this->adapter->estimateOperationDuration('modify_column', 'users');

        // Should be 20 + (2 * 0.005) = 20.01, rounded to 20
        expect($duration)->toBe(20);
    });

    it('returns default duration for unknown operations', function () {
        $duration = $this->adapter->estimateOperationDuration('unknown_operation', 'users');

        expect($duration)->toBe(100);
    });
});

describe('getDropTableSQL method', function () {
    it('generates correct drop table SQL', function () {
        $sql = $this->adapter->getDropTableSQL('users');

        expect($sql)->toBe('DROP TABLE IF EXISTS users');
    });
});

describe('beginTransaction method', function () {
    it('calls DB::beginTransaction', function () {
        DB::shouldReceive('beginTransaction')->once();

        $this->adapter->beginTransaction();
    });
});

describe('commit method', function () {
    it('calls DB::commit', function () {
        DB::shouldReceive('commit')->once();

        $this->adapter->commit();
    });
});

describe('rollback method', function () {
    it('calls DB::rollBack', function () {
        DB::shouldReceive('rollBack')->once();

        $this->adapter->rollback();
    });
});

describe('execute method', function () {
    it('executes raw SQL statement', function () {
        $sql = 'CREATE TABLE test (id INT)';

        DB::shouldReceive('statement')->once()->with($sql)->andReturn(true);

        $result = $this->adapter->execute($sql);

        expect($result)->toBe(true);
    });

    it('returns false when SQL execution fails', function () {
        $sql = 'INVALID SQL';

        DB::shouldReceive('statement')->once()->with($sql)->andReturn(false);

        $result = $this->adapter->execute($sql);

        expect($result)->toBe(false);
    });
});

describe('abstract method implementations', function () {
    it('getDriverName returns correct driver name', function () {
        expect($this->adapter->getDriverName())->toBe('test');
    });

    it('getTableStructure returns SQL structure', function () {
        $structure = $this->adapter->getTableStructure('users');

        expect($structure)->toBe('CREATE TABLE users (id INT PRIMARY KEY)');
    });

    it('getTableData returns table data', function () {
        $data = $this->adapter->getTableData('users');

        expect($data)->toBe([['id' => 1], ['id' => 2]]);
    });

    it('getTableRowCount returns row count', function () {
        $count = $this->adapter->getTableRowCount('users');

        expect($count)->toBe(2);
    });

    it('archiveTable returns success', function () {
        $result = $this->adapter->archiveTable('users', 'archived_users');

        expect($result)->toBe(true);
    });

    it('archiveColumn returns success', function () {
        $result = $this->adapter->archiveColumn('users', 'email', 'archived_email');

        expect($result)->toBe(true);
    });

    it('getAllTables returns table list', function () {
        $tables = $this->adapter->getAllTables();

        expect($tables)->toBe(['users', 'posts', 'comments']);
    });

    it('getTableColumns returns column information', function () {
        $columns = $this->adapter->getTableColumns('users');

        expect($columns)->toBe([
            ['name' => 'id', 'type' => 'integer'],
            ['name' => 'name', 'type' => 'string'],
            ['name' => 'email', 'type' => 'string'],
        ]);
    });

    it('getTableIndexes returns index information', function () {
        $indexes = $this->adapter->getTableIndexes('users');

        expect($indexes)->toBe([
            ['name' => 'primary', 'columns' => ['id']],
            ['name' => 'email_index', 'columns' => ['email']],
        ]);
    });

    it('getTableForeignKeys returns foreign key information', function () {
        $foreignKeys = $this->adapter->getTableForeignKeys('users');

        expect($foreignKeys)->toBe([
            ['name' => 'user_id_fk', 'column' => 'user_id', 'references' => 'users.id'],
        ]);
    });

    it('getCreateTableSQL generates SQL', function () {
        $sql = $this->adapter->getCreateTableSQL('users', ['id INT', 'name VARCHAR(255)']);

        expect($sql)->toBe('CREATE TABLE users (id INT, name VARCHAR(255))');
    });

    it('getRenameTableSQL generates SQL', function () {
        $sql = $this->adapter->getRenameTableSQL('old_table', 'new_table');

        expect($sql)->toBe('RENAME TABLE old_table TO new_table');
    });

    it('getAddColumnSQL generates SQL', function () {
        $sql = $this->adapter->getAddColumnSQL('users', 'phone', 'VARCHAR(20)');

        expect($sql)->toBe('ALTER TABLE users ADD COLUMN phone VARCHAR(20)');
    });

    it('getDropColumnSQL generates SQL', function () {
        $sql = $this->adapter->getDropColumnSQL('users', 'phone');

        expect($sql)->toBe('ALTER TABLE users DROP COLUMN phone');
    });

    it('getRenameColumnSQL generates SQL', function () {
        $sql = $this->adapter->getRenameColumnSQL('users', 'old_name', 'new_name');

        expect($sql)->toBe('ALTER TABLE users RENAME COLUMN old_name TO new_name');
    });

    it('getCreateIndexSQL generates SQL', function () {
        $sql = $this->adapter->getCreateIndexSQL('users', 'email_index', ['email', 'name']);

        expect($sql)->toBe('CREATE INDEX email_index ON users (email, name)');
    });

    it('getDropIndexSQL generates SQL', function () {
        $sql = $this->adapter->getDropIndexSQL('users', 'email_index');

        expect($sql)->toBe('DROP INDEX email_index ON users');
    });

    it('quoteIdentifier quotes identifiers', function () {
        $quoted = $this->adapter->quoteIdentifier('table_name');

        expect($quoted)->toBe('`table_name`');
    });
});
