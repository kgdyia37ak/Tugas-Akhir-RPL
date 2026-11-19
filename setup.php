<?php
require __DIR__.'/config.php';

try {
    // Pastikan file database.sql sudah diimpor terlebih dahulu.
    $pdo->beginTransaction();

    $bagianData = [
        ['Bagian Umum','UM'],
        ['Bagian Keuangan','KEU'],
        ['Bagian Kepegawaian','PEG']
    ];
    $findBagian=$pdo->prepare("SELECT id_bagian FROM tbl_bagian WHERE kode_bagian=?");
    $insertBagian=$pdo->prepare("INSERT INTO tbl_bagian (nama_bagian,kode_bagian) VALUES (?,?)");
    foreach($bagianData as [$nama,$kode]) {
        $findBagian->execute([$kode]);
        if (!$findBagian->fetch()) $insertBagian->execute([$nama,$kode]);
    }

    $findUser=$pdo->prepare("SELECT id_pengguna FROM tbl_pengguna WHERE username=?");
    $insertUser=$pdo->prepare("INSERT INTO tbl_pengguna (username,password,nama_lengkap,id_bagian,role,status) VALUES (?,?,?,?,?,'aktif')");
    $findBagian->execute(['UM']); $bagianUmum=(int)$findBagian->fetchColumn();

    $accounts=[
        ['pegawai','pegawai123','Pegawai Demo',$bagianUmum,'pegawai'],
        ['petugas','petugas123','Petugas Pengadaan',$bagianUmum,'petugas'],
        ['pimpinan','pimpinan123','Pihak yang Berwenang',$bagianUmum,'pimpinan']
    ];
    foreach($accounts as [$username,$password,$nama,$idBagian,$role]) {
        $findUser->execute([$username]);
        if (!$findUser->fetch()) {
            $insertUser->execute([$username,password_hash($password,PASSWORD_DEFAULT),$nama,$idBagian,$role]);
        }
    }

    $findBarang=$pdo->prepare("SELECT id_barang FROM tbl_barang WHERE kode_barang=?");
    $insertBarang=$pdo->prepare("INSERT INTO tbl_barang (kode_barang,nama_barang,kategori,satuan,stok,stok_minimum,harga_satuan,status) VALUES (?,?,?,?,?,?,?,'aktif')");
    $examples=[
        ['BRG-001','Laptop','Elektronik','Unit',5,1,8500000],
        ['BRG-002','Kertas A4','ATK','Rim',20,5,55000],
        ['BRG-003','Printer','Elektronik','Unit',3,1,2200000]
    ];
    foreach($examples as $b) {
        $findBarang->execute([$b[0]]);
        if (!$findBarang->fetch()) $insertBarang->execute($b);
    }
    $pdo->commit();
    echo '<h2>Setup akun demo berhasil.</h2><p>Database dan seluruh tabel harus sudah diimpor dari database.sql.</p>
    <ul><li>Pegawai: <b>pegawai</b> / <b>pegawai123</b></li><li>Petugas: <b>petugas</b> / <b>petugas123</b></li><li>Pimpinan: <b>pimpinan</b> / <b>pimpinan123</b></li></ul>
    <p><a href="index.php">Buka aplikasi</a></p><b>Hapus setup.php setelah selesai.</b>';
} catch(Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo 'Setup gagal: '.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8');
}
