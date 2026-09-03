<?php
/* =============================================================
   fire_plan_chat.php — 소방계획서 문답 작성
   ─────────────────────────────────────────────────────────────
   지금까지 입력해 둔 것을 최대한 끌어와 먼저 채우고,
   남은 것만 하나씩 여쭤봅니다.

     · 건물 기본정보 (building_info)  → 항목1 일반현황 대부분
     · 공통 피난계획                    → 항목5 피난경로·집결지
     · 자위소방대 편성표 (_jawi.json) → 항목9 조직·임무, 항목14 초기대응
     · 업무수행 기록표 기본값          → 항목13 업무수행 기록·유지
     · 소방훈련·교육 기록              → 항목11 훈련·교육 계획

   나머지 세부 항목은 표 화면(fire_plan_edit.php)에서 다듬습니다.
   ============================================================= */
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

require_once __DIR__ . '/fire_plan_db.php';
require_once __DIR__ . '/building_info.php';
require_once __DIR__ . '/evacuation_plan_common.php';

$USAGES = fp_usages();

/* ── 계획서 준비 ───────────────────────────────────────────
   id 가 없으면 만들어 줍니다. 용도는 나중에 문답에서 고칩니다. */
$planId = (string)($_GET['id'] ?? '');
$plan   = $planId !== '' ? fp_load_plan($planId) : null;
if (!$plan) {
  $usage = (string)($_GET['usage'] ?? 'business');
  if (!isset($USAGES[$usage])) $usage = 'business';
  $planId = fp_create_plan($usage);
  header('Location: /fire_plan_chat.php?id=' . urlencode($planId)); exit;
}

/* ── 지금까지 모아둔 자료 ─────────────────────────────────── */
$bi   = bi_load();
$mgrs = is_array($bi['mgrs'] ?? null) ? $bi['mgrs'] : [];

/* 주 담당 소방안전관리자 (없으면 첫 사람) */
$mgrName = ''; $mgrTel = '';
foreach ($mgrs as $m) {
  if (!is_array($m)) continue;
  $n = trim((string)($m['name'] ?? ''));
  if ($n === '') continue;
  if ($mgrName === '') { $mgrName = $n; $mgrTel = trim((string)($m['tel'] ?? '')); }
  if (strpos((string)($m['type'] ?? ''), '주') === 0) {
    $mgrName = $n; $mgrTel = trim((string)($m['tel'] ?? '')); break;
  }
}

/* 자위소방대 편성표 */
$TEAM = ['found'=>false, 'total'=>0, 'chief'=>'', 'deputy'=>'',
         'summary'=>'', 'groups'=>[], 'early'=>[], 'early_total'=>0];
$fpKey = fp_user_key();
if ($fpKey !== '') {
  $jf = __DIR__ . '/data/fireplan/' . $fpKey . '/_jawi.json';
  if (is_file($jf)) {
    $ja = json_decode((string)@file_get_contents($jf), true);
    /* fire_plan_jawi.php는 최신 편성표를 배열 맨 앞에 저장합니다. */
    $jp = (is_array($ja) && $ja) ? ($ja[0] ?? null) : null;
    if (is_array($jp)) {
      $n = 0; $lines = []; $groups = []; $early = []; $earlyN = 0;
      $cn = trim((string)($jp['cmd']['name'] ?? ''));
      if ($cn !== '') { $TEAM['chief'] = $cn; $n++; $lines[] = '대장 ' . $cn; }
      $dn = trim((string)($jp['deputy']['name'] ?? ''));
      if ($dn !== '') { $TEAM['deputy'] = $dn; $n++; $lines[] = '부대장 ' . $dn; }
      foreach ((array)($jp['groups'] ?? []) as $g) {
        $gn = trim((string)($g['name'] ?? ''));
        $names = []; $tasks = [];
        foreach ((array)($g['members'] ?? []) as $mm) {
          $nm = trim((string)($mm['name'] ?? ''));
          if ($nm === '') continue;
          $names[] = $nm; $n++;
          $tk = trim((string)($mm['task'] ?? ''));
          if ($tk !== '' && !in_array($tk, $tasks, true)) $tasks[] = $tk;
        }
        if (!$names) continue;
        $groups[] = ['name'=>$gn, 'names'=>$names, 'tasks'=>$tasks, 'count'=>count($names)];
        $lines[] = $gn . ' ' . count($names) . '명';
        $flat = str_replace(' ', '', $gn);
        foreach (['비상연락','통보','초기소화','소화','피난','유도'] as $k) {
          if (strpos($flat, $k) !== false) {
            $early[] = $gn . ' ' . count($names) . '명';
            $earlyN += count($names);
            break;
          }
        }
      }
      if ($n > 0) {
        $TEAM['found'] = true; $TEAM['total'] = $n;
        $TEAM['summary'] = implode(' · ', $lines);
        $TEAM['groups'] = $groups;
        $TEAM['early'] = $early; $TEAM['early_total'] = $earlyN;
      }
    }
  }
}

