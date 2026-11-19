SISTEM INFORMASI PENGADAAN BARANG – PENGADILAN AGAMA PALU
Versi penyesuaian dengan database pengadaan_pa_palu (13 tabel).

INSTALASI LARAGON
1. Ekstrak folder ini ke C:\laragon\www\sistem_pengadaan_pa_palu
2. Jalankan Apache dan MySQL.
3. Buka phpMyAdmin, lalu impor database.sql. File ini membuat database
   pengadaan_pa_palu dan 13 tabel sesuai struktur database yang diberikan.
4. Periksa config.php. Default Laragon: host localhost, user root,
   password kosong. Sesuaikan jika konfigurasi MySQL berbeda.
5. Buka http://localhost/sistem_pengadaan_pa_palu/setup.php
   untuk membuat data bagian, akun demo, dan contoh barang.
6. Hapus setup.php setelah setup berhasil.
7. Buka http://localhost/sistem_pengadaan_pa_palu/

AKUN DEMO
Pegawai/Pemohon: pegawai / pegawai123
Petugas Pengadaan: petugas / petugas123
Pihak Berwenang: pimpinan / pimpinan123

PENYESUAIAN SKEMA
- Menggunakan tbl_pengguna dengan role pegawai, petugas, pimpinan.
- Menggunakan tbl_bagian dan tbl_pegawai sesuai relasi database.
- Pengajuan menggunakan tbl_pengajuan dan tbl_detail_pengajuan.
  Barang dipilih dari tbl_barang, bukan mengetik nama barang bebas.
- Keputusan disimpan pada tbl_persetujuan.
- Penyedia menggunakan tbl_penyedia.
- Transaksi menggunakan tbl_pengadaan dan tbl_detail_pengadaan.
- Penerimaan menggunakan tbl_penerimaan.
- Aktivitas dicatat pada tbl_riwayat.
- Tabel lama users, barang, pemasok, pengajuan, pengadaan, penerimaan
  tidak lagi dibuat atau dipakai oleh aplikasi.

CATATAN
Database yang diberikan tidak memiliki kolom catatan pemeriksaan atau catatan
keputusan pada tbl_pengajuan/tbl_persetujuan. Karena itu aplikasi tidak
menyimpan catatan tersebut pada kolom yang tidak tersedia.
Form pengajuan saat ini menangani satu barang per pengajuan. Struktur database
mendukung beberapa barang; form dapat dikembangkan menjadi baris dinamis.
Pastikan membuat backup sebelum mengganti database lama.
