<?php
// login.php — 관리자 로그인
declare(strict_types=1);

// 세션 안전 기본값 + 30일 로그인 유지
$SESSION_TTL = 60 * 60 * 24 * 30;
ini_set('session.cookie_httponly', '1');
ini_set('session.gc_maxlifetime', (string)$SESSION_TTL);
ini_set('session.cookie_lifetime', (string)$SESSION_TTL);
$host = $_SERVER['HTTP_HOST'] ?? '';
if (preg_match('/([^.]+\.[^.]+)$/', $host, $m)) {
  $baseDomain = $m[1];         // 예: gngg.net
} else {
  $baseDomain = $host;         // localhost 등
}
$cookieDomain = ($host === 'localhost') ? '' : ('.' . $baseDomain);
$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) {
  session_set_cookie_params([
    'lifetime' => $SESSION_TTL,
    'path'     => '/',         // 모든 경로에서 유효
    'domain'   => $cookieDomain, // .gngg.net → www/비-www 공유
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
}
session_start();

// ── 관리자 계정 설정 ──
$ADMIN_ID   = 'mhg1234';
$ADMIN_HASH = '$2y$12$UqHFUCc7Izes3r6QBBW4muUKIWjs4MF42nYGsjcr574tXRQwytNG6';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = trim((string)($_POST['id'] ?? ''));
  $pw = trim((string)($_POST['pw'] ?? ''));

  $id_ok = hash_equals($ADMIN_ID, $id);
  $hash_is_bcrypt = (strncmp($ADMIN_HASH, '$2y$', 4) === 0) || (strncmp($ADMIN_HASH, '$argon2', 7) === 0);
  $func_exists = function_exists('password_verify');

  $pass_ok = false;
  if ($hash_is_bcrypt) {
    $pass_ok = $func_exists ? password_verify($pw, $ADMIN_HASH) : false;
  } else {
    // (예외) bcrypt/argon2가 아니라면 md5 비교 허용 — 운영에선 bcrypt/argon2 권장
    $pass_ok = hash_equals(md5($pw), $ADMIN_HASH);
  }

  if ($id_ok && $pass_ok) {
    $_SESSION['is_admin'] = true;
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    header('Location: /list.php'); // 절대경로 권장
    exit;
  } else {
    $error = '아이디 또는 비밀번호가 올바르지 않습니다.';
  }
}
?>
<!doctype html><html lang="ko"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>관리자 로그인</title>
<style>
  body{font-family:Arial,sans-serif;background:#0f172a;color:#e5e7eb;margin:0}
  .wrap{max-width:420px;margin:40px auto;padding:24px;background:#111827;border:1px solid #1f2937;border-radius:12px}
  label{display:block;margin:10px 0 6px}
  input{width:100%;padding:10px;border-radius:8px;border:1px solid #334155;background:#0f172a;color:#e5e7eb}
  .btn{margin-top:14px;padding:10px 14px;border-radius:8px;border:1px solid #334155;background:#1e293b;color:#e5e7eb;cursor:pointer}
  .err{color:#fca5a5;margin:8px 0} a{color:#93c5fd}
</style></head><body>
<div class="wrap">
  <h2 style="margin-top:0">관리자 로그인</h2>
  <?php if ($error): ?><div class="err"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <label>아이디</label><input type="text" name="id" required>
    <label>비밀번호</label><input type="password" name="pw" required>
    <button class="btn" type="submit">로그인</button>
  </form>
  <p style="margin-top:12px"><a href="/index.php">← 홈</a></p>
</div>
<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body></html>
