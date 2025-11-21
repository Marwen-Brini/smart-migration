<?php

use Flux\Monitoring\AnomalyDetector;
use Flux\Monitoring\PerformanceBaseline;

beforeEach(function () {
    $this->mockBaseline = Mockery::mock(PerformanceBaseline::class);
    $this->detector = new AnomalyDetector($this->mockBaseline);
});

afterEach(function () {
    Mockery::close();
});

describe('detect method', function () {
    it('returns no baseline message when baseline data is not available', function () {
        $this->mockBaseline->shouldReceive('getStatistics')
            ->with('test_migration')
            ->andReturn(null);

        $currentMetrics = [
            'duration_ms' => 100.0,
            'memory_mb' => 10.0,
            'query_count' => 5,
        ];

        $result = $this->detector->detect('test_migration', $currentMetrics);

        expect($result['has_anomalies'])->toBeFalse()
            ->and($result['anomalies'])->toBe([])
            ->and($result['message'])->toContain('No baseline data available');
    });

    it('detects no anomalies when metrics are within normal range', function () {
        $stats = [
            'duration' => ['avg' => 100.0, 'p95' => 150.0],
            'memory' => ['avg' => 10.0, 'max' => 15.0],
            'queries' => ['avg' => 5.0],
        ];

        $this->mockBaseline->shouldReceive('getStatistics')
            ->with('test_migration')
            ->andReturn($stats);

        $currentMetrics = [
            'duration_ms' => 120.0,
            'memory_mb' => 11.0,
            'query_count' => 6,
        ];

        $result = $this->detector->detect('test_migration', $currentMetrics);

        expect($result['has_anomalies'])->toBeFalse()
            ->and($result['anomalies'])->toBe([])
            ->and($result['severity'])->toBe('none');
    });

    it('detects duration anomaly when exceeding thresholds', function () {
        $stats = [
            'duration' => ['avg' => 100.0, 'p95' => 150.0],
            'memory' => ['avg' => 10.0, 'max' => 15.0],
            'queries' => ['avg' => 5.0],
        ];

        $this->mockBaseline->shouldReceive('getStatistics')
            ->with('test_migration')
            ->andReturn($stats);

        $currentMetrics = [
            'duration_ms' => 300.0, // 200% higher than avg, exceeds P95
            'memory_mb' => 11.0,
            'query_count' => 6,
        ];

        $result = $this->detector->detect('test_migration', $currentMetrics);

        expect($result['has_anomalies'])->toBeTrue()
            ->and($result['anomalies'])->toHaveCount(1)
            ->and($result['anomalies'][0]['type'])->toBe('duration')
            ->and($result['anomalies'][0]['severity'])->toBeIn(['high', 'critical'])
            ->and($result['severity'])->toBeIn(['high', 'critical']);
    });

    it('detects memory anomaly when exceeding thresholds', function () {
        $stats = [
            'duration' => ['avg' => 100.0, 'p95' => 150.0],
            'memory' => ['avg' => 10.0, 'max' => 15.0],
            'queries' => ['avg' => 5.0],
        ];

        $this->mockBaseline->shouldReceive('getStatistics')
            ->with('test_migration')
            ->andReturn($stats);

        $currentMetrics = [
            'duration_ms' => 120.0,
            'memory_mb' => 35.0, // 250% higher than avg
            'query_count' => 6,
        ];

        $result = $this->detector->detect('test_migration', $currentMetrics);

        expect($result['has_anomalies'])->toBeTrue()
            ->and($result['anomalies'])->toHaveCount(1)
            ->and($result['anomalies'][0]['type'])->toBe('memory')
            ->and($result['anomalies'][0]['severity'])->toBeIn(['medium', 'high', 'critical']);
    });

    it('detects query count anomaly when exceeding thresholds', function () {
        $stats = [
            'duration' => ['avg' => 100.0, 'p95' => 150.0],
            'memory' => ['avg' => 10.0, 'max' => 15.0],
            'queries' => ['avg' => 5.0],
        ];

        $this->mockBaseline->shouldReceive('getStatistics')
            ->with('test_migration')
            ->andReturn($stats);

        $currentMetrics = [
            'duration_ms' => 120.0,
            'memory_mb' => 11.0,
            'query_count' => 10, // 100% higher than avg
        ];

        $result = $this->detector->detect('test_migration', $currentMetrics);

        expect($result['has_anomalies'])->toBeTrue()
            ->and($result['anomalies'])->toHaveCount(1)
            ->and($result['anomalies'][0]['type'])->toBe('query_count')
            ->and($result['anomalies'][0]['severity'])->toBeIn(['medium', 'high', 'critical']);
    });

    it('detects rows affected anomaly when exceeding multiplier threshold', function () {
        $stats = [
            'duration' => ['avg' => 100.0, 'p95' => 150.0],
            'memory' => ['avg' => 10.0, 'max' => 15.0],
            'queries' => ['avg' => 5.0],
            'rows_affected' => ['avg' => 10.0],
        ];

        $this->mockBaseline->shouldReceive('getStatistics')
            ->with('test_migration')
            ->andReturn($stats);

        $currentMetrics = [
            'duration_ms' => 120.0,
            'memory_mb' => 11.0,
            'query_count' => 6,
            'rows_affected' => 150, // 15x more than avg (threshold is 10x)
        ];

        $result = $this->detector->detect('test_migration', $currentMetrics);

        expect($result['has_anomalies'])->toBeTrue()
            ->and($result['anomalies'])->toHaveCount(1)
            ->and($result['anomalies'][0]['type'])->toBe('rows_affected')
            ->and($result['anomalies'][0]['severity'])->toBe('high');
    });

    it('detects multiple anomalies simultaneously', function () {
        $stats = [
            'duration' => ['avg' => 100.0, 'p95' => 150.0],
            'memory' => ['avg' => 10.0, 'max' => 15.0],
            'queries' => ['avg' => 5.0],
        ];

        $this->mockBaseline->shouldReceive('getStatistics')
            ->with('test_migration')
            ->andReturn($stats);

        $currentMetrics = [
            'duration_ms' => 300.0, // Anomaly
            'memory_mb' => 35.0,    // Anomaly
            'query_count' => 10,    // Anomaly
        ];

        $result = $this->detector->detect('test_migration', $currentMetrics);

        expect($result['has_anomalies'])->toBeTrue()
            ->and($result['anomalies'])->toHaveCount(3)
            ->and($result['severity'])->toBeIn(['high', 'critical']);
    });

    it('skips rows anomaly detection when not present in current metrics', function () {
        $stats = [
            'duration' => ['avg' => 100.0, 'p95' => 150.0],
            'memory' => ['avg' => 10.0, 'max' => 15.0],
            'queries' => ['avg' => 5.0],
            'rows_affected' => ['avg' => 10.0],
        ];

        $this->mockBaseline->shouldReceive('getStatistics')
            ->with('test_migration')
            ->andReturn($stats);

        $currentMetrics = [
            'duration_ms' => 120.0,
            'memory_mb' => 11.0,
            'query_count' => 6,
            // No rows_affected
        ];

        $result = $this->detector->detect('test_migration', $currentMetrics);

        expect($result['has_anomalies'])->toBeFalse()
            ->and($result['anomalies'])->toBe([]);
    });
});

