<?php
// work_log_form.php — 특정 월의 업무 수행 기록표 작성/수정 + 인쇄(PDF)
declare(strict_types=1);

/* 관리자가 이 회원 화면을 대리로 볼 때 위에 알림 띠를 붙입니다 */
@include_once __DIR__ . '/_imp.php';

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
$uidKey = app_user_key();
if ($uidKey === '') { die('<meta charset="utf-8">' . app_user_key_notice()); }
$uidKey = preg_replace('/[^A-Za-z0-9_]/', '_', (string)$uidKey);
$BASE   = __DIR__ . '/data/worklog/' . $uidKey;
if (!is_dir($BASE)) @mkdir($BASE, 0775, true);

function load_json(string $f): array {
  if (!file_exists($f)) return [];
  $r = @file_get_contents($f); if ($r===false || trim($r)==='') return [];
  $a = json_decode($r, true); return is_array($a) ? $a : [];
}
function save_json(string $f, array $arr): bool {
  if (!is_dir(dirname($f))) @mkdir(dirname($f), 0775, true);
  $tmp=$f.'.tmp'; file_put_contents($tmp, json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
  return @rename($tmp,$f);
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

// 등록된 서명 (재사용용)
$SIGN_FILE = $BASE . '/signature.json';
$signStore = load_json($SIGN_FILE);
$savedSign = $signStore['data'] ?? '';   // data:image/png;base64,...

/** 서명 데이터 유효성 (PNG data URL, 과도한 크기 차단) */
function valid_sign(string $s): bool {
  if ($s === '') return true;                       // 빈 값 허용(서명 없음)
  if (strlen($s) > 400000) return false;            // 약 400KB 상한
  return (bool)preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#', $s);
}

// 대상 월
$month = $_GET['month'] ?? $_POST['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');
$REC_FILE = $BASE . '/m' . $month . '.json';

$fixed = load_json($BASE . '/building.json');
$rec   = load_json($REC_FILE);

// 저장 처리
$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_rec') {
  if (!hash_equals($CSRF, $_POST['csrf'] ?? '')) { http_response_code(403); exit('CSRF'); }
  $items = ['sobang','pinan','hwagi','etc'];
  $rec = [
    'date'      => trim($_POST['date'] ?? ''),
    'performer' => trim($_POST['performer'] ?? ''),
  ];
  foreach ($items as $k) {
    $rec[$k] = [
      'note'   => trim($_POST[$k.'_note'] ?? ''),
      'result' => in_array($_POST[$k.'_result'] ?? '', ['양호','불량'], true) ? $_POST[$k.'_result'] : '',
      'action' => trim($_POST[$k.'_action'] ?? ''),
    ];
  }
  $rec['report'] = [
    'when'   => trim($_POST['r_when'] ?? ''),
    'method' => in_array($_POST['r_method'] ?? '', ['대면','서면','정보통신'], true) ? $_POST['r_method'] : '',
    'person' => trim($_POST['r_person'] ?? ''),
    'fix'    => in_array($_POST['r_fix'] ?? '', ['이전','제거','수리·교체','기타'], true) ? $_POST['r_fix'] : '',
  ];

  // 서명 처리
  $postSign = (string)($_POST['sign_data'] ?? '');
  if (valid_sign($postSign)) {
    $rec['sign'] = $postSign;
    // '기본 서명으로 등록' 체크 시 재사용 저장소에도 보관
    if (!empty($_POST['sign_remember']) && $postSign !== '') {
      save_json($SIGN_FILE, ['data'=>$postSign, 'updated'=>date('Y-m-d H:i:s')]);
      $savedSign = $postSign;
    }
  }

  save_json($REC_FILE, $rec);
  $saved = true;

  /* 저장이 끝나면 목록(work_log.php)으로 보냅니다.
     새로고침으로 같은 내용이 다시 저장되는 것도 함께 막아줍니다.
     ※ $url 헬퍼는 아래에서 정의되므로 여기서는 직접 만듭니다. */
  $go = '/work_log.php?saved=' . rawurlencode($month);
  if (is_admin() && trim((string)($_GET['uid'] ?? '')) !== '') {
    $go .= '&uid=' . rawurlencode($uidKey);
  }
  header('Location: ' . $go);
  exit;
}

// 값 헬퍼
$g = function($k, $d='') use ($rec) { return $rec[$k] ?? $d; };
$it = function($k, $sub) use ($rec) { return $rec[$k][$sub] ?? ''; };
$rp = function($k) use ($rec) { return $rec['report'][$k] ?? ''; };
// 확인내용: 그 달 기록이 있으면 그 값, 없으면(신규) 건물 정보의 기본값 사용
$hasRec = !empty($rec);
$noteVal = function($k) use ($rec, $fixed, $hasRec) {
  $cur = $rec[$k]['note'] ?? '';
  if ($cur !== '') return $cur;
  if (!$hasRec) return $fixed['note_'.$k] ?? '';   // 아직 저장 안 한 달이면 기본값 미리 채움
  return '';
};
$defPerformer = $g('performer') !== '' ? $g('performer') : ($fixed['performer'] ?? '');

// 이 달에 찍힌 서명 (없으면 등록된 기본 서명을 미리 채움)
$curSign = $rec['sign'] ?? '';
if ($curSign === '' && !$hasRec) $curSign = $savedSign;

// 체크 표시 헬퍼
function ck($cur,$val){ return $cur===$val ? '&#10004;' : ''; }  // ✔
$monthLabel = (function($m){ [$y,$mo]=explode('-',$m); return $y.'년 '.(int)$mo.'월'; })($month);
$nick = $_SESSION['nickname'] ?? '사용자';
$adminView = is_admin() && trim((string)($_GET['uid'] ?? $_POST['uid'] ?? '')) !== '';
$adminQuery = $adminView ? ('uid=' . rawurlencode($uidKey)) : '';
$url = function(string $path) use ($adminQuery): string {
  if ($adminQuery === '') return $path;
  return $path . (strpos($path, '?') === false ? '?' : '&') . $adminQuery;
};
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>업무 수행 기록표 <?=h($monthLabel)?> — TWORIX</title>
<style>
:root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--bd2:#d4dbe6;--fg:#1a2436;--mut2:#56627a;--brand:#2563eb;--brand2:#1d4ed8;--form-line-color:#333;--form-line-width:1px}
*{box-sizing:border-box;margin:0;padding:0}
html,body{background:var(--bg);color:var(--fg);font-family:Inter,ui-sans-serif,system-ui,"Apple SD Gothic Neo",sans-serif;line-height:1.5}
a{text-decoration:none}
.topbar{background:#fff;border-bottom:1px solid var(--bd);padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;gap:12px;position:sticky;top:0;z-index:20}
.topbar .brand{font-weight:800;font-size:20px;letter-spacing:.5px}
.topbar .actions{display:flex;gap:8px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:9px;border:1px solid var(--bd2);background:#fff;color:var(--fg);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit}
.btn:hover{border-color:var(--brand);color:var(--brand2)}
.btn--primary{background:var(--brand);border-color:var(--brand);color:#fff}
.btn--primary:hover{background:var(--brand2);color:#fff}
.toast{max-width:900px;margin:16px auto 0;background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;border-radius:9px;padding:10px 14px;font-size:13px}
.hint{max-width:900px;margin:12px auto 0;color:var(--mut2);font-size:13px;padding:0 8px}

/* ── 점검 진행 안내 ── */
.ckguide{max-width:900px;margin:12px auto 0;padding:12px 15px;border-radius:10px;
  background:#fffbeb;border:1px solid #f6d8a8;color:#92400e;font-size:13px;line-height:1.7}
.ckguide b{font-weight:800}
.ckguide__n{color:#b45309;font-weight:700}
.ckguide--done{background:#f6fdf8;border-color:#bfe6cb;color:#15803d}
/* 아직 결과를 안 고른 줄을 옅게 짚어줍니다 */
tr.row--todo td{background:#fffdf5}
tr.row--todo .res{box-shadow:inset 0 0 0 2px rgba(217,119,6,.22)}
/* 다 고르면 저장 버튼을 눈에 띄게 */
.btn--nudge{box-shadow:0 0 0 4px rgba(22,163,74,.26);
  animation:ckPulse 1.5s ease-in-out infinite}
@keyframes ckPulse{0%,100%{transform:translateY(0)}50%{transform:translateY(-2px)}}
@media(prefers-reduced-motion:reduce){.btn--nudge{animation:none}}
@media print{.ckguide{display:none!important}
  tr.row--todo td{background:transparent!important}
  tr.row--todo .res{box-shadow:none!important}}

/* A4 용지 */
.sheet{max-width:900px;margin:16px auto 40px;background:#fff;border:1px solid var(--bd);border-radius:8px;padding:26px 30px;box-shadow:0 10px 30px rgba(20,40,80,.06)}
.law{font-size:12px;color:#333;margin-bottom:6px}
.title{text-align:center;font-size:24px;font-weight:800;letter-spacing:6px;margin:6px 0 8px}
.guide{font-size:11px;color:#333;margin-bottom:10px}
table.f{width:100%;border-collapse:collapse;border-spacing:0;table-layout:fixed;
  border:var(--form-line-width) solid var(--form-line-color)}
table.f>tbody>tr>td,table.f>tbody>tr>th{border:var(--form-line-width) solid var(--form-line-color);padding:6px 8px;font-size:13px;vertical-align:middle;word-break:break-all}
table.f>tbody>tr>th{background:#f3f4f6;font-weight:700;text-align:center}
/* 연속된 표는 겹치지 않고 앞 표의 아래쪽 선 하나만 공유합니다. */
table.f--joined{margin-top:0!important;border-top:0!important}
table.f--joined>tbody>tr:first-child>td,table.f--joined>tbody>tr:first-child>th{border-top-width:0}
.lbl{background:#f3f4f6;font-weight:700;text-align:center;width:110px}
.center{text-align:center}
input.cell,textarea.cell{width:100%;border:0;background:transparent;font-size:13px;font-family:inherit;color:#111;resize:none;outline:none;padding:2px 0}
input.cell:focus,textarea.cell:focus{background:#eef5ff}
textarea.cell{min-height:64px}
.chk{display:inline-flex;align-items:center;gap:4px;margin-right:10px;font-size:13px;cursor:pointer}
.chk input{margin:0}
.chkline{display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap}
.res label{display:flex;align-items:center;gap:5px;font-size:13px;cursor:pointer;margin:2px 0}
.footnote{font-size:11px;color:#333;margin-top:12px;line-height:1.7}
.paper{font-size:11px;color:#333;text-align:right;margin-top:10px}

/* ★ 통합표는 20칸 기준입니다.
   한 행에서 colspan 합계가 20이 되도록 수정하면 셀 너비를 쉽게 바꿀 수 있습니다.
   예: colspan="4" = 20%, colspan="10" = 50% */
.master-table col{width:5%}
.master-table .center-cell{text-align:center}

/* 수행자 + 서명 */
.perf{display:flex;align-items:center;gap:8px}
.perf__name{flex:1 1 auto;min-width:60px}
.sign{display:flex;align-items:center;gap:6px;flex-shrink:0;min-width:0}
.sign img{height:34px;width:auto;max-width:120px;min-width:60px;object-fit:contain;flex-shrink:0}
.sign__btn{padding:4px 10px;border:1px solid var(--bd2);border-radius:7px;background:#fff;
  color:var(--mut2);font-size:11.5px;cursor:pointer;font-family:inherit;white-space:nowrap}
.sign__btn:hover{border-color:var(--brand);color:var(--brand2)}

/* 서명 모달 */
.mask{position:fixed;inset:0;background:rgba(15,22,40,.55);display:none;align-items:center;
  justify-content:center;z-index:60;padding:16px}
.mask.on{display:flex}
.modal{background:#fff;border-radius:14px;padding:20px;width:100%;max-width:460px;
  box-shadow:0 20px 60px rgba(20,40,80,.25)}
.modal h3{font-size:16px;font-weight:700;margin-bottom:4px}
.modal p{font-size:12.5px;color:var(--mut2);margin-bottom:14px}
#padWrap{border:1px dashed var(--bd2);border-radius:10px;background:#fbfcfe;position:relative}
#pad{display:block;width:100%;height:170px;touch-action:none;cursor:crosshair}
#padHint{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
  color:#b9c2d2;font-size:13px;pointer-events:none}
.modal__row{display:flex;align-items:center;gap:8px;margin-top:14px;flex-wrap:wrap}
.modal__row label{display:flex;align-items:center;gap:6px;font-size:12.5px;color:var(--mut2);cursor:pointer}
.modal__row .grow{margin-left:auto;display:flex;gap:8px}

/* ── 모바일: 표를 세로 카드로 재배치 ── */
@media screen and (max-width:640px){
  .topbar{padding:0 14px;height:auto;min-height:54px;flex-wrap:wrap;gap:8px;padding-top:8px;padding-bottom:8px}
  .topbar .brand{font-size:18px}
  .topbar .actions{width:100%;display:grid;grid-template-columns:auto 1fr 1fr;gap:6px}
  .topbar .actions .btn{justify-content:center;padding:10px 8px;font-size:12.5px}
  .toast,.hint{margin-left:12px;margin-right:12px;font-size:12.5px}

  .sheet{margin:12px;padding:16px 14px;border-radius:10px}
  .title{font-size:17px;letter-spacing:2px;margin:4px 0 6px}
  .law,.guide{font-size:10.5px}

  /* 표 → 블록 */
  table.f,table.f tbody,table.f tr,table.f td,table.f th{display:block;width:auto!important}
  table.f{margin-top:10px!important;border:1px solid #333;border-radius:8px;overflow:hidden}
  table.f colgroup{display:none}
  table.f tr{border-bottom:1px solid #333}
  table.f tr:last-child{border-bottom:0}
  table.f td,table.f th{border:0;border-bottom:1px solid #d8dde6;padding:9px 11px;font-size:13.5px}
  table.f tr>td:last-child,table.f tr>th:last-child{border-bottom:0}
  table.f th{background:#eef1f6;font-size:12px;text-align:left}
  .lbl{background:#f3f4f6;text-align:left!important;font-size:12px;color:#4a5568;
    padding:7px 11px!important;width:auto!important}
  .lbl br{display:none}

  /* 라벨 다음 칸에 여백 */
  input.cell,textarea.cell{font-size:15px;padding:4px 0}
  textarea.cell{min-height:56px}

  /* 항목 표: 라벨을 제목처럼 */
  .res{display:flex;gap:16px;padding:8px 11px!important}
  .res label{font-size:14px}
  .chk{font-size:13.5px;margin-right:12px}

  /* 수행자+서명 */
  .perf{flex-wrap:wrap;gap:10px}
  .perf__name{flex:1 1 100%}
  .sign{width:100%;justify-content:space-between;
    border-top:1px dashed var(--bd2);padding-top:8px}
  .sign img{height:40px;width:auto;max-width:150px;min-width:70px;flex-shrink:0}
  .sign__btn{padding:8px 14px;font-size:13px}

  /* 서명 모달 */
  .modal{padding:16px;border-radius:12px}
  #pad{height:200px}
  .modal__row .grow{width:100%;margin-left:0;margin-top:6px}
  .modal__row .grow .btn{flex:1;justify-content:center}
  .modal__row .btn{padding:11px 14px}

  .footnote{font-size:10.5px}
  .paper{display:none}
}

/* 수행일자 달력 */
.dwrap{position:relative;display:flex;align-items:center;gap:6px}
.dinput{flex:1;cursor:pointer}
.dbtn{flex-shrink:0;border:1px solid #d4dbe6;background:#fff;border-radius:7px;
  padding:3px 8px;font-size:14px;line-height:1.4;cursor:pointer}
.dbtn:hover{border-color:#2563eb}
.dpop{position:absolute;top:calc(100% + 6px);left:0;z-index:60;width:274px;
  background:#fff;border:1px solid #d4dbe6;border-radius:12px;padding:12px;
  box-shadow:0 12px 32px -10px rgba(15,30,60,.28)}
.dpop[hidden]{display:none}
.dpop__hd{font-size:13.5px;font-weight:800;text-align:center;margin-bottom:9px;color:#1a2436}
.dpop__wk,.dpop__grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px}
.dpop__wk{margin-bottom:4px}
.dpop__wk span{text-align:center;font-size:11px;font-weight:700;color:#7a8699;padding:2px 0}
.dpop__wk .sun,.dcell.sun{color:#dc2626}
.dpop__wk .sat,.dcell.sat{color:#2563eb}
.dcell{border:0;background:transparent;border-radius:8px;padding:7px 0;font-size:13.5px;
  font-family:inherit;color:#1a2436;cursor:pointer;transition:.12s}
.dcell:hover{background:#eef2ff}
.dcell--pad{cursor:default}
.dcell--pad:hover{background:transparent}
.dcell.is-today{outline:1px solid #93c5fd;font-weight:700}
.dcell.is-sel{background:#2563eb;color:#fff;font-weight:700}
.dcell.is-sel.sun,.dcell.is-sel.sat{color:#fff}
.dpop__ft{display:flex;gap:6px;margin-top:9px;padding-top:9px;border-top:1px solid #e3e8f0;flex-wrap:wrap}
.dmini{flex:1;border:1px solid #d4dbe6;background:#fff;border-radius:8px;padding:6px 8px;
  font-size:12px;font-family:inherit;cursor:pointer;white-space:nowrap}
.dmini:hover{border-color:#2563eb;color:#1d4ed8}
.dmini--close{flex:0 0 auto}
@media(max-width:560px){
  .dpop{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:min(320px,92vw)}
  .dcell{padding:11px 0;font-size:15px}
}
@media print{
  :root{--form-line-color:#000;--form-line-width:.25mm}
  *{-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .topbar,.toast,.hint,.mask{display:none !important}
  .dbtn,.dpop{display:none !important}
  .dwrap{display:block}
  .sign__btn{display:none !important}
  .sign img{height:26px !important;width:auto !important;max-width:95px !important;min-width:50px !important}
  body{background:#fff}
  .sheet{box-shadow:none;border:0;margin:0;max-width:none;border-radius:0;padding:0}
  input.cell:focus,textarea.cell:focus{background:transparent}
  @page{size:A4;margin:12mm}

  /* 한 장에 맞추기 — 전체 압축 */
  .sheet{font-size:10.5px}
  .title{font-size:18px;letter-spacing:3px;margin:2px 0 4px}
  .law,.guide{font-size:9px;margin-bottom:3px}
  /* ★ 표 전체에 '쪼개지 마'를 걸면, 표가 페이지에 살짝만 안 맞아도
     표 통째로 다음 페이지로 밀려버립니다 — 그게 2페이지로 넘어간 원인이었습니다.
     줄(행) 단위로만 안 쪼개지게 하고, 표 자체는 필요하면 줄 사이에서 넘어가게 둡니다. */
  table.f tr,table.f td,table.f th{page-break-inside:avoid !important;break-inside:avoid !important}
  table.f{border-collapse:collapse!important;border-spacing:0!important}
  /* ★ border-collapse 에서는 표 맨 가장자리(특히 우측) 선이 인쇄 시 절반만 그려지는 일이
     흔합니다. 표 자체에 바깥 테두리를 명시해 안쪽 선과 같은 굵기로 맞춥니다. */
  table.f{border:var(--form-line-width) solid var(--form-line-color)!important}
  table.f>tbody>tr>td,table.f>tbody>tr>th{
    border-color:var(--form-line-color)!important;
    border-style:solid!important;
    border-width:var(--form-line-width)!important;
  }
  table.f--joined{border-top:0!important}
  table.f--joined>tbody>tr:first-child>td,table.f--joined>tbody>tr:first-child>th{border-top-width:0!important}
  table.f td,table.f th{padding:3px 5px;font-size:10.5px;line-height:1.25}
  /* 확인내용/조치사항 textarea — 지난번 48mm는 실제 렌더링에서 2페이지로 넘어갔습니다.
     이번엔 여유를 크게 두고 32mm로 줄입니다(그래도 원래 34px의 약 4배 큽니다). */
  textarea.cell{min-height:32mm;font-size:10.5px;line-height:1.3}
  input.cell{font-size:10.5px}
  /* 확인결과(양호/불량) 라디오 버튼 — 인쇄용 크기 지정이 빠져있어서 브라우저 기본 크기·파란색으로
     크게 나오고 있었습니다. 등급·보고방법 체크박스(.chk input)와 똑같이 맞춥니다. */
  .res{vertical-align:middle}
  .res label{font-size:10px;margin:0;display:flex;align-items:center;gap:4px}
  .res input{width:11px;height:11px;margin:0;accent-color:#333}
  .chk{font-size:10px;margin-right:5px;white-space:nowrap}
  .chkline{display:flex;flex-wrap:nowrap;align-items:center;gap:6px;white-space:nowrap}
  .chk input{margin:0;width:11px;height:11px;accent-color:#333}
  .footnote{font-size:8.5px;margin-top:6px;line-height:1.45}
  .paper{font-size:8.5px;margin-top:4px}
  .perf{gap:5px}
}
</style>
</head>
<body>

<div class="topbar">
  <a class="brand" href="/index.php">소방계획서.w.l.f</a>
  <div class="actions">
    <a class="btn" href="<?=h($url('/work_log.php'))?>">← 목록</a>
    <button class="btn" id="saveBtn" type="button" onclick="document.getElementById('recform').requestSubmit()">저장</button>
    <button class="btn btn--primary" type="button" onclick="window.print()">🖨 PDF / 인쇄</button>
  </div>
</div>

<?php if ($saved): ?><div class="toast">✓ <?=h($monthLabel)?> 기록이 저장되었습니다. ‘PDF / 인쇄’로 내려받을 수 있습니다.</div><?php endif; ?>
<div class="hint">칸을 클릭해 입력한 뒤 <b>저장</b>하세요. <b>PDF / 인쇄</b>를 누르고 인쇄 대화상자에서 <b>대상: PDF로 저장</b>을 선택하면 서식이 그대로 저장됩니다. (<?=h($monthLabel)?> 기록)</div>

<!-- 점검 진행 안내 (아래 스크립트가 내용을 채웁니다) -->
<div class="ckguide no-print" id="checkGuide"></div>

<form id="recform" method="post" action="<?=h($url('/work_log_form.php'))?>">
<input type="hidden" name="csrf" value="<?=h($CSRF)?>">
<input type="hidden" name="action" value="save_rec">
<input type="hidden" name="month" value="<?=h($month)?>">

<div class="sheet">
  <div class="law">■ 화재의 예방 및 안전관리에 관한 법률 시행규칙 [별지 제12호서식]</div>
  <div class="title">소방안전관리자 업무 수행 기록표</div>
  <div class="guide">※ [ ]에는 해당되는 곳에 √표를 합니다.</div>

  <!--
    ★ 표 수정 방법
    이 표는 전체 너비를 20칸으로 나눈 구조입니다. 1칸 = 5%.
    같은 <tr> 안의 colspan 합계는 기본적으로 20이 되게 맞추세요.
    가로 셀 합치기: colspan 숫자를 늘립니다.
    세로 셀 합치기: rowspan 숫자를 늘립니다.
  -->
  <table class="f master-table">
    <colgroup>
      <?php for ($ci=0; $ci<20; $ci++): ?><col><?php endfor; ?>
    </colgroup>

    <!-- 수행일자 / 수행자 : 4 + 8 + 3 + 5 = 20 -->
    <tr>
      <td class="lbl" colspan="4">수행일자</td>
      <td colspan="8">
        <?php
          [$calY, $calM] = array_map('intval', explode('-', $month));
          $calDays  = (int)date('t', mktime(0, 0, 0, $calM, 1, $calY));
          $calFirst = (int)date('w', mktime(0, 0, 0, $calM, 1, $calY));   // 0=일요일
          $calToday = (date('Y-m') === $month) ? (int)date('j') : 0;
          $calCur   = $g('date');
          $calSel   = preg_match('/^' . preg_quote($month, '/') . '-(\d{2})$/', $calCur, $mm) ? (int)$mm[1] : 0;
        ?>
        <div class="dwrap">
          <input class="cell dinput" type="text" name="date" id="dateInput" autocomplete="off"
                 value="<?=h($calCur)?>" placeholder="<?=h($month)?>-__ (눌러서 날짜 선택)">
          <button type="button" class="dbtn" id="dateBtn" aria-expanded="false"
                  aria-controls="datePop" aria-label="달력 열기">📅</button>

          <div class="dpop" id="datePop" hidden>
            <div class="dpop__hd"><?=h($monthLabel)?></div>
            <div class="dpop__wk">
              <span class="sun">일</span><span>월</span><span>화</span><span>수</span>
              <span>목</span><span>금</span><span class="sat">토</span>
            </div>
            <div class="dpop__grid">
              <?php for ($i = 0; $i < $calFirst; $i++): ?><span class="dcell dcell--pad"></span><?php endfor; ?>
              <?php for ($d = 1; $d <= $calDays; $d++):
                      $w = ($calFirst + $d - 1) % 7;
                      $cls = 'dcell';
                      if ($w === 0) $cls .= ' sun';
                      if ($w === 6) $cls .= ' sat';
                      if ($d === $calToday) $cls .= ' is-today';
                      if ($d === $calSel)   $cls .= ' is-sel'; ?>
                <button type="button" class="<?=$cls?>" data-d="<?=$d?>"><?=$d?></button>
              <?php endfor; ?>
            </div>
            <div class="dpop__ft">
              <?php if ($calToday): ?>
                <button type="button" class="dmini" data-d="<?=$calToday?>">오늘 (<?=$calToday?>일)</button>
              <?php endif; ?>
              <button type="button" class="dmini" id="dClear">지우기</button>
              <button type="button" class="dmini dmini--close" id="dClose">닫기</button>
            </div>
          </div>
        </div>
      </td>
      <td class="lbl" colspan="3">수행자</td>
      <td colspan="5">
        <div class="perf">
          <input class="cell perf__name" type="text" name="performer" value="<?=h($defPerformer)?>" placeholder="성명">
          <div class="sign" id="signSlot">
            <img id="signImg" src="<?=h($curSign)?>" alt="" style="<?=$curSign===''?'display:none':''?>">
            <button type="button" class="sign__btn" id="btnSign"><?=$curSign===''?'서명하기':'서명 변경'?></button>
          </div>
        </div>
        <input type="hidden" name="sign_data" id="signData" value="<?=h($curSign)?>">
      </td>
    </tr>

    <!-- 소방안전관리대상물 -->
    <!-- 첫 칸은 아래 4개 행을 세로로 합칩니다. -->
    <tr>
      <td class="lbl" rowspan="4" colspan="3">소방안전<br>관리대상물</td>
      <td class="lbl" colspan="3">상호</td>
      <td colspan="7"><input class="cell" type="text" name="_sangho" value="<?=h($fixed['sangho'] ?? '')?>" readonly></td>
      <td class="lbl" colspan="2">등급</td>
      <td colspan="5">
        <?php foreach (['특급','1급','2급','3급'] as $gg): ?>
          <span class="chk"><input type="checkbox" disabled <?= (($fixed['grade'] ?? '')===$gg)?'checked':'' ?>> <?=$gg?></span>
        <?php endforeach; ?>
      </td>
    </tr>

    <!-- rowspan 3칸이 이미 있으므로 나머지 colspan 합계는 17 -->
    <tr>
      <td class="lbl" colspan="3">소재지</td>
      <td colspan="14"><input class="cell" type="text" name="_addr" value="<?=h($fixed['address'] ?? '')?>" readonly></td>
    </tr>

    <!-- 5개 정보의 제목 -->
    <tr>
      <td class="lbl" colspan="3">지하층</td>
      <td class="lbl" colspan="3">지상층</td>
      <td class="lbl" colspan="4">연면적(㎡)</td>
      <td class="lbl" colspan="4">바닥면적(㎡)</td>
      <td class="lbl" colspan="3">동수</td>
    </tr>
    <!-- 5개 정보의 값 -->
    <tr>
      <td class="center-cell" colspan="3"><?=h($fixed['floor_b'] ?? '')?></td>
      <td class="center-cell" colspan="3"><?=h($fixed['floor_a'] ?? '')?></td>
      <td class="center-cell" colspan="4"><?=h($fixed['area_t'] ?? '')?></td>
      <td class="center-cell" colspan="4"><?=h($fixed['area_f'] ?? '')?></td>
      <td class="center-cell" colspan="3"><?=h($fixed['dongsu'] ?? '')?></td>
    </tr>

    <!-- 업무 확인표 : 3 + 8 + 3 + 6 = 20 -->
    <tr>
      <th colspan="3">항 목</th>
      <th colspan="8">확인내용</th>
      <th colspan="3">확인결과</th>
      <th colspan="6">조치사항</th>
    </tr>
    <?php
      $rows = [
        ['sobang','소방시설'],
        ['pinan','피난방화시설'],
        ['hwagi','화기취급감독'],
        ['etc','기타사항'],
      ];
      foreach ($rows as [$k,$label]):
    ?>
    <tr data-row="<?=$k?>">
      <td class="lbl" colspan="3"><?=$label?></td>
      <td colspan="8"><textarea class="cell" name="<?=$k?>_note" placeholder="확인내용"><?=h($noteVal($k))?></textarea></td>
      <td class="res" colspan="3">
        <label><input type="radio" name="<?=$k?>_result" value="양호" <?= $it($k,'result')==='양호'?'checked':'' ?>> 양호</label>
        <label><input type="radio" name="<?=$k?>_result" value="불량" <?= $it($k,'result')==='불량'?'checked':'' ?>> 불량</label>
      </td>
      <td colspan="6"><textarea class="cell" name="<?=$k?>_action" placeholder="조치사항"><?=h($it($k,'action'))?></textarea></td>
    </tr>
    <?php endforeach; ?>

    <!-- 불량사항 개선보고 : 왼쪽 3칸은 2개 행을 세로로 합침 -->
    <tr>
      <td class="lbl" rowspan="2" colspan="3">불량사항<br>개선보고</td>
      <td class="lbl" colspan="3">보고일시</td>
      <td colspan="8">
        <span class="chkline">
          보고방법
          <?php foreach (['대면','서면','정보통신'] as $mm): ?>
            <label class="chk"><input type="radio" name="r_method" value="<?=$mm?>" <?= $rp('method')===$mm?'checked':'' ?>> <?=$mm?></label>
          <?php endforeach; ?>
        </span>
      </td>
      <td class="lbl" colspan="6">보고받은 사람</td>
    </tr>
    <tr>
      <td colspan="3"><input class="cell" type="text" name="r_when" value="<?=h($rp('when'))?>" placeholder="  .  .  ."></td>
      <td colspan="8">
        <span class="chkline">
          조치방법
          <?php foreach (['이전','제거','수리·교체','기타'] as $fx): ?>
            <label class="chk"><input type="radio" name="r_fix" value="<?=$fx?>" <?= $rp('fix')===$fx?'checked':'' ?>> <?=$fx?></label>
          <?php endforeach; ?>
        </span>
      </td>
      <td colspan="6"><input class="cell" type="text" name="r_person" value="<?=h($rp('person'))?>" placeholder="성명"></td>
    </tr>
  </table>

  <div class="footnote">
    ※ 작성요령<br>
    1. 소방안전관리대상물의 소방안전관리자는 소방안전관리업무를 수행한 날을 포함하여 월 1회 이상 작성<br>
    2. 당해연도 소방계획서 및 소방시설등(최초점검, 작동점검, 종합점검) 점검표에 따른 점검항목을 참고하여 작성<br>
    3. 소방안전관리대상물의 특성에 따라 기타사항에 추가항목을 작성<br>
    4. 경보설비의 수신기, 소화설비의 제어반 및 가압송수장치(펌프 등)를 중점적으로 확인하여 작성
  </div>
  <div class="paper">210mm×297mm[백상지(80g/㎡) 또는 중질지(80g/㎡)]</div>
</div>
</form>

<!-- 서명 모달 -->
<div class="mask" id="signMask">
  <div class="modal">
    <h3>서명</h3>
    <p>아래 칸에 마우스로 서명하세요.</p>
    <div id="padWrap">
      <canvas id="pad"></canvas>
      <div id="padHint">여기에 서명</div>
    </div>
    <div class="modal__row">
      <label><input type="checkbox" id="rememberSign" checked> 기본 서명으로 등록 (다음 달부터 자동 입력)</label>
    </div>
    <div class="modal__row">
      <button type="button" class="btn" id="padClear">지우기</button>
      <?php if ($savedSign !== ''): ?>
        <button type="button" class="btn" id="padSaved">등록된 서명 불러오기</button>
      <?php endif; ?>
      <div class="grow">
        <button type="button" class="btn" id="padCancel">취소</button>
        <button type="button" class="btn btn--primary" id="padOk">확인</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const mask = document.getElementById('signMask');
  const pad  = document.getElementById('pad');
  const hint = document.getElementById('padHint');
  const img  = document.getElementById('signImg');
  const data = document.getElementById('signData');
  const btn  = document.getElementById('btnSign');
  const savedSign = <?= json_encode($savedSign, JSON_UNESCAPED_SLASHES) ?>;
  let ctx, drawing = false, dirty = false;

  function setupCanvas(){
    const r = pad.getBoundingClientRect();
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    pad.width  = Math.round(r.width  * dpr);
    pad.height = Math.round(r.height * dpr);
    ctx = pad.getContext('2d');
    ctx.scale(dpr, dpr);
    ctx.lineWidth = 2.2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#111';
  }
  function pos(e){
    const r = pad.getBoundingClientRect();
    const t = e.touches ? e.touches[0] : e;
    return { x: t.clientX - r.left, y: t.clientY - r.top };
  }
  function start(e){ e.preventDefault(); drawing = true; dirty = true; hint.style.display='none';
    const p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); }
  function move(e){ if(!drawing) return; e.preventDefault();
    const p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); }
  function end(){ drawing = false; }

  pad.addEventListener('pointerdown', start);
  pad.addEventListener('pointermove', move);
  window.addEventListener('pointerup', end);

  function clearPad(){
    ctx.clearRect(0,0,pad.width,pad.height);
    dirty = false; hint.style.display='flex';
  }

  btn.addEventListener('click', function(){
    mask.classList.add('on');
    setTimeout(function(){ setupCanvas(); clearPad(); }, 30);
  });
  document.getElementById('padCancel').addEventListener('click', function(){ mask.classList.remove('on'); });
  document.getElementById('padClear').addEventListener('click', clearPad);

  const savedBtn = document.getElementById('padSaved');
  if (savedBtn) savedBtn.addEventListener('click', function(){
    if (!savedSign) return;
    const im = new Image();
    im.onload = function(){
      clearPad();
      const r = pad.getBoundingClientRect();
      const s = Math.min(r.width / im.width, r.height / im.height, 1);
      ctx.drawImage(im, (r.width - im.width*s)/2, (r.height - im.height*s)/2, im.width*s, im.height*s);
      dirty = true; hint.style.display='none';
    };
    im.src = savedSign;
  });

  document.getElementById('padOk').addEventListener('click', function(){
    if (!dirty) { alert('서명을 그려주세요.'); return; }

    // 그려진 영역만 잘라내고 적당한 크기로 축소해서 저장
    const url = trimAndResize(pad, 300, 90);
    data.value = url;
    img.src = url; img.style.display = '';
    btn.textContent = '서명 변경';
    let rem = document.getElementById('signRememberField');
    if (!rem) {
      rem = document.createElement('input');
      rem.type = 'hidden'; rem.name = 'sign_remember'; rem.id = 'signRememberField';
      document.getElementById('recform').appendChild(rem);
    }
    rem.value = document.getElementById('rememberSign').checked ? '1' : '';
    mask.classList.remove('on');
  });

  /** 빈 여백을 잘라내고 최대 maxW×maxH 안으로 축소한 PNG 반환 */
  function trimAndResize(src, maxW, maxH){
    const w = src.width, h = src.height;
    const im = src.getContext('2d').getImageData(0,0,w,h).data;
    let x0=w, y0=h, x1=0, y1=0, found=false;
    for (let y=0; y<h; y++){
      for (let x=0; x<w; x++){
        if (im[(y*w+x)*4+3] > 8){
          found = true;
          if (x<x0) x0=x; if (x>x1) x1=x;
          if (y<y0) y0=y; if (y>y1) y1=y;
        }
      }
    }
    if (!found) return src.toDataURL('image/png');
    const pad2 = 6;
    x0=Math.max(0,x0-pad2); y0=Math.max(0,y0-pad2);
    x1=Math.min(w-1,x1+pad2); y1=Math.min(h-1,y1+pad2);
    const cw = x1-x0+1, ch = y1-y0+1;
    const s = Math.min(maxW/cw, maxH/ch, 1);
    const out = document.createElement('canvas');
    out.width = Math.max(1, Math.round(cw*s));
    out.height = Math.max(1, Math.round(ch*s));
    out.getContext('2d').drawImage(src, x0, y0, cw, ch, 0, 0, out.width, out.height);
    return out.toDataURL('image/png');
  }

  mask.addEventListener('click', function(e){ if (e.target === mask) mask.classList.remove('on'); });
})();
</script>

<script>
(function(){
  var inp = document.getElementById('dateInput');
  var btn = document.getElementById('dateBtn');
  var pop = document.getElementById('datePop');
  if (!inp || !btn || !pop) return;

  var MONTH = <?=json_encode($month)?>;

  function open(){ pop.hidden = false; btn.setAttribute('aria-expanded','true'); }
  function close(){ pop.hidden = true;  btn.setAttribute('aria-expanded','false'); }
  function toggle(){ pop.hidden ? open() : close(); }

  function pick(d){
    var v = MONTH + '-' + (d < 10 ? '0' + d : d);
    inp.value = v;
    pop.querySelectorAll('.dcell').forEach(function(c){ c.classList.remove('is-sel'); });
    var hit = pop.querySelector('.dcell[data-d="' + d + '"]');
    if (hit) hit.classList.add('is-sel');
    close();
  }

  btn.addEventListener('click', function(e){ e.stopPropagation(); toggle(); });
  inp.addEventListener('click', function(e){ e.stopPropagation(); open(); });

  pop.addEventListener('click', function(e){
    e.stopPropagation();
    var t = e.target.closest('[data-d]');
    if (t){ pick(parseInt(t.getAttribute('data-d'), 10)); return; }
    if (e.target.id === 'dClear'){ inp.value = '';
      pop.querySelectorAll('.dcell').forEach(function(c){ c.classList.remove('is-sel'); });
      close(); return; }
    if (e.target.id === 'dClose'){ close(); }
  });

  document.addEventListener('click', function(){ if (!pop.hidden) close(); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && !pop.hidden) close(); });
})();
</script>

<script>
/* ── 점검 진행 안내 ──────────────────────────────────────────
   1) 아직 결과를 안 고른 항목이 있으면 → 그 줄을 짚어주고 '양호' 선택을 권합니다
   2) 네 항목을 다 고르면 → 저장 버튼을 눈에 띄게 합니다               */
(function(){
  var KEYS = ['sobang','pinan','hwagi','etc'];
  var NAMES = { sobang:'소방시설', pinan:'피난방화시설', hwagi:'화기취급감독', etc:'기타사항' };

  var bar  = document.getElementById('checkGuide');
  var save = document.getElementById('saveBtn');
  if (!bar) return;

  function picked(k){
    return !!document.querySelector('input[name="' + k + '_result"]:checked');
  }

  function update(){
    var done = KEYS.filter(picked);
    var left = KEYS.filter(function(k){ return !picked(k); });

    /* 아직 안 고른 줄에 옅은 표시를 남겨, 어디를 봐야 할지 알려줍니다 */
    KEYS.forEach(function(k){
      var row = document.querySelector('tr[data-row="' + k + '"]');
      if (row) row.classList.toggle('row--todo', !picked(k));
    });

    if (left.length === 0){
      bar.className = 'ckguide ckguide--done';
      bar.innerHTML = '✅ 네 항목 모두 확인했습니다. 이제 <b>저장</b>을 눌러주세요.';
      if (save) save.classList.add('btn--nudge');
    } else {
      bar.className = 'ckguide';
      bar.innerHTML = '점검을 마치셨으면 <b>확인결과</b>에서 <b>양호</b>를 눌러주세요 · ' +
        '남은 항목 <b>' + left.map(function(k){ return NAMES[k]; }).join(' · ') + '</b> ' +
        '<span class="ckguide__n">(' + done.length + '/' + KEYS.length + ')</span>';
      if (save) save.classList.remove('btn--nudge');
    }
  }

  KEYS.forEach(function(k){
    document.querySelectorAll('input[name="' + k + '_result"]').forEach(function(el){
      el.addEventListener('change', update);
    });
  });
  update();
})();
</script>
</body>
</html>
