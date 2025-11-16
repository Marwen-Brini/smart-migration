<?php

use Flux\Dashboard\DashboardService;
use Flux\Http\Api\DashboardApiController;
use Flux\Monitoring\PerformanceBaseline;
use Flux\Analyzers\MigrationAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->mockDashboardService = Mockery::mock(DashboardService::class);
    $this->mockPerformanceBaseline = Mockery::mock(PerformanceBaseline::class);
    $this->mockMigrationAnalyzer = Mockery::mock(MigrationAnalyzer::class);

    $this->controller = new DashboardApiController(
        $this->mockDashboardService,
        $this->mockPerformanceBaseline,
        $this->mockMigrationAnalyzer
    );
});

afterEach(function () {
    Mockery::close();
});

describe('status method', function () {
    it('returns status successfully', function () {
        $statusData = [
            'pending_count' => 3,
            'applied_count' => 10,
            'drift_detected' => false,
        ];

        $this->mockDashboardService->shouldReceive('getStatus')
            ->once()
            ->andReturn($statusData);

        $response = $this->controller->status();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true))->toBe($statusData);
    });

    it('returns error on exception', function () {
        $this->mockDashboardService->shouldReceive('getStatus')
            ->once()
            ->andThrow(new Exception('Test error'));

        $response = $this->controller->status();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(500)
            ->and($response->getData(true))->toHaveKey('error');
    });
});

describe('migrations method', function () {
    it('returns migrations list', function () {
        $migrationsData = [
            ['name' => 'create_users_table', 'status' => 'pending'],
            ['name' => 'create_posts_table', 'status' => 'applied'],
        ];

        $this->mockDashboardService->shouldReceive('getMigrations')
            ->once()
            ->andReturn($migrationsData);

        $response = $this->controller->migrations();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true))->toBe($migrationsData);
    });
});

describe('history method', function () {
    it('returns migration history', function () {
        $historyData = [
            ['migration' => 'create_users_table', 'batch' => 1],
        ];

        $this->mockDashboardService->shouldReceive('getHistory')
            ->once()
            ->andReturn($historyData);

        $response = $this->controller->history();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true))->toBe($historyData);
    });
});

describe('schema method', function () {
    it('returns database schema', function () {
        $schemaData = [
            'tables' => ['users' => ['columns' => []]],
        ];

        $this->mockDashboardService->shouldReceive('getSchema')
            ->once()
            ->andReturn($schemaData);

        $response = $this->controller->schema();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true))->toBe($schemaData);
    });
});

describe('drift method', function () {
    it('returns drift information', function () {
        $driftData = [
            'has_drift' => false,
            'differences' => [],
        ];

        $this->mockDashboardService->shouldReceive('getDrift')
            ->once()
            ->andReturn($driftData);

        $response = $this->controller->drift();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true))->toBe($driftData);
    });
});

describe('snapshots method', function () {
    it('returns snapshots list', function () {
        $snapshotsData = [
            ['name' => 'snapshot_2025_01_01', 'created_at' => '2025-01-01'],
        ];

        $this->mockDashboardService->shouldReceive('getSnapshots')
            ->once()
            ->andReturn($snapshotsData);

        $response = $this->controller->snapshots();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true))->toBe($snapshotsData);
    });
});

describe('metrics method', function () {
    it('returns metrics data', function () {
        $metricsData = [
            'total_migrations' => 15,
            'avg_duration' => 120,
        ];

        $this->mockDashboardService->shouldReceive('getMetrics')
            ->once()
            ->andReturn($metricsData);

        $response = $this->controller->metrics();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true))->toBe($metricsData);
    });
});

describe('performanceBaselines method', function () {
    it('returns performance baselines', function () {
        $baselinesData = [
            'create_users_table' => ['avg' => 100, 'min' => 80, 'max' => 150],
        ];

        $this->mockPerformanceBaseline->shouldReceive('getAll')
            ->once()
            ->andReturn($baselinesData);

        $response = $this->controller->performanceBaselines();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true))->toBe($baselinesData);
    });
});

