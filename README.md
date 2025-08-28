# Emailer - Professional Email Marketing Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/idoneo/emailer.svg?style=flat-square)](https://packagist.org/packages/idoneo/emailer)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/idoneo/emailer/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/idoneo/emailer/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/idoneo/emailer/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/idoneo/emailer/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/idoneo/emailer.svg?style=flat-square)](https://packagist.org/packages/idoneo/emailer)

Professional email marketing package for Laravel with maximum deliverability, advanced tracking, and multi-provider support.

## Features

✨ **Multi-Provider Support**: SMTP, Mailgun, SendGrid, MailBaby with automatic fallback
📊 **Advanced Analytics**: Open rates, click tracking, bounce tracking, and detailed statistics  
⚡ **Queue-Based Processing**: Scalable email delivery with configurable delays
🎯 **Team-Based Configuration**: Per-team email settings and branding
📈 **Real-time Tracking**: Pixel tracking for opens and click tracking for links
🔄 **Webhook Integration**: Automatic status updates from email providers
🎨 **Template Support**: Rich HTML templates with variable replacement
📱 **Responsive Design**: Mobile-optimized email templates
🛡️ **Spam Prevention**: Built-in delays and best practices for deliverability

## Installation

You can install the package via composer:

```bash
composer require idoneo/emailer
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="emailer-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="emailer-config"
```

This is the contents of the published config file:

```php
return [
    'email_provider' => env('EMAILER_PROVIDER', 'smtp'),
    'queue_name' => env('EMAILER_QUEUE', 'emailer'),
    'delays' => [
        'base_minutes' => env('EMAILER_DELAY_BASE_MINUTES', 5),
        'random_seconds' => env('EMAILER_DELAY_RANDOM_SECONDS', 120),
    ],
    // ... more configuration options
];
```

## Basic Usage

### Creating a Message Campaign

```php
use idoneo\Emailer\Models\Message;
use idoneo\Emailer\Models\MessageType;
use idoneo\Emailer\Facades\Emailer;

// Create a message type
$messageType = MessageType::create([
    'name' => 'Newsletter',
    'status' => 1
]);

// Create a message
$message = Message::create([
    'name' => 'Welcome Newsletter',
    'subject' => 'Welcome to our platform!',
    'content' => '<h1>Welcome {{name}}!</h1><p>Thank you for joining us.</p>',
    'type_id' => $messageType->id,
    'team_id' => auth()->user()->currentTeam->id,
    'status_id' => 1
]);

// Start the campaign
Emailer::startCampaign($message);
```

### Sending Test Emails

```php
// Send a test email
Emailer::sendTest($message, 'test@example.com', 'Test User');
```

### Getting Campaign Statistics

```php
$stats = Emailer::getCampaignStats($message);

echo "Total sent: " . $stats['sent'];
echo "Open rate: " . $stats['open_rate'] . "%";
echo "Click rate: " . $stats['click_rate'] . "%";
```

## Configuration

### Environment Variables

Add these variables to your `.env` file:

```bash
# Email Provider Configuration
EMAILER_PROVIDER=smtp                    # smtp|mailgun|sendgrid|mailbaby
EMAILER_FALLBACK_TO_SMTP=true           # Fallback to SMTP if provider fails

# Queue Configuration  
EMAILER_QUEUE=emailer                    # Queue name for email jobs

# Delay Configuration (Anti-spam)
EMAILER_DELAY_BASE_MINUTES=5            # Minutes between emails
EMAILER_DELAY_RANDOM_SECONDS=120        # Random seconds added

# Default Email Settings
EMAILER_FROM_ADDRESS=noreply@example.com
EMAILER_FROM_NAME="Your Company"
EMAILER_REPLY_TO_ADDRESS=support@example.com

# Tracking
EMAILER_TRACKING_ENABLED=true
EMAILER_OPEN_TRACKING=true
EMAILER_CLICK_TRACKING=true

# Provider-specific settings
MAILGUN_DOMAIN=mg.example.com
MAILGUN_SECRET=key-xxxxx

SENDGRID_API_KEY=SG.xxxxx

MAILBABY_API_KEY=xxxxx
MAILBABY_API_URL=https://api.mailbaby.net
```

### Team-Based Configuration

The package supports team-based email configuration. Each team can have its own SMTP settings:

```php
// In your Team model, implement these methods:
public function hasOutgoingEmailConfig(): bool
{
    return $this->getSetting('mail_host') !== null;
}

public function getOutgoingEmailConfig(): array
{
    return [
        'host' => $this->getSetting('mail_host'),
        'port' => $this->getSetting('mail_port', 587),
        'username' => $this->getSetting('mail_username'),
        'password' => $this->getSetting('mail_password'),
        'encryption' => $this->getSetting('mail_encryption', 'tls'),
        'from_address' => $this->getSetting('mail_from_address'),
        'from_name' => $this->getSetting('mail_from_name'),
    ];
}
```

## Commands

### Send Pending Messages

Process pending message deliveries:

```bash
# Send all pending messages
php artisan emailer:send-pending

# Limit the number of messages processed
php artisan emailer:send-pending --limit=50

# Send for specific team only
php artisan emailer:send-pending --team=123

# Dry run (show what would be sent)
php artisan emailer:send-pending --dry-run
```

## Email Tracking

The package includes comprehensive tracking features:

### Open Tracking
Automatically tracks when recipients open emails using invisible tracking pixels.

### Click Tracking  
Tracks clicks on links within emails by automatically replacing URLs with tracked versions.

### Webhook Support
Receives real-time updates from email providers:

- **Mailgun**: `/emailer/webhook/mailgun`
- **SendGrid**: `/emailer/webhook/sendgrid`  
- **MailBaby**: `/emailer/webhook/mailbaby`

## Advanced Usage

### Custom Email Providers

Extend the package to support additional email providers:

```php
// In your SendMessageCampaignJob extension
protected function sendViaCustomProvider()
{
    // Implement your custom provider logic
}
```

### Template Variables

The package supports template variables that are automatically replaced:

```php
$message = Message::create([
    'content' => '<h1>Hello {{name}}!</h1><p>Your email is {{email}}</p>',
    // ...
]);
```

Available variables:
- `{{name}}` - Contact name
- `{{email}}` - Contact email  
- Add custom variables by extending the `getHtmlForContact()` method

## Model Relationships

The package assumes certain relationships exist in your application:

```php
// Your Team model should have:
public function messages()
{
    return $this->hasMany(\idoneo\Emailer\Models\Message::class);
}

// Your Contact model should have:  
public function messageDeliveries()
{
    return $this->hasMany(\idoneo\Emailer\Models\MessageDelivery::class);
}

// Your Category model should have:
public function messages()
{
    return $this->hasMany(\idoneo\Emailer\Models\Message::class);
}
```

## Performance

### Queue Workers

Make sure you have queue workers running to process email jobs:

```bash
php artisan queue:work --queue=emailer
```

### Database Indexing

The package includes optimized database indexes for performance. For large volumes, consider:

- Partitioning delivery tables by date
- Archiving old delivery records
- Using read replicas for analytics queries

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [idoneo](https://github.com/idoneo)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
# Test webhook functionality
