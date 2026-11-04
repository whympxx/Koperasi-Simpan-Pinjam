<?php

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

if (! function_exists('rupiah')) {
    function rupiah($amount, bool $withPrefix = true): string
    {
        $formatted = number_format((float) $amount, 0, ',', '.');

        return $withPrefix ? 'Rp '.$formatted : $formatted;
    }
}

if (! function_exists('tanggal')) {
    function tanggal($date, bool $withTime = false): string
    {
        if (! $date) {
            return '-';
        }

        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        return $date->locale('id')->translatedFormat($withTime ? 'd F Y H:i' : 'd F Y');
    }
}

/** Ubah input uang format Indonesia ("1.500.000" atau "1.500.000,50") menjadi float. */
if (! function_exists('parse_money')) {
    function parse_money($value): float
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return 0.0;
        }
        $raw = str_replace(['Rp', ' '], '', $raw);
        $raw = str_replace('.', '', $raw);
        $raw = str_replace(',', '.', $raw);

        return is_numeric($raw) ? (float) $raw : 0.0;
    }
}

if (! function_exists('status_badge')) {
    function status_badge(?string $status): string
    {
        $map = [
            'pending' => ['warning', 'Menunggu'],
            'reviewing' => ['info', 'Direview'],
            'approved' => ['success', 'Disetujui'],
            'rejected' => ['danger', 'Ditolak'],
            'disbursed' => ['primary', 'Dicairkan'],
            'active' => ['success', 'Aktif'],
            'completed' => ['secondary', 'Selesai'],
            'restructured' => ['info', 'Restrukturisasi'],
            'paid' => ['success', 'Lunas'],
            'overdue' => ['danger', 'Terlambat'],
            'partial' => ['info', 'Sebagian'],
            'failed' => ['danger', 'Gagal'],
            'cancelled' => ['secondary', 'Dibatalkan'],
            'inactive' => ['warning', 'Nonaktif'],
            'resigned' => ['secondary', 'Keluar'],
            'closed' => ['secondary', 'Ditutup'],
            'draft' => ['warning', 'Draf'],
            'final' => ['success', 'Final'],
        ];
        [$color, $label] = $map[$status] ?? ['light text-dark', ucfirst((string) $status)];

        return '<span class="badge bg-'.$color.'">'.e($label).'</span>';
    }
}
