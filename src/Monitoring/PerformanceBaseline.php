<?php

namespace Flux\Monitoring;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class PerformanceBaseline
{
    protected string $baselineFile;
    protected array $baseline = [];
    protected array $currentMetrics = [];

    public function __construct()
    {
        $this->baselineFile = storage_path('app/smart-migration/performance-baseline.json');
        $this->loadBaseline();
    }

    /**
     * Load existing baseline
     */
    protected function loadBaseline(): void
    {
        if (File::exists($this->baselineFile)) {
            $this->baseline = json_decode(File::get($this->baselineFile), true) ?? [];
        }
    }

    /**
     * Record migration performance
     */
    public function record(string $migration, array $metrics): void
    {
        $key = $this->getMigrationKey($migration);

        if (!isset($this->baseline[$key])) {
            $this->baseline[$key] = [
                'migration' => $migration,
                'first_recorded' => now()->toIso8601String(),
                'runs' => [],
                'statistics' => [],
            ];
        }

        // Add this run
        $this->baseline[$key]['runs'][] = array_merge($metrics, [
            'timestamp' => now()->toIso8601String(),
            'environment' => app()->environment(),
        ]);

        // Keep only last 100 runs
        $this->baseline[$key]['runs'] = array_slice(
            $this->baseline[$key]['runs'],
            -100
        );

        // Update statistics
        $this->updateStatistics($key);

        // Save baseline
        $this->save();
    }

    /**
     * Update statistics for a migration
     */
    protected function updateStatistics(string $key): void
    {
        $runs = $this->baseline[$key]['runs'];

        if (empty($runs)) {
            return;
        }

        $durations = array_column($runs, 'duration_ms');
        $memoryUsages = array_column($runs, 'memory_mb');
        $queryCount = array_column($runs, 'query_count');

        $this->baseline[$key]['statistics'] = [
            'total_runs' => count($runs),
            'duration' => [
                'min' => min($durations),
                'max' => max($durations),
                'avg' => array_sum($durations) / count($durations),
                'median' => $this->median($durations),
                'p95' => $this->percentile($durations, 95),
                'p99' => $this->percentile($durations, 99),
            ],
            'memory' => [
                'min' => min($memoryUsages),
                'max' => max($memoryUsages),
                'avg' => array_sum($memoryUsages) / count($memoryUsages),
            ],
            'queries' => [
                'min' => min($queryCount),
                'max' => max($queryCount),
                'avg' => array_sum($queryCount) / count($queryCount),
            ],
            'last_updated' => now()->toIso8601String(),
        ];
    }

    /**
     * Check if current performance deviates from baseline
     */
    public function checkDeviation(string $migration, array $currentMetrics): array
    {
        $key = $this->getMigrationKey($migration);

        if (!isset($this->baseline[$key]['statistics'])) {
            return [
                'has_baseline' => false,
                'message' => 'No baseline available yet',
            ];
        }

        $stats = $this->baseline[$key]['statistics'];
        $deviations = [];

        // Check duration deviation
        $durationDeviation = $this->calculateDeviation(
            $currentMetrics['duration_ms'],
            $stats['duration']['avg']
        );

        if ($durationDeviation > 50) { // More than 50% slower
            $deviations[] = [
                'metric' => 'duration',
                'deviation_percent' => $durationDeviation,
                'current' => $currentMetrics['duration_ms'],
                'baseline_avg' => $stats['duration']['avg'],
                'baseline_p95' => $stats['duration']['p95'],
                'severity' => $this->getSeverity($durationDeviation),
            ];
        }

        // Check memory deviation
        $memoryDeviation = $this->calculateDeviation(
            $currentMetrics['memory_mb'],
            $stats['memory']['avg']
        );

        if ($memoryDeviation > 100) { // More than 100% increase
            $deviations[] = [
                'metric' => 'memory',
                'deviation_percent' => $memoryDeviation,
                'current' => $currentMetrics['memory_mb'],
                'baseline_avg' => $stats['memory']['avg'],
                'severity' => $this->getSeverity($memoryDeviation),
            ];
        }

        // Check query count deviation
        $queryDeviation = $this->calculateDeviation(
            $currentMetrics['query_count'],
            $stats['queries']['avg']
        );

        if ($queryDeviation > 25) { // More than 25% more queries
            $deviations[] = [
                'metric' => 'query_count',
                'deviation_percent' => $queryDeviation,
                'current' => $currentMetrics['query_count'],
                'baseline_avg' => $stats['queries']['avg'],
                'severity' => $this->getSeverity($queryDeviation),
            ];
        }

        return [
            'has_baseline' => true,
            'has_deviations' => !empty($deviations),
            'deviations' => $deviations,
            'baseline_statistics' => $stats,
        ];
    }

