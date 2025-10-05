<?php

use Flux\Analyzers\MigrationAnalyzer;
use Flux\Commands\PlanCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->mockAnalyzer = Mockery::mock(MigrationAnalyzer::class);

    // Create command mock
    $this->command = Mockery::mock(PlanCommand::class)->makePartial()->shouldAllowMockingProtectedMethods();

    // Set the analyzer on the command using reflection
    $reflection = new ReflectionClass($this->command);
    $analyzerProperty = $reflection->getProperty('analyzer');
    $analyzerProperty->setAccessible(true);
    $analyzerProperty->setValue($this->command, $this->mockAnalyzer);
});

afterEach(function () {
    Mockery::close();
});

describe('constructor', function () {
    it('properly injects MigrationAnalyzer', function () {
        // Create a real command instance to test constructor
        $command = new PlanCommand;

        // Verify the analyzer was injected using reflection
        $reflection = new ReflectionClass($command);
        $analyzerProperty = $reflection->getProperty('analyzer');
        $analyzerProperty->setAccessible(true);
        $analyzer = $analyzerProperty->getValue($command);

        expect($analyzer)->toBeInstanceOf(MigrationAnalyzer::class);
    });
});

describe('handle method', function () {
    it('shows warning when no pending migrations found (covers lines 37-40)', function () {
        // Mock console output
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('info')->with('🔍 <options=bold>Smart Migration Plan Analysis</options=bold>')->once();
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════')->once();
        $this->command->shouldReceive('warn')->with('⚠️  No pending migrations found.')->once(); // Line 37

        // Mock getMigrationsToAnalyze to return empty array
        $this->command->shouldReceive('getMigrationsToAnalyze')->andReturn([]); // Returns empty, triggers lines 37-40

        // Execute
        $result = $this->command->handle();

        // Assert success even with no migrations (lines 37-40)
        expect($result)->toBe(0);
    });

    it('analyzes pending migrations successfully', function () {
        // Mock console output
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('info')->with('🔍 <options=bold>Smart Migration Plan Analysis</options=bold>')->once();
        $this->command->shouldReceive('comment')->with('═══════════════════════════════════════════════════════════════════════════════')->once();
        $this->command->shouldReceive('info')->with('📋 Found 1 migration(s) to analyze:')->once();
        $this->command->shouldReceive('info')->with('✅ <options=bold>Analysis completed successfully!</options=bold>')->once();

        // Mock getMigrationsToAnalyze to return a migration file
        $this->command->shouldReceive('getMigrationsToAnalyze')->andReturn(['/path/to/migration.php']);
        $this->command->shouldReceive('analyzeMigration')->with('/path/to/migration.php')->once();

        // Execute
        $result = $this->command->handle();

        // Assert success
        expect($result)->toBe(0);
    });
});

describe('getMigrationsToAnalyze', function () {
    it('returns error when specific migration file not found (covers lines 63-70)', function () {
        // Mock command to return a specific migration name
        $this->command->shouldReceive('argument')->with('migration')->andReturn('create_users_table');
        $this->command->shouldReceive('getMigrationPath')->andReturn('/path/to/migrations');
        $this->command->shouldReceive('error')->with('❌ Migration file not found: <fg=red>create_users_table</fg=red>')->once(); // Line 65

        // Mock File::exists to return false
        File::shouldReceive('exists')->with('/path/to/migrations/create_users_table.php')->andReturn(false); // Line 64

        // Execute using reflection to call protected method
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getMigrationsToAnalyze');
        $method->setAccessible(true);
        $result = $method->invoke($this->command);

        // Should return empty array when file not found (covers lines 67, 70)
        expect($result)->toBe([]);
    });

    it('returns specific migration file when it exists', function () {
        // Mock command to return a specific migration name
        $this->command->shouldReceive('argument')->with('migration')->andReturn('create_users_table');
        $this->command->shouldReceive('getMigrationPath')->andReturn('/path/to/migrations');

        // Mock File::exists to return true
        File::shouldReceive('exists')->with('/path/to/migrations/create_users_table.php')->andReturn(true);

        // Execute using reflection to call protected method
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getMigrationsToAnalyze');
        $method->setAccessible(true);
        $result = $method->invoke($this->command);

        // Should return the specific file when it exists (covers line 70)
        expect($result)->toBe(['/path/to/migrations/create_users_table.php']);
    });

    it('returns pending migrations when no specific migration requested', function () {
        // Mock command to return no specific migration
        $this->command->shouldReceive('argument')->with('migration')->andReturn(null);
        $this->command->shouldReceive('getMigrationPath')->andReturn('/path/to/migrations');

        // Mock File::glob to return migration files
        File::shouldReceive('glob')->with('/path/to/migrations/*.php')->andReturn([
            '/path/to/migrations/2023_01_01_000000_create_users_table.php',
            '/path/to/migrations/2023_01_02_000000_create_posts_table.php',
        ]);

        // Mock getMigrationName and isMigrationRun
        $this->command->shouldReceive('getMigrationName')
            ->with('/path/to/migrations/2023_01_01_000000_create_users_table.php')
            ->andReturn('2023_01_01_000000_create_users_table');
        $this->command->shouldReceive('getMigrationName')
            ->with('/path/to/migrations/2023_01_02_000000_create_posts_table.php')
            ->andReturn('2023_01_02_000000_create_posts_table');

        $this->command->shouldReceive('isMigrationRun')
            ->with('2023_01_01_000000_create_users_table')
            ->andReturn(true); // Already run
        $this->command->shouldReceive('isMigrationRun')
            ->with('2023_01_02_000000_create_posts_table')
            ->andReturn(false); // Pending

        // Execute using reflection to call protected method
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getMigrationsToAnalyze');
        $method->setAccessible(true);
        $result = $method->invoke($this->command);

        // Should return only pending migration
        expect($result)->toBe(['/path/to/migrations/2023_01_02_000000_create_posts_table.php']);
    });
});

