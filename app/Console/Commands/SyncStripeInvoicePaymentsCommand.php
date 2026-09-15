<?php

namespace App\Console\Commands;

use App\Services\Payment\StripeCustomerService;
use App\Services\Payment\SubscriptionService;
use App\Support\StripeInvoiceIds;
use Illuminate\Console\Command;
use Modules\PaymentGateways\Models\PaymentHistory;
use Stripe\Invoice;

class SyncStripeInvoicePaymentsCommand extends Command
{
    protected $signature = 'ssu:sync-stripe-invoices {--hours=48 : How far back to look} {--dry-run : List missing invoices without writing rows}';

    protected $description = 'Record paid Stripe subscription invoices that are missing from the Online Payment Report';

    public function handle(StripeCustomerService $stripeCustomer, SubscriptionService $subscriptions): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $dryRun = (bool) $this->option('dry-run');

        $stripeCustomer->configureStripe();

        $created = 0;
        $skipped = 0;
        $startingAfter = null;

        do {
            $params = [
                'limit' => 100,
                'status' => 'paid',
                'created' => ['gte' => now()->subHours($hours)->getTimestamp()],
            ];

            if ($startingAfter) {
                $params['starting_after'] = $startingAfter;
            }

            $invoices = Invoice::all($params);

            foreach ($invoices->data as $invoice) {
                $amount = round(($invoice->amount_paid ?? 0) / 100, 2);
                $ids = StripeInvoiceIds::lookupIds($invoice);
                $subscriptionId = StripeInvoiceIds::subscriptionId($invoice);

                if ($amount <= 0 || $ids === [] || $subscriptionId === '') {
                    $skipped++;
                    continue;
                }

                if (PaymentHistory::query()->whereIn('transaction_id', $ids)->exists()) {
                    $skipped++;
                    continue;
                }

                $this->line(sprintf(
                    '%s %s %s $%s',
                    $dryRun ? '[dry-run]' : '[sync]',
                    $invoice->id,
                    $subscriptionId,
                    number_format($amount, 2),
                ));

                if (! $dryRun) {
                    $subscriptions->handleInvoicePaymentSucceeded($invoice);
                    $created++;
                }
            }

            $startingAfter = $invoices->has_more && count($invoices->data) > 0
                ? $invoices->data[count($invoices->data) - 1]->id
                : null;
        } while ($startingAfter);

        $this->info($dryRun
            ? "Dry run complete. {$skipped} invoices already recorded or skipped."
            : "Recorded {$created} missing invoice payments. Skipped {$skipped}.");

        return self::SUCCESS;
    }
}
