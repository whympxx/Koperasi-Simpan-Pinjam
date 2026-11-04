@php
    /** @var \App\Models\User $member */
    $acct = $member->savingsAccount?->account_number ?? '0000000000';
    $hash = md5($member->nik.$acct);
    $bits = collect(str_split(str_pad(base_convert(substr($hash, 0, 15), 16, 2), 81, '1', STR_PAD_LEFT)))->take(81);
@endphp
<div class="member-card-scene" data-tilt="10" tabindex="0" role="button" aria-label="Kartu anggota, tekan untuk membalik">
    <div class="member-card">
        <div class="face front">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="label">Kartu Anggota</div>
                    <div class="fw-bold" style="letter-spacing:.1em">{{ config('koperasi.short_name') }}</div>
                </div>
                <i class="fas fa-building-columns fa-lg" style="color:var(--k-gold-400)"></i>
            </div>
            <div class="chip my-2"></div>
            <div class="number mb-2">{{ trim(chunk_split($acct, 4, ' ')) }}</div>
            <div class="d-flex justify-content-between align-items-end mt-auto">
                <div class="min-w-0">
                    <div class="label">Nama Anggota</div>
                    <div class="fw-semibold text-truncate">{{ strtoupper($member->full_name) }}</div>
                </div>
                <div class="text-end">
                    <div class="label">NIK</div>
                    <div class="fw-semibold">{{ $member->nik }}</div>
                </div>
            </div>
        </div>
        <div class="face back">
            <div class="stripe"></div>
            <div class="d-flex gap-3">
                <div class="qr">@foreach ($bits as $b)<span class="{{ $b === '1' ? '' : 'o' }}"></span>@endforeach</div>
                <div class="small">
                    <div class="label">Kategori</div><div class="mb-1">{{ $member->categoryLabel() }}</div>
                    <div class="label">Anggota sejak</div><div>{{ tanggal($member->savingsAccount?->opened_date ?? $member->created_at) }}</div>
                </div>
            </div>
            <div class="mt-auto small" style="color:rgba(255,255,255,.6);font-size:.65rem">
                Kartu ini milik {{ config('koperasi.name') }}. Tunjukkan kepada pengurus saat bertransaksi di kantor koperasi.
            </div>
        </div>
    </div>
</div>
<div class="small text-muted mt-2"><i class="fas fa-rotate me-1"></i>Klik kartu untuk melihat sisi belakang</div>
