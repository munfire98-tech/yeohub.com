<?php
/* =============================================================
   impersonate.php — 관리자가 회원 화면으로 들어가기 (대리 보기)
   ─────────────────────────────────────────────────────────────
   관리자가 회원과 똑같은 화면을 보면서 바로 고칠 수 있습니다.
   관리자용 편집 화면을 따로 만들 필요가 없습니다.

     /impersonate.php?uid=회원아이디   ← 들어가기
     /impersonate.php?stop=1           ← 관리자로 돌아오기

   ★ 안전장치 ★
     · 관리자만 시작할 수 있습니다
     · 들어가 있는 동안 관리자 권한은 잠시 내려놓습니다
       (관리자 화면이 회원 계정으로 열리는 일을 막습니다)
     · 화면 위에 항상 주황색 띠가 붙어 누구 계정인지 보입니다
     · 누가 언제 누구 계정에 들어갔는지 기록에 남습니다
   ============================================================= */
declare(strict_types=1);

if (!ini_get('date.timezone')) { date_default_timezone_set('Asia/Seoul'); }
ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) { session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']); }
session_start();

function imp_is_admin(): bool {
  return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
function imp_bye(string $msg): void {
  http_response_code(403);
  echo '<!doctype html><meta charset="utf-8">'
     . '<div style="max-width:480px;margin:80px auto;padding:0 20px;'
     . 'font-family:system-ui,\'Apple SD Gothic Neo\',sans-serif;line-height:1.8">'
     . '<h2 style="font-size:19px">' . htmlspecialchars($msg, ENT_QUOTES) . '</h2>'
     . '<p><a href="/admin_members.php" style="color:#1d4ed8">← 회원 관리로</a></p></div>';
  exit;
}

/* 누가 언제 들어갔는지 남깁니다 */
function imp_log(string $action, string $uid, string $adminName): void {
  $file = __DIR__ . '/data/impersonate_log.json';
  $dir  = dirname($file);
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  $rows = [];
  if (is_file($file)) {
    $a = json_decode((string)@file_get_contents($file), true);
    if (is_array($a)) $rows = $a;
  }
  $rows[] = [
    'at'     => date('Y-m-d H:i:s'),
    'action' => $action,          // enter | leave
    'uid'    => $uid,
    'admin'  => $adminName,
    'ip'     => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
  ];
  if (count($rows) > 3000) $rows = array_slice($rows, -3000);
  @file_put_contents($file, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

/* ══════════════════════════════════════════════════════════
   관리자로 돌아오기
   ══════════════════════════════════════════════════════════ */
if (isset($_GET['stop'])) {
  $imp = $_SESSION['_imp'] ?? null;
  if (!$imp || empty($imp['admin'])) {
    /* 대리 보기 중이 아니면 그냥 관리자 화면으로 */
    header('Location: /admin_members.php'); exit;
  }
  $uid   = (string)($imp['uid'] ?? '');
  $aname = (string)($imp['admin']['nickname'] ?? '관리자');
  $back  = (string)($imp['back'] ?? '/admin_members.php');

  $_SESSION = $imp['admin'];          // 원래 관리자 세션으로 되돌립니다
  session_regenerate_id(true);
  imp_log('leave', $uid, $aname);

  /* 돌아갈 곳은 우리 사이트 안이어야 합니다 */
  if ($back === '' || $back[0] !== '/' || strpos($back, '//') === 0) $back = '/admin_members.php';
  header('Location: ' . $back);
  exit;
}

/* ══════════════════════════════════════════════════════════
   회원 화면으로 들어가기
   ══════════════════════════════════════════════════════════ */
if (!imp_is_admin()) imp_bye('관리자만 쓸 수 있습니다.');

$uid = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($_GET['uid'] ?? ''));
if ($uid === '') imp_bye('어느 회원인지 지정해 주세요.');

$members = [];
$mf = __DIR__ . '/data/members.json';
if (is_file($mf)) {
  $a = json_decode((string)@file_get_contents($mf), true);
  if (is_array($a)) $members = $a;
}
if (!isset($members[$uid])) imp_bye('회원 목록에 없는 아이디입니다. 탈퇴했을 수 있습니다.');

$m       = $members[$uid];
$adminNm = (string)($_SESSION['nickname'] ?? '관리자');

/* 돌아갈 곳 기억 (회원 관리 목록 / 서류 화면) */
$back = (string)($_SERVER['HTTP_REFERER'] ?? '');
if ($back !== '') {
  $path = parse_url($back, PHP_URL_PATH);
  $q    = parse_url($back, PHP_URL_QUERY);
  $back = is_string($path) ? $path . ($q ? '?' . $q : '') : '';
}
if ($back === '' || $back[0] !== '/') $back = '/admin_members.php';

/* 관리자 세션을 통째로 보관해 두고, 회원 신분으로 바꿉니다 */
$keep = $_SESSION;
unset($keep['_imp']);               // 중첩 방지

session_regenerate_id(true);
$_SESSION = [
  '_imp' => [
    'admin' => $keep,
    'uid'   => $uid,
    'nick'  => (string)($m['nickname'] ?? $uid),
    'at'    => date('Y-m-d H:i:s'),
    'back'  => $back,
  ],
  /* 아래부터는 회원과 똑같은 상태 — 관리자 권한(is_admin, ID_OK)은 넣지 않습니다 */
  'is_user'   => 1,
  'role'      => (string)($m['role'] ?? 'building'),
  'member_id' => $uid,
  'nickname'  => (string)($m['nickname'] ?? $uid),
];
if (!empty($m['kakao_id'])) $_SESSION['kakao_id'] = (string)$m['kakao_id'];

imp_log('enter', $uid, $adminNm);

/* 어느 화면으로 갈지 — ?to= 로 지정할 수 있습니다 (우리 사이트 안쪽만 허용) */
$to = (string)($_GET['to'] ?? '');
if ($to === '' || strpos($to, '//') === 0 || !preg_match('#^/[A-Za-z0-9_]+\.php#', $to)) {
  $to = ($_SESSION['role'] === 'building') ? '/building_manager.php' : '/clients_mini.php';
}
header('Location: ' . $to);
exit;