describe('analyzeMigration', function () {
    it('displays no operations message when migration has no operations (covers line 98)', function () {
        // Mock console output
        $this->command->shouldReceive('getMigrationName')->with('/path/to/migration.php')->andReturn('create_users_table');
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('info')->with('📄 <options=bold>Migration:</options=bold> <fg=cyan>create_users_table</fg=cyan>')->once();
        $this->command->shouldReceive('comment')->with('───────────────────────────────────────────────────────────────────────────────')->once();
        $this->command->shouldReceive('comment')->with('   ℹ️  No operations detected in this migration')->once(); // Line 98

        // Mock analyzer to return analysis with empty operations
        $this->mockAnalyzer->shouldReceive('analyze')->with('/path/to/migration.php')->andReturn([
            'operations' => [], // Empty operations triggers line 98
            'summary' => ['safe' => 0, 'warnings' => 0, 'dangerous' => 0],
            'estimated_time' => '~10ms',
        ]);

        $this->command->shouldReceive('displaySummary')->once();

        // Execute using reflection to call protected method
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('analyzeMigration');
        $method->setAccessible(true);
        $method->invoke($this->command, '/path/to/migration.php');
    });

    it('handles analyzer exceptions (covers lines 107-108)', function () {
        // Mock console output
        $this->command->shouldReceive('getMigrationName')->with('/path/to/migration.php')->andReturn('create_users_table');
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('info')->with('📄 <options=bold>Migration:</options=bold> <fg=cyan>create_users_table</fg=cyan>')->once();
        $this->command->shouldReceive('comment')->with('───────────────────────────────────────────────────────────────────────────────')->once();
        $this->command->shouldReceive('error')->with('❌ <fg=red>Failed to analyze migration:</fg=red> Parsing failed')->once(); // Line 108

        // Mock analyzer to throw exception
        $this->mockAnalyzer->shouldReceive('analyze')->with('/path/to/migration.php')->andThrow(new Exception('Parsing failed'));

        // Execute using reflection to call protected method (covers lines 107-108)
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('analyzeMigration');
        $method->setAccessible(true);
        $method->invoke($this->command, '/path/to/migration.php');
    });

    it('displays operations when analysis succeeds', function () {
        // Mock console output
        $this->command->shouldReceive('getMigrationName')->with('/path/to/migration.php')->andReturn('create_users_table');
        $this->command->shouldReceive('newLine')->atLeast()->once();
        $this->command->shouldReceive('info')->with('📄 <options=bold>Migration:</options=bold> <fg=cyan>create_users_table</fg=cyan>')->once();
        $this->command->shouldReceive('comment')->with('───────────────────────────────────────────────────────────────────────────────')->once();

        // Mock analyzer to return analysis with operations
        $operations = [
            ['risk' => 'safe', 'description' => 'Create table users', 'sql' => 'CREATE TABLE users', 'impact' => 'New table', 'duration' => '~10ms'],
        ];
        $this->mockAnalyzer->shouldReceive('analyze')->with('/path/to/migration.php')->andReturn([
            'operations' => $operations,
            'summary' => ['safe' => 1, 'warnings' => 0, 'dangerous' => 0],
            'estimated_time' => '~10ms',
        ]);

        $this->command->shouldReceive('displayOperation')->with($operations[0])->once();
        $this->command->shouldReceive('displaySummary')->once();

        // Execute using reflection to call protected method
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('analyzeMigration');
        $method->setAccessible(true);
        $method->invoke($this->command, '/path/to/migration.php');
    });
});

