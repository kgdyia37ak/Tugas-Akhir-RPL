<?php
require __DIR__.'/config.php';
require __DIR__.'/functions.php';

if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['login'])) {
    $account=row($pdo,"SELECT * FROM tbl_pengguna WHERE username=? AND status='aktif' LIMIT 1",[trim($_POST['username'] ?? '')]);
    if ($account && password_verify($_POST['password'] ?? '',$account['password'])) {
        session_regenerate_id(true);
        $_SESSION['user']=[
            'id_pengguna'=>(int)$account['id_pengguna'],
            'nama_lengkap'=>$account['nama_lengkap'],
            'username'=>$account['username'],
            'role'=>$account['role'],
            'id_bagian'=>$account['id_bagian']
        ];
        redirect();
    }
    flash('Username atau password tidak sesuai.','error');
    header('Location: index.php'); exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    require_login(); verify_csrf();
    $action=$_POST['action'] ?? '';
    try {
        if ($action==='ajukan') {
            allow_roles(['pegawai']);
            $bagian=(int)user()['id_bagian'];
            $items=$_POST['barang'] ?? []; $qty=$_POST['jumlah'] ?? [];
            if (!$bagian || !is_array($items) || !$items) throw new RuntimeException('Akun pemohon harus memiliki bagian dan minimal satu barang harus dipilih.');
            $pdo->beginTransaction();
            $s=$pdo->prepare("INSERT INTO tbl_pengajuan (no_pengajuan,id_pengguna,id_bagian,tanggal_pengajuan,keterangan,status) VALUES (?,?,?,?,?,'menunggu pemeriksaan')");
            $s->execute([nomor('PGB-'),user()['id_pengguna'],$bagian,date('Y-m-d'),trim($_POST['keterangan'] ?? '')]);
            $id=(int)$pdo->lastInsertId();
            $d=$pdo->prepare("INSERT INTO tbl_detail_pengajuan (id_pengajuan,id_barang,jumlah,keterangan) VALUES (?,?,?,?)");
            foreach ($items as $i=>$barangId) {
                $q=(int)($qty[$i] ?? 0);
                if ((int)$barangId<1 || $q<1) throw new RuntimeException('Barang dan jumlah harus diisi dengan benar.');
                $d->execute([$id,(int)$barangId,$q,'']);
            }
            log_activity($pdo,'Membuat pengajuan','tbl_pengajuan',$id);
            $pdo->commit(); flash('Pengajuan berhasil dikirim.'); redirect('riwayat');
        }
        if ($action==='periksa') {
            allow_roles(['petugas']);
            $id=(int)$_POST['id'];
            $s=$pdo->prepare("UPDATE tbl_pengajuan SET status='menunggu persetujuan' WHERE id_pengajuan=? AND status='menunggu pemeriksaan'");
            $s->execute([$id]);
            if (!$s->rowCount()) throw new RuntimeException('Pengajuan tidak ditemukan atau sudah diperiksa.');
            log_activity($pdo,'Memeriksa pengajuan','tbl_pengajuan',$id,trim($_POST['catatan'] ?? ''));
            flash('Pengajuan diteruskan kepada pimpinan.'); redirect('pengajuan');
        }
        if ($action==='putusan') {
            allow_roles(['pimpinan']);
            $id=(int)$_POST['id']; $decision=$_POST['keputusan'] ?? '';
            if (!in_array($decision,['disetujui','ditolak'],true)) throw new RuntimeException('Keputusan tidak valid.');
            $pdo->beginTransaction();
            $s=$pdo->prepare("UPDATE tbl_pengajuan SET status=? WHERE id_pengajuan=? AND status='menunggu persetujuan'");
            $s->execute([$decision,$id]);
            if (!$s->rowCount()) throw new RuntimeException('Pengajuan tidak ditemukan atau sudah diputuskan.');
            $pdo->prepare("INSERT INTO tbl_persetujuan (id_pengajuan,id_pengguna,status_persetujuan) VALUES (?,?,?)")->execute([$id,user()['id_pengguna'],$decision]);
            log_activity($pdo,'Memberikan keputusan','tbl_pengajuan',$id,$decision);
            $pdo->commit(); flash('Keputusan berhasil disimpan.'); redirect('persetujuan');
        }
        if ($action==='tambah_barang') {
            allow_roles(['petugas']);
            $s=$pdo->prepare("INSERT INTO tbl_barang (kode_barang,nama_barang,kategori,satuan,stok,stok_minimum,harga_satuan,spesifikasi) VALUES (?,?,?,?,?,?,?,?)");
            $s->execute([trim($_POST['kode_barang']),trim($_POST['nama_barang']),trim($_POST['kategori']),trim($_POST['satuan']),max(0,(int)$_POST['stok']),max(0,(int)$_POST['stok_minimum']),max(0,(float)$_POST['harga_satuan']),trim($_POST['spesifikasi'] ?? '')]);
            log_activity($pdo,'Menambah barang','tbl_barang',(int)$pdo->lastInsertId());
            flash('Barang berhasil ditambahkan.'); redirect('barang');
        }
        if ($action==='tambah_penyedia') {
            allow_roles(['petugas']);
            $s=$pdo->prepare("INSERT INTO tbl_penyedia (nama_penyedia,alamat,no_telepon,email,kontak_person,npwp) VALUES (?,?,?,?,?,?)");
            $s->execute([trim($_POST['nama_penyedia']),trim($_POST['alamat']),trim($_POST['no_telepon']),trim($_POST['email']),trim($_POST['kontak_person']),trim($_POST['npwp'])]);
            log_activity($pdo,'Menambah penyedia','tbl_penyedia',(int)$pdo->lastInsertId());
            flash('Data penyedia berhasil ditambahkan.'); redirect('penyedia');
        }
        if ($action==='proses_pengadaan') {
            allow_roles(['petugas']);
            $id=(int)$_POST['id_pengajuan']; $vendor=(int)$_POST['id_penyedia']; $total=max(0,(float)$_POST['total_harga']);
            if (!$vendor) throw new RuntimeException('Pilih penyedia terlebih dahulu.');
            $pdo->beginTransaction();
            $p=row($pdo,"SELECT * FROM tbl_pengajuan WHERE id_pengajuan=? AND status='disetujui' FOR UPDATE",[$id]);
            if (!$p) throw new RuntimeException('Pengajuan belum disetujui atau sudah diproses.');
            $s=$pdo->prepare("INSERT INTO tbl_pengadaan (no_pengadaan,id_pengajuan,id_penyedia,id_pengguna,tanggal_pengadaan,total_harga,status) VALUES (?,?,?,?,?,?,'diproses')");
            $s->execute([nomor('PGD-'),$id,$vendor,user()['id_pengguna'],date('Y-m-d'),$total]);
            $gid=(int)$pdo->lastInsertId();
            $details=rows($pdo,"SELECT * FROM tbl_detail_pengajuan WHERE id_pengajuan=?",[$id]);
            $d=$pdo->prepare("INSERT INTO tbl_detail_pengadaan (id_pengadaan,id_barang,jumlah,harga_satuan,subtotal) VALUES (?,?,?,?,?)");
            foreach($details as $detail) {
                $b=row($pdo,"SELECT harga_satuan FROM tbl_barang WHERE id_barang=?",[$detail['id_barang']]);
                $price=(float)($b['harga_satuan'] ?? 0);
                $d->execute([$gid,$detail['id_barang'],$detail['jumlah'],$price,$price*(int)$detail['jumlah']]);
            }
            $pdo->prepare("UPDATE tbl_pengajuan SET status='diproses' WHERE id_pengajuan=?")->execute([$id]);
            log_activity($pdo,'Membuat transaksi pengadaan','tbl_pengadaan',$gid);
            $pdo->commit(); flash('Transaksi pengadaan berhasil dibuat.'); redirect('pengadaan');
        }
        if ($action==='status_pengadaan') {
            allow_roles(['petugas']);
            $id=(int)$_POST['id_pengadaan']; $status=$_POST['status'] ?? '';
            if (!in_array($status,['dikirim','selesai'],true)) throw new RuntimeException('Status pengadaan tidak valid.');
            $s=$pdo->prepare("UPDATE tbl_pengadaan SET status=? WHERE id_pengadaan=? AND status IN ('diproses','dikirim')");
            $s->execute([$status,$id]);
            if (!$s->rowCount()) throw new RuntimeException('Status tidak dapat diperbarui.');
            log_activity($pdo,'Mengubah status pengadaan','tbl_pengadaan',$id,$status);
            flash('Status pengadaan diperbarui.'); redirect('pengadaan');
        }
        if ($action==='terima') {
            allow_roles(['petugas']);
            $id=(int)$_POST['id_pengadaan']; $jumlah=(int)$_POST['jumlah_diterima']; $condition=$_POST['kondisi_barang'] ?? '';
            if ($jumlah<1 || !in_array($condition,['baik','rusak','kurang'],true)) throw new RuntimeException('Data penerimaan tidak valid.');
            $pdo->beginTransaction();
            $g=row($pdo,"SELECT * FROM tbl_pengadaan WHERE id_pengadaan=? AND status IN ('dikirim','diproses') FOR UPDATE",[$id]);
            if (!$g) throw new RuntimeException('Transaksi tidak ditemukan atau sudah selesai.');
            $pdo->prepare("INSERT INTO tbl_penerimaan (id_pengadaan,id_pengguna,tanggal_terima,jumlah_diterima,kondisi_barang,keterangan) VALUES (?,?,?,?,?,?)")
                ->execute([$id,user()['id_pengguna'],date('Y-m-d'),$jumlah,$condition,trim($_POST['keterangan'] ?? '')]);
            $pdo->prepare("UPDATE tbl_pengadaan SET status='selesai' WHERE id_pengadaan=?")->execute([$id]);
            $pdo->prepare("UPDATE tbl_pengajuan SET status='selesai' WHERE id_pengajuan=?")->execute([$g['id_pengajuan']]);
            log_activity($pdo,'Mencatat penerimaan','tbl_penerimaan',(int)$pdo->lastInsertId());
            $pdo->commit(); flash('Penerimaan barang berhasil dicatat.'); redirect('penerimaan');
        }
    } catch(Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash($ex->getMessage(),'error');
        redirect($_POST['return_page'] ?? 'dashboard');
    }
}

