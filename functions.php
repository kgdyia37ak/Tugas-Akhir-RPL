<?php
function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function user(): ?array { return $_SESSION['user'] ?? null; }
function role(): string { return user()['role'] ?? ''; }
function require_login(): void { if (!user()) { header('Location: index.php'); exit; } }
function allow_roles(array $roles): void {
    require_login();
    if (!in_array(role(), $roles, true)) { http_response_code(403); exit('Akses ditolak.'); }
}
function csrf_field(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">';
}
function verify_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Token keamanan tidak valid. Muat ulang halaman.'); }
}
function flash(string $m, string $t='success'): void { $_SESSION['flash']=['message'=>$m,'type'=>$t]; }
function redirect(string $p='dashboard'): never { header('Location: index.php?page='.urlencode($p)); exit; }
function rows(PDO $pdo,string $sql,array $params=[]): array { $s=$pdo->prepare($sql); $s->execute($params); return $s->fetchAll(); }
function row(PDO $pdo,string $sql,array $params=[]): ?array { $s=$pdo->prepare($sql); $s->execute($params); return $s->fetch() ?: null; }
function badge(string $s): string { return '<span class="status">'.e(ucfirst($s)).'</span>'; }
function rupiah($n): string { return 'Rp '.number_format((float)$n,0,',','.'); }
function log_activity(PDO $pdo,string $activity,string $table,int $id,string $note=''): void {
    $s=$pdo->prepare('INSERT INTO tbl_riwayat (id_pengguna,aktivitas,tabel_terkait,id_terkait,keterangan) VALUES (?,?,?,?,?)');
    $s->execute([user()['id_pengguna'],$activity,$table,$id,$note]);
}
function nomor(string $prefix): string { return $prefix.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(2))); }
