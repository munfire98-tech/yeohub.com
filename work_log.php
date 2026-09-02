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

/* 2급 대상물 업무수행 기록표 기본 확인문구.
   스프링클러가 없는 건물에는 관련 점검 문구를 넣지 않습니다. */
function worklog_note_defaults(string $sprinkler, string $hydrant = 'no'): array {
  $fireParts = ['소화기 비치 및 압력 상태 확인'];
  if ($sprinkler === 'yes') $fireParts[] = '스프링클러 헤드 훼손·누수·살수 장애물 여부 확인';
  if ($hydrant === 'yes') $fireParts[] = '옥내소화전함 주변 적치물 및 사용 가능 상태 확인';
  $fireParts[] = '자동화재탐지설비 감지기·수신기 정상 상태 확인';
  $fire = implode(', ', $fireParts);
  return [
    'note_sobang' => $fire,
    'note_pinan'  => '피난통로·비상구 적치물 여부, 유도등 점등 상태, 방화문 폐쇄 및 자동폐쇄장치 정상 여부 확인',
    'note_hwagi'  => '보일러실·전기실 등 화기취급 장소 주변 가연물 정리 상태와 화기 사용 후 안전조치 여부 확인',
    'note_etc'    => '소방안전관리 업무수행 중 특이사항 및 관계인 전달사항 확인',
  ];
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
  $applyDefaults = (string)($_POST['apply_defaults'] ?? '');
  if ($applyDefaults === 'quick') {
    $sprinklerChoice = ($_POST['has_sprinkler'] ?? '') === 'yes' ? 'yes' : 'no';
    $hydrantChoice = ($_POST['has_hydrant'] ?? '') === 'yes' ? 'yes' : 'no';
    $fixed = sync_worklog_fixed_with_basic($fixed);
    $fixed['sprinkler'] = $sprinklerChoice;
    $fixed['hydrant'] = $hydrantChoice;
    $fixed['facility_setup_done'] = '1';
    foreach (worklog_note_defaults($sprinklerChoice, $hydrantChoice) as $key => $value) $fixed[$key] = $value;
    save_json($FIXED_FILE, $fixed);
    header('Location: ' . $url('/work_log.php?setup=done'));
    exit;
  } else {
    // 건물 코드: 이미 있으면 그대로 유지, 처음이면 새로 발급
    $bcode = $fixed['bcode'] ?? '';
    if ($bcode === '') $bcode = issue_building_code();
    $keep = $fixed;   // 폼에 없는 항목은 기존 값을 지킨다
    $pick = function(string $k) use ($keep) {
      return array_key_exists($k, $_POST) ? trim((string)$_POST[$k]) : (string)($keep[$k] ?? '');
    };
    $postedGrade = in_array($_POST['grade'] ?? '', ['특급','1급','2급','3급'], true) ? (string)$_POST['grade'] : '';
    $fixed = [
      'bcode'    => $bcode,
      'sangho'   => $pick('sangho'),
      'grade'    => $postedGrade !== '' ? $postedGrade : $pick('grade'),
      'address'  => $pick('address'),
      'floor_b'  => $pick('floor_b'),
      'floor_a'  => $pick('floor_a'),
      'area_t'   => $pick('area_t'),
      'area_f'   => $pick('area_f'),
      'dongsu'   => $pick('dongsu'),
      'performer'=> $pick('performer'),
      'sprinkler'=> $pick('sprinkler'),
      'hydrant'=> $pick('hydrant'),
      'facility_setup_done'=> $pick('facility_setup_done'),
      'note_sobang' => $pick('note_sobang'),
      'note_pinan'  => $pick('note_pinan'),
      'note_hwagi'  => $pick('note_hwagi'),
      'note_etc'    => $pick('note_etc'),
    ];
    save_json($FIXED_FILE, $fixed);
    $saved = '확인내용 기본값이 저장되었습니다.';
  }
}

$fixed = sync_worklog_fixed_with_basic($fixed);
save_json($FIXED_FILE, $fixed);
$noteProg = worklog_note_progress($fixed);
$biProg = bi_progress();
$biDone = $biProg['filled'] >= $biProg['total'];
$sprinkler = (string)($fixed['sprinkler'] ?? '');
$sprinklerSet = in_array($sprinkler, ['yes', 'no'], true);
$hydrant = (string)($fixed['hydrant'] ?? '');
$setupComplete = $noteProg['filled'] >= $noteProg['total'] && !empty($fixed['facility_setup_done']);
$showQuickSetup = empty($fixed['facility_setup_done']);

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
$currentMonthKey = date('Y-m');
$currentMonthLabel = date('Y년 n월');
$currentMonthDone = isset($existing[$currentMonthKey]);

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
.wrap{max-width:1120px;margin:0 auto;padding:28px 24px 80px;display:flex;flex-direction:column}
.setup-overview{display:none}
.setup-card{display:flex;align-items:center;gap:12px;min-width:0;background:#fff;border:1px solid var(--bd);
  border-radius:12px;padding:14px 16px;color:var(--fg);transition:.15s}
