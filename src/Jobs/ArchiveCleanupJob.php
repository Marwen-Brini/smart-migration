<?php

namespace Flux\Jobs;

use Flux\Cleanup\ArchiveCleanupService;
use Flux\Config\SmartMigrationConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ArchiveCleanupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ArchiveCleanupService $cleanupService;

    /**
     * Create a new job instance
     */
    public function __construct(?ArchiveCleanupService $cleanupService = null)
    {
        $this->cleanupService = $cleanupService ?? new ArchiveCleanupService;
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        // Check if auto cleanup is enabled
        if (! SmartMigrationConfig::autoCleanupEnabled()) {
            return;
        }

        try {
            $result = $this->cleanupService->cleanup(false);

            // Log successful cleanup
            if (SmartMigrationConfig::loggingEnabled()) {
                Log::channel(SmartMigrationConfig::getLogChannel())->info(
                    'Scheduled archive cleanup completed',
                    $result
                );
            }

            // Send notification if configured
            if (SmartMigrationConfig::notificationsEnabled() &&
                SmartMigrationConfig::shouldNotifyEvent('archive_cleanup')) {
                $this->sendNotification($result);
            }

        } catch (\Exception $e) {
            // Log error
            if (SmartMigrationConfig::loggingEnabled()) {
                Log::channel(SmartMigrationConfig::getLogChannel())->error(
                    'Archive cleanup failed',
                    [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]
                );
            }

            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Send notification about cleanup
     */
    protected function sendNotification(array $result): void
    {
        $channels = SmartMigrationConfig::getNotificationChannels();

        foreach ($channels as $channel) {
            switch ($channel) {
                case 'slack':
                    $this->sendSlackNotification($result);
                    break;
                case 'webhook':
                    $this->sendWebhookNotification($result);
                    break;
                    // Add more channels as needed
            }
        }
    }

    /**
     * Send Slack notification
     */
    protected function sendSlackNotification(array $result): void
    {
        $webhook = SmartMigrationConfig::get('notifications.slack_webhook');

        if (! $webhook) {
            return;
        }

        $message = sprintf(
            "Archive cleanup completed:\n• Tables cleaned: %d\n• Columns cleaned: %d\n• Rows deleted: %s",
            count($result['tables_cleaned']),
            count($result['columns_cleaned']),
            number_format($result['total_rows_deleted'])
        );

        // Send to Slack (simplified version)
        $payload = [
            'text' => $message,
            'username' => 'Smart Migration',
            'icon_emoji' => ':broom:',
        ];

        $ch = curl_init($webhook);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Send webhook notification
     */
    protected function sendWebhookNotification(array $result): void
    {
        $webhook = SmartMigrationConfig::get('notifications.webhook_url');

        if (! $webhook) {
            return;
        }

        $payload = [
            'event' => 'archive_cleanup',
            'timestamp' => now()->toIso8601String(),
            'result' => $result,
        ];

        $ch = curl_init($webhook);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }
}
