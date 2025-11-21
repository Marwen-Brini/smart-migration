<?php

use Flux\Commands\BaselineCommand;
use Flux\Monitoring\PerformanceBaseline;

beforeEach(function () {
    $this->mockBaseline = Mockery::mock(PerformanceBaseline::class);

    $this->command = Mockery::mock(BaselineCommand::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    // Inject the mock baseline
    $reflection = new ReflectionClass($this->command);
    $property = $reflection->getProperty('baseline');
    $property->setAccessible(true);
    $property->setValue($this->command, $this->mockBaseline);

    // Mock output methods
    $this->command->shouldReceive('info')->andReturnNull()->byDefault();
    $this->command->shouldReceive('comment')->andReturnNull()->byDefault();
    $this->command->shouldReceive('warn')->andReturnNull()->byDefault();
    $this->command->shouldReceive('error')->andReturnNull()->byDefault();
    $this->command->shouldReceive('line')->andReturnNull()->byDefault();
    $this->command->shouldReceive('newLine')->andReturnNull()->byDefault();
});

afterEach(function () {
    Mockery::close();
});

describe('viewBaselines method', function () {
    it('displays message when no baselines exist', function () {
        $this->command->shouldReceive('argument')->with('migration')->andReturn(null);
        $this->command->shouldReceive('option')->with('json')->andReturn(false);

        $this->mockBaseline->shouldReceive('getAll')->andReturn([]);

        $this->command->shouldReceive('info')
            ->with('No performance baselines recorded yet.')
            ->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('viewBaselines');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        expect($result)->toBe(0);
    });

    it('displays baselines when they exist', function () {
        $this->command->shouldReceive('argument')->with('migration')->andReturn(null);
        $this->command->shouldReceive('option')->with('json')->andReturn(false);

        $baselines = [
            'migration1' => [
                'migration' => '2024_01_01_000000_create_users',
                'statistics' => [
                    'total_runs' => 5,
                    'duration' => ['avg' => 100.5, 'min' => 90.0, 'max' => 120.0, 'p95' => 115.0],
                    'memory' => ['avg' => 10.5, 'min' => 9.0, 'max' => 12.0],
                    'queries' => ['avg' => 10.0, 'min' => 8, 'max' => 12],
                ],
            ],
        ];

        $this->mockBaseline->shouldReceive('getAll')->andReturn($baselines);

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('viewBaselines');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        expect($result)->toBe(0);
    });

    it('outputs JSON when --json option is set', function () {
        $this->command->shouldReceive('argument')->with('migration')->andReturn(null);
        $this->command->shouldReceive('option')->with('json')->andReturn(true);

        $baselines = ['test' => ['data' => 'value']];

        $this->mockBaseline->shouldReceive('getAll')->andReturn($baselines);

        $this->command->shouldReceive('line')
            ->with(Mockery::type('string'))
            ->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('viewBaselines');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        expect($result)->toBe(0);
    });
});

describe('viewSingleBaseline method', function () {
    it('returns failure when baseline not found', function () {
        $this->command->shouldReceive('option')->with('json')->andReturn(false);

        $this->mockBaseline->shouldReceive('getStatistics')
            ->with('test_migration')
            ->andReturn(null);

        $this->command->shouldReceive('warn')
            ->with(Mockery::pattern('/No baseline data found/'))
            ->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('viewSingleBaseline');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'test_migration');

        expect($result)->toBe(1);
    });

    it('displays baseline when found', function () {
        $this->command->shouldReceive('option')->with('json')->andReturn(false);

        $stats = [
            'total_runs' => 5,
            'duration' => ['avg' => 100.0, 'min' => 90.0, 'max' => 110.0, 'median' => 100.0, 'p95' => 108.0, 'p99' => 109.0],
            'memory' => ['avg' => 10.0, 'min' => 9.0, 'max' => 11.0],
            'queries' => ['avg' => 5.0, 'min' => 4, 'max' => 6],
            'last_updated' => '2024-01-01 00:00:00',
        ];

        $this->mockBaseline->shouldReceive('getStatistics')
            ->with('test_migration')
            ->andReturn($stats);

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('viewSingleBaseline');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'test_migration');

        expect($result)->toBe(0);
    });

    it('outputs JSON for single baseline when --json is set', function () {
        $this->command->shouldReceive('option')->with('json')->andReturn(true);

        $stats = ['total_runs' => 5, 'duration' => ['avg' => 100.0]];

        $this->mockBaseline->shouldReceive('getStatistics')
            ->with('test_migration')
            ->andReturn($stats);

        $this->command->shouldReceive('line')
            ->with(Mockery::type('string'))
            ->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('viewSingleBaseline');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'test_migration');

        expect($result)->toBe(0);
    });
});

