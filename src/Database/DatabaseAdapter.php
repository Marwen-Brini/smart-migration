<?php

namespace Flux\Database;

use Illuminate\Support\Facades\DB;

abstract class DatabaseAdapter
{
    /**
     * Get the database driver name
     */
    abstract public function getDriverName(): string;

    /**
     * Check if the adapter supports this connection
     */
    public function supports(): bool
    {
        return DB::connection()->getDriverName() === $this->getDriverName();
    }

    /**
     * Get table structure as CREATE TABLE statement
     */
    abstract public function getTableStructure(string $table): string;

    /**
     * Get all table data
     */
    abstract public function getTableData(string $table): array;

    /**
     * Get table row count
     */
    abstract public function getTableRowCount(string $table): int;

    /**
     * Archive a table by renaming it
     */
    abstract public function archiveTable(string $table, string $newName): bool;

    /**
     * Archive a column by renaming it
     */
    abstract public function archiveColumn(string $table, string $column, string $newName): bool;

    /**
     * Get list of all tables
     */
    abstract public function getAllTables(): array;

    /**
     * Get list of columns for a table
     */
    abstract public function getTableColumns(string $table): array;

    /**
     * Check if a table exists
     */
    public function tableExists(string $table): bool
    {
        return in_array($table, $this->getAllTables());
    }

    /**
     * Check if a column exists
     */
    public function columnExists(string $table, string $column): bool
    {
        return in_array($column, array_column($this->getTableColumns($table), 'name'));
    }

    /**
     * Get indexes for a table
     */
    abstract public function getTableIndexes(string $table): array;

    /**
     * Get foreign keys for a table
     */
    abstract public function getTableForeignKeys(string $table): array;

    /**
     * Estimate operation duration
     */
    public function estimateOperationDuration(string $operation, string $table): int
    {
        $rowCount = $this->getTableRowCount($table);

        // Base estimates in milliseconds
        $estimates = [
            'create_table' => 10,
            'drop_table' => 10,
            'add_column' => 10 + ($rowCount * 0.001),
            'drop_column' => 10 + ($rowCount * 0.001),
            'add_index' => 50 + ($rowCount * 0.01),
            'drop_index' => 20,
            'modify_column' => 20 + ($rowCount * 0.005),
        ];

        return (int) ($estimates[$operation] ?? 100);
    }

    /**
     * Get SQL for creating a table from structure
     */
    abstract public function getCreateTableSQL(string $table, array $structure): string;

    /**
     * Get SQL for dropping a table
     */
    public function getDropTableSQL(string $table): string
    {
        return "DROP TABLE IF EXISTS {$table}";
    }

    /**
     * Get SQL for renaming a table
     */
    abstract public function getRenameTableSQL(string $from, string $to): string;

    /**
     * Get SQL for adding a column
     */
    abstract public function getAddColumnSQL(string $table, string $column, string $type): string;

    /**
     * Get SQL for dropping a column
     */
    abstract public function getDropColumnSQL(string $table, string $column): string;

    /**
     * Get SQL for renaming a column
     */
    abstract public function getRenameColumnSQL(string $table, string $from, string $to): string;

    /**
     * Get SQL for creating an index
     */
    abstract public function getCreateIndexSQL(string $table, string $name, array $columns): string;

    /**
     * Get SQL for dropping an index
     */
    abstract public function getDropIndexSQL(string $table, string $name): string;

    /**
     * Begin a transaction
     */
    public function beginTransaction(): void
    {
        DB::beginTransaction();
    }

    /**
     * Commit a transaction
     */
    public function commit(): void
    {
        DB::commit();
    }

    /**
     * Rollback a transaction
     */
    public function rollback(): void
    {
        DB::rollBack();
    }

    /**
     * Execute a raw SQL statement
     */
    public function execute(string $sql): bool
    {
        return DB::statement($sql);
    }

    /**
     * Quote an identifier (table/column name)
     */
    abstract public function quoteIdentifier(string $identifier): string;
}
