<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use App\Models\StudentPaymentTerm;

class Kernel extends Command
{
    protected $signature = 'payments:check-overdue';
    protected $description = 'Mark payment terms as overdue if past due date';

    public function handle(): int
    {
        $this->info('Checking for overdue payments...');

        $terms = StudentPaymentTerm::where('status', '!=', 'paid')
            ->where('due_date', '<', now())
            ->get();

        $updated = 0;

        foreach ($terms as $term) {
            if ($term->status !== 'overdue') {
                $term->status = 'overdue';
                $term->save();
                $updated++;
            }
        }

        $this->info("✓ Updated {$updated} payment terms to overdue status");

        return self::SUCCESS;
    }

    protected function schedule(Schedule $schedule): void
    {
        // Check for overdue payments daily at midnight
        $schedule->command('payments:check-overdue')->daily();
    }
}