describe('displayBaselineSummary method', function () {
    it('displays baseline summary correctly', function () {
        $data = [
            'migration' => 'test_migration',
            'statistics' => [
                'total_runs' => 5,
                'duration' => ['avg' => 100.0, 'min' => 90.0, 'max' => 110.0, 'p95' => 108.0],
                'memory' => ['avg' => 10.0, 'min' => 9.0, 'max' => 11.0],
                'queries' => ['avg' => 5.0, 'min' => 4, 'max' => 6],
            ],
        ];

        $this->command->shouldReceive('comment')
            ->with('test_migration')
            ->once();

        $this->command->shouldReceive('line')
            ->with(Mockery::type('string'))
            ->atLeast()->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('displayBaselineSummary');
        $method->setAccessible(true);

        $method->invoke($this->command, $data);

        expect(true)->toBeTrue();
    });
});

describe('displayDetailedStats method', function () {
    it('displays detailed statistics', function () {
        $stats = [
            'total_runs' => 5,
            'duration' => ['avg' => 100.0, 'min' => 90.0, 'max' => 110.0, 'median' => 100.0, 'p95' => 108.0, 'p99' => 109.0],
            'memory' => ['avg' => 10.0, 'min' => 9.0, 'max' => 11.0],
            'queries' => ['avg' => 5.0, 'min' => 4, 'max' => 6],
            'last_updated' => '2024-01-01 00:00:00',
        ];

        $this->command->shouldReceive('info')
            ->with(Mockery::type('string'))
            ->atLeast()->once();

        $this->command->shouldReceive('comment')
            ->with(Mockery::type('string'))
            ->atLeast()->once();

        $this->command->shouldReceive('line')
            ->with(Mockery::type('string'))
            ->atLeast()->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('displayDetailedStats');
        $method->setAccessible(true);

        $method->invoke($this->command, $stats);

        expect(true)->toBeTrue();
    });
});

describe('resetBaselines method', function () {
    it('resets single migration baseline when confirmed', function () {
        $this->command->shouldReceive('argument')->with('migration')->andReturn('test_migration');
        $this->command->shouldReceive('confirm')
            ->with(Mockery::type('string'), false)
            ->andReturn(true);

        $this->mockBaseline->shouldReceive('reset')
            ->with('test_migration')
            ->once();

        $this->command->shouldReceive('info')
            ->with(Mockery::pattern('/Baseline reset for/'))
            ->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('resetBaselines');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        expect($result)->toBe(0);
    });

    it('cancels when not confirmed', function () {
        $this->command->shouldReceive('argument')->with('migration')->andReturn('test_migration');
        $this->command->shouldReceive('confirm')
            ->with(Mockery::type('string'), false)
            ->andReturn(false);

        $this->command->shouldReceive('info')
            ->with('Cancelled.')
            ->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('resetBaselines');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        expect($result)->toBe(0);
    });

    it('resets all baselines when confirmed', function () {
        $this->command->shouldReceive('argument')->with('migration')->andReturn(null);
        $this->command->shouldReceive('confirm')
            ->with(Mockery::type('string'), false)
            ->andReturn(true);

        $this->mockBaseline->shouldReceive('resetAll')
            ->once();

        $this->command->shouldReceive('info')
            ->with(Mockery::pattern('/All baselines/'))
            ->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('resetBaselines');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        expect($result)->toBe(0);
    });
});

