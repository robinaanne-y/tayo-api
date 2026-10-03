<?php

namespace App\Console\Commands;

use App\Support\RecurringTaskOccurrenceGenerator;
use Illuminate\Console\Command;

class GenerateRecurringTaskOccurrences extends Command
{
    protected $signature = 'tasks:generate-occurrences';

    protected $description = 'Generate the next due occurrence for every recurring task whose rule is due soon';

    public function handle(RecurringTaskOccurrenceGenerator $generator): int
    {
        $created = $generator->generate();

        $this->info("Generated {$created} task occurrence(s).");

        return self::SUCCESS;
    }
}
