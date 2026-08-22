<?php
/* =============================================================
   jawi_chat.php — 소방훈련·교육 실시 결과 기록부 문답형 작성
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

require_once __DIR__ . '/jawi_db.php';
require_once __DIR__ . '/building_info.php';

/* ── 기록 준비 ────────────────────────────────────────────
   주소에 id 가 없을 때 무조건 새 기록을 만들면, 화면에 들어올 때마다
   빈 기록이 쌓입니다. 그래서 먼저 이어서 쓸 기록이 있는지 물어봅니다. */
$id  = (string)($_GET['id'] ?? '');
$rec = $id !== '' ? jw_load($id) : null;

/* 새로 시작을 고른 경우에만 만듭니다 */
if (!$rec && ($_GET['new'] ?? '') === '1') {
  $id = jw_create();
  header('Location: /jawi_chat.php?id=' . urlencode($id));
  exit;
}

if (!$rec) {
  $picks = jw_list();
  if (!$picks) {                       // 첫 기록이면 물어볼 것 없이 바로 시작
    $id = jw_create();
    header('Location: /jawi_chat.php?id=' . urlencode($id));
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
  <title>훈련·교육 기록부 문답 — yeohub</title>
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
      <a class="btn" href="/jawi.php?stay=1">← 목록</a>
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
        $tdate = trim((string)($p['edu_date'] ?? ''));
        $upd   = substr((string)($p['updated_at'] ?? ''), 0, 16);
        $full  = function_exists('tr_is_complete') ? jw_is_complete($pid) : ($tdate !== '');
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
          <a class="btn btn--pri" href="/jawi_chat.php?id=<?=h(rawurlencode($pid))?>">이어서 쓰기 →</a>
          <a class="btn" href="/jawi_edit.php?id=<?=h(rawurlencode($pid))?>">표로</a>
        </div>
      <?php endforeach; ?>
      <?php if (count($picks) > 8): ?>
        <div class="row"><div class="row__m row__d">…외 <?=count($picks) - 8?>건은 목록에서 확인하세요.</div>
          <a class="btn" href="/jawi.php?stay=1">목록 보기</a></div>
      <?php endif; ?>
    </div>

    <div class="newbox">
      <div class="newbox__l"><b>새로 실시한 훈련이라면</b><br>
        새 기록을 만들어 처음부터 작성합니다.</div>
      <a class="btn btn--pri" href="/jawi_chat.php?new=1">+ 새 기록 시작하기</a>
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
  if (!hash_equals(jw_csrf(), (string)($_POST['csrf'] ?? ''))) {
    echo json_encode(['ok'=>false,'error'=>'세션이 만료되었습니다. 새로고침 후 다시 시도해 주세요.'], JSON_UNESCAPED_UNICODE); exit;
  }
  $patch = json_decode((string)($_POST['patch'] ?? '{}'), true);
  if (!is_array($patch)) $patch = [];

  $data = $rec['data'] ?? [];
  $allow = ['site_name','rep','address','tel','grade','write_date','writer',
            'wd_day','wd_night','hd_day','hd_night','mgrs',
            'jawi_total','jawi_chief','jawi_chief_tel','jawi_vice','jawi_call','jawi_fire',
            'jawi_guide','jawi_emer','jawi_join','jawi_absent',
            'init_org','init_total','init_join','init_absent',
            'edu_date','edu_place','edu_content','edu_fix','edu_action','attend'];
  foreach ($patch as $k => $v) {
    if (!in_array($k, $allow, true)) continue;
    if ($k === 'mgrs' || $k === 'attend') { $data[$k] = is_array($v) ? $v : []; }
    else { $data[$k] = is_scalar($v) ? (string)$v : ''; }
  }
  $ok = jw_save($id, $data);
  echo json_encode(['ok'=>$ok, 'error'=>$ok?'':'저장하지 못했습니다.'], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ── 처음부터 다시 ────────────────────────────────────────
   지금까지 답한 훈련·교육 내용만 비웁니다.
   대상물 정보(대상명·등급 등)는 기본정보에서 다시 채워지므로 그대로 둡니다. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'reset') {
  if (!hash_equals(jw_csrf(), (string)($_POST['csrf'] ?? ''))) {
    http_response_code(403); exit('잘못된 요청입니다.');
  }
  $keepKeys = ['site_name','address','grade','rep','tel','wd_day','wd_night','hd_day','hd_night','mgrs'];
  $old  = $rec['data'] ?? [];
  $fresh = [];
  foreach ($keepKeys as $k) { if (isset($old[$k])) $fresh[$k] = $old[$k]; }

  jw_save($id, $fresh);
  header('Location: /jawi_chat.php?id=' . urlencode($id) . '&reset=1');
  exit;
}
/* ── 화면 ─────────────────────────────────────────────── */
$d    = $rec['data'] ?? [];
$bi   = bi_load();
$mgrs = is_array($bi['mgrs'] ?? null) ? $bi['mgrs'] : [];
$nick = $_SESSION['nickname'] ?? '사용자';

/* 대상물 정보는 기본정보에서 자동으로 채운다 */
$autoBase = [
  'site_name' => (string)($bi['name'] ?? ''),
  'address'   => (string)($bi['address'] ?? ''),
  'grade'     => (string)($bi['grade'] ?? ''),
  'rep'       => (string)($bi['rep'] ?? ''),
  'tel'       => (string)($bi['tel'] ?? ''),
  'wd_day'    => (string)($bi['wd_day'] ?? ''),
  'wd_night'  => (string)($bi['wd_night'] ?? ''),
  'hd_day'    => (string)($bi['hd_day'] ?? ''),
  'hd_night'  => (string)($bi['hd_night'] ?? ''),
  'mgrs'      => $mgrs,
];
$mgrNames = [];
$mgrTels = [];
foreach ($mgrs as $m) {
  $n = trim((string)($m['name'] ?? ''));
  if ($n === '') continue;
  $mgrNames[] = $n;
  $t = trim((string)($m['tel'] ?? ''));
  if ($t !== '') $mgrTels[$n] = $t;
}
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>자위소방대 교육·훈련 기록부 문답 작성 — 소방계획서.com</title>
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
        <input type="hidden" name="csrf" value="<?=h(jw_csrf())?>">
        <button class="btn" type="submit">↺ 처음부터 다시</button>
      </form>
      <a class="btn" href="/jawi_edit.php?id=<?=h(rawurlencode($id))?>">표로 작성</a>
      <a class="btn" href="/jawi.php?stay=1">← 목록</a>
    </div>
  </div>
</nav>

<div class="prog">
  <div class="prog__in">
    <div class="prog__row">
      <span>자위소방대 교육·훈련 기록부</span>
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
var CSRF = <?=json_encode(jw_csrf())?>;
var SAVED = <?=json_encode($d, JSON_UNESCAPED_UNICODE)?>;
var AUTO  = <?=json_encode($autoBase, JSON_UNESCAPED_UNICODE)?>;
var MGRS  = <?=json_encode($mgrNames, JSON_UNESCAPED_UNICODE)?>;
var MGR_TEL = <?=json_encode($mgrTels, JSON_UNESCAPED_UNICODE)?>;
var LEGACY = <?=json_encode(jw_legacy_members(), JSON_UNESCAPED_UNICODE)?>;
var TEAM   = <?=json_encode(jw_legacy_summary(), JSON_UNESCAPED_UNICODE)?>;
var NICK  = <?=json_encode($nick, JSON_UNESCAPED_UNICODE)?>;
var BNAME = <?=json_encode((string)($bi['name'] ?? ''), JSON_UNESCAPED_UNICODE)?>;

/* 자위소방대 조 — 서식의 칸 이름과 짝지어 둡니다 */
var TEAM_FIELDS = [['jawi_vice','부대장'],['jawi_call','통보연락'],['jawi_fire','초기소화'],
                   ['jawi_guide','피난유도'],['jawi_emer','비상연락']];

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
  { id:'edu_date', q:'교육·훈련을 언제 하셨나요?', type:'datetime',
    hint:'날짜 칸을 누르면 달력이, 시간 칸을 누르면 시계가 나옵니다.' },

  { id:'edu_place', q:'어디서 하셨나요?', type:'choice+',
    options:['2층 회의실','지상 1층 로비','방재실','강당','옥외 주차장'] },

  { id:'jawi_chief', q:'자위소방대장은 누구신가요?', type:'mgr', telField:'jawi_chief_tel',
    hint:'보통 소방안전관리자나 관리책임자가 맡습니다. 고르시면 연락처도 같이 채워집니다.' },

  { id:'jawi_chief_tel', q:'대장 연락처를 알려주세요.', type:'text',
    ph:'010-0000-0000', skip:true },   // 위에서 관리자를 고르면 자동으로 채워져 건너뜁니다

  { id:'__jawicount', q:'자위소방대 인원을 알려주세요.', type:'count', prefix:'jawi',
    hint:'미참석 인원은 자동으로 계산됩니다.' },

  { id:'__jawiteam', q:'자위소방대 각 조에 몇 명씩인가요?', type:'team',
    hint:'모르시면 건너뛰셔도 됩니다. 나중에 표에서 채울 수 있습니다.', skip:true },

  { id:'init_org', q:'초기대응체계는 어떻게 구성되어 있나요?', type:'pick',
    samples:['방재실 근무자 중심으로 상시 편성',
             '층별 담당자를 지정하여 편성',
             '주간은 관리사무소, 야간은 방재실 근무자로 편성'] },

  { id:'__initcount', q:'초기대응체계 인원을 알려주세요.', type:'count', prefix:'init', skip:true },

  { id:'edu_content', q:'어떤 내용으로 교육·훈련하셨나요?', type:'pick',
    samples:['자위소방대 편성표와 각자 맡은 임무 확인',
             '화재 발생 시 통보·초기소화·피난유도 절차 숙지',
             '소화기와 옥내소화전 사용법 실습',
             '초기대응체계 가동 절차 및 상황 전파 훈련'] },

  { id:'edu_fix', q:'교육·훈련하면서 보완할 점이 있었나요?', type:'pick', skip:true,
    samples:['일부 대원이 자신의 임무를 정확히 알지 못함',
             '야간 근무자가 참석하지 못해 별도 교육이 필요함',
             '비상연락망 일부가 최신 상태가 아님',
             '특이사항 없음'] },

  { id:'edu_action', q:'그에 대해 어떻게 조치하시겠어요?', type:'pick', skip:true,
    samples:['편성표를 각 층 게시판에 부착하고 임무를 재안내',
             '야간 근무자를 위한 별도 회차를 마련하여 실시',
             '비상연락망을 최신 상태로 갱신',
             '다음 교육 시 동일 내용으로 재확인'] },

  { id:'__attend', q:'참석자 명단을 넣어두시겠어요?', type:'attend', skip:true,
    hint:'서식 뒤쪽 참석확인 칸에 들어갑니다. 나중에 표에서 더 넣으셔도 됩니다.' }
];

/* ── 진행률 ── */
function visible(){
  return STEPS.filter(function(s){
    return true;
  });
}
function bump(){
  var vs = visible(), n = 0;
  vs.forEach(function(s){
    if (s.type === 'count') { if ((SAVED[s.prefix+'_join']||'') !== '') n++; return; }
    if (s.type === 'team')  { if (TEAM_FIELDS.some(function(t){ return (SAVED[t[0]]||'') !== ''; })) n++; return; }
    if (s.id === '__attend') { if ((SAVED.attend||[]).length) n++; return; }
    if (s.type === 'multi' || s.type === 'multi+') { if ((SAVED[s.id]||[]).length) n++; return; }
    if ((SAVED[s.id]||'') !== '') n++;
  });
  var pct = vs.length ? Math.round(n/vs.length*100) : 0;
  document.getElementById('pPct').textContent = pct + '%';
  document.getElementById('pNum').textContent = n + '/' + vs.length;
  document.getElementById('pBar').style.width = pct + '%';
}
function answered(s){
  /* 총원만 있고 참석 인원이 없으면 아직 답한 것이 아닙니다.
     (편성표에서 총원은 가져오지만 그날 몇 명 왔는지는 알 수 없습니다) */
  if (s.type === 'count') return (SAVED[s.prefix+'_join']||'') !== '';
  /* 조별 인원은 다섯 칸 중 하나라도 채워져 있으면 답한 것으로 봅니다 */
  if (s.type === 'team') {
    return TEAM_FIELDS.some(function(t){ return (SAVED[t[0]]||'') !== ''; });
  }
  if (s.id === '__attend') return (SAVED.attend||[]).length > 0;
  if (s.type === 'multi' || s.type === 'multi+') return (SAVED[s.id]||[]).length > 0;
  return (SAVED[s.id]||'') !== '';
}

/* ── 시작 ── */
function start(){
  /* 대상물 정보는 기본정보에서 자동으로 채워 넣는다 */
  var need = {};
  ['site_name','address','grade','rep','tel','wd_day','wd_night','hd_day','hd_night'].forEach(function(k){
    if (!(SAVED[k]||'').trim() && (AUTO[k]||'').trim()) need[k] = AUTO[k];
  });
  if (!(SAVED.mgrs||[]).length && (AUTO.mgrs||[]).length) need.mgrs = AUTO.mgrs;

  var msg = '안녕하세요' + (NICK && NICK!=='사용자' ? ', ' + NICK + '님' : '') +
    '. 자위소방대 및 초기대응체계 교육·훈련 기록부를 함께 채워보겠습니다.\n\n';
  if (Object.keys(need).length){
    msg += '**' + (BNAME || '건물') + '**의 대상물 정보는 기본정보에서 가져와 이미 채웠습니다. ' +
           '훈련 내용만 답해 주시면 됩니다.';
    save(need, function(){ for(var k in need) SAVED[k]=need[k]; bump(); });
  } else {
    msg += '훈련 내용을 하나씩 여쭤보겠습니다.';
  }
  bot(md(msg));

  /* 편성표를 만들어 두셨으면 그것부터 가져올지 물어봅니다.
     대장·조별 인원·참석자 명단이 한 번에 채워집니다. */
  var already = (SAVED.jawi_total||'') !== '' || (SAVED.jawi_chief||'') !== '';
  if (TEAM && TEAM.found && !already) {
    typing(function(){ askTeamImport(); });
    return;
  }
  bump(); go();
}

/* 편성표에서 한 번에 가져오기 */
function askTeamImport(){
  var initLine = (TEAM.init_total > 0)
    ? '\n\n**초기대응체계** ' + TEAM.init_total + '명 — ' + TEAM.init_desc
    : '';
  bot(md('**자위소방대 편성표를 만들어 두셨네요.**\n\n' +
    TEAM.summary + initLine + '\n\n' +
    '이대로 가져오면 **대장·조별 인원·총원·초기대응체계·참석자 명단**이 한 번에 채워집니다. ' +
    '가져온 뒤에도 하나씩 고칠 수 있습니다.'));

  var b = box();
  var w = document.createElement('div'); w.className='opts';

  var yes = document.createElement('button');
  yes.className='opt'; yes.type='button';
  yes.style.cssText='background:var(--brand);border-color:var(--brand);color:#fff';
  yes.textContent = '📋 편성표에서 ' + TEAM.total + '명 가져오기';
  yes.onclick = function(){
    clearBox();
    me('편성표에서 가져오기');
    var patch = {};
    for (var k in (TEAM.fields||{})) patch[k] = TEAM.fields[k];
    patch.attend = TEAM.attend || [];
    for (var k2 in patch) SAVED[k2] = patch[k2];
    save(patch, function(){
      bump();
      bot(md('가져왔습니다. 채워진 칸은 건너뛰고 **남은 것만** 여쭤보겠습니다.'));
      setTimeout(go, 420);
    });
  };
  w.appendChild(yes);

  var no = document.createElement('button');
  no.className='opt'; no.type='button'; no.textContent = '직접 입력할게요';
  no.onclick = function(){ clearBox(); me('직접 입력할게요'); bump(); go(); };
  w.appendChild(no);

  b.appendChild(w);
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
    s.options.forEach(function(o){
      var btn=document.createElement('button'); btn.className='opt'; btn.type='button'; btn.textContent=o;
      btn.onclick=function(){
        var val = (s.map && s.map[o]!==undefined) ? s.map[o] : o;
        var p={}; p[s.id]=val;
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
    (MGRS.length?MGRS:[]).forEach(function(nm){
      var btn=document.createElement('button'); btn.className='opt'; btn.type='button';
      /* 연락처를 이미 아는 분이면 버튼에 같이 보여줍니다 */
      var tel = (MGR_TEL && MGR_TEL[nm]) ? MGR_TEL[nm] : '';
      btn.textContent = nm + (tel ? ' (' + tel + ')' : '');
      btn.onclick=function(){
        var p={}; p[s.id]=nm;
        /* 소방안전관리자를 고르면 연락처도 같이 채워 다시 묻지 않습니다 */
        if (s.telField && tel) p[s.telField] = tel;
        put(p, nm + (tel ? ' · ' + tel : ''), null);
      };
      w2.appendChild(btn);
    });
    b.appendChild(w2);
    addFree(b, s, MGRS.length ? '다른 분이에요' : '이름 입력');
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
    var tg=mkNum('총원', SAVED[pfx+'_total']||'');
    var jn=mkNum('참석',     SAVED[pfx+'_join']||'');
    var ab=document.createElement('div'); ab.className='nbox';
    ab.innerHTML='<span class="nbox__l">미참석</span>';
    var abv=document.createElement('div'); abv.className='nctl';
    var abi=document.createElement('input'); abi.className='ncell'; abi.readOnly=true; abi.value='0';
    abv.appendChild(abi); abv.appendChild(Object.assign(document.createElement('span'),{className:'nauto',textContent:'자동'}));
    ab.appendChild(abv);
    row2.appendChild(tg.el); row2.appendChild(jn.el); row2.appendChild(ab);
    b.appendChild(row2);

    /* 편성표에서 가져온 총원이면 그 사실을 알려줍니다 */
    if ((SAVED[pfx+'_total']||'') !== '' && TEAM && TEAM.found) {
      var note = document.createElement('div');
      note.style.cssText = 'font-size:12.5px;color:var(--mut2);margin-top:8px;line-height:1.6';
      var src = (pfx === 'init' && TEAM.init_desc)
        ? '편성표의 ' + esc(TEAM.init_desc) + ' 에서 가져왔습니다.'
        : '편성표에서 가져왔습니다.';
      note.innerHTML = '총원 <b>' + esc(SAVED[pfx+'_total']) + '명</b>은 ' + src +
                       ' 그날 <b>몇 분이 오셨는지</b>만 넣어주세요.';
      b.appendChild(note);
    }
    function recalc(){
      var a=parseInt(tg.input.value||'0',10)||0, c=parseInt(jn.input.value||'0',10)||0;
      abi.value = Math.max(0, a-c);
    }
    tg.input.addEventListener('input',recalc); jn.input.addEventListener('input',recalc);
    tg.onstep=recalc; jn.onstep=recalc; recalc();
    mkGo(b, function(){
      var a=(tg.input.value||'').trim();
      if(a===''){ tg.input.focus(); return; }
      var p={}; p[pfx+'_total']=a; p[pfx+'_join']=(jn.input.value||'0');
      p[pfx+'_absent']=abi.value;
      put(p, '총원 '+a+'명 · 참석 '+p[pfx+'_join']+'명 · 미참석 '+abi.value+'명', null);
    });
    addReview(b, s);
    return;
  }

  if (s.type === 'pick'){
    var w4=document.createElement('div'); w4.className='opts';
    /* 편성표로 만들 수 있는 문구가 있으면 맨 앞에 놓습니다.
       실제 편성 그대로라 예시보다 정확합니다. */
    var samples = (s.samples||[]).slice();
    if (s.id === 'init_org' && TEAM && TEAM.init_desc) {
      var fromTeam = TEAM.init_desc.split(' · ').join(', ') + '으로 편성';
      if (samples.indexOf(fromTeam) === -1) samples.unshift(fromTeam);
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

  /* 조별 인원 — 부대장·통보연락·초기소화·피난유도·비상연락 */
  if (s.type === 'team'){
    var teams = TEAM_FIELDS;
    var row = document.createElement('div'); row.className='nrow';
    var boxes = {};
    teams.forEach(function(t){
      var api = mkNum(t[1], SAVED[t[0]]||'');
      boxes[t[0]] = api;
      row.appendChild(api.el);
    });
    b.appendChild(row);

    /* 편성표에서 가져왔으면 그대로 두고 넘어가도 된다고 알려줍니다 */
    var filled = teams.filter(function(t){ return (SAVED[t[0]]||'') !== ''; });
    if (filled.length && TEAM && TEAM.found) {
      var note2 = document.createElement('div');
      note2.style.cssText = 'font-size:12.5px;color:var(--mut2);margin-top:8px;line-height:1.6';
      note2.innerHTML = '편성표에서 가져온 인원입니다. 맞으면 <b>다음</b>을 누르시고, ' +
                        '다르면 고쳐주세요.';
      b.appendChild(note2);
    }

    mkGo(b, function(){
      var p = {}, txt = [];
      teams.forEach(function(t){
        var val = (boxes[t[0]].input.value||'').trim();
        p[t[0]] = val;
        if (val !== '' && val !== '0') txt.push(t[1] + ' ' + val + '명');
      });
      put(p, txt.length ? txt.join(' · ') : '(입력 안 함)', null);
    });
    addSkip(b, s);
    addReview(b, s);
    return;
  }

  /* 한 줄 입력 (글자·숫자) */
  if (s.type === 'text' || s.type === 'number'){
    var r = document.createElement('div'); r.className='inrow';
    var inp = document.createElement('input');
    inp.type = (s.type === 'number') ? 'number' : 'text';
    if (s.type === 'number'){ inp.min='0'; inp.inputMode='numeric'; }
    if (s.ph) inp.placeholder = s.ph;
    inp.value = SAVED[s.id] || '';
    r.appendChild(inp);
    b.appendChild(r);
    var go = mkGo(b, function(){
      var val = (inp.value||'').trim();
      if (val === ''){ inp.focus(); return; }
      var p = {}; p[s.id] = val;
      put(p, val, null);
    });
    inp.addEventListener('keydown', function(e){
      if (e.key === 'Enter'){ e.preventDefault(); go.click(); }
    });
    addSkip(b, s);
    addReview(b, s);
    setTimeout(function(){ inp.focus(); }, 60);
    return;
  }

  /* 참석자 명단 — 직책·성명을 줄 단위로 */
  if (s.type === 'attend'){
    var cur = (SAVED.attend||[]).filter(function(x){ return (x.name||'').trim() !== ''; });
    var wrap = document.createElement('div');
    wrap.style.cssText = 'display:flex;flex-direction:column;gap:7px';

    var help = document.createElement('div');
    help.style.cssText = 'font-size:12.5px;color:var(--mut2);line-height:1.6';
    help.innerHTML = '한 줄에 한 명씩, <b>직책 성명</b> 순으로 적어주세요.<br>' +
                     '예) 지휘조 김철수 / 초기소화 이영희';
    wrap.appendChild(help);

    var ta = document.createElement('textarea');
    ta.rows = 6;
    ta.placeholder = '지휘조 김철수\n비상연락조 이영희\n초기소화조 박민수';
    ta.style.cssText = 'width:100%;padding:11px 14px;border:1px solid var(--bd2);border-radius:11px;' +
      'font-size:14.5px;font-family:inherit;line-height:1.7;resize:vertical';
    ta.value = cur.map(function(x){
      return ((x.role||'') + ' ' + (x.name||'')).trim();
    }).join('\n');
    wrap.appendChild(ta);
    b.appendChild(wrap);

    /* 편성표에 저장해 둔 대원이 있으면 눌러서 불러옵니다 */
    if (LEGACY.length) {
      var row2 = document.createElement('div'); row2.className='subrow';
      var lb = document.createElement('button');
      lb.className='btn btn--sm'; lb.type='button';
      lb.textContent = '📋 편성표에서 ' + LEGACY.length + '명 불러오기';
      lb.onclick = function(){
        ta.value = LEGACY.map(function(x){ return ((x.role||'') + ' ' + (x.name||'')).trim(); }).join('\n');
        ta.focus();
      };
      row2.appendChild(lb);
      b.appendChild(row2);
    }

    mkGo(b, function(){
      var list = [];
      (ta.value||'').split('\n').forEach(function(line){
        var t = line.trim();
        if (t === '') return;
        var sp = t.indexOf(' ');
        if (sp > 0) list.push({ role: t.slice(0, sp), name: t.slice(sp+1).trim(), ok: '' });
        else        list.push({ role: '', name: t, ok: '' });
      });
      if (list.length > 50) list = list.slice(0, 50);
      put({ attend: list }, list.length ? (list.length + '명 입력') : '(입력 안 함)', null);
    }, '이대로 넣기');
    addSkip(b, s, '명단 없이 진행');
    addReview(b, s);
    return;
  }
}

/* ── 잘 모르겠을 때 소방계획서.com에 물어보기 ── */
function requestReview(s, btn){
  if (btn.disabled) return;
  btn.disabled = true;
  btn.textContent = '요청 중…';
  var label = (s.q || '').replace(/\?$/, '');
  var fd = new FormData();
  fd.append('kind', 'review');
  fd.append('text', '자위소방대 교육·훈련 기록부: ' + label);
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
    var rows=[
      ['대상명', SAVED.site_name], ['등급', SAVED.grade],
      ['실시 일시', SAVED.edu_date], ['실시 장소', SAVED.edu_place],
      ['자위소방대장', SAVED.jawi_chief],
      ['자위소방대', SAVED.jawi_total ? ('총원 '+SAVED.jawi_total+' · 참석 '+(SAVED.jawi_join||0)+' · 미참석 '+(SAVED.jawi_absent||0)) : ''],
      ['초기대응체계', SAVED.init_total ? ('총원 '+SAVED.init_total+' · 참석 '+(SAVED.init_join||0)) : ''],
      ['주요 내용', SAVED.edu_content],
      ['참석자 명단', (SAVED.attend||[]).length ? ((SAVED.attend||[]).length + '명') : '']
    ];
    var html='<h2>자위소방대 교육·훈련 실시 결과</h2>';
    rows.forEach(function(r){
      var v=String(r[1]||'').trim();
      html+='<div class="sum"><span class="sum__k">'+esc(r[0])+'</span>'+
            '<span class="sum__v'+(v?'':' none')+'">'+esc(v||'비어 있음')+'</span></div>';
    });
    html+='<div class="doneRow">'+
      '<a class="btn btn--pri" href="/jawi_print.php?id=<?=h(rawurlencode($id))?>">🖨 인쇄 · PDF 저장</a>'+
      '<a class="btn" href="/jawi_edit.php?id=<?=h(rawurlencode($id))?>">표에서 다듬기</a>'+
      '<a class="btn" href="/jawi.php?stay=1">목록으로</a></div>';
    d.innerHTML=html;
    chat.appendChild(d); down();
  });
}

start();
</script>
<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>
</html>
