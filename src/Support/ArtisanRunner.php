<?php

namespace Flux\Support;

interface ArtisanRunner
{
    /**
     * Call an Artisan command
     */
    public function call(string $command, array $parameters = []): int;

    /**
     * Get the output from the last command
     */
    public function output(): string;
}
