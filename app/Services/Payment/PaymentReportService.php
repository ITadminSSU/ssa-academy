<?php

namespace App\Services\Payment;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Modules\PaymentGateways\Models\PaymentHistory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaymentReportService
{
   /**
    * Get payment reports with filters
    */
   public function getPayments(array $params, bool $paginate = true): LengthAwarePaginator|Collection
   {
      $page = array_key_exists('per_page', $params) ? intval($params['per_page']) : 10;

      $query = PaymentHistory::with(['user', 'purchase'])
         ->when(array_key_exists('search', $params) && $params['search'], function ($query) use ($params) {
            return $query->where(function ($q) use ($params) {
               $q->where('transaction_id', 'LIKE', '%' . $params['search'] . '%')
                  ->orWhere('amount', 'LIKE', '%' . $params['search'] . '%')
                  ->orWhereHas('user', function ($user) use ($params) {
                     $user->where('name', 'LIKE', '%' . $params['search'] . '%')
                        ->orWhere('email', 'LIKE', '%' . $params['search'] . '%');
                  });
            });
         })
         ->when(array_key_exists('payment_type', $params), function ($query) use ($params) {
            return $query->where('payment_type', $params['payment_type']);
         })
         ->when(array_key_exists('status', $params), function ($query) use ($params) {
            if ($params['status'] === 'pending') {
               return $query->whereJsonContains('meta->status', 'pending');
            } elseif ($params['status'] === 'verified') {
               return $query->whereJsonContains('meta->status', 'verified');
            }
            return $query;
         })
         ->when(array_key_exists('user_id', $params), function ($query) use ($params) {
            return $query->where('user_id', $params['user_id']);
         })
         ->tap(fn ($query) => $this->applyDateFilters($query, $params))
         ->orderBy('created_at', 'desc');

      if (!$paginate) {
         return $query->get();
      }

      return $query->paginate($page);
   }

   /**
    * Get online payments (all payment types except offline)
    */
   public function getOnlinePayments(array $params, bool $paginate = true): LengthAwarePaginator|Collection
   {
      $params['payment_type'] = ['stripe', 'paypal', 'razorpay', 'mollie', 'paystack', 'sslcommerz'];

      $page = array_key_exists('per_page', $params) ? intval($params['per_page']) : 10;

      $query = PaymentHistory::with(['user', 'purchase'])
         ->whereIn('payment_type', $params['payment_type'])
         ->when(array_key_exists('search', $params) && $params['search'], function ($query) use ($params) {
            return $query->where(function ($q) use ($params) {
               $q->where('transaction_id', 'LIKE', '%' . $params['search'] . '%')
                  ->orWhere('amount', 'LIKE', '%' . $params['search'] . '%')
                  ->orWhereHas('user', function ($user) use ($params) {
                     $user->where('name', 'LIKE', '%' . $params['search'] . '%')
                        ->orWhere('email', 'LIKE', '%' . $params['search'] . '%');
                  });
            });
         })
         ->tap(fn ($query) => $this->applyDateFilters($query, $params))
         ->orderBy('created_at', 'desc');

      if (!$paginate) {
         return $query->get();
      }

      return $query->paginate($page);
   }

   /**
    * Get offline payments
    */
   public function getOfflinePayments(array $params, bool $paginate = true): LengthAwarePaginator|Collection
   {
      $page = array_key_exists('per_page', $params) ? intval($params['per_page']) : 10;

      $query = PaymentHistory::with(['user', 'purchase', 'media'])
         ->where('payment_type', 'offline')
         ->when(array_key_exists('search', $params) && $params['search'], function ($query) use ($params) {
            return $query->where(function ($q) use ($params) {
               $q->where('transaction_id', 'LIKE', '%' . $params['search'] . '%')
                  ->orWhere('amount', 'LIKE', '%' . $params['search'] . '%')
                  ->orWhereHas('user', function ($user) use ($params) {
                     $user->where('name', 'LIKE', '%' . $params['search'] . '%')
                        ->orWhere('email', 'LIKE', '%' . $params['search'] . '%');
                  });
            });
         })
         ->when(array_key_exists('status', $params), function ($query) use ($params) {
            if ($params['status'] === 'pending') {
               return $query->whereJsonContains('meta->status', 'pending');
            } elseif ($params['status'] === 'verified') {
               return $query->whereJsonContains('meta->status', 'verified');
            }
            return $query;
         })
         ->tap(fn ($query) => $this->applyDateFilters($query, $params))
         ->orderBy('created_at', 'desc');

      if (!$paginate) {
         return $query->get();
      }

      return $query->paginate($page);
   }

   /**
    * Verify offline payment and enroll user
    */
   public function verifyOfflinePayment(int $paymentId, array $data): bool
   {
      $user = Auth::user();
      $payment = PaymentHistory::findOrFail($paymentId);

      // Update payment status
      $meta = $payment->meta ?? [];
      $meta['status'] = 'verified';
      $meta['verified_at'] = now()->toDateTimeString();
      $meta['verified_by'] = $user->id;
      $meta['admin_notes'] = $data['admin_notes'] ?? null;

      $payment->update(['meta' => $meta]);

      return true;
   }

   /**
    * Reject offline payment
    */
   public function rejectOfflinePayment(int $paymentId, ?string $reason): bool
   {
      $user = Auth::user();
      $payment = PaymentHistory::findOrFail($paymentId);

      $meta = $payment->meta ?? [];
      $meta['status'] = 'rejected';
      $meta['rejected_at'] = now()->toDateTimeString();
      $meta['rejected_by'] = $user->id;
      $meta['admin_notes'] = $reason;

      $payment->update(['meta' => $meta]);

      return true;
   }

   private function applyDateFilters($query, array $params)
   {
      $period = $params['period'] ?? null;

      if ($period === '24h') {
         return $query->where('created_at', '>=', now()->subHours(24));
      }

      if ($period === '7d') {
         return $query->where('created_at', '>=', now()->subDays(7));
      }

      if ($period === '30d') {
         return $query->where('created_at', '>=', now()->subDays(30));
      }

      $date = $params['date'] ?? null;
      if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
         return $query->whereDate('created_at', $date);
      }

      if (! empty($params['date_from'])) {
         $query->whereDate('created_at', '>=', $params['date_from']);
      }

      if (! empty($params['date_to'])) {
         $query->whereDate('created_at', '<=', $params['date_to']);
      }

      return $query;
   }
}

