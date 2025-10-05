<?php

namespace Flux\Tests;

use Flux\FluxServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Flux\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            FluxServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        // Set up comprehensive smart migration config
        $app['config']->set('smart-migration', [
            'safety' => [
                'auto_backup' => true,
                'safe_rollback' => true,
                'require_confirmation' => false,
            ],
            'display' => [
                'colors' => true,
                'emojis' => true,
                'progress_bars' => true,
                'show_sql' => false,
                'show_timing' => true,
            ],
            'archive' => [
                'table_prefix' => 'archived_',
                'column_prefix' => 'archived_',
                'include_timestamp' => true,
                'retention_days' => 30,
            ],
            'cleanup' => [
                'auto_enabled' => false,
            ],
            'drivers' => [
                'mysql' => true,
                'pgsql' => true,
                'sqlite' => true,
                'sqlsrv' => true,
            ],
            'snapshots' => [
                'path' => 'database/snapshots',
                'format' => 'json',
                'max_snapshots' => 10,
                'auto_enabled' => false,
                'include_data' => false,
            ],
            'drift' => [
                'auto_detect' => true,
                'check_before_migrate' => true,
                'warn_only' => false,
                'ignored_tables' => [],
                'ignored_columns' => [],
            ],
            'logging' => [
                'enabled' => false,
                'channel' => 'stack',
            ],
        ]);

        // Create migrations table for testing
        $app['db']->connection()->getSchemaBuilder()->create('migrations', function ($table) {
            $table->increments('id');
            $table->string('migration');
            $table->integer('batch');
        });
    }
}