describe('displaySummary', function () {
    it('displays safe operations summary (covers line 141)', function () {
        $analysis = [
            'summary' => ['safe' => 3, 'warnings' => 0, 'dangerous' => 0],
            'estimated_time' => '~30ms',
        ];

        // Mock console output including the safe operations line
        $this->command->shouldReceive('comment')->with('┌─ <options=bold>📊 Summary</options=bold> ─────────────────────────────────────────────────────────┐')->once();
        $this->command->shouldReceive('comment')->with('│                                                                              │')->atLeast()->once();
        $this->command->shouldReceive('comment')->with('│   ✅ <fg=green>3 safe operation(s)</fg=green>'.str_repeat(' ', 49).'│')->once(); // Line 141
        $this->command->shouldReceive('comment')->with('│   ⏱️  <fg=magenta>Estimated total time: ~30ms</fg=magenta>'.str_repeat(' ', 33).'│')->once();
        $this->command->shouldReceive('comment')->with('└──────────────────────────────────────────────────────────────────────────┘')->once();
        $this->command->shouldReceive('newLine')->once();

        // Execute using reflection to call protected method
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('displaySummary');
        $method->setAccessible(true);
        $method->invoke($this->command, $analysis);
    });

    it('displays dangerous operations summary (covers line 149)', function () {
        $analysis = [
            'summary' => ['safe' => 0, 'warnings' => 0, 'dangerous' => 2],
            'estimated_time' => '~20ms',
        ];

        // Mock console output for dangerous operations (covers line 149)
        $this->command->shouldReceive('comment')->with(Mockery::pattern('/┌─.*Summary.*─+┐/'))->once();
        $this->command->shouldReceive('comment')->with('│                                                                              │')->atLeast()->once();
        $this->command->shouldReceive('comment')->with(Mockery::pattern('/│.*🔴.*dangerous operation\(s\).*│/'))->once(); // Line 149
        $this->command->shouldReceive('comment')->with(Mockery::pattern('/│.*⏱️.*Estimated total time.*│/'))->once();
        $this->command->shouldReceive('comment')->with('└──────────────────────────────────────────────────────────────────────────┘')->once();
        $this->command->shouldReceive('newLine')->once();

        // Execute using reflection to call protected method
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('displaySummary');
        $method->setAccessible(true);
        $method->invoke($this->command, $analysis);
    });

    it('displays all summary types together', function () {
        $analysis = [
            'summary' => ['safe' => 2, 'warnings' => 1, 'dangerous' => 1],
            'estimated_time' => '~50ms',
        ];

        // Mock console output for all summary types
        $this->command->shouldReceive('comment')->with(Mockery::pattern('/┌─.*Summary.*─+┐/'))->once();
        $this->command->shouldReceive('comment')->with('│                                                                              │')->atLeast()->once();
        $this->command->shouldReceive('comment')->with(Mockery::pattern('/│.*✅.*safe operation\(s\).*│/'))->once();
        $this->command->shouldReceive('comment')->with(Mockery::pattern('/│.*⚠️.*warning\(s\).*│/'))->once();
        $this->command->shouldReceive('comment')->with(Mockery::pattern('/│.*🔴.*dangerous operation\(s\).*│/'))->once();
        $this->command->shouldReceive('comment')->with(Mockery::pattern('/│.*⏱️.*Estimated total time.*│/'))->once();
        $this->command->shouldReceive('comment')->with('└──────────────────────────────────────────────────────────────────────────┘')->once();
        $this->command->shouldReceive('newLine')->once();

        // Execute using reflection to call protected method
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('displaySummary');
        $method->setAccessible(true);
        $method->invoke($this->command, $analysis);
    });
});

