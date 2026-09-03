# Digital Arsip Dokumen Pekerjaan — BBWS Nusa Tenggara II

Sistem manajemen siklus hidup dokumen pekerjaan BBWS Nusa Tenggara II.
Rancangan lengkap ada di *Dokumen Perancangan Sistem* (Word) yang sudah
diberikan sebelumnya — proyek ini adalah implementasi Fase 1 dari rencana
fase pengembangan pada Bab VIII dokumen tersebut.

Lihat **SETUP.md** untuk langkah instalasi.

## Cakupan Fase 1 (sudah ada di proyek ini)

- Skema database lengkap: struktur organisasi (pohon, CRUD), jenis
  pekerjaan, fase, status pekerjaan, tags, data pekerjaan, lokasi spasial
  (PostGIS), dokumen → file → versi file, tabel Spatie Permission.
- Eloquent model untuk seluruh entitas di atas, lengkap dengan relasi.
- Seeder: struktur organisasi contoh, fase default, role & permission
  sesuai Bab VII dokumen perancangan.

## Belum ada di proyek ini (menyusul di fase berikutnya)

UI (CRUD Livewire untuk setiap modul), autentikasi (breeze belum
di-generate — lihat SETUP.md), arsip fisik & QR Code, peminjaman, peta
publik, OCR, notifikasi, dashboard.
