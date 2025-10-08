<?php

namespace Flux\Snapshots;

use Flux\Config\SmartMigrationConfig;
use Flux\Database\DatabaseAdapterFactoryInterface;
use Illuminate\Support\Facades\File;

class SnapshotManager
{
    /**
     * Current snapshot format version
     * Increment when adapter improvements change the schema format
     */
    public const CURRENT_FORMAT_VERSION = '1.0.0';

    protected string $snapshotPath;

    protected string $format;

    protected DatabaseAdapterFactoryInterface $adapterFactory;

    public function __construct(?DatabaseAdapterFactoryInterface $adapterFactory = null)
    {
        // Support both DI and legacy instantiation
        $this->adapterFactory = $adapterFactory ?? app(DatabaseAdapterFactoryInterface::class);

        $this->snapshotPath = database_path(SmartMigrationConfig::getSnapshotPath());
        $this->format = SmartMigrationConfig::getSnapshotFormat();

        // Ensure snapshot directory exists
        if (! File::exists($this->snapshotPath)) {
            File::makeDirectory($this->snapshotPath, 0755, true);
        }
    }

    /**
     * Create a new snapshot of the current database schema
     */
    public function create(?string $name = null): array
    {
        $adapter = $this->adapterFactory->create();

        $name = $name ?: 'snapshot_'.date('Y_m_d_His');
        $version = $this->generateVersion();

        $snapshot = [
            'name' => $name,
            'version' => $version,
            'format_version' => self::CURRENT_FORMAT_VERSION,
            'timestamp' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'database' => [
                'driver' => $adapter->getDriverName(),
                'name' => config('database.connections.'.config('database.default').'.database'),
            ],
            'schema' => $this->captureSchema(),
        ];

        // Include data if configured
        if (SmartMigrationConfig::get('snapshots.include_data', false)) {
            $snapshot['data'] = $this->captureData();
        }

        // Save snapshot
        $filename = "{$name}.{$this->format}";
        $filepath = "{$this->snapshotPath}/{$filename}";

        $this->saveSnapshot($filepath, $snapshot);

        // Cleanup old snapshots if needed
        $this->cleanupOldSnapshots();

        return $snapshot;
    }

    /**
     * Get the latest snapshot
     */
    public function getLatest(): ?array
    {
        $files = File::glob("{$this->snapshotPath}/*.{$this->format}");

        if (empty($files)) {
            return null;
        }

        // Sort by modification time
        usort($files, fn ($a, $b) => File::lastModified($b) - File::lastModified($a));

        return $this->loadSnapshot($files[0]);
    }

    /**
     * Get a specific snapshot by name
     */
    public function get(string $name): ?array
    {
        $filepath = "{$this->snapshotPath}/{$name}.{$this->format}";

        if (! File::exists($filepath)) {
            return null;
        }

        return $this->loadSnapshot($filepath);
    }

    /**
     * List all snapshots
     */
    public function list(): array
    {
        $files = File::glob("{$this->snapshotPath}/*.{$this->format}");
        $snapshots = [];

        foreach ($files as $file) {
            $snapshot = $this->loadSnapshot($file);
            if ($snapshot) {
                $snapshots[] = [
                    'name' => $snapshot['name'],
                    'version' => $snapshot['version'],
                    'timestamp' => $snapshot['timestamp'],
                    'environment' => $snapshot['environment'] ?? 'unknown',
                    'size' => File::size($file),
                    'path' => $file,
                ];
            }
        }

        // Sort by timestamp descending
        usort($snapshots, fn ($a, $b) => strcmp($b['timestamp'], $a['timestamp']));

        return $snapshots;
    }

    /**
     * Delete a snapshot
     */
    public function delete(string $name): bool
    {
        $filepath = "{$this->snapshotPath}/{$name}.{$this->format}";

        if (File::exists($filepath)) {
            return File::delete($filepath);
        }

        return false;
    }

    /**
     * Compare two snapshots
     */
    public function compare(string $snapshot1, string $snapshot2): array
    {
        $snap1 = $this->get($snapshot1);
        $snap2 = $this->get($snapshot2);

        if (! $snap1 || ! $snap2) {
            throw new \Exception('One or both snapshots not found');
        }

        return $this->compareSchemas($snap1['schema'], $snap2['schema']);
    }

    /**
     * Restore schema from a snapshot
     */
    public function restore(string $name): bool
    {
        $snapshot = $this->get($name);

        if (! $snapshot) {
            throw new \Exception("Snapshot '{$name}' not found");
        }

        // This would restore the schema - implement carefully!
        // For safety, we'll just return false for now
        return false;
    }

