<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Model;

/** Pencatatan log aktivitas dan notifikasi pengguna. */
class Audit
{
    public static function log(?int $userId, string $action, ?string $entityType = null, ?int $entityId = null, ?array $old = null, ?array $new = null): void
    {
        $request = request();

        ActivityLog::create([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    public static function notify(int $userId, string $title, string $message, string $type = 'info', string $category = 'system', ?string $actionUrl = null): UserNotification
    {
        return UserNotification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'category' => $category,
            'action_url' => $actionUrl,
        ]);
    }

    public static function notifyAdmins(string $title, string $message, string $type = 'info', string $category = 'system', ?string $actionUrl = null): void
    {
        User::whereIn('role', ['admin', 'super_admin'])->where('status', 'active')->pluck('id')
            ->each(fn ($id) => self::notify($id, $title, $message, $type, $category, $actionUrl));
    }

    /**
     * Nomor referensi berurutan per hari, mis. LOAN2511040001.
     *
     * @param  class-string<Model>  $model
     */
    public static function reference(string $prefix, string $model, string $column): string
    {
        $base = $prefix.now()->format('ymd');
        $last = $model::where($column, 'like', $base.'%')->orderByDesc($column)->value($column);
        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $base.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
