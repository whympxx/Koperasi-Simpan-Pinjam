@extends('layouts.app')
@section('title', 'Basis Aturan (Rule Engine)')
@section('content')
@php
    use App\Services\RuleEngine;
    $facts = RuleEngine::factCatalog();
    $ops = RuleEngine::operators();
    $badge = ['approve' => 'success', 'reject' => 'danger', 'manual_review' => 'warning'];
    $statusIcon = ['fired' => 'fa-circle-check text-success', 'not_fired' => 'fa-circle-xmark text-secondary', 'skipped' => 'fa-circle-minus text-muted'];
    $conds = old('fact_key')
        ? collect(old('fact_key'))->map(fn ($f, $i) => ['fact_key' => $f, 'operator' => old("operator.$i"), 'value_type' => old("value_type.$i"), 'compare_value' => old("compare_value.$i"), 'fallback_value' => old("fallback_value.$i")])->all()
        : ($editing ? $editing->conditions->toArray() : [['fact_key' => '', 'operator' => '>', 'value_type' => 'static', 'compare_value' => '', 'fallback_value' => '']]);
    $f = fn ($k, $d = null) => old($k, $editing?->$k ?? $d);
@endphp

<p class="text-muted">Aturan dievaluasi berurutan menurut prioritas (angka kecil lebih dulu). Aturan pertama yang seluruh kondisinya terpenuhi menentukan keputusan.</p>

