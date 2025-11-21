<?php

namespace Flux\Http\Api;

use Flux\Monitoring\PerformanceBaseline;
use Flux\Analyzers\MigrationAnalyzer;
use Flux\Safety\SafeMigrator;
use Flux\Safety\SafeMigratorFactory;
use Flux\Support\ArtisanRunner;
use Flux\Database\DatabaseAdapterFactoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Flux\Dashboard\DashboardService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;

class DashboardApiController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected PerformanceBaseline $performanceBaseline,
        protected MigrationAnalyzer $migrationAnalyzer,
        protected ?SafeMigratorFactory $safeMigratorFactory = null,
        protected ?ArtisanRunner $artisanRunner = null
    ) {
    }

    /**
     * Get overall status
     */
    public function status(): JsonResponse
    {
        try {
            $status = $this->dashboardService->getStatus();
            return response()->json($status);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all migrations
     */
    public function migrations(): JsonResponse
    {
        try {
            $migrations = $this->dashboardService->getMigrations();
            return response()->json($migrations);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get migration history
     */
    public function history(): JsonResponse
    {
        try {
            $history = $this->dashboardService->getHistory();
            return response()->json($history);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get database schema
     */
    public function schema(): JsonResponse
    {
        try {
            $schema = $this->dashboardService->getSchema();
            return response()->json($schema);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get drift information
     */
    public function drift(): JsonResponse
    {
        try {
            $drift = $this->dashboardService->getDrift();
            return response()->json($drift);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get snapshots
     */
    public function snapshots(): JsonResponse
    {
        try {
            $snapshots = $this->dashboardService->getSnapshots();
            return response()->json($snapshots);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get metrics
     */
    public function metrics(): JsonResponse
    {
        try {
            $metrics = $this->dashboardService->getMetrics();
            return response()->json($metrics);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate fix migration for drift
     */
    public function generateFixMigration(): JsonResponse
    {
        try {
            $result = $this->dashboardService->generateFixMigration();
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new snapshot
     */
    public function createSnapshot(): JsonResponse
    {
        try {
            $name = request()->input('name');
            $result = $this->dashboardService->createSnapshot($name);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a snapshot
     */
    public function deleteSnapshot(string $name): JsonResponse
    {
        try {
            $result = $this->dashboardService->deleteSnapshot($name);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Run migrations
     */
    public function runMigrations(): JsonResponse
    {
        try {
            $options = request()->all();
            $result = $this->dashboardService->runMigrations($options);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rollback migrations
     */
    public function rollbackMigrations(): JsonResponse
    {
        try {
            $options = request()->all();
            $result = $this->dashboardService->rollbackMigrations($options);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get performance baselines
     */
    public function performanceBaselines(): JsonResponse
    {
        try {
            $baselines = $this->performanceBaseline->getAll();
            return response()->json($baselines);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get performance report
     */
    public function performanceReport(): JsonResponse
    {
        try {
            $report = $this->performanceBaseline->generateReport();
            return response()->json($report);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get performance for specific migration
     */
    public function migrationPerformance(string $migration): JsonResponse
    {
        try {
            $stats = $this->performanceBaseline->getStatistics($migration);

            if (!$stats) {
                return response()->json([
                    'error' => 'No performance data found for this migration'
                ], 404);
            }

            return response()->json($stats);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview a specific migration (plan)
     */
    public function migrationPreview(string $migration): JsonResponse
    {
        try {
            $migrationPath = database_path('migrations');
            $file = $migrationPath . '/' . $migration . '.php';

            if (!file_exists($file)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Migration file not found'
                ], 404);
            }

            $analysis = $this->migrationAnalyzer->analyze($file);

            return response()->json([
                'success' => true,
                'migration' => $migration,
                'analysis' => $analysis,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Run migration in safe mode
     */
    public function runSafeMigration(): JsonResponse
    {
        try {
            $migration = request()->input('migration');

            if (!$migration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Migration name is required'
                ], 400);
            }

            $migrationPath = database_path('migrations');
            $file = $migrationPath . '/' . $migration . '.php';

            if (!file_exists($file)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Migration file not found'
                ], 404);
            }

            // Create SafeMigrator instance using factory
            $safeMigrator = $this->getSafeMigrator();

            $batch = $safeMigrator->getRepository()->getNextBatchNumber();

            // Get data loss estimate
            $dataLoss = $safeMigrator->estimateDataLoss($file);

            // Get affected tables
            $affectedTables = $safeMigrator->getAffectedTables($file);

            $startTime = microtime(true);

            // Run the migration safely
            $safeMigrator->runSafe($file, $batch, false);

            $duration = round((microtime(true) - $startTime) * 1000);

            return response()->json([
                'success' => true,
                'message' => 'Migration executed successfully',
                'migration' => $migration,
                'duration_ms' => $duration,
                'affected_tables' => $affectedTables,
                'data_loss' => $dataLoss,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            ], 500);
        }
    }

    /**
     * Get SafeMigrator instance
     */
    protected function getSafeMigrator(): SafeMigrator
    {
        if ($this->safeMigratorFactory) {
            return $this->safeMigratorFactory->create();
        }

        // Fallback to direct creation for backwards compatibility
        $repository = App::make('migration.repository');
        $filesystem = App::make('files');
        $events = App::make('events');
        $database = App::make('db');

        $safeMigrator = new SafeMigrator($repository, $database, $filesystem, $events);
        $safeMigrator->setAdapterFactory(App::make(DatabaseAdapterFactoryInterface::class));

        return $safeMigrator;
    }

    /**
     * Safe rollback (undo) migration
     */
    public function undoSafeMigration(): JsonResponse
    {
        try {
            $migration = request()->input('migration');
            $step = request()->input('step', 1);

            // Create SafeMigrator instance using factory
            $safeMigrator = $this->getSafeMigrator();
            $repository = $safeMigrator->getRepository();

            // Get migrations to rollback
            $ran = $repository->getRan();

            if (empty($ran)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No migrations to rollback'
                ], 400);
            }

            // If specific migration provided, rollback that one
            if ($migration) {
                $migrationPath = database_path('migrations');
                $file = $migrationPath . '/' . $migration . '.php';

                if (!file_exists($file)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Migration file not found'
                    ], 404);
                }

                $startTime = microtime(true);

                // Perform safe undo
                $safeMigrator->undoSafe($file);

                $duration = round((microtime(true) - $startTime) * 1000);

                return response()->json([
                    'success' => true,
                    'message' => 'Migration rolled back successfully (data archived)',
                    'migration' => $migration,
                    'duration_ms' => $duration,
                ]);
            } else {
                // Rollback last batch/step
                $migrations = $repository->getMigrations($step);
                $rolledBack = [];

                $startTime = microtime(true);

                foreach ($migrations as $migrationObj) {
                    $migrationPath = database_path('migrations');
                    $file = $migrationPath . '/' . $migrationObj->migration . '.php';

                    if (file_exists($file)) {
                        $safeMigrator->undoSafe($file);
                        $rolledBack[] = $migrationObj->migration;
                    }
                }

                $duration = round((microtime(true) - $startTime) * 1000);

                return response()->json([
                    'success' => true,
                    'message' => count($rolledBack) . ' migration(s) rolled back successfully',
                    'rolled_back' => $rolledBack,
                    'duration_ms' => $duration,
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            ], 500);
        }
    }

    /**
     * Get ArtisanRunner instance
     */
    protected function getArtisanRunner(): ArtisanRunner
    {
        if ($this->artisanRunner) {
            return $this->artisanRunner;
        }

        return App::make(ArtisanRunner::class);
    }

    /**
     * Detect migration conflicts
     */
    public function detectConflicts(): JsonResponse
    {
        try {
            $runner = $this->getArtisanRunner();

            // Call the conflicts command with JSON output
            $runner->call('migrate:conflicts', ['--json' => true]);

            $output = $runner->output();
            $result = json_decode($output, true);

            if ($result === null) {
                // If JSON parsing fails, run without JSON to get conflicts
                $runner->call('migrate:conflicts');

                return response()->json([
                    'success' => true,
                    'conflicts' => [],
                    'message' => 'No conflicts detected'
                ]);
            }

            return response()->json([
                'success' => true,
                'conflicts' => $result['conflicts'] ?? [],
                'total' => count($result['conflicts'] ?? []),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detect database differences (migrate:diff)
     */
    public function detectDifferences(): JsonResponse
    {
        try {
            $runner = $this->getArtisanRunner();

            // Run migrate:diff with dry-run to get differences
            $exitCode = $runner->call('migrate:diff', [
                '--dry-run' => true,
                '--force' => true,
            ]);

            $output = $runner->output();

            // Parse differences from output
            $hasDifferences = strpos($output, 'No differences detected') === false &&
                             (strpos($output, 'Found differences') !== false ||
                              strpos($output, 'Total changes:') !== false);

            // Extract difference details
            $tablesAdded = [];
            $tablesRemoved = [];
            $tablesModified = [];

            // Match pattern: "+ Table 'table_name'"
            if (preg_match_all('/\+\s+Table\s+[\'"]?(\w+)[\'"]?/', $output, $matches)) {
                $tablesAdded = $matches[1];
            }

            // Match pattern: "- Table 'table_name'"
            if (preg_match_all('/\-\s+Table\s+[\'"]?(\w+)[\'"]?/', $output, $matches)) {
                $tablesRemoved = $matches[1];
            }

            // Match pattern: "~ Table 'table_name'" or similar modification markers
            if (preg_match_all('/~\s+Table\s+[\'"]?(\w+)[\'"]?/', $output, $matches)) {
                $tablesModified = $matches[1];
            }

            return response()->json([
                'success' => true,
                'has_differences' => $hasDifferences,
                'tables_added' => $tablesAdded,
                'tables_removed' => $tablesRemoved,
                'tables_modified' => $tablesModified,
                'output' => $output,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate migration from differences
     */
    public function generateDiffMigration(): JsonResponse
    {
        try {
            $runner = $this->getArtisanRunner();
            $name = request()->input('name');

            $options = [
                '--force' => true,
            ];

            if ($name) {
                $options['--name'] = $name;
            }

            $exitCode = $runner->call('migrate:diff', $options);
            $output = $runner->output();

            $success = $exitCode === 0;

            // Extract migration file path from output
            $migrationPath = null;
            if (preg_match('/Created:\s+(.+\.php)/', $output, $matches)) {
                $migrationPath = trim($matches[1]);
            }

            return response()->json([
                'success' => $success,
                'message' => $success ? 'Migration generated successfully!' : 'Failed to generate migration',
                'migration_path' => $migrationPath,
                'output' => $output,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test migration on temporary database
     */
    public function testMigration(): JsonResponse
    {
        try {
            $runner = $this->getArtisanRunner();
            $migration = request()->input('migration');
            $withData = request()->input('with_data', false);
            $testRollback = request()->input('test_rollback', true);

            if (!$migration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Migration name is required'
                ], 400);
            }

            // Build command options
            $options = [
                'migration' => $migration,
            ];

            if ($withData) {
                $options['--with-data'] = true;
            }

            if ($testRollback) {
                $options['--rollback'] = true;
            }

            // Run the command and capture output
            $exitCode = $runner->call('migrate:test', $options);
            $output = $runner->output();

            $success = $exitCode === 0 && (
                strpos($output, 'All tests passed') !== false ||
                strpos($output, 'Migration passed') !== false
            );

            // Parse output for structured data
            $tablesAdded = [];
            $columnsAdded = [];
            $duration = null;

            if (preg_match('/Migration passed \((\d+\.?\d*)ms\)/', $output, $matches)) {
                $duration = (float)$matches[1];
            }

            if (preg_match('/Tables added: (.+)/', $output, $matches)) {
                $tablesAdded = array_map('trim', explode(',', $matches[1]));
            }

            return response()->json([
                'success' => $success,
                'message' => $success ? 'Migration test passed!' : 'Migration test failed',
                'migration' => $migration,
                'duration_ms' => $duration,
                'tables_added' => $tablesAdded,
                'columns_added' => $columnsAdded,
                'output' => $output,
                'tested_rollback' => $testRollback,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'output' => $runner->output(),
            ], 500);
        }
    }
}
