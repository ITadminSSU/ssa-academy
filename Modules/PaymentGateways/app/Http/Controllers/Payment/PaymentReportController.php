<?php

namespace Modules\PaymentGateways\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Modules\PaymentGateways\Models\PaymentHistory;
use App\Services\Course\CourseEnrollmentService;
use App\Services\Payment\PaymentReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Exam\Services\ExamEnrollmentService;

class PaymentReportController extends Controller
{
   public function __construct(
      private PaymentReportService $paymentReportService,
      private CourseEnrollmentService $courseEnrollment,
      private ExamEnrollmentService $examEnrollment,
   ) {}

   /**
    * Display online payments
    */
   public function online_index(Request $request)
   {
      $payments = $this->paymentReportService->getOnlinePayments($request->all(), true);

      return Inertia::render('dashboard/payment-reports/online', compact('payments'));
   }

   /**
    * Display offline payments
    */
   public function offline_index(Request $request)
   {
      $payments = $this->paymentReportService->getOfflinePayments($request->all(), true);

      return Inertia::render('dashboard/payment-reports/offline', compact('payments'));
   }

   /**
    * Verify offline payment and enroll user
    */
   public function verify(Request $request, int $id)
   {
      $request->validate([
         'admin_notes' => 'nullable|string|max:500',
      ]);

      $payment = PaymentHistory::findOrFail($id);

      // Verify the payment
      $this->paymentReportService->verifyOfflinePayment($id, $request->all());

      // Enroll user based on purchase type
      if ($payment->purchase_type === 'App\Models\Course\Course') {
         $this->courseEnrollment->createCourseEnroll([
            'user_id' => $payment->user_id,
            'course_id' => $payment->purchase_id,
            'enrollment_type' => 'paid',
         ]);
      } elseif ($payment->purchase_type === 'Modules\Exam\Models\Exam') {
         $this->examEnrollment->createExamEnroll([
            'user_id' => $payment->user_id,
            'exam_id' => $payment->purchase_id,
            'enrollment_type' => 'paid',
         ]);
      }

      return redirect()->back()->with('success', 'Payment verified and user enrolled successfully.');
   }

   /**
    * Reject offline payment
    */
   public function reject(Request $request, int $id)
   {
      $request->validate([
         'admin_notes' => 'nullable|string|max:500',
      ]);

      $this->paymentReportService->rejectOfflinePayment($id, $request->admin_notes);

      return redirect()->back()->with('success', 'Payment rejected successfully.');
   }
}
