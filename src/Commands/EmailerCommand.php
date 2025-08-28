<?php

namespace idoneo\Emailer\Commands;

use Illuminate\Console\Command;

class EmailerCommand extends Command
{
    public $signature = 'emailer';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
