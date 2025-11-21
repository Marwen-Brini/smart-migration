<?php

use Flux\Dashboard\DashboardService;
use Flux\Http\Api\DashboardApiController;
use Flux\Monitoring\PerformanceBaseline;
use Flux\Analyzers\MigrationAnalyzer;
use Flux\Safety\SafeMigratorFactory;
use Flux\Safety\SafeMigrator;
use Flux\Support\ArtisanRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->mockDashboardService = Mockery::mock(DashboardService::class);
    $this->mockPerformanceBaseline = Mockery::mock(PerformanceBaseline::class);
    $this->mockMigrationAnalyzer = Mockery::mock(MigrationAnalyzer::class);
    $this->mockSafeMigratorFactory = Mockery::mock(SafeMigratorFactory::class);
    $this->mockArtisanRunner = Mockery::mock(ArtisanRunner::class);

    $this->controller = new DashboardApiController(
        $this->mockDashboardService,
        $this->mockPerformanceBaseline,
        $this->mockMigrationAnalyzer,
        $this->mockSafeMigratorFactory,
        $this->mockArtisanRunner
    );
});

afterEach(function () {
    Mockery::close();
});

describe('getSafeMigrator fallback', function () {
    it('uses App::make when factory is null', function () {
        // Create controller without SafeMigratorFactory
        $controllerWithoutFactory = new DashboardApiController(
            $this->mockDashboardService,
            $this->mockPerformanceBaseline,
            $this->mockMigrationAnalyzer,
            null, // No factory
            $this->mockArtisanRunner
        );

        // Create a temporary migration file
        $migrationPath = database_path('migrations');
        $migrationName = '2025_01_01_000006_test_fallback_migration';
        $file = $migrationPath . '/' . $migrationName . '.php';

        if (!is_dir($migrationPath)) {
            mkdir($migrationPath, 0755, true);
        }

        file_put_contents($file, '<?php class TestFallbackMigration {}');

        request()->merge(['migration' => $migrationName]);

        // Access protected method via reflection
        $reflection = new ReflectionClass($controllerWithoutFactory);
        $method = $reflection->getMethod('getSafeMigrator');
        $method->setAccessible(true);

        $safeMigrator = $method->invoke($controllerWithoutFactory);

        // Cleanup
        if (file_exists($file)) {
            unlink($file);
        }

        expect($safeMigrator)->toBeInstanceOf(SafeMigrator::class);
    });
});