    /**
     * Calculate percentage deviation
     */
    protected function calculateDeviation(float $current, float $baseline): float
    {
        if ($baseline == 0) {
            return 0;
        }

        return round((($current - $baseline) / $baseline) * 100, 2);
    }

    /**
     * Get severity level based on deviation
     */
    protected function getSeverity(float $deviation): string
    {
        if ($deviation > 200) {
            return 'critical';
        } elseif ($deviation > 100) {
            return 'high';
        } elseif ($deviation > 50) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Get baseline statistics for a migration
     */
    public function getStatistics(string $migration): ?array
    {
        $key = $this->getMigrationKey($migration);

        return $this->baseline[$key]['statistics'] ?? null;
    }

    /**
     * Get all baselines
     */
    public function getAll(): array
    {
        return $this->baseline;
    }

    /**
     * Reset baseline for a migration
     */
    public function reset(string $migration): void
    {
        $key = $this->getMigrationKey($migration);
        unset($this->baseline[$key]);
        $this->save();
    }

    /**
     * Reset all baselines
     */
    public function resetAll(): void
    {
        $this->baseline = [];
        $this->save();
    }

    /**
     * Save baseline to file
     */
    protected function save(): void
    {
        $dir = dirname($this->baselineFile);

        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($this->baselineFile, json_encode($this->baseline, JSON_PRETTY_PRINT));
    }

    /**
     * Get migration key
     */
    protected function getMigrationKey(string $migration): string
    {
        return md5($migration);
    }

    /**
     * Calculate median
     */
    protected function median(array $values): float
    {
        sort($values);
        $count = count($values);

        if ($count == 0) {
            return 0;
        }

        $middle = floor($count / 2);

        if ($count % 2 == 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        } else {
            return $values[$middle];
        }
    }

    /**
     * Calculate percentile
     */
    protected function percentile(array $values, int $percentile): float
    {
        sort($values);
        $count = count($values);

        if ($count == 0) {
            return 0;
        }

        $index = ceil(($percentile / 100) * $count) - 1;
        $index = max(0, min($index, $count - 1));

        return $values[$index];
    }

    /**
     * Generate performance report
     */
    public function generateReport(): array
    {
        $report = [
            'generated_at' => now()->toIso8601String(),
            'total_migrations_tracked' => count($this->baseline),
            'migrations' => [],
            'summary' => [
                'avg_duration_ms' => 0,
                'total_runs' => 0,
                'slowest_migrations' => [],
                'most_memory_intensive' => [],
            ],
        ];

        $allDurations = [];
        $allMemory = [];

        foreach ($this->baseline as $key => $data) {
            if (!isset($data['statistics'])) {
                continue;
            }

            $stats = $data['statistics'];
            $report['migrations'][] = [
                'migration' => $data['migration'],
                'total_runs' => $stats['total_runs'],
                'duration' => $stats['duration'],
                'memory' => $stats['memory'],
                'queries' => $stats['queries'],
            ];

            $allDurations[] = $stats['duration']['avg'];
            $allMemory[] = $stats['memory']['avg'];
            $report['summary']['total_runs'] += $stats['total_runs'];
        }

        if (!empty($allDurations)) {
            $report['summary']['avg_duration_ms'] = array_sum($allDurations) / count($allDurations);
        }

        // Sort and get top 5 slowest
        usort($report['migrations'], fn($a, $b) => $b['duration']['avg'] <=> $a['duration']['avg']);
        $report['summary']['slowest_migrations'] = array_slice($report['migrations'], 0, 5);

        // Sort and get top 5 memory intensive
        usort($report['migrations'], fn($a, $b) => $b['memory']['avg'] <=> $a['memory']['avg']);
        $report['summary']['most_memory_intensive'] = array_slice($report['migrations'], 0, 5);

        return $report;
    }
}
