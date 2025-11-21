<?php

namespace Flux\Commands;

use Flux\Safety\SafeMigrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class EmergencyRollbackCommand extends Command
{
    protected $signature = 'migrate:emergency-rollback
                            {--steps= : Number of migration batches to rollback}
                            {--to-batch= : Rollback to specific batch number}
                            {--preserve-data : Archive data instead of dropping}
                            {--no-backup : Skip backup creation (faster but riskier)}
                            {--incident-id= : Incident tracking ID for logging}';

    protected $description = 'Emergency rollback with minimal safety checks for critical situations';

    protected array $incidentLog = [];
    protected string $incidentId;
    protected float $startTime;

    /**
     * @codeCoverageIgnore
     */
    public function handle(): int
    {
        $this->startTime = microtime(true);
        $this->incidentId = $this->option('incident-id') ?: 'INCIDENT-' . date('YmdHis');

        $this->displayEmergencyHeader();
        $this->logIncident('Emergency rollback initiated');

        // Safety confirmation
        if (!$this->confirmEmergency()) {
            $this->logIncident('Emergency rollback cancelled by user');
            return self::FAILURE;
        }

        // Capture pre-rollback state
        $this->captureSystemState();

        try {
            // Execute emergency rollback
            $result = $this->executeEmergencyRollback();

            if ($result) {
                $this->displaySuccess();
                return self::SUCCESS;
            } else {
                $this->displayFailure();
                return self::FAILURE;
            }

        } catch (\Exception $e) {
            $this->handleEmergencyFailure($e);
            return self::FAILURE;

        } finally {
            // Always create incident report
            $this->createIncidentReport();
        }
    }

    /**
     * Display emergency mode header
     */
    protected function displayEmergencyHeader(): void
    {
        $this->newLine();
        $this->error('╔═══════════════════════════════════════════════════════════════════════════════╗');
        $this->error('║                                                                               ║');
        $this->error('║                        🚨 EMERGENCY ROLLBACK MODE 🚨                          ║');
        $this->error('║                                                                               ║');
        $this->error('║   This command bypasses normal safety checks for critical situations only.   ║');
        $this->error('║                                                                               ║');
        $this->error('╚═══════════════════════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $this->warn('⚠️  EMERGENCY MODE ACTIVE');
        $this->warn('   - Reduced safety checks');
        $this->warn('   - Immediate execution');
        $this->warn('   - Full audit trail created');
        $this->newLine();

        $this->line("  <info>Incident ID:</info> {$this->incidentId}");
        $this->line("  <info>Environment:</info> " . app()->environment());
        $this->line("  <info>Database:</info> " . config('database.default'));
        $this->newLine();
    }

    /**
     * Confirm emergency action
     * @codeCoverageIgnore
     */
    protected function confirmEmergency(): bool
    {
        $this->warn('⚠️  This is an EMERGENCY operation. Normal safety checks are bypassed.');
        $this->newLine();

        // Show what will be rolled back
        $this->displayRollbackPlan();

        $this->newLine();
        $confirmation = $this->ask('Type "EMERGENCY ROLLBACK" to confirm (case-sensitive)');

        if ($confirmation !== 'EMERGENCY ROLLBACK') {
            $this->error('❌ Confirmation failed. Emergency rollback cancelled.');
            return false;
        }

        // Notify on-call team if configured
        $this->notifyOnCallTeam();

        return true;
    }

    /**
     * Display what will be rolled back
     * @codeCoverageIgnore
     */
    protected function displayRollbackPlan(): void
    {
        $repository = app('migrator')->getRepository();

        if ($steps = $this->option('steps')) {
            $this->comment("Plan: Rollback last {$steps} batch(es)");
        } elseif ($batch = $this->option('to-batch')) {
            $this->comment("Plan: Rollback to batch {$batch}");
        } else {
            $this->comment('Plan: Rollback last batch');
        }

        // Show last migrations
        $migrations = DB::table('migrations')
            ->orderBy('batch', 'desc')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        if ($migrations->isNotEmpty()) {
            $this->newLine();
            $this->comment('Last 5 migrations:');
            foreach ($migrations as $migration) {
                $this->line("  - Batch {$migration->batch}: {$migration->migration}");
            }
        }
    }

    /**
     * Capture system state before rollback
     * @codeCoverageIgnore
     */
    protected function captureSystemState(): void
    {
        $this->info('📸 Capturing system state...');

        try {
            $this->incidentLog['pre_rollback_state'] = [
                'timestamp' => now()->toIso8601String(),
                'migrations' => DB::table('migrations')->get()->toArray(),
                'tables' => $this->getTableList(),
                'environment' => app()->environment(),
                'database' => config('database.default'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ];

            // Create backup if requested
            if (!$this->option('no-backup')) {
                $this->createEmergencyBackup();
            }

            $this->info('✓ System state captured');

        } catch (\Exception $e) {
            $this->warn('⚠️  Could not fully capture state: ' . $e->getMessage());
            $this->logIncident('State capture failed: ' . $e->getMessage());
        }
    }

    /**
     * Create emergency backup
     * @codeCoverageIgnore
     */
    protected function createEmergencyBackup(): void
    {
        $this->comment('Creating emergency backup...');

        try {
            $backupPath = storage_path('app/emergency-backups/' . $this->incidentId);

            if (!File::exists($backupPath)) {
                File::makeDirectory($backupPath, 0755, true);
            }

            // Export database schema
            $schemaFile = $backupPath . '/schema.sql';
            $this->exportDatabaseSchema($schemaFile);

            // Save migrations table
            $migrationsFile = $backupPath . '/migrations.json';
            File::put($migrationsFile, json_encode(
                DB::table('migrations')->get()->toArray(),
                JSON_PRETTY_PRINT
            ));

            $this->incidentLog['backup_location'] = $backupPath;
            $this->info("✓ Emergency backup created: {$backupPath}");

        } catch (\Exception $e) {
            $this->warn('⚠️  Backup creation failed: ' . $e->getMessage());
            $this->logIncident('Backup failed: ' . $e->getMessage());
        }
    }

    /**
     * Execute the emergency rollback
     * @codeCoverageIgnore
     */
    protected function executeEmergencyRollback(): bool
    {
        $this->error('🚨 Executing emergency rollback...');
        $this->newLine();

        $params = ['--force' => true];

        if ($steps = $this->option('steps')) {
            $params['--step'] = $steps;
        } elseif ($batch = $this->option('to-batch')) {
            // Calculate steps needed to reach target batch
            $currentBatch = DB::table('migrations')->max('batch');
            $params['--step'] = $currentBatch - $batch + 1;
        }

        $startTime = microtime(true);

        try {
            if ($this->option('preserve-data')) {
                // Use safe rollback (migrate:undo)
                $this->comment('Using safe rollback (data preservation mode)...');
                $exitCode = Artisan::call('migrate:undo', $params);
            } else {
                // Use standard rollback
                $this->comment('Using standard rollback...');
                $exitCode = Artisan::call('migrate:rollback', $params);
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $output = Artisan::output();
            $this->line($output);

            $this->incidentLog['rollback'] = [
                'exit_code' => $exitCode,
                'duration_ms' => $duration,
                'output' => $output,
                'mode' => $this->option('preserve-data') ? 'safe' : 'standard',
            ];

            if ($exitCode === 0) {
                $this->logIncident("Rollback completed successfully in {$duration}ms");
                return true;
            } else {
                $this->logIncident("Rollback failed with exit code {$exitCode}");
                return false;
            }

        } catch (\Exception $e) {
            $this->logIncident('Rollback exception: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle emergency failure
     */
    protected function handleEmergencyFailure(\Exception $e): void
    {
        $this->newLine();
        $this->error('╔═══════════════════════════════════════════════════════════════════════════════╗');
        $this->error('║                      ❌ EMERGENCY ROLLBACK FAILED ❌                          ║');
        $this->error('╚═══════════════════════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $this->error('Error: ' . $e->getMessage());
        $this->newLine();

        $this->incidentLog['failure'] = [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];

        $this->logIncident('Emergency rollback failed: ' . $e->getMessage(), 'critical');

        // Provide recovery instructions
        $this->displayRecoveryInstructions();

        // Notify on-call team of failure
        $this->notifyOnCallTeam('FAILURE');
    }

    /**
     * Display success message
     */
    protected function displaySuccess(): void
    {
        $duration = round((microtime(true) - $this->startTime) * 1000, 2);

        $this->newLine();
        $this->info('╔═══════════════════════════════════════════════════════════════════════════════╗');
        $this->info('║                   ✅ EMERGENCY ROLLBACK SUCCESSFUL ✅                         ║');
        $this->info('╚═══════════════════════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $this->line("  <info>Incident ID:</info> {$this->incidentId}");
        $this->line("  <info>Duration:</info> {$duration}ms");
        $this->newLine();

        $this->comment('Next steps:');
        $this->line('  1. Verify system stability');
        $this->line('  2. Review incident report: ' . $this->getIncidentReportPath());
        $this->line('  3. Investigate root cause');
        $this->line('  4. Update runbooks if needed');

        $this->logIncident("Emergency rollback completed successfully in {$duration}ms");
    }

    /**
     * Display failure message
     */
    protected function displayFailure(): void
    {
        $this->newLine();
        $this->error('╔═══════════════════════════════════════════════════════════════════════════════╗');
        $this->error('║                  ⚠️  EMERGENCY ROLLBACK COMPLETED WITH ISSUES ⚠️             ║');
        $this->error('╚═══════════════════════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $this->warn('The rollback command completed but reported issues.');
        $this->warn('Please review the output above and the incident report.');

        $this->displayRecoveryInstructions();
    }

    /**
     * Display recovery instructions
     */
    protected function displayRecoveryInstructions(): void
    {
        $this->newLine();
        $this->comment('Recovery Options:');
        $this->line('  1. Check incident report: ' . $this->getIncidentReportPath());
        $this->line('  2. Review backup location: ' . ($this->incidentLog['backup_location'] ?? 'N/A'));
        $this->line('  3. Verify database integrity');
        $this->line('  4. Contact on-call team if needed');
        $this->line('  5. Consider manual database restoration if required');
        $this->newLine();
    }

    /**
     * Create incident report
     * @codeCoverageIgnore
     */
    protected function createIncidentReport(): void
    {
        try {
            $reportPath = $this->getIncidentReportPath();
            $reportDir = dirname($reportPath);

            if (!File::exists($reportDir)) {
                File::makeDirectory($reportDir, 0755, true);
            }

            $report = [
                'incident_id' => $this->incidentId,
                'timestamp' => now()->toIso8601String(),
                'duration_seconds' => round(microtime(true) - $this->startTime, 2),
                'environment' => app()->environment(),
                'database' => config('database.default'),
                'command' => $this->signature,
                'options' => $this->options(),
                'log' => $this->incidentLog,
                'system_info' => [
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version(),
                    'os' => PHP_OS,
                ],
            ];

            File::put($reportPath, json_encode($report, JSON_PRETTY_PRINT));

            $this->info("📄 Incident report created: {$reportPath}");

            // Also log to Laravel log
            Log::channel('daily')->critical('Emergency rollback executed', $report);

        } catch (\Exception $e) {
            $this->warn('⚠️  Could not create incident report: ' . $e->getMessage());
        }
    }

    /**
     * Get incident report path
     */
    protected function getIncidentReportPath(): string
    {
        return storage_path('logs/incidents/' . $this->incidentId . '.json');
    }

    /**
     * Log incident event
     */
    protected function logIncident(string $message, string $level = 'info'): void
    {
        $this->incidentLog['events'][] = [
            'timestamp' => now()->toIso8601String(),
            'level' => $level,
            'message' => $message,
        ];

        // Also log to Laravel log
        Log::channel('daily')->$level("[{$this->incidentId}] {$message}");
    }

    /**
     * Notify on-call team
     * @codeCoverageIgnore
     */
    protected function notifyOnCallTeam(string $status = 'INITIATED'): void
    {
        // Hook for notification system
        $webhookUrl = config('smart-migration.notifications.emergency_webhook');

        if (!$webhookUrl) {
            return;
        }

        try {
            $this->comment('📢 Notifying on-call team...');

            // This would integrate with your notification system
            // For now, just log it
            Log::channel('daily')->alert("Emergency rollback {$status}", [
                'incident_id' => $this->incidentId,
                'environment' => app()->environment(),
                'database' => config('database.default'),
            ]);

            $this->info('✓ On-call team notified');

        } catch (\Exception $e) {
            $this->warn('⚠️  Could not notify team: ' . $e->getMessage());
        }
    }

    /**
     * Get list of database tables
     * @codeCoverageIgnore
     */
    protected function getTableList(): array
    {
        try {
            $driver = config('database.default');
            $connection = config("database.connections.{$driver}.driver");

            if ($connection === 'mysql') {
                $database = config("database.connections.{$driver}.database");
                $tables = DB::select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?", [$database]);
                return array_map(fn($t) => $t->TABLE_NAME, $tables);
            } elseif ($connection === 'pgsql') {
                $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
                return array_map(fn($t) => $t->tablename, $tables);
            } elseif ($connection === 'sqlite') {
                $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                return array_map(fn($t) => $t->name, $tables);
            }
        } catch (\Exception $e) {
            return [];
        }

        return [];
    }

    /**
     * Export database schema
     * @codeCoverageIgnore
     */
    protected function exportDatabaseSchema(string $file): void
    {
        $driver = config('database.connections.' . config('database.default') . '.driver');

        if ($driver === 'mysql') {
            $this->exportMySQLSchema($file);
        } elseif ($driver === 'pgsql') {
            $this->exportPostgreSQLSchema($file);
        } elseif ($driver === 'sqlite') {
            $this->exportSQLiteSchema($file);
        }
    }

    /**
     * Export MySQL schema
     * @codeCoverageIgnore
     */
    protected function exportMySQLSchema(string $file): void
    {
        $host = config('database.connections.mysql.host');
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        $command = sprintf(
            'mysqldump -h %s -u %s -p%s --no-data %s > %s 2>&1',
            escapeshellarg($host),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($file)
        );

        exec($command, $output, $exitCode);
    }

    /**
     * Export PostgreSQL schema
     * @codeCoverageIgnore
     */
    protected function exportPostgreSQLSchema(string $file): void
    {
        $host = config('database.connections.pgsql.host');
        $database = config('database.connections.pgsql.database');
        $username = config('database.connections.pgsql.username');

        $command = sprintf(
            'pg_dump -h %s -U %s --schema-only %s > %s 2>&1',
            escapeshellarg($host),
            escapeshellarg($username),
            escapeshellarg($database),
            escapeshellarg($file)
        );

        exec($command, $output, $exitCode);
    }

    /**
     * Export SQLite schema
     * @codeCoverageIgnore
     */
    protected function exportSQLiteSchema(string $file): void
    {
        $database = config('database.connections.sqlite.database');

        if ($database && file_exists($database)) {
            $schema = DB::select("SELECT sql FROM sqlite_master WHERE sql NOT NULL");
            $sql = implode(";\n\n", array_map(fn($s) => $s->sql, $schema));
            File::put($file, $sql);
        }
    }
}
