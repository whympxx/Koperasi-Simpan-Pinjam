<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends Controller
{
    public function request()
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request)
    {
        $data = $request->validate([
            'nik' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email'],
        ], [], ['nik' => 'NIK']);

        $user = User::where('nik', strtoupper(trim($data['nik'])))
            ->where('email', strtolower(trim($data['email'])))
            ->where('status', 'active')
            ->first();

        if ($user) {
            Password::sendResetLink(['email' => $user->email]);
        }

        // Pesan sama untuk semua kasus agar data akun tidak bisa ditebak.
        return back()->with('success', 'Jika NIK dan email cocok, link reset password telah dikirim ke email Anda (berlaku 60 menit).');
    }

    public function reset(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', new StrongPassword],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => $password])->save();
            Audit::log($user->id, 'password_reset', 'user', $user->id);
            Audit::notify($user->id, 'Password Berhasil Direset', 'Password akun Anda direset pada '.now()->format('d/m/Y H:i').'.', 'success', 'account_activity');
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Password berhasil direset. Silakan login dengan password baru.')
            : back()->withErrors(['email' => 'Token reset password tidak valid atau sudah kedaluwarsa.']);
    }
}
