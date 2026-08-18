<?php
/* =============================================================
   assist.php — 서식 작성 도우미 (규칙 기반)
   로그인 없이 누구나 쓸 수 있습니다. 외부 API를 쓰지 않아
   비용이 들지 않고, 정해둔 문장만 나오므로 오답이 없습니다.
   ============================================================= */
declare(strict_types=1);

if (!ini_get('date.timezone')) { date_default_timezone_set('Asia/Seoul'); }
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

require_once __DIR__ . '/assist_flows.php';

if (!function_exists('h')) {
  function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

$FLOWS = assist_flows();
$FAQ   = assist_faq();
$NICK  = $_SESSION['nickname'] ?? '';
$LOGGED = !empty($_SESSION['is_user']) || !empty($_SESSION['is_admin']);
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>서식 작성 도우미 — TWORIX</title>
<meta name="description" content="소방안전관리 법정서식을 질문에 답하며 채웁니다. 업무수행 기록표, 소방훈련 기록부, 자위소방대 편성표, 소방계획서.">
<style>
:root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--bd2:#d4dbe6;--fg:#1a2436;
  --mut:#7a8699;--mut2:#56627a;--brand:#2563eb;--brand2:#1d4ed8;--accent:#0891b2}
*{box-sizing:border-box;margin:0;padding:0}
html,body{background:var(--bg);color:var(--fg);line-height:1.65;
  font-family:Inter,system-ui,"Apple SD Gothic Neo","Malgun Gothic",sans-serif}
a{text-decoration:none;color:inherit}
button{font:inherit;color:inherit;cursor:pointer}
:focus-visible{outline:2px solid var(--brand);outline-offset:2px}

.nav{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.93);
  backdrop-filter:blur(10px);border-bottom:1px solid var(--bd)}
.nav__in{max-width:860px;margin:0 auto;padding:0 20px;height:56px;
  display:flex;align-items:center;justify-content:space-between;gap:12px}