describe('generateReport method', function () {
    it('generates and displays report', function () {
        $this->command->shouldReceive('option')->with('json')->andReturn(false);

        $report = [
            'total_migrations_tracked' => 5,
            'summary' => [
                'total_runs' => 25,
                'avg_duration_ms' => 150.0,
                'slowest_migrations' => [
                    ['migration' => 'slow1', 'duration' => ['avg' => 200.0, 'max' => 250.0]],
                ],
                'most_memory_intensive' => [
                    ['migration' => 'memory1', 'memory' => ['avg' => 20.0, 'max' => 25.0]],
                ],
            ],
            'generated_at' => '2024-01-01 00:00:00',
        ];

        $this->mockBaseline->shouldReceive('generateReport')
            ->andReturn($report);

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('generateReport');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        expect($result)->toBe(0);
    });

    it('outputs JSON report when --json is set', function () {
        $this->command->shouldReceive('option')->with('json')->andReturn(true);

        $report = ['summary' => ['total_runs' => 10]];

        $this->mockBaseline->shouldReceive('generateReport')
            ->andReturn($report);

        $this->command->shouldReceive('line')
            ->with(Mockery::type('string'))
            ->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('generateReport');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        expect($result)->toBe(0);
    });
});

describe('exportBaselines method', function () {
    it('exports baselines to file', function () {
        $this->command->shouldReceive('option')->with('export')->andReturn('/tmp/test-baseline.json');

        $baselines = ['test' => ['data' => 'value']];

        $this->mockBaseline->shouldReceive('getAll')
            ->andReturn($baselines);

        $this->command->shouldReceive('info')
            ->with(Mockery::pattern('/Baselines exported to/'))
            ->once();

        $this->command->shouldReceive('comment')
            ->with(Mockery::pattern('/Total migrations/'))
            ->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('exportBaselines');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        // Cleanup
        if (file_exists('/tmp/test-baseline.json')) {
            unlink('/tmp/test-baseline.json');
        }

        expect($result)->toBe(0);
    });
});

describe('importBaselines method', function () {
    it('returns failure when file not found', function () {
        $this->command->shouldReceive('option')->with('import')->andReturn('/nonexistent/file.json');

        $this->command->shouldReceive('error')
            ->with(Mockery::pattern('/File not found/'))
            ->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('importBaselines');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        expect($result)->toBe(1);
    });

    it('returns failure when JSON is invalid', function () {
        $tmpFile = '/tmp/test-invalid.json';
        file_put_contents($tmpFile, 'invalid json');

        $this->command->shouldReceive('option')->with('import')->andReturn($tmpFile);

        $this->command->shouldReceive('error')
            ->with('Invalid JSON file')
            ->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('importBaselines');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        // Cleanup
        unlink($tmpFile);

        expect($result)->toBe(1);
    });

    it('shows warning for valid JSON file', function () {
        $tmpFile = '/tmp/test-valid.json';
        file_put_contents($tmpFile, json_encode(['test' => 'data']));

        $this->command->shouldReceive('option')->with('import')->andReturn($tmpFile);

        $this->command->shouldReceive('warn')
            ->with(Mockery::type('string'))
            ->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('importBaselines');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        // Cleanup
        unlink($tmpFile);

        expect($result)->toBe(1);
    });
});

describe('showHelp method', function () {
    it('displays help information', function () {
        $this->command->shouldReceive('info')
            ->with(Mockery::type('string'))
            ->atLeast()->once();

        $this->command->shouldReceive('comment')
            ->with(Mockery::type('string'))
            ->atLeast()->once();

        $this->command->shouldReceive('line')
            ->with(Mockery::type('string'))
            ->atLeast()->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('showHelp');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        expect($result)->toBe(0);
    });
});

describe('displayHeader method', function () {
    it('displays command header', function () {
        $this->command->shouldReceive('info')
            ->with(Mockery::type('string'))
            ->once();

        $this->command->shouldReceive('comment')
            ->with(Mockery::type('string'))
            ->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('displayHeader');
        $method->setAccessible(true);

        $method->invoke($this->command);

        expect(true)->toBeTrue();
    });
});

