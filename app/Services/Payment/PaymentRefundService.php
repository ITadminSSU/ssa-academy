<?php

namespace App\Services\Payment;

use App\Enums\CandidateStatus;
use App\Enums\LearnerUserType;
use App\Enums\PaymentRefundStatus;
use App\Models\PaymentGatewayRefundAttempt;
use App\Models\PaymentRefundAuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\PaymentGateways\Models\PaymentHistory;

class PaymentRefundService
{
    public function __construct(private GatewayRefundService $gatewayRefundService) {}

    public function getRefundablePaymentsForUser(int|string $userId): Collection
    {
        return PaymentHistory::with(['purchase'])
            ->where('user_id', $userId)
            ->whereIn('payment_type', ['stripe', 'paypal'])
            ->whereIn('refund_status', [
                PaymentRefundStatus::PAID->value,
                PaymentRefundStatus::REFUND_PENDING->value,
            ])
            ->whereNotNull('transaction_id')
            ->orderByDesc('created_at')
            ->get();
    }

    public function getGatewayRefundAttemptsForUser(int|string $userId): Collection
    {
        return PaymentGatewayRefundAttempt::with(['paymentHistory.purchase', 'initiatedBy:id,name,email'])
            ->whereHas('paymentHistory', fn ($query) => $query->where('user_id', $userId))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    public function processAdminGatewayRefund(int|string $paymentId, array $data = []): array
    {
        $payment = PaymentHistory::with(['user', 'purchase'])->findOrFail($paymentId);

        if ($payment->refund_status === PaymentRefundStatus::REFUNDED) {
            throw new \RuntimeException('This payment has already been refunded.');
        }

        if (!in_array($payment->payment_type, ['stripe', 'paypal'], true)) {
            throw new \RuntimeException('Only Stripe and PayPal support automatic refunds. Use Manage for bank/wire transfers.');
        }

        if (empty($payment->transaction_id)) {
            throw new \RuntimeException('Payment is missing a transaction ID required for gateway refund.');
        }

        $result = $this->gatewayRefundService->processRefund($payment);

        $attempt = PaymentGatewayRefundAttempt::create([
            'payment_history_id' => $payment->id,
            'initiated_by_user_id' => Auth::id(),
            'gateway' => $result->gateway,
            'transaction_id' => $payment->transaction_id,
            'success' => $result->success,
            'gateway_refund_id' => $result->gatewayRefundId,
            'response_payload' => $result->response,
            'error_message' => $result->errorMessage,
            'created_at' => now(),
        ]);

        if ($result->success) {
            $note = trim(($data['refund_notes'] ?? '') . "\n" . sprintf(
                'Admin 1-click refund via %s. Refund ID: %s',
                ucfirst($result->gateway),
                $result->gatewayRefundId ?? 'n/a'
            ));

            $this->updateRefundStatus($payment->id, [
                'refund_status' => PaymentRefundStatus::REFUNDED->value,
                'refund_notes' => $note,
            ]);

            return [
                'success' => true,
                'message' => 'Refund processed successfully via ' . ucfirst($result->gateway) . '.',
                'attempt' => $attempt,
            ];
        }

        $failureNote = trim(($data['refund_notes'] ?? $payment->refund_notes ?? '') . "\n" . sprintf(
            'Admin gateway refund via %s failed: %s',
            ucfirst($result->gateway),
            $result->errorMessage ?? 'Unknown error'
        ));

        $this->recordGatewayFailureAudit($payment, $failureNote);

        return [
            'success' => false,
            'message' => $result->errorMessage ?? 'Refund failed.',
            'attempt' => $attempt,
        ];
    }

    public function canProcessGatewayRefund(PaymentHistory $payment): bool
    {
        return in_array($payment->payment_type, ['stripe', 'paypal'], true)
            && !empty($payment->transaction_id)
            && $payment->refund_status !== PaymentRefundStatus::REFUNDED;
    }

    public function processGatewayRefund(int|string $candidateId, int|string $paymentId, array $data = []): array
    {
        $candidate = User::query()
            ->where('id', $candidateId)
            ->where('role', 'student')
            ->where('user_type', LearnerUserType::EXTERNAL)
            ->firstOrFail();

        if ($candidate->candidate_status !== CandidateStatus::HIRED) {
            throw new \RuntimeException('Gateway refunds can only be processed for hired candidates.');
        }

        $payment = PaymentHistory::with('purchase')
            ->where('id', $paymentId)
            ->where('user_id', $candidate->id)
            ->firstOrFail();

        if ($payment->refund_status === PaymentRefundStatus::REFUNDED) {
            throw new \RuntimeException('This payment has already been refunded.');
        }

        if (!in_array($payment->payment_type, ['stripe', 'paypal'], true)) {
            throw new \RuntimeException('Only Stripe and PayPal payments support automatic refunds.');
        }

        $result = $this->gatewayRefundService->processRefund($payment);

        $attempt = PaymentGatewayRefundAttempt::create([
            'payment_history_id' => $payment->id,
            'initiated_by_user_id' => Auth::id(),
            'gateway' => $result->gateway,
            'transaction_id' => $payment->transaction_id,
            'success' => $result->success,
            'gateway_refund_id' => $result->gatewayRefundId,
            'response_payload' => $result->response,
            'error_message' => $result->errorMessage,
            'created_at' => now(),
        ]);

        if ($result->success) {
            $note = trim(($data['refund_notes'] ?? '') . "\n" . sprintf(
                'Gateway refund via %s successful. Refund ID: %s',
                ucfirst($result->gateway),
                $result->gatewayRefundId ?? 'n/a'
            ));

            $this->updateRefundStatus($payment->id, [
                'refund_status' => PaymentRefundStatus::REFUNDED->value,
                'refund_notes' => $note,
            ]);

            return [
                'success' => true,
                'message' => 'Refund processed successfully via ' . ucfirst($result->gateway) . '.',
                'attempt' => $attempt,
            ];
        }

        $failureNote = trim(($data['refund_notes'] ?? $payment->refund_notes ?? '') . "\n" . sprintf(
            'Gateway refund via %s failed: %s',
            ucfirst($result->gateway),
            $result->errorMessage ?? 'Unknown error'
        ));

        $this->recordGatewayFailureAudit($payment, $failureNote);

        return [
            'success' => false,
            'message' => $result->errorMessage ?? 'Refund failed.',
            'attempt' => $attempt,
        ];
    }

    public function processAllGatewayRefunds(int|string $candidateId, array $data = []): array
    {
        User::query()
            ->where('id', $candidateId)
            ->where('role', 'student')
            ->where('user_type', LearnerUserType::EXTERNAL)
            ->where('candidate_status', CandidateStatus::HIRED)
            ->firstOrFail();

        $payments = $this->getRefundablePaymentsForUser($candidateId);
        $results = [];

        foreach ($payments as $payment) {
            $results[] = [
                'payment_id' => $payment->id,
                ...$this->processGatewayRefund($candidateId, $payment->id, $data),
            ];
        }

        $successCount = collect($results)->where('success', true)->count();
        $failureCount = collect($results)->where('success', false)->count();

        return [
            'results' => $results,
            'summary' => [
                'total' => count($results),
                'succeeded' => $successCount,
                'failed' => $failureCount,
            ],
            'message' => count($results) === 0
                ? 'No refundable payments found.'
                : "Processed {$successCount} refund(s), {$failureCount} failed.",
        ];
    }

    private function recordGatewayFailureAudit(PaymentHistory $payment, string $note): void
    {
        $previousStatus = $payment->refund_status?->value ?? $payment->refund_status;
        $previousNotes = $payment->refund_notes;

        $payment->update(['refund_notes' => $note]);

        PaymentRefundAuditLog::create([
            'payment_history_id' => $payment->id,
            'changed_by_user_id' => Auth::id(),
            'previous_status' => $previousStatus,
            'new_status' => $previousStatus,
            'previous_notes' => $previousNotes,
            'new_notes' => $note,
            'created_at' => now(),
        ]);
    }

    public function getPayments(array $params = [], bool $paginate = true): LengthAwarePaginator|Collection
    {
        $page = (int) ($params['per_page'] ?? 15);

        $query = PaymentHistory::with(['user', 'purchase'])
            ->when(!empty($params['search']), function ($query) use ($params) {
                $query->where(function ($q) use ($params) {
                    $q->where('transaction_id', 'LIKE', '%' . $params['search'] . '%')
                        ->orWhere('invoice', 'LIKE', '%' . $params['search'] . '%')
                        ->orWhereHas('user', function ($user) use ($params) {
                            $user->where('name', 'LIKE', '%' . $params['search'] . '%')
                                ->orWhere('email', 'LIKE', '%' . $params['search'] . '%');
                        });
                });
            })
            ->when(!empty($params['refund_status']), function ($query) use ($params) {
                $query->where('refund_status', $params['refund_status']);
            })
            ->when(!empty($params['user_id']), function ($query) use ($params) {
                $query->where('user_id', $params['user_id']);
            })
            ->orderByDesc('created_at');

        if (!$paginate) {
            return $query->get();
        }

        return $query->paginate($page);
    }

    public function getUserPayments(int|string $userId): array
    {
        $user = User::findOrFail($userId);

        $payments = PaymentHistory::with(['purchase'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (PaymentHistory $payment) {
                $payment->audit_logs = $this->getAuditLogs($payment->id);

                return $payment;
            });

        return [
            'user' => $user,
            'payments' => $payments,
            'summary' => [
                'total_payments' => $payments->count(),
                'total_amount' => round($payments->sum('amount'), 2),
                'refund_pending_count' => $payments->where('refund_status', PaymentRefundStatus::REFUND_PENDING->value)->count(),
                'refunded_count' => $payments->where('refund_status', PaymentRefundStatus::REFUNDED->value)->count(),
            ],
        ];
    }

    public function getPaymentWithAudit(int|string $paymentId): PaymentHistory
    {
        $payment = PaymentHistory::with(['user', 'purchase'])->findOrFail($paymentId);
        $payment->setRelation('audit_logs', $this->getAuditLogs($payment->id));

        return $payment;
    }

    public function getAuditLogs(int|string $paymentId): Collection
    {
        return PaymentRefundAuditLog::with('changedBy:id,name,email')
            ->where('payment_history_id', $paymentId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function updateRefundStatus(int|string $paymentId, array $data): PaymentHistory
    {
        return DB::transaction(function () use ($paymentId, $data) {
            $payment = PaymentHistory::findOrFail($paymentId);
            $previousStatus = $payment->refund_status;
            $previousNotes = $payment->refund_notes;
            $newStatus = $data['refund_status'];
            $newNotes = $data['refund_notes'] ?? null;

            if ($previousStatus === $newStatus && $previousNotes === $newNotes) {
                return $payment;
            }

            $payment->update([
                'refund_status' => $newStatus,
                'refund_notes' => $newNotes,
            ]);

            PaymentRefundAuditLog::create([
                'payment_history_id' => $payment->id,
                'changed_by_user_id' => Auth::id(),
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'previous_notes' => $previousNotes,
                'new_notes' => $newNotes,
                'created_at' => now(),
            ]);

            return $payment->fresh(['user', 'purchase']);
        }, 5);
    }
}
