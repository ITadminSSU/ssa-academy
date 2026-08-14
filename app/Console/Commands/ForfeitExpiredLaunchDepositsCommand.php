<?php

namespace App\Console\Commands;

use App\Services\Payment\LaunchOfferEnrollmentService;
use Illuminate\Console\Command;

class ForfeitExpiredLaunchDepositsCommand extends Command
{
    protected $signature = 'ssu:forfeit-expired-launch-deposits';

    protected $description = 'Cancel reserved launch-offer seats whose balance deadline has passed (deposit kept).';

    public function handle(LaunchOfferEnrollmentService $launchOfferEnrollment): int
    {
        $count = $launchOfferEnrollment->forfeitExpiredReservations();

        $this->info("Forfeited {$count} expired pre-registration seat(s).");

        return self::SUCCESS;
    }
}
