<?php
/**
 * admin_members.php — 회원 관리 (관리자 전용)
 *   · 전체 회원 목록 / 검색 / 정렬
 *   · 여러 명 선택 후 일괄 삭제 (개인 데이터 폴더까지 정리)
 *   · 삭제 전 자동 백업
 */
declare(strict_types=1);

/* ── 세션 설정: login.php와 반드시 동일해야 관리자 로그인이 인식됨 ──
   login.php가 쿠키 도메인을 .tworix.com 으로 잡기 때문에,
   여기서도 같은 설정을 써야 같은 세션을 보게 된다. */
$SESSION_TTL = 60 * 60 * 24 * 30;
ini_set('session.cookie_httponly', '1');
ini_set('session.gc_maxlifetime', (string)$SESSION_TTL);
ini_set('session.cookie_lifetime', (string)$SESSION_TTL);
$host = $_SERVER['HTTP_HOST'] ?? '';
if (preg_match('/([^.]+\.[^.]+)$/', $host, $m)) {
  $baseDomain = $m[1];
} else {
  $baseDomain = $host;
}
$cookieDomain = ($host === 'localhost') ? '' : ('.' . $baseDomain);
$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if (PHP_VERSION_ID >= 70300) {
  session_set_cookie_params([
    'lifetime' => $SESSION_TTL,
    'path'     => '/',
    'domain'   => $cookieDomain,
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
}
session_start();

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

/* mbstring이 없는 서버에서도 동작하도록 폴백 */
function lc(string $s): string {
  return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}
function contains(string $hay, string $needle): bool {
  if ($needle === '') return true;
  return function_exists('mb_strpos')
    ? mb_strpos($hay, $needle, 0, 'UTF-8') !== false
    : strpos($hay, $needle) !== false;
}

/* ── 관리자만 접근 ── */
$isAdmin = (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
        || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
if (!$isAdmin) {
  /* 대리 보기 중이라 관리자 권한을 잠시 내려놓은 상태입니다 */
  if (!empty($_SESSION['_imp'])) {
    $nk = h((string)($_SESSION['_imp']['nick'] ?? '회원'));
    header('Content-Type: text/html; charset=utf-8');
    echo "<div style=\"font-family:system-ui,'Apple SD Gothic Neo',sans-serif;max-width:520px;"
       . "margin:70px auto;padding:0 20px;line-height:1.8\">"
       . "<h2 style='font-size:20px'>지금 {$nk}님 계정으로 보는 중입니다</h2>"
       . "<p style='color:#56627a'>관리자 화면을 보려면 먼저 관리자로 돌아가세요.</p>"
       . "<p><a href='/impersonate.php?stop=1' style='display:inline-block;margin-top:8px;"
       . "padding:11px 20px;background:#b45309;color:#fff;border-radius:9px;"
       . "text-decoration:none;font-weight:700'>관리자로 돌아가기 →</a></p>"
       . "<p style='margin-top:14px'><a href='/building_manager.php' style='color:#1d4ed8;font-size:14px'>"
       . "← {$nk}님 화면으로</a></p></div>";
    exit;
  }
  header('Content-Type: text/html; charset=utf-8');
  echo "<div style=\"font-family:system-ui,'Malgun Gothic',sans-serif;max-width:560px;margin:60px auto;padding:0 20px;line-height:1.8\">";
  echo "<h2>관리자 권한이 필요합니다</h2>";
  echo "<p>관리자로 로그인한 뒤 다시 시도하세요.</p>";
  echo "<p><a href='/login.php' style='display:inline-block;margin-top:8px;padding:10px 18px;background:#2563eb;color:#fff;border-radius:8px;text-decoration:none;font-weight:600'>관리자 로그인 →</a></p>";
  echo "<hr style='margin:26px 0;border:0;border-top:1px solid #e5e7eb'>";
  echo "<p style='font-size:13px;color:#6b7280'>이미 로그인했는데 이 화면이 보인다면, 아래 상태를 확인하세요.</p>";
  echo "<pre style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;font-size:12.5px'>";
  echo "session_id : " . h(session_id() ?: '(없음)') . "\n";
  echo "is_admin   : " . (isset($_SESSION['is_admin']) ? var_export($_SESSION['is_admin'], true) : '(미설정)') . "\n";
  echo "ID_OK      : " . (isset($_SESSION['ID_OK']) ? var_export($_SESSION['ID_OK'], true) : '(미설정)') . "\n";
  echo "세션 키    : " . h(implode(', ', array_keys($_SESSION)) ?: '(비어 있음)') . "\n";
  echo "쿠키 도메인: " . h($cookieDomain) . "\n";
  echo "</pre>";
  echo "<p style='font-size:13px;color:#6b7280'>모든 값이 비어 있다면 로그인 세션이 전달되지 않은 것입니다.</p>";
  echo "</div>";
  exit;
}

$DATA      = __DIR__ . '/data';
$FILE      = $DATA . '/members.json';
$BACKUPDIR = $DATA . '/_backups';

/* 피난 시뮬레이션 배정 */
require_once __DIR__ . '/evac_common.php';
$evacAssign = evac_load_assign();   // { uid: [modelId, ...] }
$evacRequests = evac_load_requests(); // { uid: {at, building_name, address, manager_name, manager_phone} }

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

function load_members(string $f): array {
  if (!file_exists($f)) return [];
  $r = @file_get_contents($f);
  if ($r === false || trim($r) === '') return [];
  $a = json_decode($r, true);
  return is_array($a) ? $a : [];
}
function save_members(string $f, array $arr): bool {
  $tmp = $f . '.tmp';
  if (file_put_contents($tmp, json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
  return @rename($tmp, $f);
}
/* 폴더 통째로 삭제 */
function rrmdir(string $dir): void {
  if (!is_dir($dir)) return;
  foreach (scandir($dir) ?: [] as $f) {
    if ($f === '.' || $f === '..') continue;
    $p = $dir . '/' . $f;
    is_dir($p) ? rrmdir($p) : @unlink($p);
  }
  @rmdir($dir);
}
/* 회원 1명의 개인 데이터 폴더 목록
 *
 * ⚠ withdraw.php 의 wd_dirs() 와 반드시 같게 유지할 것.
 *   폴더 이름 규칙이 서식마다 달라서, 아이디를 가공한 이름도 함께 지운다.
 *   하나라도 남으면 같은 아이디로 재가입했을 때 예전 기록이 그대로 보인다. */
function member_dirs(string $DATA, string $uid): array {
  $keys = array_values(array_unique(array_filter([
    $uid,
    preg_replace('/[^A-Za-z0-9_\-]/', '_', $uid),   // app_user_key() 규칙 (jawi/building)
    preg_replace('/[^A-Za-z0-9_]/',   '_', $uid),   // work_log.php 규칙
  ])));

  $out = [];
  foreach ($keys as $k) {
    $out[] = $DATA . '/users/m_'   . $k;   // 개인 데이터
    $out[] = $DATA . '/building/'  . $k;   // 건물 기본정보
    $out[] = $DATA . '/worklog/'   . $k;   // 업무일지
    $out[] = $DATA . '/fireplan/'  . $k;   // 소방계획서·자위소방대 편성표
    $out[] = $DATA . '/train/'     . $k;   // 소방훈련·교육 기록부  ← 빠져 있던 폴더
    $out[] = $DATA . '/jawi/'      . $k;   // 자위소방대 교육·훈련  ← 빠져 있던 폴더
  }
  return array_values(array_unique($out));
}

/* 폴더 밖에 흩어져 있는 회원 데이터도 함께 지운다.
   (배정된 피난 시뮬레이션, 배정 요청 기록) */
function member_purge_extras(string $DATA, string $uid): int {
  $n = 0;

  /* 1) 배정 목록에서 이 회원을 빼고, 다른 회원이 안 쓰는 모델 파일은 삭제 */
  $af = $DATA . '/evac_assign.json';
  if (is_file($af)) {
    $a = json_decode((string)@file_get_contents($af), true);
    if (is_array($a) && isset($a[$uid])) {
      $mine = (array)$a[$uid];
      unset($a[$uid]);

      $stillUsed = [];
      foreach ($a as $list) foreach ((array)$list as $mid) $stillUsed[$mid] = true;

      foreach ($mine as $mid) {
        if (!empty($stillUsed[$mid])) continue;
        $mf = $DATA . '/evac_models/' . preg_replace('/[^A-Za-z0-9_-]/', '', (string)$mid) . '.json';
        if (is_file($mf)) { @unlink($mf); $n++; }
      }
      @file_put_contents($af, json_encode($a, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
      $n++;
    }
  }

  /* 2) 검토요청 기록 — 탈퇴한 회원의 "확인완료" 가 계속 남아 있던 문제 */
  $lf = $DATA . '/assist_log.json';
  if (is_file($lf)) {
    $rows = json_decode((string)@file_get_contents($lf), true);
    if (is_array($rows)) {
      $keep = array_values(array_filter($rows, fn($r) => (string)($r['uid'] ?? '') !== $uid));
      if (count($keep) !== count($rows)) {
        @file_put_contents($lf, json_encode($keep, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        $n++;
      }
    }
  }

  /* 3) 배정 요청 기록 */
  $before = evac_load_requests();
  if (isset($before[$uid]) && evac_remove_request($uid)) $n++;

  return $n;
}

/* 회원 목록에 없는 데이터 폴더 찾기 (탈퇴자가 남긴 것) */
function orphan_dirs(string $DATA, array $members): array {
  $out = [];
  foreach (['building' => '건물 기본정보', 'worklog' => '업무일지',
            'fireplan' => '소방계획서·자위소방대'] as $sub => $label) {
    $base = $DATA . '/' . $sub;
    if (!is_dir($base)) continue;
    foreach (scandir($base) ?: [] as $e) {
      if ($e === '.' || $e === '..' || !is_dir($base . '/' . $e)) continue;
      if (isset($members[$e])) continue;                 // 현재 회원 것
      $files = glob($base . '/' . $e . '/*') ?: [];
      $out[] = [
        'path'  => $base . '/' . $e,
        'rel'   => 'data/' . $sub . '/' . $e,
        'label' => $label,
        'uid'   => $e,
        'files' => count($files),
        'guest' => ($e === 'kakao_guest' || $e === 'guest'),
      ];
    }
  }
  return $out;
}

$msg = ''; $msgType = '';

/* ── 삭제 처리 ── */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['act'] ?? '') === 'delete') {
  if (!hash_equals($CSRF, $_POST['csrf'] ?? '')) {
    $msg = '잘못된 요청입니다.'; $msgType = 'err';
  } else {
    $targets = $_POST['uids'] ?? [];
    $targets = is_array($targets) ? array_map('strval', $targets) : [];
    $withData = !empty($_POST['with_data']);

    if (!$targets) {
      $msg = '삭제할 회원을 선택하세요.'; $msgType = 'err';
    } else {
      $members = load_members($FILE);

      /* 백업 먼저 */
      if (!is_dir($BACKUPDIR)) @mkdir($BACKUPDIR, 0775, true);
      $bk = $BACKUPDIR . '/members_' . date('Ymd_His') . '.json';
      @copy($FILE, $bk);

      $deleted = []; $dirCnt = 0;
      foreach ($targets as $uid) {
        if (!isset($members[$uid])) continue;
        unset($members[$uid]);
        $deleted[] = $uid;
        /* 계정과 묶인 기록(시뮬 배정·배정요청·검토요청)은
           체크와 상관없이 항상 지웁니다.
           남겨두면 같은 아이디로 다시 가입한 사람에게 그대로 넘어갑니다. */
        member_purge_extras($DATA, $uid);
        if ($withData) {
          foreach (member_dirs($DATA, $uid) as $d) {
            if (is_dir($d)) { rrmdir($d); $dirCnt++; }
          }
        }
      }

      if ($deleted && save_members($FILE, $members)) {
        $msg = count($deleted) . '명 삭제 완료 (' . implode(', ', $deleted) . ')'
             . ($withData && $dirCnt ? " · 데이터 폴더 {$dirCnt}개 정리" : '')
             . ' · 백업: ' . basename($bk);
        $msgType = 'ok';
      } else {
        $msg = '삭제에 실패했습니다.'; $msgType = 'err';
      }
    }
  }
}

/* ── 탈퇴자가 남긴 데이터 폴더 정리 ── */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['act'] ?? '') === 'purge_orphans') {
  if (!hash_equals($CSRF, $_POST['csrf'] ?? '')) {
    $msg = '잘못된 요청입니다.'; $msgType = 'err';
  } else {
    $picked = $_POST['paths'] ?? [];
    $picked = is_array($picked) ? array_map('strval', $picked) : [];
    $all    = orphan_dirs($DATA, load_members($FILE));
    $valid  = array_column($all, 'path');   // 목록에 있는 경로만 지운다
    $n = 0;
    foreach ($picked as $p) {
      if (!in_array($p, $valid, true)) continue;   // 경로 조작 방지
      if (is_dir($p)) { rrmdir($p); $n++; }
    }
    $msg = $n ? "남아 있던 데이터 폴더 {$n}개를 정리했습니다." : '정리할 폴더를 선택하세요.';
    $msgType = $n ? 'ok' : 'err';
  }
}

/* ── 목록 조회 ── */
$members = load_members($FILE);
$orphans = orphan_dirs($DATA, $members);
$reviewCount = [];
$reviewFile = $DATA . '/assist_log.json';
if (is_file($reviewFile)) {
  $reviewRows = json_decode((string)@file_get_contents($reviewFile), true);
  if (is_array($reviewRows)) {
    foreach ($reviewRows as $reviewRow) {
      if (($reviewRow['kind'] ?? '') !== 'review' || ($reviewRow['status'] ?? 'pending') === 'resolved') continue;
      $reviewUid = (string)($reviewRow['uid'] ?? '');
      if ($reviewUid !== '') $reviewCount[$reviewUid] = ($reviewCount[$reviewUid] ?? 0) + 1;
    }
  }
}
$q       = trim((string)($_GET['q'] ?? ''));
$fRole   = (string)($_GET['role'] ?? '');

$rows = [];
foreach ($members as $uid => $m) {
  $uid = (string)$uid;
  $email = (string)($m['email'] ?? '');
  $nick  = (string)($m['nickname'] ?? '');
  $role  = (string)($m['role'] ?? 'agency');

  if ($q !== '') {
    $hay = lc($uid . ' ' . $email . ' ' . $nick);
    if (!contains($hay, lc($q))) continue;
  }
  if ($fRole !== '' && $role !== $fRole) continue;

  /* 개인 데이터 폴더가 있는지 */
  $hasData = false;
  foreach (member_dirs($DATA, $uid) as $d) if (is_dir($d)) { $hasData = true; break; }

  $rows[] = [
    'uid'      => $uid,
    'nickname' => $nick,
    'role'     => $role,
    'email'    => $email,
    'email_ok' => !empty($m['email_ok'] ?? $m['verified'] ?? false),
    'phone'    => (string)($m['phone'] ?? ''),
    'created'  => (string)($m['created'] ?? ''),
    'last'     => (string)($m['last_login'] ?? ''),
    'hasData'  => $hasData,
    'reviewCount' => (int)($reviewCount[$uid] ?? 0),
    'evacRequest' => is_array($evacRequests[$uid] ?? null) ? $evacRequests[$uid] : [],
  ];
}
/* 시뮬레이션 요청 회원을 먼저, 그 다음 최근 가입순 */
usort($rows, function($a, $b) {
  $requestOrder = (int)!empty($b['evacRequest']) <=> (int)!empty($a['evacRequest']);
  return $requestOrder !== 0 ? $requestOrder : strcmp($b['created'], $a['created']);
});

/* 이메일 중복 집계 (같은 이메일을 여러 계정이 쓰는 경우 표시) */
$emailCount = [];
foreach ($members as $m) {
  $e = lc(trim((string)($m['email'] ?? '')));
  if ($e !== '') $emailCount[$e] = ($emailCount[$e] ?? 0) + 1;
}

$total = count($members);
$evacRequestCount = 0;
foreach ($evacRequests as $requestUid => $request) {
  if (isset($members[$requestUid]) && is_array($request)) $evacRequestCount++;
}
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>회원 관리 — TWORIX</title>
<style>
:root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--bd2:#d4dbe6;--fg:#1a2436;
  --mut:#7a8699;--mut2:#56627a;--brand:#2563eb;--brand2:#1d4ed8;--danger:#dc2626}
*{box-sizing:border-box;margin:0;padding:0}
html,body{background:var(--bg);color:var(--fg);
  font-family:Inter,system-ui,"Apple SD Gothic Neo",sans-serif;line-height:1.6}
a{text-decoration:none}
.nav{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.92);backdrop-filter:blur(10px);
  border-bottom:1px solid var(--bd)}
.nav__in{max-width:1180px;margin:0 auto;padding:0 22px;height:56px;
  display:flex;align-items:center;justify-content:space-between;gap:14px}
.brand{font-weight:800;font-size:21px;letter-spacing:.5px;color:var(--fg)}
.wrap{max-width:1180px;margin:0 auto;padding:26px 22px 70px}
h1{font-size:24px;font-weight:700;margin-bottom:4px}
.sub{color:var(--mut2);font-size:14px;margin-bottom:20px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 15px;border-radius:9px;
  border:1px solid var(--bd2);background:#fff;color:var(--fg);font-size:13px;font-weight:600;
  cursor:pointer;font-family:inherit}
.btn:hover{border-color:var(--brand);color:var(--brand2)}
.btn--danger{background:var(--danger);border-color:var(--danger);color:#fff}
.btn--danger:hover{background:#b91c1c;border-color:#b91c1c;color:#fff}
.btn--danger:disabled{background:#e5e7eb;border-color:#e5e7eb;color:#9ca3af;cursor:not-allowed}
.msg{padding:11px 14px;border-radius:9px;font-size:13.5px;margin-bottom:16px;line-height:1.6}
.msg.ok{background:#ecfdf5;color:#047857;border:1px solid #a7f3d0}
.msg.err{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}
.card{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:18px}
.bar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:14px}
.bar input[type=search]{flex:1;min-width:200px;padding:9px 12px;border:1px solid var(--bd2);
  border-radius:9px;font-size:14px;font-family:inherit}
.bar select{padding:9px 12px;border:1px solid var(--bd2);border-radius:9px;font-size:13.5px;font-family:inherit}
.cnt{font-size:13px;color:var(--mut2);margin-left:auto}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{padding:10px 8px;border-bottom:1px solid var(--bd);text-align:left;vertical-align:middle}
th{background:#f7f9fc;font-weight:700;font-size:12px;color:var(--mut2);white-space:nowrap}
tr:hover td{background:#fafbfd}
td.uid{font-weight:700}
.tag{display:inline-block;font-size:11px;border-radius:20px;padding:2px 9px;font-weight:700;white-space:nowrap}
.tag--bld{background:#eef2ff;color:var(--brand2)}
.tag--agc{background:#f0fdf4;color:#15803d}
.tag--dup{background:#fef3c7;color:#b45309}
.tag--data{background:#f1f5f9;color:#475569}
.tag--review{background:#fff7ed;color:#c2410c;border:1px solid #fdba74}
.tag--evac{background:#eff6ff;color:#1d4ed8;border:1px solid #93c5fd}
.ok-i{color:#059669;font-weight:700}
.no-i{color:#cbd5e1}
.dim{color:var(--mut);font-size:12px}
.actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;
  margin-top:16px;padding-top:14px;border-top:1px solid var(--bd)}
.chk-lb{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--mut2);cursor:pointer}
.warn{font-size:12px;color:#b45309;background:#fff7ed;border:1px solid #fed7aa;
  border-radius:8px;padding:8px 11px;margin-top:10px}
.empty{text-align:center;color:var(--mut2);padding:36px 20px}
@media(max-width:760px){
  .hide-sm{display:none}
  .wrap{padding:18px 14px 60px}
}
/* ── 시뮬레이션 배정 모달 ── */
.btn--evac{padding:5px 10px;font-size:12px;white-space:nowrap}
.btn--evac-request{background:#2563eb;color:#fff;border-color:#2563eb}
.btn--evac-request:hover{background:#1d4ed8;color:#fff}
.evac-cnt{color:var(--brand2)}
.btn--evac-request .evac-cnt{color:#fff}
.evac-modal{position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:200;
  display:flex;align-items:center;justify-content:center;padding:20px}
.evac-modal[hidden]{display:none}
.evac-box{background:#fff;border-radius:14px;width:100%;max-width:560px;
  max-height:86vh;overflow:auto;padding:20px 22px}
.evac-box h2{font-size:17px;margin-bottom:2px}
.evac-box .sub{margin-bottom:14px}
.evac-request-info{border:1px solid #bfdbfe;background:#eff6ff;border-radius:9px;padding:12px 13px;margin:12px 0}
.evac-request-info[hidden]{display:none}
.evac-request-info__title{font-size:12px;font-weight:800;color:#1d4ed8;margin-bottom:7px}
.evac-request-info__grid{display:grid;grid-template-columns:92px 1fr;gap:5px 9px;font-size:12.5px;line-height:1.5}
.evac-request-info__grid dt{color:var(--mut2);font-weight:700}
.evac-request-info__grid dd{margin:0;color:var(--fg);word-break:break-word}
.evac-sec{font-size:12px;font-weight:700;color:var(--mut2);margin:14px 0 6px;
  padding-top:12px;border-top:1px solid var(--bd)}
.evac-row{display:flex;align-items:center;gap:10px;padding:9px 11px;border:1px solid var(--bd);
  border-radius:9px;margin-bottom:7px;font-size:13px}
.evac-row .nm{font-weight:700}
.evac-row .mt{color:var(--mut);font-size:11.5px}
.evac-row .sp{flex:1}
.evac-row button{padding:5px 11px;font-size:12px;border-radius:7px;border:1px solid var(--bd2);
  background:#fff;cursor:pointer;font-family:inherit;font-weight:600;white-space:nowrap}
.evac-row button.asg{background:var(--brand);border-color:var(--brand);color:#fff}
.evac-row button.asg:hover{background:var(--brand2)}
.evac-row button.asg:disabled{background:#e8edf5;border-color:#e8edf5;color:#8a94a6;cursor:default}
.evac-row button.upl{background:#fff;border-color:var(--brand);color:var(--brand)}
.evac-row button.upl:hover{background:#eff6ff}
.evac-sec__hint{font-weight:600;color:var(--mut);font-size:11.5px}
.evac-row button.rm{color:var(--danger)}
.evac-row button.rm:hover{border-color:var(--danger)}
.evac-row a.vw{font-size:12px;color:var(--brand2);font-weight:600}
.evac-note{font-size:12px;color:var(--mut2);background:#f8fafc;border:1px solid var(--bd);
  border-radius:8px;padding:9px 11px;margin-top:12px;line-height:1.6}
.evac-close{float:right;border:0;background:none;font-size:20px;cursor:pointer;color:var(--mut)}
.evac-msg{font-size:12.5px;margin-top:8px}
.evac-msg.err{color:var(--danger)}
.evac-msg.ok{color:#047857}
.evac-row .qr{padding:4px 9px;border:1px solid #b6d0f5;border-radius:7px;background:#fff;
  color:#1d4ed8;font-size:11.5px;font-weight:800;letter-spacing:.02em;white-space:nowrap}
.evac-row .qr:hover{background:#eef4ff;border-color:#2563eb}
.uid .uidlink{color:var(--brand2);font-weight:700;border-bottom:1px dotted #93c5fd}
.uid .uidlink:hover{border-bottom-style:solid}
.btn--docs{padding:5px 10px;font-size:12px;white-space:nowrap;margin-left:4px;
  border:1px solid #cbd5e1;border-radius:7px;background:#fff;color:#334155;font-weight:700}
.btn--docs:hover{border-color:var(--brand);color:var(--brand2);background:#f7faff}
.btn--imp{padding:5px 10px;font-size:12px;white-space:nowrap;margin-left:4px;
  border:1px solid #fed7aa;border-radius:7px;background:#fffbf5;color:#b45309;font-weight:700}
.btn--imp:hover{border-color:#f59e0b;background:#fef3c7}
</style>
</head>
<body>

<nav class="nav">
  <div class="nav__in">
    <a class="brand" href="/">TWORIX</a>
    <div style="display:flex;gap:8px">
      <a class="btn" href="/clients_mini.php">← 대시보드</a>
      <a class="btn" href="/logout.php">로그아웃</a>
    </div>
  </div>
</nav>

<main class="wrap">
  <h1>회원 관리</h1>
  <p class="sub">가입한 회원을 확인하고, 필요 없는 계정을 삭제합니다.
    <?php if ($evacRequestCount): ?> · <b>시뮬레이션 배정요청 <?=$evacRequestCount?>건</b><?php endif; ?>
  </p>

  <?php if ($msg): ?>
    <div class="msg <?=h($msgType)?>"><?=h($msg)?></div>
  <?php endif; ?>

  <div class="card">
    <!-- 검색·필터 -->
    <form class="bar" method="get">
      <input type="search" name="q" value="<?=h($q)?>" placeholder="아이디 · 이메일 · 닉네임 검색">
      <select name="role" onchange="this.form.submit()">
        <option value="">전체 유형</option>
        <option value="building" <?=$fRole==='building'?'selected':''?>>건물관리자</option>
        <option value="agency"   <?=$fRole==='agency'?'selected':''?>>대행업체</option>
      </select>
      <button class="btn" type="submit">검색</button>
      <?php if ($q !== '' || $fRole !== ''): ?>
        <a class="btn" href="/admin_members.php">초기화</a>
      <?php endif; ?>
	      <span class="cnt">전체 <b><?=$total?></b>명 · 표시 <b><?=count($rows)?></b>명<?php if ($evacRequestCount): ?> · 배정요청 <b><?=$evacRequestCount?></b>건<?php endif; ?></span>
    </form>

    <!-- 목록 -->
    <form method="post" id="delForm"
          onsubmit="return confirm('선택한 회원을 삭제합니다. 되돌릴 수 없습니다.\n계속할까요?')">
      <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
      <input type="hidden" name="act" value="delete">

      <?php if (!$rows): ?>
        <div class="empty">조건에 맞는 회원이 없습니다.</div>
      <?php else: ?>
      <div style="overflow-x:auto">
      <table>
        <thead>
          <tr>
            <th style="width:34px"><input type="checkbox" id="chkAll" title="전체 선택"></th>
            <th>아이디</th>
            <th class="hide-sm">닉네임</th>
            <th>유형</th>
            <th>시뮬레이션</th>
            <th>이메일</th>
            <th class="hide-sm">연락처</th>
            <th class="hide-sm">가입일</th>
            <th class="hide-sm">최근 로그인</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
          $eKey = lc(trim($r['email']));
          $isDup = $eKey !== '' && ($emailCount[$eKey] ?? 0) > 1;
        ?>
          <tr>
            <td><input type="checkbox" class="chk" name="uids[]" value="<?=h($r['uid'])?>"></td>
            <td class="uid">
              <a class="uidlink" href="/admin_member_review.php?uid=<?=urlencode($r['uid'])?>"
                 title="이 회원의 기본정보·소방계획서·업무일지를 봅니다"><?=h($r['uid'])?></a>
	              <?php if ($r['reviewCount']): ?>
	                <br><a class="tag tag--review" href="/admin_member_review.php?uid=<?=urlencode($r['uid'])?>">확인요청 <?=$r['reviewCount']?>건</a>
	              <?php endif; ?>
	              <?php if ($r['evacRequest']): ?><br><span class="tag tag--evac" data-evac-request-badge="<?=h($r['uid'])?>">시뮬레이션 요청</span><?php endif; ?>
	              <?php if ($r['hasData']): ?><br><span class="tag tag--data">데이터 있음</span><?php endif; ?>
            </td>
            <td class="hide-sm"><?=h($r['nickname'] ?: '-')?></td>
            <td>
              <?php if ($r['role'] === 'building'): ?>
                <span class="tag tag--bld">건물관리자</span>
              <?php else: ?>
                <span class="tag tag--agc">대행업체</span>
              <?php endif; ?>
            </td>
            <td>
	              <?php $ec = count($evacAssign[$r['uid']] ?? []); $hasEvacRequest = !empty($r['evacRequest']); ?>
	              <button type="button" class="btn btn--evac <?=$hasEvacRequest ? 'btn--evac-request' : ''?>" data-uid="<?=h($r['uid'])?>" data-nick="<?=h($r['nickname'] ?: $r['uid'])?>">
	                <?=$hasEvacRequest ? '배정요청' : '배정'?><?php if ($ec): ?> <b class="evac-cnt"><?=$ec?></b><?php endif; ?>
	              </button>
              <a class="btn btn--docs" href="/admin_member_review.php?uid=<?=urlencode($r['uid'])?>"
                 title="기본정보 · 소방계획서 · 업무수행 기록표를 봅니다">📄 서류</a>
              <a class="btn btn--imp" href="/impersonate.php?uid=<?=urlencode($r['uid'])?>"
                 title="이 회원 화면으로 들어가 직접 고칩니다"
                 onclick="return confirm('<?=h($r['nickname'] ?: $r['uid'])?>님 계정으로 들어갑니다.\n화면 위 띠에서 언제든 관리자로 돌아올 수 있습니다.')">👤 대리보기</a>
            </td>
            <td>
              <?php if ($r['email'] !== ''): ?>
                <?=h($r['email'])?>
                <?=$r['email_ok'] ? '<span class="ok-i" title="인증됨">✓</span>' : '<span class="no-i" title="미인증">✗</span>'?>
                <?php if ($isDup): ?><br><span class="tag tag--dup">중복 <?=$emailCount[$eKey]?>개</span><?php endif; ?>
              <?php else: ?>
                <span class="dim">(없음)</span>
              <?php endif; ?>
            </td>
            <td class="hide-sm"><?=h($r['phone'] ?: '-')?></td>
            <td class="hide-sm dim"><?=h(substr($r['created'], 0, 16) ?: '-')?></td>
            <td class="hide-sm dim"><?=h(substr($r['last'], 0, 16) ?: '-')?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>

      <div class="actions">
        <label class="chk-lb">
          <input type="checkbox" name="with_data" value="1" checked>
          개인 데이터 폴더도 함께 삭제 (건물 기본정보 · 업무일지 · 소방계획서 · 배정된 시뮬레이션)
        </label>
        <button class="btn btn--danger" type="submit" id="delBtn" disabled>
          🗑 선택한 회원 삭제 (<span id="selCnt">0</span>)
        </button>
      </div>
      <div class="warn">
        삭제하면 되돌릴 수 없습니다. 실행 직전 <code>data/_backups/</code>에 members.json이 자동 백업됩니다.
        <b>체크를 해제하면 데이터가 서버에 남아, 같은 아이디로 다시 가입할 때 예전 내용이 그대로 보입니다.</b>
      </div>
      <?php endif; ?>
    </form>

    <?php if ($orphans): ?>
    <div style="margin-top:28px;padding-top:24px;border-top:1px solid var(--bd)">
      <h2 style="font-size:17px;font-weight:800">남아 있는 데이터 폴더</h2>
      <p class="sub" style="margin-top:6px">
        지금 회원 목록에 없는 폴더입니다. 탈퇴한 회원이 남긴 것이거나, 예전에 회원 구분 없이
        저장되던 <code>kakao_guest</code> 폴더입니다.
        <b>같은 아이디로 다시 가입하면 이 내용이 그대로 보입니다.</b>
      </p>

      <form method="post" style="margin-top:14px"
            onsubmit="return confirm('선택한 폴더를 삭제합니다. 되돌릴 수 없습니다.\n계속할까요?')">
        <input type="hidden" name="act" value="purge_orphans">
        <input type="hidden" name="csrf" value="<?=h($CSRF)?>">

        <div class="tbl-wrap">
        <table>
          <thead><tr><th style="width:36px"></th><th>폴더</th><th>내용</th><th>파일</th></tr></thead>
          <tbody>
          <?php foreach ($orphans as $o): ?>
            <tr>
              <td><input type="checkbox" name="paths[]" value="<?=h($o['path'])?>"></td>
              <td><code><?=h($o['rel'])?></code>
                <?php if ($o['guest']): ?>
                  <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;
                        background:#fef2f2;color:#dc2626;margin-left:6px">여러 명이 섞였을 수 있음</span>
                <?php endif; ?>
              </td>
              <td><?=h($o['label'])?></td>
              <td><?=(int)$o['files']?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        </div>

        <div class="actions" style="margin-top:14px">
          <label class="chk-lb">
            <input type="checkbox" onclick="document.querySelectorAll('input[name=\'paths[]\']')
              .forEach(function(c){c.checked=this.checked}.bind(this))"> 전체 선택
          </label>
          <button class="btn btn--danger" type="submit">🗑 선택한 폴더 삭제</button>
        </div>
        <div class="warn">
          지우기 전에 내용을 확인하고 싶으시면 FTP로 해당 폴더의 <code>info.json</code>을 열어보세요.
          아직 쓰고 있는 회원 것이면 지우지 마세요.
        </div>
      </form>
    </div>
    <?php endif; ?>
  </div>
</main>

<script>
  const chkAll = document.getElementById('chkAll');
  const chks   = Array.from(document.querySelectorAll('.chk'));
  const delBtn = document.getElementById('delBtn');
  const selCnt = document.getElementById('selCnt');

  function refresh(){
    const n = chks.filter(c => c.checked).length;
    if (selCnt) selCnt.textContent = n;
    if (delBtn) delBtn.disabled = (n === 0);
    if (chkAll) chkAll.checked = (n > 0 && n === chks.length);
  }
  if (chkAll) chkAll.addEventListener('change', () => {
    chks.forEach(c => c.checked = chkAll.checked);
    refresh();
  });
  chks.forEach(c => c.addEventListener('change', refresh));
  refresh();
</script>

<!-- ── 시뮬레이션 배정 모달 ── -->
<div class="evac-modal" id="evacModal" hidden>
  <div class="evac-box">
    <button class="evac-close" id="evacClose" title="닫기">✕</button>
    <h2>피난 시뮬레이션 배정</h2>
    <p class="sub" id="evacWho"></p>

    <div class="evac-request-info" id="evacRequestInfo" hidden>
      <div class="evac-request-info__title">회원이 확인해서 보낸 신청정보</div>
      <dl class="evac-request-info__grid" id="evacRequestDetails"></dl>
    </div>

    <div class="evac-sec">현재 배정된 모델</div>
    <div id="evacAssigned"><p class="dim">불러오는 중…</p></div>

    <div class="evac-sec">서버 보관함 (어느 PC에서든 배정 가능)</div>
    <div id="evacLibrary"><p class="dim">불러오는 중…</p></div>

    <div class="evac-sec">이 브라우저에만 있는 모델
      <span class="evac-sec__hint">— 아직 서버에 올라가지 않았습니다</span></div>
    <div id="evacLocal"></div>

    <div class="evac-msg" id="evacMsg"></div>
    <div class="evac-note">
      <b>서버 보관함</b>의 도면은 어느 PC에서 접속하든 배정할 수 있습니다.
      배정하면 해당 회원의 <b>건물 소방안전관리 페이지</b> 상단에 열람 전용으로 표시됩니다.<br>
      도면 본문은 복사되지 않고 연결만 되므로, <a href="/fire_evac_sim.php" target="_blank">시뮬레이터</a>에서
      고치면 배정된 회원 화면에도 바로 반영됩니다.<br>
      아래 <b>이 브라우저에만 있는 모델</b>은 지금 쓰는 브라우저에 저장된 것입니다.
      캐시를 지우면 사라지니, <b>서버에 올리기</b>를 눌러 보관함으로 옮긴 뒤 배정하세요.
    </div>
  </div>
</div>

<script>
(function(){
  const CSRF   = <?=json_encode($CSRF)?>;
  const API    = '/evac_assign_api.php';
  const LIBAPI = '/evac_library_api.php';        // 서버 보관함 (data/evac_models/)
  const STORE  = 'fireEvac.models.v1';           // fire_evac_sim.php의 localStorage 키
  const REQUESTS = <?=json_encode($evacRequests, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;

  const modal  = document.getElementById('evacModal');
  const boxWho = document.getElementById('evacWho');
  const boxAsg = document.getElementById('evacAssigned');
  const boxLoc = document.getElementById('evacLocal');
  const boxLib = document.getElementById('evacLibrary');
  const boxMsg = document.getElementById('evacMsg');
  const reqInfo = document.getElementById('evacRequestInfo');
  const reqDetails = document.getElementById('evacRequestDetails');
  let curUid = '', assignedIds = [], libIds = [];

  const esc = s => String(s).replace(/[&<>"']/g,
    c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const msg = (t, cls) => { boxMsg.textContent = t || ''; boxMsg.className = 'evac-msg ' + (cls||''); };
  const fmt = t => t ? new Date(t).toLocaleDateString('ko-KR',
    {year:'2-digit',month:'2-digit',day:'2-digit'}) : '';

  function renderRequestInfo(request){
    if (!request || typeof request !== 'object') {
      reqInfo.hidden = true;
      reqDetails.innerHTML = '';
      return;
    }
    const rows = [
      ['신청 시각', request.at || '-'],
      ['건물명', request.building_name || '-'],
      ['건물 주소', request.address || '(기존 신청정보에 없음)'],
      ['안전관리자', request.manager_name || '(기존 신청정보에 없음)'],
      ['전화번호', request.manager_phone || '(기존 신청정보에 없음)']
    ];
    reqDetails.innerHTML = rows.map(row => '<dt>'+esc(row[0])+'</dt><dd>'+esc(row[1])+'</dd>').join('');
    reqInfo.hidden = false;
  }

  function localModels(){
    try{
      const m = JSON.parse(localStorage.getItem(STORE) || '{}') || {};
      return Object.entries(m)
        .map(([id,v]) => ({id, name:v.name||'이름 없는 건물', map:v.map||'',
                           scenario:v.scenario||{}, updated:v.updated||0}))
        .sort((a,b) => b.updated - a.updated);
    }catch(e){ return []; }
  }

  function renderAssigned(models){
    assignedIds = models.map(m => m.id);
    boxAsg.innerHTML = models.length ? models.map(m =>
      '<div class="evac-row"><span class="nm">'+esc(m.name)+'</span>'+
      '<span class="mt">'+fmt(m.updated)+'</span><span class="sp"></span>'+
      '<a class="vw" href="/evac_view.php?id='+encodeURIComponent(m.id)+'" target="_blank">보기 ↗</a>'+
      '<a class="qr" href="/evac_qr.php?id='+encodeURIComponent(m.id)+'" target="_blank" title="QR 만들기">QR</a>'+
      '<button class="rm" data-id="'+esc(m.id)+'">해제</button></div>'
    ).join('') : '<p class="dim">배정된 모델이 없습니다.</p>';
    boxAsg.querySelectorAll('.rm').forEach(b =>
      b.addEventListener('click', () => unassign(b.dataset.id)));
    loadLibrary();
  }

  /* ── 서버 보관함 (data/evac_models/) ──
     예전에는 이 자리가 없어서, 서버에 도면이 있어도 화면에 뜨지 않았습니다.
     여기 목록은 어느 PC에서 접속하든 똑같이 보입니다. */
  async function loadLibrary(){
    boxLib.innerHTML = '<p class="dim">불러오는 중…</p>';
    try{
      const r = await fetch(LIBAPI + '?act=list', {credentials:'same-origin'});
      const j = await r.json();
      if (!j.ok){ boxLib.innerHTML = '<p class="dim">'+esc(j.error||'보관함을 불러오지 못했습니다.')+'</p>';
                  libIds = []; renderLocal(); return; }
      renderLibrary(j.models || []);
    }catch(e){
      boxLib.innerHTML = '<p class="dim">보관함 통신 오류</p>';
      libIds = []; renderLocal();
    }
  }

  function renderLibrary(models){
    libIds = models.map(m => m.id);
    boxLib.innerHTML = models.length ? models.map(m => {
      const done  = assignedIds.includes(m.id);
      const empty = !m.bytes;
      return '<div class="evac-row"><span class="nm">'+esc(m.name)+'</span>'+
        '<span class="mt">'+fmt(m.updated)+(m.used?' · '+m.used+'명 배정':'')+'</span>'+
        '<span class="sp"></span>'+
        '<a class="vw" href="/evac_view.php?id='+encodeURIComponent(m.id)+'" target="_blank">보기 ↗</a>'+
        (empty ? '<span class="mt">도면 없음</span>'
               : '<button class="asg" data-id="'+esc(m.id)+'">'+(done?'배정됨':'배정')+'</button>')+
        '</div>';
    }).join('') : '<p class="dim">서버 보관함이 비어 있습니다. 아래 목록에서 «서버에 올리기»를 눌러 옮기세요.</p>';

    boxLib.querySelectorAll('.asg').forEach(b => {
      if (assignedIds.includes(b.dataset.id)) { b.disabled = true; return; }
      b.addEventListener('click', () => assign(b.dataset.id));
    });
    renderLocal();
  }

  function renderLocal(){
    /* 서버 보관함에 이미 올라간 것은 위 목록에서 처리하므로 여기서는 감춥니다. */
    const list = localModels().filter(m => !libIds.includes(m.id));
    boxLoc.innerHTML = list.length ? list.map(m => {
      const empty = !m.map;
      return '<div class="evac-row"><span class="nm">'+esc(m.name)+'</span>'+
        '<span class="mt">'+fmt(m.updated)+'</span><span class="sp"></span>'+
        (empty ? '<span class="mt">도면 없음</span>'
               : '<button class="upl" data-id="'+esc(m.id)+'">서버에 올리기</button>')+
        '</div>';
    }).join('')
      : '<p class="dim">이 브라우저에만 있는 모델은 없습니다. (모두 서버에 올라가 있습니다)</p>';
    boxLoc.querySelectorAll('.upl').forEach(b =>
      b.addEventListener('click', () => uploadToLibrary(b.dataset.id)));
  }

  /* 브라우저에만 있던 도면을 서버 보관함으로 올립니다.
     올라가야 다른 PC에서도 배정할 수 있고, 캐시를 지워도 사라지지 않습니다. */
  async function uploadToLibrary(id){
    const m = localModels().find(x => x.id === id);
    if (!m || !m.map){ msg('도면이 비어 있어 올릴 수 없습니다.', 'err'); return; }
    msg('서버에 올리는 중…');
    try{
      const body = new URLSearchParams({
        act:'save', id:m.id, name:m.name, map:m.map,
        scenario: JSON.stringify(m.scenario || {})
      });
      body.set('csrf', CSRF);
      const r = await fetch(LIBAPI, {method:'POST', credentials:'same-origin', body});
      const j = await r.json();
      if (j.ok){ msg('서버 보관함에 올렸습니다. 이제 배정할 수 있습니다.', 'ok'); loadLibrary(); }
      else msg(j.error || '올리기 실패', 'err');
    }catch(e){ msg('통신 오류', 'err'); }
  }

  async function loadAssigned(){
    boxAsg.innerHTML = '<p class="dim">불러오는 중…</p>';
    try{
      const r = await fetch(API + '?act=list&uid=' + encodeURIComponent(curUid),
        {credentials:'same-origin'});
      const j = await r.json();
      if (j.ok) renderAssigned(j.models || []);
      else { boxAsg.innerHTML = ''; msg(j.error || '조회 실패', 'err'); renderLocal(); }
    }catch(e){ boxAsg.innerHTML = ''; msg('통신 오류', 'err'); renderLocal(); }
  }

  async function post(data){
    const body = new URLSearchParams(data); body.set('csrf', CSRF);
    const r = await fetch(API, {method:'POST', credentials:'same-origin', body});
    return r.json();
  }

  /* 서버는 보관함의 도면을 '연결'만 합니다. 도면 본문을 보낼 필요가 없습니다. */
  async function assign(id){
    if (!id){ msg('모델을 고르세요.', 'err'); return; }
    msg('배정 중…');
    try{
      const j = await post({act:'assign', uid:curUid, model_id:id});
      if (j.ok){
        msg('배정했습니다.', 'ok');
        delete REQUESTS[curUid];
        renderRequestInfo(null);
        document.querySelector('[data-evac-request-badge="'+CSS.escape(curUid)+'"]')?.remove();
        renderAssigned(j.models || []);
        refreshBadge();
      }
      else msg(j.error || '배정 실패', 'err');
    }catch(e){ msg('통신 오류', 'err'); }
  }

  async function unassign(id){
    if (!confirm('이 모델 배정을 해제할까요?')) return;
    msg('해제 중…');
    try{
      const j = await post({act:'unassign', uid:curUid, model_id:id});
      if (j.ok){ msg('해제했습니다.', 'ok'); renderAssigned(j.models || []); refreshBadge(); }
      else msg(j.error || '해제 실패', 'err');
    }catch(e){ msg('통신 오류', 'err'); }
  }

  /* 목록 버튼의 배정 숫자 갱신 */
  function refreshBadge(){
    const btn = document.querySelector('.btn--evac[data-uid="'+CSS.escape(curUid)+'"]');
    if (!btn) return;
    const n = assignedIds.length;
    const requested = !!REQUESTS[curUid];
    btn.classList.toggle('btn--evac-request', requested);
    btn.innerHTML = (requested ? '배정요청' : '배정') + (n ? ' <b class="evac-cnt">'+n+'</b>' : '');
  }

  document.querySelectorAll('.btn--evac').forEach(b =>
    b.addEventListener('click', () => {
      curUid = b.dataset.uid;
      boxWho.textContent = (b.dataset.nick || curUid) + ' (' + curUid + ') 회원에게 보여줄 모델을 선택하세요.';
      msg('');
      renderRequestInfo(REQUESTS[curUid] || null);
      modal.hidden = false;
      loadAssigned();
    }));
  document.getElementById('evacClose').addEventListener('click', () => modal.hidden = true);
  modal.addEventListener('click', e => { if (e.target === modal) modal.hidden = true; });
})();
</script>
</body>
</html>
