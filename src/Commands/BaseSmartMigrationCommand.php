<?php

namespace Flux\Commands;

use Flux\Config\SmartMigrationConfig;
use Illuminate\Console\Command;

abstract class BaseSmartMigrationCommand extends Command
{
    /**
     * Write an info message with optional emoji
     */
    protected function infoWithEmoji(string $emoji, string $message): void
    {
        if (SmartMigrationConfig::emojisEnabled()) {
            $this->info("{$emoji} {$message}");
        } else {
            $this->info($message);
        }
    }

    /**
     * Write a comment message with optional emoji
     */
    protected function commentWithEmoji(string $emoji, string $message): void
    {
        if (SmartMigrationConfig::emojisEnabled()) {
            $this->comment("{$emoji} {$message}");
        } else {
            $this->comment($message);
        }
    }

    /**
     * Write an error message with optional emoji
     */
    protected function errorWithEmoji(string $emoji, string $message): void
    {
        if (SmartMigrationConfig::emojisEnabled()) {
            $this->error("{$emoji} {$message}");
        } else {
            $this->error($message);
        }
    }

    /**
     * Write a warning message with optional emoji
     */
    protected function warnWithEmoji(string $emoji, string $message): void
    {
        if (SmartMigrationConfig::emojisEnabled()) {
            $this->warn("{$emoji} {$message}");
        } else {
            $this->warn($message);
        }
    }

    /**
     * Display a colored message based on configuration
     */
    protected function displayColored(string $message, string $color = 'info'): void
    {
        if (! SmartMigrationConfig::colorsEnabled()) {
            $this->line($message);

            return;
        }

        match ($color) {
            'info' => $this->info($message),
            'comment' => $this->comment($message),
            'error' => $this->error($message),
            'warn' => $this->warn($message),
            default => $this->line($message),
        };
    }

    /**
     * Display SQL if configured
     */
    protected function displaySql(string $sql): void
    {
        if (SmartMigrationConfig::showSql()) {
            $this->displayColored("SQL: {$sql}", 'comment');
        }
    }

    /**
     * Display timing if configured
     */
    protected function displayTiming(float $startTime): void
    {
        if (SmartMigrationConfig::showTiming()) {
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->displayColored("Duration: {$duration}ms", 'info');
        }
    }

    /**
     * Check if progress bars should be shown
     */
    protected function shouldShowProgressBar(): bool
    {
        return SmartMigrationConfig::progressBarsEnabled();
    }

    /**
     * Get risk emoji based on risk level
     */
    protected function getRiskEmoji(string $risk): string
    {
        if (! SmartMigrationConfig::emojisEnabled()) {
            return '';
        }

        return match ($risk) {
            'safe' => '✅',
            'warning' => '⚠️',
            'danger' => '🔴',
            default => '❓',
        };
    }

    /**
     * Get risk color based on risk level
     */
    protected function getRiskColor(string $risk): string
    {
        return match ($risk) {
            'safe' => 'green',
            'warning' => 'yellow',
            'danger' => 'red',
            default => 'gray',
        };
    }

    /**
     * Format risk display
     */
    protected function formatRisk(string $risk): string
    {
        $emoji = $this->getRiskEmoji($risk);
        $label = strtoupper($risk);

        if (! SmartMigrationConfig::colorsEnabled()) {
            return $emoji ? "{$emoji} {$label}" : $label;
        }

        $color = $this->getRiskColor($risk);

        return $emoji ? "{$emoji} <fg={$color}>{$label}</fg={$color}>" : "<fg={$color}>{$label}</fg={$color}>";
    }
}