.setup-card:hover{border-color:#93b4ed;box-shadow:0 7px 18px rgba(37,99,235,.07)}
.setup-card__icon{width:36px;height:36px;flex:0 0 36px;border-radius:9px;background:#eef4ff;
  color:var(--brand2);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800}
.setup-card__text{flex:1;min-width:0}
.setup-card__text b{display:block;font-size:13.5px}
.setup-card__text small{display:block;font-size:11.5px;color:var(--mut2);margin-top:2px;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.setup-card__state{flex-shrink:0;font-size:11px;font-weight:800;padding:3px 8px;border-radius:999px;
  background:#f0fdf4;color:#15803d}
.setup-card__state.needs{background:#fff7ed;color:#c2410c}
.monthly-section{order:1}
.setup-details{order:3}
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
.monthguide{display:flex;align-items:center;gap:14px;flex-wrap:wrap;background:#fff7ed;
  border:1px solid #fdba74;border-radius:12px;padding:14px 16px;margin-bottom:20px;color:#9a3412}
.monthguide__month{display:inline-flex;align-items:center;justify-content:center;min-width:50px;height:50px;
  border-radius:10px;background:#fff;border:1px solid #fed7aa;font-size:15px;font-weight:800;color:#c2410c}
.monthguide__text{flex:1;min-width:220px}
.monthguide__text b{display:block;font-size:14px;color:#9a3412}
.monthguide__text small{display:block;font-size:12px;color:#b45309;margin-top:2px}
.btn--month{background:#ea580c;border-color:#ea580c;color:#fff;white-space:nowrap;
  box-shadow:0 0 0 4px rgba(234,88,12,.16);animation:monthNudge 1.5s ease-in-out infinite}
.btn--month:hover{background:#c2410c;border-color:#c2410c;color:#fff}
@keyframes monthNudge{0%,100%{transform:translateY(0)}50%{transform:translateY(-3px)}}
@media(prefers-reduced-motion:reduce){.btn--month{animation:none}}
.mcard--attention{border:2px solid #f59e0b;background:#fffbeb;
  box-shadow:0 8px 20px rgba(217,119,6,.10)}
.mcard--attention:hover{border-color:#ea580c;box-shadow:0 10px 24px rgba(217,119,6,.16)}
.mcard--attention .todo{color:#c2410c;font-weight:800}
.mcard--attention .mtoolbar a{display:inline-flex;background:#ea580c;color:#fff;
  border-radius:8px;padding:6px 10px;font-weight:800}
.current-tag{display:inline-flex;vertical-align:middle;margin-left:6px;padding:2px 7px;border-radius:999px;
  background:#ffedd5;color:#c2410c;font-size:10px;font-weight:800}
@media(max-width:680px){.nav__inner{padding:0 16px}.page-head__inner{padding:32px 20px 26px}}
@media(max-width:680px){.setup-overview{grid-template-columns:1fr}}
@media(max-width:560px){.monthguide .btn{width:100%;justify-content:center}.monthguide__text{min-width:0}}
.notehd{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:22px 0 4px;padding-top:18px;border-top:1px solid var(--bd)}
.notehd h3{font-size:15px;font-weight:800}
.noteprog{font-size:11.5px;font-weight:800;padding:3px 10px;border-radius:999px;background:#fff7ed;color:#b45309}
.noteprog.is-done{background:#f0fdf4;color:#15803d}
.sprinkler-setup{display:flex;align-items:center;gap:14px;flex-wrap:wrap;background:#f7faff;
  border:1px solid #cfe0fb;border-radius:12px;padding:15px 16px;margin:14px 0 18px}
.sprinkler-setup__text{flex:1;min-width:230px}
.sprinkler-setup__text b{display:block;font-size:14px;color:#1e3a5f}
.sprinkler-setup__text small{display:block;font-size:12px;color:var(--mut2);margin-top:3px;line-height:1.55}
.grade-chip{display:inline-flex;margin-right:6px;padding:2px 8px;border-radius:999px;background:#dcfce7;
  color:#15803d;font-size:11px;font-weight:800;vertical-align:1px}
.sprinkler-options{display:flex;gap:8px;flex-wrap:wrap}
.sprinkler-choice{border:1px solid #b9cbe5;background:#fff;color:#244564;border-radius:9px;
  padding:9px 13px;font-size:12.5px;font-weight:800;cursor:pointer;font-family:inherit}
.sprinkler-choice:hover{border-color:var(--brand);color:var(--brand2);background:#eef5ff}
.sprinkler-choice.is-selected{background:var(--brand);border-color:var(--brand);color:#fff;
  box-shadow:0 0 0 3px rgba(37,99,235,.13)}
.month-titlebar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:4px}
.month-titlebar h2{margin:0}.settings-jump{padding:7px 11px;font-size:12px;white-space:nowrap}
.quick-mask{position:fixed;inset:0;z-index:1000;display:flex;align-items:center;justify-content:center;
  padding:20px;background:rgba(15,23,42,.56);backdrop-filter:blur(3px)}
.quick-card{width:min(460px,100%);padding:25px;border-radius:18px;background:#fff;box-shadow:0 24px 70px rgba(15,23,42,.28)}
.quick-card h2{font-size:21px;margin-bottom:7px}.quick-card>p{color:var(--mut2);font-size:13px;line-height:1.65;margin-bottom:20px}
.quick-q{padding:15px 0;border-top:1px solid var(--bd)}.quick-q__head{display:flex;align-items:center;justify-content:space-between;gap:15px;font-weight:750}
.quick-choices{display:flex;gap:6px}.quick-choice{position:relative}.quick-choice input{position:absolute;opacity:0}
.quick-choice span{display:inline-flex;min-width:55px;justify-content:center;padding:8px 12px;border:1px solid var(--bd2);border-radius:9px;background:#f8fafc;color:var(--mut2);font-size:13px;cursor:pointer}
.quick-choice input:checked+span{border-color:var(--brand);background:#eff6ff;color:var(--brand2);font-weight:800}
.quick-submit{width:100%;justify-content:center;margin-top:17px;padding:11px}
@media(max-width:560px){.sprinkler-options,.sprinkler-choice{width:100%}.sprinkler-choice{justify-content:center}}
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
    <a class="nav__brand" href="/index.php">YEOHUB</a>
    <div class="nav__right">
      <span><?=h($nick)?>님</span>
      <a class="btn" href="<?=h($url('/building_manager.php'))?>">← 메인</a>
      <a class="btn" href="/logout.php">로그아웃</a>
    </div>
  </div>
</nav>

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
  <?php if (($_GET['setup'] ?? '') === 'done'): ?><div class="toast">소방시설 기본값을 저장했습니다.</div><?php endif; ?>

  <div class="setup-overview">
    <a class="setup-card" href="#buildingSetup" onclick="return openSetup('buildingSetup')">
      <span class="setup-card__icon">건물</span>
      <span class="setup-card__text">
        <b>건물정보</b>
        <small><?=h(trim((string)($fixed['sangho'] ?? '')) ?: '대상물명 미입력')?> · <?=h(trim((string)($fixed['grade'] ?? '')) ?: '등급 미입력')?></small>
      </span>
      <span class="setup-card__state <?= $biDone ? '' : 'needs' ?>"><?= $biDone ? '완료' : '확인 필요' ?></span>
    </a>
    <a class="setup-card" href="#defaultSetup" onclick="return openSetup('defaultSetup')">
      <span class="setup-card__icon">기본</span>
      <span class="setup-card__text">
        <b>확인내용 기본값</b>
        <small>
          <?php if ($sprinklerSet): ?>스프링클러 <?= $sprinkler === 'yes' ? '있음' : '없음' ?> · <?=$noteProg['filled']?>/<?=$noteProg['total']?> 저장
          <?php else: ?>스프링클러 여부만 확인하면 자동 저장<?php endif; ?>
        </small>
      </span>
      <?php $defaultsComplete = $sprinklerSet && $noteProg['filled'] >= $noteProg['total']; ?>
      <span class="setup-card__state <?= $defaultsComplete ? '' : 'needs' ?>"><?= $defaultsComplete ? '완료' : '확인 필요' ?></span>
    </a>
  </div>

  <details class="section setup-details" id="setupDetails">
    <summary style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;cursor:pointer;list-style:none">
    <h2 style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0">
      소방시설 기본값 수정
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
      펼쳐보기
    </span>
    </summary>
    <div class="desc">매월 기록에 자동으로 들어갈 문구입니다. 시설이나 점검 방법이 바뀌었을 때만 수정하세요.</div>
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

      <div class="desc" style="margin-bottom:14px">
        매월 기록표에 <b>자동으로 채워질 문장</b>입니다. 한 번 적어두면 매달 그대로 들어가고,
        그 달에 특별한 일이 있으면 그때만 고치시면 됩니다.
      </div>

      <?php
        $noteSamples = worklog_note_defaults($sprinkler === 'yes' ? 'yes' : 'no', $hydrant === 'yes' ? 'yes' : 'no');
        $noteFields = [
          'note_sobang' => ['소방시설', '소화기·스프링클러 등 건물에 설치된 소방시설을 점검한 내용',
            $noteSamples['note_sobang']],
          'note_pinan' => ['피난방화시설', '비상구·피난통로·유도등·방화문 상태를 확인한 내용',
            $noteSamples['note_pinan']],
          'note_hwagi' => ['화기취급감독', '불을 쓰는 곳과 그 주변을 살핀 내용',
            $noteSamples['note_hwagi']],
          'note_etc' => ['기타사항', '위에 해당하지 않는 확인 사항', $noteSamples['note_etc']],
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
    function openSetup(targetId){
      var details = document.getElementById('setupDetails');
      if (details) details.open = true;
      window.setTimeout(function(){
        var target = document.getElementById(targetId);
        if (target) target.scrollIntoView({behavior:'smooth', block:'start'});
      }, 0);
      return false;
    }
    </script>
  </details>

  <div class="section monthly-section">
    <div class="month-titlebar">
      <h2>월별 기록 (최근 2년)</h2>
      <button class="btn settings-jump" type="button" onclick="openDefaultSettings()">⚙ 기본값 수정</button>
    </div>
    <div class="desc">작성할 달을 선택하세요. 작성 완료된 달은 초록색으로 표시됩니다. 연도별로 한 번에 인쇄·PDF 저장할 수 있습니다.</div>
    <?php if (!$hasFixed): ?>
      <div class="warn">상단 <b>건물정보</b> 카드에서 상세 설정을 열어 기본정보를 먼저 확인해 주세요.</div>
    <?php endif; ?>

    <?php if (!$currentMonthDone): ?>
      <div class="monthguide" role="status">
        <span class="monthguide__month"><?=h(date('n'))?>월</span>
        <div class="monthguide__text">
          <b><?=h($currentMonthLabel)?> 업무 수행 기록이 아직 없습니다.</b>
          <small>이번 달 점검 내용을 작성하고 저장해 주세요.</small>
        </div>
        <a class="btn btn--month" href="<?=h($url('/work_log_form.php?month=' . rawurlencode($currentMonthKey)))?>">
          이번 달 업무 수행 작성하기 →
        </a>
      </div>
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
          <?php foreach ($list as $mo):
            $isCurrentMonth = $mo['key'] === $currentMonthKey;
            $needsCurrentMonth = $isCurrentMonth && !$mo['done'];
          ?>
            <div class="mcard<?= $needsCurrentMonth ? ' mcard--attention' : '' ?>"<?= $isCurrentMonth ? ' aria-current="date"' : '' ?>>
              <div class="mlabel"><?=h($mo['label'])?><?php if ($isCurrentMonth): ?><span class="current-tag">이번 달</span><?php endif; ?></div>
              <div class="mstat">
                <?php if ($mo['done']): ?>
                  <span class="done">✓ 작성 완료</span>
                <?php elseif ($needsCurrentMonth): ?>
                  <span class="todo">업무 수행 필요</span>
                <?php else: ?>
                  <span class="todo">미작성</span>
                <?php endif; ?>
              </div>
              <div class="mtoolbar">
                <a href="<?=h($url('/work_log_form.php?month=' . rawurlencode($mo['key'])))?>"><?= $mo['done'] ? '수정/인쇄 →' : ($needsCurrentMonth ? '이번 달 작성하기 →' : '작성하기 →') ?></a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($showQuickSetup): ?>
    <div class="quick-mask" role="dialog" aria-modal="true" aria-labelledby="quickSetupTitle">
      <form class="quick-card" method="post">
        <input type="hidden" name="action" value="save_fixed">
        <input type="hidden" name="apply_defaults" value="quick">
        <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
        <h2 id="quickSetupTitle">소방시설 기본 설정</h2>
        <p>두 가지만 알려주세요. 월별 기록에 사용할 점검 문구를 자동으로 준비합니다.</p>
        <div class="quick-q"><div class="quick-q__head"><span>스프링클러가 있습니까?</span><span class="quick-choices">
          <label class="quick-choice"><input type="radio" name="has_sprinkler" value="yes" required><span>있음</span></label>
          <label class="quick-choice"><input type="radio" name="has_sprinkler" value="no"><span>없음</span></label>
        </span></div></div>
        <div class="quick-q"><div class="quick-q__head"><span>옥내소화전이 있습니까?</span><span class="quick-choices">
          <label class="quick-choice"><input type="radio" name="has_hydrant" value="yes" required><span>있음</span></label>
          <label class="quick-choice"><input type="radio" name="has_hydrant" value="no"><span>없음</span></label>
        </span></div></div>
        <button class="btn btn--primary quick-submit" type="submit">기본값 저장하고 월별 기록 보기</button>
      </form>
    </div>
  <?php endif; ?>

  <script>
  function openDefaultSettings(){
    var box = document.getElementById('setupDetails');
    if (!box) return;
    box.open = true;
    box.scrollIntoView({behavior:'smooth', block:'start'});
  }
  </script>

</main>

</body>
</html>
