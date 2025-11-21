<?php

use Flux\Support\LaravelArtisanRunner;
use Flux\Support\ArtisanRunner;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Output\OutputInterface;

describe('LaravelArtisanRunner', function () {
    it('implements ArtisanRunner interface', function () {
        $runner = new LaravelArtisanRunner();

        expect($runner)->toBeInstanceOf(ArtisanRunner::class);
    });

    it('has call method with correct signature', function () {
        $runner = new LaravelArtisanRunner();

        expect(method_exists($runner, 'call'))->toBeTrue();

        $reflection = new ReflectionMethod($runner, 'call');
        $params = $reflection->getParameters();

        expect($params)->toHaveCount(2)
            ->and($params[0]->getName())->toBe('command')
            ->and($params[1]->getName())->toBe('parameters')
            ->and($params[1]->isDefaultValueAvailable())->toBeTrue()
            ->and($params[1]->getDefaultValue())->toBe([]);
    });

    it('has output method with correct signature', function () {
        $runner = new LaravelArtisanRunner();

        expect(method_exists($runner, 'output'))->toBeTrue();

        $reflection = new ReflectionMethod($runner, 'output');
        $params = $reflection->getParameters();

        expect($params)->toHaveCount(0);
    });

    it('calls kernel with command and parameters', function () {
        $kernel = Mockery::mock(Kernel::class);
        $kernel->shouldReceive('call')
            ->once()
            ->with('migrate:status', ['--database' => 'testing'], Mockery::type(OutputInterface::class))
            ->andReturn(0);

        $runner = new LaravelArtisanRunner($kernel);
        $result = $runner->call('migrate:status', ['--database' => 'testing']);

        expect($result)->toBe(0);
    });

    it('calls kernel with command only when no parameters', function () {
        $kernel = Mockery::mock(Kernel::class);
        $kernel->shouldReceive('call')
            ->once()
            ->with('migrate:status', [], Mockery::type(OutputInterface::class))
            ->andReturn(0);

        $runner = new LaravelArtisanRunner($kernel);
        $result = $runner->call('migrate:status');

        expect($result)->toBe(0);
    });

    it('returns exit code from kernel call', function () {
        $kernel = Mockery::mock(Kernel::class);
        $kernel->shouldReceive('call')
            ->once()
            ->with('some:command', [], Mockery::type(OutputInterface::class))
            ->andReturn(1);

        $runner = new LaravelArtisanRunner($kernel);
        $result = $runner->call('some:command');

        expect($result)->toBe(1);
    });

    it('captures output from command execution', function () {
        $kernel = Mockery::mock(Kernel::class);
        $kernel->shouldReceive('call')
            ->once()
            ->with('migrate:status', [], Mockery::type(OutputInterface::class))
            ->andReturnUsing(function ($command, $params, $output) {
                $output->writeln("Migration table created successfully.");
                return 0;
            });

        $runner = new LaravelArtisanRunner($kernel);
        $runner->call('migrate:status');
        $output = $runner->output();

        expect($output)->toBe("Migration table created successfully.\n");
    });

    it('returns empty string when no output written', function () {
        $kernel = Mockery::mock(Kernel::class);
        $kernel->shouldReceive('call')
            ->once()
            ->with('some:command', [], Mockery::type(OutputInterface::class))
            ->andReturn(0);

        $runner = new LaravelArtisanRunner($kernel);
        $runner->call('some:command');
        $output = $runner->output();

        expect($output)->toBe('');
    });

    it('clears output buffer between commands', function () {
        $kernel = Mockery::mock(Kernel::class);

        $kernel->shouldReceive('call')
            ->once()
            ->with('first:command', [], Mockery::type(OutputInterface::class))
            ->andReturnUsing(function ($command, $params, $output) {
                $output->writeln("First output");
                return 0;
            });

        $kernel->shouldReceive('call')
            ->once()
            ->with('second:command', [], Mockery::type(OutputInterface::class))
            ->andReturnUsing(function ($command, $params, $output) {
                $output->writeln("Second output");
                return 0;
            });

        $runner = new LaravelArtisanRunner($kernel);

        $runner->call('first:command');
        $runner->call('second:command');
        $output = $runner->output();

        // Should only contain second command output
        expect($output)->toBe("Second output\n");
    });

    it('can call command and retrieve its output', function () {
        $kernel = Mockery::mock(Kernel::class);
        $kernel->shouldReceive('call')
            ->once()
            ->with('migrate:status', [], Mockery::type(OutputInterface::class))
            ->andReturnUsing(function ($command, $params, $output) {
                $output->writeln("Migration status output");
                return 0;
            });

        $runner = new LaravelArtisanRunner($kernel);
        $exitCode = $runner->call('migrate:status');
        $output = $runner->output();

        expect($exitCode)->toBe(0)
            ->and($output)->toBe("Migration status output\n");
    });

    it('uses application kernel when none provided', function () {
        // This tests the default behavior when no kernel is injected
        $runner = new LaravelArtisanRunner();

        // Use reflection to verify it has a kernel
        $reflection = new ReflectionClass($runner);
        $property = $reflection->getProperty('kernel');
        $property->setAccessible(true);

        expect($property->getValue($runner))->toBeInstanceOf(Kernel::class);
    });
});
