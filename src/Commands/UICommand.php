<?php

namespace Flux\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class UICommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:ui
                          {--port=8080 : Port to run the dashboard on}
                          {--host=localhost : Host to run the dashboard on}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Launch the Smart Migration web dashboard';

    /**
     * Execute the console command.
     * @codeCoverageIgnore
     */
    public function handle(): int
    {
        $port = $this->option('port');
        $host = $this->option('host');

        // Display header
        $this->displayHeader($host, $port);

        // Check if node_modules exists
        if (! $this->checkDependencies()) {
            return self::FAILURE;
        }

        // Start Vite dev server
        $this->info('Starting dashboard server...');
        $this->newLine();

        try {
            $this->startViteServer($port);
        } catch (\Exception $e) {
            $this->error('Failed to start dashboard server: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Display dashboard header
     */
    protected function displayHeader(string $host, string $port): void
    {
        $this->newLine();
        $this->line('<fg=blue>┌────────────────────────────────────────────────────┐</>');
        $this->line('<fg=blue>│</> <fg=white;options=bold>    🛡️  Smart Migration Dashboard</><fg=blue>           │</>');
        $this->line('<fg=blue>└────────────────────────────────────────────────────┘</>');
        $this->newLine();
        $this->line('<fg=cyan>Dashboard URL:</> <fg=white;options=bold>http://'.$host.':'.$port.'</>');
        $this->line('<fg=cyan>API Endpoint:</> <fg=white>http://'.$host.':'.$port.'/api/smart-migration</>');
        $this->newLine();
        $this->info('Press Ctrl+C to stop the server');
        $this->newLine();
    }

    /**
     * Check if dependencies are installed
     * @codeCoverageIgnore
     */
    protected function checkDependencies(): bool
    {
        $packagePath = $this->getPackagePath();

        if (! file_exists($packagePath.'/node_modules')) {
            $this->error('❌ Node dependencies not installed');
            $this->newLine();
            $this->line('Please run the following commands:');
            $this->line('<fg=yellow>cd '.$packagePath.'</>');
            $this->line('<fg=yellow>npm install</>');
            $this->newLine();

            return false;
        }

        return true;
    }

    /**
     * Start Vite development server
     * @codeCoverageIgnore
     */
    protected function startViteServer(string $port): void
    {
        $packagePath = $this->getPackagePath();

        // Change to package directory
        chdir($packagePath);

        $process = new Process([
            'npm',
            'run',
            'dev',
            '--',
            '--port='.$port,
        ]);

        $process->setTimeout(null);
        $process->setTty(Process::isTtySupported());

        $process->run(function ($type, $buffer) {
            echo $buffer;
        });
    }

    /**
     * Get the package path
     */
    protected function getPackagePath(): string
    {
        return dirname(__DIR__, 2);
    }
}
