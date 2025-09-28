<?php

namespace Flux;

use Flux\Commands\FluxCommand;
use Flux\Commands\PlanCommand;
use Flux\Commands\SafeCommand;
use Flux\Commands\UndoCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FluxServiceProvider extends PackageServiceProvider
{
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
            ->hasCommand(UndoCommand::class);
    }
}
