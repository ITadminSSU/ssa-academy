<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentRefundStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePaymentRefundRequest;
use App\Http\Requests\ProcessGatewayRefundRequest;
use App\Services\Payment\PaymentRefundService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\PaymentGateways\Models\PaymentHistory;

class PaymentRefundController extends Controller
{
    public function __construct(private PaymentRefundService $paymentRefundService) {}

    public function index(Request $request)
    {
        $payments = $this->paymentRefundService->getPayments($request->all());

        $payments->getCollection()->transform(function (PaymentHistory $payment) {
            $payment->setAttribute('can_gateway_refund', $this->paymentRefundService->canProcessGatewayRefund($payment));

            return $payment;
        });

        return Inertia::render('dashboard/payment-refunds/index', [
            'payments' => $payments,
            'statuses' => collect(PaymentRefundStatus::cases())->map(fn (PaymentRefundStatus $status) => [
                'value' => $status->value,
                'label' => $status->getLabel(),
            ])->values()->all(),
            'filters' => [
                'refund_status' => $request->query('refund_status'),
                'search' => $request->query('search'),
            ],
        ]);
    }

    public function userPayments(string $userId)
    {
        $detail = $this->paymentRefundService->getUserPayments($userId);

        return Inertia::render('dashboard/payment-refunds/user', [
            ...$detail,
            'statuses' => collect(PaymentRefundStatus::cases())->map(fn (PaymentRefundStatus $status) => [
                'value' => $status->value,
                'label' => $status->getLabel(),
            ])->values()->all(),
        ]);
    }

    public function update(UpdatePaymentRefundRequest $request, string $payment)
    {
        $this->paymentRefundService->updateRefundStatus($payment, $request->validated());

        return back()->with('success', 'Refund status updated successfully');
    }

    public function processGatewayRefund(ProcessGatewayRefundRequest $request, string $payment)
    {
        $result = $this->paymentRefundService->processAdminGatewayRefund($payment, $request->validated());

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message'],
        );
    }
}
