<?php

return [

    /*
    | Format nomor surat keluar otomatis. Token yang tersedia:
    |   {no}             nomor urut agenda surat keluar pada tahun tsb
    |   {klasifikasi}    kode klasifikasi arsip surat (mis. HK.02.01)
    |   {unit}           kode unit pengolah; bila kosong memakai kode_instansi
    |   {bulan_romawi}   bulan tanggal surat dalam angka romawi (I-XII)
    |   {tahun}          tahun tanggal surat
    | Segmen yang kosong otomatis dibuang beserta pemisahnya.
    | Nomor hasil generate tetap bisa diubah petugas sebelum disimpan.
    */
    'letter_number_format' => env('SIARPU_LETTER_NUMBER_FORMAT', '{no}/{klasifikasi}/{unit}/{tahun}'),

    'agency_code' => env('SIARPU_AGENCY_CODE', 'BBWSNT2'),

    // Batas ukuran berkas pindaian surat (KB).
    'letter_max_file_kb' => (int) env('SIARPU_LETTER_MAX_FILE_KB', 51200),

];
