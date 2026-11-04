<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    public function index()
    {
        return view('admin.announcements', [
            'announcements' => Announcement::with('author')->latest()->paginate(15),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $announcement = Announcement::create($data + ['created_by' => $request->user()->id]);

        if ($announcement->is_published && $request->boolean('notify')) {
            User::where('role', 'nasabah')->where('status', 'active')->pluck('id')
                ->each(fn ($id) => Audit::notify($id, 'Pengumuman: '.$announcement->title, $announcement->body, $announcement->category === 'penting' ? 'warning' : 'info', 'system', route('dashboard', absolute: false)));
        }
        Audit::log($request->user()->id, 'announcement_created', 'announcement', $announcement->id, null, ['title' => $announcement->title]);

        return back()->with('success', 'Pengumuman diterbitkan.');
    }

    public function update(Request $request, Announcement $announcement)
    {
        $announcement->update($this->validated($request));
        Audit::log($request->user()->id, 'announcement_updated', 'announcement', $announcement->id);

        return back()->with('success', 'Pengumuman diperbarui.');
    }

    public function destroy(Request $request, Announcement $announcement)
    {
        Audit::log($request->user()->id, 'announcement_deleted', 'announcement', $announcement->id, ['title' => $announcement->title]);
        $announcement->delete();

        return back()->with('success', 'Pengumuman dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:3000'],
            'category' => ['required', Rule::in(array_keys(Announcement::CATEGORIES))],
            'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
        ], [], ['title' => 'judul', 'body' => 'isi pengumuman', 'expires_at' => 'tanggal berakhir']);

        return $data + ['is_published' => $request->boolean('is_published')];
    }
}
