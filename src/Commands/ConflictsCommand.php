<?php

namespace Flux\Commands;

use Flux\Analyzers\MigrationAnalyzer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Collection;

class ConflictsCommand extends Command
{
    protected $signature = 'migrate:conflicts
                            {--path= : Path to migrations directory}
                            {--json : Output as JSON}
                            {--auto-resolve : Attempt automatic resolution}';

    protected $description = 'Detect and resolve migration conflicts';

    protected MigrationAnalyzer $analyzer;
    protected array $conflicts = [];

    public function __construct()
    {
        parent::__construct();
        $this->analyzer = app(MigrationAnalyzer::class);
    }

    public function handle(): int
    {
        $this->displayHeader();

        $path = $this->option('path') ?: database_path('migrations');
        $migrations = $this->getMigrations($path);

        if ($migrations->isEmpty()) {
            $this->warn('No migrations found.');
            return self::SUCCESS;
        }

        // Analyze migrations for conflicts
        $this->analyzeConflicts($migrations);

        if ($this->option('json')) {
            $this->outputJson();
            return empty($this->conflicts) ? self::SUCCESS : self::FAILURE;
        }

        // Display results
        if (empty($this->conflicts)) {
            $this->displayNoConflicts();
        } else {
            $this->displayConflicts();

            if ($this->option('auto-resolve')) {
                $this->attemptAutoResolve();
            } else {
                $this->displayResolutionOptions();
            }
        }

        return empty($this->conflicts) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Display command header
     */
    protected function displayHeader(): void
    {
        $this->info('🔍 Migration Conflict Detection');
        $this->comment('═══════════════════════════════════════════════════════════════════════════════');
        $this->newLine();
    }

    /**
     * Get all migration files
     */
    protected function getMigrations(string $path): Collection
    {
        $migrator = app('migrator');
        $files = $migrator->getMigrationFiles($path);

        return collect($files)->map(function ($file, $name) use ($path) {
            // $file already contains the full path from getMigrationFiles()
            return [
                'name' => $name,
                'file' => $file,
                'path' => $file,
                'timestamp' => $this->extractTimestamp($name),
                'operations' => $this->extractOperations($file),
            ];
        })->values();
    }

    /**
     * Extract timestamp from migration name
     */
    protected function extractTimestamp(string $name): int
    {
        if (preg_match('/^(\d{4})_(\d{2})_(\d{2})_(\d{6})_/', $name, $matches)) {
            return (int) ($matches[1] . $matches[2] . $matches[3] . $matches[4]);
        }
        return 0;
    }

    /**
     * Extract operations from migration file
     */
    protected function extractOperations(string $filePath): array
    {
        if (!File::exists($filePath)) {
            return [];
        }

        $content = File::get($filePath);
        $operations = [];

        // Extract table operations using regex
        $patterns = [
            'create' => '/Schema::create\([\'"]([a-z_]+)[\'"]/i',
            'table' => '/Schema::table\([\'"]([a-z_]+)[\'"]/i',
            'drop' => '/Schema::drop(?:IfExists)?\([\'"]([a-z_]+)[\'"]/i',
            'rename' => '/Schema::rename\([\'"]([a-z_]+)[\'"].*?[\'"]([a-z_]+)[\'"]/i',
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $index => $table) {
                    $operations[] = [
                        'type' => $type,
                        'table' => $table,
                        'target' => $matches[2][$index] ?? null, // For renames
                    ];
                }
            }
        }

        // Extract column operations
        $columnPatterns = [
            'add_column' => '/\$table->(?:big)?(?:integer|string|text|boolean|timestamp|date|json|decimal)\([\'"]([a-z_]+)[\'"]/i',
            'drop_column' => '/\$table->dropColumn\(\[?[\'"]([a-z_]+)[\'"]?\]?\)/i',
            'rename_column' => '/\$table->renameColumn\([\'"]([a-z_]+)[\'"].*?[\'"]([a-z_]+)[\'"]/i',
        ];

