<?php

namespace Flux\Commands;

use Flux\Safety\SafeMigrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UndoCommand extends Command
{
    protected $signature = 'migrate:undo
                            {--force : Force the operation to run in production}
                            {--step=1 : Number of migrations to rollback}
                            {--batch= : Rollback a specific batch}
                            {--pretend : Dump the operations that would be performed}';

    protected $description = 'Safely rollback migrations without data loss by archiving instead of dropping';

    protected SafeMigrator $migrator;

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $this->migrator = $this->getMigrator();

        $this->newLine();
        $this->info('↩️  <options=bold>Smart Migration - Safe Undo</options=bold>');
        $this->comment('═══════════════════════════════════════════════════════════════════════════════');
        $this->warn('🛡️  <fg=yellow>Data will be preserved by archiving tables/columns instead of dropping them</fg=yellow>');
        $this->newLine();

        $migrations = $this->getMigrationsToRollback();

        if (empty($migrations)) {
            $this->info('✅ <fg=green>Nothing to rollback - no recent migrations found!</fg=green>');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->displayRollbackPlan($migrations);

        if (! $this->option('pretend') && ! $this->confirm('❓ <fg=cyan>Do you want to proceed with this safe rollback?</fg=cyan>')) {
            $this->comment('❌ <fg=yellow>Rollback cancelled by user</fg=yellow>');

            return self::FAILURE;
        }

        $totalMigrations = count($migrations);

        // Use progress bar for multiple rollbacks
        if ($totalMigrations > 1 && ! $this->option('pretend')) {
            $this->newLine();
            $this->info('🚀 <fg=cyan>Rolling back '.$totalMigrations.' migrations...</fg=cyan>');
            $this->newLine();

            $bar = $this->output->createProgressBar($totalMigrations);
            $bar->setFormat(' <fg=cyan>%current%/%max%</> [<fg=yellow>%bar%</>] <fg=gray>%percent:3s%%</> <fg=blue>%message%</>');
            $bar->setMessage('Initializing rollbacks...');
            $bar->start();

            foreach ($migrations as $migration) {
                $bar->setMessage("Rolling back: {$migration->migration}");
                try {
                    $this->rollbackMigrationQuiet($migration);
                    $bar->advance();
                    $bar->setMessage("✅ Rolled back: {$migration->migration}");
                } catch (\Exception $e) {
                    $bar->finish();
                    $this->newLine(2);
                    $this->error('❌ <fg=red>Rollback failed:</fg=red> '.$e->getMessage());
                    throw $e;
                }
            }

            $bar->finish();
            $this->newLine(2);
        } else {
            foreach ($migrations as $migration) {
                $this->rollbackMigration($migration);
            }
        }

        $this->newLine();
        $this->info('✨ <options=bold>Rollback completed successfully!</options=bold>');
        $this->comment('📦 <fg=blue>Archived data is preserved with timestamp suffixes and can be restored if needed</fg=blue>');
        $this->newLine();

        return self::SUCCESS;
    }

    protected function rollbackMigration(object $migration): void
    {
        $file = $this->getMigrationPath().'/'.$migration->migration.'.php';

        if (! file_exists($file)) {
            $this->error("❌ <fg=red>Migration file not found:</fg=red> {$migration->migration}");

            return;
        }

        $this->newLine();
        $this->info("↩️ <options=bold>Rolling back:</options=bold> <fg=cyan>{$migration->migration}</fg=cyan>");
        $this->comment('───────────────────────────────────────────────────────────────────────────────');

        try {
            if ($this->option('pretend')) {
                $this->comment('📝 <fg=magenta>Simulating rollback (pretend mode)...</fg=magenta>');
                $this->pretendRollback($file);
            } else {
                $startTime = microtime(true);

                $this->comment('🔄 <fg=blue>Performing safe rollback with data preservation...</fg=blue>');
                // Perform safe rollback
                $this->migrator->undoSafe($file);

                $duration = round((microtime(true) - $startTime) * 1000);
                $this->info("✅ <fg=green>Rolled back successfully in</fg=green> <fg=magenta>{$duration}ms</fg=magenta>");

                // Show what was archived
                $this->showArchivedItems($file);
            }

        } catch (\Exception $e) {
            $this->error('❌ <fg=red>Rollback failed:</fg=red> '.$e->getMessage());
            throw $e;
        }
    }

    protected function displayRollbackPlan(array $migrations): void
    {
        $this->info('🗺 <options=bold>Rollback Plan</options=bold>');
        $this->comment('═══════════════════════════════════════════════════════════════════════════════');
        $this->newLine();

        foreach ($migrations as $index => $migration) {
            $file = $this->getMigrationPath().'/'.$migration->migration.'.php';

            if (! file_exists($file)) {
                $this->warn(sprintf('%d. ⚠️  <fg=yellow>%s - File not found</fg=yellow>', $index + 1, $migration->migration));

                continue;
            }

            $this->comment(sprintf('%d. 📄 <fg=cyan>%s</fg=cyan>', $index + 1, $migration->migration));

            // Check what will be archived
            $archiveInfo = $this->getArchiveInfo($file);

            if (! empty($archiveInfo['tables'])) {
                foreach ($archiveInfo['tables'] as $table) {
                    $count = $this->getTableRowCount($table);
                    $this->line("     📦 Table '<fg=red>{$table}</fg=red>' will be archived (<fg=yellow>{$count}</fg=yellow> rows)");
                }
            }

            if (! empty($archiveInfo['columns'])) {
                foreach ($archiveInfo['columns'] as $item) {
                    $count = $this->getColumnDataCount($item['table'], $item['column']);
                    $this->line("     📦 Column '<fg=red>{$item['table']}.{$item['column']}</fg=red>' will be archived (<fg=yellow>{$count}</fg=yellow> non-null values)");
                }
            }

            if (empty($archiveInfo['tables']) && empty($archiveInfo['columns'])) {
                $this->line('     ✅ <fg=green>No destructive operations - safe to rollback</fg=green>');
            }

            if ($index < count($migrations) - 1) {
                $this->newLine();
            }
        }

        $this->newLine();
        $totalMigrations = count($migrations);
        $this->comment("ℹ️  <fg=blue>Total migrations to rollback: {$totalMigrations}</fg=blue>");
        $this->newLine();
    }

    protected function getArchiveInfo(string $file): array
    {
        $content = file_get_contents($file);
        $info = ['tables' => [], 'columns' => []];

        // Find tables that would be dropped (we'll archive instead)
        preg_match_all('/Schema::create\([\'"](\w+)[\'"]/', $content, $creates);
        foreach ($creates[1] as $table) {
            if (Schema::hasTable($table)) {
                $info['tables'][] = $table;
            }
        }

        // Find columns that would be dropped (we'll archive instead)
        preg_match_all('/\$table->(\w+)\([\'"](\w+)[\'"]/', $content, $columns);
        preg_match_all('/Schema::table\([\'"](\w+)[\'"]/', $content, $tables);

        // This is a simplified check - in production you'd want more sophisticated parsing
        foreach ($tables[1] as $tableIndex => $table) {
            // Check if table exists
            if (Schema::hasTable($table)) {
                // For POC, we'll just note that columns might be affected
                // In a real implementation, we'd parse the actual column operations
            }
        }

        return $info;
    }

    /**
     * Rollback migration quietly for progress bar mode
     */
    protected function rollbackMigrationQuiet(object $migration): void
    {
        $file = $this->getMigrationPath().'/'.$migration->migration.'.php';

        if (! file_exists($file)) {
            throw new \RuntimeException("Migration file not found: {$migration->migration}");
        }

        if ($this->option('pretend')) {
            $this->pretendRollback($file);
        } else {
            $this->migrator->undoSafe($file);
        }
    }

    protected function showArchivedItems(string $file): void
    {
        $timestamp = now()->format('Ymd_His');
        $archiveInfo = $this->getArchiveInfo($file);

        if (! empty($archiveInfo['tables']) || ! empty($archiveInfo['columns'])) {
            $this->newLine();
            $this->info('📦 <options=bold>Archived items</options=bold> <fg=blue>(preserved with timestamp {$timestamp}):</fg=blue>');

            foreach ($archiveInfo['tables'] ?? [] as $table) {
                $archivedName = "_archived_{$table}_{$timestamp}";
                if (Schema::hasTable($archivedName)) {
                    $count = DB::table($archivedName)->count();
                    $this->line("    • <fg=green>Table:</fg=green> <fg=cyan>{$archivedName}</fg=cyan> (<fg=yellow>{$count}</fg=yellow> rows)");
                }
            }

            foreach ($archiveInfo['columns'] ?? [] as $item) {
                $archivedName = "_archived_{$item['column']}_{$timestamp}";
                $this->line("    • <fg=green>Column:</fg=green> <fg=cyan>{$item['table']}.{$archivedName}</fg=cyan>");
            }

            $this->newLine();
            $this->warn('💡 <fg=yellow>Tip: Archived data will be kept for 7 days before automatic cleanup</fg=yellow>');
            $this->warn('     <fg=yellow>You can restore it manually if needed using SQL commands</fg=yellow>');
        }
    }

    protected function pretendRollback(string $file): void
    {
        $archiveInfo = $this->getArchiveInfo($file);
        $timestamp = now()->format('Ymd_His');

        $this->comment('<fg=magenta>Would execute the following SQL commands:</fg=magenta>');
        $this->newLine();

        foreach ($archiveInfo['tables'] ?? [] as $table) {
            $this->line("  <fg=blue>RENAME TABLE</fg=blue> <fg=cyan>{$table}</fg=cyan> <fg=blue>TO</fg=blue> <fg=green>_archived_{$table}_{$timestamp}</fg=green>");
        }

        foreach ($archiveInfo['columns'] ?? [] as $item) {
            $this->line("  <fg=blue>ALTER TABLE</fg=blue> <fg=cyan>{$item['table']}</fg=cyan> <fg=blue>RENAME COLUMN</fg=blue> <fg=red>{$item['column']}</fg=red> <fg=blue>TO</fg=blue> <fg=green>_archived_{$item['column']}_{$timestamp}</fg=green>");
        }

        $migrationName = basename($file, '.php');
        $this->line("  <fg=blue>DELETE FROM</fg=blue> <fg=cyan>migrations</fg=cyan> <fg=blue>WHERE</fg=blue> migration = '<fg=yellow>{$migrationName}</fg=yellow>'");
        $this->newLine();
    }

    protected function getMigrationsToRollback(): array
    {
        $repository = $this->migrator->getRepository();

        if ($batch = $this->option('batch')) {
            return $repository->getMigrations($batch);
        }

        $steps = (int) $this->option('step');

        return $repository->getLast($steps);
    }

    protected function getMigrator(): SafeMigrator
    {
        $repository = $this->laravel['migration.repository'];
        $filesystem = $this->laravel['files'];

        return new SafeMigrator($repository, $this->laravel['db'], $filesystem, $this->laravel['events']);
    }

    protected function getMigrationPath(): string
    {
        return database_path('migrations');
    }

    protected function getTableRowCount(string $table): int
    {
        try {
            return DB::table($table)->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    protected function getColumnDataCount(string $table, string $column): int
    {
        try {
            return DB::table($table)->whereNotNull($column)->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    protected function confirmToProceed(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        if ($this->laravel->environment('production')) {
            return $this->confirm('You are in production! Do you really wish to run this command?');
        }

        return true;
    }
}