describe('calculateDeviation method', function () {
    it('calculates deviation correctly for positive change', function () {
        $reflection = new ReflectionClass($this->detector);
        $method = $reflection->getMethod('calculateDeviation');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, 150.0, 100.0);

        expect($result)->toBe(50.0);
    });

    it('calculates deviation correctly for negative change', function () {
        $reflection = new ReflectionClass($this->detector);
        $method = $reflection->getMethod('calculateDeviation');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, 75.0, 100.0);

        expect($result)->toBe(-25.0);
    });

    it('handles zero baseline correctly', function () {
        $reflection = new ReflectionClass($this->detector);
        $method = $reflection->getMethod('calculateDeviation');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, 100.0, 0.0);

        expect($result)->toBe(100.0);
    });

    it('returns zero when both values are zero', function () {
        $reflection = new ReflectionClass($this->detector);
        $method = $reflection->getMethod('calculateDeviation');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, 0.0, 0.0);

        expect($result)->toBe(0.0);
    });
});

describe('getSeverity method', function () {
    it('returns critical severity for very high duration deviation', function () {
        $reflection = new ReflectionClass($this->detector);
        $method = $reflection->getMethod('getSeverity');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, 250.0, 'duration');

        expect($result)->toBe('critical');
    });

    it('returns high severity for moderate duration deviation', function () {
        $reflection = new ReflectionClass($this->detector);
        $method = $reflection->getMethod('getSeverity');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, 150.0, 'duration');

        expect($result)->toBe('high');
    });

    it('returns medium severity for low duration deviation', function () {
        $reflection = new ReflectionClass($this->detector);
        $method = $reflection->getMethod('getSeverity');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, 75.0, 'duration');

        expect($result)->toBe('medium');
    });

    it('returns low severity for minimal duration deviation', function () {
        $reflection = new ReflectionClass($this->detector);
        $method = $reflection->getMethod('getSeverity');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, 25.0, 'duration');

        expect($result)->toBe('low');
    });

    it('handles memory type correctly', function () {
        $reflection = new ReflectionClass($this->detector);
        $method = $reflection->getMethod('getSeverity');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, 350.0, 'memory');

        expect($result)->toBe('critical');
    });

    it('handles query_count type correctly', function () {
        $reflection = new ReflectionClass($this->detector);
        $method = $reflection->getMethod('getSeverity');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, 120.0, 'query_count');

        expect($result)->toBe('critical');
    });

    it('falls back to duration thresholds for unknown type', function () {
        $reflection = new ReflectionClass($this->detector);
        $method = $reflection->getMethod('getSeverity');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, 250.0, 'unknown_type');

        expect($result)->toBe('critical');
    });
});

