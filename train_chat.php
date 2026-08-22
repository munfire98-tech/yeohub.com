<?php
/* =============================================================
   train_chat.php — 소방훈련·교육 실시 결과 기록부 문답형 작성
   ─────────────────────────────────────────────────────────────
   질문에 답하면 별지 제28호서식이 채워집니다.
   한 문항 답할 때마다 바로 저장되어, 중간에 나가도 이어서 할 수 있습니다.
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

require_once __DIR__ . '/train_db.php';
require_once __DIR__ . '/building_info.php';

/* ── 기록 준비 ────────────────────────────────────────────
   주소에 id 가 없을 때 무조건 새 기록을 만들면, 화면에 들어올 때마다
   빈 기록이 쌓입니다. 그래서 먼저 이어서 쓸 기록이 있는지 물어봅니다. */
$id  = (string)($_GET['id'] ?? '');
$rec = $id !== '' ? tr_load($id) : null;

/* 새로 시작을 고른 경우에만 만듭니다 */
if (!$rec && ($_GET['new'] ?? '') === '1') {
  $id = tr_create();
  header('Location: /train_chat.php?id=' . urlencode($id));
  exit;
}

if (!$rec) {
  $picks = tr_list();
  if (!$picks) {                       // 첫 기록이면 물어볼 것 없이 바로 시작
    $id = tr_create();
    header('Location: /train_chat.php?id=' . urlencode($id));
    exit;
  }
  /* 이어서 쓸 기록 고르기 화면 */
  $nickPick = $_SESSION['nickname'] ?? '사용자';
  ?>
  <!doctype html>
  <html lang="ko">
  <head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>훈련·교육 기록부 문답 — 소방계획서.com</title>
  <style>
  :root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--bd2:#d4dbe6;--fg:#1a2436;
    --mut:#7a8699;--mut2:#56627a;--brand:#2563eb;--brand2:#1d4ed8}
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:var(--bg);color:var(--fg);line-height:1.65;
    font-family:Inter,system-ui,"Apple SD Gothic Neo",sans-serif}
  a{text-decoration:none;color:inherit}
  .nav{background:#fff;border-bottom:1px solid var(--bd)}
  .nav__in{max-width:720px;margin:0 auto;padding:0 20px;height:56px;
    display:flex;align-items:center;justify-content:space-between}
  .brand{font-weight:800;font-size:21px}
  .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:9px;
    border:1px solid var(--bd2);background:#fff;font-size:13.5px;font-weight:600;transition:.15s}
  .btn:hover{border-color:var(--brand);color:var(--brand2)}
  .btn--pri{background:var(--brand);border-color:var(--brand);color:#fff}
  .btn--pri:hover{background:var(--brand2);color:#fff}
  .wrap{max-width:720px;margin:0 auto;padding:28px 20px 70px}
  h1{font-size:23px;font-weight:800;letter-spacing:-.3px}
  .lead{color:var(--mut2);font-size:14.5px;margin-top:8px}
  .card{background:var(--card);border:1px solid var(--bd);border-radius:14px;
    padding:18px 20px;margin-top:18px}
  .card h2{font-size:15px;font-weight:800;margin-bottom:4px}
  .card .sub{font-size:13px;color:var(--mut2);margin-bottom:12px}
  .row{display:flex;align-items:center;gap:12px;padding:12px 0;
    border-top:1px solid var(--bd);flex-wrap:wrap}
  .row:first-of-type{border-top:0}
  .row__m{flex:1;min-width:180px}
  .row__t{font-weight:700;font-size:14.5px}
  .row__d{font-size:12.5px;color:var(--mut2);margin-top:2px}
  .pill{font-size:11px;font-weight:800;padding:3px 9px;border-radius:999px}
  .pill--done{background:#f0fdf4;color:#15803d}
  .pill--wip{background:#fff7ed;color:#b45309}
  .newbox{display:flex;gap:14px;align-items:center;flex-wrap:wrap;
    background:#eff6ff;border:1px solid #bfdbfe;border-radius:13px;padding:16px 18px;margin-top:16px}
  .newbox__l{flex:1;min-width:200px;font-size:13.5px;color:#1e40af;line-height:1.65}
  </style>
  </head>
  <body>
  <nav class="nav"><div class="nav__in">
    <a class="brand" href="/index.php">소방계획서.com</a>
    <div style="display:flex;gap:8px">
      <a class="btn" href="/train.php">← 목록</a>
    </div>
  </div></nav>

  <main class="wrap">
    <h1>이어서 쓰시겠어요?</h1>
    <p class="lead">이미 작성 중인 훈련·교육 기록이 있습니다.
      새로 시작하면 기록이 하나 더 만들어집니다.</p>

    <div class="card">
      <h2>작성하던 기록</h2>
      <div class="sub">최근에 고친 것부터 보여드립니다.</div>
      <?php foreach (array_slice($picks, 0, 8) as $p):
        $pid   = (string)($p['id'] ?? '');
        $ttl   = trim((string)($p['title'] ?? '')) ?: '(대상명 미입력)';
        $tdate = trim((string)($p['train_date'] ?? ''));
        $upd   = substr((string)($p['updated_at'] ?? ''), 0, 16);
        $full  = function_exists('tr_is_complete') ? tr_is_complete($pid) : ($tdate !== '');
      ?>
        <div class="row">
          <div class="row__m">
            <div class="row__t"><?=h($ttl)?>
              <?php if ($full): ?><span class="pill pill--done">작성 완료</span>
              <?php else: ?><span class="pill pill--wip">작성 중</span><?php endif; ?>
            </div>
            <div class="row__d">
              <?= $tdate !== '' ? '훈련일 ' . h($tdate) . ' · ' : '훈련일 미입력 · ' ?>
              마지막 수정 <?=h($upd ?: '-')?>
            </div>
          </div>
          <a class="btn btn--pri" href="/train_chat.php?id=<?=h(rawurlencode($pid))?>">이어서 쓰기 →</a>
          <a class="btn" href="/train_edit.php?id=<?=h(rawurlencode($pid))?>">표로</a>
        </div>
      <?php endforeach; ?>
      <?php if (count($picks) > 8): ?>
        <div class="row"><div class="row__m row__d">…외 <?=count($picks) - 8?>건은 목록에서 확인하세요.</div>
          <a class="btn" href="/train.php">목록 보기</a></div>
      <?php endif; ?>
    </div>

    <div class="newbox">
      <div class="newbox__l"><b>새로 실시한 훈련이라면</b><br>
        새 기록을 만들어 처음부터 작성합니다.</div>
      <a class="btn btn--pri" href="/train_chat.php?new=1">+ 새 기록 시작하기</a>
    </div>
  </main>
  </body>
  </html>
  <?php
  exit;
}

/* ── 한 문항 저장 ─────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'save_step') {
  header('Content-Type: application/json; charset=utf-8');
  if (!hash_equals(tr_csrf(), (string)($_POST['csrf'] ?? ''))) {
    echo json_encode(['ok'=>false,'error'=>'세션이 만료되었습니다. 새로고침 후 다시 시도해 주세요.'], JSON_UNESCAPED_UNICODE); exit;
  }
  $patch = json_decode((string)($_POST['patch'] ?? '{}'), true);
  if (!is_array($patch)) $patch = [];

  $data = $rec['data'] ?? [];
  $allow = ['t_name','t_use','t_rep','t_tel','t_addr','t_grade','mgrs',
            'fire_date','fire_place','fire_kind','fire_teacher','fire_target','fire_join','fire_absent',
            'fire_material','fire_types','fire_c_sohwa','fire_c_tongbo','fire_c_pinan',
            'fire_content','fire_result','fire_problem','fire_improve',
            'edu_skip','edu_date','edu_place','edu_teacher','edu_target','edu_join','edu_absent',
            'edu_content','edu_result','edu_problem','edu_improve'];
  foreach ($patch as $k => $v) {
    if (!in_array($k, $allow, true)) continue;
    if ($k === 'fire_types' || $k === 'mgrs') { $data[$k] = is_array($v) ? $v : []; }
    else { $data[$k] = is_scalar($v) ? (string)$v : ''; }
  }
  $ok = tr_save($id, $data);
  echo json_encode(['ok'=>$ok, 'error'=>$ok?'':'저장하지 못했습니다.'], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ── 처음부터 다시 ────────────────────────────────────────
   지금까지 답한 훈련·교육 내용만 비웁니다.
   대상물 정보(대상명·등급 등)는 기본정보에서 다시 채워지므로 그대로 둡니다. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'reset') {
  if (!hash_equals(tr_csrf(), (string)($_POST['csrf'] ?? ''))) {
    http_response_code(403); exit('잘못된 요청입니다.');
  }
  $keepKeys = ['t_name','t_use','t_rep','t_tel','t_addr','t_grade','mgrs'];
  $old  = $rec['data'] ?? [];
  $fresh = [];
  foreach ($keepKeys as $k) { if (isset($old[$k])) $fresh[$k] = $old[$k]; }

  /* 올렸던 사진도 함께 정리합니다 */
  foreach ((array)($old['photos'] ?? []) as $pv) {
    if (trim((string)$pv) !== '') tr_photo_delete((string)$pv);
  }
  tr_save($id, $fresh);
  header('Location: /train_chat.php?id=' . urlencode($id) . '&reset=1');
  exit;
}
/* ── 사진 저장 ────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'save_photo') {
  header('Content-Type: application/json; charset=utf-8');
  if (!hash_equals(tr_csrf(), (string)($_POST['csrf'] ?? ''))) {
    echo json_encode(['ok'=>false,'error'=>'세션이 만료되었습니다.'], JSON_UNESCAPED_UNICODE); exit;
  }
  $slot = (string)($_POST['slot'] ?? '');
  if (!in_array($slot, ['fire1','fire2','edu1','edu2'], true)) {
    echo json_encode(['ok'=>false,'error'=>'잘못된 요청입니다.'], JSON_UNESCAPED_UNICODE); exit;
  }
  [$name, $err] = tr_photo_save($_FILES['photo'] ?? null);
  if ($err !== '') { echo json_encode(['ok'=>false,'error'=>$err], JSON_UNESCAPED_UNICODE); exit; }

  $data = $rec['data'] ?? [];
  $photos = $data['photos'] ?? [];
  $old = (string)($photos[$slot] ?? '');
  if ($name !== '') { if ($old !== '') tr_photo_delete($old); $photos[$slot] = $name; }
  $data['photos'] = $photos;
  tr_save($id, $data);
  echo json_encode(['ok'=>true, 'url'=>tr_photo_url($name)], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ── 화면 ─────────────────────────────────────────────── */
$d    = $rec['data'] ?? [];
$bi   = bi_load();
$mgrs = is_array($bi['mgrs'] ?? null) ? $bi['mgrs'] : [];
$nick = $_SESSION['nickname'] ?? '사용자';

/* 대상물 정보는 기본정보에서 자동으로 채운다 */
$autoBase = [
  't_name'  => (string)($bi['name'] ?? ''),
  't_use'   => (string)($bi['use'] ?? ''),
  't_addr'  => (string)($bi['address'] ?? ''),
  't_grade' => (string)($bi['grade'] ?? ''),
  't_rep'   => (string)($bi['rep'] ?? ''),
  't_tel'   => (string)($bi['tel'] ?? ''),
  'mgrs'    => $mgrs,
];
$mgrNames = [];
foreach ($mgrs as $m) { $n = trim((string)($m['name'] ?? '')); if ($n !== '') $mgrNames[] = $n; }

/* ── 자위소방대 편성표에서 끌어올 수 있는 것들 ──────────────
   fire_plan_jawi.php 로 만든 편성표(_jawi.json)에는
   대장·부대장·활동조가 들어 있습니다.
   훈련교관(대장)과 참석대상 인원을 여기서 미리 채웁니다. */
$TEAM = ['found' => false, 'total' => 0, 'chief' => '', 'summary' => '', 'names' => []];
if (function_exists('app_user_key')) {
  $tk = app_user_key();
  if ($tk !== '') {
    $tf = __DIR__ . '/data/fireplan/' . $tk . '/_jawi.json';
    if (is_file($tf)) {
      $ta = json_decode((string)@file_get_contents($tf), true);
      $tp = (is_array($ta) && $ta) ? ($ta[count($ta) - 1] ?? null) : null;
      if (is_array($tp)) {
        $names = []; $lines = [];
        $cn = trim((string)($tp['cmd']['name'] ?? ''));
        if ($cn !== '') { $names[] = $cn; $TEAM['chief'] = $cn; $lines[] = '대장 ' . $cn; }
        $dn = trim((string)($tp['deputy']['name'] ?? ''));
        if ($dn !== '') { $names[] = $dn; $lines[] = '부대장 ' . $dn; }
        foreach ((array)($tp['groups'] ?? []) as $g) {
          $gn = trim((string)($g['name'] ?? ''));
          $cnt = 0;
          foreach ((array)($g['members'] ?? []) as $m2) {
            $nm = trim((string)($m2['name'] ?? ''));
            if ($nm === '') continue;
            $names[] = $nm; $cnt++;
          }
          if ($cnt > 0) $lines[] = $gn . ' ' . $cnt . '명';
        }
        if ($names) {
          $TEAM['found']   = true;
          $TEAM['total']   = count($names);
          $TEAM['names']   = $names;
          $TEAM['summary'] = implode(' · ', $lines);
        }
      }
    }
  }
}

/* 기본정보의 근무인원 — 참석대상을 가늠할 때 씁니다 */
$wdDay   = (int)($bi['wd_day'] ?? 0);
$wdNight = (int)($bi['wd_night'] ?? 0);
$STAFF   = $wdDay + $wdNight;

/* 지하층이 없으면 '지하 주차장' 같은 선택지는 보여줄 이유가 없습니다 */
$floorB = (int)preg_replace('/[^0-9]/', '', (string)($bi['floor_b'] ?? ''));
$HINTS = [
  'has_basement' => $floorB > 0,
  'use'          => (string)($bi['use'] ?? ''),
];
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>훈련·교육 기록부 문답 작성 — yeohub</title>
<style>
:root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--bd2:#d4dbe6;--fg:#1a2436;
  --mut:#7a8699;--mut2:#56627a;--brand:#2563eb;--brand2:#1d4ed8;--accent:#0891b2}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--fg);line-height:1.65;
  font-family:Inter,system-ui,"Apple SD Gothic Neo","Malgun Gothic",sans-serif}
a{text-decoration:none;color:inherit}
button{font:inherit;color:inherit;cursor:pointer}
:focus-visible{outline:2px solid var(--brand);outline-offset:2px}

.nav{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.94);
  backdrop-filter:blur(10px);border-bottom:1px solid var(--bd)}
.nav__in{max-width:780px;margin:0 auto;padding:0 20px;height:56px;
  display:flex;align-items:center;justify-content:space-between;gap:12px}
.brand{font-weight:800;font-size:21px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 15px;border-radius:9px;
  border:1px solid var(--bd2);background:#fff;font-size:13px;font-weight:600;transition:.15s}
.btn:hover{border-color:var(--brand);color:var(--brand2)}
.btn--pri{background:var(--brand);border-color:var(--brand);color:#fff}
.btn--pri:hover{background:var(--brand2);color:#fff}
.btn--sm{padding:6px 12px;font-size:12.5px}

.prog{position:sticky;top:56px;z-index:45;background:#fff;border-bottom:1px solid var(--bd)}
.prog__in{max-width:780px;margin:0 auto;padding:11px 20px}
.prog__row{display:flex;justify-content:space-between;font-size:12.5px;color:var(--mut2);margin-bottom:6px}
.prog__row b{color:var(--brand2)}
.bar{height:6px;background:#eef2f7;border-radius:3px;overflow:hidden}
.bar i{display:block;height:100%;background:var(--brand);width:0;transition:width .45s cubic-bezier(.2,.7,.3,1)}

.wrap{max-width:780px;margin:0 auto;padding:24px 20px 70px}
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
  font-size:13.5px;font-weight:500;transition:.14s;text-align:left}
.opt:hover{border-color:var(--brand);color:var(--brand2);background:#f7faff}
.opt.on{background:var(--brand);border-color:var(--brand);color:#fff}
.opt--long{border-radius:11px;line-height:1.5;max-width:100%}
.inrow{display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap}
.inrow input,.inrow textarea{flex:1;min-width:170px;padding:11px 14px;border:1px solid var(--bd2);
  border-radius:11px;background:#fff;font-size:14.5px;font-family:inherit}
.inrow textarea{min-height:86px;resize:vertical;line-height:1.6}
.inrow input:focus,.inrow textarea:focus{outline:none;border-color:var(--brand);
  box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.subrow{display:flex;gap:8px;margin-top:9px;flex-wrap:wrap}

.dtrow{display:flex;gap:8px;flex-wrap:wrap}
.dtrow input{padding:11px 13px;border:1px solid var(--bd2);border-radius:11px;
  font-size:14.5px;font-family:inherit;background:#fff;cursor:pointer}
.nrow{display:flex;gap:16px;flex-wrap:wrap}
.nbox{display:flex;flex-direction:column;gap:5px}
.nbox__l{font-size:12px;color:var(--mut2);font-weight:700}
.nctl{display:inline-flex;align-items:center;gap:5px}
.nbtn{width:34px;height:34px;border:1px solid var(--bd2);background:#fff;border-radius:9px;
  font-size:17px;line-height:1;color:var(--mut2)}
.nbtn:hover{border-color:var(--brand);color:var(--brand2)}
.ncell{width:66px;text-align:center;padding:8px 4px;border:1px solid var(--bd2);
  border-radius:9px;font-size:15px;font-family:inherit}
.nauto{font-size:12px;color:var(--mut);align-self:center}

.pslot{display:flex;flex-direction:column;gap:7px;max-width:260px}
.pdrop{display:flex;align-items:center;justify-content:center;aspect-ratio:4/3;
  border:1.5px dashed var(--bd2);border-radius:11px;background:#fff;cursor:pointer;overflow:hidden}
.pdrop:hover{border-color:var(--brand);background:#f7faff}
.pdrop img{width:100%;height:100%;object-fit:cover}
.pdrop__ph{font-size:12.5px;color:var(--mut);text-align:center;line-height:1.9}

.done{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:22px;margin-left:42px}
.done h2{font-size:18px;font-weight:800;margin-bottom:12px}
.sum{display:flex;justify-content:space-between;gap:14px;padding:8px 0;
  border-top:1px solid var(--bd);font-size:14px;flex-wrap:wrap}
.sum:first-of-type{border-top:0}
.sum__k{color:var(--mut2);font-size:13px}
.sum__v{font-weight:600;text-align:right;max-width:60%}
.sum__v.none{color:var(--mut);font-weight:400}
.doneRow{display:flex;gap:9px;flex-wrap:wrap;margin-top:18px}

.typing{display:inline-flex;gap:4px;align-items:center;padding:3px 0}
.typing i{width:6px;height:6px;border-radius:50%;background:var(--mut);display:block;animation:blink 1.2s infinite}
.typing i:nth-child(2){animation-delay:.18s}
.typing i:nth-child(3){animation-delay:.36s}
@keyframes blink{0%,60%,100%{opacity:.28}30%{opacity:1}}
@media(max-width:560px){.answer,.done{margin-left:0}.msg__b{max-width:calc(100% - 42px)}}
@media(prefers-reduced-motion:reduce){*{animation-duration:.001ms!important;transition-duration:.001ms!important}}
</style>
</head>
<body>

<nav class="nav">
  <div class="nav__in">
    <a class="brand" href="/index.php">소방계획서.com</a>
    <div style="display:flex;gap:8px">
      <form method="post" style="display:inline"
            onsubmit="return confirm('지금까지 답한 훈련·교육 내용을 모두 지우고 처음부터 다시 시작합니다.\n계속할까요?')">
        <input type="hidden" name="act" value="reset">
        <input type="hidden" name="csrf" value="<?=h(tr_csrf())?>">
        <button class="btn" type="submit">↺ 처음부터 다시</button>
      </form>
      <a class="btn" href="/train_edit.php?id=<?=h(rawurlencode($id))?>">표로 작성</a>
      <a class="btn" href="/train.php">← 목록</a>
    </div>
  </div>
</nav>

<div class="prog">
  <div class="prog__in">
    <div class="prog__row">
      <span>소방훈련·교육 기록부</span>
      <span><b id="pPct">0%</b> · <span id="pNum">0/0</span></span>
    </div>
    <div class="bar"><i id="pBar"></i></div>
  </div>
</div>

<main class="wrap">
  <?php if (isset($_GET['reset'])): ?>
    <div style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;border-radius:11px;
                padding:12px 15px;font-size:13.5px;margin-bottom:14px">
      ↺ 처음부터 다시 시작합니다. 대상물 정보는 기본정보에서 그대로 가져옵니다.</div>
  <?php endif; ?>
  <div id="chat"></div>
</main>

<script>
var CSRF = <?=json_encode(tr_csrf())?>;
var SAVED = <?=json_encode($d, JSON_UNESCAPED_UNICODE)?>;
var AUTO  = <?=json_encode($autoBase, JSON_UNESCAPED_UNICODE)?>;
var MGRS  = <?=json_encode($mgrNames, JSON_UNESCAPED_UNICODE)?>;
var TEAM  = <?=json_encode($TEAM, JSON_UNESCAPED_UNICODE)?>;   /* 자위소방대 편성표 */
var STAFF = <?=(int)$STAFF?>;                                   /* 기본정보 근무인원 */
var HINTS = <?=json_encode($HINTS, JSON_UNESCAPED_UNICODE)?>;
var NICK  = <?=json_encode($nick, JSON_UNESCAPED_UNICODE)?>;
var BNAME = <?=json_encode((string)($bi['name'] ?? ''), JSON_UNESCAPED_UNICODE)?>;

var chat = document.getElementById('chat');
var step = 0;

function esc(s){ return String(s==null?'':s)
  .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function md(s){ return esc(s).replace(/\*\*(.+?)\*\*/g,'<b>$1</b>').replace(/\n/g,'<br>'); }
function down(){ requestAnimationFrame(function(){
  window.scrollTo({top:document.body.scrollHeight, behavior:'smooth'}); }); }
function bot(html, hint){
  var d=document.createElement('div'); d.className='msg';
  d.innerHTML='<div class="msg__av">🚒</div><div class="msg__b">'+html+
    (hint?'<div class="hint">'+esc(hint)+'</div>':'')+'</div>';
  chat.appendChild(d); down(); return d;
}
function me(t){
  var d=document.createElement('div'); d.className='msg msg--me';
  d.innerHTML='<div class="msg__av">🙂</div><div class="msg__b">'+esc(t)+'</div>';
  chat.appendChild(d); down();
}
function typing(cb){ var x=bot('<span class="typing"><i></i><i></i><i></i></span>');
  setTimeout(function(){ x.remove(); cb(); }, 330); }
function clearBox(){ var a=document.getElementById('ansBox'); if(a) a.remove(); }
function box(){ clearBox(); var d=document.createElement('div');
  d.className='answer'; d.id='ansBox'; chat.appendChild(d); down(); return d; }

/* ── 저장 ── */
function save(patch, done){
  var fd=new FormData();
  fd.append('act','save_step'); fd.append('csrf',CSRF);
  fd.append('patch', JSON.stringify(patch));
  fetch(location.pathname + location.search, {method:'POST', body:fd, credentials:'same-origin'})
    .then(function(r){ return r.json(); })
    .then(function(j){
      if(!j || !j.ok) bot(md('⚠️ ' + ((j&&j.error)?j.error:'저장하지 못했습니다.')));
      if(done) done();
    })
    .catch(function(){ bot(md('⚠️ 연결이 끊겼습니다. 잠시 후 다시 시도해 주세요.')); if(done) done(); });
}
function put(patch, shown, next){
  for(var k in patch) SAVED[k]=patch[k];
  clearBox(); if(shown!==null) me(shown);
  save(patch, function(){ step++; bump(); go(); });
}

/* ── 질문 ── */
var STEPS = [
  { id:'fire_date', q:'소방훈련을 언제 하셨나요?', type:'datetime',
    hint:'날짜 칸을 누르면 달력이, 시간 칸을 누르면 시계가 나옵니다.' },

  { id:'fire_place', q:'어디서 하셨나요?', type:'choice+',
    options:['지상 1층 로비','지하 주차장','옥외 주차장','건물 전체','옥상'] },

  { id:'fire_kind', q:'자체훈련인가요, 소방서와 함께한 합동훈련인가요?', type:'choice',
    options:['자체훈련','합동훈련'] },

  { id:'fire_teacher', q:'훈련교관은 누구셨나요?', type:'mgr',
    hint:'보통 소방안전관리자가 맡습니다.' },

  { id:'__firecount', q:'참석 인원을 알려주세요.', type:'count', prefix:'fire',
    hint:'미참석 인원은 자동으로 계산됩니다.' },

  { id:'fire_types', q:'어떤 훈련을 하셨나요? 하신 것을 모두 골라주세요.', type:'multi',
    options:['소화훈련','통보훈련','피난훈련'] },

  { id:'fire_c_sohwa', q:'소화훈련은 어떻게 진행하셨나요?', type:'pick',
    only:'소화훈련',
    samples:['소화기 사용법 교육 및 실습(안전핀 제거 → 노즐 조준 → 손잡이 압착)',
             '옥내소화전 전개 및 방수 실습',
             '주방 자동소화장치 작동 상태 확인 및 설명'] },

  { id:'fire_c_tongbo', q:'통보훈련은 어떻게 진행하셨나요?', type:'pick',
    only:'통보훈련',
    samples:['화재 발견자 → 방재실 통보 → 119 신고 순서 숙지',
             '비상방송설비를 이용한 전관 방송 실시',
             '입주사 비상연락망을 통한 상황 전파 훈련'] },

  { id:'fire_c_pinan', q:'피난훈련은 어떻게 진행하셨나요?', type:'pick',
    only:'피난훈련',
    samples:['층별 피난경로를 따라 지정 집결지까지 대피',
             '피난유도등·유도표지 확인 후 계단 이용 대피',
             '대피 완료 후 층별 인원 점검 및 미대피자 확인'] },

  { id:'fire_material', q:'훈련에 사용한 재료가 있나요?', type:'multi+',
    options:['소화기','옥내소화전','방연마스크','완강기','소화기 시뮬레이터','사용 안 함'] },

  { id:'fire_result', q:'훈련 성과는 어땠나요?', type:'pick',
    samples:['전 층 재실자가 5분 이내에 지정 집결지까지 대피 완료',
             '참석자 전원이 소화기 사용법을 직접 실습하고 숙지함',
             '자위소방대원이 각자 맡은 역할을 정확히 수행함'] },

  { id:'fire_problem', q:'훈련하면서 아쉬웠던 점이 있나요?', type:'pick', skip:true,
    samples:['일부 층에서 대피 시작이 늦어 전체 대피 시간이 길어짐',
             '비상방송이 일부 구역에서 잘 들리지 않음',
             '피난통로에 적치물이 있어 이동이 지체됨',
             '특이사항 없음'] },

  { id:'fire_improve', q:'다음에는 어떻게 보완하시겠어요?', type:'pick', skip:true,
    samples:['입주사에 대피 개시 절차를 다시 안내하고 다음 훈련에서 확인',
             '비상방송 음량과 스피커 상태를 점검 후 보수',
             '피난통로 적치물 정기 점검을 월 1회 실시',
             '다음 훈련 시 동일 시나리오로 재실시하여 개선 여부 확인'] },

  { id:'edu_skip', q:'소방안전교육도 함께 하셨나요?', type:'choice',
    options:['네, 교육도 했습니다','훈련만 했습니다'],
    map:{'네, 교육도 했습니다':'', '훈련만 했습니다':'1'} },

  { id:'edu_date', q:'교육은 언제 하셨나요?', type:'datetime', needEdu:true },
  { id:'edu_place', q:'교육 장소는 어디였나요?', type:'choice+', needEdu:true,
    options:['2층 회의실','지상 1층 로비','강당','각 층 사무실'] },
  { id:'edu_teacher', q:'교육 강사는 누구셨나요?', type:'mgr', needEdu:true },
  { id:'__educount', q:'교육 참석 인원을 알려주세요.', type:'count', prefix:'edu', needEdu:true },
  { id:'edu_content', q:'어떤 내용으로 교육하셨나요?', type:'pick', needEdu:true,
    samples:['소화기 등 소방시설의 위치와 사용법',
             '화재 발생 시 신고 요령과 초기 대응 절차',
             '피난경로 및 비상구 위치, 대피 시 유의사항',
             '자위소방대 편성과 각자의 임무'] },
  { id:'edu_result', q:'교육 성과는 어땠나요?', type:'pick', needEdu:true, skip:true,
    samples:['참석자 대부분이 소방시설 위치와 사용법을 숙지함',
             '질의응답을 통해 궁금한 점을 해소함',
             '입주사별 담당자가 자기 역할을 확인함'] },
  { id:'edu_problem', q:'교육에서 아쉬웠던 점이 있나요?', type:'pick', needEdu:true, skip:true,
    samples:['교대 근무자가 참석하지 못함','교육 시간이 짧아 실습이 부족했음','특이사항 없음'] },
  { id:'edu_improve', q:'교육은 어떻게 보완하시겠어요?', type:'pick', needEdu:true, skip:true,
    samples:['교대 근무자를 위한 별도 회차를 마련','실습 시간을 늘려 재실시','교육 자료를 사내 게시판에 상시 게시'] },

  { id:'__photo', q:'훈련·교육 사진을 넣어두시겠어요?', type:'photo', skip:true,
    hint:'서식 뒤쪽에 들어갑니다. 나중에 표에서 더 올리셔도 됩니다.' }
];

/* ── 진행률 ── */
function visible(){
  return STEPS.filter(function(s){
    if (s.needEdu && SAVED.edu_skip === '1') return false;
    if (s.only && (SAVED.fire_types||[]).indexOf(s.only) < 0) return false;
    return true;
  });
}
function bump(){
  var vs = visible(), n = 0;
  vs.forEach(function(s){
    if (s.type === 'count') { if ((SAVED[s.prefix+'_target']||'') !== '') n++; return; }
    if (s.id === '__photo') { var p = SAVED.photos||{}; if (p.fire1||p.edu1) n++; return; }
    if (s.type === 'multi' || s.type === 'multi+') { if ((SAVED[s.id]||[]).length) n++; return; }
    if ((SAVED[s.id]||'') !== '') n++;
  });
  var pct = vs.length ? Math.round(n/vs.length*100) : 0;
  document.getElementById('pPct').textContent = pct + '%';
  document.getElementById('pNum').textContent = n + '/' + vs.length;
  document.getElementById('pBar').style.width = pct + '%';
}
function answered(s){
  if (s.type === 'count') return (SAVED[s.prefix+'_target']||'') !== '';
  if (s.id === '__photo') { var p = SAVED.photos||{}; return !!(p.fire1||p.edu1); }
  if (s.type === 'multi' || s.type === 'multi+') return (SAVED[s.id]||[]).length > 0;
  if (s.id === 'edu_skip') return SAVED.edu_skip !== undefined && SAVED.edu_skip !== null && SAVED.__eduAsked;
  return (SAVED[s.id]||'') !== '';
}

/* ── 시작 ── */
function start(){
  /* 대상물 정보는 기본정보에서 자동으로 채워 넣는다 */
  var need = {};
  ['t_name','t_use','t_addr','t_grade','t_rep','t_tel'].forEach(function(k){
    if (!(SAVED[k]||'').trim() && (AUTO[k]||'').trim()) need[k] = AUTO[k];
  });
  if (!(SAVED.mgrs||[]).length && (AUTO.mgrs||[]).length) need.mgrs = AUTO.mgrs;

  var msg = '안녕하세요' + (NICK && NICK!=='사용자' ? ', ' + NICK + '님' : '') +
    '. 소방훈련·교육 기록부를 함께 채워보겠습니다.\n\n';
  if (Object.keys(need).length){
    msg += '**' + (BNAME || '건물') + '**의 대상물 정보는 기본정보에서 가져와 이미 채웠습니다. ' +
           '훈련 내용만 답해 주시면 됩니다.';
    save(need, function(){ for(var k in need) SAVED[k]=need[k]; bump(); });
  } else {
    msg += '훈련 내용을 하나씩 여쭤보겠습니다.';
  }
  if (TEAM && TEAM.found) {
    msg += '\n\n자위소방대 편성표(**' + TEAM.total + '명**)도 있어서, ' +
           '참석대상 인원과 교관은 눌러서 바로 넣으실 수 있습니다.';
  }
  bot(md(msg));
  bump(); go();
}

function go(){
  var vs = visible();
  while (step < vs.length && answered(vs[step])) step++;
  if (step >= vs.length) { finish(); return; }
  typing(function(){ ask(vs[step]); });
}

function ask(s){
  bot(md(s.q), s.hint||'');
  var b = box();

  if (s.type === 'datetime'){
    var row=document.createElement('div'); row.className='dtrow';
    var di=document.createElement('input'); di.type='date';
    var ti=document.createElement('input'); ti.type='time'; ti.step='300';
    var cur=/^(\d{4}-\d{2}-\d{2})(?:\s+(\d{2}:\d{2}))?/.exec(SAVED[s.id]||'');
    if(cur){ di.value=cur[1]; if(cur[2]) ti.value=cur[2]; }
    else { di.value = new Date().toISOString().slice(0,10); }
    row.appendChild(di); row.appendChild(ti); b.appendChild(row);
    mkGo(b, function(){
      if(!di.value){ di.focus(); return; }
      var v = di.value + (ti.value ? ' ' + ti.value : '');
      var p={}; p[s.id]=v; put(p, v, null);
    });
    addReview(b, s);
    return;
  }

  if (s.type === 'choice' || s.type === 'choice+'){
    var w=document.createElement('div'); w.className='opts';
    /* 지하층이 없는 건물에 '지하 주차장'을 권하지 않도록,
       기본정보의 층수를 보고 맞지 않는 보기는 뺍니다. */
    var opts = (s.options||[]).filter(function(o){
      if (!HINTS.has_basement && o.indexOf('지하') === 0) return false;
      return true;
    });
    opts.forEach(function(o){
      var btn=document.createElement('button'); btn.className='opt'; btn.type='button'; btn.textContent=o;
      btn.onclick=function(){
        var val = (s.map && s.map[o]!==undefined) ? s.map[o] : o;
        var p={}; p[s.id]=val;
        if (s.id==='edu_skip') SAVED.__eduAsked = true;
        put(p, o, null);
      };
      w.appendChild(btn);
    });
    b.appendChild(w);
    if (s.type === 'choice+') addFree(b, s, '직접 입력');
    addSkip(b, s);
    addReview(b, s);
    return;
  }

  if (s.type === 'mgr'){
    var w2=document.createElement('div'); w2.className='opts';
    /* 소방안전관리자에 더해, 편성표의 자위소방대장도 후보로 올립니다.
       실제로 훈련을 이끄는 사람인 경우가 많습니다. */
    var cands = (MGRS||[]).slice();
    if (TEAM && TEAM.chief && cands.indexOf(TEAM.chief) === -1) cands.push(TEAM.chief);
    cands.forEach(function(nm){
      var btn=document.createElement('button'); btn.className='opt'; btn.type='button'; btn.textContent=nm;
      btn.onclick=function(){ var p={}; p[s.id]=nm; put(p, nm, null); };
      w2.appendChild(btn);
    });
    b.appendChild(w2);
    addFree(b, s, cands.length ? '다른 분이에요' : '이름 입력');
    addSkip(b, s);
    addReview(b, s);
    return;
  }

  if (s.type === 'multi' || s.type === 'multi+'){
    var picked = (SAVED[s.id]||[]).slice();
    var w3=document.createElement('div'); w3.className='opts';
    s.options.forEach(function(o){
      var btn=document.createElement('button'); btn.className='opt'; btn.type='button'; btn.textContent=o;
      if(picked.indexOf(o)>=0) btn.classList.add('on');
      btn.onclick=function(){
        var i=picked.indexOf(o);
        if(i>=0){ picked.splice(i,1); btn.classList.remove('on'); }
        else { picked.push(o); btn.classList.add('on'); }
      };
      w3.appendChild(btn);
    });
    b.appendChild(w3);
    mkGo(b, function(){
      if(!picked.length){ alert('하나 이상 골라주세요.'); return; }
      var p={};
      if (s.type === 'multi+') p[s.id] = picked.join(', ');
      else p[s.id] = picked;
      put(p, picked.join(', '), null);
    });
    addReview(b, s);
    return;
  }

  if (s.type === 'count'){
    var pfx = s.prefix;
    var row2=document.createElement('div'); row2.className='nrow';
    var tg=mkNum('참석대상', SAVED[pfx+'_target']||'');
    var jn=mkNum('참석',     SAVED[pfx+'_join']||'');
    var ab=document.createElement('div'); ab.className='nbox';
    ab.innerHTML='<span class="nbox__l">미참석</span>';
    var abv=document.createElement('div'); abv.className='nctl';
    var abi=document.createElement('input'); abi.className='ncell'; abi.readOnly=true; abi.value='0';
    abv.appendChild(abi); abv.appendChild(Object.assign(document.createElement('span'),{className:'nauto',textContent:'자동'}));
    ab.appendChild(abv);
    row2.appendChild(tg.el); row2.appendChild(jn.el); row2.appendChild(ab);
    b.appendChild(row2);

    /* 참석대상 인원을 매번 세어 적지 않도록, 이미 아는 숫자를 버튼으로 내어줍니다.
       편성표 총원과 기본정보의 근무인원이 그것입니다. */
    (function(){
      var picks = [];
      if (TEAM && TEAM.found && TEAM.total > 0)
        picks.push(['자위소방대 ' + TEAM.total + '명', TEAM.total]);
      if (STAFF > 0 && STAFF !== (TEAM && TEAM.total))
        picks.push(['근무인원 ' + STAFF + '명', STAFF]);
      if (!picks.length) return;

      var wrap = document.createElement('div');
      wrap.style.cssText = 'display:flex;gap:7px;flex-wrap:wrap;align-items:center;margin-top:10px';
      var lab = document.createElement('span');
      lab.style.cssText = 'font-size:12px;color:var(--mut)';
      lab.textContent = '참석대상 바로 넣기';
      wrap.appendChild(lab);

      picks.forEach(function(pk){
        var btn = document.createElement('button');
        btn.type = 'button'; btn.className = 'opt';
        btn.style.cssText = 'padding:6px 12px;font-size:12.5px';
        btn.textContent = pk[0];
        btn.onclick = function(){
          tg.input.value = pk[1];
          if ((jn.input.value||'') === '') jn.input.value = pk[1];
          recalc();
        };
        wrap.appendChild(btn);
      });
      b.appendChild(wrap);

      if (TEAM && TEAM.found) {
        var note = document.createElement('div');
        note.style.cssText = 'font-size:11.5px;color:var(--mut);margin-top:6px;line-height:1.6';
        note.textContent = '편성표: ' + TEAM.summary;
        b.appendChild(note);
      }
    })();

    function recalc(){
      var a=parseInt(tg.input.value||'0',10)||0, c=parseInt(jn.input.value||'0',10)||0;
      abi.value = Math.max(0, a-c);
    }
    tg.input.addEventListener('input',recalc); jn.input.addEventListener('input',recalc);
    tg.onstep=recalc; jn.onstep=recalc; recalc();
    mkGo(b, function(){
      var a=(tg.input.value||'').trim();
      if(a===''){ tg.input.focus(); return; }
      var p={}; p[pfx+'_target']=a; p[pfx+'_join']=(jn.input.value||'0');
      p[pfx+'_absent']=abi.value;
      put(p, '대상 '+a+'명 · 참석 '+p[pfx+'_join']+'명 · 미참석 '+abi.value+'명', null);
    });
    addReview(b, s);
    return;
  }

  if (s.type === 'pick'){
    var w4=document.createElement('div'); w4.className='opts';
    /* 편성표를 만들어 두었으면, 그 편성을 근거로 한 문구를 맨 앞에 올립니다.
       실제 편성과 맞아떨어져 예시보다 정확합니다. */
    var samples = (s.samples||[]).slice();
    if (TEAM && TEAM.found) {
      var extra = '';
      if (s.id === 'fire_result')
        extra = '자위소방대 ' + TEAM.total + '명이 편성표대로 각자 맡은 임무를 수행함';
      else if (s.id === 'edu_content')
        extra = '자위소방대 편성표에 따른 조별 임무와 초기대응 절차 교육';
      if (extra && samples.indexOf(extra) === -1) samples.unshift(extra);
    }
    samples.forEach(function(sm){
      var btn=document.createElement('button'); btn.className='opt opt--long'; btn.type='button'; btn.textContent=sm;
      btn.onclick=function(){ var p={}; p[s.id]=sm; put(p, sm, null); };
      w4.appendChild(btn);
    });
    b.appendChild(w4);
    addFree(b, s, '직접 쓸게요', true);
    addSkip(b, s);
    addReview(b, s);
    return;
  }

  if (s.type === 'photo'){
    var wrap=document.createElement('div');
    wrap.style.cssText='display:flex;gap:14px;flex-wrap:wrap';
    [['fire1','소방훈련 사진'],['edu1','소방교육 사진']].forEach(function(pp){
      var slot=document.createElement('div'); slot.className='pslot';
      var lb=document.createElement('div'); lb.style.cssText='font-size:12.5px;font-weight:700;color:var(--mut2)';
      lb.textContent=pp[1];
      var drop=document.createElement('label'); drop.className='pdrop';
      var have=(SAVED.photos||{})[pp[0]];
      drop.innerHTML = have ? '<img src="train_photo.php?f='+encodeURIComponent(have)+'" alt="">'
                            : '<span class="pdrop__ph">📷<br>눌러서 사진 넣기</span>';
      var fi=document.createElement('input'); fi.type='file'; fi.accept='image/*'; fi.style.display='none';
      drop.appendChild(fi);
      fi.addEventListener('change', function(){
        var file=fi.files&&fi.files[0]; if(!file) return;
        drop.innerHTML='<span class="pdrop__ph">올리는 중…</span>'; drop.appendChild(fi);
        var fd=new FormData();
        fd.append('act','save_photo'); fd.append('csrf',CSRF);
        fd.append('slot',pp[0]); fd.append('photo',file);
        fetch(location.pathname+location.search,{method:'POST',body:fd,credentials:'same-origin'})
          .then(function(r){return r.json();})
          .then(function(j){
            if(j&&j.ok){
              drop.innerHTML='<img src="'+j.url+'" alt="">'; drop.appendChild(fi);
              SAVED.photos = SAVED.photos||{}; SAVED.photos[pp[0]]='ok'; bump();
            } else {
              drop.innerHTML='<span class="pdrop__ph">'+esc((j&&j.error)||'실패')+'<br>다시 눌러주세요</span>';
              drop.appendChild(fi);
            }
          })
          .catch(function(){
            drop.innerHTML='<span class="pdrop__ph">연결 실패<br>다시 눌러주세요</span>'; drop.appendChild(fi);
          });
      });
      slot.appendChild(lb); slot.appendChild(drop);
      wrap.appendChild(slot);
    });
    b.appendChild(wrap);
    mkGo(b, function(){ clearBox(); me('사진 넣기 완료'); step++; bump(); go(); }, '다 넣었어요');
    addSkip(b, s, '사진 없이 진행');
    addReview(b, s);
    return;
  }
}

/* ── 잘 모르겠을 때 yeohub에 물어보기 ── */
function requestReview(s, btn){
  if (btn.disabled) return;
  btn.disabled = true;
  btn.textContent = '요청 중…';
  var label = (s.q || '').replace(/\?$/, '');
  var fd = new FormData();
  fd.append('kind', 'review');
  fd.append('text', '소방훈련·교육 기록부: ' + label);
  fetch('/assist_log.php', { method:'POST', body:fd, credentials:'same-origin' })
    .then(function(r){ return r.json(); })
    .then(function(j){
      if (!j || !j.ok) throw new Error('request');
      clearBox();
      me('잘 모르겠어요 · yeohub에 요청');
      bot(md('**yeohub에 검토를 요청했습니다.**\n' +
        '관리자가 확인하고 답을 드립니다. 이 항목은 비워둔 채로 다음으로 넘어가겠습니다.'));
      step++; setTimeout(go, 520);
    })
    .catch(function(){
      btn.disabled = false;
      btn.textContent = '🙋 잘 모르겠어요 · yeohub에 요청하기';
      alert('요청을 접수하지 못했습니다. 잠시 후 다시 시도해 주세요.');
    });
}

/* 질문마다 붙이는 요청 버튼 */
function addReview(b, s){
  if (s.noreview) return;
  var row = b.querySelector('.subrow');
  if (!row){ row = document.createElement('div'); row.className='subrow'; b.appendChild(row); }
  var rq = document.createElement('button');
  rq.className = 'btn btn--sm';
  rq.type = 'button';
  rq.style.cssText = 'border-color:#fdba74;color:#b45309';
  rq.textContent = '🙋 잘 모르겠어요 · yeohub에 요청하기';
  rq.onclick = function(){ requestReview(s, rq); };
  row.appendChild(rq);
}

/* ── 도구 ── */
function mkNum(label, val){
  var box=document.createElement('div'); box.className='nbox';
  box.innerHTML='<span class="nbox__l">'+esc(label)+'</span>';
  var ctl=document.createElement('div'); ctl.className='nctl';
  var minus=document.createElement('button'); minus.type='button'; minus.className='nbtn'; minus.textContent='−';
  var inp=document.createElement('input'); inp.type='number'; inp.min='0'; inp.className='ncell'; inp.value=val||'';
  var plus=document.createElement('button'); plus.type='button'; plus.className='nbtn'; plus.textContent='＋';
  var api={el:box,input:inp,onstep:null};
  minus.onclick=function(){ inp.value=Math.max(0,(parseInt(inp.value||'0',10)||0)-1); if(api.onstep)api.onstep(); };
  plus.onclick =function(){ inp.value=(parseInt(inp.value||'0',10)||0)+1; if(api.onstep)api.onstep(); };
  ctl.appendChild(minus); ctl.appendChild(inp); ctl.appendChild(plus);
  box.appendChild(ctl);
  return api;
}
function mkGo(b, fn, label){
  var row=b.querySelector('.subrow');
  if(!row){ row=document.createElement('div'); row.className='subrow'; b.appendChild(row); }
  var go=document.createElement('button'); go.className='btn btn--pri'; go.type='button';
  go.textContent=label||'다음'; go.onclick=fn;
  row.appendChild(go); return go;
}
function addFree(b, s, label, multi){
  var row=b.querySelector('.subrow');
  if(!row){ row=document.createElement('div'); row.className='subrow'; b.appendChild(row); }
  var t=document.createElement('button'); t.className='btn btn--sm'; t.type='button'; t.textContent='✏️ '+label;
  t.onclick=function(){
    clearBox(); me(label);
    var nb=box();
    var r=document.createElement('div'); r.className='inrow';
    var inp = multi ? document.createElement('textarea') : document.createElement('input');
    if(!multi) inp.type='text';
    inp.placeholder='직접 적어주세요';
    r.appendChild(inp); nb.appendChild(r);
    mkGo(nb, function(){
      var v=(inp.value||'').trim(); if(v===''){ inp.focus(); return; }
      var p={}; p[s.id]=v; put(p, v, null);
    });
    inp.focus();
  };
  row.appendChild(t);
}
function addSkip(b, s, label){
  if(!s.skip) return;
  var row=b.querySelector('.subrow');
  if(!row){ row=document.createElement('div'); row.className='subrow'; b.appendChild(row); }
  var sk=document.createElement('button'); sk.className='btn btn--sm'; sk.type='button';
  sk.textContent=label||'건너뛰기';
  sk.onclick=function(){ clearBox(); me('(건너뜀)'); step++; go(); };
  row.appendChild(sk);
}

/* ── 마무리 ── */
function finish(){
  clearBox();
  typing(function(){
    bot(md('다 채웠습니다. 아래 내용으로 기록부가 만들어집니다.\n\n' +
      '표에서 더 다듬으실 수 있고, 바로 인쇄하거나 PDF로 저장하셔도 됩니다.'));
    var d=document.createElement('div'); d.className='done';
    var ft=(SAVED.fire_types||[]).join('·');
    var rows=[
      ['대상명', SAVED.t_name], ['등급', SAVED.t_grade],
      ['훈련 일시', SAVED.fire_date], ['훈련 장소', SAVED.fire_place],
      ['훈련 구분', SAVED.fire_kind], ['훈련교관', SAVED.fire_teacher],
      ['훈련 참석', SAVED.fire_target ? ('대상 '+SAVED.fire_target+' · 참석 '+(SAVED.fire_join||0)+' · 미참석 '+(SAVED.fire_absent||0)) : ''],
      ['훈련 종류', ft]
    ];
    if (SAVED.edu_skip !== '1'){
      rows.push(['교육 일시', SAVED.edu_date]);
      rows.push(['교육 참석', SAVED.edu_target ? ('대상 '+SAVED.edu_target+' · 참석 '+(SAVED.edu_join||0)) : '']);
    }
    var html='<h2>소방훈련·교육 실시 결과</h2>';
    rows.forEach(function(r){
      var v=String(r[1]||'').trim();
      html+='<div class="sum"><span class="sum__k">'+esc(r[0])+'</span>'+
            '<span class="sum__v'+(v?'':' none')+'">'+esc(v||'비어 있음')+'</span></div>';
    });
    html+='<div class="doneRow">'+
      '<a class="btn btn--pri" href="/train_print.php?id=<?=h(rawurlencode($id))?>">🖨 인쇄 · PDF 저장</a>'+
      '<a class="btn" href="/train_edit.php?id=<?=h(rawurlencode($id))?>">표에서 다듬기</a>'+
      '<a class="btn" href="/train.php">목록으로</a></div>';
    d.innerHTML=html;
    chat.appendChild(d); down();
  });
}

start();
</script>
<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>
</html>
