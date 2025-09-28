<?php

namespace Flux\Database\Adapters;

use Flux\Database\DatabaseAdapter;
use Illuminate\Support\Facades\DB;

class SQLiteAdapter extends DatabaseAdapter
{
    /**
     * Get the database driver name
     */
    public function getDriverName(): string
    {
        return 'sqlite';
    }

    /**
     * Get table structure as CREATE TABLE statement
     */
    public function getTableStructure(string $table): string
    {
        $result = DB::select("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", [$table]);

        if (!empty($result)) {
            return $result[0]->sql;
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
        return $this->execute("ALTER TABLE \"{$table}\" RENAME TO \"{$newName}\"");
    }

    /**
     * Archive a column by renaming it
     */
    public function archiveColumn(string $table, string $column, string $newName): bool
    {
        // SQLite doesn't support renaming columns directly before version 3.25.0
        // We need to recreate the table
        $structure = $this->getTableStructure($table);
        $data = $this->getTableData($table);

        // Create temporary table with new column name
        $tempTable = "{$table}_temp_" . time();
        $newStructure = str_replace(
            "\"{$column}\"",
            "\"{$newName}\"",
            str_replace(
                "`{$column}`",
                "`{$newName}`",
                str_replace(
                    " {$column} ",
                    " {$newName} ",
                    $structure
                )
            )
        );
        $newStructure = str_replace($table, $tempTable, $newStructure);

        $this->execute($newStructure);

        // Copy data
        if (!empty($data)) {
            $columns = $this->getTableColumns($table);
            $columnNames = array_column($columns, 'name');
            $newColumnNames = array_map(function ($col) use ($column, $newName) {
                return $col === $column ? $newName : $col;
            }, $columnNames);

            $oldCols = implode(', ', array_map(fn($c) => "\"{$c}\"", $columnNames));
            $newCols = implode(', ', array_map(fn($c) => "\"{$c}\"", $newColumnNames));

            $this->execute("INSERT INTO \"{$tempTable}\" ({$newCols}) SELECT {$oldCols} FROM \"{$table}\"");
        }

        // Drop original table and rename temp
        $this->execute("DROP TABLE \"{$table}\"");
        $this->execute("ALTER TABLE \"{$tempTable}\" RENAME TO \"{$table}\"");

        return true;
    }

    /**
     * Get list of all tables
     */
    public function getAllTables(): array
    {
        $tables = DB::select("
            SELECT name FROM sqlite_master
            WHERE type='table'
                AND name NOT LIKE 'sqlite_%'
            ORDER BY name
        ");

        return array_map(function ($table) {
            return $table->name;
        }, $tables);
    }

    /**
     * Get list of columns for a table
     */
    public function getTableColumns(string $table): array
    {
        $columns = DB::select("PRAGMA table_info(\"{$table}\")");

        return array_map(function ($column) {
            return [
                'name' => $column->name,
                'type' => $column->type,
                'nullable' => $column->notnull == 0,
                'default' => $column->dflt_value,
                'key' => $column->pk ? 'PRI' : '',
                'extra' => '',
            ];
        }, $columns);
    }

    /**
     * Get indexes for a table
     */
    public function getTableIndexes(string $table): array
    {
        $indexes = DB::select("PRAGMA index_list(\"{$table}\")");

        $result = [];
        foreach ($indexes as $index) {
            $columns = DB::select("PRAGMA index_info(\"{$index->name}\")");
            $columnNames = array_map(function ($col) {
                return $col->name;
            }, $columns);

            $result[] = [
                'name' => $index->name,
                'columns' => $columnNames,
                'unique' => $index->unique == 1,
                'primary' => strpos($index->name, 'sqlite_autoindex') === 0,
            ];
        }

        return $result;
    }

    /**
     * Get foreign keys for a table
     */
    public function getTableForeignKeys(string $table): array
    {
        $foreignKeys = DB::select("PRAGMA foreign_key_list(\"{$table}\")");

        return array_map(function ($fk) {
            return [
                'name' => "fk_{$fk->id}",
                'column' => $fk->from,
                'foreign_table' => $fk->table,
                'foreign_column' => $fk->to,
            ];
        }, $foreignKeys);
    }

    /**
     * Get SQL for creating a table from structure
     */
    public function getCreateTableSQL(string $table, array $structure): string
    {
        return $this->getTableStructure($table);
    }

    /**
     * Get SQL for renaming a table
     */
    public function getRenameTableSQL(string $from, string $to): string
    {
        return "ALTER TABLE \"{$from}\" RENAME TO \"{$to}\"";
    }

    /**
     * Get SQL for adding a column
     */
    public function getAddColumnSQL(string $table, string $column, string $type): string
    {
        return "ALTER TABLE \"{$table}\" ADD COLUMN \"{$column}\" {$type}";
    }

    /**
     * Get SQL for dropping a column
     */
    public function getDropColumnSQL(string $table, string $column): string
    {
        // SQLite doesn't support DROP COLUMN before version 3.35.0
        // We need to recreate the table without the column
        return "-- SQLite: DROP COLUMN requires table recreation";
    }

    /**
     * Get SQL for renaming a column
     */
    public function getRenameColumnSQL(string $table, string $from, string $to): string
    {
        // SQLite 3.25.0+ supports RENAME COLUMN
        return "ALTER TABLE \"{$table}\" RENAME COLUMN \"{$from}\" TO \"{$to}\"";
    }

    /**
     * Get SQL for creating an index
     */
    public function getCreateIndexSQL(string $table, string $name, array $columns): string
    {
        $columnList = implode('", "', $columns);
        return "CREATE INDEX \"{$name}\" ON \"{$table}\" (\"{$columnList}\")";
    }

    /**
     * Get SQL for dropping an index
     */
    public function getDropIndexSQL(string $table, string $name): string
    {
        return "DROP INDEX \"{$name}\"";
    }

    /**
     * Quote an identifier (table/column name)
     */
    public function quoteIdentifier(string $identifier): string
    {
        return "\"{$identifier}\"";
    }
}