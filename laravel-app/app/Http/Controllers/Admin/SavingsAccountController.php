<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SavingsAccount;
use Illuminate\Http\Request;

class SavingsAccountController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'active');
        $search = trim((string) $request->query('search', ''));

        $accounts = SavingsAccount::with('user')
            ->when(in_array($status, ['active', 'closed'], true), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('account_number', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('full_name', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%"))))
            ->orderByDesc('balance')->paginate(20)->withQueryString();

        return view('admin.savings', [
            'accounts' => $accounts,
            'status' => $status,
            'search' => $search,
            'totalBalance' => SavingsAccount::where('status', 'active')->sum('balance'),
            'activeCount' => SavingsAccount::where('status', 'active')->count(),
        ]);
    }
}