$logged=user();
$role=role();
$allowed=[
 'pegawai'=>['dashboard','pengajuan','riwayat','profil'],
 'petugas'=>['dashboard','pengajuan','barang','penyedia','pengadaan','penerimaan','laporan'],
 'pimpinan'=>['dashboard','persetujuan','laporan']
];
$menus=[
 'pegawai'=>['dashboard'=>'Beranda','pengajuan'=>'Ajukan Barang','riwayat'=>'Riwayat Pengajuan','profil'=>'Profil Saya'],
 'petugas'=>['dashboard'=>'Beranda','pengajuan'=>'Pemeriksaan Pengajuan','barang'=>'Data Barang','penyedia'=>'Data Penyedia','pengadaan'=>'Proses Pengadaan','penerimaan'=>'Penerimaan Barang','laporan'=>'Laporan'],
 'pimpinan'=>['dashboard'=>'Beranda','persetujuan'=>'Persetujuan Pengajuan','laporan'=>'Laporan']
];
$page=$_GET['page'] ?? 'dashboard';
if ($logged && !in_array($page,$allowed[$role] ?? [],true)) { http_response_code(403); $page='forbidden'; }
$flash=$_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$roleLabel=['pegawai'=>'Pemohon / Pegawai','petugas'=>'Petugas Pengadaan','pimpinan'=>'Pihak yang Berwenang'];
if (!$logged): ?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login | Pengadaan Barang PA Palu</title><link rel="stylesheet" href="style.css"></head><body>
<div class="login-screen"><section class="login-art"><div class="brand-logo"><img src="logo_pa_palu.png" alt="Logo PA Palu"></div><div class="art-copy"><span class="eyebrow">PENGADILAN AGAMA PALU</span><h1>Pengadaan yang<br><em>lebih tertata.</em></h1><p>Kelola pengajuan kebutuhan, pemeriksaan, persetujuan, dan proses pengadaan dalam satu sistem.</p></div><div class="art-footer">Sistem Pengadaan Barang · 2026</div></section>
<section class="login-panel"><div class="login-box"><div class="mini-brand">PA <span>PALU</span></div><p class="eyebrow muted">SELAMAT DATANG</p><h2>Masuk ke akun</h2><p class="subtle">Gunakan akun sesuai hak akses Anda.</p>
<?php if($flash): ?><p class="error"><?=e($flash['message'])?></p><?php endif; ?>
<form method="post"><label>Username</label><input name="username" required autofocus><label>Password</label><input type="password" name="password" required><button class="primary-btn full" name="login" value="1">Masuk <span>→</span></button></form>
<div class="login-hint">Akun dibuat oleh administrator melalui database atau aplikasi.</div></div><div class="login-copyright">© 2026 Pengadilan Agama Palu</div></section></div></body></html>
<?php exit; endif; ?>