describe('getArtisanRunner fallback', function () {
    it('uses App::make when artisanRunner is null', function () {
        // Create controller without ArtisanRunner
        $controllerWithoutRunner = new DashboardApiController(
            $this->mockDashboardService,
            $this->mockPerformanceBaseline,
            $this->mockMigrationAnalyzer,
            $this->mockSafeMigratorFactory,
            null // No runner
        );

        // Access protected method via reflection
        $reflection = new ReflectionClass($controllerWithoutRunner);
        $method = $reflection->getMethod('getArtisanRunner');
        $method->setAccessible(true);

        $artisanRunner = $method->invoke($controllerWithoutRunner);

        expect($artisanRunner)->toBeInstanceOf(ArtisanRunner::class);
    });
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

    it('returns error on exception', function () {
        $this->mockDashboardService->shouldReceive('getMigrations')
            ->once()
            ->andThrow(new Exception('Migrations error'));

        $response = $this->controller->migrations();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(500)
            ->and($response->getData(true)['error'])->toBe('Migrations error');
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

    it('returns error on exception', function () {
        $this->mockDashboardService->shouldReceive('getHistory')
            ->once()
            ->andThrow(new Exception('History error'));

        $response = $this->controller->history();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(500)
            ->and($response->getData(true)['error'])->toBe('History error');
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

    it('returns error on exception', function () {
        $this->mockDashboardService->shouldReceive('getSchema')
            ->once()
            ->andThrow(new Exception('Schema error'));

        $response = $this->controller->schema();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(500)
            ->and($response->getData(true)['error'])->toBe('Schema error');
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

    it('returns error on exception', function () {
        $this->mockDashboardService->shouldReceive('getDrift')
            ->once()
            ->andThrow(new Exception('Drift error'));

        $response = $this->controller->drift();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(500)
            ->and($response->getData(true)['error'])->toBe('Drift error');
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

    it('returns error on exception', function () {
        $this->mockDashboardService->shouldReceive('getSnapshots')
            ->once()
            ->andThrow(new Exception('Snapshots error'));

        $response = $this->controller->snapshots();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(500)
            ->and($response->getData(true)['error'])->toBe('Snapshots error');
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

    it('returns error on exception', function () {
        $this->mockDashboardService->shouldReceive('getMetrics')
            ->once()
            ->andThrow(new Exception('Metrics error'));

        $response = $this->controller->metrics();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(500)
            ->and($response->getData(true)['error'])->toBe('Metrics error');
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

    it('returns error on exception', function () {
        $this->mockPerformanceBaseline->shouldReceive('getAll')
            ->once()
            ->andThrow(new Exception('Baselines error'));

        $response = $this->controller->performanceBaselines();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(500)
            ->and($response->getData(true)['error'])->toBe('Baselines error');
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

    it('returns error on exception', function () {
        $this->mockPerformanceBaseline->shouldReceive('generateReport')
            ->once()
            ->andThrow(new Exception('Report error'));

        $response = $this->controller->performanceReport();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(500)
            ->and($response->getData(true)['error'])->toBe('Report error');
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

    it('returns error on exception', function () {
        $this->mockPerformanceBaseline->shouldReceive('getStatistics')
            ->with('test_migration')
            ->once()
            ->andThrow(new Exception('Performance error'));

        $response = $this->controller->migrationPerformance('test_migration');

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(500)
            ->and($response->getData(true)['error'])->toBe('Performance error');
    });
});

describe('detectConflicts method', function () {
    it('returns conflicts when detected', function () {
        $this->mockArtisanRunner->shouldReceive('call')
            ->with('migrate:conflicts', ['--json' => true])
            ->once()
            ->andReturn(0);

        $this->mockArtisanRunner->shouldReceive('output')
            ->once()
            ->andReturn('{"conflicts": [{"file": "test.php", "type": "duplicate"}]}');

        $response = $this->controller->detectConflicts();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true)['success'])->toBeTrue()
            ->and($response->getData(true)['conflicts'])->toHaveCount(1)
            ->and($response->getData(true)['total'])->toBe(1);
    });

    it('returns no conflicts when JSON parsing fails', function () {
        $this->mockArtisanRunner->shouldReceive('call')
            ->with('migrate:conflicts', ['--json' => true])
            ->once()
            ->andReturn(0);

        $this->mockArtisanRunner->shouldReceive('output')
            ->once()
            ->andReturn('Invalid JSON');

        $this->mockArtisanRunner->shouldReceive('call')
            ->with('migrate:conflicts')
            ->once()
            ->andReturn(0);

        $response = $this->controller->detectConflicts();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true)['success'])->toBeTrue()
            ->and($response->getData(true)['conflicts'])->toBe([])
            ->and($response->getData(true)['message'])->toBe('No conflicts detected');
    });

    it('returns error on exception', function () {
        $this->mockArtisanRunner->shouldReceive('call')
            ->andThrow(new Exception('Conflicts error'));

        $response = $this->controller->detectConflicts();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(500)
            ->and($response->getData(true)['success'])->toBeFalse()
            ->and($response->getData(true)['message'])->toBe('Conflicts error');
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

    it('returns error on exception', function () {
        $this->mockDashboardService->shouldReceive('generateFixMigration')
            ->once()
            ->andThrow(new Exception('Fix migration error'));

        $response = $this->controller->generateFixMigration();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(500)
            ->and($response->getData(true)['success'])->toBeFalse()
            ->and($response->getData(true)['message'])->toBe('Fix migration error');
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

    it('returns error on exception', function () {
        request()->merge(['name' => 'test']);

        $this->mockDashboardService->shouldReceive('createSnapshot')
            ->once()
            ->andThrow(new Exception('Snapshot error'));

        $response = $this->controller->createSnapshot();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(500)
            ->and($response->getData(true)['success'])->toBeFalse()
            ->and($response->getData(true)['message'])->toBe('Snapshot error');
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

    it('returns error on exception', function () {
        $this->mockDashboardService->shouldReceive('deleteSnapshot')
            ->with('snapshot_name')
            ->once()
            ->andThrow(new Exception('Delete error'));

        $response = $this->controller->deleteSnapshot('snapshot_name');

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(500)
            ->and($response->getData(true)['success'])->toBeFalse()
            ->and($response->getData(true)['message'])->toBe('Delete error');
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

    it('returns error on exception', function () {
        request()->merge(['force' => true]);

        $this->mockDashboardService->shouldReceive('runMigrations')
            ->once()
            ->andThrow(new Exception('Run migrations error'));

        $response = $this->controller->runMigrations();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(500)
            ->and($response->getData(true)['success'])->toBeFalse()
            ->and($response->getData(true)['message'])->toBe('Run migrations error');
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

    it('returns error on exception', function () {
        request()->merge(['step' => 1]);

        $this->mockDashboardService->shouldReceive('rollbackMigrations')
            ->once()
            ->andThrow(new Exception('Rollback error'));

        $response = $this->controller->rollbackMigrations();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(500)
            ->and($response->getData(true)['success'])->toBeFalse()
            ->and($response->getData(true)['message'])->toBe('Rollback error');
    });
});

describe('migrationPreview method', function () {
    it('returns 404 when migration file not found', function () {
        $response = $this->controller->migrationPreview('nonexistent_migration');

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(404)
            ->and($response->getData(true)['success'])->toBeFalse()
            ->and($response->getData(true)['message'])->toBe('Migration file not found');
    });

    it('returns analysis for existing migration', function () {
        // Create a temporary migration file
        $migrationPath = database_path('migrations');
        $migrationName = '2025_01_01_000000_test_preview_migration';
        $file = $migrationPath . '/' . $migrationName . '.php';

        if (!is_dir($migrationPath)) {
            mkdir($migrationPath, 0755, true);
        }

        file_put_contents($file, '<?php use Illuminate\Database\Migrations\Migration; class TestPreviewMigration extends Migration { public function up() {} public function down() {} }');

        $analysisResult = [
            'operations' => [
                ['type' => 'create_table', 'table' => 'users']
            ]
        ];

        $this->mockMigrationAnalyzer->shouldReceive('analyze')
            ->with($file)
            ->once()
            ->andReturn($analysisResult);

        $response = $this->controller->migrationPreview($migrationName);

        // Cleanup
        if (file_exists($file)) {
            unlink($file);
        }

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true)['success'])->toBeTrue()
            ->and($response->getData(true)['migration'])->toBe($migrationName)
            ->and($response->getData(true)['analysis'])->toBe($analysisResult);
    });

    it('returns error on analyzer exception', function () {
        // Create a temporary migration file
        $migrationPath = database_path('migrations');
        $migrationName = '2025_01_01_000001_test_exception_migration';
        $file = $migrationPath . '/' . $migrationName . '.php';

        if (!is_dir($migrationPath)) {
            mkdir($migrationPath, 0755, true);
        }

        file_put_contents($file, '<?php class TestExceptionMigration {}');

        $this->mockMigrationAnalyzer->shouldReceive('analyze')
            ->with($file)
            ->once()
            ->andThrow(new Exception('Analyzer error'));

        $response = $this->controller->migrationPreview($migrationName);

        // Cleanup
        if (file_exists($file)) {
            unlink($file);
        }

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(500)
            ->and($response->getData(true)['success'])->toBeFalse()
            ->and($response->getData(true)['message'])->toBe('Analyzer error');
    });
});

describe('runSafeMigration method', function () {
    it('returns 400 when migration name is missing', function () {
        request()->merge(['migration' => null]);

        $response = $this->controller->runSafeMigration();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(400)
            ->and($response->getData(true)['success'])->toBeFalse()
            ->and($response->getData(true)['message'])->toBe('Migration name is required');
    });

    it('returns 404 when migration file not found', function () {
        request()->merge(['migration' => 'nonexistent_migration']);

        $response = $this->controller->runSafeMigration();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(404)
            ->and($response->getData(true)['success'])->toBeFalse()
            ->and($response->getData(true)['message'])->toBe('Migration file not found');
    });

    it('runs migration successfully', function () {
        // Create a temporary migration file
        $migrationPath = database_path('migrations');
        $migrationName = '2025_01_01_000002_test_safe_migration';
        $file = $migrationPath . '/' . $migrationName . '.php';

        if (!is_dir($migrationPath)) {
            mkdir($migrationPath, 0755, true);
        }

        file_put_contents($file, '<?php class TestSafeMigration {}');

        request()->merge(['migration' => $migrationName]);

        // Mock repository
        $mockRepository = Mockery::mock();
        $mockRepository->shouldReceive('getNextBatchNumber')->once()->andReturn(1);

        // Mock SafeMigrator
        $mockSafeMigrator = Mockery::mock(SafeMigrator::class);
        $mockSafeMigrator->shouldReceive('getRepository')->andReturn($mockRepository);
        $mockSafeMigrator->shouldReceive('estimateDataLoss')->with($file)->once()->andReturn(['rows' => 0]);
        $mockSafeMigrator->shouldReceive('getAffectedTables')->with($file)->once()->andReturn(['users']);
        $mockSafeMigrator->shouldReceive('runSafe')->with($file, 1, false)->once();

        $this->mockSafeMigratorFactory->shouldReceive('create')
            ->once()
            ->andReturn($mockSafeMigrator);

        $response = $this->controller->runSafeMigration();

        // Cleanup
        if (file_exists($file)) {
            unlink($file);
        }

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true)['success'])->toBeTrue()
            ->and($response->getData(true)['migration'])->toBe($migrationName)
            ->and($response->getData(true)['affected_tables'])->toBe(['users']);
    });

    it('returns error on exception', function () {
        // Create a temporary migration file
        $migrationPath = database_path('migrations');
        $migrationName = '2025_01_01_000003_test_error_migration';
        $file = $migrationPath . '/' . $migrationName . '.php';

        if (!is_dir($migrationPath)) {
            mkdir($migrationPath, 0755, true);
        }

        file_put_contents($file, '<?php class TestErrorMigration {}');

        request()->merge(['migration' => $migrationName]);

        $this->mockSafeMigratorFactory->shouldReceive('create')
            ->once()
            ->andThrow(new Exception('Safe migration error'));

        $response = $this->controller->runSafeMigration();

        // Cleanup
        if (file_exists($file)) {
            unlink($file);
        }

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(500)
            ->and($response->getData(true)['success'])->toBeFalse()
            ->and($response->getData(true)['message'])->toBe('Safe migration error');
    });
});

describe('undoSafeMigration method', function () {
    it('returns 400 when no migrations to rollback', function () {
        request()->merge(['migration' => null]);

        // Mock repository with empty ran migrations
        $mockRepository = Mockery::mock();
        $mockRepository->shouldReceive('getRan')->once()->andReturn([]);

        // Mock SafeMigrator
        $mockSafeMigrator = Mockery::mock(SafeMigrator::class);
        $mockSafeMigrator->shouldReceive('getRepository')->andReturn($mockRepository);

        $this->mockSafeMigratorFactory->shouldReceive('create')
            ->once()
            ->andReturn($mockSafeMigrator);

        $response = $this->controller->undoSafeMigration();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(400)
            ->and($response->getData(true)['success'])->toBeFalse()
            ->and($response->getData(true)['message'])->toBe('No migrations to rollback');
    });

    it('rollsback migrations by step when no specific migration provided', function () {
        // Create a temporary migration file
        $migrationPath = database_path('migrations');
        $migrationName = '2025_01_01_000004_test_rollback_migration';
        $file = $migrationPath . '/' . $migrationName . '.php';

        if (!is_dir($migrationPath)) {
            mkdir($migrationPath, 0755, true);
        }

        file_put_contents($file, '<?php class TestRollbackMigration {}');

        request()->merge(['migration' => null, 'step' => 1]);

        // Mock repository
        $mockRepository = Mockery::mock();
        $mockRepository->shouldReceive('getRan')->once()->andReturn([$migrationName]);
        $mockRepository->shouldReceive('getMigrations')
            ->with(1)
            ->once()
            ->andReturn([(object)['migration' => $migrationName]]);

        // Mock SafeMigrator
        $mockSafeMigrator = Mockery::mock(SafeMigrator::class);
        $mockSafeMigrator->shouldReceive('getRepository')->andReturn($mockRepository);
        $mockSafeMigrator->shouldReceive('undoSafe')->with($file)->once();

        $this->mockSafeMigratorFactory->shouldReceive('create')
            ->once()
            ->andReturn($mockSafeMigrator);

        $response = $this->controller->undoSafeMigration();

        // Cleanup
        if (file_exists($file)) {
            unlink($file);
        }

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true)['success'])->toBeTrue()
            ->and($response->getData(true)['rolled_back'])->toContain($migrationName)
            ->and($response->getData(true))->toHaveKey('duration_ms');
    });

    it('undoes specific migration successfully', function () {
        // Create a temporary migration file
        $migrationPath = database_path('migrations');
        $migrationName = '2025_01_01_000005_test_specific_undo_migration';
        $file = $migrationPath . '/' . $migrationName . '.php';

        if (!is_dir($migrationPath)) {
            mkdir($migrationPath, 0755, true);
        }

        file_put_contents($file, '<?php class TestSpecificUndoMigration {}');

        request()->merge(['migration' => $migrationName]);

        // Mock repository
        $mockRepository = Mockery::mock();
        $mockRepository->shouldReceive('getRan')->once()->andReturn([$migrationName]);

        // Mock SafeMigrator
        $mockSafeMigrator = Mockery::mock(SafeMigrator::class);
        $mockSafeMigrator->shouldReceive('getRepository')->andReturn($mockRepository);
        $mockSafeMigrator->shouldReceive('undoSafe')->with($file)->once();

        $this->mockSafeMigratorFactory->shouldReceive('create')
            ->once()
            ->andReturn($mockSafeMigrator);

        $response = $this->controller->undoSafeMigration();

        // Cleanup
        if (file_exists($file)) {
            unlink($file);
        }

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true)['success'])->toBeTrue()
            ->and($response->getData(true)['migration'])->toBe($migrationName)
            ->and($response->getData(true))->toHaveKey('duration_ms');
    });

    it('returns 404 when specific migration file not found', function () {
        request()->merge(['migration' => 'nonexistent_migration']);

        // Mock repository
        $mockRepository = Mockery::mock();
        $mockRepository->shouldReceive('getRan')->once()->andReturn(['some_migration']);

        // Mock SafeMigrator
        $mockSafeMigrator = Mockery::mock(SafeMigrator::class);
        $mockSafeMigrator->shouldReceive('getRepository')->andReturn($mockRepository);

        $this->mockSafeMigratorFactory->shouldReceive('create')
            ->once()
            ->andReturn($mockSafeMigrator);

        $response = $this->controller->undoSafeMigration();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(404)
            ->and($response->getData(true)['success'])->toBeFalse()
            ->and($response->getData(true)['message'])->toBe('Migration file not found');
    });

    it('returns error on exception', function () {
        request()->merge(['migration' => null]);

        $this->mockSafeMigratorFactory->shouldReceive('create')
            ->once()
            ->andThrow(new Exception('Undo error'));

        $response = $this->controller->undoSafeMigration();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(500)
            ->and($response->getData(true)['success'])->toBeFalse()
            ->and($response->getData(true)['message'])->toBe('Undo error');
    });
});

describe('detectDifferences method', function () {
    it('detects differences successfully', function () {
        $this->mockArtisanRunner->shouldReceive('call')
            ->with('migrate:diff', ['--dry-run' => true, '--force' => true])
            ->once()
            ->andReturn(0);

        $this->mockArtisanRunner->shouldReceive('output')
            ->once()
            ->andReturn("Found differences\n+ Table 'users'\n- Table 'old_table'\nTotal changes: 2");

        $response = $this->controller->detectDifferences();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true)['success'])->toBeTrue()
            ->and($response->getData(true)['has_differences'])->toBeTrue()
            ->and($response->getData(true)['tables_added'])->toContain('users')
            ->and($response->getData(true)['tables_removed'])->toContain('old_table');
    });

    it('detects no differences', function () {
        $this->mockArtisanRunner->shouldReceive('call')
            ->with('migrate:diff', ['--dry-run' => true, '--force' => true])
            ->once()
            ->andReturn(0);

        $this->mockArtisanRunner->shouldReceive('output')
            ->once()
            ->andReturn('No differences detected');

        $response = $this->controller->detectDifferences();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true)['success'])->toBeTrue()
            ->and($response->getData(true)['has_differences'])->toBeFalse();
    });

    it('detects modified tables', function () {
        $this->mockArtisanRunner->shouldReceive('call')
            ->with('migrate:diff', ['--dry-run' => true, '--force' => true])
            ->once()
            ->andReturn(0);

        $this->mockArtisanRunner->shouldReceive('output')
            ->once()
            ->andReturn("Found differences\n~ Table 'users'\n~ Table 'posts'\nTotal changes: 2");

        $response = $this->controller->detectDifferences();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true)['success'])->toBeTrue()
            ->and($response->getData(true)['has_differences'])->toBeTrue()
            ->and($response->getData(true)['tables_modified'])->toContain('users')
            ->and($response->getData(true)['tables_modified'])->toContain('posts');
    });

    it('returns error on exception', function () {
        $this->mockArtisanRunner->shouldReceive('call')
            ->andThrow(new Exception('Diff error'));

        $response = $this->controller->detectDifferences();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(500)
            ->and($response->getData(true)['success'])->toBeFalse()
            ->and($response->getData(true)['message'])->toBe('Diff error');
    });
});

describe('generateDiffMigration method', function () {
    it('generates migration successfully', function () {
        request()->merge(['name' => 'custom_diff']);

        $this->mockArtisanRunner->shouldReceive('call')
            ->with('migrate:diff', ['--force' => true, '--name' => 'custom_diff'])
            ->once()
            ->andReturn(0);

        $this->mockArtisanRunner->shouldReceive('output')
            ->once()
            ->andReturn('Created: database/migrations/2025_01_01_000000_custom_diff.php');

        $response = $this->controller->generateDiffMigration();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true)['success'])->toBeTrue()
            ->and($response->getData(true)['migration_path'])->toContain('custom_diff.php');
    });

    it('generates migration without custom name', function () {
        request()->merge(['name' => null]);

        $this->mockArtisanRunner->shouldReceive('call')
            ->with('migrate:diff', ['--force' => true])
            ->once()
            ->andReturn(0);

        $this->mockArtisanRunner->shouldReceive('output')
            ->once()
            ->andReturn('Created: database/migrations/2025_01_01_000000_diff.php');

        $response = $this->controller->generateDiffMigration();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true)['success'])->toBeTrue();
    });

    it('returns error on exception', function () {
        request()->merge(['name' => null]);

        $this->mockArtisanRunner->shouldReceive('call')
            ->andThrow(new Exception('Generate diff error'));

        $response = $this->controller->generateDiffMigration();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(500)
            ->and($response->getData(true)['success'])->toBeFalse()
            ->and($response->getData(true)['message'])->toBe('Generate diff error');
    });
});

describe('testMigration method', function () {
    it('returns 400 when migration name is missing', function () {
        request()->merge(['migration' => null]);

        $response = $this->controller->testMigration();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(400)
            ->and($response->getData(true)['success'])->toBeFalse()
            ->and($response->getData(true)['message'])->toBe('Migration name is required');
    });

    it('tests migration successfully', function () {
        request()->merge([
            'migration' => 'test_migration',
            'with_data' => false,
            'test_rollback' => true,
        ]);

        $this->mockArtisanRunner->shouldReceive('call')
            ->with('migrate:test', [
                'migration' => 'test_migration',
                '--rollback' => true,
            ])
            ->once()
            ->andReturn(0);

        $this->mockArtisanRunner->shouldReceive('output')
            ->once()
            ->andReturn('Migration passed (50ms)');

        $response = $this->controller->testMigration();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true)['success'])->toBeTrue()
            ->and($response->getData(true)['migration'])->toBe('test_migration')
            ->and((float) $response->getData(true)['duration_ms'])->toBe(50.0);
    });

    it('tests migration with data option', function () {
        request()->merge([
            'migration' => 'test_migration',
            'with_data' => true,
            'test_rollback' => false,
        ]);

        $this->mockArtisanRunner->shouldReceive('call')
            ->with('migrate:test', [
                'migration' => 'test_migration',
                '--with-data' => true,
            ])
            ->once()
            ->andReturn(0);

        $this->mockArtisanRunner->shouldReceive('output')
            ->once()
            ->andReturn('All tests passed');

        $response = $this->controller->testMigration();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true)['success'])->toBeTrue();
    });

    it('parses tables added from output', function () {
        request()->merge([
            'migration' => 'test_migration',
            'with_data' => false,
            'test_rollback' => true,
        ]);

        $this->mockArtisanRunner->shouldReceive('call')
            ->with('migrate:test', [
                'migration' => 'test_migration',
                '--rollback' => true,
            ])
            ->once()
            ->andReturn(0);

        $this->mockArtisanRunner->shouldReceive('output')
            ->once()
            ->andReturn("Migration passed (100ms)\nTables added: users, posts, comments");

        $response = $this->controller->testMigration();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true)['success'])->toBeTrue()
            ->and($response->getData(true)['tables_added'])->toContain('users')
            ->and($response->getData(true)['tables_added'])->toContain('posts')
            ->and($response->getData(true)['tables_added'])->toContain('comments')
            ->and((float) $response->getData(true)['duration_ms'])->toBe(100.0);
    });

    it('returns error on exception', function () {
        request()->merge(['migration' => 'test_migration']);

        $this->mockArtisanRunner->shouldReceive('call')
            ->andThrow(new Exception('Test error'));

        $this->mockArtisanRunner->shouldReceive('output')
            ->andReturn('');

        $response = $this->controller->testMigration();

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getStatusCode())->toBe(500)
            ->and($response->getData(true)['success'])->toBeFalse()
            ->and($response->getData(true)['message'])->toBe('Test error');
    });
});
