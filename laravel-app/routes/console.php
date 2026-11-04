<?php

use App\Services\LoanService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('loans:mark-overdue', function (LoanService $loans) {
    $this->info($loans->markOverdue().' angsuran ditandai terlambat.');
})->purpose('Tandai angsuran yang lewat jatuh tempo sebagai terlambat');

Schedule::command('loans:mark-overdue')->dailyAt('00:10');
