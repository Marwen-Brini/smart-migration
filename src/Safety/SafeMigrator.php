<?php

namespace Flux\Safety;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SafeMigrator extends Migrator
{
    protected array $backupData = [];
    protected array $affectedTables = [];

    /**
     * Run migration with safety features
     */
    public function runSafe(string $file, int $batch, bool $pretend = false): void
    {
        $name = $this->getMigrationName($file);

        // Load the migration file and get the instance
        $migration = $this->resolveMigration($file);

        if ($pretend) {
            $this->pretendToRun($migration, 'up');
            return;
        }

        $this->write("<comment>🔄 Migrating (SAFE):</comment> <info>{$name}</info>");

        try {
            // Analyze and backup affected tables
            $this->analyzeAndBackup($file);

            // Run the migration
            $this->runMigration($migration, 'up');

            // Record migration in database
            $this->repository->log($name, $batch);

            $this->write("<info>✅ Migrated (SAFE):</info>  <comment>{$name}</comment>");

        } catch (\Exception $e) {
            // Attempt to restore backups if needed
            $this->restoreBackups();

            $this->write("<error>❌ Migration failed and rolled back:</error> <comment>{$name}</comment>");
            throw $e;
        }
    }

    /**
     * Undo migration without data loss
     */
    public function undoSafe(string $file): bool
    {
        $name = $this->getMigrationName($file);

        // Load the migration file and get the instance
        $migration = $this->resolveMigration($file);

        $this->write("<comment>↩️ Rolling back (SAFE):</comment> <info>{$name}</info>");

        try {
            // Instead of running down(), we'll rename/archive
            $this->safeRollback($file);

            // Remove from migration log
            $this->repository->delete((object)['migration' => $name]);

            $this->write("<info>✅ Rolled back (SAFE):</info> <comment>{$name}</comment>");

            return true;

        } catch (\Exception $e) {
            $this->write("<error>❌ Rollback failed:</error> <comment>{$name}</comment>");
            throw $e;
        }
    }

    /**
     * Analyze migration and backup affected tables
     */
    protected function analyzeAndBackup(string $file): void
    {
        $content = file_get_contents($file);

        // Find tables that will be affected
        preg_match_all('/Schema::(table|create|drop|dropIfExists)\([\'"](\w+)[\'"]/', $content, $matches);

        foreach ($matches[2] as $table) {
            if (Schema::hasTable($table)) {
                $this->backupTable($table);
                $this->affectedTables[] = $table;
            }
        }
    }

    /**
     * Backup a table's structure and data
     */
    protected function backupTable(string $table): void
    {
        $this->write("<comment>📦 Backing up table:</comment> <info>{$table}</info>");

        // Get table structure
        $columns = DB::select("SHOW CREATE TABLE {$table}");

        // Store structure
        $this->backupData[$table] = [
            'structure' => $columns[0]->{'Create Table'} ?? '',
            'data' => DB::table($table)->get()->toArray(),
            'count' => DB::table($table)->count(),
        ];
    }

    /**
     * Restore backed up tables if needed
     */
    protected function restoreBackups(): void
    {
        foreach ($this->backupData as $table => $backup) {
            $this->write("<comment>🔄 Attempting to restore table:</comment> <info>{$table}</info>");

            try {
                // Drop the potentially corrupted table
                Schema::dropIfExists($table);

                // Recreate table structure
                DB::statement($backup['structure']);

                // Restore data
                if (!empty($backup['data'])) {
                    DB::table($table)->insert($backup['data']);
                }

                $this->write("<info>✅ Restored table:</info> <comment>{$table}</comment>");

            } catch (\Exception $e) {
                $this->write("<error>❌ Failed to restore table:</error> <comment>{$table}</comment>");
            }
        }
    }

