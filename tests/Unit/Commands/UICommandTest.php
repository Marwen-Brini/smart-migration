<?php

use Flux\Commands\UICommand;

beforeEach(function () {
    $this->command = Mockery::mock(UICommand::class)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    // Mock output methods
    $this->command->shouldReceive('info')->andReturnNull()->byDefault();
    $this->command->shouldReceive('error')->andReturnNull()->byDefault();
    $this->command->shouldReceive('line')->andReturnNull()->byDefault();
    $this->command->shouldReceive('newLine')->andReturnNull()->byDefault();
});

afterEach(function () {
    Mockery::close();
});

describe('displayHeader method', function () {
    it('displays dashboard header with host and port', function () {
        $this->command->shouldReceive('line')
            ->with(Mockery::type('string'))
            ->atLeast()->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('displayHeader');
        $method->setAccessible(true);

        $method->invoke($this->command, 'localhost', '8080');

        expect(true)->toBeTrue();
    });
});

describe('getPackagePath method', function () {
    it('returns package path', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getPackagePath');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        expect($result)->toBeString()
            ->and($result)->toContain('smart-migration');
    });
});

describe('checkDependencies method', function () {
    it('returns true when node_modules exists', function () {
        // Create a mock that returns a known path
        $mockPath = '/tmp/test-package';
        mkdir($mockPath, 0755, true);
        mkdir($mockPath . '/node_modules', 0755, true);

        $this->command->shouldReceive('getPackagePath')
            ->andReturn($mockPath);

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('checkDependencies');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        // Cleanup
        rmdir($mockPath . '/node_modules');
        rmdir($mockPath);

        expect($result)->toBeTrue();
    });

    it('returns false when node_modules does not exist', function () {
        $mockPath = '/tmp/test-package-no-deps';
        mkdir($mockPath, 0755, true);

        $this->command->shouldReceive('getPackagePath')
            ->andReturn($mockPath);

        $this->command->shouldReceive('error')
            ->with(Mockery::type('string'))
            ->once();

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('checkDependencies');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);

        // Cleanup
        rmdir($mockPath);

        expect($result)->toBeFalse();
    });
});

// Note: handle() and startViteServer() methods are skipped because they involve
// process execution and external dependencies (npm, node, vite) which are difficult
// to mock in unit tests. These are tested in integration/manual testing instead.
