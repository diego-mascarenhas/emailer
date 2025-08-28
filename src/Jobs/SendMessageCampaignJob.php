<?php

namespace idoneo\Emailer\Jobs;

use idoneo\Emailer\Mail\MessageDeliveryMail;
use idoneo\Emailer\Models\MessageDelivery;
use idoneo\Emailer\Traits\ConfiguresTeamMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendMessageCampaignJob implements ShouldQueue
{
    use ConfiguresTeamMail, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The message delivery instance.
     *
     * @var \idoneo\Emailer\Models\MessageDelivery
     */
    public $messageDelivery;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job should run.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(MessageDelivery $messageDelivery)
    {
        $this->messageDelivery = $messageDelivery;
        $this->onQueue(config('emailer.queue_name', 'emailer'));
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            Log::info('🚀 Emailer SendMessageCampaignJob: Starting job execution', [
                'delivery_id' => $this->messageDelivery->id,
                'job_queue' => $this->queue ?? 'default',
                'job_attempts' => $this->attempts(),
            ]);

            $this->messageDelivery->load(['contact', 'message', 'message.template', 'team']);

            Log::info('📊 Emailer SendMessageCampaignJob: Data loaded', [
                'delivery_id' => $this->messageDelivery->id,
                'team_id' => $this->messageDelivery->team->id ?? 'null',
                'team_name' => $this->messageDelivery->team->name ?? 'null',
                'contact_email' => $this->messageDelivery->contact->email ?? $this->messageDelivery->recipient_email ?? 'null',
                'message_id' => $this->messageDelivery->message->id ?? 'null',
                'message_name' => $this->messageDelivery->message->name ?? 'null',
            ]);

            // Check if it's time to send (respect scheduled time)
            if ($this->messageDelivery->sent_at && $this->messageDelivery->sent_at->isFuture()) {
                Log::info('⏰ Message delivery not yet time to send, releasing job', [
                    'delivery_id' => $this->messageDelivery->id,
                    'scheduled_time' => $this->messageDelivery->sent_at,
                    'current_time' => now(),
                ]);
                // Release this job to be retried later
                $delay = $this->messageDelivery->sent_at->diffInSeconds(now());
                $this->release($delay);

                return;
            }

            // Check if contact exists and has email or if we have recipient_email
            $recipientEmail = $this->messageDelivery->contact->email ?? $this->messageDelivery->recipient_email;

            if (!$recipientEmail) {
                Log::warning('Message delivery skipped: No recipient email', [
                    'delivery_id' => $this->messageDelivery->id,
                    'contact_id' => $this->messageDelivery->contact_id,
                ]);
                $this->messageDelivery->markAsError();

                return;
            }

            // Update recipient email if not set
            if (!$this->messageDelivery->recipient_email && $recipientEmail) {
                $this->messageDelivery->update(['recipient_email' => $recipientEmail]);
            }

            // Determine email provider
            $emailProvider = config('emailer.email_provider', 'smtp');

            // Send via the appropriate provider
            switch ($emailProvider) {
                case 'mailgun':
                    $this->sendViaMailgun();
                    break;

                case 'sendgrid':
                    $this->sendViaSendGrid();
                    break;

                case 'mailbaby':
                    $this->sendViaMailBaby();
                    break;

                default:
                    $this->sendViaSmtp();
                    break;
            }

            Log::info('✅ Emailer SendMessageCampaignJob: Job completed successfully', [
                'delivery_id' => $this->messageDelivery->id,
                'provider' => $emailProvider,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Emailer SendMessageCampaignJob: Job failed', [
                'delivery_id' => $this->messageDelivery->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->messageDelivery->markAsError();

            // Re-throw the exception to let Laravel handle retries
            throw $e;
        }
    }

    /**
     * Send via Mailgun API
     */
    protected function sendViaMailgun()
    {
        if (!class_exists('\Mailgun\Mailgun')) {
            Log::warning('Mailgun SDK not available, falling back to SMTP');
            $this->sendViaSmtp();
            return;
        }

        try {
            $mgClient = \Mailgun\Mailgun::create(config('services.mailgun.secret'));
            $domain = config('services.mailgun.domain');

            // Render the email content using the Mailable
            $mail = new MessageDeliveryMail($this->messageDelivery);
            $renderedContent = $mail->render();

            Log::info('🔧 Emailer SendMessageCampaignJob: Content rendered for Mailgun', [
                'delivery_id' => $this->messageDelivery->id,
                'message_name' => $this->messageDelivery->message->name,
                'rendered_content_length' => strlen($renderedContent ?? ''),
                'has_html_content' => !empty($renderedContent),
                'from_config' => config('mail.from.name') . ' <' . config('mail.from.address') . '>',
            ]);

            // Send via Mailgun SDK with tracking enabled
            $result = $mgClient->messages()->send($domain, [
                'from' => config('mail.from.name') . ' <' . config('mail.from.address') . '>',
                'to' => $this->messageDelivery->recipient_email,
                'subject' => $this->messageDelivery->message->subject ?? $this->messageDelivery->message->name,
                'html' => $renderedContent,
                'o:tracking' => 'yes',
                'o:tracking-clicks' => 'yes',
                'o:tracking-opens' => 'yes',
            ]);

            // Extract real Message ID from Mailgun response
            $providerMessageId = $result->getId();

            Log::info('✅ Emailer SendMessageCampaignJob: Email sent via Mailgun SDK', [
                'delivery_id' => $this->messageDelivery->id,
                'contact_email' => $this->messageDelivery->recipient_email,
                'provider_message_id' => $providerMessageId,
                'mailgun_response' => $result->getMessage(),
            ]);

            // Mark as sent with real provider message ID
            $this->messageDelivery->update([
                'email_provider' => 'mailgun',
                'provider_message_id' => $providerMessageId,
                'sent_at' => now(),
                'status_id' => 1, // 1 = sent
            ]);

        } catch (\Exception $e) {
            Log::error('Mailgun SDK failed, falling back to Laravel Mail', [
                'delivery_id' => $this->messageDelivery->id,
                'error' => $e->getMessage(),
            ]);

            // Fallback to SMTP if configured
            if (config('emailer.fallback_to_smtp', true)) {
                $this->sendViaSmtp();
            } else {
                throw $e;
            }
        }
    }

    /**
     * Send via SendGrid API
     */
    protected function sendViaSendGrid()
    {
        // Fallback to SMTP for now, or implement SendGrid API
        Log::info('SendGrid not yet implemented, falling back to SMTP');
        $this->sendViaSmtp();
    }

    /**
     * Send via MailBaby API
     */
    protected function sendViaMailBaby()
    {
        // Fallback to SMTP for now, or implement MailBaby API
        Log::info('MailBaby not yet implemented, falling back to SMTP');
        $this->sendViaSmtp();
    }

    /**
     * Send via SMTP
     */
    protected function sendViaSmtp()
    {
        Log::info('📧 Emailer SendMessageCampaignJob: Using SMTP', [
            'delivery_id' => $this->messageDelivery->id,
            'team_id' => $this->messageDelivery->team->id ?? null,
            'team_name' => $this->messageDelivery->team->name ?? null,
            'team_has_custom_smtp' => method_exists($this->messageDelivery->team ?? new \stdClass(), 'hasOutgoingEmailConfig') ?
                $this->messageDelivery->team->hasOutgoingEmailConfig() : false,
            'before_config_host' => config('mail.mailers.smtp.host'),
            'before_config_username' => config('mail.mailers.smtp.username'),
        ]);

        if ($this->messageDelivery->team) {
            $this->configureMailForTeam($this->messageDelivery->team);
        }

        Log::info('✅ Emailer SendMessageCampaignJob: SMTP configured, about to send email', [
            'delivery_id' => $this->messageDelivery->id,
            'contact_email' => $this->messageDelivery->recipient_email,
            'after_config_host' => config('mail.mailers.smtp.host'),
            'after_config_username' => config('mail.mailers.smtp.username'),
            'after_config_from_address' => config('mail.from.address'),
            'after_config_from_name' => config('mail.from.name'),
        ]);

        // Send the email
        Mail::to($this->messageDelivery->recipient_email)
            ->send(new MessageDeliveryMail($this->messageDelivery));

        Log::info('✅ Emailer SendMessageCampaignJob: Email sent via SMTP', [
            'delivery_id' => $this->messageDelivery->id,
            'contact_email' => $this->messageDelivery->recipient_email,
        ]);

        // Mark as sent
        $this->messageDelivery->update([
            'email_provider' => 'smtp',
            'sent_at' => now(),
            'status_id' => 1, // 1 = sent
        ]);
    }
}
