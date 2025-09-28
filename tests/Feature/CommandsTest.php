<?php

it('can register plan command', function () {
    $this->artisan('migrate:plan', ['--help' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Preview what a migration will do');
});

it('can register safe command', function () {
    $this->artisan('migrate:safe', ['--help' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Run migrations with automatic backups');
});

it('can register undo command', function () {
    $this->artisan('migrate:undo', ['--help' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Safely rollback migrations without data loss');
});

it('plan command shows analysis', function () {
    // Create a test migration file
    $migrationPath = database_path('migrations');
    if (! is_dir($migrationPath)) {
        mkdir($migrationPath, 0755, true);
    }

    $testMigration = $migrationPath.'/2025_01_01_000000_test_migration.php';
    file_put_contents($testMigration, '<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create("test_table", function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists("test_table");
    }
};');

    $this->artisan('migrate:plan')
        ->assertSuccessful()
        ->expectsOutputToContain('Smart Migration Plan Analysis')
        ->expectsOutputToContain('test_migration');

    // Cleanup
    unlink($testMigration);
});

it('safe command requires confirmation in production', function () {
    $this->app['env'] = 'production';

    $this->artisan('migrate:safe')
        ->expectsConfirmation('You are in production! Do you really wish to run this command?', 'no')
        ->assertFailed();
});

it('undo command shows rollback plan', function () {
    $this->artisan('migrate:undo', ['--pretend' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Smart Migration - Safe Undo');
});
