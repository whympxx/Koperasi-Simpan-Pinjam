<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Transaction;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Services\Audit;
use App\Services\LoanService;
use App\Services\SavingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $role = $request->query('role', 'all');
        $status = $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));

        $users = User::with(['division', 'savingsAccount'])
            ->when(isset(User::ROLES[$role]), fn ($q) => $q->where('role', $role))
            ->when(isset(User::STATUSES[$status]), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('full_name', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->latest()->paginate(15)->withQueryString();

        $counts = User::selectRaw('role, status, COUNT(*) as total')->groupBy('role', 'status')->get();

        return view('admin.users.index', compact('users', 'role', 'status', 'search', 'counts'));
    }

    public function create()
    {
        return view('admin.users.create', ['divisions' => Division::orderBy('name')->get()]);
    }

    public function store(Request $request, SavingsService $savings)
    {
        $request->merge([
            'nik' => strtoupper(trim((string) $request->input('nik'))),
            'email' => strtolower(trim((string) $request->input('email'))),
            'phone' => preg_replace('/\s+/', '', (string) $request->input('phone')),
            'monthly_salary' => parse_money($request->input('monthly_salary')),
        ]);

        $data = $request->validate($this->profileRules() + [
            'nik' => ['required', 'regex:/^[A-Z0-9]{1,20}$/', 'unique:users,nik'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'password' => ['required', 'confirmed', new StrongPassword],
        ], ['nik.regex' => 'NIK hanya boleh huruf/angka, maksimal 20 karakter.', 'phone.regex' => 'Gunakan format nomor Indonesia, contoh 081234567890.'], $this->attributeNames());

        $data['payroll_deduction'] = $data['member_category'] !== 'outsourcing' && $request->boolean('payroll_deduction');

        $user = DB::transaction(function () use ($data, $savings, $request) {
            $user = User::create($data + ['role' => 'nasabah', 'status' => 'active', 'is_verified' => true]);
            $account = $savings->createAccount($user);
            Audit::notify($user->id, 'Akun Nasabah Siap Digunakan', 'Akun Anda telah didaftarkan. Login menggunakan NIK '.$user->nik.'. No. rekening simpanan: '.$account->account_number.'.', 'success', 'account_activity');
            Audit::log($request->user()->id, 'nasabah_registered', 'user', $user->id, null, ['nik' => $user->nik, 'account_number' => $account->account_number]);

            return $user;
        });

        return redirect()->route('admin.users.show', $user)->with('success', 'Nasabah '.$user->full_name.' berhasil didaftarkan.');
    }

    public function show(User $user, LoanService $loans)
    {
        $user->load(['division', 'savingsAccount']);

        return view('admin.users.show', [
            'user' => $user,
            'divisions' => Division::orderBy('name')->get(),
            'activeLoan' => $user->activeLoan()->first(),
            'maxLoan' => $user->isNasabah() ? $loans->maxLoanAmount($user) : 0,
            'transactions' => $user->transactions()->latest()->latest('id')->limit(10)->get(),
            'applications' => $user->loanApplications()->latest()->limit(10)->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->guardTarget($request->user(), $user);

        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
            'phone' => preg_replace('/\s+/', '', (string) $request->input('phone')),
            'monthly_salary' => parse_money($request->input('monthly_salary')),
        ]);
        $data = $request->validate($this->profileRules() + [
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user->id)],
        ], ['phone.regex' => 'Gunakan format nomor Indonesia, contoh 081234567890.'], $this->attributeNames());
        $data['payroll_deduction'] = $data['member_category'] !== 'outsourcing' && $request->boolean('payroll_deduction');

        $old = $user->only(array_keys($data));
        $user->update($data);
        Audit::log($request->user()->id, 'user_updated', 'user', $user->id, $old, $data);

        return back()->with('success', 'Data anggota berhasil diperbarui.');
    }

    public function toggleStatus(Request $request, User $user)
    {
        $this->guardTarget($request->user(), $user);
        if ($user->status === 'resigned') {
            return back()->with('error', 'Anggota yang sudah keluar tidak dapat diaktifkan kembali dari sini.');
        }

        $new = $user->status === 'active' ? 'inactive' : 'active';
        Audit::log($request->user()->id, 'user_status_changed', 'user', $user->id, ['status' => $user->status], ['status' => $new]);
        $user->update(['status' => $new]);

        return back()->with('success', 'Status '.$user->full_name.' sekarang: '.User::STATUSES[$new].'.');
    }

    /** Pengurus mencatat setoran (mis. potongan gaji HRD) — langsung dibukukan. */
    public function deposit(Request $request, User $user, SavingsService $savings)
    {
        abort_unless($user->isNasabah() && $user->status === 'active', 404);
        $request->merge(['amount' => parse_money($request->input('amount'))]);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:'.config('koperasi.min_deposit_amount'), 'max:'.config('koperasi.max_deposit_amount')],
            'payment_method' => ['required', Rule::in(array_keys(Transaction::METHODS))],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $savings->deposit($user, (float) $data['amount'], $data['payment_method'], $data['description'] ?? null, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Setoran '.rupiah($data['amount']).' untuk '.$user->full_name.' telah dibukukan.');
    }

    public function closeMembership(Request $request, User $user, SavingsService $savings)
    {
        $this->guardTarget($request->user(), $user);
        $data = $request->validate([
            'payment_method' => ['required', Rule::in(['transfer', 'cash'])],
            'notes' => ['nullable', 'string', 'max:500'],
            'confirm' => ['accepted'],
        ], ['confirm.accepted' => 'Centang konfirmasi untuk memproses keluar keanggotaan.']);

        try {
            $tx = $savings->closeMembership($user, $request->user(), $data['payment_method'], $data['notes'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Keanggotaan '.$user->full_name.' diakhiri. Simpanan dikembalikan: '.rupiah($tx?->amount ?? 0).'.');
    }

    /** Admin biasa tidak boleh mengubah akun sendiri atau akun super admin. */
    private function guardTarget(User $actor, User $target): void
    {
        abort_if($actor->id === $target->id, 403, 'Anda tidak dapat mengubah akun sendiri dari menu ini.');
        abort_if($target->isSuperAdmin() && ! $actor->isSuperAdmin(), 403, 'Hanya super admin yang dapat mengubah akun super admin.');
    }

    private function profileRules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^(\+62|62|0)8[1-9][0-9]{6,10}$/'],
            'division_id' => ['nullable', 'exists:divisions,id'],
            'position' => ['nullable', 'string', 'max:50'],
            'member_category' => ['required', Rule::in(array_keys(User::CATEGORIES))],
            'employment_date' => ['required', 'date', 'before_or_equal:today'],
            'monthly_salary' => ['required', 'numeric', 'min:0'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function attributeNames(): array
    {
        return [
            'nik' => 'NIK', 'full_name' => 'nama lengkap', 'phone' => 'nomor telepon', 'division_id' => 'divisi',
            'position' => 'jabatan', 'member_category' => 'kategori anggota', 'employment_date' => 'tanggal masuk kerja',
            'monthly_salary' => 'gaji bulanan', 'address' => 'alamat',
        ];
    }
}
