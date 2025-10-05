<?php

use Flux\Commands\BaseSmartMigrationCommand;
use Illuminate\Support\Facades\Config;

// Create a concrete implementation for testing
class TestSmartMigrationCommand extends BaseSmartMigrationCommand
{
    protected $signature = 'test:command';

    protected $description = 'Test command';

    public function handle()
    {
        // Implementation not needed for testing
    }

    // Expose protected methods for testing
    public function testInfoWithEmoji(string $emoji, string $message): void
    {
        $this->infoWithEmoji($emoji, $message);
    }

    public function testCommentWithEmoji(string $emoji, string $message): void
    {
        $this->commentWithEmoji($emoji, $message);
    }

    public function testErrorWithEmoji(string $emoji, string $message): void
    {
        $this->errorWithEmoji($emoji, $message);
    }

    public function testWarnWithEmoji(string $emoji, string $message): void
    {
        $this->warnWithEmoji($emoji, $message);
    }

    public function testDisplayColored(string $message, string $color = 'info'): void
    {
        $this->displayColored($message, $color);
    }

    public function testDisplaySql(string $sql): void
    {
        $this->displaySql($sql);
    }

    public function testDisplayTiming(float $startTime): void
    {
        $this->displayTiming($startTime);
    }

    public function testShouldShowProgressBar(): bool
    {
        return $this->shouldShowProgressBar();
    }

    public function testGetRiskEmoji(string $risk): string
    {
        return $this->getRiskEmoji($risk);
    }

    public function testGetRiskColor(string $risk): string
    {
        return $this->getRiskColor($risk);
    }

    public function testFormatRisk(string $risk): string
    {
        return $this->formatRisk($risk);
    }
}

beforeEach(function () {
    $this->command = \Mockery::mock(TestSmartMigrationCommand::class)->makePartial()->shouldAllowMockingProtectedMethods();

    // Mock the output interface to avoid null reference errors
    $output = \Mockery::mock('\Symfony\Component\Console\Output\OutputInterface');
    $output->shouldReceive('writeln')->byDefault();

    // Set the output property directly
    $reflection = new \ReflectionClass($this->command);
    $outputProperty = $reflection->getParentClass()->getProperty('output');
    $outputProperty->setAccessible(true);
    $outputProperty->setValue($this->command, $output);
});

afterEach(function () {
    \Mockery::close();
});

describe('infoWithEmoji method', function () {
    it('displays message with emoji when emojis are enabled', function () {
        // Emojis are enabled by default in TestCase config
        $this->command->shouldReceive('info')->once()->with('🎉 Test message');

        $this->command->testInfoWithEmoji('🎉', 'Test message');
    });

    it('displays message without emoji when emojis are disabled', function () {
        // Temporarily disable emojis for this test
        config(['smart-migration.display.emojis' => false]);

        $this->command->shouldReceive('info')->once()->with('Test message');

        $this->command->testInfoWithEmoji('🎉', 'Test message');
    });
});

describe('commentWithEmoji method', function () {
    it('displays comment with emoji when emojis are enabled', function () {
        // Emojis are enabled by default in TestCase config
        $this->command->shouldReceive('comment')->once()->with('💬 Test comment');

        $this->command->testCommentWithEmoji('💬', 'Test comment');
    });

    it('displays comment without emoji when emojis are disabled', function () {
        config(['smart-migration.display.emojis' => false]);

        $this->command->shouldReceive('comment')->once()->with('Test comment');

        $this->command->testCommentWithEmoji('💬', 'Test comment');
    });
});

describe('errorWithEmoji method', function () {
    it('displays error with emoji when emojis are enabled', function () {
        // Emojis are enabled by default in TestCase config
        $this->command->shouldReceive('error')->once()->with('❌ Test error');

        $this->command->testErrorWithEmoji('❌', 'Test error');
    });

    it('displays error without emoji when emojis are disabled', function () {
        config(['smart-migration.display.emojis' => false]);

        $this->command->shouldReceive('error')->once()->with('Test error');

        $this->command->testErrorWithEmoji('❌', 'Test error');
    });
});

describe('warnWithEmoji method', function () {
    it('displays warning with emoji when emojis are enabled', function () {
        // Emojis are enabled by default in TestCase config
        $this->command->shouldReceive('warn')->once()->with('⚠️ Test warning');

        $this->command->testWarnWithEmoji('⚠️', 'Test warning');
    });

    it('displays warning without emoji when emojis are disabled', function () {
        config(['smart-migration.display.emojis' => false]);

        $this->command->shouldReceive('warn')->once()->with('Test warning');

        $this->command->testWarnWithEmoji('⚠️', 'Test warning');
    });
});