    /**
     * Safe rollback that preserves data
     */
    protected function safeRollback(string $file): void
    {
        $content = file_get_contents($file);
        $timestamp = now()->format('Ymd_His');

        // Handle dropped columns by renaming instead
        preg_match_all('/\$table->dropColumn\([\'"](\w+)[\'"]/', $content, $dropColumns);
        foreach ($dropColumns[1] as $column) {
            preg_match('/Schema::table\([\'"](\w+)[\'"].*?' . preg_quote($column) . '/s', $content, $tableMatch);
            if (isset($tableMatch[1])) {
                $table = $tableMatch[1];
                $this->archiveColumn($table, $column, $timestamp);
            }
        }

        // Handle dropped tables by renaming instead
        preg_match_all('/Schema::drop(?:IfExists)?\([\'"](\w+)[\'"]/', $content, $dropTables);
        foreach ($dropTables[1] as $table) {
            $this->archiveTable($table, $timestamp);
        }
    }

    /**
     * Archive a column instead of dropping it
     */
    protected function archiveColumn(string $table, string $column, string $timestamp): void
    {
        if (Schema::hasColumn($table, $column)) {
            $newName = "_archived_{$column}_{$timestamp}";

            $this->write("<comment>📦 Archiving column:</comment> <info>{$table}.{$column}</info> <comment>-></comment> <question>{$newName}</question>");

            DB::statement("ALTER TABLE {$table} RENAME COLUMN {$column} TO {$newName}");
        }
    }

    /**
     * Archive a table instead of dropping it
     */
    protected function archiveTable(string $table, string $timestamp): void
    {
        if (Schema::hasTable($table)) {
            $newName = "_archived_{$table}_{$timestamp}";

            $this->write("<comment>📦 Archiving table:</comment> <info>{$table}</info> <comment>-></comment> <question>{$newName}</question>");

            Schema::rename($table, $newName);
        }
    }

    /**
     * Get tables that would be affected by a migration
     */
    public function getAffectedTables(string $file): array
    {
        $content = file_get_contents($file);
        $tables = [];

        preg_match_all('/Schema::(table|create|drop|dropIfExists|rename)\([\'"](\w+)[\'"]/', $content, $matches);

        foreach ($matches[2] as $table) {
            $tables[] = $table;
        }

        return array_unique($tables);
    }

    /**
     * Estimate the data that would be lost
     */
    public function estimateDataLoss(string $file): array
    {
        $content = file_get_contents($file);
        $loss = [];

        // Check for drop operations
        preg_match_all('/Schema::drop(?:IfExists)?\([\'"](\w+)[\'"]/', $content, $dropTables);
        foreach ($dropTables[1] as $table) {
            if (Schema::hasTable($table)) {
                $loss[] = [
                    'type' => 'table',
                    'name' => $table,
                    'rows' => DB::table($table)->count(),
                ];
            }
        }

        // Check for column drops
        preg_match_all('/\$table->dropColumn\([\'"](\w+)[\'"]/', $content, $dropColumns);
        foreach ($dropColumns[1] as $column) {
            preg_match('/Schema::table\([\'"](\w+)[\'"].*?' . preg_quote($column) . '/s', $content, $tableMatch);
            if (isset($tableMatch[1])) {
                $table = $tableMatch[1];
                if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                    $nonNullCount = DB::table($table)->whereNotNull($column)->count();
                    if ($nonNullCount > 0) {
                        $loss[] = [
                            'type' => 'column',
                            'table' => $table,
                            'name' => $column,
                            'rows' => $nonNullCount,
                        ];
                    }
                }
            }
        }

        return $loss;
    }

    /**
     * Resolve a migration instance from a file
     * Handles both named classes and anonymous classes (Laravel 9+)
     */
    protected function resolveMigration(string $file)
    {
        $migration = $this->files->getRequire($file);

        // Check if it's an anonymous class (Laravel 9+)
        if (is_object($migration)) {
            return $migration;
        }

        // Fall back to the old way for named classes
        $class = $this->getMigrationClass(Str::studly(implode('_', array_slice(explode('_', basename($file, '.php')), 4))));

        return new $class;
    }
}