        foreach ($columnPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $index => $column) {
                    $operations[] = [
                        'type' => $type,
                        'column' => $column,
                        'target' => $matches[2][$index] ?? null,
                    ];
                }
            }
        }

        return $operations;
    }

    /**
     * Analyze migrations for conflicts
     */
    protected function analyzeConflicts(Collection $migrations): void
    {
        $tableOperations = [];

        // Group operations by table
        foreach ($migrations as $migration) {
            foreach ($migration['operations'] as $operation) {
                $table = $operation['table'] ?? null;
                if (!$table) {
                    continue;
                }

                if (!isset($tableOperations[$table])) {
                    $tableOperations[$table] = [];
                }

                $tableOperations[$table][] = [
                    'migration' => $migration['name'],
                    'timestamp' => $migration['timestamp'],
                    'operation' => $operation,
                ];
            }
        }

        // DEBUG: Show what operations were found
        if ($this->option('verbose')) {
            $this->comment('Found operations for tables: ' . implode(', ', array_keys($tableOperations)));
            foreach ($tableOperations as $table => $ops) {
                $this->line("  {$table}: " . count($ops) . " operations");
            }
        }

        // Detect conflicts
        foreach ($tableOperations as $table => $operations) {
            $this->detectTableConflicts($table, $operations);
        }
    }

    /**
     * Detect conflicts for a specific table
     */
    protected function detectTableConflicts(string $table, array $operations): void
    {
        // Sort operations by timestamp
        usort($operations, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);

        $hasCreate = false;
        $hasDrop = false;

        foreach ($operations as $index => $op) {
            // Conflict 1: Multiple creates
            if ($op['operation']['type'] === 'create') {
                if ($hasCreate) {
                    $this->addConflict('duplicate_create', $table, $op, $operations);
                }
                $hasCreate = true;
            }

            // Conflict 2: Drop before create
            if ($op['operation']['type'] === 'drop') {
                $hasDrop = true;
            }

            // Conflict 3: Modify table before it's created
            if (!$hasCreate && in_array($op['operation']['type'], ['table', 'rename'])) {
                $this->addConflict('modify_before_create', $table, $op, $operations);
            }

            // Conflict 4: Create after drop (should use create_or_replace)
            if ($hasDrop && $op['operation']['type'] === 'create') {
                $this->addConflict('create_after_drop', $table, $op, $operations);
            }

            // Conflict 5: Multiple migrations modifying same table in same batch
            if ($op['operation']['type'] === 'table') {
                $sameBatchModifications = array_filter($operations, function ($other) use ($op, $index, $operations) {
                    return $other['operation']['type'] === 'table'
                        && abs($other['timestamp'] - $op['timestamp']) < 100 // Within 1 minute
                        && $other !== $operations[$index];
                });

                if (count($sameBatchModifications) > 0) {
                    $this->addConflict('concurrent_modifications', $table, $op, $sameBatchModifications);
                }
            }
        }
    }

    /**
     * Add a conflict to the list
     */
    protected function addConflict(string $type, string $table, array $operation, array $relatedOps): void
    {
        $this->conflicts[] = [
            'type' => $type,
            'table' => $table,
            'migration' => $operation['migration'],
            'operation' => $operation['operation'],
            'related_operations' => $relatedOps,
        ];
    }

    /**
     * Display when no conflicts found
     */
    protected function displayNoConflicts(): void
    {
        $this->info('✅ No migration conflicts detected!');
        $this->newLine();
        $this->comment('All migrations appear to be compatible and well-ordered.');
        $this->comment('═══════════════════════════════════════════════════════════════════════════════');
    }

    /**
     * Display detected conflicts
     */
    protected function displayConflicts(): void
    {
        $this->warn('⚠️  Detected ' . count($this->conflicts) . ' potential conflict(s):');
        $this->newLine();

        foreach ($this->conflicts as $index => $conflict) {
            $this->displayConflict($index + 1, $conflict);
        }

        $this->comment('═══════════════════════════════════════════════════════════════════════════════');
    }

    /**
     * Display a single conflict
     */
    protected function displayConflict(int $number, array $conflict): void
    {
        $this->comment("Conflict #{$number}: " . $this->getConflictTitle($conflict['type']));
        $this->line('────────────────────────────────────────────────────────────────────────────');
        $this->line("  <info>Table:</info> {$conflict['table']}");
        $this->line("  <info>Migration:</info> {$conflict['migration']}");
        $this->line("  <info>Operation:</info> {$conflict['operation']['type']}");

        if (!empty($conflict['related_operations'])) {
            $this->line("  <info>Related migrations:</info>");
            foreach ($conflict['related_operations'] as $related) {
                $this->line("    - {$related['migration']} ({$related['operation']['type']})");
            }
        }

        $this->newLine();
        $this->line("  <comment>Impact:</comment> " . $this->getConflictImpact($conflict['type']));
        $this->line("  <comment>Recommendation:</comment> " . $this->getConflictRecommendation($conflict['type']));
        $this->newLine();
    }

    /**
     * Get conflict title
     */
    protected function getConflictTitle(string $type): string
    {
        return match ($type) {
            'duplicate_create' => 'Duplicate Table Creation',
            'modify_before_create' => 'Modifying Table Before Creation',
            'create_after_drop' => 'Creating Table After Drop',
            'concurrent_modifications' => 'Concurrent Table Modifications',
            default => 'Unknown Conflict',
        };
    }

    /**
     * Get conflict impact
     */
    protected function getConflictImpact(string $type): string
    {
        return match ($type) {
            'duplicate_create' => 'Migration will fail if first creation succeeds',
            'modify_before_create' => 'Migration will fail - table does not exist yet',
            'create_after_drop' => 'Potential data loss if not handled carefully',
            'concurrent_modifications' => 'Migrations may interfere with each other',
            default => 'Unknown impact',
        };
    }

    /**
     * Get conflict recommendation
     */
    protected function getConflictRecommendation(string $type): string
    {
        return match ($type) {
            'duplicate_create' => 'Remove duplicate, or use Schema::dropIfExists() before second create',
            'modify_before_create' => 'Reorder migrations or move table creation earlier',
            'create_after_drop' => 'Combine into single migration or ensure data is backed up',
            'concurrent_modifications' => 'Merge into single migration or ensure proper ordering',
            default => 'Review migration order',
        };
    }

    /**
     * Display resolution options
     */
    protected function displayResolutionOptions(): void
    {
        $this->newLine();
        $this->comment('Resolution Options:');
        $this->line('  1. Manually reorder migrations (rename files with new timestamps)');
        $this->line('  2. Merge conflicting migrations into a single migration');
        $this->line('  3. Add Schema::dropIfExists() before duplicate creates');
        $this->line('  4. Run with --auto-resolve flag to attempt automatic fixes');
        $this->newLine();
    }

    /**
     * Attempt automatic resolution
     */
    protected function attemptAutoResolve(): void
    {
        $this->newLine();
        $this->info('Attempting automatic conflict resolution...');
        $this->newLine();

        $resolved = 0;
        foreach ($this->conflicts as $conflict) {
            if ($this->resolveConflict($conflict)) {
                $resolved++;
            }
        }

        if ($resolved > 0) {
            $this->info("✅ Automatically resolved {$resolved} conflict(s)");
            $this->comment('Please review the changes and test your migrations.');
        } else {
            $this->warn('⚠️  Could not automatically resolve conflicts.');
            $this->comment('Manual intervention required.');
        }
    }

    /**
     * Resolve a specific conflict
     */
    protected function resolveConflict(array $conflict): bool
    {
        // This would implement actual resolution logic
        // For now, we'll just return false (manual resolution required)
        $this->comment("  - Cannot auto-resolve: {$this->getConflictTitle($conflict['type'])}");
        return false;
    }

    /**
     * Output as JSON
     */
    protected function outputJson(): void
    {
        $this->line(json_encode([
            'conflicts' => $this->conflicts,
            'count' => count($this->conflicts),
            'has_conflicts' => !empty($this->conflicts),
        ], JSON_PRETTY_PRINT));
    }
}