describe('risk methods', function () {
    it('covers safe risk cases (covers lines 165, 175, 185)', function () {
        // Execute using reflection to call protected methods
        $reflection = new ReflectionClass($this->command);

        $iconMethod = $reflection->getMethod('getRiskIcon');
        $iconMethod->setAccessible(true);
        $icon = $iconMethod->invoke($this->command, 'safe');
        expect($icon)->toBe('✅'); // Line 165

        $labelMethod = $reflection->getMethod('getRiskLabel');
        $labelMethod->setAccessible(true);
        $label = $labelMethod->invoke($this->command, 'safe');
        expect($label)->toBe('SAFE   '); // Line 175

        $colorMethod = $reflection->getMethod('getRiskColor');
        $colorMethod->setAccessible(true);
        $color = $colorMethod->invoke($this->command, 'safe');
        expect($color)->toBe('fg=green'); // Line 185
    });

    it('covers danger risk cases (covers lines 167, 177, 187)', function () {
        // Execute using reflection to call protected methods
        $reflection = new ReflectionClass($this->command);

        $iconMethod = $reflection->getMethod('getRiskIcon');
        $iconMethod->setAccessible(true);
        $icon = $iconMethod->invoke($this->command, 'danger');
        expect($icon)->toBe('🔴'); // Line 167

        $labelMethod = $reflection->getMethod('getRiskLabel');
        $labelMethod->setAccessible(true);
        $label = $labelMethod->invoke($this->command, 'danger');
        expect($label)->toBe('DANGER '); // Line 177

        $colorMethod = $reflection->getMethod('getRiskColor');
        $colorMethod->setAccessible(true);
        $color = $colorMethod->invoke($this->command, 'danger');
        expect($color)->toBe('fg=red'); // Line 187
    });

    it('covers warning risk cases', function () {
        // Execute using reflection to call protected methods
        $reflection = new ReflectionClass($this->command);

        $iconMethod = $reflection->getMethod('getRiskIcon');
        $iconMethod->setAccessible(true);
        $icon = $iconMethod->invoke($this->command, 'warning');
        expect($icon)->toBe('⚠️');

        $labelMethod = $reflection->getMethod('getRiskLabel');
        $labelMethod->setAccessible(true);
        $label = $labelMethod->invoke($this->command, 'warning');
        expect($label)->toBe('WARNING');

        $colorMethod = $reflection->getMethod('getRiskColor');
        $colorMethod->setAccessible(true);
        $color = $colorMethod->invoke($this->command, 'warning');
        expect($color)->toBe('fg=yellow');
    });

    it('covers default/unknown risk cases', function () {
        // Execute using reflection to call protected methods
        $reflection = new ReflectionClass($this->command);

        $iconMethod = $reflection->getMethod('getRiskIcon');
        $iconMethod->setAccessible(true);
        $icon = $iconMethod->invoke($this->command, 'unknown');
        expect($icon)->toBe('❓');

        $labelMethod = $reflection->getMethod('getRiskLabel');
        $labelMethod->setAccessible(true);
        $label = $labelMethod->invoke($this->command, 'unknown');
        expect($label)->toBe('UNKNOWN');

        $colorMethod = $reflection->getMethod('getRiskColor');
        $colorMethod->setAccessible(true);
        $color = $colorMethod->invoke($this->command, 'unknown');
        expect($color)->toBe('fg=gray');
    });
});