/* 최근 소방훈련·교육 실시 기록 (항목11 계획의 근거로 씁니다) */
$LASTTRAIN = '';
$trKey = $_SESSION['member_id'] ?? ('kakao_' . ($_SESSION['kakao_id'] ?? ''));
$trIdx = __DIR__ . '/data/train/' . $trKey . '/_index.json';
if (is_file($trIdx)) {
  $ti = json_decode((string)@file_get_contents($trIdx), true);
  if (is_array($ti) && $ti) {
    $dates = [];
    foreach ($ti as $row) {
      $dt = trim((string)($row['fire_date'] ?? $row['date'] ?? ''));
      if ($dt !== '') $dates[] = substr($dt, 0, 10);
    }
    if ($dates) { rsort($dates); $LASTTRAIN = $dates[0]; }
  }
}

/* 진행률 — 생략(해당없음) 처리된 항목은 분모에서 뺍니다 */
function fp_progress_of(?array $p): array {
  $total = 0;
  foreach (fp_sections() as $ch) $total += count($ch['items']);
  if (!$p) return ['done'=>0, 'total'=>$total];
  $st = fp_count_states($p);
  $skip = (int)($st['skip'] ?? 0);
  return ['done'=>(int)($st['done'] ?? 0), 'total'=>max(1, $total - $skip)];
}

/* ── 저장 (fetch 로 들어옴) ──────────────────────────────── */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['act'] ?? '') === 'save_step') {
  header('Content-Type: application/json; charset=utf-8');
  if (!hash_equals(fp_csrf(), (string)($_POST['csrf'] ?? ''))) {
    echo json_encode(['ok'=>false,'error'=>'세션이 만료되었습니다. 새로고침 후 다시 시도해 주세요.']); exit;
  }

  $code  = (string)($_POST['code'] ?? '1');
  $patch = json_decode((string)($_POST['patch'] ?? '{}'), true);
  if (!is_array($patch)) $patch = [];

  $cur = fp_get_section($planId, $code);
  foreach ($patch as $k => $v) {
    $cur[$k] = is_array($v) ? array_values(array_map('strval', $v)) : (string)$v;
  }
  unset($cur['is_skipped'], $cur['skip_reason'], $cur['is_done']);

  $done = $code === '1' ? (trim((string)($cur['name'] ?? '')) !== '')
                        : (trim((string)($cur['memo'] ?? '')) !== '' || count($cur) > 0);
  fp_save_section($planId, $code, $cur, $done);

  if ($code === '1') {
    fp_apply_skips($planId, fp_skip_rules($cur));
    fp_update_shared($planId, (string)($cur['name'] ?? ''), fp_jawi_type($cur), null);
  }

  $p2 = fp_load_plan($planId);
  echo json_encode(['ok'=>true] + fp_progress_of($p2), JSON_UNESCAPED_UNICODE);
  exit;
}

/* ── 처음부터 다시 ─────────────────────────────────────── */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['act'] ?? '') === 'reset') {
  fp_csrf_check();
  $p = fp_load_plan($planId);
  if ($p) {
    foreach (array_keys((array)($p['sections'] ?? [])) as $code) {
      fp_save_section($planId, (string)$code, [], false);
    }
  }
  header('Location: /fire_plan_chat.php?id=' . urlencode($planId) . '&reset=1'); exit;
}

$s1    = fp_get_section($planId, '1');
$savedEvac = fp_get_section($planId, '5');
$commonEvac = epc_load();
$commonEvacStatus = epc_status($commonEvac);
$AUTOEVAC = epc_missing_patch($savedEvac, epc_to_fire_section($commonEvac));
$state = fp_progress_of($plan);
$nick  = $_SESSION['nickname'] ?? '사용자';
$CSRF  = fp_csrf();

/* 기본정보에서 곧바로 옮겨 담을 수 있는 항목1 값 */
$floors = '';
$fa = trim((string)($bi['floor_a'] ?? ''));
$fb = trim((string)($bi['floor_b'] ?? ''));
if ($fa !== '' || $fb !== '') {
  $floors = ($fb !== '' && $fb !== '0' ? '지하 ' . $fb . '층 / ' : '') . ($fa !== '' ? '지상 ' . $fa . '층' : '');
}
$staffSum = (int)($bi['wd_day'] ?? 0) + (int)($bi['wd_night'] ?? 0);

