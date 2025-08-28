<?php

namespace idoneo\Emailer\Traits;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

trait ConfiguresTeamMail
{
    /**
     * Configure mail settings for a specific team.
     * If team has custom SMTP, use it. Otherwise, use system SMTP.
     */
    protected function configureMailForTeam($team)
    {
        $emailProvider = config('emailer.email_provider', 'smtp');

        Log::info('🔧 Emailer ConfiguresTeamMail: Starting SMTP configuration', [
            'team_id' => $team->id,
            'team_name' => $team->name ?? 'Unknown',
            'email_provider' => $emailProvider,
            'has_custom_smtp' => method_exists($team, 'hasOutgoingEmailConfig') ? $team->hasOutgoingEmailConfig() : false,
        ]);

        // Priority: Always check team email settings first (from_name, from_address)
        $teamEmailConfig = method_exists($team, 'getOutgoingEmailConfig') ? $team->getOutgoingEmailConfig() : [];

        // Set team's from_name and from_address if available
        if (!empty($teamEmailConfig['from_name'])) {
            Config::set('mail.from.name', $teamEmailConfig['from_name']);
        }
        if (!empty($teamEmailConfig['from_address'])) {
            Config::set('mail.from.address', $teamEmailConfig['from_address']);
        }

        Log::info('🔧 Emailer ConfiguresTeamMail: Applied team email config', [
            'team_id' => $team->id,
            'team_has_from_name' => !empty($teamEmailConfig['from_name']),
            'team_has_from_address' => !empty($teamEmailConfig['from_address']),
            'final_from_name' => config('mail.from.name'),
            'final_from_address' => config('mail.from.address'),
        ]);

        // If EMAIL_PROVIDER is mailgun, only transport changes, but keep team email config
        if ($emailProvider === 'mailgun') {
            Log::info('🚀 Emailer: Using Mailgun API with team email config', [
                'team_id' => $team->id,
                'mailgun_domain' => config('services.mailgun.domain'),
                'mailgun_configured' => !empty(config('services.mailgun.secret')),
                'from_name' => config('mail.from.name'),
                'from_address' => config('mail.from.address'),
            ]);

            return; // Use Mailgun transport but with team email config applied
        }

        // Check if team has its own email configuration (only for SMTP provider)
        if (method_exists($team, 'hasOutgoingEmailConfig') && $team->hasOutgoingEmailConfig()) {
            // Use team's custom SMTP configuration
            $config = $team->getOutgoingEmailConfig();

            Log::info('✅ Emailer: Using TEAM custom SMTP configuration', [
                'team_id' => $team->id,
                'smtp_host' => $config['host'] ?? null,
                'smtp_port' => $config['port'] ?? null,
                'smtp_username' => $config['username'] ?? null,
                'smtp_encryption' => $config['encryption'] ?? null,
                'from_address' => $config['from_address'] ?? null,
                'from_name' => $config['from_name'] ?? null,
                'password_configured' => !empty($config['password']),
            ]);

            if (isset($config['host'])) Config::set('mail.mailers.smtp.host', $config['host']);
            if (isset($config['port'])) Config::set('mail.mailers.smtp.port', $config['port']);
            if (isset($config['username'])) Config::set('mail.mailers.smtp.username', $config['username']);
            if (isset($config['password'])) Config::set('mail.mailers.smtp.password', $config['password']);
            if (isset($config['encryption'])) Config::set('mail.mailers.smtp.encryption', $config['encryption']);
            if (isset($config['from_address'])) Config::set('mail.from.address', $config['from_address']);
            if (isset($config['from_name'])) Config::set('mail.from.name', $config['from_name']);

            // No advertising footer for teams with custom SMTP
            Config::set('emailer.mail_advertising_footer', '');

            Log::info('✅ Emailer: Team SMTP configuration applied successfully', [
                'team_id' => $team->id,
                'final_host' => config('mail.mailers.smtp.host'),
                'final_port' => config('mail.mailers.smtp.port'),
                'final_username' => config('mail.mailers.smtp.username'),
                'final_from_address' => config('mail.from.address'),
            ]);
        } else {
            // Use system SMTP configuration
            Log::info('📧 Emailer: Using SYSTEM SMTP configuration', [
                'team_id' => $team->id,
                'system_host' => config('mail.mailers.smtp.host'),
                'system_username' => config('mail.mailers.smtp.username'),
                'system_from_address' => config('mail.from.address'),
            ]);

            // Set advertising footer if configured
            $advertisingFooter = config('emailer.advertising_footer', '');
            Config::set('emailer.mail_advertising_footer', $advertisingFooter);

            Log::info('✅ Emailer: System SMTP configuration confirmed', [
                'team_id' => $team->id,
                'final_host' => config('mail.mailers.smtp.host'),
                'final_username' => config('mail.mailers.smtp.username'),
                'final_from_address' => config('mail.from.address'),
                'advertising_footer_length' => strlen($advertisingFooter),
            ]);
        }
    }

    /**
     * Get the appropriate "from" address for a team.
     * Uses team setting if available, otherwise system default.
     */
    protected function getFromAddressForTeam($team)
    {
        if (method_exists($team, 'hasOutgoingEmailConfig') && $team->hasOutgoingEmailConfig()) {
            $config = $team->getOutgoingEmailConfig();
            return $config['from_address'] ?? config('mail.from.address');
        }

        return config('mail.from.address');
    }

    /**
     * Get the appropriate "from" name for a team.
     * Uses team setting if available, otherwise system default.
     */
    protected function getFromNameForTeam($team)
    {
        if (method_exists($team, 'hasOutgoingEmailConfig') && $team->hasOutgoingEmailConfig()) {
            $config = $team->getOutgoingEmailConfig();
            return $config['from_name'] ?? config('mail.from.name');
        }

        return config('mail.from.name');
    }

    /**
     * Check if team should show advertising footer.
     */
    protected function shouldShowAdvertisingForTeam($team)
    {
        return method_exists($team, 'isUsingSystemSmtp') ? $team->isUsingSystemSmtp() : true;
    }
}
