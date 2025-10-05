<?php

use Flux\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

// No shared mocks - each test file should create its own mocks as needed

afterEach(function () {
    Mockery::close();

    // Clean up only smart-migration test temporary files
    $tempPatterns = [
        '/tmp/migration_test*',
        '/tmp/snapshot_test*',
    ];

    foreach ($tempPatterns as $pattern) {
        $files = glob($pattern) ?: [];
        foreach ($files as $file) {
            if (file_exists($file) && is_file($file)) {
                unlink($file);
            }
        }
    }
});
