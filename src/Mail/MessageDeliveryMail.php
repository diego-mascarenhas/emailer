<?php

namespace idoneo\Emailer\Mail;

use idoneo\Emailer\Models\MessageDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

class MessageDeliveryMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $delivery;

    /**
     * Create a new message instance.
     */
    public function __construct(MessageDelivery $delivery)
    {
        $this->delivery = $delivery;
        $this->onQueue(config('emailer.queue_name', 'emailer'));
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $subject = $this->delivery->message
            ? ($this->delivery->message->subject ?? $this->delivery->message->name)
            : 'Newsletter';

        $html = $this->delivery->getHtmlForContact();

        // Add advertising footer if team is using system SMTP
        $advertisingFooter = config('emailer.mail_advertising_footer', '');
        if ($advertisingFooter) {
            $html .= $advertisingFooter;
        }

        // Inline CSS for better email client compatibility
        if (class_exists('\TijsVerkoyen\CssToInlineStyles\CssToInlineStyles')) {
            $css = config('emailer.default_css', '');
            $inliner = new CssToInlineStyles;
            $htmlInlined = $inliner->convert($html, $css);
        } else {
            $htmlInlined = $html;
        }

        return $this->subject($subject)
            ->html($htmlInlined)
            ->from(
                $this->getFromAddress(),
                $this->getFromName()
            );
    }

    /**
     * Get the from address for this delivery
     */
    protected function getFromAddress()
    {
        // First check if team has custom from address
        if ($this->delivery->team && method_exists($this->delivery->team, 'getOutgoingEmailConfig')) {
            $config = $this->delivery->team->getOutgoingEmailConfig();
            if (!empty($config['from_address'])) {
                return $config['from_address'];
            }
        }

        // Fall back to configured default
        return config('mail.from.address', config('emailer.from_address', 'noreply@example.com'));
    }

    /**
     * Get the from name for this delivery
     */
    protected function getFromName()
    {
        // First check if team has custom from name
        if ($this->delivery->team && method_exists($this->delivery->team, 'getOutgoingEmailConfig')) {
            $config = $this->delivery->team->getOutgoingEmailConfig();
            if (!empty($config['from_name'])) {
                return $config['from_name'];
            }
        }

        // Fall back to configured default
        return config('mail.from.name', config('emailer.from_name', 'Emailer'));
    }

    /**
     * Get the reply-to address if configured
     */
    protected function getReplyTo()
    {
        $replyTo = config('emailer.reply_to_address');
        if ($replyTo) {
            return [$replyTo => config('emailer.reply_to_name', config('emailer.from_name', 'Emailer'))];
        }

        return [];
    }
}