<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sistem Pengadaan Barang | PA Palu</title><link rel="stylesheet" href="style.css"></head><body>
<div class="app"><aside class="sidebar"><div class="side-brand"><div class="brand-mark small">PA</div><div><b>Pengadilan Agama</b><small>KOTA PALU</small></div></div><div class="nav-caption">MENU UTAMA</div><nav>
<?php foreach($menus[$role] as $key=>$label): ?><a class="nav-item <?=$page===$key?'active':''?>" href="index.php?page=<?=$key?>"><span>•</span><?=e($label)?></a><?php endforeach; ?>
</nav><div class="side-bottom"><div class="help-card"><div class="help-icon">?</div><b>Butuh bantuan?</b><p>Hubungi administrator sistem jika mengalami kendala.</p></div><a class="nav-item logout" href="index.php?logout=1"><span>↪</span>Keluar</a></div></aside>
<main class="main"><header class="topbar"><div class="breadcrumb">Sistem Pengadaan <span>/</span><b><?=e($menus[$role][$page] ?? 'Akses Ditolak')?></b></div><div class="top-user"><div class="avatar"><?=e(strtoupper(substr(user()['nama_lengkap'],0,2)))?></div><div><b><?=e(user()['nama_lengkap'])?></b><small><?=e($roleLabel[$role])?></small></div></div></header>
<div class="content">
<?php if($flash): ?><div class="notice <?=e($flash['type'])?>"><?=e($flash['message'])?></div><?php endif; ?>
<?php if($page==='forbidden'): ?><h1>Akses ditolak</h1>
<?php elseif($page==='dashboard'):
 $where=$role==='pegawai'?'WHERE id_pengguna=?':'';
 $params=$role==='pegawai'?[user()['id_pengguna']]:[];
 $st=$pdo->prepare("SELECT COUNT(*) FROM tbl_pengajuan $where");$st->execute($params);$total=(int)$st->fetchColumn();
 $st=$pdo->prepare("SELECT COUNT(*) FROM tbl_pengajuan ".($role==='pegawai'?'WHERE id_pengguna=? AND':'WHERE')." status IN ('menunggu pemeriksaan','menunggu persetujuan','diproses')");$st->execute($params);$waiting=(int)$st->fetchColumn();
 $st=$pdo->prepare("SELECT COUNT(*) FROM tbl_pengajuan ".($role==='pegawai'?'WHERE id_pengguna=? AND':'WHERE')." status='selesai'");$st->execute($params);$done=(int)$st->fetchColumn();
 $sql="SELECT p.no_pengajuan,p.tanggal_pengajuan,p.status,u.nama_lengkap AS pemohon,
 (SELECT GROUP_CONCAT(CONCAT(b.nama_barang,' (',d.jumlah,' ',b.satuan,')') SEPARATOR ', ') FROM tbl_detail_pengajuan d JOIN tbl_barang b ON b.id_barang=d.id_barang WHERE d.id_pengajuan=p.id_pengajuan) AS barang
 FROM tbl_pengajuan p JOIN tbl_pengguna u ON u.id_pengguna=p.id_pengguna ".($role==='pegawai'?'WHERE p.id_pengguna=?':'')." ORDER BY p.created_at DESC LIMIT 8";
 $data=rows($pdo,$sql,$params);
 ?>
 <div class="page-head"><div class="eyebrow-small">DASHBOARD</div><h1>Selamat datang, <?=e(user()['nama_lengkap'])?></h1><p>Ringkasan aktivitas sistem pengadaan barang.</p></div>
 <div class="stats"><div class="stat-card"><div class="stat-top">Total Pengajuan</div><div class="stat-number"><?=$total?></div></div><div class="stat-card"><div class="stat-top">Menunggu Proses</div><div class="stat-number"><?=$waiting?></div></div><div class="stat-card"><div class="stat-top">Selesai</div><div class="stat-number"><?=$done?></div></div></div>
 <section class="table-panel"><div class="table-tools"><b>Pengajuan Terbaru</b></div><div class="table-wrap"><table><thead><tr><th>NOMOR</th><th>BARANG</th><?php if($role!=='pegawai'):?><th>PEMOHON</th><?php endif;?><th>STATUS</th><th>TANGGAL</th></tr></thead><tbody><?php foreach($data as $r):?><tr><td><?=e($r['no_pengajuan'])?></td><td><?=e($r['barang'] ?: '-')?></td><?php if($role!=='pegawai'):?><td><?=e($r['pemohon'])?></td><?php endif;?><td><?=badge($r['status'])?></td><td><?=e(date('d/m/Y',strtotime($r['tanggal_pengajuan'])))?></td></tr><?php endforeach;?><?php if(!$data):?><tr><td colspan="5">Belum ada pengajuan.</td></tr><?php endif;?></tbody></table></div></section>

