<?php
// work_log_setup_chat.php — 업무수행 기록표 기본값 대화형 설정
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

require_once __DIR__ . '/building_info.php';
require_once __DIR__ . '/user_key.php';

$uidKeyRaw = app_user_key();
if ($uidKeyRaw === '') { die('<meta charset="utf-8">' . h(app_user_key_notice())); }
$uidKey = preg_replace('/[^A-Za-z0-9_]/', '_', (string)$uidKeyRaw);
$BASE = __DIR__ . '/data/worklog/' . $uidKey;
if (!is_dir($BASE)) @mkdir($BASE, 0775, true);
$FIXED_FILE = $BASE . '/building.json';

function wlc_load_json(string $f): array {
  if (!is_file($f)) return [];
  $r = @file_get_contents($f);
  if ($r === false || trim($r) === '') return [];
  $a = json_decode($r, true);
  return is_array($a) ? $a : [];
}
function wlc_save_json(string $f, array $arr): bool {
  if (!is_dir(dirname($f))) @mkdir(dirname($f), 0775, true);
  $tmp = $f . '.tmp';
  if (@file_put_contents($tmp, json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
  return @rename($tmp, $f);
}
function wlc_issue_building_code(): string {
  $counterFile = __DIR__ . '/data/building_counter.json';
  if (!is_dir(dirname($counterFile))) @mkdir(dirname($counterFile), 0775, true);
  $fp = @fopen($counterFile, 'c+');
  if ($fp === false) return 'BLD-' . date('ymdHis');
  flock($fp, LOCK_EX);
  $raw = stream_get_contents($fp);
  $data = json_decode($raw ?: '', true);
  $next = (is_array($data) ? (int)($data['last'] ?? 0) : 0) + 1;
  rewind($fp); ftruncate($fp, 0);
  fwrite($fp, json_encode(['last' => $next], JSON_UNESCAPED_UNICODE));
  fflush($fp); flock($fp, LOCK_UN); fclose($fp);
  return 'BLD-' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}
function wlc_basic_to_fixed(array $fixed): array {
  $bi = bi_load();
  $mgrs = is_array($bi['mgrs'] ?? null) ? $bi['mgrs'] : [];
  $fixed['bcode'] = trim((string)($fixed['bcode'] ?? '')) !== '' ? (string)$fixed['bcode'] : wlc_issue_building_code();
  $fixed['sangho'] = (string)($bi['name'] ?? '');
  $fixed['grade'] = (string)($bi['grade'] ?? '');
  $fixed['address'] = (string)($bi['address'] ?? '');
  $fixed['floor_b'] = (string)($bi['floor_b'] ?? '');
  $fixed['floor_a'] = (string)($bi['floor_a'] ?? '');
  $fixed['area_t'] = (string)($bi['area_t'] ?? '');
  $fixed['area_f'] = (string)($bi['area_f'] ?? '');
  $fixed['dongsu'] = (string)($bi['dongsu'] ?? '');
  $fixed['performer'] = trim((string)($mgrs[0]['name'] ?? '')) !== ''
    ? (string)$mgrs[0]['name']
    : (string)($fixed['performer'] ?? '');
  return $fixed;
}
function wlc_progress(array $fixed): array {
  $labels = [
    'note_sobang' => '소방시설',
    'note_pinan' => '피난방화시설',
    'note_hwagi' => '화기취급감독',
    'note_etc' => '기타사항',
  ];
  $filled = 0; $missing = [];
  foreach ($labels as $k => $label) {
    if (trim((string)($fixed[$k] ?? '')) !== '') $filled++;
    else $missing[] = $label;
  }
  return ['filled'=>$filled, 'total'=>count($labels), 'percent'=>(int)round($filled / count($labels) * 100), 'missing'=>$missing];
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = (string)$_SESSION['csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'save_step') {
  header('Content-Type: application/json; charset=utf-8');
  if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) {
    echo json_encode(['ok'=>false, 'error'=>'세션이 만료되었습니다. 새로고침 후 다시 시도해 주세요.']); exit;
  }
  $patch = json_decode((string)($_POST['patch'] ?? '{}'), true);
  if (!is_array($patch)) $patch = [];
  $fixed = wlc_basic_to_fixed(wlc_load_json($FIXED_FILE));
  foreach (['note_sobang','note_pinan','note_hwagi','note_etc'] as $k) {
    if (array_key_exists($k, $patch)) $fixed[$k] = trim((string)$patch[$k]);
  }
  $ok = wlc_save_json($FIXED_FILE, $fixed);
  $p = wlc_progress($fixed);
  echo json_encode(['ok'=>$ok, 'percent'=>$p['percent'], 'filled'=>$p['filled'], 'total'=>$p['total'],
    'error'=>$ok ? '' : '저장하지 못했습니다. data 폴더 권한을 확인해 주세요.'], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ── 처음부터 다시 — 확인내용 기본값 4개만 비웁니다 ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'reset') {
  if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) {
    http_response_code(403); exit('잘못된 요청입니다.');
  }
  $cur = wlc_basic_to_fixed(wlc_load_json($FIXED_FILE));
  foreach (['note_sobang','note_pinan','note_hwagi','note_etc'] as $k) $cur[$k] = '';
  wlc_save_json($FIXED_FILE, $cur);
  header('Location: /work_log_setup_chat.php?reset=1');
  exit;
}

$fixed = wlc_basic_to_fixed(wlc_load_json($FIXED_FILE));
wlc_save_json($FIXED_FILE, $fixed);
$prog = wlc_progress($fixed);
$biProg = bi_progress();
$biDone = $biProg['filled'] >= $biProg['total'];
$nick = $_SESSION['nickname'] ?? '사용자';
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>업무수행 기록표 설정 — TWORIX</title>
<style>
:root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--bd2:#d4dbe6;--fg:#1a2436;--mut:#7a8699;--mut2:#56627a;--brand:#2563eb;--brand2:#1d4ed8;--accent:#0891b2}
*{box-sizing:border-box;margin:0;padding:0}body{background:var(--bg);color:var(--fg);line-height:1.65;font-family:Inter,system-ui,"Apple SD Gothic Neo","Malgun Gothic",sans-serif}a{text-decoration:none;color:inherit}button{font:inherit;color:inherit;cursor:pointer}
.nav{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.94);backdrop-filter:blur(10px);border-bottom:1px solid var(--bd)}
.nav__in{max-width:760px;margin:0 auto;padding:0 20px;height:56px;display:flex;align-items:center;justify-content:space-between;gap:12px}.brand{font-weight:800;font-size:21px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 15px;border-radius:9px;border:1px solid var(--bd2);background:#fff;font-size:13px;font-weight:600;transition:.15s}.btn:hover{border-color:var(--brand);color:var(--brand2)}.btn--pri{background:var(--brand);border-color:var(--brand);color:#fff}.btn--pri:hover{background:var(--brand2);color:#fff}.btn--sm{padding:6px 12px;font-size:12.5px}
.prog{position:sticky;top:56px;z-index:45;background:#fff;border-bottom:1px solid var(--bd)}.prog__in{max-width:760px;margin:0 auto;padding:11px 20px}.prog__row{display:flex;justify-content:space-between;font-size:12.5px;color:var(--mut2);margin-bottom:6px}.prog__row b{color:var(--brand2)}.bar{height:6px;background:#eef2f7;border-radius:3px;overflow:hidden}.bar i{display:block;height:100%;background:var(--brand);width:0;transition:width .45s cubic-bezier(.2,.7,.3,1)}
.wrap{max-width:760px;margin:0 auto;padding:24px 20px 60px}.msg{display:flex;gap:11px;margin-bottom:15px;animation:pop .28s ease both}@keyframes pop{from{opacity:0;transform:translateY(7px)}to{opacity:1;transform:none}}.msg__av{width:31px;height:31px;border-radius:9px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:15px;background:#eef2ff}.msg__b{background:var(--card);border:1px solid var(--bd);border-radius:4px 14px 14px 14px;padding:14px 17px;max-width:calc(100% - 44px);font-size:14.8px;line-height:1.72}.msg--me{flex-direction:row-reverse}.msg--me .msg__av{background:#e6edfb}.msg--me .msg__b{background:var(--brand);border-color:var(--brand);color:#fff;border-radius:14px 4px 14px 14px}.msg__b b{font-weight:700}.hint{font-size:12.5px;color:var(--mut);margin-top:9px;padding-top:9px;border-top:1px dashed var(--bd)}
.answer{margin:0 0 22px 42px}.opts{display:flex;flex-wrap:wrap;gap:8px}.opt{padding:9px 15px;border:1px solid var(--bd2);border-radius:999px;background:#fff;font-size:13.5px;font-weight:500;transition:.14s}.opt:hover{border-color:var(--brand);color:var(--brand2);background:#f7faff}.inrow{display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap}.inrow input{flex:1;min-width:180px;padding:11px 14px;border:1px solid var(--bd2);border-radius:11px;background:#fff;font-size:14.8px;font-family:inherit}.subrow{display:flex;gap:8px;margin-top:9px;flex-wrap:wrap}
.summary{background:#fff;border:1px solid var(--bd);border-radius:14px;padding:16px 18px;margin-bottom:18px;font-size:13px;color:var(--mut2)}.summary b{color:var(--fg)}.done{background:#fff;border:1px solid var(--bd);border-radius:14px;padding:22px;margin-left:42px}.done h2{font-size:18px;font-weight:800;margin-bottom:12px}.sum{display:flex;justify-content:space-between;gap:14px;padding:8px 0;border-top:1px solid var(--bd);font-size:14px;flex-wrap:wrap}.sum:first-of-type{border-top:0}.sum__k{color:var(--mut2);font-size:13px}.sum__v{font-weight:600;text-align:right}.doneRow{display:flex;gap:9px;flex-wrap:wrap;margin-top:18px}.alert{display:flex;gap:11px;border-radius:12px;padding:14px 16px;font-size:14px;line-height:1.7;margin-bottom:18px;background:#fff7ed;border:1px solid #fed7aa;color:#92400e}.typing{display:inline-flex;gap:4px;align-items:center;padding:3px 0}.typing i{width:6px;height:6px;border-radius:50%;background:var(--mut);display:block;animation:blink 1.2s infinite}.typing i:nth-child(2){animation-delay:.18s}.typing i:nth-child(3){animation-delay:.36s}@keyframes blink{0%,60%,100%{opacity:.28}30%{opacity:1}}
@media(max-width:560px){.answer,.done{margin-left:0}.msg__b{max-width:calc(100% - 42px)}}
</style>
</head>
<body>
<nav class="nav"><div class="nav__in"><a class="brand" href="/index.php">TWORIX</a><div style="display:flex;gap:8px"><form method="post" style="display:inline" onsubmit="return confirm('확인내용 기본값을 모두 지우고 처음부터 다시 시작합니다.\n계속할까요?')"><input type="hidden" name="act" value="reset"><input type="hidden" name="csrf" value="<?=h($CSRF)?>"><button class="btn" type="submit">↺ 처음부터 다시</button></form><a class="btn" href="/work_log.php">← 목록</a><a class="btn" href="/building_manager.php">메인</a></div></div></nav>
<div class="prog"><div class="prog__in"><div class="prog__row"><span>업무수행 기록표 기본값</span><span><b id="pPct"><?=$prog['percent']?>%</b> · <span id="pNum"><?=$prog['filled']?>/<?=$prog['total']?></span></span></div><div class="bar"><i id="pBar" style="width:<?=$prog['percent']?>%"></i></div></div></div>
<main class="wrap">
  <?php if (!$biDone): ?><div class="alert">먼저 건물 기본정보를 마저 입력하면 상호·주소·등급·규모가 업무수행 기록표에 자동으로 들어갑니다. 현재 누락: <?=h(implode(', ', $biProg['missing']))?></div><?php endif; ?>
  <div class="summary">
    기본정보에서 가져온 값:
    <b><?=h($fixed['sangho'] ?: '대상명 미입력')?></b>
    · <?=h($fixed['address'] ?: '주소 미입력')?>
    · <?=h($fixed['grade'] ?: '등급 미입력')?>
    · 수행자 <?=h($fixed['performer'] ?: '미입력')?>
  </div>
  <div id="chat"></div>
</main>
<script>
var CSRF = <?=json_encode($CSRF)?>;
var SAVED = <?=json_encode($fixed, JSON_UNESCAPED_UNICODE)?>;
var NICK = <?=json_encode($nick, JSON_UNESCAPED_UNICODE)?>;
var chat = document.getElementById('chat');
var step = 0;
var STEPS = [
  {field:'note_sobang', label:'소방시설', q:'소방시설 확인내용 기본값은 어떤 문구로 넣을까요?', examples:['수신기 및 제어반 정상 작동 확인','소화기 비치상태 및 압력계 정상 확인','옥내소화전함 구성품 및 사용 가능 여부 확인']},
  {field:'note_pinan', label:'피난방화시설', q:'피난방화시설 확인내용 기본값은 어떤 문구로 넣을까요?', examples:['피난구 유도등 점등 상태 확인','방화문 폐쇄 및 피난통로 적치물 여부 확인','비상구 개방상태 및 피난동선 장애물 확인']},
  {field:'note_hwagi', label:'화기취급감독', q:'화기취급감독 확인내용 기본값은 어떤 문구로 넣을까요?', examples:['화기취급 구역 이상 유무 확인','전열기구 및 콘센트 주변 가연물 방치 여부 확인','용접·절단 작업 등 화기작업 관리상태 확인']},
  {field:'note_etc', label:'기타사항', q:'기타사항 확인내용 기본값은 어떤 문구로 넣을까요?', examples:['소화기 비치 상태 및 표지 확인','방재실 비상연락망 최신 상태 확인','자체점검 지적사항 조치 진행상태 확인']}
];
function esc(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function md(s){return esc(s).replace(/\*\*(.+?)\*\*/g,'<b>$1</b>').replace(/\n/g,'<br>');}
function down(){requestAnimationFrame(function(){window.scrollTo({top:document.body.scrollHeight,behavior:'smooth'});});}
function bot(html,hint){var d=document.createElement('div');d.className='msg';d.innerHTML='<div class="msg__av">📋</div><div class="msg__b">'+html+(hint?'<div class="hint">'+esc(hint)+'</div>':'')+'</div>';chat.appendChild(d);down();return d;}
function me(t){var d=document.createElement('div');d.className='msg msg--me';d.innerHTML='<div class="msg__av">🙂</div><div class="msg__b">'+esc(t)+'</div>';chat.appendChild(d);down();}
function typing(cb){var d=bot('<span class="typing"><i></i><i></i><i></i></span>');setTimeout(function(){d.remove();cb();},300);}
function clearBox(){var a=document.getElementById('ansBox');if(a)a.remove();}
function box(){clearBox();var d=document.createElement('div');d.className='answer';d.id='ansBox';chat.appendChild(d);down();return d;}
function filled(s){return String(SAVED[s.field]||'').trim()!=='';}
function save(patch,done){
  var fd=new FormData();fd.append('act','save_step');fd.append('csrf',CSRF);fd.append('patch',JSON.stringify(patch));
  fetch(location.pathname,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
    if(j&&j.ok){document.getElementById('pPct').textContent=j.percent+'%';document.getElementById('pNum').textContent=j.filled+'/'+j.total;document.getElementById('pBar').style.width=j.percent+'%';done(true);}
    else{bot(md('⚠️ '+((j&&j.error)?j.error:'저장하지 못했습니다.')));done(false);}
  }).catch(function(){bot(md('⚠️ 저장 중 연결이 끊겼습니다. 잠시 후 다시 시도해 주세요.'));done(false);});
}
function start(){
  bot(md('안녕하세요' + (NICK && NICK!=='사용자' ? ', '+NICK+'님' : '') + '.\n업무수행 기록표에서 매월 반복되는 **확인내용 기본값**만 함께 정해볼게요.\n\n상호·주소·등급·규모·수행자는 위 기본정보 값을 그대로 사용합니다.'));
  next();
}
function next(){
  while(step<STEPS.length && filled(STEPS[step])) step++;
  if(step>=STEPS.length){finish();return;}
  typing(function(){ask(STEPS[step]);});
}
function ask(s){
  bot(md(s.q),'아래 예시를 누르면 바로 입력됩니다. 직접 문구를 넣어도 됩니다.');
  var b=box();var opts=document.createElement('div');opts.className='opts';
  s.examples.forEach(function(ex){var btn=document.createElement('button');btn.className='opt';btn.type='button';btn.textContent=ex;btn.onclick=function(){submit(s,ex);};opts.appendChild(btn);});
  b.appendChild(opts);
  var row=document.createElement('div');row.className='inrow';row.style.marginTop='10px';
  var inp=document.createElement('input');inp.type='text';inp.placeholder='직접 입력';row.appendChild(inp);b.appendChild(row);
  var sub=document.createElement('div');sub.className='subrow';
  var go=document.createElement('button');go.className='btn btn--pri';go.type='button';go.textContent='직접 입력한 내용 넣기';go.onclick=function(){var v=(inp.value||'').trim();if(v===''){inp.focus();return;}submit(s,v);};
  var req=document.createElement('button');req.className='btn btn--sm';req.type='button';req.textContent='잘 모르겠어요 · TWORIX에 요청하기';req.onclick=function(){requestReview(s,req);};
  sub.appendChild(go);sub.appendChild(req);b.appendChild(sub);
  inp.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();go.click();}});
}
function submit(s,value){
  clearBox();me(value);var patch={};patch[s.field]=value;SAVED[s.field]=value;
  save(patch,function(){step++;next();});
}
function requestReview(s,btn){
  if(btn.disabled)return;btn.disabled=true;btn.textContent='요청 중...';
  var fd=new FormData();fd.append('kind','review');fd.append('text','업무수행 기록표: '+s.label+' 확인내용 기본값');
  fetch('/assist_log.php',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
    if(!j||!j.ok)throw new Error('request');btn.textContent='TWORIX 요청 완료 ✓';clearBox();me('TWORIX에 검토요청');bot(md('**TWORIX에 검토를 요청했습니다.**\n관리자가 확인할 수 있게 남겨두고, 다음 항목으로 넘어가겠습니다.'));step++;setTimeout(next,520);
  }).catch(function(){btn.disabled=false;btn.textContent='잘 모르겠어요 · TWORIX에 요청하기';alert('요청을 접수하지 못했습니다. 잠시 후 다시 시도해 주세요.');});
}
function finish(){
  clearBox();typing(function(){
    bot(md('업무수행 기록표 기본값 설정이 끝났습니다.'));
    var rows=[['소방시설',SAVED.note_sobang],['피난방화시설',SAVED.note_pinan],['화기취급감독',SAVED.note_hwagi],['기타사항',SAVED.note_etc]];
    var html='<h2>확인내용 기본값</h2>';
    rows.forEach(function(r){html+='<div class="sum"><span class="sum__k">'+esc(r[0])+'</span><span class="sum__v">'+esc(r[1]||'검토요청 또는 미입력')+'</span></div>';});
    html+='<div class="doneRow"><a class="btn btn--pri" href="/work_log.php">월별 기록으로 →</a><a class="btn" href="/building_manager.php">메인으로</a></div>';
    var d=document.createElement('div');d.className='done';d.innerHTML=html;chat.appendChild(d);down();
  });
}
start();
</script>
<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>
</html>
