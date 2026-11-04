<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $action = (string) $request->query('action', '');
        $search = trim((string) $request->query('search', ''));

        $logs = ActivityLog::with('user')
            ->when($action !== '', fn ($q) => $q->where('action', $action))
            ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('full_name', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%")))
            ->latest()->latest('id')
            ->paginate(30)->withQueryString();

        return view('admin.activity-logs', [
            'logs' => $logs,
            'actions' => ActivityLog::select('action')->distinct()->orderBy('action')->pluck('action'),
            'action' => $action,
            'search' => $search,
        ]);
    }
}
