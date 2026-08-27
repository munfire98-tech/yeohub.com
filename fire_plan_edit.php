<?php
// fire_plan_edit.php — 소방계획서 작성 위저드
declare(strict_types=1);

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
require_once __DIR__ . '/evacuation_plan_common.php';
$nick = $_SESSION['nickname'] ?? '사용자';
$commonEvac = epc_load();
$commonEvacStatus = epc_status($commonEvac);
$commonFireData = epc_to_fire_section($commonEvac);

/* ── 계획서 로드 (소유권 확인) ── */
$plan = fp_load_plan((string)($_GET['id'] ?? ''));
if (!$plan) { header('Location: /fire_plan.php'); exit; }
$planId   = (string)$plan['id'];
$usages   = fp_usages();
$usage    = $usages[$plan['usage_code']] ?? ['nm'=>$plan['usage_code'],'cat'=>''];
$sections = fp_sections();

/* 현재 섹션 */
$cur = (string)($_GET['s'] ?? '1');
$allCodes = [];
foreach ($sections as $ch) {
  foreach (array_keys($ch['items']) as $code) $allCodes[] = (string)$code;   // 정수 키를 문자열로
}
if (!in_array($cur, $allCodes, true)) $cur = '1';

/* ── 저장 처리 ── */
$savedMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  fp_csrf_check();

  if ($cur === '5' && ($_POST['act'] ?? '') === 'sync_common_evac') {
    if ($commonEvacStatus['has_content']) {
      $currentEvac = fp_get_section($planId, '5');
      $data = epc_apply_common($currentEvac, $commonFireData);
      $any = trim((string)($data['floor_exit'] ?? '')) !== ''
          || trim((string)($data['route'] ?? '')) !== ''
          || !empty($data['evac'])
          || trim((string)($data['assembly'] ?? '')) !== '';
      fp_save_section($planId, '5', $data, $any);
      header("Location: /fire_plan_edit.php?id=$planId&s=5&common_synced=1"); exit;
    }
    header("Location: /fire_plan_edit.php?id=$planId&s=5&common_missing=1"); exit;
  }

  if ($cur === '1') {
    $chk = function(string $k): array {   // 체크박스 그룹
      $v = $_POST[$k] ?? [];
      return is_array($v) ? array_values(array_map('strval', $v)) : [];
    };
    $data = [
      /* 기본 */
      'name'      => trim((string)($_POST['name'] ?? '')),
      'addr'      => trim((string)($_POST['addr'] ?? '')),
      'rep_name'  => trim((string)($_POST['rep_name'] ?? '')),   // 대표자(책임자)
      'rep_tel'   => trim((string)($_POST['rep_tel'] ?? '')),
      'mgr_name'  => trim((string)($_POST['mgr_name'] ?? '')),   // 소방안전관리자
      'mgr_tel'   => trim((string)($_POST['mgr_tel'] ?? '')),
      'recv_loc'  => trim((string)($_POST['recv_loc'] ?? '')),   // 수신기 위치
      /* 규모·구조 */
      'grade'     => (string)($_POST['grade'] ?? '2급'),
      'main_use'  => trim((string)($_POST['main_use'] ?? '')),   // 주용도
      'approval'  => (string)($_POST['approval'] ?? ''),
      'area'      => (string)($_POST['area'] ?? ''),             // 연면적
      'bld_area'  => (string)($_POST['bld_area'] ?? ''),         // 건축면적
      'floors'    => trim((string)($_POST['floors'] ?? '')),     // 층수
      'height'    => (string)($_POST['height'] ?? ''),           // 높이
      'structure' => trim((string)($_POST['structure'] ?? '')),  // 구조
      'roof'      => trim((string)($_POST['roof'] ?? '')),       // 지붕
      /* 시설 */
      'elev'      => $chk('elev'),      // 승강기: 승용/비상용/피난용
      'park'      => $chk('park'),      // 주차장: 옥내/옥외/자주식/기계식
      'ev'        => (string)($_POST['ev'] ?? '없음'),   // 전기차충전소
      'stairs'    => $chk('stairs'),    // 계단
      /* 운영 */
      'wd_day'    => trim((string)($_POST['wd_day'] ?? '')),   // 평일 주간
      'wd_night'  => trim((string)($_POST['wd_night'] ?? '')), // 평일 야간
      'hd_day'    => trim((string)($_POST['hd_day'] ?? '')),   // 휴일 주간
      'hd_night'  => trim((string)($_POST['hd_night'] ?? '')), // 휴일 야간
      'staff'     => (string)($_POST['staff'] ?? ''),      // 근무인원
      'resident'  => (string)($_POST['resident'] ?? ''),   // 거주인원
      'use_cnt'   => (string)($_POST['use_cnt'] ?? ''),    // 최대수용인원
      /* 해당 여부 */
      'public'    => (string)($_POST['public'] ?? '해당없음'),
      'split'     => (string)($_POST['split']  ?? '해당없음'),
      'joint'     => (string)($_POST['joint']  ?? '해당없음'),
      'hazmat'    => (string)($_POST['hazmat'] ?? '해당없음'),
      /* 화재보험 */
      'ins'       => (string)($_POST['ins'] ?? '미가입'),
      'ins_co'    => trim((string)($_POST['ins_co'] ?? '')),
      'ins_term'  => trim((string)($_POST['ins_term'] ?? '')),
      'ins_life'  => trim((string)($_POST['ins_life'] ?? '')),  // 대인
      'ins_prop'  => trim((string)($_POST['ins_prop'] ?? '')),  // 대물
    ];
    fp_save_section($planId, '1', $data, $data['name'] !== '');
    fp_apply_skips($planId, fp_skip_rules($data));                  // 분기 규칙 적용
    $pdate = trim((string)($_POST['plan_date'] ?? ''));              // 사용자가 지정한 작성일
    fp_update_shared($planId, $data['name'], fp_jawi_type($data), $pdate);
  } elseif ($cur === '2') {
    /* 서식 1.4 소방시설 현황 — 체크박스 그룹 */
    $g = function(string $k): array {
      $v = $_POST[$k] ?? [];
      return is_array($v) ? array_values(array_map('strval', $v)) : [];
    };
    $data = [
      'fire_ext' => $g('fire_ext'), 'alarm'  => $g('alarm'),
      'escape'   => $g('escape'),   'water'  => $g('water'),
      'active'   => $g('active'),   'etc_fac'=> $g('etc_fac'),
      'memo'     => trim((string)($_POST['memo'] ?? '')),
    ];
    $any = $data['fire_ext'] || $data['alarm'] || $data['escape']
        || $data['water'] || $data['active'] || $data['etc_fac'];
    fp_save_section($planId, '2', $data, $any);
  } elseif ($cur === '6') {
    /* 서식 1.5 방화구획·마감재·방염 */
    $g = function(string $k): array {
      $v = $_POST[$k] ?? [];
      return is_array($v) ? array_values(array_map('strval', $v)) : [];
    };
    $data = [
      'bkchk'  => $g('bkchk'),  'bkdoor' => $g('bkdoor'),
      'smoke'  => $g('smoke'),  'flame'  => $g('flame'),
      'finish' => trim((string)($_POST['finish'] ?? '')),
      'flame_cert' => (string)($_POST['flame_cert'] ?? '없음'),
      'memo'   => trim((string)($_POST['memo'] ?? '')),
    ];
    $any = $data['bkchk'] || $data['bkdoor'] || $data['smoke'] || $data['flame']
        || $data['finish'] !== '' || $data['memo'] !== '';
    fp_save_section($planId, '6', $data, $any);
  } elseif ($cur === '3' || $cur === '4') {
    /* 점검·정비 계획 표 (3행 × 시기/담당/비고 + 메모) */
    $data = ['memo' => trim((string)($_POST['memo'] ?? ''))];
    $any = $data['memo'] !== '';
    foreach ([1,2,3] as $i) {
      foreach (['when','who','note'] as $f) {
        $k = "r{$i}_{$f}";
        $data[$k] = trim((string)($_POST[$k] ?? ''));
        if ($data[$k] !== '') $any = true;
      }
    }
    fp_save_section($planId, $cur, $data, $any);
  } elseif ($cur === '5') {
    $ev = $_POST['evac'] ?? [];
    $data = [
      'floor_exit'=> trim((string)($_POST['floor_exit'] ?? '')),
      'route'     => trim((string)($_POST['route'] ?? '')),
      'evac'      => is_array($ev) ? array_values(array_map('strval',$ev)) : [],
      'weak_cnt'  => (string)($_POST['weak_cnt'] ?? ''),
      'weak_loc'  => trim((string)($_POST['weak_loc'] ?? '')),
      'weak_plan' => trim((string)($_POST['weak_plan'] ?? '')),
      'assembly'  => trim((string)($_POST['assembly'] ?? '')),
      'common_updated' => trim((string)($_POST['common_updated'] ?? '')),
    ];
    $any = $data['floor_exit']!=='' || $data['route']!=='' || $data['evac'] || $data['assembly']!=='';
    fp_save_section($planId, '5', $data, $any);

  } elseif ($cur === '11') {
    $data = ['memo' => trim((string)($_POST['memo'] ?? ''))];
    $any = $data['memo'] !== '';
    foreach ([1,2,3] as $i) {
      foreach (['when','who','how'] as $f) {
        $k = "t{$i}_{$f}";
        $data[$k] = trim((string)($_POST[$k] ?? ''));
        if ($data[$k] !== '') $any = true;
      }
    }
    fp_save_section($planId, '11', $data, $any);

  } elseif ($cur === '14') {
    $data = ['memo' => trim((string)($_POST['memo'] ?? ''))];
    $any = $data['memo'] !== '';
    foreach ([1,2,3,4,5] as $i) {
      $k = "s{$i}";
      $data[$k] = trim((string)($_POST[$k] ?? ''));
      if ($data[$k] !== '') $any = true;
    }
    fp_save_section($planId, '14', $data, $any);

  } else {
    /* 나머지 항목: 메모 형태로 저장 */
    $memo = trim((string)($_POST['memo'] ?? ''));
    fp_save_section($planId, $cur, ['memo'=>$memo], $memo !== '');
  }

  if (($_POST['go'] ?? '') === 'next') {
    /* 생략되지 않은 다음 섹션으로 이동 */
    $planNow = fp_load_plan($planId);
    $skipSet = [];
    foreach (($planNow['sections'] ?? []) as $code => $sec) {
      if (is_array($sec) && !empty($sec['is_skipped'])) $skipSet[] = (string)$code;
    }
    $idx = array_search($cur, $allCodes, true);
    for ($i = $idx + 1; $i < count($allCodes); $i++) {
      if (!in_array($allCodes[$i], $skipSet, true)) {
        header("Location: /fire_plan_edit.php?id=$planId&s=".$allCodes[$i]); exit;
      }
    }
  }
  header("Location: /fire_plan_edit.php?id=$planId&s=$cur&saved=1"); exit;
}
if (!empty($_GET['saved'])) $savedMsg = '저장되었습니다 ✓';
if (!empty($_GET['common_synced'])) $savedMsg = '공통 피난계획을 반영했습니다 ✓';
if (!empty($_GET['common_missing'])) $savedMsg = '먼저 공통 피난계획을 작성해 주세요.';

