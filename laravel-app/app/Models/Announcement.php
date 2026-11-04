<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    public const CATEGORIES = [
        'info' => 'Informasi',
        'rat' => 'Rapat Anggota (RAT)',
        'penting' => 'Penting',
    ];

    protected $fillable = ['title', 'body', 'category', 'is_published', 'expires_at', 'created_by'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'expires_at' => 'date'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Pengumuman yang tampil ke anggota: terbit dan belum kedaluwarsa. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()));
    }
}
