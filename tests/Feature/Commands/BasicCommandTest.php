<?php

namespace Flux\Tests\Feature\Commands;

use Flux\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

class BasicCommandTest extends TestCase
{
    public function test_check_command_exists()
    {
        $exitCode = Artisan::call('migrate:check', ['--help' => true]);
        $this->assertIsInt($exitCode);
    }

    public function test_snapshot_command_exists()
    {
        $exitCode = Artisan::call('migrate:snapshot', ['--help' => true]);
        $this->assertIsInt($exitCode);
    }

    public function test_cleanup_command_exists()
    {
        $exitCode = Artisan::call('migrate:cleanup', ['--help' => true]);
        $this->assertIsInt($exitCode);
    }

    public function test_config_command_exists()
    {
        $exitCode = Artisan::call('migrate:config', ['--help' => true]);
        $this->assertIsInt($exitCode);
    }
}