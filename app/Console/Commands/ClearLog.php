<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use File;

class ClearLog extends Command
{
    protected $signature = 'logs:clear';
    protected $description = 'Clear the log files';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $logFiles = File::glob(storage_path('logs/*.log'));

        foreach ($logFiles as $file) {
            File::put($file, '');
        }

        $this->info('Log files have been cleared');
    }
}