describe('handle method', function () {
    it('calls exportBaselines when --export option is set', function () {
        $this->command->shouldReceive('argument')->with('action')->andReturn(null);
        $this->command->shouldReceive('option')->with('export')->andReturn(true);
        $this->command->shouldReceive('option')->with('import')->andReturn(false);

        $this->command->shouldReceive('exportBaselines')->once()->andReturn(0);

        $result = $this->command->handle();

        expect($result)->toBe(0);
    });

    it('calls importBaselines when --import option is set', function () {
        $this->command->shouldReceive('argument')->with('action')->andReturn(null);
        $this->command->shouldReceive('option')->with('export')->andReturn(false);
        $this->command->shouldReceive('option')->with('import')->andReturn(true);

        $this->command->shouldReceive('importBaselines')->once()->andReturn(0);

        $result = $this->command->handle();

        expect($result)->toBe(0);
    });

    it('calls viewBaselines for view action', function () {
        $this->command->shouldReceive('argument')->with('action')->andReturn('view');
        $this->command->shouldReceive('option')->with('export')->andReturn(false);
        $this->command->shouldReceive('option')->with('import')->andReturn(false);

        $this->command->shouldReceive('viewBaselines')->once()->andReturn(0);

        $result = $this->command->handle();

        expect($result)->toBe(0);
    });

    it('calls resetBaselines for reset action', function () {
        $this->command->shouldReceive('argument')->with('action')->andReturn('reset');
        $this->command->shouldReceive('option')->with('export')->andReturn(false);
        $this->command->shouldReceive('option')->with('import')->andReturn(false);

        $this->command->shouldReceive('resetBaselines')->once()->andReturn(0);

        $result = $this->command->handle();

        expect($result)->toBe(0);
    });

    it('calls generateReport for report action', function () {
        $this->command->shouldReceive('argument')->with('action')->andReturn('report');
        $this->command->shouldReceive('option')->with('export')->andReturn(false);
        $this->command->shouldReceive('option')->with('import')->andReturn(false);

        $this->command->shouldReceive('generateReport')->once()->andReturn(0);

        $result = $this->command->handle();

        expect($result)->toBe(0);
    });

    it('calls showHelp for unknown action', function () {
        $this->command->shouldReceive('argument')->with('action')->andReturn('unknown');
        $this->command->shouldReceive('option')->with('export')->andReturn(false);
        $this->command->shouldReceive('option')->with('import')->andReturn(false);

        $this->command->shouldReceive('showHelp')->once()->andReturn(0);

        $result = $this->command->handle();

        expect($result)->toBe(0);
    });
});

describe('viewBaselines additional cases', function () {
    it('calls viewSingleBaseline when migration argument is provided', function () {
        $this->command->shouldReceive('argument')->with('migration')->andReturn('test_migration');

        $this->command->shouldReceive('viewSingleBaseline')
            ->with('test_migration')
            ->once()
            ->andReturn(0);

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('viewBaselines');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        expect($result)->toBe(0);
    });

    it('skips entries without statistics', function () {
        $this->command->shouldReceive('argument')->with('migration')->andReturn(null);
        $this->command->shouldReceive('option')->with('json')->andReturn(false);

        $baselines = [
            'migration1' => [
                'migration' => '2024_01_01_000000_create_users',
                // No 'statistics' key
            ],
        ];

        $this->mockBaseline->shouldReceive('getAll')->andReturn($baselines);

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('viewBaselines');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        expect($result)->toBe(0);
    });
});

describe('resetBaselines cancellation', function () {
    it('returns success when all reset is cancelled', function () {
        $this->command->shouldReceive('argument')->with('migration')->andReturn(null);
        $this->command->shouldReceive('confirm')
            ->with('Reset ALL performance baselines?', false)
            ->andReturn(false);

        $this->command->shouldReceive('info')
            ->with('Cancelled.')
            ->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('resetBaselines');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        expect($result)->toBe(0);
    });
});
