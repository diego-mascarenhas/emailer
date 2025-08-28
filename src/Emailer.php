<?php

namespace idoneo\Emailer;

use idoneo\Emailer\Jobs\SendMessageCampaignJob;
use idoneo\Emailer\Models\Message;
use idoneo\Emailer\Models\MessageDelivery;
use idoneo\Emailer\Models\MessageDeliveryStat;

class Emailer
{
    /**
     * Get package version
     */
    public function version(): string
    {
        return '1.0.0';
    }

    /**
     * Start a message campaign
     */
    public function startCampaign(Message $message): bool
    {
        try {
            // Populate message deliveries if not already done
            $this->populateMessageDeliveries($message);

            // Update message status to active
            $message->update(['status_id' => 1]);

            return true;
        } catch (\Exception $e) {
            \Log::error('Emailer: Failed to start campaign', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Stop a message campaign
     */
    public function stopCampaign(Message $message): bool
    {
        try {
            // Update message status to inactive
            $message->update(['status_id' => 0]);

            return true;
        } catch (\Exception $e) {
            \Log::error('Emailer: Failed to stop campaign', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send a test email
     */
    public function sendTest(Message $message, string $email, string $name = null): bool
    {
        try {
            // Create a temporary delivery for testing
            $delivery = new MessageDelivery([
                'team_id' => $message->team_id,
                'message_id' => $message->id,
                'recipient_email' => $email,
                'recipient_name' => $name,
                'status_id' => 0,
            ]);
            $delivery->save();

            // Dispatch the job immediately
            SendMessageCampaignJob::dispatchSync($delivery);

            return true;
        } catch (\Exception $e) {
            \Log::error('Emailer: Failed to send test email', [
                'message_id' => $message->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get campaign statistics
     */
    public function getCampaignStats(Message $message): array
    {
        $stats = MessageDeliveryStat::updateForMessage($message->id);

        return [
            'total_contacts' => $stats->total_contacts ?? 0,
            'sent' => $stats->sent ?? 0,
            'delivered' => $stats->delivered ?? 0,
            'opened' => $stats->opened ?? 0,
            'clicked' => $stats->clicks ?? 0,
            'failed' => $stats->failed ?? 0,
            'pending' => $stats->pending_deliveries ?? 0,
            'success_rate' => $stats->success_rate ?? 0,
            'open_rate' => $stats->open_rate ?? 0,
            'click_rate' => $stats->click_rate ?? 0,
            'bounce_rate' => $stats->bounce_rate ?? 0,
        ];
    }

    /**
     * Populate message deliveries for contacts
     */
    protected function populateMessageDeliveries(Message $message): void
    {
        // Get contacts from the message's category
        $contacts = collect();

        if ($message->category) {
            $contactModel = config('emailer.contact_model', 'App\Models\Contact');
            $contacts = $message->category->contacts()->where('status_id', 1)->get();
        } else {
            // If no category, get all active contacts from the team
            $contactModel = config('emailer.contact_model', 'App\Models\Contact');
            $contacts = $contactModel::where('team_id', $message->team_id)
                ->where('status_id', 1)
                ->whereNotNull('email')
                ->get();
        }

        $contactIndex = 0;
        foreach ($contacts as $contact) {
            // Check if delivery already exists
            $existingDelivery = MessageDelivery::where('message_id', $message->id)
                ->where('contact_id', $contact->id)
                ->first();

            if (!$existingDelivery) {
                // Schedule with configurable intervals from config
                $baseMinutes = config('emailer.delays.base_minutes', 5);
                $maxRandomSeconds = config('emailer.delays.random_seconds', 120);

                $baseDelayMinutes = $contactIndex * $baseMinutes;
                $randomDelaySeconds = rand(0, $maxRandomSeconds);
                $scheduledTime = now()->addMinutes($baseDelayMinutes)->addSeconds($randomDelaySeconds);

                MessageDelivery::create([
                    'team_id' => $message->team_id,
                    'message_id' => $message->id,
                    'contact_id' => $contact->id,
                    'recipient_email' => $contact->email,
                    'recipient_name' => $contact->name ?? '',
                    'status_id' => 0, // 0 = pending
                    'sent_at' => $scheduledTime,
                ]);

                $contactIndex++;
            }
        }
    }
}
