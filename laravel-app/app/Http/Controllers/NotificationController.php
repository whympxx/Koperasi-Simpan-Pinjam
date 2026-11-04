<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('filter', 'all');
        $search = trim((string) $request->query('search', ''));

        $notifications = $request->user()->appNotifications()
            ->when($filter === 'unread', fn ($q) => $q->where('is_read', false))
            ->when($filter === 'read', fn ($q) => $q->where('is_read', true))
            ->when(isset(UserNotification::CATEGORIES[$filter]), fn ($q) => $q->where('category', $filter))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('message', 'like', "%{$search}%")))
            ->latest()->latest('id')
            ->paginate(20)->withQueryString();

        $unread = $request->user()->appNotifications()->where('is_read', false)->count();

        return view('notifications.index', compact('notifications', 'unread', 'filter', 'search'));
    }

    public function read(Request $request, UserNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->update(['is_read' => true, 'read_at' => now()]);

        if ($request->boolean('open') && $notification->action_url) {
            return redirect($notification->action_url);
        }

        return back()->with('success', 'Notifikasi ditandai sudah dibaca.');
    }

    public function readAll(Request $request)
    {
        $request->user()->appNotifications()->where('is_read', false)->update(['is_read' => true, 'read_at' => now()]);

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    public function destroy(Request $request, UserNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->delete();

        return back()->with('success', 'Notifikasi dihapus.');
    }
}
