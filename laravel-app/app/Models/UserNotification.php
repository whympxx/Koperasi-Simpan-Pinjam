<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    protected $table = 'notifications';

    public const CATEGORIES = [
        'loan_approval' => 'Pinjaman',
        'payment_reminder' => 'Pengingat Bayar',
        'account_activity' => 'Aktivitas Akun',
        'system' => 'Sistem',
    ];

    protected $fillable = ['user_id', 'title', 'message', 'type', 'category', 'is_read', 'read_at', 'action_url'];

    protected function casts(): array
    {
        return ['is_read' => 'boolean', 'read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
