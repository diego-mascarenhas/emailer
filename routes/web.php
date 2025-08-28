<?php

use idoneo\Emailer\Http\Controllers\EmailerTrackingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Emailer Package Routes
|--------------------------------------------------------------------------
|
| These routes handle email tracking functionality like open tracking,
| click tracking, and other email analytics features.
|
*/

// Email tracking routes
if (config('emailer.tracking.enabled', true)) {

    // Open tracking (pixel)
    Route::get('/track/{token}', [EmailerTrackingController::class, 'track'])
        ->name('emailer.track')
        ->middleware(config('emailer.routes.tracking_middleware', []));

    // Click tracking
    Route::get('/track-click/{token}', [EmailerTrackingController::class, 'trackClick'])
        ->name('emailer.track.click')
        ->middleware(config('emailer.routes.tracking_middleware', []));

    // Unsubscribe link
    Route::get('/unsubscribe/{token}', [EmailerTrackingController::class, 'unsubscribe'])
        ->name('emailer.unsubscribe');
}

// Webhooks for email providers (if enabled)
if (config('emailer.features.webhooks', true)) {

    // Mailgun webhooks
    Route::post('/webhook/mailgun', [EmailerTrackingController::class, 'mailgunWebhook'])
        ->name('emailer.webhook.mailgun')
        ->middleware(['api']);

    // SendGrid webhooks
    Route::post('/webhook/sendgrid', [EmailerTrackingController::class, 'sendgridWebhook'])
        ->name('emailer.webhook.sendgrid')
        ->middleware(['api']);

    // MailBaby webhooks
    Route::post('/webhook/mailbaby', [EmailerTrackingController::class, 'mailbabyWebhook'])
        ->name('emailer.webhook.mailbaby')
        ->middleware(['api']);
}
