@php $u = $user ?? null; @endphp
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Nama Lengkap</label>
        <input type="text" name="full_name" value="{{ old('full_name', $u?->full_name) }}" class="form-control" maxlength="100" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Email</label>
        <input type="email" name="email" value="{{ old('email', $u?->email) }}" class="form-control" maxlength="100" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">No. Telepon</label>
        <input type="text" name="phone" value="{{ old('phone', $u?->phone) }}" class="form-control" placeholder="081234567890" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Divisi</label>
        <select name="division_id" class="form-select">
            <option value="">- Pilih -</option>
            @foreach ($divisions as $d)<option value="{{ $d->id }}" @selected((string) old('division_id', $u?->division_id) === (string) $d->id)>{{ $d->name }}</option>@endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Jabatan</label>
        <input type="text" name="position" value="{{ old('position', $u?->position) }}" class="form-control" maxlength="50">
    </div>
    <div class="col-md-6">
        <label class="form-label">Tanggal Masuk Kerja</label>
        <input type="date" name="employment_date" value="{{ old('employment_date', $u?->employment_date?->toDateString()) }}" class="form-control" max="{{ today()->toDateString() }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Kategori Anggota</label>
        <select name="member_category" class="form-select" required>
            @foreach (\App\Models\User::CATEGORIES as $k => $v)<option value="{{ $k }}" @selected(old('member_category', $u?->member_category ?? 'karyawan') === $k)>{{ $v }}</option>@endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Gaji / Komisi Bulanan</label>
        <div class="input-group"><span class="input-group-text">Rp</span>
            <input type="text" name="monthly_salary" value="{{ old('monthly_salary', $u ? (int) $u->monthly_salary : '') }}" class="form-control" data-money required></div>
    </div>
    <div class="col-12">
        <div class="form-check">
            <input type="hidden" name="payroll_deduction" value="0">
            <input class="form-check-input" type="checkbox" name="payroll_deduction" id="payroll" value="1" @checked(old('payroll_deduction', $u?->payroll_deduction ?? true))>
            <label class="form-check-label" for="payroll">Angsuran dipotong gaji melalui HRD/Yayasan (plafon standar). Tidak berlaku untuk outsourcing.</label>
        </div>
    </div>
    <div class="col-12">
        <label class="form-label">Alamat</label>
        <textarea name="address" class="form-control" rows="2" maxlength="500">{{ old('address', $u?->address) }}</textarea>
    </div>
</div>
