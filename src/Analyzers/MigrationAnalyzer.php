<?php

namespace Flux\Analyzers;

use Flux\Config\SmartMigrationConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MigrationAnalyzer
{
    protected array $dangerousOperations = [
        'drop', 'dropIfExists', 'dropColumn', 'dropForeign',
        'dropPrimary', 'dropUnique', 'dropIndex', 'dropSpatialIndex',
        'dropTimestamps', 'dropSoftDeletes', 'dropRememberToken',
    ];

    protected array $warningOperations = [
        'rename', 'renameColumn', 'change', 'index', 'unique',
        'spatialIndex', 'foreign', 'primary',
    ];

    public function analyze(string $migrationFile): array
    {
        $content = File::get($migrationFile);
        $operations = $this->parseOperations($content);
        $analysis = [];
        $summary = ['safe' => 0, 'warnings' => 0, 'dangerous' => 0];
        $totalTime = 0;

        foreach ($operations as $operation) {
            $analyzed = $this->analyzeOperation($operation);
            $analysis[] = $analyzed;

            switch ($analyzed['risk']) {
                case 'safe':
                    $summary['safe']++;
                    break;
                case 'warning':
                    $summary['warnings']++;
                    break;
                case 'danger':
                    $summary['dangerous']++;
                    break;
            }

            if (isset($analyzed['duration_ms'])) {
                $totalTime += $analyzed['duration_ms'];
            }
        }

        return [
            'operations' => $analysis,
            'summary' => $summary,
            'estimated_time' => $this->formatDuration($totalTime),
        ];
    }

    protected function parseOperations(string $content): array
    {
        $operations = [];

        // Parse Schema::create
        preg_match_all('/Schema::create\([\'"](\w+)[\'"]/', $content, $creates);
        foreach ($creates[1] as $table) {
            $operations[] = ['type' => 'create_table', 'table' => $table];

            // Parse columns within create
            $pattern = "/Schema::create\(['\"]".preg_quote($table)."['\"],.*?function.*?\(.*?\).*?\{(.*?)\}\);/s";
            if (preg_match($pattern, $content, $tableMatch)) {
                $tableContent = $tableMatch[1];
                $operations = array_merge($operations, $this->parseTableOperations($tableContent, $table));
            }
        }

        // Parse Schema::table
        preg_match_all('/Schema::table\([\'"](\w+)[\'"]/', $content, $tables);
        foreach ($tables[1] as $table) {
            $pattern = "/Schema::table\(['\"]".preg_quote($table)."['\"],.*?function.*?\(.*?\).*?\{(.*?)\}\);/s";
            if (preg_match($pattern, $content, $tableMatch)) {
                $tableContent = $tableMatch[1];
                $operations = array_merge($operations, $this->parseTableOperations($tableContent, $table));
            }
        }

        // Parse Schema::drop
        preg_match_all('/Schema::drop(?:IfExists)?\([\'"](\w+)[\'"]/', $content, $drops);
        foreach ($drops[1] as $table) {
            $operations[] = ['type' => 'drop_table', 'table' => $table];
        }

        // Parse Schema::rename
        preg_match_all('/Schema::rename\([\'"](\w+)[\'"],\s*[\'"](\w+)[\'"]/', $content, $renames);
        foreach ($renames[1] as $index => $oldTable) {
            $operations[] = ['type' => 'rename_table', 'from' => $oldTable, 'to' => $renames[2][$index]];
        }

        return $operations;
    }

    protected function parseTableOperations(string $content, string $table): array
    {
        $operations = [];

        // Parse column additions
        preg_match_all('/\$table->(\w+)\([\'"]?(\w+)[\'"]?(?:,\s*(\d+))?\)/', $content, $columns);
        foreach ($columns[1] as $index => $type) {
            $columnName = $columns[2][$index];

            if (in_array($type, $this->dangerousOperations)) {
                $operations[] = [
                    'type' => $type,
                    'table' => $table,
                    'column' => $columnName,
                ];
            } elseif (in_array($type, $this->warningOperations)) {
                $operations[] = [
                    'type' => $type,
                    'table' => $table,
                    'column' => $columnName,
                ];
            } elseif (! in_array($type, ['nullable', 'default', 'unsigned', 'comment'])) {
                $operations[] = [
                    'type' => 'add_column',
                    'table' => $table,
                    'column' => $columnName,
                    'column_type' => $type,
                ];
            }
        }

        return $operations;
    }

    protected function analyzeOperation(array $operation): array
    {
        $risk = $this->assessRisk($operation);
        $sql = $this->generateSQL($operation);
        $impact = $this->assessImpact($operation);
        $duration = $this->estimateDuration($operation);
        $description = $this->generateDescription($operation);

        return [
            'risk' => $risk,
            'description' => $description,
            'sql' => $sql,
            'impact' => $impact,
            'duration' => $duration['formatted'],
            'duration_ms' => $duration['ms'],
        ];
    }

    protected function assessRisk(array $operation): string
    {
        $type = $operation['type'] ?? '';

        // First check config for custom risk levels
        $configRisk = SmartMigrationConfig::getOperationRisk($this->mapOperationType($type));
        if ($configRisk) {
            return $configRisk;
        }

        // Fall back to default assessment
        if (Str::startsWith($type, 'drop') || $type === 'drop_table') {
            return 'danger';
        }

        if (in_array($type, $this->warningOperations) || $type === 'rename_table') {
            return 'warning';
        }

        return 'safe';
    }

    /**
     * Map internal operation types to config operation types
     */
    protected function mapOperationType(string $type): string
    {
        return match ($type) {
            'create_table' => 'create_table',
            'add_column' => 'add_column',
            'dropColumn' => 'drop_column',
            'drop_table' => 'drop_table',
            'index' => 'add_index',
            'dropIndex' => 'drop_index',
            'change' => 'modify_column',
            'renameColumn' => 'rename_column',
            default => $type,
        };
    }

    protected function generateSQL(array $operation): string
    {
        $type = $operation['type'] ?? '';

        return match ($type) {
            'create_table' => "CREATE TABLE {$operation['table']} (...)",
            'drop_table' => "DROP TABLE {$operation['table']}",
            'rename_table' => "RENAME TABLE {$operation['from']} TO {$operation['to']}",
            'add_column' => "ALTER TABLE {$operation['table']} ADD {$operation['column']} {$this->getSQLType($operation['column_type'] ?? 'string')}",
            'dropColumn' => "ALTER TABLE {$operation['table']} DROP COLUMN {$operation['column']}",
            'index' => "CREATE INDEX ON {$operation['table']} ({$operation['column']})",
            'unique' => "CREATE UNIQUE INDEX ON {$operation['table']} ({$operation['column']})",
            'foreign' => "ALTER TABLE {$operation['table']} ADD FOREIGN KEY ({$operation['column']})",
            default => strtoupper(str_replace('_', ' ', $type)),
        };
    }

    protected function getSQLType(string $laravelType): string
    {
        return match ($laravelType) {
            'bigIncrements', 'bigInteger' => 'BIGINT',
            'integer', 'increments' => 'INT',
            'string' => 'VARCHAR(255)',
            'text', 'longText' => 'TEXT',
            'boolean' => 'BOOLEAN',
            'timestamp', 'timestamps' => 'TIMESTAMP',
            'date' => 'DATE',
            'datetime' => 'DATETIME',
            'decimal' => 'DECIMAL(8,2)',
            'json', 'jsonb' => 'JSON',
            default => 'VARCHAR(255)',
        };
    }

    protected function assessImpact(array $operation): string
    {
        $type = $operation['type'] ?? '';
        $table = $operation['table'] ?? '';

        if ($table) {
            try {
                $rowCount = DB::table($table)->count();

                return match ($type) {
                    'drop_table' => "{$rowCount} rows will be DELETED",
                    'dropColumn' => "{$rowCount} rows affected",
                    'add_column' => "{$rowCount} rows will get new column",
                    'index', 'unique' => "{$rowCount} rows to index",
                    default => "{$rowCount} rows affected",
                };
            } catch (\Exception $e) {
                if ($type === 'create_table') {
                    return 'New table will be created';
                }

                return 'Table not found (will be created)';
            }
        }

        return 'Unknown impact';
    }

    protected function estimateDuration(array $operation): array
    {
        $type = $operation['type'] ?? '';
        $table = $operation['table'] ?? '';
        $baseTime = 10; // Base time in ms

        if ($table) {
            try {
                $rowCount = DB::table($table)->count();

                $timeMs = match ($type) {
                    'create_table' => $baseTime,
                    'drop_table' => $baseTime + ($rowCount * 0.001),
                    'add_column' => $baseTime + ($rowCount * 0.01),
                    'dropColumn' => $baseTime + ($rowCount * 0.01),
                    'index', 'unique' => $baseTime + ($rowCount * 0.1),
                    'foreign' => $baseTime + ($rowCount * 0.05),
                    default => $baseTime,
                };

            } catch (\Exception $e) {
                $timeMs = $baseTime;
            }
        } else {
            $timeMs = $baseTime;
        }

        return [
            'ms' => $timeMs,
            'formatted' => $this->formatDuration($timeMs),
        ];
    }

    protected function formatDuration(float $ms): string
    {
        if ($ms < 1000) {
            return '~'.round($ms).'ms';
        } elseif ($ms < 60000) {
            return '~'.round($ms / 1000, 1).'s';
        } else {
            return '~'.round($ms / 60000, 1).'min';
        }
    }

    protected function generateDescription(array $operation): string
    {
        $type = $operation['type'] ?? '';

        return match ($type) {
            'create_table' => "Create table '{$operation['table']}'",
            'drop_table' => "Drop table '{$operation['table']}'",
            'rename_table' => "Rename table '{$operation['from']}' to '{$operation['to']}'",
            'add_column' => "Add column '{$operation['column']}' to '{$operation['table']}'",
            'dropColumn' => "Drop column '{$operation['column']}' from '{$operation['table']}'",
            'index' => "Add index on '{$operation['column']}' in '{$operation['table']}'",
            'unique' => "Add unique index on '{$operation['column']}' in '{$operation['table']}'",
            'foreign' => "Add foreign key on '{$operation['column']}' in '{$operation['table']}'",
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}
