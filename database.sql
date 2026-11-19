CREATE DATABASE IF NOT EXISTS pengadaan_pa_palu
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pengadaan_pa_palu;

-- 1. Data bagian/divisi
CREATE TABLE IF NOT EXISTS tbl_bagian (
  id_bagian INT(11) NOT NULL AUTO_INCREMENT,
  nama_bagian VARCHAR(50) NOT NULL,
  kode_bagian VARCHAR(10) NOT NULL,
  keterangan TEXT NULL,
  PRIMARY KEY (id_bagian),
  UNIQUE KEY uq_bagian_kode (kode_bagian)
) ENGINE=InnoDB;

-- 2. Akun pengguna dan hak akses
-- pegawai = Pegawai/Bagian Pemohon; petugas = Petugas Pengadaan;
-- pimpinan = Pihak yang Berwenang.
CREATE TABLE IF NOT EXISTS tbl_pengguna (
  id_pengguna INT(11) NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL,
  password VARCHAR(255) NOT NULL COMMENT 'Simpan hasil password_hash(), bukan password teks biasa',
  nama_lengkap VARCHAR(100) NOT NULL,
  id_bagian INT(11) NULL,
  role ENUM('pegawai','petugas','pimpinan') NOT NULL,
  email VARCHAR(100) NULL,
  no_telepon VARCHAR(15) NULL,
  foto VARCHAR(255) NULL,
  status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pengguna),
  UNIQUE KEY uq_pengguna_username (username),
  KEY idx_pengguna_bagian (id_bagian),
  CONSTRAINT fk_pengguna_bagian FOREIGN KEY (id_bagian)
    REFERENCES tbl_bagian(id_bagian) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- 3. Profil pegawai (relasi satu-ke-satu dengan pengguna)
