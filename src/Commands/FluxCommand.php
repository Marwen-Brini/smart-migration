<?php

namespace Flux\Commands;

use Illuminate\Console\Command;

class FluxCommand extends Command
{
    public $signature = 'smart-migration';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