@foreach (RuleEngine::groups() as $group => $label)
    <div class="card mb-4">
        <div class="card-header bg-white fw-semibold">{{ $label }}</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Kode</th><th>Prioritas</th><th>Nama</th><th>JIKA</th><th>MAKA</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach ($rules[$group] ?? [] as $r)
                    <tr class="{{ $r->is_active ? '' : 'text-muted' }}">
                        <td><strong>{{ $r->rule_code }}</strong> @if ($r->is_system)<i class="fas fa-shield-halved text-primary" title="Aturan inti"></i>@endif</td>
                        <td>{{ $r->priority }}</td>
                        <td>{{ $r->name }}</td>
                        <td class="small">{{ app(RuleEngine::class)->describe($r) }}</td>
                        <td><span class="badge bg-{{ $badge[$r->conclusion] }}">{{ RuleEngine::conclusions()[$r->conclusion] }}</span></td>
                        <td>{!! $r->is_active ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Nonaktif</span>' !!}</td>
                        <td class="text-nowrap">
                            <a href="{{ route('admin.rules.edit', $r) }}#form" class="btn btn-sm btn-outline-primary"><i class="fas fa-pen"></i></a>
                            <form method="POST" action="{{ route('admin.rules.toggle', $r) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-secondary" title="Aktif/nonaktif"><i class="fas fa-power-off"></i></button></form>
                            @unless ($r->is_system)
                                <form method="POST" action="{{ route('admin.rules.destroy', $r) }}" class="d-inline" onsubmit="return confirm('Hapus aturan {{ $r->rule_code }}?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endforeach

<div class="row g-4">
    <div class="col-xl-7">
        <form id="form" class="card" method="POST" action="{{ $editing ? route('admin.rules.update', $editing) : route('admin.rules.store') }}">
            @csrf @if ($editing) @method('PUT') @endif
            <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                {{ $editing ? 'Ubah Aturan '.$editing->rule_code : 'Tambah Aturan Baru' }}
                @if ($editing)<a href="{{ route('admin.rules.index') }}" class="small">Batal ubah</a>@endif
            </div>
            <div class="card-body row g-3">
                <div class="col-md-3"><label class="form-label">Kode</label><input name="rule_code" value="{{ $f('rule_code', $nextCode) }}" class="form-control text-uppercase" required></div>
                <div class="col-md-5"><label class="form-label">Kelompok</label><select name="rule_group" class="form-select">
                    @foreach (RuleEngine::groups() as $k => $l)<option value="{{ $k }}" @selected($f('rule_group') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label">Prioritas</label><input type="number" min="1" name="priority" value="{{ $f('priority', 100) }}" class="form-control" required></div>
                <div class="col-md-2 d-flex align-items-end"><div class="form-check"><input type="checkbox" class="form-check-input" name="is_active" value="1" id="ra" @checked($f('is_active', true))><label for="ra" class="form-check-label">Aktif</label></div></div>
                <div class="col-md-8"><label class="form-label">Nama aturan</label><input name="name" value="{{ $f('name') }}" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Kesimpulan (MAKA)</label><select name="conclusion" class="form-select">
                    @foreach (RuleEngine::conclusions() as $k => $l)<option value="{{ $k }}" @selected($f('conclusion') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="col-12"><label class="form-label">Pesan hasil</label><input name="message" value="{{ $f('message') }}" class="form-control" required>
                    <div class="form-text">Gunakan <code>{nilai}</code> untuk nilai pembanding dan <code>{fakta}</code> untuk nilai fakta nasabah (kondisi pertama).</div></div>
                <div class="col-12"><label class="form-label">Deskripsi</label><textarea name="description" class="form-control" rows="2">{{ $f('description') }}</textarea></div>

                <div class="col-12"><label class="form-label mb-1">Kondisi (JIKA … DAN …)</label>
                    <div id="conditions">
                        @foreach ($conds as $c)
                            <div class="row g-1 mb-1 cond">
                                <div class="col-md-4"><select name="fact_key[]" class="form-select form-select-sm"><option value="">- fakta -</option>
                                    @foreach ($facts as $k => $l)<option value="{{ $k }}" @selected(($c['fact_key'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
                                <div class="col-md-2"><select name="operator[]" class="form-select form-select-sm">
                                    @foreach ($ops as $k => $l)<option value="{{ $k }}" @selected(($c['operator'] ?? '') === $k)>{{ $k }}</option>@endforeach</select></div>
                                <div class="col-md-2"><select name="value_type[]" class="form-select form-select-sm">
                                    @foreach (['static' => 'nilai', 'setting' => 'pengaturan', 'fact' => 'fakta'] as $k => $l)<option value="{{ $k }}" @selected(($c['value_type'] ?? 'static') === $k)>{{ $l }}</option>@endforeach</select></div>
                                <div class="col-md-2"><input name="compare_value[]" value="{{ $c['compare_value'] ?? '' }}" class="form-control form-control-sm" placeholder="pembanding"></div>
                                <div class="col-md-1"><input name="fallback_value[]" value="{{ $c['fallback_value'] ?? '' }}" class="form-control form-control-sm" placeholder="cadangan"></div>
                                <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.cond').remove()">×</button></div>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-1" onclick="addCond()"><i class="fas fa-plus me-1"></i>Tambah kondisi</button>
                </div>
            </div>
            <div class="card-footer bg-white"><button class="btn btn-primary"><i class="fas fa-save me-1"></i>Simpan Aturan</button></div>
        </form>
    </div>

    <div class="col-xl-5">
        <form class="card mb-4" method="POST" action="{{ route('admin.rules.simulate') }}">
            @csrf
            <div class="card-header bg-white fw-semibold"><i class="fas fa-flask me-1"></i>Simulasi Keputusan</div>
            <div class="card-body row g-2 small">
                @php $o = fn ($k, $d) => old($k, $d); @endphp
                <div class="col-6"><label class="form-label mb-0">Masa kerja (bulan)</label><input type="number" min="0" name="masa_kerja_bulan" value="{{ $o('masa_kerja_bulan', 24) }}" class="form-control form-control-sm"></div>
                <div class="col-6"><label class="form-label mb-0">Status kepegawaian</label><select name="status_karyawan" class="form-select form-select-sm">@foreach (['active', 'inactive', 'resigned'] as $s)<option @selected($o('status_karyawan', 'active') === $s)>{{ $s }}</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label mb-0">Kategori</label><select name="kategori_anggota" class="form-select form-select-sm">@foreach (array_keys(\App\Models\User::CATEGORIES) as $s)<option @selected($o('kategori_anggota', 'karyawan') === $s)>{{ $s }}</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label mb-0">Potongan HRD</label><select name="potongan_hrd" class="form-select form-select-sm"><option value="1">Ya</option><option value="0" @selected($o('potongan_hrd', '1') === '0')>Tidak</option></select></div>
                <div class="col-6"><label class="form-label mb-0">Simpanan wajib</label><input name="simpanan_wajib" value="{{ $o('simpanan_wajib', 3000000) }}" class="form-control form-control-sm" data-money></div>
                <div class="col-6"><label class="form-label mb-0">Gaji bulanan</label><input name="gaji_bulanan" value="{{ $o('gaji_bulanan', 5000000) }}" class="form-control form-control-sm" data-money></div>
                <div class="col-6"><label class="form-label mb-0">Jumlah pinjaman</label><input name="jumlah_pinjaman" value="{{ $o('jumlah_pinjaman', 3000000) }}" class="form-control form-control-sm" data-money></div>
                <div class="col-6"><label class="form-label mb-0">Tenor (bulan)</label><input type="number" min="1" name="tenor_bulan" value="{{ $o('tenor_bulan', 12) }}" class="form-control form-control-sm"></div>
                <div class="col-4"><label class="form-label mb-0">Pinjaman aktif</label><select name="punya_pinjaman_aktif" class="form-select form-select-sm"><option value="0">Tidak</option><option value="1" @selected($o('punya_pinjaman_aktif', '0') === '1')>Ya</option></select></div>
                <div class="col-4"><label class="form-label mb-0">Pengajuan pending</label><select name="punya_pengajuan_pending" class="form-select form-select-sm"><option value="0">Tidak</option><option value="1" @selected($o('punya_pengajuan_pending', '0') === '1')>Ya</option></select></div>
                <div class="col-4"><label class="form-label mb-0">Riwayat telat</label><input type="number" min="0" name="riwayat_tunggakan" value="{{ $o('riwayat_tunggakan', 0) }}" class="form-control form-control-sm"></div>
            </div>
            <div class="card-footer bg-white"><button class="btn btn-outline-primary w-100">Jalankan Simulasi</button></div>
        </form>

        @if ($simulation)
            @php $final = $simulation['eligibility']['decision'] === 'reject' ? $simulation['eligibility'] : $simulation['decision']; @endphp
            <div class="card mb-4 border-{{ $badge[$final['decision']] ?? 'secondary' }}">
                <div class="card-header bg-white fw-semibold">Hasil: <span class="badge bg-{{ $badge[$final['decision']] ?? 'secondary' }}">{{ RuleEngine::conclusions()[$final['decision']] ?? $final['decision'] }}</span>
                    @if ($final['rule_code'])<span class="badge bg-dark">{{ $final['rule_code'] }}</span>@endif</div>
                <div class="card-body small">
                    <p>{{ $final['message'] }}</p>
                    <p class="text-muted mb-1">Plafon: {{ rupiah($simulation['facts']['plafon_maksimal']) }} · Angsuran: {{ rupiah($simulation['facts']['angsuran_bulanan']) }}</p>
                    @foreach (array_filter([$simulation['eligibility'], $simulation['decision']]) as $ev)
                        <div class="fw-semibold mt-2">{{ RuleEngine::groups()[$ev['group']] }}</div>
                        <ul class="list-unstyled mb-0">@foreach ($ev['trace'] as $t)<li><i class="fas {{ $statusIcon[$t['status']] }} me-1"></i>{{ $t['rule_code'] }} {{ $t['rule_name'] }}</li>@endforeach</ul>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header bg-white fw-semibold">Log Evaluasi Terbaru</div>
            <div class="table-responsive"><table class="table table-sm mb-0 small">
                @forelse ($logs as $log)
                    <tr><td>{{ $log->created_at->format('d/m H:i') }}</td><td>{{ $log->user?->full_name ?? '-' }}</td><td>{{ $log->decision }}</td><td>{{ $log->fired_rule_code ?? '-' }}</td>
                        <td>@if ($log->entity_type === 'loan_application' && $log->entity_id)<a href="{{ route('admin.loans.show', $log->entity_id) }}">lihat</a>@endif</td></tr>
                @empty
                    <tr><td class="text-center text-muted py-3">Belum ada log.</td></tr>
                @endforelse
            </table></div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
    function addCond() {
        const rows = document.querySelectorAll('#conditions .cond');
        const clone = rows[rows.length - 1].cloneNode(true);
        clone.querySelectorAll('input').forEach(i => i.value = '');
        clone.querySelectorAll('select').forEach(s => s.selectedIndex = 0);
        document.getElementById('conditions').appendChild(clone);
    }
</script>
@endpush
