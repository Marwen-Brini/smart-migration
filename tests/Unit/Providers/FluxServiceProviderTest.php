<?php

use Flux\FluxServiceProvider;
use Flux\Dashboard\DashboardService;
use Flux\Monitoring\AnomalyDetector;
use Flux\Monitoring\PerformanceBaseline;
use Flux\Snapshots\SnapshotManager;
use Flux\Cleanup\ArchiveCleanupService;
use Flux\Database\DatabaseAdapterFactoryInterface;
use Flux\Support\ArtisanRunner;

describe('FluxServiceProvider', function () {
    describe('service registration', function () {
        it('registers DatabaseAdapterFactoryInterface as singleton', function () {
            $instance1 = app(DatabaseAdapterFactoryInterface::class);
            $instance2 = app(DatabaseAdapterFactoryInterface::class);

            expect($instance1)->toBe($instance2)
                ->and($instance1)->toBeInstanceOf(DatabaseAdapterFactoryInterface::class);
        });

        it('registers SnapshotManager as singleton', function () {
            $instance1 = app(SnapshotManager::class);
            $instance2 = app(SnapshotManager::class);

            expect($instance1)->toBe($instance2)
                ->and($instance1)->toBeInstanceOf(SnapshotManager::class);
        });

        it('registers ArchiveCleanupService as singleton', function () {
            $instance1 = app(ArchiveCleanupService::class);
            $instance2 = app(ArchiveCleanupService::class);

            expect($instance1)->toBe($instance2)
                ->and($instance1)->toBeInstanceOf(ArchiveCleanupService::class);
        });

        it('registers ArtisanRunner as singleton', function () {
            $instance1 = app(ArtisanRunner::class);
            $instance2 = app(ArtisanRunner::class);

            expect($instance1)->toBe($instance2)
                ->and($instance1)->toBeInstanceOf(ArtisanRunner::class);
        });

        it('registers DashboardService as singleton', function () {
            $instance1 = app(DashboardService::class);
            $instance2 = app(DashboardService::class);

            expect($instance1)->toBe($instance2)
                ->and($instance1)->toBeInstanceOf(DashboardService::class);
        });

        it('registers PerformanceBaseline as singleton', function () {
            $instance1 = app(PerformanceBaseline::class);
            $instance2 = app(PerformanceBaseline::class);

            expect($instance1)->toBe($instance2)
                ->and($instance1)->toBeInstanceOf(PerformanceBaseline::class);
        });

        it('registers AnomalyDetector as singleton', function () {
            $instance1 = app(AnomalyDetector::class);
            $instance2 = app(AnomalyDetector::class);

            expect($instance1)->toBe($instance2)
                ->and($instance1)->toBeInstanceOf(AnomalyDetector::class);
        });
    });

    describe('package configuration', function () {
        it('loads dashboard routes', function () {
            // Check that routes are loaded by verifying a route exists
            $routes = app('router')->getRoutes();

            // The dashboard routes should be registered
            expect($routes)->not->toBeEmpty();
        });
    });
});
