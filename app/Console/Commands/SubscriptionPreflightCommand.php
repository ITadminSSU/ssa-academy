<?php

namespace App\Console\Commands;

use App\Models\Course\Course;
use App\Models\Subscription;
use App\Services\Payment\StripeCustomerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class SubscriptionPreflightCommand extends Command
{
    protected $signature = 'subscriptions:preflight';

    protected $description = 'Verify subscription billing configuration before pilot rollout';

    public function handle(StripeCustomerService $stripeCustomer): int
    {
        $this->info('SSU Academy — Subscription Pilot Preflight');
        $this->newLine();

        $failed = false;

        $failed = $this->checkMigrations() || $failed;
        $failed = $this->checkEnvironment() || $failed;
        $failed = $this->checkStripe($stripeCustomer) || $failed;
        $failed = $this->checkRoutes() || $failed;
        $failed = $this->checkPilotCourses() || $failed;

        $this->newLine();

        if ($failed) {
            $this->error('Preflight failed. Fix the items above before pilot rollout.');

            return self::FAILURE;
        }

        $this->info('Preflight passed. Subscription billing is ready for pilot testing.');

        return self::SUCCESS;
    }

    private function checkMigrations(): bool
    {
        $this->line('Checking database…');

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $exception) {
            $this->error('  Database unavailable: ' . $exception->getMessage());

            return true;
        }

        $requiredTables = [
            'users',
            'courses',
            'subscriptions',
            'course_enrollments',
            'payment_histories',
            'stripe_webhook_events',
        ];

        $failed = false;

        foreach ($requiredTables as $table) {
            if (!Schema::hasTable($table)) {
                $this->error("  Missing table: {$table}");
                $failed = true;
            }
        }

        if (Schema::hasTable('courses')) {
            foreach (['billing_model', 'subscription_price', 'stripe_product_id', 'stripe_price_id'] as $column) {
                if (!Schema::hasColumn('courses', $column)) {
                    $this->error("  Missing courses.{$column} column — run subscription migrations");
                    $failed = true;
                }
            }
        }

        if (!$failed) {
            $this->info('  Database schema OK');
        }

        return $failed;
    }

    private function checkEnvironment(): bool
    {
        $this->line('Checking environment…');

        $failed = false;

        if (empty(config('payment.stripe.webhook_secret'))) {
            $this->error('  STRIPE_WEBHOOK_SECRET is not set');
            $failed = true;
        } else {
            $this->info('  STRIPE_WEBHOOK_SECRET is set');
        }

        $graceDays = config('payment.subscription.grace_days');
        $this->info("  Subscription grace days: {$graceDays}");

        if (empty(config('payment.subscription.portal_return_url'))) {
            $this->warn('  SUBSCRIPTION_PORTAL_RETURN_URL is not set (will default to learner subscriptions tab)');
        } else {
            $this->info('  SUBSCRIPTION_PORTAL_RETURN_URL is set');
        }

        return $failed;
    }

    private function checkStripe(StripeCustomerService $stripeCustomer): bool
    {
        $this->line('Checking Stripe gateway…');

        if (!$stripeCustomer->isStripeActive()) {
            $this->error('  Stripe is not active in payment settings');

            return true;
        }

        try {
            $stripeCustomer->resolveSecretKey();
            $this->info('  Stripe secret key resolved');
        } catch (\Throwable $exception) {
            $this->error('  Stripe secret key error: ' . $exception->getMessage());

            return true;
        }

        return false;
    }

    private function checkRoutes(): bool
    {
        $this->line('Checking routes…');

        $required = [
            'payments.stripe.webhook',
            'payments.stripe.payment',
            'subscriptions.portal',
            'courses.stripe.sync',
        ];

        $failed = false;

        foreach ($required as $name) {
            if (!Route::has($name)) {
                $this->error("  Missing route: {$name}");
                $failed = true;
            }
        }

        if (!$failed) {
            $this->info('  Subscription routes OK');
        }

        return $failed;
    }

    private function checkPilotCourses(): bool
    {
        if (!Schema::hasTable('courses')) {
            return false;
        }

        $this->line('Checking pilot courses…');

        $subscriptionCourses = Course::query()
            ->where('billing_model', 'subscription')
            ->where('pricing_type', 'paid')
            ->get(['id', 'title', 'subscription_price', 'stripe_price_id', 'status']);

        if ($subscriptionCourses->isEmpty()) {
            $this->warn('  No subscription courses configured yet');
            $this->warn('  Configure one pilot course: Pricing → Paid → Monthly subscription → Sync to Stripe');

            return false;
        }

        $failed = false;

        foreach ($subscriptionCourses as $course) {
            $issues = [];

            if (!$course->subscription_price) {
                $issues[] = 'missing subscription_price';
            }

            if (empty($course->stripe_price_id)) {
                $issues[] = 'not synced to Stripe';
            }

            if ($issues) {
                $this->warn("  [{$course->id}] {$course->title}: " . implode(', ', $issues));
                $failed = true;
            } else {
                $this->info("  [{$course->id}] {$course->title}: ready");
            }
        }

        $activeSubscriptions = Schema::hasTable('subscriptions')
            ? Subscription::query()->whereIn('status', ['active', 'trialing', 'past_due'])->count()
            : 0;

        $this->info("  Active subscription records: {$activeSubscriptions}");

        return $failed;
    }
}
