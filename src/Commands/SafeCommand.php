<?php

namespace Flux\Commands;

use Flux\Analyzers\MigrationAnalyzer;
use Flux\Safety\SafeMigrator;
use Illuminate\Console\Command;

class SafeCommand extends Command
{
    protected $signature = 'migrate:safe
                            {--force : Force the operation to run in production}
                            {--path= : The path(s) to the migrations files}
                            {--pretend : Dump the SQL queries that would be run}
                            {--seed : Run database seeders after migration}
                            {--step : Force migrations to run in steps}';

    protected $description = 'Run migrations with automatic backups and rollback on failure';

    protected SafeMigrator $migrator;

    protected MigrationAnalyzer $analyzer;

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $this->migrator = $this->getMigrator();
        $this->analyzer = new MigrationAnalyzer;

        $this->newLine();
        $this->info('🛡️  <options=bold>Smart Migration - Safe Mode</options=bold>');
        $this->comment('═══════════════════════════════════════════════════════════════════════════════');
        $this->comment('<fg=yellow>ℹ️  Automatic backup and rollback protection enabled</fg=yellow>');
        $this->newLine();

        $files = $this->getMigrationFiles();

        if (empty($files)) {
            $this->info('✅ <fg=green>Nothing to migrate - all migrations are up to date!</fg=green>');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->displayMigrationPlan($files);

        if (! $this->option('pretend') && ! $this->confirm('❓ <fg=cyan>Do you want to proceed with these migrations?</fg=cyan>')) {
            $this->comment('❌ <fg=yellow>Migration cancelled by user</fg=yellow>');

            return self::FAILURE;
        }

        $batch = $this->migrator->getRepository()->getNextBatchNumber();
        $totalFiles = count($files);

        // Use progress bar for multiple migrations
        if ($totalFiles > 1 && ! $this->option('pretend')) {
            $this->newLine();
            $this->info('🚀 <fg=cyan>Running '.$totalFiles.' migrations...</fg=cyan>');
            $this->newLine();

            $bar = $this->output->createProgressBar($totalFiles);
            $bar->setFormat(' <fg=cyan>%current%/%max%</> [<fg=yellow>%bar%</>] <fg=gray>%percent:3s%%</> <fg=blue>%message%</>');
            $bar->setMessage('Initializing...');
            $bar->start();

            foreach ($files as $index => $file) {
                $name = $this->getMigrationName($file);
                $bar->setMessage("Processing: {$name}");

                try {
                    $this->runSafeMigrationQuiet($file, $batch);
                    $bar->advance();
                    $bar->setMessage("✅ Completed: {$name}");
                } catch (\Exception $e) {
                    $bar->finish();
                    $this->newLine(2);
                    $this->error('❌ <fg=red>Migration failed:</fg=red> '.$e->getMessage());
                    throw $e;
                }
            }

            $bar->finish();
            $this->newLine(2);
        } else {
            foreach ($files as $file) {
                $this->runSafeMigration($file, $batch);
            }
        }

        if ($this->option('seed')) {
            $this->newLine();
            $this->info('🌱 <fg=green>Running database seeders...</fg=green>');
            $this->call('db:seed', ['--force' => true]);
        }

        $this->newLine();
        $this->info('✨ <options=bold>All migrations completed successfully!</options=bold>');
        $this->newLine();

        return self::SUCCESS;
    }

