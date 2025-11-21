<?php

namespace Flux\Monitoring;

class AnomalyDetector
{
    protected PerformanceBaseline $baseline;
    protected array $thresholds;

    public function __construct(PerformanceBaseline $baseline)
    {
        $this->baseline = $baseline;
        $this->thresholds = config('smart-migration.monitoring.anomaly_thresholds', [
            'duration_deviation' => 50,      // Percent deviation for duration
            'memory_deviation' => 100,       // Percent deviation for memory
            'query_count_deviation' => 25,   // Percent deviation for query count
            'rows_affected_multiplier' => 10, // Multiplier for rows affected
        ]);
    }

    /**
     * Detect anomalies in migration performance
     */
    public function detect(string $migration, array $currentMetrics): array
    {
        $anomalies = [];

        // Check if we have baseline data
        $stats = $this->baseline->getStatistics($migration);

        if (!$stats) {
            return [
                'has_anomalies' => false,
                'anomalies' => [],
                'message' => 'No baseline data available. Building baseline...',
            ];
        }

        // Detect duration anomalies
        $durationAnomaly = $this->detectDurationAnomaly($currentMetrics, $stats);
        if ($durationAnomaly) {
            $anomalies[] = $durationAnomaly;
        }

        // Detect memory anomalies
        $memoryAnomaly = $this->detectMemoryAnomaly($currentMetrics, $stats);
        if ($memoryAnomaly) {
            $anomalies[] = $memoryAnomaly;
        }

        // Detect query count anomalies
        $queryAnomaly = $this->detectQueryAnomaly($currentMetrics, $stats);
        if ($queryAnomaly) {
            $anomalies[] = $queryAnomaly;
        }

        // Detect rows affected anomalies
        if (isset($currentMetrics['rows_affected'])) {
            $rowsAnomaly = $this->detectRowsAnomaly($currentMetrics, $stats);
            if ($rowsAnomaly) {
                $anomalies[] = $rowsAnomaly;
            }
        }

        return [
            'has_anomalies' => !empty($anomalies),
            'anomalies' => $anomalies,
            'severity' => $this->calculateOverallSeverity($anomalies),
            'baseline_stats' => $stats,
        ];
    }

    /**
     * Detect duration anomalies
     */
    protected function detectDurationAnomaly(array $current, array $stats): ?array
    {
        $currentDuration = $current['duration_ms'] ?? 0;
        $avgDuration = $stats['duration']['avg'];
        $p95Duration = $stats['duration']['p95'];

        // Check if current duration exceeds P95
        if ($currentDuration > $p95Duration) {
            $deviation = $this->calculateDeviation($currentDuration, $avgDuration);

            if ($deviation > $this->thresholds['duration_deviation']) {
                return [
                    'type' => 'duration',
                    'severity' => $this->getSeverity($deviation, 'duration'),
                    'message' => sprintf(
                        'Migration took %.2fms (%.0f%% slower than baseline avg of %.2fms, exceeds P95 of %.2fms)',
                        $currentDuration,
                        $deviation,
                        $avgDuration,
                        $p95Duration
                    ),
                    'current_value' => $currentDuration,
                    'baseline_avg' => $avgDuration,
                    'baseline_p95' => $p95Duration,
                    'deviation_percent' => $deviation,
                ];
            }
        }

        return null;
    }

    /**
     * Detect memory anomalies
     */
    protected function detectMemoryAnomaly(array $current, array $stats): ?array
    {
        $currentMemory = $current['memory_mb'] ?? 0;
        $avgMemory = $stats['memory']['avg'];
        $maxMemory = $stats['memory']['max'];

        // Check if current memory usage is significantly higher
        $deviation = $this->calculateDeviation($currentMemory, $avgMemory);

        if ($deviation > $this->thresholds['memory_deviation']) {
            return [
                'type' => 'memory',
                'severity' => $this->getSeverity($deviation, 'memory'),
                'message' => sprintf(
                    'Memory usage %.2fMB (%.0f%% higher than baseline avg of %.2fMB)',
                    $currentMemory,
                    $deviation,
                    $avgMemory
                ),
                'current_value' => $currentMemory,
                'baseline_avg' => $avgMemory,
                'baseline_max' => $maxMemory,
                'deviation_percent' => $deviation,
            ];
        }

        return null;
    }

