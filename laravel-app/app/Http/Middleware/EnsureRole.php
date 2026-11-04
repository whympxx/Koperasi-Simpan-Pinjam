<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Batasi akses berdasarkan peran, mis. middleware('role:admin,super_admin'). */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Akun yang dinonaktifkan saat masih login langsung dikeluarkan.
        if ($user && $user->status !== 'active') {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['nik' => 'Akun Anda tidak aktif. Hubungi pengurus koperasi.']);
        }

        if (! $user || ! in_array($user->role, $roles, true)) {
            abort(403, 'Anda tidak memiliki izin untuk mengakses halaman ini.');
        }

        return $next($request);
    }
}
