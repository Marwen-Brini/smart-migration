<?php

use Flux\Commands\FluxCommand;

beforeEach(function () {
    $this->command = Mockery::mock(FluxCommand::class)->makePartial();
});

afterEach(function () {
    Mockery::close();
});

describe('FluxCommand', function () {
    it('has correct signature and description', function () {
        $command = new FluxCommand;

        expect($command->signature)->toBe('smart-migration');
        expect($command->description)->toBe('My command');
    });

    it('handles execution correctly', function () {
        $this->command->shouldReceive('comment')->once()->with('All done');

        $result = $this->command->handle();

        expect($result)->toBe(FluxCommand::SUCCESS);
    });

    it('returns success status code', function () {
        $this->command->shouldReceive('comment')->once()->with('All done');

        $result = $this->command->handle();

        expect($result)->toBe(0); // SUCCESS constant value
    });
});