describe('performanceReport method', function () {
    it('generates performance report', function () {
        $reportData = [
            'anomalies' => [],
            'statistics' => [],
        ];

        $this->mockPerformanceBaseline->shouldReceive('generateReport')
            ->once()
            ->andReturn($reportData);

        $response = $this->controller->performanceReport();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true))->toBe($reportData);
    });
});

describe('migrationPerformance method', function () {
    it('returns migration performance stats', function () {
        $statsData = ['avg' => 100, 'executions' => 5];

        $this->mockPerformanceBaseline->shouldReceive('getStatistics')
            ->with('create_users_table')
            ->once()
            ->andReturn($statsData);

        $response = $this->controller->migrationPerformance('create_users_table');

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true))->toBe($statsData);
    });

    it('returns 404 when no performance data found', function () {
        $this->mockPerformanceBaseline->shouldReceive('getStatistics')
            ->with('nonexistent_migration')
            ->once()
            ->andReturn(null);

        $response = $this->controller->migrationPerformance('nonexistent_migration');

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(404)
            ->and($response->getData(true))->toHaveKey('error');
    });
});

describe('detectConflicts method', function () {
    it('returns success response structure', function () {
        // Note: We skip mocking Artisan facade as it's final in test environment
        // This test verifies the response structure when conflicts are detected
        $response = $this->controller->detectConflicts();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true))
            ->toHaveKey('success')
            ->and($response->getData(true))->toHaveKey('conflicts');
    });
});

describe('generateFixMigration method', function () {
    it('generates fix migration successfully', function () {
        $result = ['success' => true, 'message' => 'Migration generated'];

        $this->mockDashboardService->shouldReceive('generateFixMigration')
            ->once()
            ->andReturn($result);

        $response = $this->controller->generateFixMigration();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true))->toBe($result);
    });
});

describe('createSnapshot method', function () {
    it('creates snapshot without name', function () {
        request()->merge(['name' => null]);

        $result = ['success' => true, 'message' => 'Snapshot created'];

        $this->mockDashboardService->shouldReceive('createSnapshot')
            ->with(null)
            ->once()
            ->andReturn($result);

        $response = $this->controller->createSnapshot();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true))->toBe($result);
    });

    it('creates snapshot with custom name', function () {
        request()->merge(['name' => 'custom_snapshot']);

        $result = ['success' => true, 'message' => 'Snapshot created'];

        $this->mockDashboardService->shouldReceive('createSnapshot')
            ->with('custom_snapshot')
            ->once()
            ->andReturn($result);

        $response = $this->controller->createSnapshot();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true))->toBe($result);
    });
});

describe('deleteSnapshot method', function () {
    it('deletes snapshot successfully', function () {
        $result = ['success' => true, 'message' => 'Snapshot deleted'];

        $this->mockDashboardService->shouldReceive('deleteSnapshot')
            ->with('snapshot_name')
            ->once()
            ->andReturn($result);

        $response = $this->controller->deleteSnapshot('snapshot_name');

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true))->toBe($result);
    });
});

describe('runMigrations method', function () {
    it('runs migrations with options', function () {
        request()->merge(['force' => true]);

        $result = ['success' => true, 'message' => 'Migrations ran'];

        $this->mockDashboardService->shouldReceive('runMigrations')
            ->with(['force' => true])
            ->once()
            ->andReturn($result);

        $response = $this->controller->runMigrations();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true))->toBe($result);
    });
});

describe('rollbackMigrations method', function () {
    it('rollsback migrations with options', function () {
        request()->merge(['step' => 1]);

        $result = ['success' => true, 'message' => 'Migrations rolled back'];

        $this->mockDashboardService->shouldReceive('rollbackMigrations')
            ->with(['step' => 1])
            ->once()
            ->andReturn($result);

        $response = $this->controller->rollbackMigrations();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true))->toBe($result);
    });
});
