<?php

namespace App\Services\Payment;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

class PaymentGatewaySyncService
{
    public function syncStripeFromEnvironment(): void
    {
        if (! config('payment.stripe.sync_from_env')) {
            return;
        }

        if (! isDBConnected() || ! Schema::hasTable('settings')) {
            return;
        }

        $setting = Setting::query()
            ->where('type', 'payment')
            ->where('sub_type', 'stripe')
            ->first();

        if (! $setting) {
            return;
        }

        $testMode = (bool) config('payment.stripe.force_test_mode');
        $testPublic = trim((string) config('payment.stripe.test_public_key', ''));
        $testSecret = trim((string) config('payment.stripe.test_secret_key', ''));
        $livePublic = trim((string) config('payment.stripe.live_public_key', ''));
        $liveSecret = trim((string) config('payment.stripe.live_secret_key', ''));

        // Require keys for the mode that will be active.
        if ($testMode && ($testPublic === '' || $testSecret === '')) {
            return;
        }

        if (! $testMode && ($livePublic === '' || $liveSecret === '')) {
            return;
        }

        $fields = is_array($setting->fields) ? $setting->fields : [];
        $fields['active'] = true;
        $fields['test_mode'] = $testMode;

        if ($testPublic !== '' && $testSecret !== '') {
            $fields['test_public_key'] = $testPublic;
            $fields['test_secret_key'] = $testSecret;
        }

        if ($livePublic !== '' && $liveSecret !== '') {
            $fields['live_public_key'] = $livePublic;
            $fields['live_secret_key'] = $liveSecret;
        }

        $setting->update(['fields' => $fields]);
    }
}
