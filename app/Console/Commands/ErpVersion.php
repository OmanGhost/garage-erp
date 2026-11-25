<?php

namespace App\Console\Commands;

use App\Support\AppVersion;
use Illuminate\Console\Command;

class ErpVersion extends Command
{
    protected $signature = 'erp:version';

    protected $description = 'Show the application version and build';

    public function handle(): int
    {
        $this->line('Garage ERP '.AppVersion::label());

        return self::SUCCESS;
    }
}
