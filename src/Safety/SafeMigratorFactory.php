<?php

namespace Flux\Safety;

use Flux\Database\DatabaseAdapterFactoryInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\App;

class SafeMigratorFactory
{
    public function __construct(
        protected DatabaseAdapterFactoryInterface $adapterFactory,
        protected ?MigrationRepositoryInterface $repository = null,
        protected ?ConnectionResolverInterface $database = null,
        protected ?Filesystem $filesystem = null,
        protected ?Dispatcher $events = null
    ) {
    }

    /**
     * Create a new SafeMigrator instance
     */
    public function create(): SafeMigrator
    {
        $repository = $this->repository ?? App::make('migration.repository');
        $filesystem = $this->filesystem ?? App::make('files');
        $events = $this->events ?? App::make('events');
        $database = $this->database ?? App::make('db');

        $safeMigrator = new SafeMigrator($repository, $database, $filesystem, $events);
        $safeMigrator->setAdapterFactory($this->adapterFactory);

        return $safeMigrator;
    }
}
