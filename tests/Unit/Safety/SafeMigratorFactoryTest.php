<?php

use Flux\Safety\SafeMigratorFactory;
use Flux\Safety\SafeMigrator;
use Flux\Database\DatabaseAdapterFactoryInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Filesystem\Filesystem;

describe('SafeMigratorFactory', function () {
    it('creates SafeMigrator with injected dependencies', function () {
        $mockAdapterFactory = Mockery::mock(DatabaseAdapterFactoryInterface::class);
        $mockRepository = Mockery::mock(MigrationRepositoryInterface::class);
        $mockDatabase = Mockery::mock(ConnectionResolverInterface::class);
        $mockFilesystem = Mockery::mock(Filesystem::class);
        $mockEvents = Mockery::mock(Dispatcher::class);

        $factory = new SafeMigratorFactory(
            $mockAdapterFactory,
            $mockRepository,
            $mockDatabase,
            $mockFilesystem,
            $mockEvents
        );

        $safeMigrator = $factory->create();

        expect($safeMigrator)->toBeInstanceOf(SafeMigrator::class);
    });

    it('creates SafeMigrator with only required dependency', function () {
        $mockAdapterFactory = Mockery::mock(DatabaseAdapterFactoryInterface::class);

        $factory = new SafeMigratorFactory($mockAdapterFactory);

        // This will use App::make() for the other dependencies
        // Since we're in a Laravel test environment, this should work
        $safeMigrator = $factory->create();

        expect($safeMigrator)->toBeInstanceOf(SafeMigrator::class);
    });

    it('uses injected repository when provided', function () {
        $mockAdapterFactory = Mockery::mock(DatabaseAdapterFactoryInterface::class);
        $mockRepository = Mockery::mock(MigrationRepositoryInterface::class);
        $mockDatabase = Mockery::mock(ConnectionResolverInterface::class);
        $mockFilesystem = Mockery::mock(Filesystem::class);
        $mockEvents = Mockery::mock(Dispatcher::class);

        $factory = new SafeMigratorFactory(
            $mockAdapterFactory,
            $mockRepository,
            $mockDatabase,
            $mockFilesystem,
            $mockEvents
        );

        $safeMigrator = $factory->create();

        // Verify the SafeMigrator was created with the injected repository
        expect($safeMigrator->getRepository())->toBe($mockRepository);
    });

    it('sets adapter factory on created SafeMigrator', function () {
        $mockAdapterFactory = Mockery::mock(DatabaseAdapterFactoryInterface::class);
        $mockRepository = Mockery::mock(MigrationRepositoryInterface::class);
        $mockDatabase = Mockery::mock(ConnectionResolverInterface::class);
        $mockFilesystem = Mockery::mock(Filesystem::class);
        $mockEvents = Mockery::mock(Dispatcher::class);

        $factory = new SafeMigratorFactory(
            $mockAdapterFactory,
            $mockRepository,
            $mockDatabase,
            $mockFilesystem,
            $mockEvents
        );

        $safeMigrator = $factory->create();

        // Use reflection to verify the adapter factory was set
        $reflection = new ReflectionClass($safeMigrator);
        $property = $reflection->getProperty('adapterFactory');
        $property->setAccessible(true);

        expect($property->getValue($safeMigrator))->toBe($mockAdapterFactory);
    });

    it('creates new SafeMigrator instance on each call', function () {
        $mockAdapterFactory = Mockery::mock(DatabaseAdapterFactoryInterface::class);
        $mockRepository = Mockery::mock(MigrationRepositoryInterface::class);
        $mockDatabase = Mockery::mock(ConnectionResolverInterface::class);
        $mockFilesystem = Mockery::mock(Filesystem::class);
        $mockEvents = Mockery::mock(Dispatcher::class);

        $factory = new SafeMigratorFactory(
            $mockAdapterFactory,
            $mockRepository,
            $mockDatabase,
            $mockFilesystem,
            $mockEvents
        );

        $safeMigrator1 = $factory->create();
        $safeMigrator2 = $factory->create();

        expect($safeMigrator1)->not->toBe($safeMigrator2)
            ->and($safeMigrator1)->toBeInstanceOf(SafeMigrator::class)
            ->and($safeMigrator2)->toBeInstanceOf(SafeMigrator::class);
    });

    it('can be instantiated with partial dependencies', function () {
        $mockAdapterFactory = Mockery::mock(DatabaseAdapterFactoryInterface::class);
        $mockRepository = Mockery::mock(MigrationRepositoryInterface::class);

        // Only provide adapterFactory and repository
        $factory = new SafeMigratorFactory(
            $mockAdapterFactory,
            $mockRepository
        );

        $safeMigrator = $factory->create();

        expect($safeMigrator)->toBeInstanceOf(SafeMigrator::class)
            ->and($safeMigrator->getRepository())->toBe($mockRepository);
    });
});
