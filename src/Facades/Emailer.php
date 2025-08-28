<?php

namespace idoneo\Emailer\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string version()
 * @method static bool startCampaign(\idoneo\Emailer\Models\Message $message)
 * @method static bool stopCampaign(\idoneo\Emailer\Models\Message $message)
 * @method static bool sendTest(\idoneo\Emailer\Models\Message $message, string $email, string $name = null)
 * @method static array getCampaignStats(\idoneo\Emailer\Models\Message $message)
 *
 * @see \idoneo\Emailer\Emailer
 */
class Emailer extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'emailer';
    }
}
