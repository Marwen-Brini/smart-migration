<?php

namespace Flux\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Flux\Analyzers\MigrationAnalyzer;

class PlanCommand extends Command
{
    protected $signature = 'migrate:plan
                            {migration? : The specific migration file to analyze}
                            {--path= : The path(s) to the migrations files}
                            {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}';

    protected $description = 'Preview what a migration will do before running it';

    protected MigrationAnalyzer $analyzer;

    public function __construct()
    {
        parent::__construct();
        $this->analyzer = new MigrationAnalyzer();
    }

    public function handle(): int
    {
        $this->newLine();
        $this->info('🔍 <options=bold>Smart Migration Plan Analysis</options=bold>');
        $this->comment('═══════════════════════════════════════════════════════════════════════════════');
        $this->newLine();

        $migrations = $this->getMigrationsToAnalyze();

        if (empty($migrations)) {
            $this->warn('⚠️  No pending migrations found.');
            $this->newLine();
            return self::SUCCESS;
        }

        $this->info('📋 Found ' . count($migrations) . ' migration(s) to analyze:');
        $this->newLine();

        foreach ($migrations as $migration) {
            $this->analyzeMigration($migration);
        }

        $this->newLine();
        $this->info('✅ <options=bold>Analysis completed successfully!</options=bold>');
        $this->newLine();

        return self::SUCCESS;
    }

    protected function getMigrationsToAnalyze(): array
    {
        $migrationName = $this->argument('migration');
        $path = $this->getMigrationPath();

        if ($migrationName) {
            $file = $path . '/' . $migrationName . '.php';
            if (!File::exists($file)) {
                $this->error("❌ Migration file not found: <fg=red>{$migrationName}</fg=red>");
                return [];
            }
            return [$file];
        }

        $files = File::glob($path . '/*.php');
        $pending = [];

        foreach ($files as $file) {
            $name = $this->getMigrationName($file);
            if (!$this->isMigrationRun($name)) {
                $pending[] = $file;
            }
        }

        return $pending;
    }

    protected function analyzeMigration(string $file): void
    {
        $name = $this->getMigrationName($file);
        $this->newLine();
        $this->info("📄 <options=bold>Migration:</options=bold> <fg=cyan>{$name}</fg=cyan>");
        $this->comment('───────────────────────────────────────────────────────────────────────────────');
        $this->newLine();

        try {
            $analysis = $this->analyzer->analyze($file);

            if (empty($analysis['operations'])) {
                $this->comment('   ℹ️  No operations detected in this migration');
            } else {
                foreach ($analysis['operations'] as $operation) {
                    $this->displayOperation($operation);
                }
            }

            $this->displaySummary($analysis);

        } catch (\Exception $e) {
            $this->error("❌ <fg=red>Failed to analyze migration:</fg=red> " . $e->getMessage());
        }
    }

    protected function displayOperation(array $operation): void
    {
        $icon = $this->getRiskIcon($operation['risk']);
        $risk = $this->getRiskLabel($operation['risk']);
        $riskColor = $this->getRiskColor($operation['risk']);

        $this->line("   {$icon} <{$riskColor}>{$risk}</{$riskColor}> │ {$operation['description']}");

        if (!empty($operation['sql'])) {
            $this->line("             │ <fg=gray>SQL:</fg=gray> <fg=blue>{$operation['sql']}</fg=blue>");
        }

        if (!empty($operation['impact'])) {
            $this->line("             │ <fg=gray>Impact:</fg=gray> {$operation['impact']}");
        }

        if (!empty($operation['duration'])) {
            $this->line("             │ <fg=gray>Duration:</fg=gray> <fg=magenta>{$operation['duration']}</fg=magenta>");
        }

        $this->newLine();
    }

    protected function displaySummary(array $analysis): void
    {
        $this->comment('┌─ <options=bold>📊 Summary</options=bold> ─────────────────────────────────────────────────────────┐');
        $this->comment('│                                                                              │');

        if ($analysis['summary']['safe'] > 0) {
            $this->comment("│   ✅ <fg=green>{$analysis['summary']['safe']} safe operation(s)</fg=green>" . str_repeat(' ', 50 - strlen($analysis['summary']['safe'])) . "│");
        }

        if ($analysis['summary']['warnings'] > 0) {
            $this->comment("│   ⚠️  <fg=yellow>{$analysis['summary']['warnings']} warning(s)</fg=yellow>" . str_repeat(' ', 52 - strlen($analysis['summary']['warnings'])) . "│");
        }

        if ($analysis['summary']['dangerous'] > 0) {
            $this->comment("│   🔴 <fg=red>{$analysis['summary']['dangerous']} dangerous operation(s)</fg=red>" . str_repeat(' ', 44 - strlen($analysis['summary']['dangerous'])) . "│");
        }

        if (!empty($analysis['estimated_time'])) {
            $timeStr = "Estimated total time: {$analysis['estimated_time']}";
            $this->comment("│   ⏱️  <fg=magenta>{$timeStr}</fg=magenta>" . str_repeat(' ', 60 - strlen($timeStr)) . "│");
        }

        $this->comment('│                                                                              │');
        $this->comment('└──────────────────────────────────────────────────────────────────────────┘');
        $this->newLine();
    }

    protected function getRiskIcon(string $risk): string
    {
        return match ($risk) {
            'safe' => '✅',
            'warning' => '⚠️',
            'danger' => '🔴',
            default => '❓',
        };
    }

    protected function getRiskLabel(string $risk): string
    {
        return match ($risk) {
            'safe' => 'SAFE   ',
            'warning' => 'WARNING',
            'danger' => 'DANGER ',
            default => 'UNKNOWN',
        };
    }

    protected function getRiskColor(string $risk): string
    {
        return match ($risk) {
            'safe' => 'fg=green',
            'warning' => 'fg=yellow',
            'danger' => 'fg=red',
            default => 'fg=gray',
        };
    }

    protected function getMigrationPath(): string
    {
        if ($this->option('path')) {
            return $this->laravel->basePath() . '/' . $this->option('path');
        }

        return database_path('migrations');
    }

    protected function getMigrationName(string $file): string
    {
        return str_replace('.php', '', basename($file));
    }

    protected function isMigrationRun(string $name): bool
    {
        return DB::table('migrations')
            ->where('migration', $name)
            ->exists();
    }
}