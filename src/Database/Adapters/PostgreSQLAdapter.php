<?php

namespace Flux\Database\Adapters;

use Flux\Database\DatabaseAdapter;
use Illuminate\Support\Facades\DB;

class PostgreSQLAdapter extends DatabaseAdapter
{
    /**
     * Get the database driver name
     */
    public function getDriverName(): string
    {
        return 'pgsql';
    }

    /**
     * Get table structure as CREATE TABLE statement
     */
    public function getTableStructure(string $table): string
    {
        // PostgreSQL doesn't have SHOW CREATE TABLE, we need to build it
        $columns = $this->getTableColumns($table);
        $indexes = $this->getTableIndexes($table);
        $foreignKeys = $this->getTableForeignKeys($table);

        $sql = "CREATE TABLE \"{$table}\" (\n";

        // Add columns
        $columnDefs = [];
        foreach ($columns as $column) {
            $def = "    \"{$column['name']}\" {$column['type']}";
            if (!$column['nullable']) {
                $def .= " NOT NULL";
            }
            if ($column['default'] !== null) {
                $def .= " DEFAULT {$column['default']}";
            }
            $columnDefs[] = $def;
        }
        $sql .= implode(",\n", $columnDefs);

        // Add primary key
        foreach ($indexes as $index) {
            if ($index['primary']) {
                $columns = implode('", "', $index['columns']);
                $sql .= ",\n    PRIMARY KEY (\"{$columns}\")";
                break;
            }
        }

        $sql .= "\n)";

        return $sql;
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
        return $this->execute("ALTER TABLE \"{$table}\" RENAME COLUMN \"{$column}\" TO \"{$newName}\"");
    }

    /**
     * Get list of all tables
     */
    public function getAllTables(): array
    {
        $tables = DB::select("
            SELECT tablename
            FROM pg_catalog.pg_tables
            WHERE schemaname = 'public'
        ");

        return array_map(function ($table) {
            return $table->tablename;
        }, $tables);
    }

    /**
     * Get list of columns for a table
     */
    public function getTableColumns(string $table): array
    {
        $columns = DB::select("
            SELECT
                column_name,
                data_type,
                character_maximum_length,
                is_nullable,
                column_default
            FROM information_schema.columns
            WHERE table_schema = 'public'
                AND table_name = ?
            ORDER BY ordinal_position
        ", [$table]);

        return array_map(function ($column) {
            $type = $column->data_type;
            if ($column->character_maximum_length) {
                $type .= "({$column->character_maximum_length})";
            }

            return [
                'name' => $column->column_name,
                'type' => $type,
                'nullable' => $column->is_nullable === 'YES',
                'default' => $column->column_default,
                'key' => '',
                'extra' => '',
            ];
        }, $columns);
    }

    /**
     * Get indexes for a table
     */
    public function getTableIndexes(string $table): array
    {
        $indexes = DB::select("
            SELECT
                i.relname as index_name,
                a.attname as column_name,
                ix.indisprimary as is_primary,
                ix.indisunique as is_unique
            FROM pg_class t
            JOIN pg_index ix ON t.oid = ix.indrelid
            JOIN pg_class i ON i.oid = ix.indexrelid
            JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(ix.indkey)
            WHERE t.relkind = 'r'
                AND t.relname = ?
            ORDER BY i.relname, a.attnum
        ", [$table]);

        $grouped = [];
        foreach ($indexes as $index) {
            if (!isset($grouped[$index->index_name])) {
                $grouped[$index->index_name] = [
                    'name' => $index->index_name,
                    'columns' => [],
                    'unique' => $index->is_unique,
                    'primary' => $index->is_primary,
                ];
            }
            $grouped[$index->index_name]['columns'][] = $index->column_name;
        }

        return array_values($grouped);
    }

    /**
     * Get foreign keys for a table
     */
    public function getTableForeignKeys(string $table): array
    {
        $foreignKeys = DB::select("
            SELECT
                tc.constraint_name as name,
                kcu.column_name,
                ccu.table_name AS foreign_table,
                ccu.column_name AS foreign_column
            FROM information_schema.table_constraints AS tc
            JOIN information_schema.key_column_usage AS kcu
                ON tc.constraint_name = kcu.constraint_name
                AND tc.table_schema = kcu.table_schema
            JOIN information_schema.constraint_column_usage AS ccu
                ON ccu.constraint_name = tc.constraint_name
                AND ccu.table_schema = tc.table_schema
            WHERE tc.constraint_type = 'FOREIGN KEY'
                AND tc.table_name = ?
        ", [$table]);

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
        return "ALTER TABLE \"{$table}\" DROP COLUMN \"{$column}\"";
    }

    /**
     * Get SQL for renaming a column
     */
    public function getRenameColumnSQL(string $table, string $from, string $to): string
    {
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