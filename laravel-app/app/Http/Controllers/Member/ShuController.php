<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\ShuService;
use Illuminate\Http\Request;

class ShuController extends Controller
{
    public function index(Request $request, ShuService $shu)
    {
        $year = (int) $request->query('year', now()->year);
        if ($year < 2000 || $year > (int) now()->year) {
            $year = (int) now()->year;
        }

        return view('member.shu', [
            'year' => $year,
            'share' => $shu->memberShare($request->user(), $year),
        ]);
    }
}
