<?php

use Flux\Monitoring\PerformanceBaseline;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Mock File facade
    File::shouldReceive('exists')->byDefault()->andReturn(false);
    File::shouldReceive('get')->byDefault()->andReturn('{}');
    File::shouldReceive('put')->byDefault()->andReturnNull();
    File::shouldReceive('makeDirectory')->byDefault()->andReturnTrue();

    $this->baseline = new PerformanceBaseline();
});

afterEach(function () {
    Mockery::close();
});

describe('record method', function () {
    it('records first run for a migration', function () {
        $metrics = [
            'duration_ms' => 100.5,
            'memory_mb' => 10.2,
            'query_count' => 5,
        ];

        $this->baseline->record('2024_01_01_000000_create_users', $metrics);

        $stats = $this->baseline->getStatistics('2024_01_01_000000_create_users');

        expect($stats)->not->toBeNull()
            ->and($stats['total_runs'])->toBe(1)
            ->and($stats['duration']['avg'])->toBe(100.5)
            ->and($stats['memory']['avg'])->toBe(10.2);
    });

    it('records multiple runs and updates statistics', function () {
        $migration = '2024_01_01_000000_create_users';

        $this->baseline->record($migration, [
            'duration_ms' => 100.0,
            'memory_mb' => 10.0,
            'query_count' => 5,
        ]);

        $this->baseline->record($migration, [
            'duration_ms' => 120.0,
            'memory_mb' => 12.0,
            'query_count' => 6,
        ]);

        $stats = $this->baseline->getStatistics($migration);

        expect($stats['total_runs'])->toBe(2)
            ->and($stats['duration']['min'])->toBe(100.0)
            ->and($stats['duration']['max'])->toBe(120.0)
            ->and($stats['duration']['avg'])->toBe(110.0);
    });

    it('keeps only last 100 runs', function () {
        $migration = '2024_01_01_000000_test_migration';

        // Record 105 runs
        for ($i = 1; $i <= 105; $i++) {
            $this->baseline->record($migration, [
                'duration_ms' => 100.0 + $i,
                'memory_mb' => 10.0,
                'query_count' => 5,
            ]);
        }

        $stats = $this->baseline->getStatistics($migration);

        expect($stats['total_runs'])->toBe(100);
    });
});

describe('checkDeviation method', function () {
    it('returns no baseline message when no data exists', function () {
        $result = $this->baseline->checkDeviation('non_existent_migration', [
            'duration_ms' => 100.0,
            'memory_mb' => 10.0,
            'query_count' => 5,
        ]);

        expect($result['has_baseline'])->toBeFalse()
            ->and($result['message'])->toContain('No baseline available');
    });

    it('detects no deviations when metrics are within normal range', function () {
        $migration = '2024_01_01_000000_create_users';

        // Build baseline
        $this->baseline->record($migration, [
            'duration_ms' => 100.0,
            'memory_mb' => 10.0,
            'query_count' => 5,
        ]);

        // Check deviation with similar metrics
        $result = $this->baseline->checkDeviation($migration, [
            'duration_ms' => 110.0, // 10% slower - below 50% threshold
            'memory_mb' => 11.0,    // 10% more - below 100% threshold
            'query_count' => 5,     // Same - below 25% threshold
        ]);

        expect($result['has_baseline'])->toBeTrue()
            ->and($result['has_deviations'])->toBeFalse()
            ->and($result['deviations'])->toBe([]);
    });

    it('detects duration deviation when exceeding threshold', function () {
        $migration = '2024_01_01_000000_create_users';

        $this->baseline->record($migration, [
            'duration_ms' => 100.0,
            'memory_mb' => 10.0,
            'query_count' => 5,
        ]);

        $result = $this->baseline->checkDeviation($migration, [
            'duration_ms' => 200.0, // 100% slower
            'memory_mb' => 10.0,
            'query_count' => 5,
        ]);

        expect($result['has_baseline'])->toBeTrue()
            ->and($result['has_deviations'])->toBeTrue()
            ->and($result['deviations'])->toHaveCount(1)
            ->and($result['deviations'][0]['metric'])->toBe('duration')
            ->and($result['deviations'][0]['severity'])->toBeIn(['medium', 'high', 'critical']);
    });

    it('detects memory deviation when exceeding threshold', function () {
        $migration = '2024_01_01_000000_create_users';

        $this->baseline->record($migration, [
            'duration_ms' => 100.0,
            'memory_mb' => 10.0,
            'query_count' => 5,
        ]);

        $result = $this->baseline->checkDeviation($migration, [
            'duration_ms' => 100.0,
            'memory_mb' => 35.0, // 250% more
            'query_count' => 5,
        ]);

        expect($result['has_baseline'])->toBeTrue()
            ->and($result['has_deviations'])->toBeTrue()
            ->and($result['deviations'])->toHaveCount(1)
            ->and($result['deviations'][0]['metric'])->toBe('memory');
    });

    it('detects query count deviation when exceeding threshold', function () {
        $migration = '2024_01_01_000000_create_users';

        $this->baseline->record($migration, [
            'duration_ms' => 100.0,
            'memory_mb' => 10.0,
            'query_count' => 5,
        ]);

        $result = $this->baseline->checkDeviation($migration, [
            'duration_ms' => 100.0,
            'memory_mb' => 10.0,
            'query_count' => 10, // 100% more
        ]);

        expect($result['has_baseline'])->toBeTrue()
            ->and($result['has_deviations'])->toBeTrue()
            ->and($result['deviations'])->toHaveCount(1)
            ->and($result['deviations'][0]['metric'])->toBe('query_count');
    });

    it('detects multiple deviations simultaneously', function () {
        $migration = '2024_01_01_000000_create_users';

        $this->baseline->record($migration, [
            'duration_ms' => 100.0,
            'memory_mb' => 10.0,
            'query_count' => 5,
        ]);

        $result = $this->baseline->checkDeviation($migration, [
            'duration_ms' => 200.0, // Deviation
            'memory_mb' => 35.0,    // Deviation
            'query_count' => 10,    // Deviation
        ]);

        expect($result['has_baseline'])->toBeTrue()
            ->and($result['has_deviations'])->toBeTrue()
            ->and($result['deviations'])->toHaveCount(3);
    });
});