CREATE TABLE IF NOT EXISTS tbl_pegawai (
  id_pegawai INT(11) NOT NULL AUTO_INCREMENT,
  id_pengguna INT(11) NOT NULL,
  id_bagian INT(11) NOT NULL,
  nip VARCHAR(20) NULL,
  jabatan VARCHAR(50) NULL,
  alamat TEXT NULL,
  PRIMARY KEY (id_pegawai),
  UNIQUE KEY uq_pegawai_pengguna (id_pengguna),
  KEY idx_pegawai_bagian (id_bagian),
  CONSTRAINT fk_pegawai_pengguna FOREIGN KEY (id_pengguna)
    REFERENCES tbl_pengguna(id_pengguna) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_pegawai_bagian FOREIGN KEY (id_bagian)
    REFERENCES tbl_bagian(id_bagian) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 4. Master data barang
CREATE TABLE IF NOT EXISTS tbl_barang (
  id_barang INT(11) NOT NULL AUTO_INCREMENT,
  kode_barang VARCHAR(20) NOT NULL,
  nama_barang VARCHAR(100) NOT NULL,
  kategori VARCHAR(50) NOT NULL,
  satuan VARCHAR(20) NOT NULL,
  stok INT(11) NOT NULL DEFAULT 0,
  stok_minimum INT(11) NOT NULL DEFAULT 0,
  harga_satuan DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  spesifikasi TEXT NULL,
  status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  PRIMARY KEY (id_barang),
  UNIQUE KEY uq_barang_kode (kode_barang)
) ENGINE=InnoDB;

-- 5. Pengajuan kebutuhan barang
CREATE TABLE IF NOT EXISTS tbl_pengajuan (
  id_pengajuan INT(11) NOT NULL AUTO_INCREMENT,
  no_pengajuan VARCHAR(20) NOT NULL,
  id_pengguna INT(11) NOT NULL COMMENT 'Pemohon',
  id_bagian INT(11) NOT NULL,
  tanggal_pengajuan DATE NOT NULL,
  keterangan TEXT NULL,
  status ENUM('menunggu pemeriksaan','menunggu persetujuan','disetujui','ditolak','diproses','selesai') NOT NULL DEFAULT 'menunggu pemeriksaan',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pengajuan),
  UNIQUE KEY uq_pengajuan_nomor (no_pengajuan),
  KEY idx_pengajuan_pengguna (id_pengguna),
  KEY idx_pengajuan_bagian (id_bagian),
  KEY idx_pengajuan_status (status),
  CONSTRAINT fk_pengajuan_pengguna FOREIGN KEY (id_pengguna)
    REFERENCES tbl_pengguna(id_pengguna) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_pengajuan_bagian FOREIGN KEY (id_bagian)
    REFERENCES tbl_bagian(id_bagian) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 6. Rincian barang dalam satu pengajuan
CREATE TABLE IF NOT EXISTS tbl_detail_pengajuan (
  id_detail_pengajuan INT(11) NOT NULL AUTO_INCREMENT,
  id_pengajuan INT(11) NOT NULL,
  id_barang INT(11) NOT NULL,
  jumlah INT(11) NOT NULL,
  keterangan TEXT NULL,
  PRIMARY KEY (id_detail_pengajuan),
  KEY idx_detail_pengajuan (id_pengajuan),
  KEY idx_detail_barang (id_barang),
  CONSTRAINT fk_detail_pengajuan_header FOREIGN KEY (id_pengajuan)
    REFERENCES tbl_pengajuan(id_pengajuan) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_detail_pengajuan_barang FOREIGN KEY (id_barang)
    REFERENCES tbl_barang(id_barang) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_detail_pengajuan_jumlah CHECK (jumlah > 0)
) ENGINE=InnoDB;

-- 7. Keputusan persetujuan oleh pihak yang berwenang
CREATE TABLE IF NOT EXISTS tbl_persetujuan (
  id_persetujuan INT(11) NOT NULL AUTO_INCREMENT,
  id_pengajuan INT(11) NOT NULL,
  id_pengguna INT(11) NOT NULL COMMENT 'Pengguna dengan role pimpinan',
  tanggal_persetujuan DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status_persetujuan ENUM('disetujui','ditolak') NOT NULL,
  PRIMARY KEY (id_persetujuan),
  KEY idx_persetujuan_pengajuan (id_pengajuan),
  KEY idx_persetujuan_pengguna (id_pengguna),
  CONSTRAINT fk_persetujuan_pengajuan FOREIGN KEY (id_pengajuan)
    REFERENCES tbl_pengajuan(id_pengajuan) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_persetujuan_pengguna FOREIGN KEY (id_pengguna)
    REFERENCES tbl_pengguna(id_pengguna) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 8. Data penyedia/pemasok
CREATE TABLE IF NOT EXISTS tbl_penyedia (
  id_penyedia INT(11) NOT NULL AUTO_INCREMENT,
  nama_penyedia VARCHAR(100) NOT NULL,
  alamat TEXT NULL,
  no_telepon VARCHAR(15) NULL,
  email VARCHAR(100) NULL,
  kontak_person VARCHAR(50) NULL,
  npwp VARCHAR(25) NULL,
  status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  PRIMARY KEY (id_penyedia)
) ENGINE=InnoDB;

-- 9. Transaksi pengadaan
CREATE TABLE IF NOT EXISTS tbl_pengadaan (
  id_pengadaan INT(11) NOT NULL AUTO_INCREMENT,
  no_pengadaan VARCHAR(20) NOT NULL,
  id_pengajuan INT(11) NOT NULL,
  id_penyedia INT(11) NOT NULL,
  id_pengguna INT(11) NOT NULL COMMENT 'Petugas Pengadaan yang memproses',
  tanggal_pengadaan DATE NOT NULL,
  total_harga DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  status ENUM('diproses','dikirim','selesai') NOT NULL DEFAULT 'diproses',
  PRIMARY KEY (id_pengadaan),
  UNIQUE KEY uq_pengadaan_nomor (no_pengadaan),
  KEY idx_pengadaan_pengajuan (id_pengajuan),
  KEY idx_pengadaan_penyedia (id_penyedia),
  KEY idx_pengadaan_pengguna (id_pengguna),
  CONSTRAINT fk_pengadaan_pengajuan FOREIGN KEY (id_pengajuan)
    REFERENCES tbl_pengajuan(id_pengajuan) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_pengadaan_penyedia FOREIGN KEY (id_penyedia)
    REFERENCES tbl_penyedia(id_penyedia) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_pengadaan_pengguna FOREIGN KEY (id_pengguna)
    REFERENCES tbl_pengguna(id_pengguna) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 10. Rincian barang yang dipesan/diadakan
CREATE TABLE IF NOT EXISTS tbl_detail_pengadaan (
  id_detail_pengadaan INT(11) NOT NULL AUTO_INCREMENT,
  id_pengadaan INT(11) NOT NULL,
  id_barang INT(11) NOT NULL,
  jumlah INT(11) NOT NULL,
  harga_satuan DECIMAL(15,2) NOT NULL,
  subtotal DECIMAL(15,2) NOT NULL,
  PRIMARY KEY (id_detail_pengadaan),
  KEY idx_detail_pengadaan_header (id_pengadaan),
  KEY idx_detail_pengadaan_barang (id_barang),
  CONSTRAINT fk_detail_pengadaan_header FOREIGN KEY (id_pengadaan)
    REFERENCES tbl_pengadaan(id_pengadaan) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_detail_pengadaan_barang FOREIGN KEY (id_barang)
    REFERENCES tbl_barang(id_barang) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_detail_pengadaan_jumlah CHECK (jumlah > 0)
) ENGINE=InnoDB;

-- 11. Pencatatan penerimaan barang
CREATE TABLE IF NOT EXISTS tbl_penerimaan (
  id_penerimaan INT(11) NOT NULL AUTO_INCREMENT,
  id_pengadaan INT(11) NOT NULL,
  id_pengguna INT(11) NOT NULL COMMENT 'Petugas yang mencatat penerimaan',
  tanggal_terima DATE NOT NULL,
  jumlah_diterima INT(11) NOT NULL,
  kondisi_barang ENUM('baik','rusak','kurang') NOT NULL,
  keterangan TEXT NULL,
  PRIMARY KEY (id_penerimaan),
  KEY idx_penerimaan_pengadaan (id_pengadaan),
  KEY idx_penerimaan_pengguna (id_pengguna),
  CONSTRAINT fk_penerimaan_pengadaan FOREIGN KEY (id_pengadaan)
    REFERENCES tbl_pengadaan(id_pengadaan) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_penerimaan_pengguna FOREIGN KEY (id_pengguna)
    REFERENCES tbl_pengguna(id_pengguna) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_penerimaan_jumlah CHECK (jumlah_diterima > 0)
) ENGINE=InnoDB;

-- 12. Audit trail aktivitas pengguna
CREATE TABLE IF NOT EXISTS tbl_riwayat (
  id_riwayat INT(11) NOT NULL AUTO_INCREMENT,
  id_pengguna INT(11) NOT NULL,
  aktivitas VARCHAR(100) NOT NULL,
  tabel_terkait VARCHAR(50) NOT NULL,
  id_terkait INT(11) NOT NULL,
  tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  keterangan TEXT NULL,
  PRIMARY KEY (id_riwayat),
  KEY idx_riwayat_pengguna (id_pengguna),
  KEY idx_riwayat_tanggal (tanggal),
  CONSTRAINT fk_riwayat_pengguna FOREIGN KEY (id_pengguna)
    REFERENCES tbl_pengguna(id_pengguna) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 13. Metadata laporan yang dicetak/dihasilkan
CREATE TABLE IF NOT EXISTS tbl_laporan (
  id_laporan INT(11) NOT NULL AUTO_INCREMENT,
  jenis_laporan ENUM('pengajuan','pengadaan','penerimaan','stok') NOT NULL,
  periode_awal DATE NOT NULL,
  periode_akhir DATE NOT NULL,
  id_pengguna INT(11) NOT NULL COMMENT 'Pengguna yang membuat laporan',
  tanggal_cetak DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  format_laporan ENUM('PDF','Excel') NOT NULL,
  PRIMARY KEY (id_laporan),
  KEY idx_laporan_pengguna (id_pengguna),
  CONSTRAINT fk_laporan_pengguna FOREIGN KEY (id_pengguna)
    REFERENCES tbl_pengguna(id_pengguna) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_laporan_periode CHECK (periode_akhir >= periode_awal)
) ENGINE=InnoDB;

-- Catatan:
-- 1. Buat akun melalui aplikasi menggunakan password_hash() PHP.
-- 2. Jangan memasukkan password dalam bentuk teks biasa.
-- 3. Role pegawai ditampilkan sebagai "Pemohon/User".
-- 4. Role pimpinan ditampilkan sebagai "Pihak yang Berwenang".
-- 5. Persetujuan akhir hanya boleh dilakukan oleh role pimpinan.
