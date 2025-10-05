<?php

use Illuminate\Support\Facades\Artisan;

it('check command exists', function () {
    $exitCode = Artisan::call('migrate:check', ['--help' => true]);
    expect($exitCode)->toBeInt();
});

it('snapshot command exists', function () {
    $exitCode = Artisan::call('migrate:snapshot', ['--help' => true]);
    expect($exitCode)->toBeInt();
});

it('cleanup command exists', function () {
    $exitCode = Artisan::call('migrate:cleanup', ['--help' => true]);
    expect($exitCode)->toBeInt();
});

it('config command exists', function () {
    $exitCode = Artisan::call('migrate:config', ['--help' => true]);
    expect($exitCode)->toBeInt();
});