describe('getStatistics method', function () {
    it('returns null for non-existent migration', function () {
        $result = $this->baseline->getStatistics('non_existent');

        expect($result)->toBeNull();
    });

    it('returns statistics for existing migration', function () {
        $migration = '2024_01_01_000000_create_users';

        $this->baseline->record($migration, [
            'duration_ms' => 100.0,
            'memory_mb' => 10.0,
            'query_count' => 5,
        ]);

        $stats = $this->baseline->getStatistics($migration);

        expect($stats)->not->toBeNull()
            ->and($stats)->toHaveKeys(['total_runs', 'duration', 'memory', 'queries', 'last_updated']);
    });
});

describe('getAll method', function () {
    it('returns empty array when no baselines recorded', function () {
        $result = $this->baseline->getAll();

        expect($result)->toBe([]);
    });

    it('returns all baselines when data exists', function () {
        $this->baseline->record('migration1', [
            'duration_ms' => 100.0,
            'memory_mb' => 10.0,
            'query_count' => 5,
        ]);

        $this->baseline->record('migration2', [
            'duration_ms' => 150.0,
            'memory_mb' => 15.0,
            'query_count' => 7,
        ]);

        $result = $this->baseline->getAll();

        expect($result)->toHaveCount(2);
    });
});

describe('reset method', function () {
    it('removes specific migration baseline', function () {
        $migration = '2024_01_01_000000_create_users';

        $this->baseline->record($migration, [
            'duration_ms' => 100.0,
            'memory_mb' => 10.0,
            'query_count' => 5,
        ]);

        $this->baseline->reset($migration);

        $stats = $this->baseline->getStatistics($migration);

        expect($stats)->toBeNull();
    });
});

describe('resetAll method', function () {
    it('removes all baselines', function () {
        $this->baseline->record('migration1', [
            'duration_ms' => 100.0,
            'memory_mb' => 10.0,
            'query_count' => 5,
        ]);

        $this->baseline->record('migration2', [
            'duration_ms' => 150.0,
            'memory_mb' => 15.0,
            'query_count' => 7,
        ]);

        $this->baseline->resetAll();

        $result = $this->baseline->getAll();

        expect($result)->toBe([]);
    });
});