    /**
     * Capture current schema
     */
    protected function captureSchema(): array
    {
        $adapter = $this->adapterFactory->create();
        $schema = [
            'tables' => [],
        ];

        $ignoredTables = SmartMigrationConfig::getDriftIgnoredTables();

        foreach ($adapter->getAllTables() as $table) {
            // Skip ignored tables
            $skip = false;
            foreach ($ignoredTables as $pattern) {
                if (fnmatch($pattern, $table)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            $schema['tables'][$table] = [
                'columns' => $adapter->getTableColumns($table),
                'indexes' => $adapter->getTableIndexes($table),
                'foreign_keys' => $adapter->getTableForeignKeys($table),
                'row_count' => $adapter->getTableRowCount($table),
            ];
        }

        return $schema;
    }

    /**
     * Capture data from tables
     */
    protected function captureData(): array
    {
        $adapter = $this->adapterFactory->create();
        $data = [];

        foreach ($adapter->getAllTables() as $table) {
            // Skip large tables or system tables
            if ($adapter->getTableRowCount($table) > 1000) {
                continue;
            }

            $data[$table] = $adapter->getTableData($table);
        }

        return $data;
    }

    /**
     * Generate a unique version hash
     */
    protected function generateVersion(): string
    {
        $schema = $this->captureSchema();
        $schemaString = json_encode($schema);

        return substr(sha1($schemaString), 0, 12);
    }

    /**
     * Save snapshot to file
     */
    protected function saveSnapshot(string $filepath, array $snapshot): void
    {
        $content = match ($this->format) {
            'json' => json_encode($snapshot, JSON_PRETTY_PRINT),
            'yaml' => yaml_emit($snapshot),
            'php' => "<?php\n\nreturn ".var_export($snapshot, true).";\n",
            default => json_encode($snapshot, JSON_PRETTY_PRINT),
        };

        File::put($filepath, $content);
    }

    /**
     * Load snapshot from file
     */
    protected function loadSnapshot(string $filepath): ?array
    {
        if (! File::exists($filepath)) {
            return null;
        }

        $content = File::get($filepath);

        return match ($this->format) {
            'json' => json_decode($content, true),
            'yaml' => yaml_parse($content),
            'php' => include $filepath,
            default => json_decode($content, true),
        };
    }

    /**
     * Clean up old snapshots
     */
    protected function cleanupOldSnapshots(): void
    {
        $maxSnapshots = SmartMigrationConfig::getMaxSnapshots();

        if ($maxSnapshots <= 0) {
            return;
        }

        $files = File::glob("{$this->snapshotPath}/*.{$this->format}");

        if (count($files) <= $maxSnapshots) {
            return;
        }

        // Sort by modification time (oldest first)
        usort($files, fn ($a, $b) => File::lastModified($a) - File::lastModified($b));

        // Delete oldest files
        $toDelete = count($files) - $maxSnapshots;
        for ($i = 0; $i < $toDelete; $i++) {
            File::delete($files[$i]);
        }
    }

    /**
     * Compare two schemas
     */
    protected function compareSchemas(array $schema1, array $schema2): array
    {
        $differences = [
            'added_tables' => [],
            'removed_tables' => [],
            'modified_tables' => [],
        ];

        $tables1 = array_keys($schema1['tables'] ?? []);
        $tables2 = array_keys($schema2['tables'] ?? []);

        // Find added tables
        $differences['added_tables'] = array_diff($tables2, $tables1);

        // Find removed tables
        $differences['removed_tables'] = array_diff($tables1, $tables2);

        // Find modified tables
        $commonTables = array_intersect($tables1, $tables2);
        foreach ($commonTables as $table) {
            $table1 = $schema1['tables'][$table];
            $table2 = $schema2['tables'][$table];

            if (json_encode($table1) !== json_encode($table2)) {
                $differences['modified_tables'][] = $table;
            }
        }

        return $differences;
    }

    /**
     * Check if snapshot has a format version mismatch
     */
    public function hasFormatVersionMismatch(array $snapshot): bool
    {
        // If snapshot doesn't have format_version, it's from an old version
        if (! isset($snapshot['format_version'])) {
            return true;
        }

        // Check if versions match
        return $snapshot['format_version'] !== self::CURRENT_FORMAT_VERSION;
    }

    /**
     * Get format version warning message
     */
    public function getFormatVersionWarning(array $snapshot): string
    {
        $snapshotVersion = $snapshot['format_version'] ?? 'unknown';
        $currentVersion = self::CURRENT_FORMAT_VERSION;

        return sprintf(
            "Warning: Snapshot format version mismatch!\n".
            "Snapshot version: %s\n".
            "Current version: %s\n".
            "This may cause false positives due to schema adapter improvements.\n".
            'Consider recreating the snapshot with: php artisan migrate:snapshot',
            $snapshotVersion,
            $currentVersion
        );
    }
}