describe('displayColored method', function () {
    it('displays plain text when colors are disabled', function () {
        config(['smart-migration.display.colors' => false]);

        $this->command->shouldReceive('line')->once()->with('Test message');

        $this->command->testDisplayColored('Test message', 'info');
    });

    it('displays info colored message when colors are enabled', function () {
        // Colors are enabled by default in TestCase config
        $this->command->shouldReceive('info')->once()->with('Test message');

        $this->command->testDisplayColored('Test message', 'info');
    });

    it('displays comment colored message when colors are enabled', function () {
        // Colors are enabled by default in TestCase config
        $this->command->shouldReceive('comment')->once()->with('Test message');

        $this->command->testDisplayColored('Test message', 'comment');
    });

    it('displays error colored message when colors are enabled', function () {
        // Colors are enabled by default in TestCase config
        $this->command->shouldReceive('error')->once()->with('Test message');

        $this->command->testDisplayColored('Test message', 'error');
    });

    it('displays warn colored message when colors are enabled', function () {
        // Colors are enabled by default in TestCase config
        $this->command->shouldReceive('warn')->once()->with('Test message');

        $this->command->testDisplayColored('Test message', 'warn');
    });

    it('displays default line when unknown color is provided', function () {
        // Colors are enabled by default in TestCase config
        $this->command->shouldReceive('line')->once()->with('Test message');

        $this->command->testDisplayColored('Test message', 'unknown');
    });

    it('uses default info color when no color is specified', function () {
        // Colors are enabled by default in TestCase config
        $this->command->shouldReceive('info')->once()->with('Test message');

        $this->command->testDisplayColored('Test message');
    });
});

describe('displaySql method', function () {
    it('displays SQL when showSql is enabled', function () {
        config(['smart-migration.display.show_sql' => true]);

        // displaySql calls displayColored which calls comment() when colors enabled
        $this->command->shouldReceive('comment')->once()->with('SQL: SELECT * FROM users');

        $this->command->testDisplaySql('SELECT * FROM users');
    });

    it('does not display SQL when showSql is disabled', function () {
        config(['smart-migration.display.show_sql' => false]);

        $this->command->shouldNotReceive('comment');
        $this->command->shouldNotReceive('line');

        $this->command->testDisplaySql('SELECT * FROM users');
    });
});

describe('displayTiming method', function () {
    it('displays timing when showTiming is enabled', function () {
        $startTime = microtime(true) - 0.5; // 500ms ago

        // Mock the internal displayColored method that displayTiming calls
        $this->command->shouldReceive('displayColored')->once()->withArgs(function ($message, $color) {
            return preg_match('/Duration: \d+ms/', $message) && $color === 'info';
        });

        $this->command->testDisplayTiming($startTime);
    });

    it('does not display timing when showTiming is disabled', function () {
        $startTime = microtime(true);

        config(['smart-migration.display.show_timing' => false]);

        $this->command->shouldNotReceive('displayColored');

        $this->command->testDisplayTiming($startTime);
    });
});

describe('shouldShowProgressBar method', function () {
    it('returns true when progress bars are enabled', function () {
        // Progress bars are enabled by default in TestCase config
        $result = $this->command->testShouldShowProgressBar();

        expect($result)->toBe(true);
    });

    it('returns false when progress bars are disabled', function () {
        config(['smart-migration.display.progress_bars' => false]);

        $result = $this->command->testShouldShowProgressBar();

        expect($result)->toBe(false);
    });
});

describe('getRiskEmoji method', function () {
    it('returns correct emoji for each risk level when emojis enabled', function () {
        // Emojis are enabled by default in TestCase config
        expect($this->command->testGetRiskEmoji('safe'))->toBe('✅');
        expect($this->command->testGetRiskEmoji('warning'))->toBe('⚠️');
        expect($this->command->testGetRiskEmoji('danger'))->toBe('🔴');
        expect($this->command->testGetRiskEmoji('unknown'))->toBe('❓');
    });

    it('returns empty string when emojis disabled', function () {
        config(['smart-migration.display.emojis' => false]);

        expect($this->command->testGetRiskEmoji('safe'))->toBe('');
        expect($this->command->testGetRiskEmoji('warning'))->toBe('');
        expect($this->command->testGetRiskEmoji('danger'))->toBe('');
        expect($this->command->testGetRiskEmoji('unknown'))->toBe('');
    });
});

describe('getRiskColor method', function () {
    it('returns correct color for each risk level', function () {
        expect($this->command->testGetRiskColor('safe'))->toBe('green');
        expect($this->command->testGetRiskColor('warning'))->toBe('yellow');
        expect($this->command->testGetRiskColor('danger'))->toBe('red');
        expect($this->command->testGetRiskColor('unknown'))->toBe('gray');
    });
});

describe('formatRisk method', function () {
    it('formats risk with emoji and color when both enabled', function () {
        // Emojis and colors are enabled by default in TestCase config
        $result = $this->command->testFormatRisk('safe');

        expect($result)->toBe('✅ <fg=green>SAFE</fg=green>');
    });

    it('formats risk with emoji but no color when colors disabled', function () {
        config(['smart-migration.display.colors' => false]);

        $result = $this->command->testFormatRisk('warning');

        expect($result)->toBe('⚠️ WARNING');
    });

    it('formats risk with color but no emoji when emojis disabled', function () {
        config(['smart-migration.display.emojis' => false]);

        $result = $this->command->testFormatRisk('danger');

        expect($result)->toBe('<fg=red>DANGER</fg=red>');
    });

    it('formats risk with no emoji or color when both disabled', function () {
        config(['smart-migration.display.emojis' => false]);
        config(['smart-migration.display.colors' => false]);

        $result = $this->command->testFormatRisk('safe');

        expect($result)->toBe('SAFE');
    });

    it('handles unknown risk levels correctly', function () {
        // Emojis and colors are enabled by default in TestCase config
        $result = $this->command->testFormatRisk('unknown');

        expect($result)->toBe('❓ <fg=gray>UNKNOWN</fg=gray>');
    });
});