describe('displayOperation', function () {
    it('displays operation with all information (covers lines 114-132)', function () {
        $operation = [
            'risk' => 'warning',
            'description' => 'Add column email to users table',
            'sql' => 'ALTER TABLE users ADD email VARCHAR(255)',
            'impact' => '1000 rows affected',
            'duration' => '~50ms',
        ];

        // Mock all the console output methods
        $this->command->shouldReceive('getRiskIcon')->with('warning')->andReturn('⚠️');
        $this->command->shouldReceive('getRiskLabel')->with('warning')->andReturn('WARNING');
        $this->command->shouldReceive('getRiskColor')->with('warning')->andReturn('fg=yellow');

        // Mock the line outputs (covers lines 118, 121, 125, 129)
        $this->command->shouldReceive('line')->with('   ⚠️ <fg=yellow>WARNING</fg=yellow> │ Add column email to users table')->once(); // Line 118
        $this->command->shouldReceive('line')->with('             │ <fg=gray>SQL:</fg=gray> <fg=blue>ALTER TABLE users ADD email VARCHAR(255)</fg=blue>')->once(); // Line 121
        $this->command->shouldReceive('line')->with('             │ <fg=gray>Impact:</fg=gray> 1000 rows affected')->once(); // Line 125
        $this->command->shouldReceive('line')->with('             │ <fg=gray>Duration:</fg=gray> <fg=magenta>~50ms</fg=magenta>')->once(); // Line 129
        $this->command->shouldReceive('newLine')->once(); // Line 132

        // Execute using reflection to call protected method
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('displayOperation');
        $method->setAccessible(true);
        $method->invoke($this->command, $operation);
    });

    it('displays operation with minimal information', function () {
        $operation = [
            'risk' => 'safe',
            'description' => 'Create table users',
            // No sql, impact, or duration
        ];

        // Mock the required methods
        $this->command->shouldReceive('getRiskIcon')->with('safe')->andReturn('✅');
        $this->command->shouldReceive('getRiskLabel')->with('safe')->andReturn('SAFE   ');
        $this->command->shouldReceive('getRiskColor')->with('safe')->andReturn('fg=green');

        // Mock only the basic line output (other lines shouldn't be called due to empty checks)
        $this->command->shouldReceive('line')->with('   ✅ <fg=green>SAFE   </fg=green> │ Create table users')->once();
        $this->command->shouldReceive('newLine')->once();

        // Execute using reflection to call protected method
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('displayOperation');
        $method->setAccessible(true);
        $method->invoke($this->command, $operation);
    });

    it('displays operation with some information missing', function () {
        $operation = [
            'risk' => 'danger',
            'description' => 'Drop table old_data',
            'sql' => 'DROP TABLE old_data',
            // No impact or duration
        ];

        // Mock the required methods
        $this->command->shouldReceive('getRiskIcon')->with('danger')->andReturn('🔴');
        $this->command->shouldReceive('getRiskLabel')->with('danger')->andReturn('DANGER ');
        $this->command->shouldReceive('getRiskColor')->with('danger')->andReturn('fg=red');

        // Mock line outputs for available information
        $this->command->shouldReceive('line')->with('   🔴 <fg=red>DANGER </fg=red> │ Drop table old_data')->once();
        $this->command->shouldReceive('line')->with('             │ <fg=gray>SQL:</fg=gray> <fg=blue>DROP TABLE old_data</fg=blue>')->once();
        $this->command->shouldReceive('newLine')->once();

        // Execute using reflection to call protected method
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('displayOperation');
        $method->setAccessible(true);
        $method->invoke($this->command, $operation);
    });
});

describe('getMigrationPath', function () {
    it('returns custom path when --path option is provided (covers line 195)', function () {
        // Mock command to return custom path option
        $this->command->shouldReceive('option')->with('path')->andReturn('custom/migrations');

        // Mock Laravel application
        $mockLaravel = Mockery::mock();
        $mockLaravel->shouldReceive('basePath')->andReturn('/app');

        // Set the laravel property using reflection
        $reflection = new ReflectionClass($this->command);
        $laravelProperty = $reflection->getProperty('laravel');
        $laravelProperty->setAccessible(true);
        $laravelProperty->setValue($this->command, $mockLaravel);

        // Execute using reflection to call protected method
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getMigrationPath');
        $method->setAccessible(true);
        $result = $method->invoke($this->command);

        // Should return custom path (covers line 195)
        expect($result)->toBe('/app/custom/migrations');
    });

    it('returns default database path when no --path option', function () {
        // Mock command to return no path option
        $this->command->shouldReceive('option')->with('path')->andReturn(null);

        // Execute using reflection to call protected method
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getMigrationPath');
        $method->setAccessible(true);
        $result = $method->invoke($this->command);

        // Should return default database migrations path
        expect($result)->toContain('database/migrations');
    });
});

describe('utility methods', function () {
    it('extracts migration name from file path', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getMigrationName');
        $method->setAccessible(true);
        $result = $method->invoke($this->command, '/path/to/2023_01_01_000000_create_users_table.php');

        expect($result)->toBe('2023_01_01_000000_create_users_table');
    });

    it('checks if migration is run', function () {
        // Mock DB query
        $mockBuilder = Mockery::mock();
        $mockBuilder->shouldReceive('where')->with('migration', 'create_users_table')->andReturnSelf();
        $mockBuilder->shouldReceive('exists')->andReturn(true);
        DB::shouldReceive('table')->with('migrations')->andReturn($mockBuilder);

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('isMigrationRun');
        $method->setAccessible(true);
        $result = $method->invoke($this->command, 'create_users_table');

        expect($result)->toBe(true);
    });
});
