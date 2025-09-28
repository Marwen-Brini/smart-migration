<?php

namespace Flux;

use Flux\Commands\CheckCommand;
use Flux\Commands\CleanupCommand;
use Flux\Commands\ConfigCommand;
use Flux\Commands\FluxCommand;
use Flux\Commands\PlanCommand;
use Flux\Commands\SafeCommand;
use Flux\Commands\SnapshotCommand;
use Flux\Commands\UndoCommand;
use Flux\Config\SmartMigrationConfig;
use Flux\Jobs\ArchiveCleanupJob;
use Illuminate\Console\Scheduling\Schedule;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FluxServiceProvider extends PackageServiceProvider
{
    /**
     * Bootstrap package services
     */
    public function boot(): void
    {
        parent::boot();

        // Register scheduled cleanup job if auto cleanup is enabled
        if (SmartMigrationConfig::autoCleanupEnabled()) {
            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $cleanupSchedule = SmartMigrationConfig::getCleanupSchedule();

                $schedule->job(new ArchiveCleanupJob())
                    ->cron($cleanupSchedule)
                    ->name('smart-migration:archive-cleanup')
                    ->withoutOverlapping();
            });
        }
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
            ->hasCommand(CleanupCommand::class);
    }
}
