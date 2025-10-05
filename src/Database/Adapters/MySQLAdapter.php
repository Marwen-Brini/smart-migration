<?php

namespace Flux\Database\Adapters;

use Flux\Database\DatabaseAdapter;
use Illuminate\Support\Facades\DB;

class MySQLAdapter extends DatabaseAdapter
{
    /**
     * Get the database driver name
     */
    public function getDriverName(): string
    {
        return 'mysql';
    }

    /**
     * Get table structure as CREATE TABLE statement
     */
    public function getTableStructure(string $table): string
    {
        $result = DB::select("SHOW CREATE TABLE `{$table}`");

        if (! empty($result)) {
            $createTable = (array) $result[0];

            return $createTable['Create Table'] ?? '';
        }

        return '';
    }

    /**
     * Get all table data
     */
    public function getTableData(string $table): array
    {
        return DB::table($table)->get()->toArray();
    }

    /**
     * Get table row count
     */
    public function getTableRowCount(string $table): int
    {
        return DB::table($table)->count();
    }

    /**
     * Archive a table by renaming it
     */
    public function archiveTable(string $table, string $newName): bool
    {
        return $this->execute("RENAME TABLE `{$table}` TO `{$newName}`");
    }

    /**
     * Archive a column by renaming it
     */
    public function archiveColumn(string $table, string $column, string $newName): bool
    {
        // Get column definition
        $columnInfo = DB::select("SHOW COLUMNS FROM `{$table}` WHERE Field = ?", [$column]);

        if (empty($columnInfo)) {
            return false;
        }

        $columnDef = $columnInfo[0];
        $type = $columnDef->Type;
        $null = $columnDef->Null === 'YES' ? 'NULL' : 'NOT NULL';
        $default = $columnDef->Default !== null ? "DEFAULT '{$columnDef->Default}'" : '';

        $sql = "ALTER TABLE `{$table}` CHANGE COLUMN `{$column}` `{$newName}` {$type} {$null} {$default}";

        return $this->execute($sql);
    }

    /**
     * Get list of all tables
     */
    public function getAllTables(): array
    {
        $tables = DB::select('SHOW TABLES');
        $database = DB::getDatabaseName();
        $key = "Tables_in_{$database}";

        return array_map(function ($table) use ($key) {
            return $table->$key;
        }, $tables);
    }

    /**
     * Get list of columns for a table
     */
    public function getTableColumns(string $table): array
    {
        $columns = DB::select("SHOW COLUMNS FROM `{$table}`");

        return array_map(function ($column) {
            return [
                'name' => $column->Field,
                'type' => $column->Type,
                'nullable' => $column->Null === 'YES',
                'default' => $column->Default,
                'key' => $column->Key,
                'extra' => $column->Extra,
            ];
        }, $columns);
    }

    /**
     * Get indexes for a table
     */
    public function getTableIndexes(string $table): array
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}`");

        $grouped = [];
        $uniqueStatus = [];
        foreach ($indexes as $index) {
            $grouped[$index->Key_name][] = $index->Column_name;
            $uniqueStatus[$index->Key_name] = $index->Non_unique == 0;
        }

        $result = [];
        foreach ($grouped as $name => $columns) {
            $result[] = [
                'name' => $name,
                'columns' => $columns,
                'unique' => $uniqueStatus[$name],
                'primary' => $name === 'PRIMARY',
            ];
        }

        return $result;
    }

    /**
     * Get foreign keys for a table
     */
    public function getTableForeignKeys(string $table): array
    {
        $database = DB::getDatabaseName();

        $foreignKeys = DB::select('
            SELECT
                CONSTRAINT_NAME as name,
                COLUMN_NAME as column_name,
                REFERENCED_TABLE_NAME as foreign_table,
                REFERENCED_COLUMN_NAME as foreign_column
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME = ?
                AND REFERENCED_TABLE_NAME IS NOT NULL
        ', [$database, $table]);

        return array_map(function ($fk) {
            return [
                'name' => $fk->name,
                'column' => $fk->column_name,
                'foreign_table' => $fk->foreign_table,
                'foreign_column' => $fk->foreign_column,
            ];
        }, $foreignKeys);
    }

    /**
     * Get SQL for creating a table from structure
     */
    public function getCreateTableSQL(string $table, array $structure): string
    {
        // This would build a CREATE TABLE statement from the structure array
        // For now, we'll use the existing structure
        return $this->getTableStructure($table);
    }

    /**
     * Get SQL for renaming a table
     */
    public function getRenameTableSQL(string $from, string $to): string
    {
        return "RENAME TABLE `{$from}` TO `{$to}`";
    }

    /**
     * Get SQL for adding a column
     */
    public function getAddColumnSQL(string $table, string $column, string $type): string
    {
        return "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$type}";
    }

    /**
     * Get SQL for dropping a column
     */
    public function getDropColumnSQL(string $table, string $column): string
    {
        return "ALTER TABLE `{$table}` DROP COLUMN `{$column}`";
    }

    /**
     * Get SQL for renaming a column
     */
    public function getRenameColumnSQL(string $table, string $from, string $to): string
    {
        // MySQL requires the full column definition for renaming
        $columns = $this->getTableColumns($table);
        $columnDef = null;

        foreach ($columns as $col) {
            if ($col['name'] === $from) {
                $columnDef = $col;
                break;
            }
        }

        if (! $columnDef) {
            throw new \Exception("Column {$from} not found in table {$table}");
        }

        $type = $columnDef['type'];
        $null = $columnDef['nullable'] ? 'NULL' : 'NOT NULL';
        $default = $columnDef['default'] !== null ? "DEFAULT '{$columnDef['default']}'" : '';

        return "ALTER TABLE `{$table}` CHANGE COLUMN `{$from}` `{$to}` {$type} {$null} {$default}";
    }

    /**
     * Get SQL for creating an index
     */
    public function getCreateIndexSQL(string $table, string $name, array $columns): string
    {
        $columnList = implode('`, `', $columns);

        return "CREATE INDEX `{$name}` ON `{$table}` (`{$columnList}`)";
    }

    /**
     * Get SQL for dropping an index
     */
    public function getDropIndexSQL(string $table, string $name): string
    {
        return "DROP INDEX `{$name}` ON `{$table}`";
    }

    /**
     * Quote an identifier (table/column name)
     */
    public function quoteIdentifier(string $identifier): string
    {
        return "`{$identifier}`";
    }
}
