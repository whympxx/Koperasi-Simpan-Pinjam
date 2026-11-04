<?php

namespace App\Http\Controllers;

use App\Rules\StrongPassword;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load('division', 'savingsAccount');

        return view('profile.show', compact('user'));
    }

    public function editPassword()
    {
        return view('profile.password');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', new StrongPassword],
        ], [
            'current_password.current_password' => 'Password lama salah.',
            'password.different' => 'Password baru harus berbeda dari password lama.',
        ]);

        $user = $request->user();
        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        Audit::log($user->id, 'change_password', 'user', $user->id);
        Audit::notify($user->id, 'Password Berhasil Diubah', 'Password akun Anda diubah pada '.now()->format('d/m/Y H:i').'.', 'success', 'account_activity');

        return back()->with('success', 'Password berhasil diubah.');
    }
}
