<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'nik' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string'],
        ], [], ['nik' => 'NIK']);

        $nik = strtoupper(trim($data['nik']));
        $user = User::where('nik', $nik)->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            Audit::log(null, 'failed_login', 'user', $user?->id, null, ['nik' => $nik]);

            return back()->withErrors(['nik' => 'NIK atau password salah.'])->onlyInput('nik');
        }

        if ($user->status !== 'active') {
            return back()->withErrors(['nik' => 'Akun Anda tidak aktif. Hubungi pengurus koperasi.'])->onlyInput('nik');
        }
        if (! $user->is_verified) {
            return back()->withErrors(['nik' => 'Akun Anda belum diverifikasi. Hubungi pengurus koperasi.'])->onlyInput('nik');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        Audit::log($user->id, 'login', 'user', $user->id);

        return redirect()->intended(route($user->homeRoute()));
    }

    public function logout(Request $request)
    {
        if ($request->user()) {
            Audit::log($request->user()->id, 'logout', 'user', $request->user()->id);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda telah berhasil keluar.');
    }
}