/* ── 화면 데이터 ── */
$s1   = fp_get_section($planId, '1');
$section5Stored = fp_get_section($planId, '5');
$commonAppliedAt = trim((string)($section5Stored['common_updated'] ?? ''));
$commonUpdateAvailable = $commonEvacStatus['has_content']
  && $commonEvacStatus['updated'] !== ''
  && $commonAppliedAt < (string)$commonEvacStatus['updated'];
$curData = ($cur === '1') ? $s1 : ($cur === '5' ? $section5Stored : fp_get_section($planId, $cur));
if ($cur === '5' && $commonEvacStatus['has_content']) {
  $curData = epc_merge_empty($curData, $commonFireData);
}

$stateMap = [];
foreach (($plan['sections'] ?? []) as $code => $sec) {
  if (is_array($sec)) {
    $stateMap[$code] = ['is_skipped'=>$sec['is_skipped'] ?? 0, 'is_done'=>$sec['is_done'] ?? 0];
  }
}
$skipReasons = fp_skip_rules($s1);
$skipCount = count($skipReasons);

$curTitle = '';
foreach ($sections as $ch) if (isset($ch['items'][$cur])) $curTitle = $ch['items'][$cur];

function seg(string $name, array $opts, string $val): string {
  $html = '<div class="seg">';
  foreach ($opts as $o) {
    $on = ($o === $val) ? ' class="on"' : '';
    $html .= '<label'.$on.'><input type="radio" name="'.h($name).'" value="'.h($o).'"'.($o===$val?' checked':'').'>'.h($o).'</label>';
  }
  return $html.'</div>';
}
/* 체크박스 (소방청 서식의 □ 표시) */
function chk(string $name, string $label, array $sel): string {
  $on = in_array($label, $sel, true);
  return '<label class="ck'.($on?' on':'').'">'
       . '<input type="checkbox" name="'.h($name).'[]" value="'.h($label).'"'.($on?' checked':'').'>'
       . '<span>'.h($label).'</span></label>';
}
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=h($usage['nm'])?> 소방계획서 작성 — TWORIX</title>
<style>
:root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--bd2:#d4dbe6;--fg:#1a2436;--mut:#7a8699;--mut2:#56627a;--brand:#2563eb;--brand2:#1d4ed8;--accent:#0891b2;--ok:#047857;--warn:#b45309;--skip:#9aa4b2}
*{box-sizing:border-box;margin:0;padding:0}
html,body{background:var(--bg);color:var(--fg);font-family:Inter,ui-sans-serif,system-ui,"Apple SD Gothic Neo",sans-serif;line-height:1.6}
a{text-decoration:none}
.nav{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.9);backdrop-filter:blur(12px);border-bottom:1px solid var(--bd)}
.nav__inner{max-width:1200px;margin:0 auto;padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;gap:16px}
.nav__brand{font-weight:800;font-size:22px;color:var(--fg)}
.nav__right{display:flex;align-items:center;gap:12px;font-size:14px;color:var(--mut2)}
.btn{display:inline-flex;align-items:center;padding:8px 16px;border-radius:9px;border:1px solid var(--bd2);background:#fff;color:var(--fg);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit}
.btn:hover{border-color:var(--brand);color:var(--brand2)}
.btn--primary{background:var(--brand);border-color:var(--brand);color:#fff}
.btn--primary:hover{background:var(--brand2);color:#fff}
.wrap{max-width:1200px;margin:0 auto;padding:26px 24px 80px}
.layout{display:grid;grid-template-columns:280px 1fr;gap:20px;align-items:start}
@media(max-width:860px){.layout{grid-template-columns:1fr}}

/* 목차 */
.toc{background:var(--card);border:1px solid var(--bd);border-radius:14px;overflow:hidden;position:sticky;top:72px}
@media(max-width:860px){.toc{position:static}}
.toc__head{background:var(--brand);color:#fff;padding:14px 16px}
.toc__head .t{font-weight:700;font-size:14.5px}
.toc__head .skipbar{margin-top:8px;font-size:12px;background:var(--brand2);border-radius:8px;padding:6px 10px}
.toc__head .skipbar b{color:#fde68a}
.toc__chap{padding:9px 16px 7px;font-size:11.5px;font-weight:800;color:var(--mut);letter-spacing:.06em;border-top:1px solid var(--bd);background:#fafbfd}
.toc ul{list-style:none}
.toc li a{display:flex;gap:8px;align-items:center;padding:7px 14px;font-size:13.5px;color:var(--fg);border-left:3px solid transparent}
.toc li a:hover{background:#f5f8ff}
.toc li a .cd{color:var(--mut);font-size:12px;min-width:38px;font-variant-numeric:tabular-nums}
.toc li.now a{border-left-color:var(--brand);background:#eef4ff;font-weight:700}
.toc li.done .cd::after{content:' ✓';color:var(--ok)}
.toc li.skipped a{color:var(--skip);text-decoration:line-through;pointer-events:none}
.toc li.skipped .why{margin-left:auto;font-size:10.5px;text-decoration:none;background:#eef1f5;color:var(--skip);border-radius:99px;padding:2px 8px;white-space:nowrap}

/* 본문 */
.panel{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:26px}
.eyebrow{font-size:12px;color:var(--accent);font-weight:800;letter-spacing:.04em}
.panel h2{font-size:20px;font-weight:700;margin:2px 0 4px;letter-spacing:-.3px}
.panel .desc{font-size:13px;color:var(--mut2);margin-bottom:20px}
fieldset{border:0;border-top:1px solid var(--bd);padding:18px 0 4px;margin-top:12px}
legend{font-weight:800;font-size:14px;padding-right:12px;color:var(--brand2)}
.row{display:grid;grid-template-columns:150px 1fr;gap:10px;align-items:center;margin-bottom:13px}
@media(max-width:560px){.row{grid-template-columns:1fr;gap:4px}}
.row label.lb{font-size:13.5px;font-weight:600}
.req{color:#dc2626}
.shared{display:inline-block;font-size:10.5px;background:#eef2ff;color:var(--brand2);border-radius:99px;padding:1px 8px;margin-left:6px;font-weight:700;vertical-align:1px}
input[type=text],input[type=number],input[type=date],textarea{
  width:100%;max-width:440px;padding:9px 12px;border:1.5px solid var(--bd2);border-radius:9px;font:inherit;font-size:14px;background:#fff}
textarea{max-width:100%;min-height:140px;resize:vertical}
input:focus,textarea:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.seg{display:inline-flex;border:1.5px solid var(--bd2);border-radius:9px;overflow:hidden}
.seg label{padding:8px 16px;font-size:13.5px;cursor:pointer;color:var(--mut2);background:#fff}
.seg label+label{border-left:1.5px solid var(--bd2)}
.seg label.on{background:var(--brand);color:#fff;font-weight:700}
.seg input{display:none}
.auto{margin-top:6px;font-size:12.5px;background:#ecfdf5;color:var(--ok);border-radius:8px;padding:7px 11px;display:none;max-width:440px}
.auto.show{display:block}
.flow{margin-top:6px;font-size:12.5px;background:#fff7ed;color:var(--warn);border-radius:8px;padding:7px 11px;display:none;max-width:440px}
.flow.show{display:block}
.actions{margin-top:24px;display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.savemsg{font-size:13px;color:var(--ok);font-weight:600}
.placeholder-note{font-size:12.5px;background:#f5f8ff;color:var(--brand2);border-radius:8px;padding:8px 12px;margin-bottom:14px}
/* ── 소방청 서식 표 ── */
table.ft{width:100%;border-collapse:collapse;font-size:13px;margin-top:6px}
table.ft th,table.ft td{border:1px solid #c9d2e0;padding:7px 9px;vertical-align:middle}
table.ft th{background:#f2f5fa;font-weight:700;color:var(--fg);text-align:center;white-space:nowrap}
table.ft th.lb1{width:76px;background:#e8edf5;font-size:12.5px;letter-spacing:-.3px}
table.ft th.lb2{width:104px;font-size:12.5px;font-weight:600;letter-spacing:-.3px}
table.ft th.lb3{width:88px;background:#f7f9fc;font-weight:600;font-size:12px}
table.ft input[type=text],table.ft input[type=number],table.ft input[type=date]{
  width:100%;padding:7px 9px;border:1px solid var(--bd2);border-radius:6px;font-size:13px;font-family:inherit;background:#fff}
table.ft input:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 2px rgba(37,99,235,.1)}
table.ft td.two{display:flex;gap:6px}
table.ft td.two input{flex:1}
table.ft .w90{width:90px !important;flex:none !important}
table.ft .w120{width:120px !important;flex:none !important}
table.ft .w150{width:150px !important;flex:none !important}
table.ft textarea{width:100%;padding:8px 10px;border:1px solid var(--bd2);border-radius:6px;font-size:13px;font-family:inherit;resize:vertical}
table.ft .mini{font-size:12px;color:var(--mut2);font-weight:600;white-space:nowrap}
.ckrow{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.ckrow + .ckrow{margin-top:6px}
.ck{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border:1px solid var(--bd2);
  border-radius:7px;background:#fff;cursor:pointer;font-size:12.5px;transition:.12s;white-space:nowrap}
.ck:hover{border-color:var(--brand)}
.ck.on{background:#eef4ff;border-color:var(--brand);color:var(--brand2);font-weight:700}
.ck input{margin:0;accent-color:var(--brand)}
table.ft .seg{display:inline-flex;margin:0}
table.ft .flow{margin-top:6px}
@media(max-width:760px){
  table.ft,table.ft tbody,table.ft tr,table.ft td,table.ft th{display:block;width:auto !important}
  table.ft tr{margin-bottom:8px;border:1px solid #c9d2e0;border-radius:8px;overflow:hidden}
  table.ft th{text-align:left;border:0;border-bottom:1px solid #e3e8f0}
  table.ft td{border:0;border-bottom:1px solid #f0f3f8}
  table.ft td.two{display:flex}
}
/* 자위소방대 편성표 */
.btn--sm{padding:5px 11px;font-size:12px;border-radius:7px}
.jawi-team{border:1px solid var(--bd);border-radius:11px;padding:14px;margin-bottom:12px;background:#fafbfd}
.jawi-team-head{display:flex;gap:8px;align-items:center;margin-bottom:10px}
.jt-name{flex:1;padding:9px 11px;border:1px solid var(--bd2);border-radius:8px;font-size:14px;font-weight:600;font-family:inherit}
.jawi-members{display:flex;flex-direction:column;gap:6px;margin-bottom:8px}
.jawi-member{display:flex;gap:6px;align-items:center}
.jawi-member input{padding:8px 10px;border:1px solid var(--bd2);border-radius:7px;font-size:13px;font-family:inherit}
.jm-name{width:110px;flex-shrink:0}
.jm-tel{width:140px;flex-shrink:0}
.jm-task{flex:1;min-width:80px}
.jawi-del{width:30px;height:34px;flex-shrink:0;border:1px solid var(--bd2);background:#fff;border-radius:7px;color:var(--mut);cursor:pointer;font-size:13px}
.jawi-del:hover{border-color:#dc2626;color:#dc2626}
.common-evac{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:14px;
  padding:14px 15px;border:1px solid #bbdfc8;border-radius:10px;background:#f7fcf8}
.common-evac.is-update{border-color:#f2cf91;background:#fffbeb}
.common-evac__text{flex:1;min-width:220px}
.common-evac__title{font-size:13.5px;font-weight:800;color:#166534;margin-bottom:2px}
.common-evac.is-update .common-evac__title{color:#92400e}
.common-evac__meta{font-size:12px;color:var(--mut2);line-height:1.6}
.common-evac__actions{display:flex;gap:7px;flex-wrap:wrap}
@media(max-width:640px){
  .jawi-member{flex-wrap:wrap}
  .jm-name,.jm-tel{width:calc(50% - 3px)}
  .jm-task{width:100%;flex-basis:100%}
}
</style>
</head>
<body>

<nav class="nav">
  <div class="nav__inner">
    <a class="nav__brand" href="/index.php">YEOHUB</a>
    <div class="nav__right">
      <span><?=h($nick)?>님</span>
      <a class="btn" href="/fire_plan_print.php?id=<?=h($planId)?>">🖨️ 인쇄/PDF</a>
      <a class="btn" href="/fire_plan.php">← 목록</a>
    </div>
  </div>
</nav>

<main class="wrap">
<div class="layout">

  <!-- ───── 목차 ───── -->
  <nav class="toc">
    <div class="toc__head">
      <div class="t">[ <?=h($usage['nm'])?> ] 소방계획서</div>
      <div class="skipbar">자동 생략된 항목 <b id="skipCount"><?=$skipCount?></b>건<?php if($plan['jawi_type']):?> · 자위소방대 <b><?=h($plan['jawi_type']==='PUBLIC'?'공공기관형':'Type-'.$plan['jawi_type'])?></b><?php endif;?></div>
    </div>
    <?php foreach ($sections as $chNo => $ch): ?>
      <div class="toc__chap"><?=h($ch['title'])?></div>
      <ul>
        <?php foreach ($ch['items'] as $code => $title):
          $code = (string)$code;                    // 정수 키를 문자열로 통일
          $st = $stateMap[$code] ?? null;
          $cls = [];
          if ($code === $cur) $cls[] = 'now';
          if ($st && $st['is_done'])    $cls[] = 'done';
          if ($st && $st['is_skipped']) $cls[] = 'skipped';
        ?>
        <li data-cd="<?=h($code)?>" class="<?=implode(' ',$cls)?>">
          <a href="/fire_plan_edit.php?id=<?=$planId?>&s=<?=h($code)?>">
            <span class="cd"><?=h($code)?></span><span class="tt"><?=h($title)?></span>
            <?php if ($st && $st['is_skipped'] && isset($skipReasons[$code])): ?>
              <span class="why"><?=h($skipReasons[$code])?></span>
            <?php endif; ?>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
  </nav>

  <!-- ───── 본문 ───── -->
  <div class="panel">
    <div class="eyebrow">항목 <?=h($cur)?></div>
    <h2><?=h($curTitle)?></h2>

    <form method="post" action="/fire_plan_edit.php?id=<?=$planId?>&s=<?=h($cur)?>">
      <input type="hidden" name="csrf" value="<?=h(fp_csrf())?>">

      <?php if ($cur === '1'): ?>
        <?php $E=$curData['elev']??[]; $P=$curData['park']??[]; $S=$curData['stairs']??[]; ?>
        <p class="desc">소방청 <b>서식 1.1 건축물 일반현황</b>입니다. □에는 해당되는 곳에 체크하세요. 아래 <b>해당 여부</b>에 따라 관련 항목이 자동 생략됩니다.</p>

        <table class="ft">
          <tr>
            <th class="lb1">작성일 <span class="req">*</span></th>
            <td colspan="5">
              <div class="ckrow" style="align-items:center;gap:8px">
                <input type="date" name="plan_date" class="w150"
                  value="<?=h($plan['plan_date'] ?? date('Y-m-d'))?>">
                <span class="mini">출력물(소방계획서 표지·서명란)에 표시됩니다</span>
              </div>
            </td>
          </tr>
          <tr>
            <th class="lb1">명 칭 <span class="req">*</span></th>
            <td colspan="5"><input type="text" name="name" required value="<?=h($curData['name'] ?? '')?>" placeholder="예: 투릭스빌딩"></td>
          </tr>
          <tr>
            <th class="lb1">도로명주소</th>
            <td colspan="5"><input type="text" name="addr" value="<?=h($curData['addr'] ?? '')?>" placeholder="예: 경기도 파주시 문발로 …"></td>
          </tr>
          <tr>
            <th class="lb1" rowspan="2">연 락 처</th>
            <th class="lb2">대표자(책임자)</th>
            <td><input type="text" name="rep_name" value="<?=h($curData['rep_name'] ?? '')?>"></td>
            <th class="lb2">소방안전관리자</th>
            <td colspan="2"><input type="text" name="mgr_name" value="<?=h($curData['mgr_name'] ?? '')?>"></td>
          </tr>
          <tr>
            <th class="lb2">연락처</th>
            <td><input type="text" name="rep_tel" value="<?=h($curData['rep_tel'] ?? '')?>" placeholder="010-0000-0000"></td>
            <th class="lb2">연락처</th>
            <td colspan="2"><input type="text" name="mgr_tel" value="<?=h($curData['mgr_tel'] ?? '')?>" placeholder="010-0000-0000"></td>
          </tr>

          <tr>
            <th class="lb1" rowspan="7">시설현황</th>
            <th class="lb2">수신기위치</th>
            <td colspan="4"><input type="text" name="recv_loc" value="<?=h($curData['recv_loc'] ?? '')?>" placeholder="예: 1층 방재실"></td>
          </tr>
          <tr>
            <th class="lb2" rowspan="3">규모/구조</th>
            <th class="lb3">대상물 급수</th>
            <td><?=seg('grade', ['특급','1급','2급','3급'], $curData['grade'] ?? '2급')?></td>
            <th class="lb3">주용도</th>
            <td><input type="text" name="main_use" value="<?=h($curData['main_use'] ?? '')?>" placeholder="예: 근린생활시설"></td>
          </tr>
          <tr>
            <th class="lb3">연면적(㎡)</th>
            <td><input type="number" step="0.01" name="area" id="fArea" value="<?=h($curData['area'] ?? '')?>" placeholder="8500"></td>
            <th class="lb3">건축면적(㎡)</th>
            <td><input type="number" step="0.01" name="bld_area" value="<?=h($curData['bld_area'] ?? '')?>"></td>
          </tr>
          <tr>
            <th class="lb3">층수 / 높이(m)</th>
            <td class="two"><input type="text" name="floors" value="<?=h($curData['floors'] ?? '')?>" placeholder="지상5/지하1">
              <input type="number" step="0.1" name="height" value="<?=h($curData['height'] ?? '')?>" placeholder="높이"></td>
            <th class="lb3">구조 / 지붕</th>
            <td class="two"><input type="text" name="structure" value="<?=h($curData['structure'] ?? '')?>" placeholder="철근콘크리트">
              <input type="text" name="roof" value="<?=h($curData['roof'] ?? '')?>" placeholder="슬래브"></td>
          </tr>
          <tr>
            <th class="lb2">사용승인일</th>
            <td colspan="4"><input type="date" name="approval" id="fApproval" value="<?=h($curData['approval'] ?? '')?>"></td>
          </tr>
          <tr>
            <th class="lb2">승강기 / 주차장</th>
            <td colspan="4">
              <div class="ckrow"><?=chk('elev','승용',$E)?><?=chk('elev','비상용',$E)?><?=chk('elev','피난용',$E)?></div>
              <div class="ckrow"><?=chk('park','옥내',$P)?><?=chk('park','옥외',$P)?><?=chk('park','자주식',$P)?><?=chk('park','기계식',$P)?></div>
              <div class="ckrow" style="align-items:center">
                <span class="mini">전기차충전소</span> <?=seg('ev', ['있음','없음'], $curData['ev'] ?? '없음')?>
              </div>
            </td>
          </tr>
          <tr>
            <th class="lb2">계 단</th>
            <td colspan="4"><div class="ckrow">
              <?=chk('stairs','특별피난계단',$S)?><?=chk('stairs','직통계단',$S)?>
              <?=chk('stairs','피난계단',$S)?><?=chk('stairs','옥외계단',$S)?>
            </div></td>
          </tr>

          <tr>
            <th class="lb1" rowspan="6">운영현황</th>
            <th class="lb2">운영시간(평일)</th>
            <td colspan="4">
              <div class="ckrow" style="align-items:center;gap:8px">
                <span class="mini">주간</span><input type="text" name="wd_day" value="<?=h($curData['wd_day'] ?? '')?>" placeholder="09:00~18:00" class="w150">
                <span class="mini">야간</span><input type="text" name="wd_night" value="<?=h($curData['wd_night'] ?? '')?>" placeholder="18:00~09:00" class="w150">
              </div>
            </td>
          </tr>
          <tr>
            <th class="lb2">운영시간(휴일)</th>
            <td colspan="4">
              <div class="ckrow" style="align-items:center;gap:8px">
                <span class="mini">주간</span><input type="text" name="hd_day" value="<?=h($curData['hd_day'] ?? '')?>" placeholder="휴무 / 시간" class="w150">
                <span class="mini">야간</span><input type="text" name="hd_night" value="<?=h($curData['hd_night'] ?? '')?>" placeholder="휴무 / 시간" class="w150">
              </div>
            </td>
          </tr>
          <tr>
            <th class="lb2">인원현황(명)</th>
            <td colspan="4">
              <div class="ckrow" style="align-items:center;gap:14px">
                <span class="mini">근무</span><input type="number" name="staff" id="fStaff" class="w90" value="<?=h($curData['staff'] ?? '')?>">
                <span class="mini">거주</span><input type="number" name="resident" class="w90" value="<?=h($curData['resident'] ?? '')?>">
                <span class="mini">최대수용</span><input type="number" name="use_cnt" class="w90" value="<?=h($curData['use_cnt'] ?? '')?>">
              </div>
              <div class="auto" id="autoType"></div>
            </td>
          </tr>
          <tr>
            <th class="lb2">공공기관</th>
            <td colspan="4"><?=seg('public', ['해당','해당없음'], $curData['public'] ?? '해당없음')?>
              <div class="flow" id="flowPublic"></div></td>
          </tr>
          <tr>
            <th class="lb2">권원분리 / 공동관리</th>
            <td colspan="4">
              <div class="ckrow" style="align-items:center;gap:10px">
                <span class="mini">권원분리</span><?=seg('split', ['해당','해당없음'], $curData['split'] ?? '해당없음')?>
                <span class="mini">공동관리</span><?=seg('joint', ['해당','해당없음'], $curData['joint'] ?? '해당없음')?>
              </div>
              <div class="flow" id="flowSplit"></div>
              <div class="flow" id="flowJoint"></div>
            </td>
          </tr>
          <tr>
            <th class="lb2">위험물 저장·취급</th>
            <td colspan="4"><?=seg('hazmat', ['해당','해당없음'], $curData['hazmat'] ?? '해당없음')?>
              <div class="flow" id="flowHazmat"></div></td>
          </tr>

          <tr>
            <th class="lb1" rowspan="2">화재보험</th>
            <th class="lb2">가입여부</th>
            <td colspan="4"><?=seg('ins', ['가입','미가입'], $curData['ins'] ?? '미가입')?></td>
          </tr>
          <tr>
            <th class="lb2">보험사 / 기간 / 금액</th>
            <td colspan="4">
              <div class="ckrow" style="align-items:center;gap:8px">
                <input type="text" name="ins_co" value="<?=h($curData['ins_co'] ?? '')?>" placeholder="보험사" class="w120">
                <input type="text" name="ins_term" value="<?=h($curData['ins_term'] ?? '')?>" placeholder="가입기간" class="w120">
                <span class="mini">대인</span><input type="text" name="ins_life" value="<?=h($curData['ins_life'] ?? '')?>" placeholder="원" class="w90">
                <span class="mini">대물</span><input type="text" name="ins_prop" value="<?=h($curData['ins_prop'] ?? '')?>" placeholder="원" class="w90">
              </div>
            </td>
          </tr>
        </table>

      <?php elseif ($cur === '2'): ?>
        <?php
          $F1=$curData['fire_ext']??[]; $F2=$curData['alarm']??[]; $F3=$curData['escape']??[];
          $F4=$curData['water']??[];    $F5=$curData['active']??[]; $F6=$curData['etc_fac']??[];
        ?>
        <p class="desc">소방청 <b>서식 1.4 소방시설 현황</b>입니다. 설치된 시설에 모두 체크하세요.</p>

        <table class="ft">
          <tr>
            <th class="lb1">소화설비</th>
            <td><div class="ckrow">
              <?=chk('fire_ext','소화기구 및 자동소화장치',$F1)?><?=chk('fire_ext','옥내소화전설비',$F1)?>
              <?=chk('fire_ext','옥외소화전설비',$F1)?><?=chk('fire_ext','스프링클러설비',$F1)?>
              <?=chk('fire_ext','간이스프링클러설비',$F1)?><?=chk('fire_ext','화재조기진압용 스프링클러설비',$F1)?>
              <?=chk('fire_ext','물분무소화설비',$F1)?><?=chk('fire_ext','미분무소화설비',$F1)?>
              <?=chk('fire_ext','포소화설비',$F1)?><?=chk('fire_ext','이산화탄소소화설비',$F1)?>
              <?=chk('fire_ext','할론소화설비',$F1)?><?=chk('fire_ext','할로겐화합물 및 불활성기체소화설비',$F1)?>
              <?=chk('fire_ext','분말소화설비',$F1)?><?=chk('fire_ext','강화액소화설비',$F1)?>
              <?=chk('fire_ext','고체에어졸소화설비',$F1)?>
            </div></td>
          </tr>
          <tr>
            <th class="lb1">경보설비</th>
            <td><div class="ckrow">
              <?=chk('alarm','단독경보형감지기',$F2)?><?=chk('alarm','비상경보설비',$F2)?>
              <?=chk('alarm','자동화재탐지설비 및 시각경보기',$F2)?><?=chk('alarm','화재알림설비',$F2)?>
              <?=chk('alarm','비상방송설비',$F2)?><?=chk('alarm','통합감시시설',$F2)?>
              <?=chk('alarm','자동화재속보설비',$F2)?><?=chk('alarm','누전경보기',$F2)?>
              <?=chk('alarm','가스누설경보기',$F2)?>
            </div></td>
          </tr>
          <tr>
            <th class="lb1">피난구조<br>설비</th>
            <td><div class="ckrow">
              <?=chk('escape','피난기구',$F3)?><?=chk('escape','공기안전매트',$F3)?>
              <?=chk('escape','피난사다리',$F3)?><?=chk('escape','(간이)완강기',$F3)?>
              <?=chk('escape','미끄럼대',$F3)?><?=chk('escape','구조대',$F3)?>
              <?=chk('escape','다수인피난장비',$F3)?><?=chk('escape','승강식피난기',$F3)?>
              <?=chk('escape','하향식피난구용내림식사다리',$F3)?>
              <?=chk('escape','인명구조기구',$F3)?><?=chk('escape','피난유도선',$F3)?>
              <?=chk('escape','유도등',$F3)?><?=chk('escape','비상조명등',$F3)?>
              <?=chk('escape','유도표지',$F3)?><?=chk('escape','휴대용비상조명등',$F3)?>
            </div></td>
          </tr>
          <tr>
            <th class="lb1">소화용수<br>설비</th>
            <td><div class="ckrow">
              <?=chk('water','상수도소화용수설비',$F4)?><?=chk('water','소화수조 및 저수조',$F4)?>
            </div></td>
          </tr>
          <tr>
            <th class="lb1">소화활동<br>설비</th>
            <td><div class="ckrow">
              <?=chk('active','거실제연설비',$F5)?><?=chk('active','부속실 등 제연설비',$F5)?>
              <?=chk('active','연결송수관설비',$F5)?><?=chk('active','연결살수설비',$F5)?>
              <?=chk('active','비상콘센트설비',$F5)?><?=chk('active','무선통신보조설비',$F5)?>
              <?=chk('active','연소방지설비',$F5)?>
            </div></td>
          </tr>
          <tr>
            <th class="lb1">기타시설</th>
            <td><div class="ckrow">
              <?=chk('etc_fac','전기시설',$F6)?><?=chk('etc_fac','가스시설',$F6)?>
              <?=chk('etc_fac','위험물시설',$F6)?><?=chk('etc_fac','방화시설',$F6)?>
            </div></td>
          </tr>
          <tr>
            <th class="lb1">비 고</th>
            <td><textarea name="memo" rows="3" placeholder="설치장소·규격 등 특이사항 (선택)"><?=h($curData['memo'] ?? '')?></textarea></td>
          </tr>
        </table>

      <?php elseif ($cur === '6'): ?>
        <?php $B1=$curData['bkchk']??[]; $B2=$curData['bkdoor']??[]; $B3=$curData['smoke']??[]; $B4=$curData['flame']??[]; ?>
        <p class="desc">소방청 <b>서식 1.5 피난·방화시설 및 제연·방염 현황</b>입니다.</p>

        <table class="ft">
          <tr>
            <th class="lb1">방화구획</th>
            <td>
              <div class="ckrow"><span class="mini">구획기준</span>
                <?=chk('bkchk','면적별',$B1)?><?=chk('bkchk','층별',$B1)?><?=chk('bkchk','용도별',$B1)?>
              </div>
              <div class="ckrow" style="margin-top:8px"><span class="mini">구획설비</span>
                <?=chk('bkdoor','방화문',$B2)?><?=chk('bkdoor','자동폐쇄장치',$B2)?>
                <?=chk('bkdoor','방화셔터',$B2)?><?=chk('bkdoor','방화스크린',$B2)?>
              </div>
            </td>
          </tr>
          <tr>
            <th class="lb1">제연설비</th>
            <td><div class="ckrow">
              <?=chk('smoke','거실제연',$B3)?><?=chk('smoke','부속실제연',$B3)?>
              <?=chk('smoke','전실제연',$B3)?><?=chk('smoke','해당없음',$B3)?>
            </div></td>
          </tr>
          <tr>
            <th class="lb1">내부 마감재</th>
            <td><input type="text" name="finish" value="<?=h($curData['finish'] ?? '')?>"
              placeholder="예: 벽·천장 불연재(석고보드), 바닥 준불연재"></td>
          </tr>
          <tr>
            <th class="lb1">방염물품</th>
            <td>
              <div class="ckrow">
                <?=chk('flame','커튼류',$B4)?><?=chk('flame','카펫',$B4)?>
                <?=chk('flame','벽지류',$B4)?><?=chk('flame','합판·목재',$B4)?>
                <?=chk('flame','무대막',$B4)?><?=chk('flame','섬유판',$B4)?>
                <?=chk('flame','해당없음',$B4)?>
              </div>
              <div class="ckrow" style="margin-top:8px;align-items:center">
                <span class="mini">방염성능검사 필증</span>
                <?=seg('flame_cert', ['있음','없음'], $curData['flame_cert'] ?? '없음')?>
              </div>
            </td>
          </tr>
          <tr>
            <th class="lb1">유지관리<br>계획</th>
            <td><textarea name="memo" rows="3" placeholder="방화구획·방화문 정기 점검 주기, 마감재 교체 계획 등"><?=h($curData['memo'] ?? '')?></textarea></td>
          </tr>
        </table>

      <?php elseif ($cur === '3' || $cur === '4'): ?>
        <?php
          $isSelf = ($cur === '3');
          $ttl = $isSelf ? '자체점검계획 및 대응대책' : '소방·피난·방화시설 점검·정비계획';
        ?>
        <p class="desc">소방청 <b>서식 1.10 자체점검 및 업무수행</b> 기준입니다. 점검 주기와 담당자를 정합니다.</p>

        <table class="ft">
          <tr>
            <th class="lb1">점검 종류</th>
            <th class="lb2">실시 시기</th>
            <th class="lb2">담당자</th>
            <th class="lb2">비고</th>
          </tr>
          <tr>
            <th class="lb2"><?=$isSelf?'작동점검':'소방시설'?></th>
            <td><input type="text" name="r1_when" value="<?=h($curData['r1_when'] ?? '')?>" placeholder="예: 매년 6월"></td>
            <td><input type="text" name="r1_who" value="<?=h($curData['r1_who'] ?? '')?>" placeholder="담당자명"></td>
            <td><input type="text" name="r1_note" value="<?=h($curData['r1_note'] ?? '')?>"></td>
          </tr>
          <tr>
            <th class="lb2"><?=$isSelf?'종합점검':'피난시설'?></th>
            <td><input type="text" name="r2_when" value="<?=h($curData['r2_when'] ?? '')?>" placeholder="예: 매년 3월"></td>
            <td><input type="text" name="r2_who" value="<?=h($curData['r2_who'] ?? '')?>"></td>
            <td><input type="text" name="r2_note" value="<?=h($curData['r2_note'] ?? '')?>"></td>
          </tr>
          <tr>
            <th class="lb2"><?=$isSelf?'외관점검':'방화시설'?></th>
            <td><input type="text" name="r3_when" value="<?=h($curData['r3_when'] ?? '')?>" placeholder="예: 매월"></td>
            <td><input type="text" name="r3_who" value="<?=h($curData['r3_who'] ?? '')?>"></td>
            <td><input type="text" name="r3_note" value="<?=h($curData['r3_note'] ?? '')?>"></td>
          </tr>
          <tr>
            <th class="lb1"><?=$isSelf?'불량 시<br>대응대책':'정비<br>절차'?></th>
            <td colspan="3"><textarea name="memo" rows="4" placeholder="<?=$isSelf
              ? '점검 중 불량 발견 시 조치 절차 (보수 요청 → 업체 선정 → 완료 확인 등)'
              : '고장·불량 발견 시 정비 절차와 담당, 예산 확보 방법 등'?>"><?=h($curData['memo'] ?? '')?></textarea></td>
          </tr>
        </table>

      <?php elseif ($cur === '5'): ?>
        <?php $EV=$curData['evac']??[]; ?>
        <div class="common-evac <?= $commonUpdateAvailable ? 'is-update' : '' ?>">
          <div class="common-evac__text">
            <?php if (!$commonEvacStatus['has_content']): ?>
              <div class="common-evac__title">공통 피난계획이 아직 없습니다</div>
              <div class="common-evac__meta">층별 대피경로와 집결지를 먼저 설정해 주세요.</div>
            <?php elseif ($commonUpdateAvailable): ?>
              <div class="common-evac__title">최신 공통 피난계획을 반영할 수 있습니다</div>
              <div class="common-evac__meta">최근 수정 <?=h(substr((string)$commonEvacStatus['updated'],0,16))?> · 적용하면 아래 공통 항목이 최신 내용으로 바뀝니다.</div>
            <?php else: ?>
              <div class="common-evac__title">공통 피난계획 반영됨</div>
              <div class="common-evac__meta">이 계획서에는 현재 공통 피난계획이 반영되어 있습니다.</div>
            <?php endif; ?>
          </div>
          <div class="common-evac__actions">
            <a class="btn btn--sm" href="/evacuation_plan_chat.php?return=<?=rawurlencode('/fire_plan_edit.php?id='.$planId.'&s=5')?>">
              <?= $commonEvacStatus['has_content'] ? '공통계획 검토·수정' : '공통계획 작성' ?>
            </a>
            <?php if ($commonEvacStatus['has_content']): ?>
              <button class="btn btn--sm <?= $commonUpdateAvailable ? 'btn--primary' : '' ?>" type="submit"
                name="act" value="sync_common_evac"
                onclick="return confirm('공통 피난계획의 최신 내용으로 아래 공통 항목을 반영할까요?');">
                <?= $commonUpdateAvailable ? '최신 내용 적용' : '다시 적용' ?>
              </button>
            <?php endif; ?>
          </div>
        </div>
        <p class="desc">소방청 <b>제3장 피난계획</b> 기준입니다. 피난경로와 화재안전취약자 대책을 정합니다.</p>
        <input type="hidden" name="common_updated" value="<?=h($curData['common_updated'] ?? '')?>">
        <table class="ft">
          <tr><th class="lb1">피난층</th>
            <td><input type="text" name="floor_exit" value="<?=h($curData['floor_exit'] ?? '')?>" placeholder="예: 지상 1층 (주출입구 2개소)"></td></tr>
          <tr><th class="lb1">피난경로</th>
            <td><textarea name="route" rows="3" placeholder="층별 피난경로를 적으세요. 예) 3층 → 동쪽 특별피난계단 → 1층 주출입구 → 외부 집결지(주차장)"><?=h($curData['route'] ?? '')?></textarea></td></tr>
          <tr><th class="lb1">피난기구</th>
            <td><div class="ckrow">
              <?=chk('evac','완강기',$EV)?><?=chk('evac','구조대',$EV)?><?=chk('evac','피난사다리',$EV)?>
              <?=chk('evac','공기안전매트',$EV)?><?=chk('evac','승강식피난기',$EV)?><?=chk('evac','유도등·유도표지',$EV)?>
            </div></td></tr>
          <tr><th class="lb1">화재안전<br>취약자</th>
            <td>
              <div class="ckrow" style="align-items:center;gap:10px">
                <span class="mini">인원</span><input type="number" name="weak_cnt" class="w90" value="<?=h($curData['weak_cnt'] ?? '')?>" placeholder="명">
                <span class="mini">위치</span><input type="text" name="weak_loc" value="<?=h($curData['weak_loc'] ?? '')?>" placeholder="예: 2층 사무실" class="w150">
              </div>
              <textarea name="weak_plan" rows="2" style="margin-top:8px" placeholder="피난보조자 지정, 피난방법 등 (고령자·장애인·임산부 등)"><?=h($curData['weak_plan'] ?? '')?></textarea>
            </td></tr>
          <tr><th class="lb1">집결지</th>
            <td><input type="text" name="assembly" value="<?=h($curData['assembly'] ?? '')?>" placeholder="예: 건물 앞 주차장"></td></tr>
        </table>

      <?php elseif ($cur === '11'): ?>
        <p class="desc">소방청 <b>서식 1.11 소방훈련 및 교육</b>입니다. 연간 실시 계획을 적습니다.</p>
        <table class="ft">
          <tr>
            <th class="lb2">구분</th><th class="lb2">실시 시기</th><th class="lb2">대상</th><th class="lb2">방법</th>
          </tr>
          <tr>
            <th class="lb2">소방훈련</th>
            <td><input type="text" name="t1_when" value="<?=h($curData['t1_when'] ?? '')?>" placeholder="예: 매년 4월·10월"></td>
            <td><input type="text" name="t1_who" value="<?=h($curData['t1_who'] ?? '')?>" placeholder="전 직원"></td>
            <td><input type="text" name="t1_how" value="<?=h($curData['t1_how'] ?? '')?>" placeholder="합동훈련"></td>
          </tr>
          <tr>
            <th class="lb2">소방교육</th>
            <td><input type="text" name="t2_when" value="<?=h($curData['t2_when'] ?? '')?>" placeholder="예: 매년 4월"></td>
            <td><input type="text" name="t2_who" value="<?=h($curData['t2_who'] ?? '')?>" placeholder="전 직원"></td>
            <td><input type="text" name="t2_how" value="<?=h($curData['t2_how'] ?? '')?>" placeholder="집합교육"></td>
          </tr>
          <tr>
            <th class="lb2">신규자<br>교육</th>
            <td><input type="text" name="t3_when" value="<?=h($curData['t3_when'] ?? '')?>" placeholder="입사 시"></td>
            <td><input type="text" name="t3_who" value="<?=h($curData['t3_who'] ?? '')?>" placeholder="신규 입사자"></td>
            <td><input type="text" name="t3_how" value="<?=h($curData['t3_how'] ?? '')?>"></td>
          </tr>
          <tr><th class="lb1">훈련 내용</th>
            <td colspan="3"><textarea name="memo" rows="3" placeholder="화재신고, 초기소화, 피난유도, 응급처치 등 훈련 시나리오"><?=h($curData['memo'] ?? '')?></textarea></td></tr>
        </table>

      <?php elseif ($cur === '14'): ?>
        <p class="desc">화재 발생 시 <b>초기대응 절차</b>입니다. 순서대로 무엇을 할지 정합니다.</p>
        <table class="ft">
          <tr><th class="lb1">① 화재경보</th>
            <td><input type="text" name="s1" value="<?=h($curData['s1'] ?? '')?>" placeholder="예: 발신기 누름 → 자동화재탐지설비 경보 → 비상방송"></td></tr>
          <tr><th class="lb1">② 화재신고</th>
            <td><input type="text" name="s2" value="<?=h($curData['s2'] ?? '')?>" placeholder="예: 119 신고 (담당: 비상연락반), 관계기관 통보"></td></tr>
          <tr><th class="lb1">③ 초기소화</th>
            <td><input type="text" name="s3" value="<?=h($curData['s3'] ?? '')?>" placeholder="예: 소화기·옥내소화전 사용 (담당: 초기소화반)"></td></tr>
          <tr><th class="lb1">④ 피난유도</th>
            <td><input type="text" name="s4" value="<?=h($curData['s4'] ?? '')?>" placeholder="예: 피난경로 확보, 대피 유도 (담당: 피난유도반)"></td></tr>
          <tr><th class="lb1">⑤ 인원확인</th>
            <td><input type="text" name="s5" value="<?=h($curData['s5'] ?? '')?>" placeholder="예: 집결지에서 인원 점검, 미대피자 파악"></td></tr>
          <tr><th class="lb1">비 고</th>
            <td><textarea name="memo" rows="3" placeholder="야간·휴일 등 상황별 대응 차이가 있으면 적으세요"><?=h($curData['memo'] ?? '')?></textarea></td></tr>
        </table>

      <?php elseif ($cur === '9'): ?>
        <div class="placeholder-note">
          📋 자위소방대 편성과 대원별 임무입니다. 인원이 자주 바뀌므로 <b>전용 편성표 페이지</b>에서 관리합니다.
        </div>
        <div style="text-align:center;padding:30px 20px;background:#fff8f5;border:1px solid #f3d0c0;border-radius:12px;margin-top:8px">
          <div style="font-size:40px;margin-bottom:10px">🧯</div>
          <p style="font-size:14px;color:var(--mut2);margin-bottom:16px">
            이름·연락처를 붙여넣으면 대장·부대장·활동조로 자동 배치됩니다.<br>편성표는 인쇄·PDF 저장도 됩니다.
          </p>
          <a class="btn btn--primary" href="/fire_plan_jawi.php" style="text-decoration:none">자위소방대 편성표 열기 →</a>
        </div>
        <textarea name="memo" style="margin-top:14px" placeholder="편성표 외에 자위소방대 관련 메모가 있으면 입력하세요… (선택)"><?=h($curData['memo'] ?? '')?></textarea>

      <?php else: ?>
        <?php
          $help = fp_item_help();
          $ph = [
            '7'  => "예) 1~2층 임차인 A상사 / 3~5층 임차인 B물산\n각 권원별 소방안전관리자: …\n공용부 관리 책임: 건물주",
            '8'  => "예) 협의회 구성: 건물주(위원장), 각 층 임차인 대표\n회의 주기: 반기 1회\n협의 사항: 공용부 점검, 합동훈련 일정",
            '10' => "예) 화기취급 작업(용접·절단) 시 사전 신고\n작업 전: 소화기 비치, 가연물 제거, 불티받이 설치\n작업 중: 감시자 배치\n작업 후: 1시간 이상 잔불 확인",
            '12' => "예) 위험물 종류: 제4류 제2석유류(경유) 200L\n저장 위치: 지하 1층 발전기실\n관리 방법: 이중 용기, 통풍, 화기 엄금 표지",
            '13' => "예) 업무수행 기록표를 매월 작성하여 보관\n작성자: 소방안전관리자\n보관 기간: 2년\n보관 장소: 방재실 캐비닛 / TWORIX 업무일지",
            '15' => "관할 소방서장이 추가로 요청한 사항이 있으면 적으세요. 없으면 비워두셔도 됩니다.",
          ];
        ?>
        <div class="placeholder-note">
          📋 <?=h($help[$cur] ?? '이 항목의 내용을 입력하세요.')?>
        </div>
        <textarea name="memo" rows="8" placeholder="<?=h($ph[$cur] ?? '내용을 입력하세요…')?>"><?=h($curData['memo'] ?? '')?></textarea>
      <?php endif; ?>

      <div class="actions">
        <button class="btn" type="submit" name="go" value="stay">저장</button>
        <button class="btn btn--primary" type="submit" name="go" value="next">저장하고 다음 서식 →</button>
        <?php if ($savedMsg): ?><span class="savemsg"><?=h($savedMsg)?></span><?php endif; ?>
      </div>
    </form>
  </div>

</div>
</main>

<?php if ($cur === '1'): ?>
<script>
/* ── 항목1 실시간: 해당여부 → 관련 항목 자동 생략 + 자위소방대 Type 표시 ── */
const state = {
  public: '<?=h($curData['public'] ?? '해당없음')?>',
  split : '<?=h($curData['split']  ?? '해당없음')?>',
  joint : '<?=h($curData['joint']  ?? '해당없음')?>',
  hazmat: '<?=h($curData['hazmat'] ?? '해당없음')?>'
};

document.querySelectorAll('.seg').forEach(seg=>{
  seg.querySelectorAll('label').forEach(lb=>{
    lb.addEventListener('click',()=>{
      seg.querySelectorAll('label').forEach(x=>x.classList.remove('on'));
      lb.classList.add('on');
      const name = lb.querySelector('input').name;
      if (name in state) state[name] = lb.querySelector('input').value;
      apply();
    });
  });
});
['fArea','fStaff'].forEach(id=>{const el=document.getElementById(id); if(el) el.addEventListener('input',apply);});
const ap=document.getElementById('fApproval'); if(ap) ap.addEventListener('change',apply);

function apply(){
  const skips = {};
  if (state.split ==='해당없음') skips['7']  = '권원분리 해당없음';
  if (state.joint ==='해당없음') skips['8']  = '공동관리 해당없음';
  if (state.hazmat==='해당없음') skips['12'] = '위험물 해당없음';

  const skipCodes = ['7','8','12'];
  document.querySelectorAll('.toc li').forEach(li=>{
    const cd=li.dataset.cd; if(!cd) return;
    const why=skips[cd];
    if (skipCodes.includes(cd)) {
      li.classList.toggle('skipped', !!why);
      let tag=li.querySelector('.why');
      if (why){ if(!tag){tag=document.createElement('span');tag.className='why';li.querySelector('a').appendChild(tag);} tag.textContent=why; }
      else if (tag) tag.remove();
    }
  });
  const sc=document.getElementById('skipCount'); if(sc) sc.textContent = Object.keys(skips).length;

  flow('flowSplit',  state.split ==='해당없음', '항목 7(관리 권원 분리)이 생략됩니다.');
  flow('flowJoint',  state.joint ==='해당없음', '항목 8(공동 소방안전관리)이 생략됩니다.');
  flow('flowHazmat', state.hazmat==='해당없음', '항목 12(위험물 저장·취급)가 생략됩니다.');

  const area=+(document.getElementById('fArea')?.value||0);
  const staff=+(document.getElementById('fStaff')?.value||0);
  let type='',reason='';
  if(state.public==='해당'){type='공공기관형';reason='공공기관';}
  else if(area>=30000){type='Type-Ⅰ';reason='연면적 3만㎡ 이상';}
  else if(staff>=50){type='Type-Ⅱ';reason='상시 근무 50명 이상';}
  else if(area||staff){type='Type-Ⅲ';reason='상시 근무 50명 미만';}
  const at=document.getElementById('autoType');
  at.classList.toggle('show',!!type);
  if(type) at.innerHTML='자위소방대 편성 <b>'+type+'</b> 자동 추천 ('+reason+') — 저장 시 2장에 적용됩니다.';

  const v=document.getElementById('fApproval')?.value;
  const ai=document.getElementById('autoInspect');
  if(ai){
    ai.classList.toggle('show',!!v);
    if(v){const m=new Date(v).getMonth()+1;
      ai.innerHTML='자체점검 시기 자동 계산: 종합점검 <b>'+m+'월</b> · 작동점검 <b>'+((m+5)%12+1)+'월</b>';}
  }
}
function flow(id,show,msg){const el=document.getElementById(id);if(!el)return;el.classList.toggle('show',show);if(show)el.textContent=msg;}
/* 체크박스 시각 토글 (소방청 서식의 □ 표시) */
document.querySelectorAll('.ck input[type=checkbox]').forEach(cb=>{
  cb.addEventListener('change',()=>cb.closest('.ck').classList.toggle('on', cb.checked));
});
apply();
</script>
<?php endif; ?>


<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>
</html>