$AUTO1 = [
  'name'     => (string)($bi['name'] ?? ''),
  'addr'     => (string)($bi['address'] ?? ''),
  'rep_name' => (string)($bi['rep'] ?? ''),
  'rep_tel'  => (string)($bi['tel'] ?? ''),
  'mgr_name' => $mgrName,
  'mgr_tel'  => $mgrTel,
  'grade'    => (string)($bi['grade'] ?? ''),
  'main_use' => (string)($bi['use'] ?? ''),
  'area'     => (string)($bi['area_t'] ?? ''),
  'bld_area' => (string)($bi['area_f'] ?? ''),
  'floors'   => $floors,
  'wd_day'   => (string)($bi['wd_day'] ?? ''),
  'wd_night' => (string)($bi['wd_night'] ?? ''),
  'hd_day'   => (string)($bi['hd_day'] ?? ''),
  'hd_night' => (string)($bi['hd_night'] ?? ''),
  'staff'    => $staffSum > 0 ? (string)$staffSum : '',
];
$AUTO1 = array_filter($AUTO1, function($v){ return trim((string)$v) !== ''; });

/* 편성표·업무수행 기본값으로 만든 서술형 문구 (항목 9·11·13·14) */
$AUTOMEMO = [];
if ($TEAM['found']) {
  $t = [];
  if ($TEAM['chief']  !== '') $t[] = '자위소방대장 ' . $TEAM['chief'];
  if ($TEAM['deputy'] !== '') $t[] = '부대장 ' . $TEAM['deputy'];
  foreach ($TEAM['groups'] as $g) {
    $line = $g['name'] . '(' . $g['count'] . '명) : ' . implode(', ', $g['names']);
    if ($g['tasks']) $line .= ' — ' . implode(' / ', $g['tasks']);
    $t[] = $line;
  }
  $AUTOMEMO['9'] = "자위소방대는 아래와 같이 편성하며, 각 조는 지정된 임무를 수행한다.\n\n"
    . implode("\n", $t)
    . "\n\n※ 인사이동 등으로 편성이 바뀌면 편성표를 갱신하고 본 계획서에 반영한다.";

  if ($TEAM['early']) {
    $AUTOMEMO['14'] = "화재를 발견하면 초기대응체계가 즉시 가동된다.\n\n"
      . "1) 화재 발견자는 육성·비상벨로 알리고 방재실에 통보한다.\n"
      . "2) 방재실은 119에 신고하고 비상방송으로 전관에 전파한다.\n"
      . "3) 초기소화 담당은 소화기·옥내소화전으로 초기 진화한다.\n"
      . "4) 피난유도 담당은 피난경로를 확보하고 재실자를 대피시킨다.\n\n"
      . "초기대응체계 편성 : " . implode(', ', $TEAM['early'])
      . " (모두 " . $TEAM['early_total'] . "명)";
  }
}
$notes = array_filter([
  '소방시설'     => (string)($bi['note_sobang'] ?? ''),
  '피난·방화시설'=> (string)($bi['note_pinan'] ?? ''),
  '화기취급'     => (string)($bi['note_hwagi'] ?? ''),
  '기타'         => (string)($bi['note_etc'] ?? ''),
], function($v){ return trim($v) !== ''; });
if ($notes) {
  $l = [];
  foreach ($notes as $k => $v) $l[] = '· ' . $k . ' : ' . $v;
  $AUTOMEMO['13'] = "소방안전관리 업무수행 기록표(별지 제12호)를 매월 작성하여 2년간 보관한다.\n"
    . "매월 확인하는 기본 내용은 다음과 같다.\n\n" . implode("\n", $l)
    . "\n\n작성자 : " . ($mgrName !== '' ? $mgrName : '소방안전관리자');
}
if ($LASTTRAIN !== '' || $TEAM['found']) {
  $l = ["소방훈련 및 교육은 연 1회 이상 실시하고, 실시 결과는 별지 제28호 서식으로 기록하여 2년간 보관한다.", ""];
  if ($TEAM['found']) $l[] = '대상 : 자위소방대원 ' . $TEAM['total'] . '명 및 상시 근무자';
  $l[] = '내용 : 소화·통보·피난 훈련과 소방안전교육';
  $l[] = '주관 : ' . ($mgrName !== '' ? $mgrName : '소방안전관리자');
  if ($LASTTRAIN !== '') $l[] = '최근 실시 : ' . $LASTTRAIN;
  $AUTOMEMO['11'] = implode("\n", $l);
}
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>소방계획서 문답 작성 — TWORIX</title>
<style>
:root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--bd2:#d4dbe6;--fg:#1a2436;
  --mut:#7a8699;--mut2:#56627a;--brand:#2563eb;--brand2:#1d4ed8;--accent:#0891b2}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--fg);line-height:1.65;
  font-family:Inter,system-ui,"Apple SD Gothic Neo","Malgun Gothic",sans-serif}
a{text-decoration:none;color:inherit} button{font:inherit;color:inherit;cursor:pointer}
.nav{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.94);
  backdrop-filter:blur(10px);border-bottom:1px solid var(--bd)}
.nav__in{max-width:760px;margin:0 auto;padding:0 20px;height:56px;
  display:flex;align-items:center;justify-content:space-between;gap:12px}
