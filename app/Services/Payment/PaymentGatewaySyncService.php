<?php

namespace App\Services\Payment;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

class PaymentGatewaySyncService
{
    public function syncStripeFromEnvironment(): void
    {
        if (!config('payment.stripe.sync_from_env')) {
            return;
        }

        if (!isDBConnected() || !Schema::hasTable('settings')) {
            return;
        }

        $publicKey = config('payment.stripe.test_public_key');
        $secretKey = config('payment.stripe.test_secret_key');

        if (empty($publicKey) || empty($secretKey)) {
            return;
        }

        $setting = Setting::query()
            ->where('type', 'payment')
            ->where('sub_type', 'stripe')
            ->first();

        if (!$setting) {
            return;
        }

        $fields = $setting->fields;
        $fields['active'] = true;
        $fields['test_mode'] = config('payment.stripe.force_test_mode');
        $fields['test_public_key'] = $publicKey;
        $fields['test_secret_key'] = $secretKey;

        $setting->update(['fields' => $fields]);
    }
}