describe('median calculation', function () {
    it('calculates median for odd number of values', function () {
        $migration = '2024_01_01_000000_create_users';

        $this->baseline->record($migration, ['duration_ms' => 100.0, 'memory_mb' => 10.0, 'query_count' => 5]);
        $this->baseline->record($migration, ['duration_ms' => 150.0, 'memory_mb' => 10.0, 'query_count' => 5]);
        $this->baseline->record($migration, ['duration_ms' => 200.0, 'memory_mb' => 10.0, 'query_count' => 5]);

        $stats = $this->baseline->getStatistics($migration);

        expect($stats['duration']['median'])->toBe(150.0);
    });

    it('calculates median for even number of values', function () {
        $migration = '2024_01_01_000000_create_users';

        $this->baseline->record($migration, ['duration_ms' => 100.0, 'memory_mb' => 10.0, 'query_count' => 5]);
        $this->baseline->record($migration, ['duration_ms' => 150.0, 'memory_mb' => 10.0, 'query_count' => 5]);
        $this->baseline->record($migration, ['duration_ms' => 200.0, 'memory_mb' => 10.0, 'query_count' => 5]);
        $this->baseline->record($migration, ['duration_ms' => 250.0, 'memory_mb' => 10.0, 'query_count' => 5]);

        $stats = $this->baseline->getStatistics($migration);

        expect($stats['duration']['median'])->toBe(175.0);
    });

    it('returns zero for empty values', function () {
        $reflection = new ReflectionClass($this->baseline);
        $method = $reflection->getMethod('median');
        $method->setAccessible(true);

        $result = $method->invoke($this->baseline, []);

        expect($result)->toBe(0.0);
    });
});

describe('percentile calculation', function () {
    it('calculates P95 correctly', function () {
        $migration = '2024_01_01_000000_create_users';

        // Record 100 runs with predictable values
        for ($i = 1; $i <= 100; $i++) {
            $this->baseline->record($migration, [
                'duration_ms' => (float) $i,
                'memory_mb' => 10.0,
                'query_count' => 5,
            ]);
        }

        $stats = $this->baseline->getStatistics($migration);

        expect($stats['duration']['p95'])->toBeGreaterThan(90.0)
            ->and($stats['duration']['p95'])->toBeLessThanOrEqual(100.0);
    });

    it('calculates P99 correctly', function () {
        $migration = '2024_01_01_000000_create_users';

        for ($i = 1; $i <= 100; $i++) {
            $this->baseline->record($migration, [
                'duration_ms' => (float) $i,
                'memory_mb' => 10.0,
                'query_count' => 5,
            ]);
        }

        $stats = $this->baseline->getStatistics($migration);

        expect($stats['duration']['p99'])->toBeGreaterThan(95.0)
            ->and($stats['duration']['p99'])->toBeLessThanOrEqual(100.0);
    });

    it('returns zero for empty values', function () {
        $reflection = new ReflectionClass($this->baseline);
        $method = $reflection->getMethod('percentile');
        $method->setAccessible(true);

        $result = $method->invoke($this->baseline, [], 95);

        expect($result)->toBe(0.0);
    });
});

describe('calculateDeviation method', function () {
    it('calculates positive deviation correctly', function () {
        $reflection = new ReflectionClass($this->baseline);
        $method = $reflection->getMethod('calculateDeviation');
        $method->setAccessible(true);

        $result = $method->invoke($this->baseline, 150.0, 100.0);

        expect($result)->toBe(50.0);
    });

    it('calculates negative deviation correctly', function () {
        $reflection = new ReflectionClass($this->baseline);
        $method = $reflection->getMethod('calculateDeviation');
        $method->setAccessible(true);

        $result = $method->invoke($this->baseline, 75.0, 100.0);

        expect($result)->toBe(-25.0);
    });

    it('handles zero baseline correctly', function () {
        $reflection = new ReflectionClass($this->baseline);
        $method = $reflection->getMethod('calculateDeviation');
        $method->setAccessible(true);

        $result = $method->invoke($this->baseline, 100.0, 0.0);

        expect($result)->toBe(0.0);
    });
});

