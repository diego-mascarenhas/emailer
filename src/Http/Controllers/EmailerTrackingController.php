<?php

namespace idoneo\Emailer\Http\Controllers;

use idoneo\Emailer\Models\MessageDelivery;
use idoneo\Emailer\Models\MessageDeliveryTracking;
use idoneo\Emailer\Models\MessageDeliveryLink;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class EmailerTrackingController
{
    /**
     * Track email opens via pixel
     */
    public function track(Request $request, $token)
    {
        try {
            $delivery = $this->findDeliveryByToken($token);

            if ($delivery) {
                // Mark as opened
                $delivery->markAsOpened();

                // Create tracking event
                MessageDeliveryTracking::createEvent(
                    $delivery->id,
                    'opened',
                    [
                        'user_agent' => $request->userAgent(),
                        'referer' => $request->header('referer'),
                    ]
                );

                Log::info('Emailer: Email opened', [
                    'delivery_id' => $delivery->id,
                    'token' => $token,
                    'ip' => $request->ip(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Emailer: Error tracking email open', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);
        }

        // Return 1x1 transparent pixel
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($pixel, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT',
        ]);
    }

    /**
     * Track link clicks
     */
    public function trackClick(Request $request, $token)
    {
        try {
            $delivery = $this->findDeliveryByToken($token);
            $originalUrl = $request->input('url');

            if ($delivery && $originalUrl) {
                // Mark as clicked
                $delivery->markAsClicked();

                // Find or create delivery link
                $deliveryLink = MessageDeliveryLink::firstOrCreate(
                    [
                        'message_delivery_id' => $delivery->id,
                        'original_url' => $originalUrl,
                    ],
                    [
                        'link' => $request->fullUrl(),
                        'click_count' => 0,
                    ]
                );

                // Record the click
                $deliveryLink->recordClick();

                // Create tracking event
                MessageDeliveryTracking::createEvent(
                    $delivery->id,
                    'clicked',
                    [
                        'url' => $originalUrl,
                        'user_agent' => $request->userAgent(),
                        'referer' => $request->header('referer'),
                    ]
                );

                Log::info('Emailer: Link clicked', [
                    'delivery_id' => $delivery->id,
                    'token' => $token,
                    'url' => $originalUrl,
                    'ip' => $request->ip(),
                ]);

                // Redirect to original URL
                return redirect($originalUrl);
            }
        } catch (\Exception $e) {
            Log::error('Emailer: Error tracking click', [
                'token' => $token,
                'url' => $request->input('url'),
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback redirect
        return redirect($request->input('url', '/'));
    }

    /**
     * Handle unsubscribe requests
     */
    public function unsubscribe(Request $request, $token)
    {
        try {
            $delivery = $this->findDeliveryByToken($token);

            if ($delivery) {
                // Mark contact as unsubscribed if contact exists
                if ($delivery->contact) {
                    $delivery->contact->update(['subscribed' => false]);
                }

                // Create tracking event
                MessageDeliveryTracking::createEvent(
                    $delivery->id,
                    'unsubscribed'
                );

                Log::info('Emailer: User unsubscribed', [
                    'delivery_id' => $delivery->id,
                    'contact_id' => $delivery->contact_id,
                    'email' => $delivery->recipient_email,
                ]);

                return view('emailer::unsubscribe', [
                    'email' => $delivery->recipient_email,
                    'success' => true,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Emailer: Error processing unsubscribe', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);
        }

        return view('emailer::unsubscribe', [
            'email' => null,
            'success' => false,
        ]);
    }

    /**
     * Handle Mailgun webhooks
     */
    public function mailgunWebhook(Request $request)
    {
        Log::info('Emailer: Mailgun webhook received', $request->all());

        try {
            $eventData = $request->input('event-data', []);
            $messageId = $eventData['message']['headers']['message-id'] ?? null;
            $event = $eventData['event'] ?? null;

            if ($messageId && $event) {
                $delivery = MessageDelivery::where('provider_message_id', $messageId)->first();

                if ($delivery) {
                    $this->processWebhookEvent($delivery, $event, $eventData);
                }
            }

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('Emailer: Error processing Mailgun webhook', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json(['error' => 'Processing failed'], 400);
        }
    }

    /**
     * Handle SendGrid webhooks
     */
    public function sendgridWebhook(Request $request)
    {
        Log::info('Emailer: SendGrid webhook received', $request->all());

        // TODO: Implement SendGrid webhook processing
        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle MailBaby webhooks
     */
    public function mailbabyWebhook(Request $request)
    {
        Log::info('Emailer: MailBaby webhook received', $request->all());

        // TODO: Implement MailBaby webhook processing
        return response()->json(['status' => 'ok']);
    }

    /**
     * Find message delivery by tracking token
     */
    protected function findDeliveryByToken($token): ?MessageDelivery
    {
        // Find delivery where the generated token matches
        $deliveries = MessageDelivery::all();

        foreach ($deliveries as $delivery) {
            if ($delivery->getTrackingToken() === $token) {
                return $delivery;
            }
        }

        return null;
    }

    /**
     * Process webhook event from email provider
     */
    protected function processWebhookEvent(MessageDelivery $delivery, string $event, array $data): void
    {
        switch ($event) {
            case 'delivered':
                $delivery->markAsDelivered();
                break;

            case 'opened':
                $delivery->markAsOpened();
                break;

            case 'clicked':
                $delivery->markAsClicked();
                break;

            case 'failed':
            case 'rejected':
                $delivery->markAsError();
                break;
        }

        // Store provider data
        $delivery->update([
            'provider_data' => array_merge($delivery->provider_data ?? [], $data),
        ]);

        Log::info('Emailer: Webhook event processed', [
            'delivery_id' => $delivery->id,
            'event' => $event,
        ]);
    }
}