describe('calculateOverallSeverity method', function () {
    it('returns none for empty anomalies', function () {
        $reflection = new ReflectionClass($this->detector);
        $method = $reflection->getMethod('calculateOverallSeverity');
        $method->setAccessible(true);

        $result = $method->invoke($this->detector, []);

        expect($result)->toBe('none');
    });

    it('returns highest severity from multiple anomalies', function () {
        $reflection = new ReflectionClass($this->detector);
        $method = $reflection->getMethod('calculateOverallSeverity');
        $method->setAccessible(true);

        $anomalies = [
            ['severity' => 'low'],
            ['severity' => 'critical'],
            ['severity' => 'medium'],
        ];

        $result = $method->invoke($this->detector, $anomalies);

        expect($result)->toBe('critical');
    });

    it('handles anomalies without severity field', function () {
        $reflection = new ReflectionClass($this->detector);
        $method = $reflection->getMethod('calculateOverallSeverity');
        $method->setAccessible(true);

        $anomalies = [
            ['type' => 'duration'], // No severity field
            ['severity' => 'medium'],
        ];

        $result = $method->invoke($this->detector, $anomalies);

        expect($result)->toBe('medium');
    });
});

describe('getSummary method', function () {
    it('returns no anomalies message for empty array', function () {
        $result = $this->detector->getSummary([]);

        expect($result)->toBe('No anomalies detected');
    });

    it('returns correct summary for single anomaly', function () {
        $anomalies = [
            ['severity' => 'high'],
        ];

        $result = $this->detector->getSummary($anomalies);

        expect($result)->toBe('1 high anomaly');
    });

    it('returns correct summary for multiple anomalies of same severity', function () {
        $anomalies = [
            ['severity' => 'high'],
            ['severity' => 'high'],
        ];

        $result = $this->detector->getSummary($anomalies);

        expect($result)->toBe('2 high anomalies');
    });

    it('returns correct summary for mixed severity anomalies', function () {
        $anomalies = [
            ['severity' => 'critical'],
            ['severity' => 'high'],
            ['severity' => 'high'],
            ['severity' => 'medium'],
        ];

        $result = $this->detector->getSummary($anomalies);

        expect($result)->toContain('1 critical')
            ->and($result)->toContain('2 high')
            ->and($result)->toContain('1 medium')
            ->and($result)->toContain('anomalies');
    });

    it('handles anomalies without severity field', function () {
        $anomalies = [
            ['type' => 'duration'],
            ['severity' => 'high'],
        ];

        $result = $this->detector->getSummary($anomalies);

        expect($result)->toContain('anomalies');
    });
});

describe('detectDurationAnomaly method', function () {
    it('returns null when current duration is below P95', function () {
        $reflection = new ReflectionClass($this->detector);
        $method = $reflection->getMethod('detectDurationAnomaly');
        $method->setAccessible(true);

        $current = ['duration_ms' => 100.0];
        $stats = [
            'duration' => ['avg' => 80.0, 'p95' => 150.0],
        ];

        $result = $method->invoke($this->detector, $current, $stats);

        expect($result)->toBeNull();
    });

    it('returns null when deviation is below threshold even if exceeding P95', function () {
        $reflection = new ReflectionClass($this->detector);
        $method = $reflection->getMethod('detectDurationAnomaly');
        $method->setAccessible(true);

        $current = ['duration_ms' => 160.0];
        $stats = [
            'duration' => ['avg' => 150.0, 'p95' => 155.0],
        ];

        $result = $method->invoke($this->detector, $current, $stats);

        expect($result)->toBeNull();
    });

    it('returns anomaly when exceeding both P95 and deviation threshold', function () {
        $reflection = new ReflectionClass($this->detector);
        $method = $reflection->getMethod('detectDurationAnomaly');
        $method->setAccessible(true);

        $current = ['duration_ms' => 300.0];
        $stats = [
            'duration' => ['avg' => 100.0, 'p95' => 150.0],
        ];

        $result = $method->invoke($this->detector, $current, $stats);

        expect($result)->not->toBeNull()
            ->and($result['type'])->toBe('duration')
            ->and($result['current_value'])->toBe(300.0)
            ->and($result['baseline_avg'])->toBe(100.0)
            ->and($result['baseline_p95'])->toBe(150.0);
    });
});