<?php elseif($page==='pengajuan' && $role==='pegawai'):
 $barang=rows($pdo,"SELECT id_barang,kode_barang,nama_barang,satuan FROM tbl_barang WHERE status='aktif' ORDER BY nama_barang"); ?>
 <div class="page-head"><h1>Ajukan Kebutuhan Barang</h1><p>Pilih barang dari master data barang.</p></div>
 <section class="panel"><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="ajukan"><input type="hidden" name="return_page" value="pengajuan">
 <div class="field"><label>Barang</label><select name="barang[]" required><?php foreach($barang as $b):?><option value="<?=$b['id_barang']?>"><?=e($b['kode_barang'].' - '.$b['nama_barang'].' ('.$b['satuan'].')')?></option><?php endforeach;?></select></div>
 <div class="field"><label>Jumlah</label><input type="number" name="jumlah[]" min="1" required></div>
 <div class="field"><label>Keterangan</label><textarea name="keterangan"></textarea></div>
 <button class="primary-btn">Kirim Pengajuan</button></form></section>

<?php elseif($page==='riwayat'):
 $data=rows($pdo,"SELECT p.*,b.nama_bagian FROM tbl_pengajuan p JOIN tbl_bagian b ON b.id_bagian=p.id_bagian WHERE p.id_pengguna=? ORDER BY p.created_at DESC",[user()['id_pengguna']]); ?>
 <div class="page-head"><h1>Riwayat Pengajuan</h1></div><section class="table-panel"><div class="table-wrap"><table><thead><tr><th>NOMOR</th><th>TANGGAL</th><th>BAGIAN</th><th>STATUS</th><th>KETERANGAN</th></tr></thead><tbody><?php foreach($data as $r):?><tr><td><?=e($r['no_pengajuan'])?></td><td><?=e($r['tanggal_pengajuan'])?></td><td><?=e($r['nama_bagian'])?></td><td><?=badge($r['status'])?></td><td><?=e($r['keterangan'])?></td></tr><?php endforeach;?></tbody></table></div></section>