.brand{font-weight:800;font-size:21px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 15px;border-radius:9px;
  border:1px solid var(--bd2);background:#fff;font-size:13px;font-weight:600;transition:.15s}
.btn:hover{border-color:var(--brand);color:var(--brand2)}
.btn--pri{background:var(--brand);border-color:var(--brand);color:#fff}
.btn--pri:hover{background:var(--brand2);color:#fff}
.btn--sm{padding:6px 12px;font-size:12.5px}

.head{border-bottom:1px solid var(--bd);background:linear-gradient(180deg,#fbfcff,#eef3fb)}
.head__in{max-width:860px;margin:0 auto;padding:34px 20px 28px}
.tag{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;
  border:1px solid var(--bd2);background:#fff;color:var(--mut2);font-size:12px;margin-bottom:11px}
.tag span{width:6px;height:6px;border-radius:50%;background:var(--accent)}
.head h1{font-size:27px;font-weight:800;letter-spacing:-.4px}
.head p{color:var(--mut2);font-size:14.5px;margin-top:7px;max-width:56ch}

.wrap{max-width:860px;margin:0 auto;padding:24px 20px 120px}

/* 서식 고르기 */
.picks{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px;margin-top:4px}
.pick{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:17px 18px;
  text-align:left;transition:.16s;width:100%}
.pick:hover{border-color:var(--brand);transform:translateY(-2px);
  box-shadow:0 10px 24px -18px rgba(37,99,235,.5)}
.pick__top{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:9px}
.badge{font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;
  background:#eef2ff;color:var(--brand2)}
.cycle{font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;
  background:#f0fdf4;color:#15803d}
.pick h3{font-size:16.5px;font-weight:700}
.pick p{font-size:13px;color:var(--mut2);margin-top:4px;line-height:1.55}

/* 대화 */
.chat{margin-top:6px}
.msg{display:flex;gap:11px;margin-bottom:16px;animation:pop .28s ease both}
@keyframes pop{from{opacity:0;transform:translateY(7px)}to{opacity:1;transform:none}}
.msg__av{width:31px;height:31px;border-radius:9px;flex-shrink:0;display:flex;
  align-items:center;justify-content:center;font-size:15px;background:#eef2ff}
.msg__b{background:var(--card);border:1px solid var(--bd);border-radius:4px 14px 14px 14px;
  padding:14px 17px;max-width:calc(100% - 44px);font-size:14.7px;line-height:1.72}
.msg--me{flex-direction:row-reverse}
.msg--me .msg__av{background:#e6edfb}
.msg--me .msg__b{background:var(--brand);border-color:var(--brand);color:#fff;
  border-radius:14px 4px 14px 14px}
.msg__b b,.msg__b strong{font-weight:700}
.msg__b ul{margin:9px 0 0;padding-left:19px}
.msg__b li{margin-bottom:4px}
.msg__b p+p{margin-top:10px}
.hint{font-size:12.5px;color:var(--mut);margin-top:9px;padding-top:9px;border-top:1px dashed var(--bd)}

/* 답변 입력 */
.answer{margin:0 0 22px 42px}
.opts{display:flex;flex-wrap:wrap;gap:8px}
.opt{padding:9px 15px;border:1px solid var(--bd2);border-radius:999px;background:#fff;
  font-size:13.5px;font-weight:500;transition:.14s}
.opt:hover{border-color:var(--brand);color:var(--brand2);background:#f7faff}
.opt.on{background:var(--brand);border-color:var(--brand);color:#fff}
.inrow{display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap}
.inrow input,.inrow textarea{flex:1;min-width:190px;padding:11px 14px;border:1px solid var(--bd2);
  border-radius:11px;background:#fff;font-size:14.5px;font-family:inherit}
.inrow textarea{min-height:88px;resize:vertical;line-height:1.6}
.inrow input:focus,.inrow textarea:focus{outline:none;border-color:var(--brand);
  box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.subrow{display:flex;gap:8px;margin-top:9px;flex-wrap:wrap}

/* 결과 */
.res{background:var(--card);border:1px solid var(--bd);border-radius:14px;
  padding:18px 19px;margin-bottom:13px}
.res__hd{display:flex;justify-content:space-between;align-items:center;gap:11px;
  margin-bottom:11px;flex-wrap:wrap}
.res__t{font-size:15px;font-weight:800}
.res__b{font-size:14.6px;line-height:1.75;white-space:pre-wrap;word-break:break-word}
.res__b.quote{background:#f7f9fd;border-left:3px solid var(--brand);border-radius:0 8px 8px 0;
  padding:14px 16px;font-size:14.5px}
.copied{font-size:12px;color:#15803d;font-weight:700}

.notice{display:flex;gap:11px;background:#fffbeb;border:1px solid #fde68a;border-radius:12px;
  padding:14px 16px;font-size:13.4px;color:#92400e;line-height:1.7;margin-top:16px}
.done-row{display:flex;gap:9px;flex-wrap:wrap;margin-top:16px}

/* 자유 질문 */
.ask{position:fixed;left:0;right:0;bottom:0;background:rgba(255,255,255,.96);
  backdrop-filter:blur(10px);border-top:1px solid var(--bd);padding:12px 20px;z-index:40}
.ask__in{max-width:860px;margin:0 auto;display:flex;gap:8px}
.ask input{flex:1;padding:12px 15px;border:1px solid var(--bd2);border-radius:11px;
  font-size:14.5px;font-family:inherit}
.ask input:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.ask__note{max-width:860px;margin:7px auto 0;font-size:11.5px;color:var(--mut);text-align:center}

.typing{display:inline-flex;gap:4px;align-items:center;padding:3px 0}
.typing i{width:6px;height:6px;border-radius:50%;background:var(--mut);display:block;
  animation:blink 1.2s infinite}
.typing i:nth-child(2){animation-delay:.18s}
.typing i:nth-child(3){animation-delay:.36s}
@keyframes blink{0%,60%,100%{opacity:.28}30%{opacity:1}}

@media(max-width:560px){
  .head h1{font-size:23px}
  .answer{margin-left:0}
  .msg__b{max-width:calc(100% - 42px)}
}
@media(prefers-reduced-motion:reduce){
  *{animation-duration:.001ms!important;transition-duration:.001ms!important}
  .pick:hover{transform:none}
}
</style>
</head>
<body>

<nav class="nav">
  <div class="nav__in">
    <a class="brand" href="/index.php">TWORIX</a>
    <div style="display:flex;gap:8px;align-items:center">
      <?php if ($LOGGED): ?>
        <a class="btn" href="/building_manager.php">← 메인</a>
      <?php else: ?>
        <a class="btn" href="/index.php">서비스 소개</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<header class="head">
  <div class="head__in">
    <div class="tag"><span></span> 서식 작성 도우미</div>
    <h1>어떤 서식을 쓰고 계신가요?</h1>
    <p>몇 가지 질문에 답하시면 서식에 그대로 넣을 문장을 만들어 드립니다.
       로그인 없이 쓰실 수 있습니다.</p>
  </div>
</header>

<main class="wrap">
  <div class="chat" id="chat"></div>
</main>

<div class="ask">
  <form class="ask__in" onsubmit="freeAsk(event)">
    <input id="askInput" type="text" placeholder="궁금한 걸 직접 물어보셔도 됩니다"
           autocomplete="off" maxlength="200">
    <button class="btn btn--pri" type="submit">보내기</button>
  </form>
  <p class="ask__note">일반적인 안내입니다. 건물별 판단이 필요한 사항은 관할 소방서에 확인하세요.</p>
</div>

<script>
var FLOWS = <?=json_encode($FLOWS, JSON_UNESCAPED_UNICODE)?>;
var FAQ   = <?=json_encode($FAQ,   JSON_UNESCAPED_UNICODE)?>;
var LOGGED = <?=json_encode($LOGGED)?>;

var chat = document.getElementById('chat');
var state = { flow:null, step:0, ans:{}, multi:[] };

/* ── 화면 그리기 도구 ─────────────────────────────────── */
function esc(s){ return String(s==null?'':s)
  .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

/* 아주 작은 마크다운: **굵게**, - 목록, 줄바꿈 */
function md(s){
  var out = esc(s).replace(/\*\*(.+?)\*\*/g,'<b>$1</b>');
  var lines = out.split('\n'), html = '', ul = false;
  for (var i=0;i<lines.length;i++){
    var l = lines[i];
    if (/^-\s+/.test(l)) {
      if (!ul) { html += '<ul>'; ul = true; }
      html += '<li>' + l.replace(/^-\s+/,'') + '</li>';
    } else {
      if (ul) { html += '</ul>'; ul = false; }
      html += l.trim()==='' ? '<br>' : '<p>' + l + '</p>';
    }
  }
  if (ul) html += '</ul>';
  return html;
}

function scrollDown(){
  requestAnimationFrame(function(){
    window.scrollTo({ top: document.body.scrollHeight, behavior:'smooth' });
  });
}

function bot(html, hint){
  var d = document.createElement('div');
  d.className = 'msg';
  d.innerHTML = '<div class="msg__av">📋</div><div class="msg__b">' + html +
    (hint ? '<div class="hint">' + esc(hint) + '</div>' : '') + '</div>';
  chat.appendChild(d); scrollDown();
  return d;
}
function me(text){
  var d = document.createElement('div');
  d.className = 'msg msg--me';
  d.innerHTML = '<div class="msg__av">🙂</div><div class="msg__b">' + esc(text) + '</div>';
  chat.appendChild(d); scrollDown();
}
function typing(cb){
  var d = bot('<span class="typing"><i></i><i></i><i></i></span>');
  setTimeout(function(){ d.remove(); cb(); }, 380);
}
function clearAnswer(){
  var a = document.getElementById('answerBox');
  if (a) a.remove();
}
function answerBox(){
  clearAnswer();
  var d = document.createElement('div');
  d.className = 'answer'; d.id = 'answerBox';
  chat.appendChild(d); scrollDown();
  return d;
}

/* ── 시작 화면 ────────────────────────────────────────── */
function start(){
  chat.innerHTML = '';
  state = { flow:null, step:0, ans:{}, multi:[] };
  bot(md('안녕하세요. 소방안전관리 서식을 채우는 걸 도와드립니다.\n어떤 서식을 작성하시나요?'));

  var box = answerBox();
  var g = document.createElement('div');
  g.className = 'picks';
  Object.keys(FLOWS).forEach(function(k){
    var f = FLOWS[k];
    var b = document.createElement('button');
    b.className = 'pick'; b.type = 'button';
    b.innerHTML = '<div class="pick__top"><span class="badge">' + esc(f.badge) +
      '</span><span class="cycle">' + esc(f.cycle) + '</span></div>' +
      '<h3>' + esc(f.label) + '</h3><p>' + esc(f.desc) + '</p>';
    b.onclick = function(){ pickFlow(k); };
    g.appendChild(b);
  });
  box.appendChild(g);
}

function pickFlow(key){
  state.flow = key; state.step = 0; state.ans = {};
  clearAnswer();
  me(FLOWS[key].label);
  typing(function(){
    bot(md(FLOWS[key].intro));
    nextStep();
  });
}

/* ── 질문 진행 ────────────────────────────────────────── */
function visibleSteps(){
  var f = FLOWS[state.flow], out = [];
  for (var i=0;i<f.steps.length;i++){
    var s = f.steps[i];
    if (s.when){
      var v = state.ans[s.when.field];
      if (s.when.is  !== undefined && v !== s.when.is)  continue;
      if (s.when.not !== undefined && v === s.when.not) continue;
    }
    out.push(s);
  }
  return out;
}

function nextStep(){
  var steps = visibleSteps();
  if (state.step >= steps.length) { finish(); return; }
  var s = steps[state.step];
  typing(function(){ askStep(s); });
}

function askStep(s){
  bot(md(s.q), s.hint || '');
  var box = answerBox();
  state.multi = [];

  if (s.type === 'choice' || s.type === 'multi'){
    var wrap = document.createElement('div');
    wrap.className = 'opts';
    (s.options||[]).forEach(function(o){
      var b = document.createElement('button');
      b.className = 'opt'; b.type='button'; b.textContent = o;
      b.onclick = function(){
        if (s.type === 'choice'){ submit(s, o); return; }
        var i = state.multi.indexOf(o);
        if (i >= 0){ state.multi.splice(i,1); b.classList.remove('on'); }
        else { state.multi.push(o); b.classList.add('on'); }
      };
      wrap.appendChild(b);
    });
    box.appendChild(wrap);

    if (s.type === 'multi'){
      var row = document.createElement('div');
      row.className = 'subrow';
      var ok = document.createElement('button');
      ok.className = 'btn btn--pri'; ok.type='button'; ok.textContent = '선택 완료';
      ok.onclick = function(){
        if (!state.multi.length){ alert('하나 이상 골라주세요.'); return; }
        submit(s, state.multi.join(', '));
      };
      row.appendChild(ok);
      box.appendChild(row);
    }
  } else {
    var row2 = document.createElement('div');
    row2.className = 'inrow';
    var inp;
    if (s.type === 'textarea'){ inp = document.createElement('textarea'); }
    else {
      inp = document.createElement('input');
      inp.type = (s.type === 'number') ? 'number' : (s.type === 'date' ? 'date' : 'text');
      if (s.type === 'number') inp.inputMode = 'numeric';
    }
    if (s.ph) inp.placeholder = s.ph;
    if (s.type === 'date') inp.value = new Date().toISOString().slice(0,10);
    row2.appendChild(inp);

    var go = document.createElement('button');
    go.className = 'btn btn--pri'; go.type='button'; go.textContent = '다음';
    go.onclick = function(){
      var v = (inp.value||'').trim();
      if (!v){ inp.focus(); return; }
      submit(s, v);
    };
    row2.appendChild(go);
    box.appendChild(row2);

    inp.addEventListener('keydown', function(e){
      if (e.key === 'Enter' && s.type !== 'textarea'){ e.preventDefault(); go.click(); }
    });
    setTimeout(function(){ inp.focus(); }, 60);
  }

  if (s.skip){
    var sr = document.createElement('div');
    sr.className = 'subrow';
    var sk = document.createElement('button');
    sk.className = 'btn btn--sm'; sk.type='button'; sk.textContent = '건너뛰기';
    sk.onclick = function(){ submit(s, ''); };
    sr.appendChild(sk);
    box.appendChild(sr);
  }

  if (LOGGED){
    var rr = document.createElement('div');
    rr.className = 'subrow';
    var rb = document.createElement('button');
    rb.className = 'btn btn--sm'; rb.type = 'button';
    rb.textContent = '모르면 TWORIX 검토요청';
    rb.onclick = function(){ requestReview(s.q, rb); };
    rr.appendChild(rb);
    box.appendChild(rr);
  }
}

function submit(s, val){
  state.ans[s.id] = val;
  clearAnswer();
  me(val === '' ? '(건너뜀)' : val);
  state.step++;
  nextStep();
}

/* ── 값 다듬기 ────────────────────────────────────────── */
function fmtDate(v){
  if (!v) return '';
  var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v);
  return m ? (m[1] + '년 ' + (+m[2]) + '월 ' + (+m[3]) + '일') : v;
}
function bullets(csv, drop){
  var a = String(csv||'').split(',').map(function(x){ return x.trim(); })
          .filter(function(x){ return x && (!drop || drop.indexOf(x) < 0); });
  return a.length ? a.map(function(x){ return '- ' + x; }).join('\n') : null;
}

/* 받침을 보고 조사를 고른다. josa('피난 훈련','을') → '피난 훈련을' */
function josa(word, kind){
  var w = String(word||'').trim();
  if (!w) return w;
  var c = w.charCodeAt(w.length - 1);
  var hasJong;
  if (c >= 0xAC00 && c <= 0xD7A3) hasJong = ((c - 0xAC00) % 28) !== 0;
  else if (c >= 48 && c <= 57)    hasJong = ('0136780'.indexOf(w[w.length-1]) < 0) ? false : true;
  else return w + (kind === '을' ? '을(를)' : kind === '은' ? '은(는)' : '이(가)');
  if (kind === '을') return w + (hasJong ? '을' : '를');
  if (kind === '은') return w + (hasJong ? '은' : '는');
  return w + (hasJong ? '이' : '가');
}

/* 서식마다 필요한 파생 문장을 만든다 */
function derive(){
  var a = state.ans, d = {};
  for (var k in a) d[k] = a[k];
  d.dayRaw = a.day || '';
  d.day = fmtDate(a.day);

  if (state.flow === 'worklog'){
    if (a.result === '모두 이상 없음') d.resultLine = '점검 결과 이상 없음.';
    else if (a.result === '일부 이상 발견 — 조치 완료')
      d.resultLine = a.issue ? ('점검 결과 다음 사항을 발견하여 조치 완료함 — ' + a.issue)
                             : '점검 결과 일부 이상을 발견하여 조치 완료함.';
    else
      d.resultLine = a.issue ? ('점검 결과 다음 사항을 발견함 — ' + a.issue + ' (조치 진행 중)')
                             : '점검 결과 일부 이상을 발견하여 조치 진행 중.';
  }

  if (state.flow === 'train'){
    var sc = (a.scenario === '직접 입력')
      ? (a.scenario_etc || '화재 발생') + ' 상황을 가정'
      : String(a.scenario||'').replace(/\s*가정$/,'') + '을 가정';
    d.scenarioLine = sc;
    d.kinds = String(a.kinds||'').split(',').map(function(x){return x.trim();}).join('·');
    d.kindsPhrase = josa(d.kinds, '을');
    d.foundLine = a.found ? ('훈련 중 확인된 보완 사항 — ' + a.found) : '훈련 중 특이사항 없음.';
  }

  if (state.flow === 'jawi'){
    var n = parseInt(a.staff, 10) || 0;
    var L = a.leader || '관리책임자';
    if (n < 10){
      d.teamPlan =
        '**소규모 편성 (상주 ' + n + '명)**\n' +
        '인원이 적어 한 사람이 여러 역할을 겸합니다.\n\n' +
        '- **지휘 · 통보** — ' + L + ' : 상황 판단, 119 신고, 관계인 연락\n' +
        '- **초기소화 · 피난유도** — 근무자 전원 : 소화기·옥내소화전 사용, 재실자 대피 안내\n\n' +
        '겸임하더라도 **누가 119에 신고할지**는 반드시 한 사람으로 정해두세요. ' +
        '모두가 신고하겠거니 하다가 아무도 안 하는 일이 실제로 생깁니다.';
    } else if (n < 50){
      d.teamPlan =
        '**표준 편성 (상주 ' + n + '명)**\n\n' +
        '- **지휘조** — ' + L + ' : 전체 상황 판단과 지휘, 소방대 도착 시 정보 전달\n' +
        '- **비상연락조** — 1~2명 : 119 신고, 관계인·입주사 연락, 안내방송\n' +
        '- **초기소화조** — 2~3명 : 소화기·옥내소화전으로 초기 진화\n' +
        '- **피난유도조** — 2~3명 : 피난 경로 안내, 층별 대피 확인, 미대피자 파악\n\n' +
        '각 조에 **조장 한 명**을 정하고, 조장이 자리를 비울 때 대신할 사람도 함께 적어두세요.';
    } else {
      d.teamPlan =
        '**층별 편성 (상주 ' + n + '명)**\n\n' +
        '인원이 많으면 본부와 층별 조직을 나누는 편이 실제로 움직입니다.\n\n' +
        '**본부**\n' +
        '- **지휘조** — ' + L + ' : 전체 지휘, 방재실 상주, 소방대 도착 시 정보 전달\n' +
        '- **비상연락조** — 2명 : 119 신고, 전관 방송, 입주사 연락\n\n' +
        '**층별 (각 층 또는 구역마다)**\n' +
        '- **초기소화 담당** — 2명 : 해당 층 소화기·옥내소화전\n' +
        '- **피난유도 담당** — 2명 : 해당 층 대피 안내와 잔류자 확인\n\n' +
        '층별 담당자 명단은 **각 층 게시판에 붙여두세요.** 본인이 담당인 줄 모르는 경우가 가장 많습니다.';
    }

    if (a.shift === '주간에만 근무'){
      d.shiftTitle = '야간·휴일에는 어떻게 하나요';
      d.shiftAdvice =
        '주간에만 근무하시면 **야간과 휴일이 빈다는 점**을 계획에 적어두어야 합니다.\n\n' +
        '- 야간 화재 시 최초 대응은 자동화재탐지설비와 관제(경비) 업체가 맡습니다. ' +
        '업체 연락처와 대응 절차를 편성표에 함께 적어두세요.\n' +
        '- 야간에 연락받을 **비상연락 담당자**를 정하고 휴대폰 번호를 적어둡니다.\n' +
        '- 야간 출입 방법(열쇠·비밀번호 관리)도 함께 정리해 두면 좋습니다.';
    } else if (a.shift === '야간에는 방재실만 근무'){
      d.shiftTitle = '야간 편성은 따로 만드세요';
      d.shiftAdvice =
        '야간에 방재실만 근무하시면 **주간 편성과 야간 편성을 나눠** 적어야 합니다.\n\n' +
        '- **야간 근무자 1인 기준 행동 순서**를 정해두세요. ' +
        '보통 상황 확인 → 119 신고 → 비상방송 → 관계인 연락 순입니다.\n' +
        '- 혼자서 초기 진화까지 하려다 시기를 놓치는 경우가 많습니다. ' +
        '**신고와 방송을 먼저** 하도록 순서를 명확히 적어두세요.\n' +
        '- 야간 담당자가 교대하면 편성표의 이름도 함께 고쳐야 합니다.';
    } else {
      d.shiftTitle = '교대 근무라면 조별로 만드세요';
      d.shiftAdvice =
        '교대 근무를 하시면 **근무조마다 편성표를 따로** 만들어야 합니다.\n\n' +
        '- A조·B조처럼 조별로 지휘, 연락, 소화, 피난유도 담당을 각각 정합니다.\n' +
        '- 조별 편성표를 방재실에 나란히 붙여두면 교대할 때 확인하기 좋습니다.\n' +
        '- 인원이 적은 야간조는 겸임이 불가피하니, **역할 우선순위**를 적어두세요.';
    }
  }

  if (state.flow === 'plan'){
    var ov = ['- 대상물 명칭: ' + (a.bname||''), '- 용도: ' + (a.use||''), '- 규모: ' + (a.floors||'')];
    if (a.area)   ov.push('- 연면적: 약 ' + Number(a.area).toLocaleString() + '㎡');
    if (a.people) ov.push('- 평상시 재실 인원: 약 ' + Number(a.people).toLocaleString() + '명');
    d.overview = ov.join('\n');

    d.riskLines = bullets(a.risk, ['특별히 없음'])
      || '- 특별히 구분되는 위험 구역 없음. 다만 전기 설비와 피난 통로 상태는 정기적으로 확인함.';
    d.weakLines = bullets(a.weak, ['해당 없음'])
      || '- 상시 피난 지원이 필요한 인원 없음. 방문객 발생 시 피난유도조가 안내함.';

    var f = [];
    var risk = String(a.risk||''), weak = String(a.weak||'');
    if (risk.indexOf('주방') >= 0)
      f.push('**주방·조리실** — 덕트 청소 주기와 자동소화장치 작동 상태를 계획서에 적어두세요. 주방 화재는 이 두 가지에서 갈립니다.');
    if (risk.indexOf('전기실') >= 0)
      f.push('**전기실·발전기실** — 출입 통제와 주변 가연물 제거를 명시하세요. 창고처럼 쓰이는 경우가 많습니다.');
    if (risk.indexOf('보일러실') >= 0)
      f.push('**보일러실** — 연료 보관 상태와 환기 확인 주기를 적어두세요.');
    if (risk.indexOf('주차장') >= 0)
      f.push('**주차장** — 전기차 충전 구역이 있다면 별도로 다뤄야 합니다. 소화 방식이 일반 차량과 다릅니다.');
    if (risk.indexOf('창고') >= 0)
      f.push('**창고·자재실** — 적재 높이와 스프링클러 헤드 간격, 통로 확보를 적어두세요.');
    if (risk.indexOf('흡연') >= 0)
      f.push('**흡연구역** — 위치와 재떨이 관리 방법을 정해두세요. 지정 구역 밖 흡연이 실제 발화 원인이 되는 경우가 많습니다.');

    if (weak.indexOf('고령자') >= 0 || weak.indexOf('거동') >= 0 || weak.indexOf('장애인') >= 0)
      f.push('**피난 약자** — 계신 층과 인원, 누가 지원할지를 이름으로 적어두세요. ' +
             '엘리베이터를 쓸 수 없으니 **피난용 들것이나 대피 의자**를 어디에 두는지도 함께 적습니다.');
    if (weak.indexOf('어린이') >= 0)
      f.push('**어린이** — 인솔자 지정과 집결 장소를 명확히 하세요. 흩어지면 인원 확인이 어렵습니다.');
    if (weak.indexOf('외국인') >= 0)
      f.push('**외국인** — 피난 안내도와 방송에 영어 등 다른 언어를 함께 넣는 것을 검토하세요.');

    if (String(a.floors||'').indexOf('지하') >= 0)
      f.push('**지하층** — 연기가 위로 차오르고 시야가 빨리 나빠집니다. 지하 피난 경로와 배연 설비 상태를 따로 다루세요.');
    if (a.use === '숙박시설')
      f.push('**숙박시설** — 투숙객은 건물 구조를 모릅니다. 객실 내 피난 안내도와 야간 대응 절차가 특히 중요합니다.');
    if (a.use === '판매시설')
      f.push('**판매시설** — 진열대가 피난 통로를 좁히지 않는지 정기적으로 확인하는 항목을 넣으세요.');
    if (a.use === '의료시설')
      f.push('**의료시설** — 스스로 대피하기 어려운 환자가 있어 수평 피난(같은 층 다른 구역으로 이동)을 중심으로 계획을 세웁니다.');
    if (a.use === '복합용도')
      f.push('**복합용도** — 용도마다 관리 주체가 다를 수 있습니다. 누가 어디를 책임지는지 경계를 분명히 적어두세요.');

    if (!f.length) f.push('선택하신 내용만으로는 특별히 강조할 부분이 드러나지 않았습니다. 건물을 한 바퀴 둘러보시면서 평소 신경 쓰이던 곳을 위험요인에 추가해 보세요.');
    d.focus = f.join('\n\n');
  }

  return d;
}

function fill(tpl, d){
  return String(tpl).replace(/\{(\w+)\}/g, function(_, k){
    return (d[k] === undefined || d[k] === null) ? '' : d[k];
  });
}

/* ── 결과 ─────────────────────────────────────────────── */
function finish(){
  var f = FLOWS[state.flow], d = derive();
  clearAnswer();

  typing(function(){
    bot(md('정리했습니다. 아래 문장을 서식에 그대로 넣으시면 됩니다.'));

    var box = answerBox();

    f.blocks.forEach(function(b){
      var card = document.createElement('div');
      card.className = 'res';
      var body = fill(b.body, d);
      var hd = '<div class="res__hd"><div class="res__t">' + esc(fill(b.title, d)) + '</div>';
      if (b.copy) hd += '<button class="btn btn--sm" type="button">복사</button>';
      hd += '</div>';
      card.innerHTML = hd + '<div class="res__b' + (b.copy ? ' quote' : '') + '">' +
                       (b.copy ? esc(body) : md(body)) + '</div>';
      if (b.copy){
        var btn = card.querySelector('button');
        btn.onclick = function(){ copyText(body, btn); };
      }
      box.appendChild(card);
    });

    var note = document.createElement('div');
    note.className = 'notice';
    note.innerHTML = '<div>⚠️</div><div>일반적인 작성 안내입니다. 건물 용도와 등급에 따라 ' +
      '요구되는 내용이 다를 수 있으니, 제출 전 <b>관할 소방서에 확인</b>하시길 권합니다.</div>';
    box.appendChild(note);

    var row = document.createElement('div');
    row.className = 'done-row';
    if (f.link){
      var go = document.createElement('a');
      go.className = 'btn btn--pri'; go.href = f.link;
      go.textContent = f.linkLabel || '작성 화면 열기';
      row.appendChild(go);
    }
    var again = document.createElement('button');
    again.className = 'btn'; again.type='button'; again.textContent = '다른 서식 작성하기';
    again.onclick = start;
    row.appendChild(again);

    var redo = document.createElement('button');
    redo.className = 'btn'; redo.type='button'; redo.textContent = '다시 답하기';
    redo.onclick = function(){ pickFlow(state.flow); };
    row.appendChild(redo);

    box.appendChild(row);
    log('done', state.flow);
  });
}

function copyText(text, btn){
  var done = function(){
    var old = btn.textContent;
    btn.textContent = '복사됨 ✓';
    btn.classList.add('copied');
    setTimeout(function(){ btn.textContent = old; btn.classList.remove('copied'); }, 1600);
  };
  if (navigator.clipboard && window.isSecureContext){
    navigator.clipboard.writeText(text).then(done, function(){ fallbackCopy(text, done); });
  } else fallbackCopy(text, done);
}
function fallbackCopy(text, done){
  var ta = document.createElement('textarea');
  ta.value = text; ta.style.position='fixed'; ta.style.left='-9999px';
  document.body.appendChild(ta); ta.select();
  try { document.execCommand('copy'); done(); } catch(e){ alert('복사에 실패했습니다. 직접 선택해 복사해 주세요.'); }
  ta.remove();
}

/* ── 자유 질문 ────────────────────────────────────────── */
function freeAsk(e){
  e.preventDefault();
  var inp = document.getElementById('askInput');
  var q = (inp.value||'').trim();
  if (!q) return;
  inp.value = '';
  clearAnswer();
  me(q);

  var hit = null;
  for (var i=0;i<FAQ.length;i++){
    for (var j=0;j<FAQ[i].keys.length;j++){
      if (q.indexOf(FAQ[i].keys[j]) >= 0){ hit = FAQ[i]; break; }
    }
    if (hit) break;
  }

  typing(function(){
    if (hit){
      bot(md(hit.answer));
      log('faq', q);
    } else {
      bot(md('그 부분은 제가 준비된 답이 없습니다.\n\n' +
        '저는 **법정 서식을 채우는 것**을 도와드립니다. 아래에서 서식을 고르시면 ' +
        '질문에 답하는 것만으로 문장을 만들어 드립니다.\n\n' +
        '회원님이 직접 판단하기 어려운 내용은 아래 **TWORIX 검토요청**을 눌러 관리자에게 확인을 요청할 수 있습니다.'));
      log('miss', q);
    }
    var box = answerBox();
    if (!hit){
      var reviewRow = document.createElement('div');
      reviewRow.className = 'done-row';
      var reviewBtn = document.createElement(LOGGED ? 'button' : 'a');
      reviewBtn.className = 'btn btn--pri';
      if (LOGGED){
        reviewBtn.type = 'button';
        reviewBtn.textContent = 'TWORIX 검토요청';
        reviewBtn.onclick = function(){ requestReview(q, reviewBtn); };
      } else {
        reviewBtn.href = '/login.php';
        reviewBtn.textContent = '로그인 후 TWORIX 검토요청';
      }
      reviewRow.appendChild(reviewBtn);
      box.appendChild(reviewRow);
    }
    var g = document.createElement('div');
    g.className = 'picks';
    Object.keys(FLOWS).forEach(function(k){
      var f = FLOWS[k];
      var b = document.createElement('button');
      b.className = 'pick'; b.type='button';
      b.innerHTML = '<div class="pick__top"><span class="badge">' + esc(f.badge) + '</span></div>' +
                    '<h3>' + esc(f.label) + '</h3><p>' + esc(f.desc) + '</p>';
      b.onclick = function(){ pickFlow(k); };
      g.appendChild(b);
    });
    box.appendChild(g);
  });
}

function requestReview(question, btn){
  if (btn.disabled) return;
  btn.disabled = true;
  btn.textContent = '요청 중…';
  var fd = new FormData();
  fd.append('kind', 'review');
  fd.append('text', question);
  fetch('assist_log.php', { method:'POST', body:fd, credentials:'same-origin' })
    .then(function(r){ return r.json(); })
    .then(function(j){
      if (!j.ok) throw new Error(j.error || 'request');
      btn.textContent = '검토요청 완료 ✓';
      bot(md('**TWORIX 검토요청**이 접수되었습니다.\n관리자가 회원님의 소방계획서와 질문 내용을 확인할 수 있습니다.'));
    })
    .catch(function(){
      btn.disabled = false;
      btn.textContent = 'TWORIX 검토요청';
      alert('검토요청을 접수하지 못했습니다. 잠시 후 다시 시도해 주세요.');
    });
}

/* ── 무엇을 물어보는지 기록 (선택) ─────────────────────── */
function log(kind, text){
  try {
    if (!window.fetch) return;
    var fd = new FormData();
    fd.append('kind', kind); fd.append('text', text);
    fetch('assist_log.php', { method:'POST', body:fd, credentials:'same-origin' })
      .catch(function(){});
  } catch(e){}
}

start();
</script>
<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>
</html>
