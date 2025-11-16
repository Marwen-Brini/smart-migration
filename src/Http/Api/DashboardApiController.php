<?php

namespace Flux\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Flux\Dashboard\DashboardService;

class DashboardApiController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {
    }

    /**
     * Get overall status
     */
    public function status(): JsonResponse
    {
        try {
            $status = $this->dashboardService->getStatus();
            return response()->json($status);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all migrations
     */
    public function migrations(): JsonResponse
    {
        try {
            $migrations = $this->dashboardService->getMigrations();
            return response()->json($migrations);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get migration history
     */
    public function history(): JsonResponse
    {
        try {
            $history = $this->dashboardService->getHistory();
            return response()->json($history);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get database schema
     */
    public function schema(): JsonResponse
    {
        try {
            $schema = $this->dashboardService->getSchema();
            return response()->json($schema);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get drift information
     */
    public function drift(): JsonResponse
    {
        try {
            $drift = $this->dashboardService->getDrift();
            return response()->json($drift);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get snapshots
     */
    public function snapshots(): JsonResponse
    {
        try {
            $snapshots = $this->dashboardService->getSnapshots();
            return response()->json($snapshots);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get metrics
     */
    public function metrics(): JsonResponse
    {
        try {
            $metrics = $this->dashboardService->getMetrics();
            return response()->json($metrics);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate fix migration for drift
     */
    public function generateFixMigration(): JsonResponse
    {
        try {
            $result = $this->dashboardService->generateFixMigration();
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new snapshot
     */
    public function createSnapshot(): JsonResponse
    {
        try {
            $name = request()->input('name');
            $result = $this->dashboardService->createSnapshot($name);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a snapshot
     */
    public function deleteSnapshot(string $name): JsonResponse
    {
        try {
            $result = $this->dashboardService->deleteSnapshot($name);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Run migrations
     */
    public function runMigrations(): JsonResponse
    {
        try {
            $options = request()->all();
            $result = $this->dashboardService->runMigrations($options);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rollback migrations
     */
    public function rollbackMigrations(): JsonResponse
    {
        try {
            $options = request()->all();
            $result = $this->dashboardService->rollbackMigrations($options);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