<?php elseif($page==='pengajuan' && $role==='petugas'):
 $data=rows($pdo,"SELECT p.*,u.nama_lengkap AS pemohon,b.nama_bagian,
 (SELECT GROUP_CONCAT(CONCAT(br.nama_barang,' (',d.jumlah,' ',br.satuan,')') SEPARATOR ', ') FROM tbl_detail_pengajuan d JOIN tbl_barang br ON br.id_barang=d.id_barang WHERE d.id_pengajuan=p.id_pengajuan) AS barang
 FROM tbl_pengajuan p JOIN tbl_pengguna u ON u.id_pengguna=p.id_pengguna JOIN tbl_bagian b ON b.id_bagian=p.id_bagian ORDER BY p.created_at DESC"); ?>
 <div class="page-head"><h1>Pemeriksaan Pengajuan</h1><p>Periksa pengajuan sebelum diteruskan kepada pimpinan.</p></div><section class="table-panel"><div class="table-wrap"><table><thead><tr><th>NOMOR</th><th>PEMOHON</th><th>BAGIAN</th><th>BARANG</th><th>STATUS</th><th>AKSI</th></tr></thead><tbody><?php foreach($data as $r):?><tr><td><?=e($r['no_pengajuan'])?></td><td><?=e($r['pemohon'])?></td><td><?=e($r['nama_bagian'])?></td><td><?=e($r['barang'])?></td><td><?=badge($r['status'])?></td><td><?php if($r['status']==='menunggu pemeriksaan'):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="periksa"><input type="hidden" name="return_page" value="pengajuan"><input type="hidden" name="id" value="<?=$r['id_pengajuan']?>"><button class="primary-btn small-btn">Teruskan</button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div></section>

<?php elseif($page==='persetujuan'):
 $data=rows($pdo,"SELECT p.*,u.nama_lengkap AS pemohon,b.nama_bagian FROM tbl_pengajuan p JOIN tbl_pengguna u ON u.id_pengguna=p.id_pengguna JOIN tbl_bagian b ON b.id_bagian=p.id_bagian WHERE p.status='menunggu persetujuan' ORDER BY p.created_at"); ?>
 <div class="page-head"><h1>Persetujuan Pengajuan</h1><p>Keputusan akhir dilakukan oleh pihak yang berwenang.</p></div><section class="table-panel"><div class="table-wrap"><table><thead><tr><th>NOMOR</th><th>PEMOHON</th><th>BAGIAN</th><th>KETERANGAN</th><th>KEPUTUSAN</th></tr></thead><tbody><?php foreach($data as $r):?><tr><td><?=e($r['no_pengajuan'])?></td><td><?=e($r['pemohon'])?></td><td><?=e($r['nama_bagian'])?></td><td><?=e($r['keterangan'])?></td><td><form method="post" class="inline-form"><?=csrf_field()?><input type="hidden" name="action" value="putusan"><input type="hidden" name="return_page" value="persetujuan"><input type="hidden" name="id" value="<?=$r['id_pengajuan']?>"><button class="primary-btn small-btn" name="keputusan" value="disetujui">Setujui</button><button class="secondary-btn" name="keputusan" value="ditolak">Tolak</button></form></td></tr><?php endforeach;?></tbody></table></div></section>

<?php elseif($page==='barang'):
 $data=rows($pdo,"SELECT * FROM tbl_barang ORDER BY nama_barang"); ?>
 <div class="page-head"><h1>Data Barang</h1></div><section class="panel"><h3>Tambah Barang</h3><form method="post" class="inline-form"><?=csrf_field()?><input type="hidden" name="action" value="tambah_barang"><input type="hidden" name="return_page" value="barang"><input name="kode_barang" placeholder="Kode barang" required><input name="nama_barang" placeholder="Nama barang" required><input name="kategori" placeholder="Kategori" required><input name="satuan" placeholder="Satuan" required><input type="number" name="stok" min="0" placeholder="Stok"><input type="number" name="stok_minimum" min="0" placeholder="Stok minimum"><input type="number" name="harga_satuan" min="0" step="0.01" placeholder="Harga satuan"><button class="primary-btn small-btn">Tambah</button></form></section><section class="table-panel"><div class="table-wrap"><table><thead><tr><th>KODE</th><th>NAMA BARANG</th><th>KATEGORI</th><th>STOK</th><th>SATUAN</th><th>HARGA</th></tr></thead><tbody><?php foreach($data as $r):?><tr><td><?=e($r['kode_barang'])?></td><td><?=e($r['nama_barang'])?></td><td><?=e($r['kategori'])?></td><td><?=e($r['stok'])?></td><td><?=e($r['satuan'])?></td><td><?=rupiah($r['harga_satuan'])?></td></tr><?php endforeach;?></tbody></table></div></section>

<?php elseif($page==='penyedia'):
 $data=rows($pdo,"SELECT * FROM tbl_penyedia ORDER BY nama_penyedia"); ?>
 <div class="page-head"><h1>Data Penyedia</h1></div><section class="panel"><h3>Tambah Penyedia</h3><form method="post" class="inline-form"><?=csrf_field()?><input type="hidden" name="action" value="tambah_penyedia"><input type="hidden" name="return_page" value="penyedia"><input name="nama_penyedia" placeholder="Nama penyedia" required><input name="alamat" placeholder="Alamat"><input name="no_telepon" placeholder="Telepon"><input name="email" type="email" placeholder="Email"><input name="kontak_person" placeholder="Kontak person"><input name="npwp" placeholder="NPWP"><button class="primary-btn small-btn">Tambah</button></form></section><section class="table-panel"><div class="table-wrap"><table><thead><tr><th>NAMA</th><th>ALAMAT</th><th>TELEPON</th><th>EMAIL</th><th>KONTAK</th></tr></thead><tbody><?php foreach($data as $r):?><tr><td><?=e($r['nama_penyedia'])?></td><td><?=e($r['alamat'])?></td><td><?=e($r['no_telepon'])?></td><td><?=e($r['email'])?></td><td><?=e($r['kontak_person'])?></td></tr><?php endforeach;?></tbody></table></div></section>

<?php elseif($page==='pengadaan'):
 $approved=rows($pdo,"SELECT id_pengajuan,no_pengajuan FROM tbl_pengajuan WHERE status='disetujui' ORDER BY tanggal_pengajuan");
 $vendors=rows($pdo,"SELECT id_penyedia,nama_penyedia FROM tbl_penyedia WHERE status='aktif' ORDER BY nama_penyedia");
 $data=rows($pdo,"SELECT g.*,p.no_pengajuan,v.nama_penyedia FROM tbl_pengadaan g JOIN tbl_pengajuan p ON p.id_pengajuan=g.id_pengajuan JOIN tbl_penyedia v ON v.id_penyedia=g.id_penyedia ORDER BY g.id_pengadaan DESC"); ?>
 <div class="page-head"><h1>Proses Pengadaan</h1></div><?php if($approved && $vendors):?><section class="panel"><h3>Buat Pengadaan</h3><?php foreach($approved as $a):?><form method="post" class="inline-form"><?=csrf_field()?><input type="hidden" name="action" value="proses_pengadaan"><input type="hidden" name="return_page" value="pengadaan"><input type="hidden" name="id_pengajuan" value="<?=$a['id_pengajuan']?>"><b><?=e($a['no_pengajuan'])?></b><select name="id_penyedia" required><option value="">Pilih penyedia</option><?php foreach($vendors as $v):?><option value="<?=$v['id_penyedia']?>"><?=e($v['nama_penyedia'])?></option><?php endforeach;?></select><input type="number" name="total_harga" min="0" step="0.01" placeholder="Total harga" required><button class="primary-btn small-btn">Proses</button></form><?php endforeach;?></section><?php endif;?><section class="table-panel"><div class="table-wrap"><table><thead><tr><th>NOMOR PENGADAAN</th><th>PENGAJUAN</th><th>PENYEDIA</th><th>TOTAL</th><th>STATUS</th><th>UBAH STATUS</th></tr></thead><tbody><?php foreach($data as $r):?><tr><td><?=e($r['no_pengadaan'])?></td><td><?=e($r['no_pengajuan'])?></td><td><?=e($r['nama_penyedia'])?></td><td><?=rupiah($r['total_harga'])?></td><td><?=badge($r['status'])?></td><td><?php if($r['status']!=='selesai'):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="status_pengadaan"><input type="hidden" name="return_page" value="pengadaan"><input type="hidden" name="id_pengadaan" value="<?=$r['id_pengadaan']?>"><select name="status"><option value="dikirim">Dikirim</option><option value="selesai">Selesai</option></select><button class="primary-btn small-btn">Simpan</button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div></section>

<?php elseif($page==='penerimaan'):
 $data=rows($pdo,"SELECT g.id_pengadaan,g.no_pengadaan,g.status,p.no_pengajuan FROM tbl_pengadaan g JOIN tbl_pengajuan p ON p.id_pengajuan=g.id_pengajuan WHERE g.status IN ('diproses','dikirim') ORDER BY g.tanggal_pengadaan"); ?>
 <div class="page-head"><h1>Penerimaan Barang</h1></div><section class="table-panel"><div class="table-wrap"><table><thead><tr><th>PENGADAAN</th><th>PENGAJUAN</th><th>STATUS</th><th>CATAT PENERIMAAN</th></tr></thead><tbody><?php foreach($data as $r):?><tr><td><?=e($r['no_pengadaan'])?></td><td><?=e($r['no_pengajuan'])?></td><td><?=badge($r['status'])?></td><td><form method="post" class="inline-form"><?=csrf_field()?><input type="hidden" name="action" value="terima"><input type="hidden" name="return_page" value="penerimaan"><input type="hidden" name="id_pengadaan" value="<?=$r['id_pengadaan']?>"><input type="number" name="jumlah_diterima" min="1" placeholder="Jumlah" required><select name="kondisi_barang"><option value="baik">Baik</option><option value="rusak">Rusak</option><option value="kurang">Kurang</option></select><input name="keterangan" placeholder="Keterangan"><button class="primary-btn small-btn">Simpan</button></form></td></tr><?php endforeach;?></tbody></table></div></section>

<?php elseif($page==='laporan'):
 $data=rows($pdo,"SELECT p.no_pengajuan,p.tanggal_pengajuan,p.status,u.nama_lengkap,b.nama_bagian FROM tbl_pengajuan p JOIN tbl_pengguna u ON u.id_pengguna=p.id_pengguna JOIN tbl_bagian b ON b.id_bagian=p.id_bagian ORDER BY p.created_at DESC"); ?>
 <div class="page-head"><div><h1>Laporan Pengadaan</h1><p>Rekapitulasi pengajuan kebutuhan barang.</p></div><button class="primary-btn small-btn" onclick="window.print()">Cetak / Simpan PDF</button></div><section class="table-panel"><div class="table-wrap"><table><thead><tr><th>NOMOR</th><th>PEMOHON</th><th>BAGIAN</th><th>TANGGAL</th><th>STATUS</th></tr></thead><tbody><?php foreach($data as $r):?><tr><td><?=e($r['no_pengajuan'])?></td><td><?=e($r['nama_lengkap'])?></td><td><?=e($r['nama_bagian'])?></td><td><?=e($r['tanggal_pengajuan'])?></td><td><?=badge($r['status'])?></td></tr><?php endforeach;?></tbody></table></div></section>

<?php elseif($page==='profil'): ?>
 <div class="page-head"><h1>Profil Saya</h1></div><section class="settings-box"><h3><?=e(user()['nama_lengkap'])?></h3><p>Username: <?=e(user()['username'])?></p><p>Peran: <?=e($roleLabel[$role])?></p></section>
<?php endif; ?>
</div><footer class="footer">© 2026 Pengadilan Agama Palu · Sistem Pengadaan Barang</footer></main></div></body></html>
