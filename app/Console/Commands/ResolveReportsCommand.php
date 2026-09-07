<?php

namespace App\Console\Commands;

use App\Models\ChallengeReport;

/**
 * The report was right, and the content was changed because of it.
 */
class ResolveReportsCommand extends CloseReportsCommand
{
    protected $signature = 'devlab:reports:resolve
                            {id* : One or more report ids, from devlab:reports}
                            {--note= : What was done about it}';

    protected $description = 'Mark reports resolved — the content was changed';

    protected function status(): string
    {
        return ChallengeReport::STATUS_RESOLVED;
    }
}
