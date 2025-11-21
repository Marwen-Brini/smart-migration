<?php

namespace Flux\Support;

use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Output\BufferedOutput;

class LaravelArtisanRunner implements ArtisanRunner
{
    protected Kernel $kernel;
    protected BufferedOutput $output;

    public function __construct(?Kernel $kernel = null)
    {
        $this->kernel = $kernel ?? app(Kernel::class);
        $this->output = new BufferedOutput();
    }

    /**
     * Call an Artisan command
     */
    public function call(string $command, array $parameters = []): int
    {
        // Clear the buffer before each command
        $this->output = new BufferedOutput();

        return $this->kernel->call($command, $parameters, $this->output);
    }

    /**
     * Get the output from the last command
     */
    public function output(): string
    {
        return $this->output->fetch();
    }
}