describe('getSeverity method', function () {
    it('returns critical for very high deviation', function () {
        $reflection = new ReflectionClass($this->baseline);
        $method = $reflection->getMethod('getSeverity');
        $method->setAccessible(true);

        $result = $method->invoke($this->baseline, 250.0);

        expect($result)->toBe('critical');
    });

    it('returns high for high deviation', function () {
        $reflection = new ReflectionClass($this->baseline);
        $method = $reflection->getMethod('getSeverity');
        $method->setAccessible(true);

        $result = $method->invoke($this->baseline, 150.0);

        expect($result)->toBe('high');
    });

    it('returns medium for medium deviation', function () {
        $reflection = new ReflectionClass($this->baseline);
        $method = $reflection->getMethod('getSeverity');
        $method->setAccessible(true);

        $result = $method->invoke($this->baseline, 75.0);

        expect($result)->toBe('medium');
    });

    it('returns low for low deviation', function () {
        $reflection = new ReflectionClass($this->baseline);
        $method = $reflection->getMethod('getSeverity');
        $method->setAccessible(true);

        $result = $method->invoke($this->baseline, 25.0);

        expect($result)->toBe('low');
    });
});

describe('generateReport method', function () {
    it('generates empty report when no baselines exist', function () {
        $report = $this->baseline->generateReport();

        expect($report['total_migrations_tracked'])->toBe(0)
            ->and($report['migrations'])->toBe([])
            ->and($report['summary']['total_runs'])->toBe(0);
    });

    it('generates report with migration data', function () {
        $this->baseline->record('migration1', [
            'duration_ms' => 100.0,
            'memory_mb' => 10.0,
            'query_count' => 5,
        ]);

        $this->baseline->record('migration2', [
            'duration_ms' => 200.0,
            'memory_mb' => 20.0,
            'query_count' => 10,
        ]);

        $report = $this->baseline->generateReport();

        expect($report['total_migrations_tracked'])->toBe(2)
            ->and($report['migrations'])->toHaveCount(2)
            ->and($report['summary']['total_runs'])->toBe(2)
            ->and($report['summary']['avg_duration_ms'])->toBeGreaterThan(0);
    });

    it('includes slowest migrations in report', function () {
        for ($i = 1; $i <= 10; $i++) {
            $this->baseline->record("migration{$i}", [
                'duration_ms' => (float) ($i * 100),
                'memory_mb' => 10.0,
                'query_count' => 5,
            ]);
        }

        $report = $this->baseline->generateReport();

        expect($report['summary']['slowest_migrations'])->toHaveCount(5)
            ->and($report['summary']['slowest_migrations'][0]['duration']['avg'])->toBeGreaterThan(
                $report['summary']['slowest_migrations'][4]['duration']['avg']
            );
    });

    it('includes most memory intensive migrations in report', function () {
        for ($i = 1; $i <= 10; $i++) {
            $this->baseline->record("migration{$i}", [
                'duration_ms' => 100.0,
                'memory_mb' => (float) ($i * 10),
                'query_count' => 5,
            ]);
        }

        $report = $this->baseline->generateReport();

        expect($report['summary']['most_memory_intensive'])->toHaveCount(5)
            ->and($report['summary']['most_memory_intensive'][0]['memory']['avg'])->toBeGreaterThan(
                $report['summary']['most_memory_intensive'][4]['memory']['avg']
            );
    });

    it('skips entries without statistics in report', function () {
        // Use reflection to directly set a baseline entry without statistics
        $reflection = new ReflectionClass($this->baseline);
        $property = $reflection->getProperty('baseline');
        $property->setAccessible(true);

        // Set an entry that has no statistics
        $property->setValue($this->baseline, [
            'test_key' => [
                'migration' => 'test_migration',
                'runs' => [],
                // No 'statistics' key
            ],
        ]);

        $report = $this->baseline->generateReport();

        // The entry should be skipped
        expect($report['migrations'])->toBe([])
            ->and($report['summary']['total_runs'])->toBe(0);
    });
});

describe('updateStatistics method', function () {
    it('returns early when runs is empty', function () {
        // Use reflection to directly set a baseline entry with empty runs
        $reflection = new ReflectionClass($this->baseline);

        $property = $reflection->getProperty('baseline');
        $property->setAccessible(true);
        $property->setValue($this->baseline, [
            'test_key' => [
                'migration' => 'test_migration',
                'runs' => [],
            ],
        ]);

        $method = $reflection->getMethod('updateStatistics');
        $method->setAccessible(true);

        // Should not throw and should not create statistics
        $method->invoke($this->baseline, 'test_key');

        $baseline = $property->getValue($this->baseline);
        expect($baseline['test_key'])->not->toHaveKey('statistics');
    });
});
