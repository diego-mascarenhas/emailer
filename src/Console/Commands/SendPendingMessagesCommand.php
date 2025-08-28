<?php

namespace idoneo\Emailer\Console\Commands;

use idoneo\Emailer\Jobs\SendMessageCampaignJob;
use idoneo\Emailer\Models\MessageDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendPendingMessagesCommand extends Command
{
    protected $signature = 'emailer:send-pending
                            {--limit=100 : Maximum number of messages to process}
                            {--team= : Send only for specific team ID}
                            {--dry-run : Show what would be sent without actually sending}';

    protected $description = 'Send all pending MessageDelivery records using queue jobs';

    public function handle()
    {
        $limit = $this->option('limit');
        $teamId = $this->option('team');
        $dryRun = $this->option('dry-run');

        $this->info('🚀 Starting Emailer: Send Pending Messages Command');

        // Build query for pending deliveries
        $query = MessageDelivery::whereNull('sent_at')
            ->where('status_id', 0) // 0 = pending
            ->with(['contact', 'message', 'team']);

        // Filter by team if specified
        if ($teamId) {
            $query->where('team_id', $teamId);
            $this->info("📍 Filtering for team ID: {$teamId}");
        }

        // Apply limit
        $query->limit($limit);

        $pendingDeliveries = $query->get();
        $totalPending = $pendingDeliveries->count();

        if ($totalPending === 0) {
            $this->info('✅ No pending message deliveries found.');

            return 0;
        }

        $this->info("📧 Found {$totalPending} pending message deliveries");

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No emails will actually be sent');
        }

        $sent = 0;
        $errors = 0;
        $skipped = 0;

        foreach ($pendingDeliveries as $delivery) {
            try {
                // Validate delivery
                $validationResult = $this->validateDelivery($delivery);

                if ($validationResult !== true) {
                    $this->warn("⚠️  Skipped delivery ID {$delivery->id}: {$validationResult}");
                    $skipped++;

                    continue;
                }

                $recipientEmail = $delivery->contact->email ?? $delivery->recipient_email;

                if ($dryRun) {
                    $this->info("📧 Would send to: {$recipientEmail} (Message: {$delivery->message->name})");
                    $sent++;
                } else {
                    // Dispatch the job with configurable delays
                    $delay = $this->calculateDelay($sent);

                    SendMessageCampaignJob::dispatch($delivery)
                        ->onQueue(config('emailer.queue_name', 'emailer'))
                        ->delay(now()->addSeconds($delay));

                    $this->info("📧 Queued to: {$recipientEmail} (delay: {$delay}s, Message: {$delivery->message->name})");

                    Log::info('Emailer: Message queued for delivery', [
                        'delivery_id' => $delivery->id,
                        'recipient' => $recipientEmail,
                        'message_id' => $delivery->message->id,
                        'delay_seconds' => $delay,
                    ]);

                    $sent++;
                }

            } catch (\Exception $e) {
                $this->error("❌ Error processing delivery ID {$delivery->id}: ".$e->getMessage());

                if (! $dryRun) {
                    $delivery->markAsError();
                }

                $errors++;

                Log::error('Emailer: Error processing delivery', [
                    'delivery_id' => $delivery->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Summary
        $this->newLine();
        $this->info('📊 Summary:');
        $this->info("   ✅ Successfully processed: {$sent}");
        $this->info("   ⚠️  Skipped: {$skipped}");
        $this->info("   ❌ Errors: {$errors}");

        if (! $dryRun && $sent > 0) {
            $this->info('🕐 Total estimated delivery time: '.$this->formatDuration($this->calculateDelay($sent - 1)));
        }

        return 0;
    }

    /**
     * Validate if a delivery can be processed
     */
    protected function validateDelivery(MessageDelivery $delivery): string|true
    {
        // Check if contact exists or we have recipient email
        $recipientEmail = $delivery->contact->email ?? $delivery->recipient_email;
        if (! $recipientEmail) {
            return 'No recipient email available';
        }

        // Check if message exists and is active
        if (! $delivery->message) {
            return 'Message not found';
        }

        if ($delivery->message->status_id != 1) {
            return 'Message is not active (status_id != 1)';
        }

        // Check if delivery is in future (scheduled)
        if ($delivery->sent_at && $delivery->sent_at->isFuture()) {
            return 'Delivery is scheduled for future: '.$delivery->sent_at->format('Y-m-d H:i:s');
        }

        return true;
    }

    /**
     * Calculate delay for a delivery based on its position
     */
    protected function calculateDelay(int $position): int
    {
        $baseMinutes = config('emailer.delays.base_minutes', 5);
        $maxRandomSeconds = config('emailer.delays.random_seconds', 120);

        $baseDelayMinutes = $position * $baseMinutes;
        $randomDelaySeconds = rand(0, $maxRandomSeconds);

        return ($baseDelayMinutes * 60) + $randomDelaySeconds;
    }

    /**
     * Format duration in seconds to human readable format
     */
    protected function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        $parts = [];
        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }
        if ($minutes > 0) {
            $parts[] = "{$minutes}m";
        }
        if ($seconds > 0) {
            $parts[] = "{$seconds}s";
        }

        return implode(' ', $parts) ?: '0s';
    }
}
