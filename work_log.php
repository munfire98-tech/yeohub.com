<?php
// work_log.php — 소방안전관리자 업무 수행 기록: 월별 목록 + 건물 고정정보
declare(strict_types=1);

if (!ini_get('date.timezone')) { date_default_timezone_set('Asia/Seoul'); }
ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) { session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']); }
session_start();

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function is_admin(): bool {
  return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
function is_logged_in(): bool { return is_admin() || !empty($_SESSION['is_user']); }

if (!is_logged_in()) { header('Location: /index.php'); exit; }
$role = $_SESSION['role'] ?? 'agency';
if (!is_admin() && $role !== 'building') { header('Location: /clients_mini.php'); exit; }

require_once __DIR__ . '/user_key.php';
require_once __DIR__ . '/building_info.php';
$uidKey = app_user_key();   // 회원을 특정하지 못하면 '' (예전 kakao_guest 통합 제거)
if ($uidKey === '') { die('<meta charset="utf-8">' . app_user_key_notice()); }
$uidKey = preg_replace('/[^A-Za-z0-9_]/', '_', (string)$uidKey);
$BASE   = __DIR__ . '/data/worklog/' . $uidKey;
if (!is_dir($BASE)) @mkdir($BASE, 0775, true);
$FIXED_FILE = $BASE . '/building.json';

function load_json(string $f): array {
  if (!file_exists($f)) return [];
  $r = @file_get_contents($f); if ($r === false || trim($r) === '') return [];
  $a = json_decode($r, true); return is_array($a) ? $a : [];
}
function save_json(string $f, array $arr): bool {
  if (!is_dir(dirname($f))) @mkdir(dirname($f), 0775, true);
  $tmp = $f . '.tmp';
  file_put_contents($tmp, json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
  return @rename($tmp, $f);
}

/* 건물 고유 코드 발급 (BLD-0001, 0002... 전체 통합 순번).
   동시 저장 충돌을 막기 위해 카운터 파일을 잠금(LOCK)으로 증가시킴 */
function issue_building_code(): string {
  $counterFile = __DIR__ . '/data/building_counter.json';
  if (!is_dir(dirname($counterFile))) @mkdir(dirname($counterFile), 0775, true);
  $fp = @fopen($counterFile, 'c+');
  if ($fp === false) {
    // 잠금 실패 시 시간 기반 대체 코드 (충돌 회피)
    return 'BLD-' . date('ymdHis');
  }
  flock($fp, LOCK_EX);
  $raw = stream_get_contents($fp);
  $data = json_decode($raw ?: '', true);
  $last = is_array($data) ? (int)($data['last'] ?? 0) : 0;
  $next = $last + 1;
  rewind($fp); ftruncate($fp, 0);
  fwrite($fp, json_encode(['last' => $next], JSON_UNESCAPED_UNICODE));
  fflush($fp); flock($fp, LOCK_UN); fclose($fp);
  return 'BLD-' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function sync_worklog_fixed_with_basic(array $fixed): array {
  $bi = bi_load();
  $mgrs = is_array($bi['mgrs'] ?? null) ? $bi['mgrs'] : [];
  $fixed['bcode'] = trim((string)($fixed['bcode'] ?? '')) !== '' ? (string)$fixed['bcode'] : issue_building_code();
  $fixed['sangho'] = (string)($bi['name'] ?? '');
  $fixed['grade'] = (string)($bi['grade'] ?? '');
  $fixed['address'] = (string)($bi['address'] ?? '');
  $fixed['floor_b'] = (string)($bi['floor_b'] ?? '');
  $fixed['floor_a'] = (string)($bi['floor_a'] ?? '');
  $fixed['area_t'] = (string)($bi['area_t'] ?? '');
  $fixed['area_f'] = (string)($bi['area_f'] ?? '');
  $fixed['dongsu'] = (string)($bi['dongsu'] ?? '');
  if (trim((string)($mgrs[0]['name'] ?? '')) !== '') $fixed['performer'] = (string)$mgrs[0]['name'];
  return $fixed;
}

function worklog_note_progress(array $fixed): array {
  $labels = ['note_sobang'=>'소방시설', 'note_pinan'=>'피난방화시설', 'note_hwagi'=>'화기취급감독', 'note_etc'=>'기타사항'];
  $filled = 0; $missing = [];
  foreach ($labels as $k => $label) {
    if (trim((string)($fixed[$k] ?? '')) !== '') $filled++;
    else $missing[] = $label;
  }
  return ['filled'=>$filled, 'total'=>count($labels), 'percent'=>(int)round($filled / count($labels) * 100), 'missing'=>$missing];
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

$fixed = load_json($FIXED_FILE);
$saved = '';
$viewUid = app_user_key();
$adminView = is_admin() && trim((string)($_GET['uid'] ?? '')) !== '' && $viewUid !== '';
$adminQuery = $adminView ? ('uid=' . rawurlencode($viewUid)) : '';
$url = function(string $path) use ($adminQuery): string {
  if ($adminQuery === '') return $path;
  return $path . (strpos($path, '?') === false ? '?' : '&') . $adminQuery;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_fixed') {
  if (!hash_equals($CSRF, $_POST['csrf'] ?? '')) { http_response_code(403); exit('CSRF'); }
  // 건물 코드: 이미 있으면 그대로 유지, 처음이면 새로 발급
  $bcode = $fixed['bcode'] ?? '';
  if ($bcode === '') $bcode = issue_building_code();
  $keep = $fixed;   // 폼에 없는 항목은 기존 값을 지킨다
  $pick = function(string $k) use ($keep) {
    return array_key_exists($k, $_POST) ? trim((string)$_POST[$k]) : (string)($keep[$k] ?? '');
  };
  $fixed = [
    'bcode'    => $bcode,
    'sangho'   => $pick('sangho'),
    'grade'    => in_array($_POST['grade'] ?? '', ['특급','1급','2급','3급'], true) ? $_POST['grade'] : '',
    'address'  => $pick('address'),
    'floor_b'  => $pick('floor_b'),
    'floor_a'  => $pick('floor_a'),
    'area_t'   => $pick('area_t'),
    'area_f'   => $pick('area_f'),
    'dongsu'   => $pick('dongsu'),
    'performer'=> $pick('performer'),
    'note_sobang' => $pick('note_sobang'),
    'note_pinan'  => $pick('note_pinan'),
    'note_hwagi'  => $pick('note_hwagi'),
    'note_etc'    => $pick('note_etc'),
  ];
  save_json($FIXED_FILE, $fixed);
  $saved = '건물 정보가 저장되었습니다.';
}

$fixed = sync_worklog_fixed_with_basic($fixed);
save_json($FIXED_FILE, $fixed);
$noteProg = worklog_note_progress($fixed);
$biProg = bi_progress();
$biDone = $biProg['filled'] >= $biProg['total'];
$setupComplete = $biDone && $noteProg['filled'] >= $noteProg['total'];

$worklogReviewPending = 0;
$worklogReviewResolvedRecent = false;
$reviewFile = __DIR__ . '/data/assist_log.json';
$memberUid = $viewUid;
$memberCreatedAt = 0;
if ($memberUid !== '') {
  $membersRows = load_json(__DIR__ . '/data/members.json');
  if (isset($membersRows[$memberUid])) {
    $memberCreatedAt = strtotime((string)($membersRows[$memberUid]['created'] ?? '')) ?: 0;
  }
}
if ($memberUid !== '' && is_file($reviewFile)) {
  $reviewRows = load_json($reviewFile);
  foreach ($reviewRows as $reviewRow) {
    if (($reviewRow['kind'] ?? '') !== 'review') continue;
    if ((string)($reviewRow['uid'] ?? '') !== $memberUid) continue;
    if (strpos((string)($reviewRow['text'] ?? ''), '업무수행 기록표:') !== 0) continue;
    $requestedAt = strtotime((string)($reviewRow['at'] ?? '')) ?: 0;
    if ($memberCreatedAt > 0 && $requestedAt > 0 && $requestedAt < $memberCreatedAt) continue;
    if (($reviewRow['status'] ?? 'pending') === 'resolved') {
      $resolvedAt = strtotime((string)($reviewRow['resolved_at'] ?? ''));
      if ($resolvedAt !== false && $resolvedAt >= strtotime('-7 days')) $worklogReviewResolvedRecent = true;
    } else {
      $worklogReviewPending++;
    }
  }
}

$existing = [];
foreach (glob($BASE . '/m*.json') ?: [] as $f) {
  if (preg_match('/m(\d{4})-(\d{2})\.json$/', $f, $m)) $existing[$m[1].'-'.$m[2]] = true;
}

// 최근 24개월을 연도별로 그룹핑 (DateTime 대신 기본 함수 사용 — 서버 호환성)
$byYear = [];
$doneByYear = [];
$ty = (int)date('Y');
$tm = (int)date('n');
for ($i = 0; $i < 24; $i++) {
  $ts   = mktime(0, 0, 0, $tm - $i, 1, $ty);
  $key  = date('Y-m', $ts);
  $year = date('Y', $ts);
  $done = isset($existing[$key]);
  $byYear[$year][] = ['key' => $key, 'label' => date('n', $ts) . '월', 'done' => $done];
  if ($done) $doneByYear[$year] = ($doneByYear[$year] ?? 0) + 1;
}

$nick = $_SESSION['nickname'] ?? '사용자';

/* 알림 미확인 개수 — 종 아이콘의 빨간 점에 씁니다 */
$unreadCount = 0;
if ($uidKey !== '') {
  $__nf = __DIR__ . '/data/notifications/' . $uidKey . '.json';
  if (is_file($__nf)) {
    $__nl = json_decode((string)@file_get_contents($__nf), true);
    if (is_array($__nl)) { foreach ($__nl as $__n) { if (empty($__n['read'])) $unreadCount++; } }
  }
}
$hasFixed = !empty($fixed['sangho']);
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>업무 수행 기록 — TWORIX</title>
<style>
:root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--bd2:#d4dbe6;--fg:#1a2436;--mut:#7a8699;--mut2:#56627a;--brand:#2563eb;--brand2:#1d4ed8;--accent:#0891b2;--ok:#16a34a}
*{box-sizing:border-box;margin:0;padding:0}
html,body{background:var(--bg);color:var(--fg);font-family:Inter,ui-sans-serif,system-ui,"Apple SD Gothic Neo",sans-serif;line-height:1.6}
a{text-decoration:none}
.nav{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.9);backdrop-filter:blur(12px);border-bottom:1px solid var(--bd)}
.nav__inner{max-width:1120px;margin:0 auto;padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;gap:16px}
.nav__brand{font-weight:800;font-size:22px;color:var(--fg);letter-spacing:.5px}
.nav__right{display:flex;align-items:center;gap:12px;font-size:14px;color:var(--mut2)}

/* ── 계정 아이콘 (건물관리 · 결제 · 알림 · 프로필) — 다른 페이지와 같은 모양 ── */
.nw-icons{display:flex;align-items:center;gap:6px}
.nw-icobtn{position:relative;display:flex;align-items:center;justify-content:center;
  width:38px;height:38px;border-radius:10px;border:1px solid transparent;background:transparent;
  color:var(--mut2);cursor:pointer;font-family:inherit;transition:.14s;text-decoration:none}
.nw-icobtn:hover{background:var(--bg);border-color:var(--bd)}
.nw-icobtn svg{width:19px;height:19px}
.nw-dot{position:absolute;top:7px;right:7px;width:7px;height:7px;border-radius:50%;
  background:#ef4444;border:1.5px solid #fff}
.nw-profile{position:relative}
.nw-avatar{width:36px;height:36px;border-radius:50%;border:0;cursor:pointer;font-family:inherit;
  background:linear-gradient(135deg,var(--brand),var(--accent));color:#fff;font-size:13px;font-weight:800;
  display:flex;align-items:center;justify-content:center;transition:.14s}
.nw-avatar:hover{filter:brightness(1.06)}
.nw-avatar.admin{background:linear-gradient(135deg,#f59e0b,#ea580c)}
.nw-pop{position:absolute;top:calc(100% + 10px);right:0;width:220px;background:var(--card);
  border:1px solid var(--bd);border-radius:14px;box-shadow:0 14px 34px rgba(16,24,38,.14);
  padding:8px;z-index:90;display:none}
.nw-pop.show{display:block}
.nw-pop__head{padding:11px 12px 12px;border-bottom:1px solid var(--bd)}
.nw-pop__name{font-size:14px;font-weight:800;color:var(--fg)}
.nw-pop__sub{font-size:11.5px;color:var(--mut);margin-top:2px}
.nw-pop__list{padding:6px 0 0}
.nw-pop__item{display:flex;align-items:center;gap:10px;width:100%;padding:9px 10px;border-radius:9px;
  border:0;background:transparent;color:var(--fg);font-size:13px;font-weight:600;font-family:inherit;
  cursor:pointer;text-align:left;text-decoration:none}
.nw-pop__item:hover{background:var(--bg)}
.nw-pop__item svg{width:16px;height:16px;color:var(--mut2);flex-shrink:0}
.nw-pop__item--danger{color:#dc2626}
.nw-pop__item--danger svg{color:#dc2626}
.nw-pop__div{height:1px;background:var(--bd);margin:6px 2px}
@media(max-width:680px){ .nw-pop{right:-8px} }
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:9px;border:1px solid var(--bd2);background:#fff;color:var(--fg);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit}
.btn:hover{border-color:var(--brand);color:var(--brand2)}
.btn--primary{background:var(--brand);border-color:var(--brand);color:#fff}
.btn--primary:hover{background:var(--brand2);color:#fff}
.page-head{position:relative;overflow:hidden;border-bottom:1px solid var(--bd);
  background:linear-gradient(rgba(37,99,235,.04) 1px,transparent 1px) 0 0/100% 28px,
  linear-gradient(90deg,rgba(37,99,235,.04) 1px,transparent 1px) 0 0/28px 100%,
  linear-gradient(180deg,#fbfcff,#eef3fb)}
.page-head::before{content:'';position:absolute;inset:0;pointer-events:none;
  background:radial-gradient(ellipse 760px 320px at 12% 0%,rgba(8,145,178,.10),transparent 70%)}
.page-head__inner{position:relative;max-width:1120px;margin:0 auto;padding:44px 24px 36px}
.crumb{font-size:13px;color:var(--mut2);margin-bottom:12px}
.crumb a{color:var(--mut2)}.crumb a:hover{color:var(--brand2)}
.badge{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;border:1px solid var(--bd2);background:#fff;color:var(--mut2);font-size:12px;margin-bottom:14px}
.badge span{width:6px;height:6px;border-radius:50%;background:var(--accent);display:inline-block}
.page-head h1{font-size:clamp(24px,3.5vw,32px);font-weight:700;letter-spacing:-.5px;margin-bottom:8px}
.page-head p{color:var(--mut2);font-size:15px}
.wrap{max-width:1120px;margin:0 auto;padding:28px 24px 80px}
.section{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:24px;margin-bottom:20px}
.section h2{font-size:17px;font-weight:700;margin-bottom:4px}
.section .desc{color:var(--mut2);font-size:13px;margin-bottom:18px}
.bcode-badge{display:inline-flex;align-items:center;font-size:13px;font-weight:700;letter-spacing:.5px;
  color:var(--brand2);background:#eef4ff;border:1px solid #c7dbff;border-radius:8px;padding:3px 10px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px}
.field{display:flex;flex-direction:column;gap:6px}
.field label{font-size:12px;color:var(--mut2);font-weight:600}
.field input{padding:10px 12px;border:1px solid var(--bd2);border-radius:9px;font-size:14px;font-family:inherit;background:#f8fafc}
.field input:focus{outline:none;border-color:var(--brand);background:#fff}
.grades{display:flex;gap:8px;flex-wrap:wrap}
.grade{display:flex;align-items:center;gap:6px;padding:9px 14px;border:1px solid var(--bd2);border-radius:9px;font-size:14px;cursor:pointer;background:#f8fafc}
.grade:has(input:checked){border-color:var(--brand);background:#f0f5ff;color:var(--brand2);font-weight:600}
.toast{background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;border-radius:9px;padding:10px 14px;font-size:13px;margin-bottom:16px}
.warn{background:#fff7ed;border:1px solid #fed7aa;color:#c2410c;border-radius:9px;padding:12px 14px;font-size:14px;margin-bottom:18px}
.months{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px}
.yeargroup{margin-bottom:26px}
.yeargroup:last-child{margin-bottom:0}
.yearhead{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px;flex-wrap:wrap}
.yearhead h3{font-size:16px;font-weight:800}
.yearempty{font-size:12px;color:var(--mut)}
.mcard{display:flex;flex-direction:column;gap:6px;border:1px solid var(--bd);border-radius:12px;padding:16px;background:#fff;transition:.15s}
.mcard:hover{border-color:var(--brand);box-shadow:0 8px 20px rgba(37,99,235,.08)}
.mcard .mlabel{font-weight:700;font-size:15px}
.mcard .mstat{font-size:12px}
.mcard .done{color:var(--ok);font-weight:600}
.mcard .todo{color:var(--mut)}
.mtoolbar{display:flex;justify-content:flex-end;margin-top:6px}
.mtoolbar a{font-size:12px;color:var(--brand2);font-weight:600}
@media(max-width:680px){.nav__inner{padding:0 16px}.page-head__inner{padding:32px 20px 26px}}
.notehd{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:22px 0 4px;padding-top:18px;border-top:1px solid var(--bd)}
.notehd h3{font-size:15px;font-weight:800}
.noteprog{font-size:11.5px;font-weight:800;padding:3px 10px;border-radius:999px;background:#fff7ed;color:#b45309}
.noteprog.is-done{background:#f0fdf4;color:#15803d}
.noteguide{display:flex;gap:12px;align-items:center;flex-wrap:wrap;background:#eff6ff;
  border:1px solid #bfdbfe;border-radius:12px;padding:14px 16px;margin:14px 0;
  font-size:13.5px;color:#1e40af;line-height:1.65}
.noteguide > div:last-of-type{flex:1;min-width:200px}
.noteguide .btn{white-space:nowrap}
.notegrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}
.notefield{display:flex;flex-direction:column;gap:5px}
.notefield label{font-size:13px;font-weight:700;display:flex;align-items:center;gap:7px}
.tag-empty{font-size:10.5px;font-weight:800;padding:2px 8px;border-radius:999px;background:#fef2f2;color:#dc2626}
.notehint{font-size:12px;color:var(--mut);line-height:1.5}
.notefield textarea{padding:11px 13px;border:1px solid var(--bd2);border-radius:9px;font-size:14px;
  font-family:inherit;background:#f8fafc;resize:vertical;min-height:78px;line-height:1.6;width:100%}
.notefield textarea:focus{outline:none;border-color:var(--brand);background:#fff;box-shadow:0 0 0 3px rgba(37,99,235,.1)}
.btn--tiny{align-self:flex-start;padding:5px 11px;font-size:12px}</style>
</head>
<body>

<nav class="nav">
  <div class="nav__inner">
    <a class="nav__brand" href="/index.php">소방계획서.com<?php if (is_admin()): ?> <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;background:#fff7ed;color:#b45309">관리자</span><?php endif; ?></a>
    <div class="nav__right">
      <div class="nw-icons">
        <a class="nw-icobtn" href="<?=h($url('/building_manager.php'))?>" title="건물 관리">
          <svg viewBox="0 0 24 24" fill="none"><path d="M4 21V5a1 1 0 011-1h8a1 1 0 011 1v16" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M14 10h5a1 1 0 011 1v10" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M7 8h1M11 8h1M7 12h1M11 12h1M7 16h1M11 16h1M17 14h1M17 18h1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        </a>
        <a class="nw-icobtn" href="/subscribe_page.php" title="결제·구독">
          <svg viewBox="0 0 24 24" fill="none"><path d="M3 7a2 2 0 012-2h13a2 2 0 012 2v2H3V7z" stroke="currentColor" stroke-width="1.8"/><path d="M3 9v8a2 2 0 002 2h13a2 2 0 002-2V9" stroke="currentColor" stroke-width="1.8"/><path d="M16 14h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        </a>
        <a class="nw-icobtn" href="/notifications.php" title="알림">
          <svg viewBox="0 0 24 24" fill="none"><path d="M6 9a6 6 0 1112 0c0 4 1.5 5.5 1.5 5.5H4.5S6 13 6 9z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M10 18a2 2 0 004 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
          <?php if ($unreadCount > 0): ?><span class="nw-dot"></span><?php endif; ?>
        </a>
        <div class="nw-profile" id="navProfile">
          <button type="button" class="nw-avatar<?= is_admin() ? ' admin' : '' ?>" id="navAvatarBtn"
            onclick="document.getElementById('navPop').classList.toggle('show')">
            <?=h(mb_substr($nick, 0, 1))?>
          </button>
          <div class="nw-pop" id="navPop">
            <div class="nw-pop__head">
              <div class="nw-pop__name"><?=h($nick)?>님</div>
              <div class="nw-pop__sub"><?= is_admin() ? '관리자' : '건물 소방안전관리자' ?></div>
            </div>
            <div class="nw-pop__list">
              <a class="nw-pop__item" href="<?=h($url('/building_manager.php'))?>">
                <svg viewBox="0 0 24 24" fill="none"><path d="M4 21V5a1 1 0 011-1h8a1 1 0 011 1v16" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M14 10h5a1 1 0 011 1v10" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                건물 관리
              </a>
              <a class="nw-pop__item" href="/settings.php">
                <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M19.4 15a1.7 1.7 0 00.34 1.87l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.7 1.7 0 00-1.87-.34 1.7 1.7 0 00-1 1.55V21a2 2 0 11-4 0v-.09a1.7 1.7 0 00-1-1.56 1.7 1.7 0 00-1.87.34l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.7 1.7 0 00.34-1.87 1.7 1.7 0 00-1.55-1H3a2 2 0 110-4h.09a1.7 1.7 0 001.55-1 1.7 1.7 0 00-.34-1.87l-.06-.06a2 2 0 112.83-2.83l.06.06a1.7 1.7 0 001.87.34H9a1.7 1.7 0 001-1.55V3a2 2 0 114 0v.09a1.7 1.7 0 001 1.55 1.7 1.7 0 001.87-.34l.06-.06a2 2 0 112.83 2.83l-.06.06a1.7 1.7 0 00-.34 1.87V9a1.7 1.7 0 001.55 1H21a2 2 0 110 4h-.09a1.7 1.7 0 00-1.55 1z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                내 정보
              </a>
              <a class="nw-pop__item" href="/subscribe_page.php">
                <svg viewBox="0 0 24 24" fill="none"><path d="M3 7a2 2 0 012-2h13a2 2 0 012 2v2H3V7z" stroke="currentColor" stroke-width="1.8"/><path d="M3 9v8a2 2 0 002 2h13a2 2 0 002-2V9" stroke="currentColor" stroke-width="1.8"/></svg>
                결제·구독
              </a>
              <a class="nw-pop__item" href="/notifications.php">
                <svg viewBox="0 0 24 24" fill="none"><path d="M6 9a6 6 0 1112 0c0 4 1.5 5.5 1.5 5.5H4.5S6 13 6 9z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                알림
              </a>
              <div class="nw-pop__div"></div>
              <a class="nw-pop__item nw-pop__item--danger" href="/?logout=1&csrf=<?=h($CSRF)?>"
                 onclick="return confirm('로그아웃할까요?');">
                <svg viewBox="0 0 24 24" fill="none"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 17l5-5-5-5M21 12H9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                로그아웃
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</nav>

<script>
  /* 프로필 드롭다운: 바깥을 누르면 닫힘 */
  document.addEventListener('click', function(e){
    var wrap = document.getElementById('navProfile');
    var pop  = document.getElementById('navPop');
    if (wrap && pop && !wrap.contains(e.target)) pop.classList.remove('show');
  });
</script>

<header class="page-head">
  <div class="page-head__inner">
    <div class="crumb"><a href="<?=h($url('/building_manager.php'))?>">건물 소방안전관리</a> › 업무 수행 기록</div>
    <div class="badge"><span></span> 업무 수행 기록표 (별지 제12호)</div>
    <h1>업무 수행 기록</h1>
    <p>법정 서식에 맞춰 월 1회 이상 작성하고, PDF로 내려받아 보관하세요.</p>
  </div>
</header>

<main class="wrap">

  <?php if ($saved): ?><div class="toast"><?=h($saved)?></div><?php endif; ?>

  <details class="section" <?= $setupComplete ? '' : 'open' ?>>
    <summary style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;cursor:pointer;list-style:none">
    <h2 style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0">
      건물 정보 (한 번만 저장하면 매월 자동 입력)
      <?php if (!empty($fixed['bcode'])): ?>
        <span class="bcode-badge"><?=h($fixed['bcode'])?></span>
      <?php endif; ?>
      <?php if ($worklogReviewPending > 0): ?>
        <span class="bcode-badge" style="background:#fef3c7;border-color:#fde68a;color:#b45309">확인요청 <?=$worklogReviewPending?>건</span>
      <?php elseif ($worklogReviewResolvedRecent): ?>
        <span class="bcode-badge" style="background:#e0f2fe;border-color:#bae6fd;color:#0369a1">관리자 확인완료</span>
      <?php endif; ?>
      <?php if ($setupComplete): ?>
        <span class="bcode-badge" style="background:#ecfdf5;border-color:#a7f3d0;color:#047857">설정 완료</span>
      <?php endif; ?>
    </h2>
    <span style="margin-left:auto;font-size:12px;color:var(--mut2);font-weight:700">
      <?= $setupComplete ? '펼쳐보기' : '확인 필요' ?>
    </span>
    </summary>
    <div class="desc">
      상호·소재지·등급·규모·수행자는 <b>건물 기본정보</b>에서 자동으로 가져옵니다.
      여기서는 업무수행 기록표에 매월 반복해서 들어갈 확인내용 기본값만 설정합니다.
      <?php if (!empty($fixed['bcode'])): ?> 건물 고유번호는 <b><?=h($fixed['bcode'])?></b>입니다.<?php endif; ?>
    </div>
    <?php if (!$biDone): ?>
      <div class="warn">건물 기본정보가 아직 완성되지 않았습니다. 누락: <?=h(implode(', ', $biProg['missing']))?></div>
    <?php endif; ?>
    <div class="grid" style="margin-bottom:16px">
      <div class="field"><label>상호</label><input value="<?=h($fixed['sangho'] ?? '')?>" readonly></div>
      <div class="field"><label>수행자</label><input value="<?=h($fixed['performer'] ?? '')?>" readonly></div>
      <div class="field"><label>등급</label><input value="<?=h($fixed['grade'] ?? '')?>" readonly></div>
      <div class="field"><label>소재지</label><input value="<?=h($fixed['address'] ?? '')?>" readonly></div>
      <div class="field"><label>규모</label><input value="지하 <?=h($fixed['floor_b'] ?? '')?>층 · 지상 <?=h($fixed['floor_a'] ?? '')?>층 · <?=h($fixed['dongsu'] ?? '')?>동" readonly></div>
      <div class="field"><label>면적</label><input value="연면적 <?=h($fixed['area_t'] ?? '')?>㎡ · 바닥면적 <?=h($fixed['area_f'] ?? '')?>㎡" readonly></div>
    </div>
    <form method="post" id="noteForm">
      <input type="hidden" name="action" value="save_fixed">
      <input type="hidden" name="csrf" value="<?=h($CSRF)?>">

      <div class="notehd">
        <h3>확인내용 기본값</h3>
        <span class="noteprog <?= $noteProg['filled'] >= $noteProg['total'] ? 'is-done' : '' ?>">
          <?=$noteProg['filled']?>/<?=$noteProg['total']?> 작성
        </span>
        <?php if ($worklogReviewPending > 0): ?>
          <span class="noteprog" style="background:#fff7ed;color:#b45309">TWORIX 검토요청 <?=$worklogReviewPending?>건 대기</span>
        <?php elseif ($worklogReviewResolvedRecent): ?>
          <span class="noteprog is-done">관리자 확인완료</span>
        <?php endif; ?>
      </div>

      <?php if ($noteProg['filled'] < $noteProg['total']): ?>
        <div class="noteguide">
          <div>💬</div>
          <div><b>무엇을 적을지 모르시겠나요?</b>
            질문에 답하면서 하나씩 채울 수 있습니다. 항목마다 실무에서 쓰는 예시 문구를 골라 넣으시면 됩니다.</div>
          <a class="btn btn--primary" href="<?=h($url('/work_log_setup_chat.php'))?>">문답으로 채우기 →</a>
        </div>
      <?php endif; ?>

      <div class="desc" style="margin-bottom:14px">
        매월 기록표에 <b>자동으로 채워질 문장</b>입니다. 한 번 적어두면 매달 그대로 들어가고,
        그 달에 특별한 일이 있으면 그때만 고치시면 됩니다.
      </div>

      <?php
        $noteFields = [
          'note_sobang' => ['소방시설', '소화기·옥내소화전·자동화재탐지설비 등을 점검한 내용',
            '소화기 압력 및 설치 위치 확인, 옥내소화전함 주변 적치물 없음, 자동화재탐지설비 수신기 정상 작동 확인'],
          'note_pinan' => ['피난방화시설', '비상구·피난통로·유도등·방화문 상태를 확인한 내용',
            '피난통로 및 비상구 적치물 없음 확인, 유도등 점등 상태 정상, 방화문 폐쇄 상태 및 자동폐쇄장치 정상 확인'],
          'note_hwagi' => ['화기취급감독', '불을 쓰는 곳과 그 주변을 살핀 내용',
            '보일러실·전기실 주변 가연물 정리 상태 확인, 지정 흡연구역 외 흡연 여부 점검, 화기 사용 후 뒷정리 확인'],
          'note_etc' => ['기타사항', '위에 해당하지 않는 확인 사항', '특이사항 없음'],
        ];
      ?>
      <div class="notegrid">
        <?php foreach ($noteFields as $key => $nf): ?>
          <div class="notefield">
            <label for="<?=h($key)?>"><?=h($nf[0])?>
              <?php if (trim((string)($fixed[$key] ?? '')) === ''): ?>
                <span class="tag-empty">미작성</span>
              <?php endif; ?>
            </label>
            <div class="notehint"><?=h($nf[1])?></div>
            <textarea id="<?=h($key)?>" name="<?=h($key)?>" rows="3"
                      placeholder="<?=h($nf[2])?>"><?=h((string)($fixed[$key] ?? ''))?></textarea>
            <button type="button" class="btn btn--tiny" onclick="fillNote('<?=h($key)?>')">✍️ 예시 넣기</button>
          </div>
        <?php endforeach; ?>
      </div>

      <div style="display:flex;gap:9px;flex-wrap:wrap;margin-top:18px;align-items:center">
        <button class="btn btn--primary" type="submit">확인내용 기본값 저장</button>
        <a class="btn" href="<?=h($url('/work_log_setup_chat.php'))?>">💬 문답으로 채우기</a>
        <a class="btn" href="<?=h($url('/building_setup_chat.php'))?>">건물 기본정보 수정</a>
        <a class="btn" href="<?=h($url('/building_setup.php'))?>">표로 자세히 수정</a>
      </div>
    </form>

    <script>
    var NOTE_SAMPLES = <?=json_encode(array_map(fn($x) => $x[2], $noteFields), JSON_UNESCAPED_UNICODE)?>;
    function fillNote(key){
      var el = document.getElementById(key);
      if (!el) return;
      if (el.value.trim() !== '' && !confirm('이미 적힌 내용을 예시로 바꿀까요?')) return;
      el.value = NOTE_SAMPLES[key] || '';
      el.focus();
    }
    </script>
  </details>

  <div class="section">
    <h2>월별 기록 (최근 2년)</h2>
    <div class="desc">작성할 달을 선택하세요. 작성 완료된 달은 초록색으로 표시됩니다. 연도별로 한 번에 인쇄·PDF 저장할 수 있습니다.</div>
    <?php if (!$hasFixed): ?>
      <div class="warn">먼저 위 <b>건물 정보</b>를 저장하면 월별 기록 작성이 훨씬 편해집니다.</div>
    <?php endif; ?>

    <?php foreach ($byYear as $year => $list): $year = (string)$year; ?>
      <div class="yeargroup">
        <div class="yearhead">
          <h3><?=h($year)?>년</h3>
          <?php $cnt = $doneByYear[$year] ?? 0; ?>
          <?php if ($cnt > 0): ?>
            <a class="btn btn--primary" href="<?=h($url('/work_log_print.php?year=' . rawurlencode($year)))?>">🖨 <?=$year?>년 전체 인쇄 / PDF (<?=$cnt?>건)</a>
          <?php else: ?>
            <span class="yearempty">작성된 기록 없음</span>
          <?php endif; ?>
        </div>
        <div class="months">
          <?php foreach ($list as $mo): ?>
            <div class="mcard">
              <div class="mlabel"><?=h($mo['label'])?></div>
              <div class="mstat">
                <?php if ($mo['done']): ?><span class="done">✓ 작성 완료</span><?php else: ?><span class="todo">미작성</span><?php endif; ?>
              </div>
              <div class="mtoolbar">
                <a href="<?=h($url('/work_log_form.php?month=' . rawurlencode($mo['key'])))?>"><?= $mo['done'] ? '수정/인쇄 →' : '작성하기 →' ?></a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

</main>

</body>
</html>
