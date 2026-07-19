<?php

namespace App\Services\Payment;

use App\Services\SettingsService;
use Illuminate\Support\Facades\Http;
use Modules\PaymentGateways\Models\PaymentHistory;
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use Stripe\Refund;
use Stripe\Stripe;

class GatewayRefundService
{
    public function __construct(private SettingsService $settingsService) {}

    public function processRefund(PaymentHistory $payment): GatewayRefundResult
    {
        if (empty($payment->transaction_id)) {
            return GatewayRefundResult::failed(
                $payment->payment_type,
                'No transaction ID stored for this payment.'
            );
        }

        return match ($payment->payment_type) {
            'stripe' => $this->refundStripe($payment),
            'paypal' => $this->refundPaypal($payment),
            default => GatewayRefundResult::failed(
                $payment->payment_type,
                'Gateway refunds are only supported for Stripe and PayPal payments.'
            ),
        };
    }

    private function refundStripe(PaymentHistory $payment): GatewayRefundResult
    {
        $settings = $this->settingsService->getSetting(['type' => 'payment', 'sub_type' => 'stripe']);
        $fields = $settings->fields ?? [];

        if (!($fields['active'] ?? false)) {
            return GatewayRefundResult::failed('stripe', 'Stripe is not enabled.');
        }

        $secretKey = ($fields['test_mode'] ?? true)
            ? ($fields['test_secret_key'] ?? '')
            : ($fields['live_secret_key'] ?? '');

        if (empty($secretKey)) {
            return GatewayRefundResult::failed('stripe', 'Stripe secret key is not configured.');
        }

        try {
            Stripe::setApiKey($secretKey);

            $refund = Refund::create([
                'payment_intent' => $payment->transaction_id,
                'reason' => 'requested_by_customer',
            ]);

            return new GatewayRefundResult(
                success: true,
                gateway: 'stripe',
                gatewayRefundId: $refund->id,
                response: $refund->toArray(),
            );
        } catch (\Throwable $th) {
            return GatewayRefundResult::failed('stripe', $th->getMessage());
        }
    }

    private function refundPaypal(PaymentHistory $payment): GatewayRefundResult
    {
        $settings = $this->settingsService->getSetting(['type' => 'payment', 'sub_type' => 'paypal']);
        $fields = $settings->fields ?? [];

        if (!($fields['active'] ?? false)) {
            return GatewayRefundResult::failed('paypal', 'PayPal is not enabled.');
        }

        $mode = ($fields['test_mode'] ?? true) ? 'sandbox' : 'live';
        $currency = strtoupper($fields['currency'] ?? 'USD');
        $baseUrl = $mode === 'sandbox'
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';

        try {
            $config = setPaypalConfig($fields, $mode);
            $provider = new PayPalClient();
            $provider->setApiCredentials($config);
            $tokenResponse = $provider->getAccessToken();
            $accessToken = $tokenResponse['access_token'] ?? null;

            if (!$accessToken) {
                return GatewayRefundResult::failed('paypal', 'Unable to obtain PayPal access token.');
            }

            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post("{$baseUrl}/v2/payments/captures/{$payment->transaction_id}/refund", [
                    'amount' => [
                        'value' => number_format((float) $payment->amount, 2, '.', ''),
                        'currency_code' => $currency,
                    ],
                    'note_to_payer' => 'Refund processed after candidate hire.',
                ]);

            $body = $response->json();

            if ($response->successful() && in_array($body['status'] ?? '', ['COMPLETED', 'PENDING'], true)) {
                return new GatewayRefundResult(
                    success: true,
                    gateway: 'paypal',
                    gatewayRefundId: $body['id'] ?? null,
                    response: $body,
                );
            }

            $message = $body['message']
                ?? $body['details'][0]['description']
                ?? $body['details'][0]['issue']
                ?? 'PayPal refund request failed.';

            return GatewayRefundResult::failed('paypal', $message, $body);
        } catch (\Throwable $th) {
            return GatewayRefundResult::failed('paypal', $th->getMessage());
        }
    }
}