.brand{font-weight:800;font-size:21px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 15px;border-radius:9px;
  border:1px solid var(--bd2);background:#fff;font-size:13px;font-weight:600;transition:.15s}
.btn:hover{border-color:var(--brand);color:var(--brand2)}
.btn--pri{background:var(--brand);border-color:var(--brand);color:#fff}
.btn--pri:hover{background:var(--brand2);color:#fff}
.btn--sm{padding:6px 12px;font-size:12.5px}
.prog{position:sticky;top:56px;z-index:45;background:#fff;border-bottom:1px solid var(--bd)}
.prog__in{max-width:760px;margin:0 auto;padding:11px 20px}
.prog__row{display:flex;justify-content:space-between;font-size:12.5px;color:var(--mut2);margin-bottom:6px}
.prog__row b{color:var(--brand2)}
.bar{height:6px;background:#eef2f7;border-radius:3px;overflow:hidden}
.bar i{display:block;height:100%;background:var(--brand);width:0;transition:width .45s cubic-bezier(.2,.7,.3,1)}
.wrap{max-width:760px;margin:0 auto;padding:24px 20px 70px}
.msg{display:flex;gap:11px;margin-bottom:15px;animation:pop .28s ease both}
@keyframes pop{from{opacity:0;transform:translateY(7px)}to{opacity:1;transform:none}}
.msg__av{width:31px;height:31px;border-radius:9px;flex-shrink:0;display:flex;
  align-items:center;justify-content:center;font-size:15px;background:#eef2ff}
.msg__b{background:var(--card);border:1px solid var(--bd);border-radius:4px 14px 14px 14px;
  padding:14px 17px;max-width:calc(100% - 44px);font-size:14.8px;line-height:1.72}
.msg--me{flex-direction:row-reverse}
.msg--me .msg__av{background:#e6edfb}
.msg--me .msg__b{background:var(--brand);border-color:var(--brand);color:#fff;
  border-radius:14px 4px 14px 14px}
.msg__b b{font-weight:700}
.hint{font-size:12.5px;color:var(--mut);margin-top:9px;padding-top:9px;border-top:1px dashed var(--bd)}
.answer{margin:0 0 22px 42px}
.opts{display:flex;flex-wrap:wrap;gap:8px}
.opt{padding:9px 15px;border:1px solid var(--bd2);border-radius:999px;background:#fff;
  font-size:13.5px;font-weight:500;transition:.14s}
.opt:hover{border-color:var(--brand);color:var(--brand2);background:#f7faff}
.opt--long{border-radius:12px;text-align:left;line-height:1.55}
.opt.on{background:var(--brand);border-color:var(--brand);color:#fff}
.inrow{display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap}
.inrow input,.inrow textarea{flex:1;min-width:180px;padding:11px 14px;border:1px solid var(--bd2);
  border-radius:11px;background:#fff;font-size:14.8px;font-family:inherit}
.inrow textarea{min-height:120px;resize:vertical;line-height:1.7}
.subrow{display:flex;gap:8px;margin-top:9px;flex-wrap:wrap}
.nrow{display:flex;gap:8px;flex-wrap:wrap}
.nbox{flex:1;min-width:110px;background:#fff;border:1px solid var(--bd2);border-radius:11px;padding:9px 11px}
.nbox__l{font-size:11.5px;color:var(--mut);display:block;margin-bottom:3px}
.ncell{width:100%;border:0;font-size:16px;font-weight:700;font-family:inherit;color:var(--fg);background:none}
.ncell:focus{outline:none}
.summary{background:#fff;border:1px solid var(--bd);border-radius:14px;padding:16px 18px;
  margin-bottom:18px;font-size:13px;color:var(--mut2)}
.summary b{color:var(--fg)}
.summary__t{font-size:12px;font-weight:800;color:var(--brand2);margin-bottom:7px}
.srow{padding:3px 0}
.done{background:#fff;border:1px solid var(--bd);border-radius:14px;padding:22px;margin-left:42px}
.done h2{font-size:18px;font-weight:800;margin-bottom:12px}
.sum{display:flex;justify-content:space-between;gap:14px;padding:8px 0;
  border-top:1px solid var(--bd);font-size:14px;flex-wrap:wrap}
.sum:first-of-type{border-top:0}
.sum__k{color:var(--mut2);font-size:13px}
.sum__v{font-weight:600;text-align:right}
.sum__v.none{color:var(--mut);font-weight:400}
.doneRow{display:flex;gap:9px;flex-wrap:wrap;margin-top:18px}
.alert{display:flex;gap:11px;border-radius:12px;padding:14px 16px;font-size:14px;
  line-height:1.7;margin-bottom:18px;background:#fff7ed;border:1px solid #fed7aa;color:#92400e}
.typing{display:inline-flex;gap:4px;align-items:center;padding:3px 0}
.typing i{width:6px;height:6px;border-radius:50%;background:var(--mut);display:block;animation:blink 1.2s infinite}
.typing i:nth-child(2){animation-delay:.18s}
.typing i:nth-child(3){animation-delay:.36s}
@keyframes blink{0%,60%,100%{opacity:.28}30%{opacity:1}}
@media(max-width:560px){.answer,.done{margin-left:0}.msg__b{max-width:calc(100% - 42px)}}
</style>
</head>
<body>
<nav class="nav"><div class="nav__in">
  <a class="brand" href="/index.php">YEOHUB</a>
  <div style="display:flex;gap:8px">
    <form method="post" style="display:inline"
      onsubmit="return confirm('입력한 내용을 모두 지우고 처음부터 다시 시작합니다.\n계속할까요?')">
      <input type="hidden" name="act" value="reset">
      <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
      <button class="btn" type="submit">↺ 처음부터 다시</button>
    </form>
    <a class="btn" href="/fire_plan_edit.php?id=<?=h(rawurlencode($planId))?>">표에서 편집</a>
    <a class="btn" href="/fire_plan.php">← 목록</a>
  </div>
</div></nav>

<div class="prog"><div class="prog__in">
  <div class="prog__row"><span>소방계획서 작성</span>
    <span><b id="pPct">0%</b> · <span id="pNum">0/15</span></span></div>
  <div class="bar"><i id="pBar" style="width:0%"></i></div>
</div></div>

<main class="wrap">
  <?php if (trim((string)($bi['name'] ?? '')) === ''): ?>
    <div class="alert">건물 기본정보가 아직 비어 있습니다.
      먼저 <a href="/building_setup_chat.php" style="text-decoration:underline">기본정보</a>를 입력하시면
      대상물 현황이 자동으로 채워져 훨씬 빨리 끝납니다.</div>
  <?php endif; ?>

  <div class="summary">
    <div class="summary__t">이미 입력해 두신 것에서 가져옵니다</div>
    <div class="srow">· 건물 기본정보 —
      <b><?=h($AUTO1['name'] ?? '미입력')?></b>
      <?= isset($AUTO1['addr']) ? ' · ' . h($AUTO1['addr']) : '' ?>
      <?= isset($AUTO1['grade']) ? ' · ' . h($AUTO1['grade']) : '' ?></div>
    <div class="srow">· 공통 피난계획 —
      <?php if ($commonEvacStatus['has_content']): ?>
        <b><?= (int)$commonEvacStatus['floor_count'] ?>개 층</b>
        <?= $commonEvacStatus['updated'] !== '' ? ' · 최근 수정 ' . h(substr((string)$commonEvacStatus['updated'],0,16)) : '' ?>
      <?php else: ?>아직 없음<?php endif; ?></div>
    <div class="srow">· 자위소방대 편성표 —
      <?= $TEAM['found'] ? '<b>' . (int)$TEAM['total'] . '명</b> · ' . h($TEAM['summary']) : '아직 없음' ?></div>
    <div class="srow">· 업무수행 기록표 기본값 —
      <?= $notes ? '<b>' . count($notes) . '개 항목</b>' : '아직 없음' ?></div>
    <div class="srow">· 소방훈련·교육 기록 —
      <?= $LASTTRAIN !== '' ? '최근 <b>' . h($LASTTRAIN) . '</b>' : '아직 없음' ?></div>
  </div>

  <div id="chat"></div>
</main>

<script>
var CSRF   = <?=json_encode($CSRF)?>;
var PLANID = <?=json_encode($planId)?>;
var NICK   = <?=json_encode($nick, JSON_UNESCAPED_UNICODE)?>;
var S1     = <?=json_encode($s1, JSON_UNESCAPED_UNICODE)?>;
var AUTO1  = <?=json_encode($AUTO1, JSON_UNESCAPED_UNICODE)?>;
var SAVED_EVAC = <?=json_encode($savedEvac, JSON_UNESCAPED_UNICODE)?>;
var AUTOEVAC = <?=json_encode($AUTOEVAC, JSON_UNESCAPED_UNICODE)?>;
var COMMON_EVAC_STATUS = <?=json_encode($commonEvacStatus, JSON_UNESCAPED_UNICODE)?>;
var AUTOMEMO = <?=json_encode($AUTOMEMO, JSON_UNESCAPED_UNICODE)?>;
var TEAM   = <?=json_encode($TEAM, JSON_UNESCAPED_UNICODE)?>;
var USAGES = <?=json_encode($USAGES, JSON_UNESCAPED_UNICODE)?>;
var USAGE  = <?=json_encode((string)($plan['usage_code'] ?? 'business'), JSON_UNESCAPED_UNICODE)?>;
var STATE  = <?=json_encode($state, JSON_UNESCAPED_UNICODE)?>;

var chat = document.getElementById('chat');
var SAVED = {};                       // 항목1 누적값
for (var k in S1) SAVED[k] = S1[k];
var step = 0;

function esc(s){ return String(s==null?'':s)
  .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function md(s){ return esc(s).replace(/\*\*(.+?)\*\*/g,'<b>$1</b>').replace(/\n/g,'<br>'); }
function down(){ requestAnimationFrame(function(){
  window.scrollTo({top:document.body.scrollHeight, behavior:'smooth'}); }); }
function bot(html, hint){
  var d=document.createElement('div'); d.className='msg';
  d.innerHTML='<div class="msg__av">📋</div><div class="msg__b">'+html+
    (hint?'<div class="hint">'+esc(hint)+'</div>':'')+'</div>';
  chat.appendChild(d); down(); return d;
}
function me(t){
  var d=document.createElement('div'); d.className='msg msg--me';
  d.innerHTML='<div class="msg__av">🙂</div><div class="msg__b">'+esc(t)+'</div>';
  chat.appendChild(d); down();
}
function typing(cb){
  var d=bot('<span class="typing"><i></i><i></i><i></i></span>');
  setTimeout(function(){ d.remove(); cb(); }, 300);
}
function clearBox(){ var a=document.getElementById('ansBox'); if(a) a.remove(); }
function box(){ clearBox(); var d=document.createElement('div');
  d.className='answer'; d.id='ansBox'; chat.appendChild(d); down(); return d; }

function setProg(done, total){
  var pct = total ? Math.round(done/total*100) : 0;
  document.getElementById('pPct').textContent = pct + '%';
  document.getElementById('pNum').textContent = done + '/' + total;
  document.getElementById('pBar').style.width = pct + '%';
}
setProg(STATE.done||0, STATE.total||15);

function save(code, patch, done){
  var fd=new FormData();
  fd.append('act','save_step'); fd.append('csrf',CSRF);
  fd.append('code',code); fd.append('patch',JSON.stringify(patch));
  fetch(location.pathname+location.search,{method:'POST',body:fd,credentials:'same-origin'})
    .then(function(r){ return r.json(); })
    .then(function(j){
      if(j&&j.ok){ setProg(j.done,j.total); if(done) done(true); }
      else { bot(md('⚠️ '+((j&&j.error)?j.error:'저장하지 못했습니다.'))); if(done) done(false); }
    })
    .catch(function(){ bot(md('⚠️ 저장 중 연결이 끊겼습니다.')); if(done) done(false); });
}

/* ── 물어볼 것 — 기본정보로 못 채우는 것만 ───────────────── */
var STEPS = [
  { id:'main_use', q:'이 건물을 주로 어떤 용도로 쓰나요?', type:'choice+',
    options:['업무시설(오피스)','근린생활시설','판매시설','공동주택','숙박시설','교육연구시설','공장','창고'],
    hint:'소방계획서의 용도 구분에 쓰입니다.' },

  { id:'structure', q:'건물 구조는 어떻게 되나요?', type:'choice+',
    options:['철근콘크리트조','철골철근콘크리트조','철골조','조적조','목조'] },

  { id:'roof', q:'지붕은 어떤 형태인가요?', type:'choice+', skip:true,
    options:['평슬래브','경사지붕','철골 샌드위치패널','기타'] },

  { id:'recv_loc', q:'화재수신기는 어디에 있나요?', type:'choice+',
    options:['1층 방재실','1층 로비 관리실','지하 1층 기계실','경비실'],
    hint:'화재 발생 시 가장 먼저 확인하는 곳이라 계획서에 반드시 들어갑니다.' },

  { id:'use_cnt', q:'최대 수용인원은 몇 명 정도인가요?', type:'num',
    hint:'정확하지 않아도 됩니다. 대략적인 인원을 적어주세요.' },

  { id:'public', q:'특정소방대상물 중 공공기관에 해당하나요?', type:'yn' },
  { id:'split',  q:'건물의 관리 권원이 나뉘어 있나요?', type:'yn',
    hint:'층별·구역별로 소유자나 관리자가 다르면 «해당»입니다. 단일 소유면 «해당없음».' },
  { id:'joint',  q:'여러 관리자가 공동으로 소방안전관리를 하나요?', type:'yn' },
  { id:'hazmat', q:'위험물을 저장하거나 취급하나요?', type:'yn',
    hint:'지정수량 이상의 위험물이 있으면 «해당»입니다.' },

  { id:'ins', q:'화재보험에 가입되어 있나요?', type:'choice',
    options:['가입','미가입'] },
  { id:'ins_co', q:'보험사는 어디인가요?', type:'text', ph:'예: OO화재',
    only_ins:true, skip:true },

  { id:'__evac', q:'화재 시 대피 집결지는 어디인가요?', type:'text',
    ph:'예: 건물 앞 주차장', code:'5', field:'assembly',
    hint:'피난계획(항목5)에 들어갑니다.' }
];

function need(s){
  if (s.only_ins && SAVED.ins !== '가입') return false;
  return true;
}
function filled(s){
  if (s.code === '5') return String(SAVED_EVAC[s.field]||AUTOEVAC[s.field]||'').trim() !== '';
  if (s.code) return false;
  return String(SAVED[s.id]||'').trim() !== '';
}

/* ── 시작 ─────────────────────────────────────────────── */
function start(){
  var msg = '안녕하세요' + (NICK && NICK!=='사용자' ? ', ' + NICK + '님' : '') +
            '. 소방계획서를 함께 채워보겠습니다.\n\n';

  var got = [];
  if (Object.keys(AUTO1).length) got.push('건물 기본정보');
  if (COMMON_EVAC_STATUS.has_content) got.push('공통 피난계획');
  if (TEAM.found) got.push('자위소방대 편성표');
  if (AUTOMEMO['13']) got.push('업무수행 기록표 기본값');
  if (AUTOMEMO['11']) got.push('훈련·교육 기록');

  if (got.length){
    msg += '**' + got.join(' · ') + '**에서 가져올 수 있는 건 먼저 채워두겠습니다.\n' +
           '남은 것만 여쭤볼게요.';
  } else {
    msg += '항목을 하나씩 여쭤보겠습니다.';
  }
  bot(md(msg));
  typing(prefill);
}

/* 이미 아는 값을 한 번에 밀어 넣습니다 */
function prefill(){
  var patch1 = {};
  for (var k in AUTO1) if (!String(SAVED[k]||'').trim()) patch1[k] = AUTO1[k];

  var memoCodes = Object.keys(AUTOMEMO);
  var jobs = [];
  if (Object.keys(patch1).length) jobs.push(['1', patch1]);
  if (Object.keys(AUTOEVAC).length) jobs.push(['5', AUTOEVAC]);
  memoCodes.forEach(function(c){ jobs.push([c, {memo:AUTOMEMO[c]}]); });

  if (!jobs.length){ go(); return; }

  var lines = [];
  if (Object.keys(patch1).length) lines.push('항목1 일반현황 — ' + Object.keys(patch1).length + '칸');
  if (Object.keys(AUTOEVAC).length) lines.push('항목5 공통 피난계획');
  if (AUTOMEMO['9'])  lines.push('항목9 자위소방대 조직·임무');
  if (AUTOMEMO['11']) lines.push('항목11 소방훈련·교육 계획');
  if (AUTOMEMO['13']) lines.push('항목13 업무수행 기록·유지');
  if (AUTOMEMO['14']) lines.push('항목14 화재 초기대응');

  var i = 0;
  (function next(){
    if (i >= jobs.length){
      for (var k2 in patch1) SAVED[k2] = patch1[k2];
      for (var k3 in AUTOEVAC) SAVED_EVAC[k3] = AUTOEVAC[k3];
      bot(md('**채워 넣었습니다.**\n\n' + lines.map(function(l){ return '· ' + l; }).join('\n') +
             '\n\n내용은 나중에 «표에서 편집»으로 고칠 수 있습니다.'));
      setTimeout(go, 500);
      return;
    }
    save(jobs[i][0], jobs[i][1], function(){ i++; next(); });
  })();
}

function go(){
  while (step < STEPS.length && (!need(STEPS[step]) || filled(STEPS[step]))) step++;
  if (step >= STEPS.length){ finish(); return; }
  typing(function(){ ask(STEPS[step]); });
}

function put(s, value, label){
  clearBox(); me(label || value);
  if (s.code){                       // 항목1이 아닌 다른 섹션에 저장
    var p = {}; p[s.field] = value;
    if (s.code === '5') SAVED_EVAC[s.field] = value;
    save(s.code, p, function(){ step++; go(); });
    return;
  }
  var p1 = {}; p1[s.id] = value; SAVED[s.id] = value;
  save('1', p1, function(){ step++; go(); });
}

function ask(s){
  bot(md(s.q), s.hint);
  var b = box();

  if (s.type === 'choice' || s.type === 'choice+'){
    var w = document.createElement('div'); w.className = 'opts';
    (s.options||[]).forEach(function(o){
      var btn=document.createElement('button'); btn.className='opt'; btn.type='button';
      btn.textContent=o;
      btn.onclick=function(){ put(s, o, null); };
      w.appendChild(btn);
    });
    b.appendChild(w);
    if (s.type === 'choice+') addFree(b, s, '직접 입력');
    addSkip(b, s);
    return;
  }

  if (s.type === 'yn'){
    var w2 = document.createElement('div'); w2.className='opts';
    [['해당없음','아니요 · 해당없음'],['해당','네 · 해당됩니다']].forEach(function(pair){
      var btn=document.createElement('button'); btn.className='opt'; btn.type='button';
      btn.textContent=pair[1];
      btn.onclick=function(){ put(s, pair[0], pair[1]); };
      w2.appendChild(btn);
    });
    b.appendChild(w2);
    return;
  }

  if (s.type === 'num'){
    var row=document.createElement('div'); row.className='inrow';
    var inp=document.createElement('input'); inp.type='number'; inp.min='0';
    inp.placeholder = s.ph || '숫자만';
    row.appendChild(inp); b.appendChild(row);
    var sub=document.createElement('div'); sub.className='subrow';
    var go1=document.createElement('button'); go1.className='btn btn--pri'; go1.type='button';
    go1.textContent='넣기';
    go1.onclick=function(){ var v=(inp.value||'').trim(); if(v===''){inp.focus();return;} put(s, v, v+'명'); };
    sub.appendChild(go1); b.appendChild(sub);
    addSkipTo(sub, s);
    inp.addEventListener('keydown',function(e){ if(e.key==='Enter'){e.preventDefault();go1.click();} });
    inp.focus();
    return;
  }

  /* text */
  var row2=document.createElement('div'); row2.className='inrow';
  var inp2=document.createElement('input'); inp2.type='text'; inp2.placeholder=s.ph||'직접 입력';
  row2.appendChild(inp2); b.appendChild(row2);
  var sub2=document.createElement('div'); sub2.className='subrow';
  var go2=document.createElement('button'); go2.className='btn btn--pri'; go2.type='button';
  go2.textContent='넣기';
  go2.onclick=function(){ var v=(inp2.value||'').trim(); if(v===''){inp2.focus();return;} put(s, v, null); };
  sub2.appendChild(go2); b.appendChild(sub2);
  addSkipTo(sub2, s);
  inp2.addEventListener('keydown',function(e){ if(e.key==='Enter'){e.preventDefault();go2.click();} });
  inp2.focus();
}

function addFree(b, s, label){
  var row=document.createElement('div'); row.className='inrow'; row.style.marginTop='10px';
  var inp=document.createElement('input'); inp.type='text'; inp.placeholder=label;
  row.appendChild(inp); b.appendChild(row);
  var sub=document.createElement('div'); sub.className='subrow';
  var go1=document.createElement('button'); go1.className='btn btn--pri btn--sm'; go1.type='button';
  go1.textContent='직접 입력한 내용 넣기';
  go1.onclick=function(){ var v=(inp.value||'').trim(); if(v===''){inp.focus();return;} put(s, v, null); };
  sub.appendChild(go1); b.appendChild(sub);
  inp.addEventListener('keydown',function(e){ if(e.key==='Enter'){e.preventDefault();go1.click();} });
}
function addSkip(b, s){
  if (!s.skip) return;
  var sub=document.createElement('div'); sub.className='subrow';
  b.appendChild(sub); addSkipTo(sub, s);
}
function addSkipTo(sub, s){
  if (!s.skip) return;
  var sk=document.createElement('button'); sk.className='btn btn--sm'; sk.type='button';
  sk.textContent='건너뛰기';
  sk.onclick=function(){ clearBox(); me('건너뛰기'); step++; go(); };
  sub.appendChild(sk);
}

function finish(){
  clearBox();
  typing(function(){
    bot(md('**소방계획서 기본 작성이 끝났습니다.**\n\n' +
      '남은 세부 항목(소방시설 현황, 점검계획, 방화구획 등)은 ' +
      '표 화면에서 항목별로 채우시면 됩니다.'));

    var rows = [
      ['대상물명', SAVED.name],
      ['소재지', SAVED.addr],
      ['등급', SAVED.grade],
      ['주용도', SAVED.main_use],
      ['구조', SAVED.structure],
      ['수신기 위치', SAVED.recv_loc],
      ['최대 수용인원', SAVED.use_cnt ? SAVED.use_cnt + '명' : ''],
      ['자위소방대', TEAM.found ? (TEAM.total + '명 (편성표 반영)') : ''],
      ['권원분리 / 공동관리', (SAVED.split||'-') + ' / ' + (SAVED.joint||'-')],
      ['위험물', SAVED.hazmat],
      ['화재보험', SAVED.ins]
    ];
    var html = '<h2>소방계획서 요약</h2>';
    rows.forEach(function(r){
      var v = String(r[1]||'').trim();
      html += '<div class="sum"><span class="sum__k">'+esc(r[0])+'</span>'+
              '<span class="sum__v'+(v?'':' none')+'">'+esc(v||'비어 있음')+'</span></div>';
    });
    html += '<div class="doneRow">'+
      '<a class="btn btn--pri" href="/fire_plan_edit.php?id='+encodeURIComponent(PLANID)+'">표에서 이어서 작성 →</a>'+
      '<a class="btn" href="/fire_plan_print.php?id='+encodeURIComponent(PLANID)+'">🖨 인쇄 · PDF</a>'+
      '<a class="btn" href="/fire_plan.php">목록으로</a></div>';

    var d=document.createElement('div'); d.className='done'; d.innerHTML=html;
    chat.appendChild(d); down();
  });
}

start();
</script>
<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>
</html>
