<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CandidateStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCandidateStatusRequest;
use App\Http\Requests\ProcessGatewayRefundRequest;
use App\Services\CandidatePipelineService;
use App\Services\Payment\PaymentRefundService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CandidatePipelineController extends Controller
{
    public function __construct(
        private CandidatePipelineService $candidatePipeline,
        private PaymentRefundService $paymentRefundService,
    ) {}

    public function index(Request $request)
    {
        $candidates = $this->candidatePipeline->getCandidates([
            ...$request->all(),
        ]);

        return Inertia::render('dashboard/candidates/index', [
            'candidates' => $candidates,
            'statuses' => collect(CandidateStatus::cases())->map(fn (CandidateStatus $status) => [
                'value' => $status->value,
                'label' => $status->getLabel(),
            ])->values()->all(),
            'filters' => [
                'status' => $request->query('status'),
                'search' => $request->query('search'),
            ],
        ]);
    }

    public function show(string $id)
    {
        $detail = $this->candidatePipeline->getCandidateDetail($id);

        return Inertia::render('dashboard/candidates/show', $detail);
    }

    public function updateStatus(UpdateCandidateStatusRequest $request, string $id)
    {
        $this->candidatePipeline->updateStatus($id, $request->validated());

        return back()->with('success', 'Candidate status updated successfully');
    }

    public function processRefund(ProcessGatewayRefundRequest $request, string $candidate, string $payment)
    {
        $result = $this->paymentRefundService->processGatewayRefund(
            $candidate,
            $payment,
            $request->validated(),
        );

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message'],
        );
    }

    public function processAllRefunds(ProcessGatewayRefundRequest $request, string $candidate)
    {
        $result = $this->paymentRefundService->processAllGatewayRefunds(
            $candidate,
            $request->validated(),
        );

        $type = ($result['summary']['failed'] ?? 0) > 0 && ($result['summary']['succeeded'] ?? 0) === 0
            ? 'error'
            : 'success';

        return back()->with($type, $result['message']);
    }
}
