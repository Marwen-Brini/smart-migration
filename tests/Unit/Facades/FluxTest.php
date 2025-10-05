<?php

use Flux\Facades\Flux;

it('returns the correct facade accessor', function () {
    expect(Flux::getFacadeRoot())->toBeInstanceOf(\Flux\Flux::class);
});

it('can be resolved from the container', function () {
    $instance = app(\Flux\Flux::class);
    expect($instance)->toBeInstanceOf(\Flux\Flux::class);
});

it('facade accessor method returns correct class name', function () {
    // Use reflection to test the protected method
    $reflection = new ReflectionClass(Flux::class);
    $method = $reflection->getMethod('getFacadeAccessor');
    $method->setAccessible(true);

    expect($method->invoke(null))->toBe(\Flux\Flux::class);
});
