<?php

namespace App\Console\Commands;

use App\Models\ChallengeReport;

/**
 * The report was not actionable — mistaken, duplicate of a known issue, or noise.
 */
class DismissReportsCommand extends CloseReportsCommand
{
    protected $signature = 'devlab:reports:dismiss
                            {id* : One or more report ids, from devlab:reports}
                            {--note= : Why it was not actionable}';

    protected $description = 'Mark reports dismissed — no change was needed';

    protected function status(): string
    {
        return ChallengeReport::STATUS_DISMISSED;
    }
}
