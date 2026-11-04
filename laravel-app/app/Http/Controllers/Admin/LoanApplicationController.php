<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanApplication;
use App\Models\RuleEvaluationLog;
use App\Services\LoanService;
use Illuminate\Http\Request;
use RuntimeException;

class LoanApplicationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');
        $search = trim((string) $request->query('search', ''));

        $applications = LoanApplication::with('user.division')
            ->when($status === 'pending', fn ($q) => $q->whereIn('status', ['pending', 'reviewing']))
            ->when($status !== 'pending' && isset(LoanApplication::STATUSES[$status]), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('application_number', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('full_name', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%"))))
            ->latest()->latest('id')->paginate(15)->withQueryString();

        $counts = LoanApplication::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('admin.loans.index', compact('applications', 'status', 'search', 'counts'));
    }

    public function show(LoanApplication $application, LoanService $loans)
    {
        $application->load(['user.division', 'reviewer', 'approver', 'activeLoan']);
        $member = $application->user;

        return view('admin.loans.show', [
            'application' => $application,
            'member' => $member,
            'facts' => $loans->facts($member, (float) $application->loan_amount, (int) $application->loan_term_months),
            'logs' => RuleEvaluationLog::where('entity_type', 'loan_application')->where('entity_id', $application->id)->oldest()->get(),
        ]);
    }

    public function approve(Request $request, LoanApplication $application, LoanService $loans)
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        try {
            $loans->approve($application, $request->user(), $data['notes'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan '.$application->application_number.' disetujui dan dana dicairkan.');
    }

    public function reject(Request $request, LoanApplication $application, LoanService $loans)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], [], ['reason' => 'alasan penolakan']);

        try {
            $loans->reject($application, $request->user(), $data['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan '.$application->application_number.' ditolak.');
    }
}