    /**
     * Detect query count anomalies
     */
    protected function detectQueryAnomaly(array $current, array $stats): ?array
    {
        $currentQueries = $current['query_count'] ?? 0;
        $avgQueries = $stats['queries']['avg'];

        $deviation = $this->calculateDeviation($currentQueries, $avgQueries);

        if ($deviation > $this->thresholds['query_count_deviation']) {
            return [
                'type' => 'query_count',
                'severity' => $this->getSeverity($deviation, 'query_count'),
                'message' => sprintf(
                    'Executed %d queries (%.0f%% more than baseline avg of %.0f)',
                    $currentQueries,
                    $deviation,
                    $avgQueries
                ),
                'current_value' => $currentQueries,
                'baseline_avg' => $avgQueries,
                'deviation_percent' => $deviation,
            ];
        }

        return null;
    }

    /**
     * Detect rows affected anomalies
     */
    protected function detectRowsAnomaly(array $current, array $stats): ?array
    {
        $currentRows = $current['rows_affected'] ?? 0;

        // If baseline doesn't track rows, skip
        if (!isset($stats['rows_affected'])) {
            return null;
        }

        $avgRows = $stats['rows_affected']['avg'] ?? 0;

        // Only alert if rows affected is significantly higher (not lower, as that could be expected)
        if ($avgRows > 0 && $currentRows > ($avgRows * $this->thresholds['rows_affected_multiplier'])) {
            return [
                'type' => 'rows_affected',
                'severity' => 'high',
                'message' => sprintf(
                    'Affected %d rows (%dx more than baseline avg of %.0f)',
                    $currentRows,
                    round($currentRows / $avgRows, 1),
                    $avgRows
                ),
                'current_value' => $currentRows,
                'baseline_avg' => $avgRows,
            ];
        }

        return null;
    }

    /**
     * Calculate percentage deviation
     */
    protected function calculateDeviation(float $current, float $baseline): float
    {
        if ($baseline == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $baseline) / $baseline) * 100, 2);
    }

    /**
     * Get severity level
     */
    protected function getSeverity(float $deviation, string $type): string
    {
        $thresholds = [
            'duration' => [
                'critical' => 200,
                'high' => 100,
                'medium' => 50,
            ],
            'memory' => [
                'critical' => 300,
                'high' => 200,
                'medium' => 100,
            ],
            'query_count' => [
                'critical' => 100,
                'high' => 50,
                'medium' => 25,
            ],
        ];

        $levels = $thresholds[$type] ?? $thresholds['duration'];

        if ($deviation >= $levels['critical']) {
            return 'critical';
        } elseif ($deviation >= $levels['high']) {
            return 'high';
        } elseif ($deviation >= $levels['medium']) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Calculate overall severity from multiple anomalies
     */
    protected function calculateOverallSeverity(array $anomalies): string
    {
        if (empty($anomalies)) {
            return 'none';
        }

        $severityLevels = ['none' => 0, 'low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
        $maxSeverity = 0;

        foreach ($anomalies as $anomaly) {
            $severity = $anomaly['severity'] ?? 'low';
            $maxSeverity = max($maxSeverity, $severityLevels[$severity] ?? 0);
        }

        return array_search($maxSeverity, $severityLevels) ?: 'low';
    }

    /**
     * Get anomaly summary for display
     */
    public function getSummary(array $anomalies): string
    {
        if (empty($anomalies)) {
            return 'No anomalies detected';
        }

        $counts = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
        ];

        foreach ($anomalies as $anomaly) {
            $severity = $anomaly['severity'] ?? 'low';
            $counts[$severity] = ($counts[$severity] ?? 0) + 1;
        }

        $parts = [];
        foreach ($counts as $severity => $count) {
            if ($count > 0) {
                $parts[] = "$count $severity";
            }
        }

        return implode(', ', $parts) . ' anomal' . (count($anomalies) === 1 ? 'y' : 'ies');
    }
}
