<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->query('type', 'all');
        $status = $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));

        $query = Transaction::query()
            ->when(isset(Transaction::TYPES[$type]), fn ($q) => $q->where('type', $type))
            ->when(in_array($status, ['pending', 'completed', 'failed', 'cancelled'], true), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('reference_number', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('full_name', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%"))));

        $totals = Transaction::where('status', 'completed')->selectRaw('type, SUM(amount) as total')->groupBy('type')->pluck('total', 'type');

        return view('admin.transactions', [
            'transactions' => (clone $query)->with(['user', 'processor'])->latest()->latest('id')->paginate(20)->withQueryString(),
            'totals' => $totals,
            'type' => $type,
            'status' => $status,
            'search' => $search,
        ]);
    }
}
