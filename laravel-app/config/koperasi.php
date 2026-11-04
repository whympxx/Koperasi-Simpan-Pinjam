<?php

/*
| Nilai bawaan ketentuan KOPPUJA (RAT Tahun Buku 2025).
| Semua nilai dapat diubah pengurus lewat menu Pengaturan (tabel system_settings);
| nilai di sini hanya dipakai bila pengaturan belum tersimpan.
*/

return [
    'name' => env('KOPERASI_NAME', 'Koperasi Konsumen Karyawan PT. Puninar Jaya (KOPPUJA)'),
    'short_name' => 'KOPPUJA',

    'defaults' => [
        'loan_interest_rate' => 1.5,            // % per bulan, flat
        'max_loan_multiplier' => 2,             // plafon: 2x simpanan wajib (potongan HRD/Yayasan)
        'max_loan_multiplier_limited' => 1,     // plafon: 1x simpanan wajib (outsourcing/angsur mandiri)
        'max_loan_term' => 18,                  // tenor kebijakan khusus
        'max_loan_term_standard' => 12,         // tenor standar
        'min_mandatory_savings' => 50000,
        'monthly_mandatory_savings' => 50000,   // iuran simpanan wajib per bulan (potong gaji)
        'min_employment_months' => 12,
        'min_loan_amount' => 100000,
        'late_fee_percentage' => 0.5,           // % per hari dari nilai angsuran
        'loan_auto_approve_enabled' => true,
        'loan_auto_approve_max_amount' => 5000000,
        'loan_auto_approve_max_term_months' => 12,
        'shu_pct_cadangan' => 25,
        'shu_pct_jasa_anggota' => 40,
        'shu_pct_jasa_simpanan' => 50,
        'shu_pct_jasa_pinjaman' => 50,
        'shu_pct_dana_pengurus' => 10,
        'shu_pct_dana_karyawan' => 5,
        'shu_pct_dana_pendidikan' => 10,
        'shu_pct_dana_sosial' => 10,
    ],

    'max_deposit_amount' => 50000000,
    'min_deposit_amount' => 1000,
];