describe('detectMemoryAnomaly method', function () {
    it('returns null when deviation is below threshold', function () {
        $reflection = new ReflectionClass($this->detector);
        $method = $reflection->getMethod('detectMemoryAnomaly');
        $method->setAccessible(true);

        $current = ['memory_mb' => 11.0];
        $stats = [
            'memory' => ['avg' => 10.0, 'max' => 15.0],
        ];

        $result = $method->invoke($this->detector, $current, $stats);

        expect($result)->toBeNull();
    });

    it('returns anomaly when exceeding deviation threshold', function () {
        $reflection = new ReflectionClass($this->detector);
        $method = $reflection->getMethod('detectMemoryAnomaly');
        $method->setAccessible(true);

        $current = ['memory_mb' => 35.0];
        $stats = [
            'memory' => ['avg' => 10.0, 'max' => 15.0],
        ];

        $result = $method->invoke($this->detector, $current, $stats);

        expect($result)->not->toBeNull()
            ->and($result['type'])->toBe('memory')
            ->and($result['current_value'])->toBe(35.0)
            ->and($result['baseline_avg'])->toBe(10.0);
    });
});

describe('detectQueryAnomaly method', function () {
    it('returns null when deviation is below threshold', function () {
        $reflection = new ReflectionClass($this->detector);
        $method = $reflection->getMethod('detectQueryAnomaly');
        $method->setAccessible(true);

        $current = ['query_count' => 6];
        $stats = [
            'queries' => ['avg' => 5.0],
        ];

        $result = $method->invoke($this->detector, $current, $stats);

        expect($result)->toBeNull();
    });

    it('returns anomaly when exceeding deviation threshold', function () {
        $reflection = new ReflectionClass($this->detector);
        $method = $reflection->getMethod('detectQueryAnomaly');
        $method->setAccessible(true);

        $current = ['query_count' => 10];
        $stats = [
            'queries' => ['avg' => 5.0],
        ];

        $result = $method->invoke($this->detector, $current, $stats);

        expect($result)->not->toBeNull()
            ->and($result['type'])->toBe('query_count')
            ->and($result['current_value'])->toBe(10);
    });
});

describe('detectRowsAnomaly method', function () {
    it('returns null when baseline does not track rows', function () {
        $reflection = new ReflectionClass($this->detector);
        $method = $reflection->getMethod('detectRowsAnomaly');
        $method->setAccessible(true);

        $current = ['rows_affected' => 100];
        $stats = [];

        $result = $method->invoke($this->detector, $current, $stats);

        expect($result)->toBeNull();
    });

    it('returns null when rows affected is below multiplier threshold', function () {
        $reflection = new ReflectionClass($this->detector);
        $method = $reflection->getMethod('detectRowsAnomaly');
        $method->setAccessible(true);

        $current = ['rows_affected' => 50];
        $stats = [
            'rows_affected' => ['avg' => 10.0],
        ];

        $result = $method->invoke($this->detector, $current, $stats);

        expect($result)->toBeNull();
    });

    it('returns anomaly when exceeding multiplier threshold', function () {
        $reflection = new ReflectionClass($this->detector);
        $method = $reflection->getMethod('detectRowsAnomaly');
        $method->setAccessible(true);

        $current = ['rows_affected' => 150];
        $stats = [
            'rows_affected' => ['avg' => 10.0],
        ];

        $result = $method->invoke($this->detector, $current, $stats);

        expect($result)->not->toBeNull()
            ->and($result['type'])->toBe('rows_affected')
            ->and($result['current_value'])->toBe(150)
            ->and($result['severity'])->toBe('high');
    });

    it('returns null when baseline avg is zero', function () {
        $reflection = new ReflectionClass($this->detector);
        $method = $reflection->getMethod('detectRowsAnomaly');
        $method->setAccessible(true);

        $current = ['rows_affected' => 100];
        $stats = [
            'rows_affected' => ['avg' => 0],
        ];

        $result = $method->invoke($this->detector, $current, $stats);

        expect($result)->toBeNull();
    });
});
