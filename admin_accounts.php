<?php
/* =============================================================
   admin_accounts.php — 관리자 계정 추가·삭제
   ─────────────────────────────────────────────────────────────
   로그인한 관리자만 접근할 수 있습니다. 여기서 만든 계정은
   admin_login.php 에서 아이디·비밀번호로 로그인할 수 있습니다.

   최초 비상용 계정(mhg1234)은 이 화면에 안 보이고 삭제도 안 됩니다
   (index.php 에 코드로 고정된 계정이라, 잠길 위험이 없습니다).
   ============================================================= */
declare(strict_types=1);
ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) { session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']); }
session_start();

$isAdmin = (!empty($_SESSION['is_admin'])) || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
if (!$isAdmin) { header('Location: /admin_login.php'); exit; }

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function aa_file(): string {
  $dir = __DIR__ . '/data';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  return $dir . '/admins.json';
}
function aa_read(): array {
  $f = aa_file();
  if (!is_file($f)) return [];
  $r = @file_get_contents($f);
  if ($r === false || trim($r) === '') return [];
  $a = json_decode($r, true);
  return is_array($a) ? $a : [];
}
function aa_write(array $list): bool {
  $f = aa_file();
  $tmp = $f . '.tmp';
  if (file_put_contents($tmp, json_encode($list, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
  return @rename($tmp, $f);
}
function aa_uuid(): string {
  $d = random_bytes(16);
  $d[6] = chr((ord($d[6]) & 0x0f) | 0x40); $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

const ADMIN_USER_FOR_CHECK = 'mhg1234';   // 비상용 계정과 아이디 겹침 방지용 dup 체크

$flash = ''; $flashType = 'ok';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $act = $_POST['act'] ?? '';
  if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) {
    $flash = '세션이 만료되었습니다. 새로고침 후 다시 시도해 주세요.'; $flashType = 'err';
  } elseif ($act === 'add') {
    $user = trim((string)($_POST['username'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_]{4,20}$/', $user)) {
      $flash = '아이디는 영문/숫자/밑줄 4~20자여야 합니다.'; $flashType = 'err';
    } elseif (mb_strlen($pass) < 8) {
      $flash = '비밀번호는 8자 이상이어야 합니다.'; $flashType = 'err';
    } else {
      $list = aa_read();
      $dup = ($user === ADMIN_USER_FOR_CHECK);
      foreach ($list as $a) if (($a['username'] ?? '') === $user) $dup = true;
      if ($dup) {
        $flash = '이미 있는 아이디입니다.'; $flashType = 'err';
      } else {
        $list[] = ['id' => aa_uuid(), 'username' => $user, 'hash' => password_hash($pass, PASSWORD_DEFAULT),
                   'created_at' => date('Y-m-d H:i:s')];
        aa_write($list);
        $flash = '관리자 계정 "' . $user . '" 을(를) 추가했습니다.'; $flashType = 'ok';
      }
    }
  } elseif ($act === 'delete') {
    $id = (string)($_POST['id'] ?? '');
    $list = array_values(array_filter(aa_read(), fn($a) => ($a['id'] ?? '') !== $id));
    aa_write($list);
    $flash = '삭제되었습니다.'; $flashType = 'ok';
  }
}
$accounts = aa_read();
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>관리자 계정 관리</title>
<style>
  :root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--bd2:#d4dbe6;--fg:#1a2436;--mut:#7a8699;--mut2:#56627a;
    --brand:#2563eb;--brand2:#1d4ed8;--danger:#dc2626;--danger-soft:#fdeceb;--ok:#16a34a;--ok-soft:#eefaf1}
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:var(--bg);color:var(--fg);font-family:Inter,ui-sans-serif,system-ui,"Apple SD Gothic Neo",sans-serif;
    padding:0 0 80px}
  .nav{background:#fff;border-bottom:1px solid var(--bd);padding:14px 20px;display:flex;align-items:center;gap:12px}
  .nav b{font-weight:800;font-size:15px}
  .nav a{margin-left:auto;font-size:12.5px;color:var(--mut);text-decoration:none}
  .wrap{max-width:640px;margin:26px auto;padding:0 18px}
  h1{font-size:20px;font-weight:800;margin-bottom:5px}
  .lead{color:var(--mut);font-size:13px;margin-bottom:22px;line-height:1.6}
  .card{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:20px;margin-bottom:16px}
  .card h2{font-size:14px;font-weight:800;margin-bottom:14px}
  .flash{border-radius:9px;padding:11px 14px;font-size:13px;font-weight:600;margin-bottom:16px}
  .flash.ok{background:var(--ok-soft);color:var(--ok)}
  .flash.err{background:var(--danger-soft);color:var(--danger)}
  .row{display:flex;gap:10px;flex-wrap:wrap}
  .fld{flex:1;min-width:150px}
  .fld label{display:block;font-size:11.5px;font-weight:700;color:var(--mut2);margin-bottom:6px}
  .inp{width:100%;padding:11px 13px;border-radius:9px;border:1px solid var(--bd2);background:#f8fafc;
    color:var(--fg);font-size:13.5px;outline:none;font-family:inherit}
  .inp:focus{border-color:var(--brand)}
  .btn{border:0;border-radius:9px;padding:11px 18px;font-size:13.5px;font-weight:700;cursor:pointer;
    font-family:inherit;background:var(--brand);color:#fff;margin-top:8px}
  .btn:hover{background:var(--brand2)}
  .btn--danger{background:#fff;color:var(--danger);border:1px solid var(--danger-soft)}
  .btn--danger:hover{background:var(--danger-soft)}
  table{width:100%;border-collapse:collapse;font-size:13px}
  th,td{border-bottom:1px solid var(--bd);padding:10px 8px;text-align:left}
  th{color:var(--mut);font-size:11.5px;font-weight:700}
  .empty{color:var(--mut);font-size:13px;padding:16px 0;text-align:center}
  .note{font-size:11.5px;color:var(--mut);line-height:1.7;margin-top:10px}
</style>
</head>
<body>
<div class="nav"><b>관리자 계정 관리</b><a href="/building_manager.php">← 관리자 화면으로</a></div>

<div class="wrap">
  <h1>관리자 계정</h1>
  <div class="lead">여기서 추가한 계정은 <code>/admin_login.php</code> 에서 로그인할 수 있습니다.</div>

  <?php if ($flash): ?><div class="flash <?=h($flashType)?>"><?=h($flash)?></div><?php endif; ?>

  <div class="card">
    <h2>새 관리자 추가</h2>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
      <input type="hidden" name="act" value="add">
      <div class="row">
        <div class="fld">
          <label>아이디</label>
          <input class="inp" name="username" placeholder="영문/숫자/밑줄 4~20자" required
            pattern="[A-Za-z0-9_]{4,20}" autocomplete="off">
        </div>
        <div class="fld">
          <label>비밀번호</label>
          <input class="inp" type="password" name="password" placeholder="8자 이상" required
            minlength="8" autocomplete="new-password">
        </div>
      </div>
      <button class="btn" type="submit">계정 추가</button>
    </form>
  </div>

  <div class="card">
    <h2>현재 관리자 계정 (<?=count($accounts)?>개)</h2>
    <?php if (!$accounts): ?>
      <div class="empty">추가된 계정이 없습니다. 위에서 만들어 보세요.</div>
    <?php else: ?>
      <table>
        <tr><th>아이디</th><th>추가일</th><th></th></tr>
        <?php foreach ($accounts as $a): ?>
          <tr>
            <td><?=h($a['username'] ?? '')?></td>
            <td><?=h($a['created_at'] ?? '')?></td>
            <td>
              <form method="post" onsubmit="return confirm('&quot;<?=h($a['username'] ?? '')?>&quot; 계정을 삭제할까요?')">
                <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
                <input type="hidden" name="act" value="delete">
                <input type="hidden" name="id" value="<?=h($a['id'] ?? '')?>">
                <button class="btn btn--danger" type="submit" style="margin-top:0;padding:6px 12px">삭제</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
    <div class="note">비상용 최초 계정(mhg1234)은 코드에 고정되어 있어 여기 표시되지 않고 삭제할 수도 없습니다.</div>
  </div>
</div>
</body>
</html>
