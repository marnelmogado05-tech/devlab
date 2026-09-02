<?php

namespace App\Console\Commands;

use App\Actions\Attempts\ExpireStaleAttempts;
use Illuminate\Console\Command;

class ExpireAttemptsCommand extends Command
{
    protected $signature = 'devlab:expire-attempts';

    protected $description = 'Close challenge attempts left open past the configured window';

    public function handle(ExpireStaleAttempts $expire): int
    {
        $count = $expire->handle();

        $this->info("Expired {$count} attempt(s).");

        return self::SUCCESS;
    }
}