    protected function runSafeMigration(string $file, int $batch): void
    {
        $name = $this->getMigrationName($file);

        $this->newLine();
        $this->info("🔄 <options=bold>Processing:</options=bold> <fg=cyan>{$name}</fg=cyan>");
        $this->comment('───────────────────────────────────────────────────────────────────────────────');

        try {
            // Check for potential data loss
            $dataLoss = $this->migrator->estimateDataLoss($file);
            if (! empty($dataLoss)) {
                $this->warn('⚠️  <fg=yellow>Potential data loss detected:</fg=yellow>');
                foreach ($dataLoss as $loss) {
                    if ($loss['type'] === 'table') {
                        $this->warn("     📦 Table '<fg=red>{$loss['name']}</fg=red>' with <fg=yellow>{$loss['rows']}</fg=yellow> rows will be backed up");
                    } else {
                        $this->warn("     📄 Column '<fg=red>{$loss['table']}.{$loss['name']}</fg=red>' with <fg=yellow>{$loss['rows']}</fg=yellow> non-null values will be backed up");
                    }
                }
                $this->newLine();
            }

            // Get affected tables for backup
            $affectedTables = $this->migrator->getAffectedTables($file);
            if (! empty($affectedTables)) {
                $this->comment('📦 <fg=blue>Tables to backup:</fg=blue> '.implode(', ', array_map(fn ($table) => "<fg=cyan>{$table}</fg=cyan>", $affectedTables)));
            }

            // Run migration with safety
            $startTime = microtime(true);

            if ($this->option('pretend')) {
                $this->comment('📝 <fg=magenta>Simulating migration (pretend mode)...</fg=magenta>');
                $this->migrator->pretendToRun($file, 'up');
            } else {
                $this->comment('🔄 <fg=blue>Executing migration with safety protection...</fg=blue>');
                $this->migrator->runSafe($file, $batch, false);
            }

            $duration = round((microtime(true) - $startTime) * 1000);
            $this->info("✅ <fg=green>Completed successfully in</fg=green> <fg=magenta>{$duration}ms</fg=magenta>");

        } catch (\Exception $e) {
            $this->error('❌ <fg=red>Migration failed:</fg=red> '.$e->getMessage());

            if (! $this->option('pretend')) {
                $this->warn('⬅️ <fg=yellow>Migration has been rolled back automatically</fg=yellow>');
                $this->warn('📦 <fg=yellow>Any affected tables have been restored from backup</fg=yellow>');
            }

            throw $e;
        }
    }

    protected function displayMigrationPlan(array $files): void
    {
        $this->newLine();
        $this->info('🗺 <options=bold>Migration Plan</options=bold>');
        $this->comment('═══════════════════════════════════════════════════════════════════════════════');
        $this->newLine();

        foreach ($files as $index => $file) {
            $name = $this->getMigrationName($file);
            $analysis = $this->analyzer->analyze($file);

            $this->comment(sprintf('%d. 📄 <fg=cyan>%s</fg=cyan>', $index + 1, $name));

            $safeCount = $analysis['summary']['safe'];
            $warningCount = $analysis['summary']['warnings'];
            $dangerCount = $analysis['summary']['dangerous'];

            $statusLine = '   ';
            if ($safeCount > 0) {
                $statusLine .= "<fg=green>✅ {$safeCount} safe</fg=green> ";
            }
            if ($warningCount > 0) {
                $statusLine .= "<fg=yellow>⚠️  {$warningCount} warnings</fg=yellow> ";
            }
            if ($dangerCount > 0) {
                $statusLine .= "<fg=red>🔴 {$dangerCount} dangerous</fg=red> ";
            }

            if ($safeCount === 0 && $warningCount === 0 && $dangerCount === 0) {
                $statusLine .= '<fg=gray>ℹ️  No operations detected</fg=gray>';
            }

            $this->line($statusLine);

            if (! empty($analysis['estimated_time'])) {
                $this->line("   <fg=magenta>⏱️  Estimated time: {$analysis['estimated_time']}</fg=magenta>");
            }

            if ($index < count($files) - 1) {
                $this->newLine();
            }
        }

        $this->newLine();
        $totalFiles = count($files);
        $this->comment("ℹ️  <fg=blue>Total migrations to execute: {$totalFiles}</fg=blue>");
        $this->newLine();
    }

    protected function getMigrationFiles(): array
    {
        $path = $this->getMigrationPath();
        $files = $this->migrator->getMigrationFiles($path);

        $ran = $this->migrator->getRepository()->getRan();

        $pending = [];
        foreach ($files as $file) {
            $name = $this->getMigrationName($file);
            if (! in_array($name, $ran)) {
                $pending[] = $file;
            }
        }

        return $pending;
    }

    protected function getMigrator(): SafeMigrator
    {
        $repository = $this->laravel['migration.repository'];
        $filesystem = $this->laravel['files'];

        return new SafeMigrator($repository, $this->laravel['db'], $filesystem, $this->laravel['events']);
    }

    protected function getMigrationPath(): string
    {
        if ($this->option('path')) {
            return $this->laravel->basePath().'/'.$this->option('path');
        }

        return database_path('migrations');
    }

    protected function getMigrationName(string $file): string
    {
        return str_replace('.php', '', basename($file));
    }

    /**
     * Run migration quietly for progress bar mode
     */
    protected function runSafeMigrationQuiet(string $file, int $batch): void
    {
        if ($this->option('pretend')) {
            $this->migrator->pretendToRun($file, 'up');
        } else {
            $this->migrator->runSafe($file, $batch, false);
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
