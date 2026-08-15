<?php

namespace App\Console\Commands;

use App\Services\Payment\LaunchOfferReminderService;
use Illuminate\Console\Command;

class SendLaunchBalanceRemindersCommand extends Command
{
    protected $signature = 'ssu:send-launch-balance-reminders';

    protected $description = 'Send launch-day, mid-grace, and final-day balance payment reminders for reserved seats.';

    public function handle(LaunchOfferReminderService $reminders): int
    {
        $stats = $reminders->sendDueReminders();

        $this->info(sprintf(
            'Launch notices: %d · Mid reminders: %d · Final reminders: %d',
            $stats['launch'],
            $stats['mid'],
            $stats['final'],
        ));

        return self::SUCCESS;
    }
}
