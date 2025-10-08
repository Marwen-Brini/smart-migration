<?php

namespace Flux;

use Flux\Cleanup\ArchiveCleanupService;
use Flux\Commands\CheckCommand;
use Flux\Commands\CleanupCommand;
use Flux\Commands\ConfigCommand;
use Flux\Commands\DiffCommand;
use Flux\Commands\FluxCommand;
use Flux\Commands\PlanCommand;
use Flux\Commands\SafeCommand;
use Flux\Commands\SnapshotCommand;
use Flux\Commands\UndoCommand;
use Flux\Config\SmartMigrationConfig;
use Flux\Database\DatabaseAdapterFactory;
use Flux\Database\DatabaseAdapterFactoryInterface;
use Flux\Jobs\ArchiveCleanupJob;
use Flux\Snapshots\SnapshotManager;
use Illuminate\Console\Scheduling\Schedule;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FluxServiceProvider extends PackageServiceProvider
{
    /**
     * Register package services
     */
    public function register(): void
    {
        parent::register();

        // Register DatabaseAdapterFactory as singleton
        $this->app->singleton(DatabaseAdapterFactoryInterface::class, DatabaseAdapterFactory::class);

        // Register SnapshotManager with dependency injection
        $this->app->singleton(SnapshotManager::class, function ($app) {
            return new SnapshotManager(
                $app->make(DatabaseAdapterFactoryInterface::class)
            );
        });

        // Register ArchiveCleanupService with dependency injection
        $this->app->singleton(ArchiveCleanupService::class, function ($app) {
            return new ArchiveCleanupService(
                $app->make(DatabaseAdapterFactoryInterface::class)
            );
        });
    }

    /**
     * Bootstrap package services
     */
    public function boot(): void
    {
        parent::boot();

        // Register scheduled cleanup job if auto cleanup is enabled
        // @codeCoverageIgnoreStart
        if (SmartMigrationConfig::autoCleanupEnabled()) {
            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $cleanupSchedule = SmartMigrationConfig::getCleanupSchedule();

                $schedule->job(new ArchiveCleanupJob)
                    ->cron($cleanupSchedule)
                    ->name('smart-migration:archive-cleanup')
                    ->withoutOverlapping();
            });
        }
        // @codeCoverageIgnoreEnd
    }

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('smart-migration')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_smart_migration_table')
            ->hasCommand(FluxCommand::class)
            ->hasCommand(PlanCommand::class)
            ->hasCommand(SafeCommand::class)
            ->hasCommand(UndoCommand::class)
            ->hasCommand(ConfigCommand::class)
            ->hasCommand(CheckCommand::class)
            ->hasCommand(SnapshotCommand::class)
            ->hasCommand(CleanupCommand::class)
            ->hasCommand(DiffCommand::class);
    }
}
