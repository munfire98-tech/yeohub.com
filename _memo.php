<?php
/* =============================================================
   _memo.php — 관리자 메모 (어느 화면에서든 열립니다)
   ─────────────────────────────────────────────────────────────
   화면 오른쪽 아래에 작은 버튼이 떠 있고, 누르면 메모창이 열립니다.
   적으면 바로 저장되고, 화면을 옮겨도 그대로 이어집니다.

   쓰는 법 — 공통으로 불리는 파일 맨 위에 한 줄만 넣으면 됩니다.
     @include_once __DIR__ . '/_memo.php';

   저장 위치: data/admin_memo.json  (admin_memo.php 와 같은 파일을 씁니다)
   관리자에게만 보입니다.
   ============================================================= */
declare(strict_types=1);

if (defined('MEMO_BAR_READY')) return;
define('MEMO_BAR_READY', 1);
if (PHP_SAPI === 'cli') return;

/* ── 저장·불러오기 (다른 파일과 이름이 겹치지 않게 memo_ 를 붙입니다) ── */
function memo_file(): string { return __DIR__ . '/data/admin_memo.json'; }

function memo_is_admin(): bool {
  if ((!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1)) return true;

  /* 회원 화면을 대리로 보는 중에는 관리자 권한을 잠시 내려놓습니다.
     그래도 메모는 계속 쓸 수 있어야 하므로, 원래 관리자였는지 확인합니다. */
  $a = $_SESSION['_imp']['admin'] ?? null;
  if (is_array($a)) {
    return (!empty($a['is_admin']) && $a['is_admin'])
        || (!empty($a['ID_OK']) && $a['ID_OK'] == 1);
  }
  return false;
}

function memo_load(): array {
  $f = memo_file();
  if (!is_file($f)) return [];
  $a = json_decode((string)@file_get_contents($f), true);
  return is_array($a) ? $a : [];
}

function memo_save(array $d): bool {
  $f   = memo_file();
  $dir = dirname($f);
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
  if (@file_put_contents($tmp, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) {
    @unlink($tmp); return false;
  }
  return @rename($tmp, $f);
}

function memo_csrf(): string {
  if (empty($_SESSION['memo_csrf'])) $_SESSION['memo_csrf'] = bin2hex(random_bytes(16));
  return (string)$_SESSION['memo_csrf'];
}

/* ══════════════════════════════════════════════════════════
   저장 요청 처리 — 어느 화면에서 보내든 여기서 받습니다.
   화면이 그려지기 전에 처리하고 끝냅니다.
   ══════════════════════════════════════════════════════════ */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['__memo_act'])) {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  header('Content-Type: application/json; charset=utf-8');
  header('X-Content-Type-Options: nosniff');

  if (!memo_is_admin()) { echo json_encode(['ok' => false, 'error' => '권한 없음']); exit; }
  if (!hash_equals(memo_csrf(), (string)($_POST['csrf'] ?? ''))) {
    echo json_encode(['ok' => false, 'error' => '세션이 만료되었습니다. 새로고침 후 다시 시도해 주세요.']); exit;
  }

  $act  = (string)$_POST['__memo_act'];
  $data = memo_load();

  if ($act === 'note') {
    $txt = (string)($_POST['text'] ?? '');
    if (function_exists('mb_substr')) $txt = mb_substr($txt, 0, 20000, 'UTF-8');
    else                              $txt = substr($txt, 0, 60000);
    $data['quick_note']   = $txt;
    $data['quick_at']     = date('Y-m-d H:i:s');
    echo json_encode(['ok' => memo_save($data), 'at' => $data['quick_at']], JSON_UNESCAPED_UNICODE);
    exit;
  }

  /* 큰 목표 · 작은 목표 · 진행 상황 — admin_memo.php 와 같은 칸입니다 */
  if ($act === 'goal') {
    $k = (string)($_POST['key'] ?? '');
    if (!in_array($k, ['big_goal', 'small_goal', 'process'], true)) {
      echo json_encode(['ok' => false, 'error' => '알 수 없는 항목']); exit;
    }
    $txt = (string)($_POST['text'] ?? '');
    $txt = function_exists('mb_substr') ? mb_substr($txt, 0, 4000, 'UTF-8') : substr($txt, 0, 12000);
    $data[$k] = $txt;
    echo json_encode(['ok' => memo_save($data)], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($act === 'todo_add') {
    $txt = trim((string)($_POST['text'] ?? ''));
    if (function_exists('mb_substr')) $txt = mb_substr($txt, 0, 200, 'UTF-8');
    if ($txt === '') { echo json_encode(['ok' => false, 'error' => '내용을 적어주세요.']); exit; }
    $todos = is_array($data['todos'] ?? null) ? $data['todos'] : [];
    if (count($todos) >= 200) { echo json_encode(['ok' => false, 'error' => '할 일이 너무 많습니다. 정리해 주세요.']); exit; }
    array_unshift($todos, [
      'text' => $txt,
      'done' => false,
      'at'   => date('Y-m-d H:i'),
      'from' => substr((string)($_POST['from'] ?? ''), 0, 60),   // 어느 화면에서 적었는지
    ]);
    $data['todos'] = $todos;
    echo json_encode(['ok' => memo_save($data), 'todos' => $todos], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($act === 'todo_toggle' || $act === 'todo_del') {
    $i     = (int)($_POST['i'] ?? -1);
    $todos = is_array($data['todos'] ?? null) ? $data['todos'] : [];
    if (isset($todos[$i])) {
      if ($act === 'todo_del') { array_splice($todos, $i, 1); }
      else { $todos[$i]['done'] = empty($todos[$i]['done']); }
      $data['todos'] = $todos;
      memo_save($data);
    }
    echo json_encode(['ok' => true, 'todos' => $todos], JSON_UNESCAPED_UNICODE);
    exit;
  }

  echo json_encode(['ok' => false, 'error' => '알 수 없는 요청']);
  exit;
}

/* ══════════════════════════════════════════════════════════
   화면에 메모 버튼 붙이기
   ══════════════════════════════════════════════════════════ */
ob_start(function (string $html): string {

  if (session_status() !== PHP_SESSION_ACTIVE) return $html;
  if (!memo_is_admin()) return $html;

  /* HTML 화면일 때만 (사진·JSON 응답은 건드리지 않습니다) */
  $pos = strripos($html, '</body>');
  if ($pos === false) return $html;

  $d     = memo_load();
  $note  = (string)($d['quick_note'] ?? '');
  $bigG  = (string)($d['big_goal'] ?? '');
  $smlG  = (string)($d['small_goal'] ?? '');
  $proc  = (string)($d['process'] ?? '');
  $todos = is_array($d['todos'] ?? null) ? $d['todos'] : [];
  $open  = 0;
  foreach ($todos as $t) { if (empty($t['done'])) $open++; }

  $csrf = memo_csrf();
  $here = basename((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
  $impNick = '';
  if (!empty($_SESSION['_imp']['uid'])) {
    $impNick = (string)($_SESSION['_imp']['nick'] ?? $_SESSION['_imp']['uid']);
  }

  $J = fn($x) => json_encode($x, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

  $ui = '
<style>
  #memoFab{position:fixed;right:18px;bottom:18px;z-index:99998;width:52px;height:52px;
    border-radius:50%;border:0;background:#1a2436;color:#fff;font-size:21px;cursor:pointer;
    box-shadow:0 6px 20px rgba(15,30,60,.32);display:flex;align-items:center;justify-content:center}
  #memoFab:hover{background:#2563eb}
  #memoFab .cnt{position:absolute;top:-3px;right:-3px;min-width:20px;height:20px;padding:0 5px;
    border-radius:10px;background:#dc2626;color:#fff;font-size:11px;font-weight:800;
    display:flex;align-items:center;justify-content:center}
  #memoPanel{position:fixed;right:18px;bottom:80px;z-index:99998;width:min(360px,calc(100vw - 36px));
    max-height:min(560px,calc(100vh - 120px));background:#fff;border:1px solid #d4dbe6;
    border-radius:15px;box-shadow:0 18px 46px -12px rgba(15,30,60,.38);
    display:none;flex-direction:column;overflow:hidden;
    font-family:Inter,system-ui,"Apple SD Gothic Neo",sans-serif}
  #memoPanel.on{display:flex}
  .memo__hd{padding:13px 16px;border-bottom:1px solid #e3e8f0;display:flex;align-items:center;
    gap:8px;font-size:14px;font-weight:800;color:#1a2436}
  .memo__hd .sp{flex:1}
  .memo__x{border:0;background:transparent;font-size:19px;color:#7a8699;cursor:pointer;line-height:1}
  .memo__tabs{display:flex;border-bottom:1px solid #e3e8f0}
  .memo__tab{flex:1;padding:10px;border:0;background:#fff;font-size:13px;font-weight:700;
    color:#7a8699;cursor:pointer;font-family:inherit;border-bottom:2px solid transparent}
  .memo__tab.on{color:#1d4ed8;border-bottom-color:#2563eb}
  .memo__body{padding:14px 16px;overflow-y:auto;flex:1}
  .memo__pane{display:none}
  .memo__pane.on{display:block}
  #memoText{width:100%;min-height:180px;border:1px solid #d4dbe6;border-radius:10px;
    padding:11px 13px;font-size:13.5px;font-family:inherit;line-height:1.7;resize:vertical}
  #memoText:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12)}
  .memo__st{font-size:11.5px;color:#7a8699;margin-top:7px;min-height:16px}
  .memo__add{display:flex;gap:7px;margin-bottom:11px}
  .memo__add input{flex:1;border:1px solid #d4dbe6;border-radius:9px;padding:9px 11px;
    font-size:13.5px;font-family:inherit}
  .memo__add input:focus{outline:none;border-color:#2563eb}
  .memo__add button{border:1px solid #2563eb;background:#2563eb;color:#fff;border-radius:9px;
    padding:0 14px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap}
  .memo__li{display:flex;gap:9px;align-items:flex-start;padding:9px 0;border-top:1px solid #eef2f7}
  .memo__li:first-child{border-top:0}
  .memo__li input[type=checkbox]{margin-top:3px;accent-color:#2563eb;flex-shrink:0}
  .memo__li .tx{flex:1;font-size:13.5px;line-height:1.55;word-break:break-all;color:#1a2436}
  .memo__li.done .tx{text-decoration:line-through;color:#9aa5b5}
  .memo__li .mt{font-size:11px;color:#9aa5b5;margin-top:2px}
  .memo__li .rm{border:0;background:transparent;color:#c3ccd9;cursor:pointer;font-size:15px;line-height:1}
  .memo__li .rm:hover{color:#dc2626}
  .memo__lb{display:block;font-size:12px;font-weight:800;color:#56627a;margin:0 0 5px}
  .memo__g{width:100%;border:1px solid #d4dbe6;border-radius:10px;padding:10px 12px;
    font-size:13.5px;font-family:inherit;line-height:1.7;resize:vertical;margin-bottom:13px}
  .memo__g:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12)}
  .memo__full{display:inline-block;margin-top:4px;font-size:12.5px;color:#1d4ed8;
    text-decoration:none;font-weight:700}
  .memo__imp{padding:8px 16px;background:#fffbf5;border-bottom:1px solid #fed7aa;
    font-size:11.5px;color:#b45309;line-height:1.5}
  .memo__empty{font-size:13px;color:#9aa5b5;padding:22px 0;text-align:center}
  @media print{#memoFab,#memoPanel{display:none !important}}
</style>

<button id="memoFab" type="button" title="관리자 메모">📝'
  . ($open > 0 ? '<span class="cnt">' . $open . '</span>' : '') . '</button>

<div id="memoPanel">
  <div class="memo__hd">📝 관리자 메모 <span class="sp"></span>
    <button class="memo__x" type="button" id="memoClose">×</button></div>
  <div class="memo__tabs">
    <button class="memo__tab on" type="button" data-p="note">메모장</button>
    <button class="memo__tab" type="button" data-p="goal">목표</button>
    <button class="memo__tab" type="button" data-p="todo">할 일'
  . ($open > 0 ? ' (' . $open . ')' : '') . '</button>
  </div>
  ' . ($impNick !== '' ? '<div class="memo__imp">👤 ' . htmlspecialchars($impNick, ENT_QUOTES) . '님 계정으로 보는 중 · 메모는 관리자 것으로 저장됩니다</div>' : '') . '
  <div class="memo__body">
    <div class="memo__pane on" data-p="note">
      <textarea id="memoText" placeholder="생각나는 것을 적어두세요. 적으면 바로 저장되고 어느 화면에서든 이어집니다."></textarea>
      <div class="memo__st" id="memoStat"></div>
    </div>
    <div class="memo__pane" data-p="goal">
      <label class="memo__lb">🎯 큰 목표</label>
      <textarea class="memo__g" data-k="big_goal" rows="3" placeholder="올해, 또는 이 분기에 이루고 싶은 것">' . htmlspecialchars($bigG, ENT_QUOTES) . '</textarea>
      <label class="memo__lb">📌 작은 목표</label>
      <textarea class="memo__g" data-k="small_goal" rows="3" placeholder="이번 주, 이번 달에 할 것">' . htmlspecialchars($smlG, ENT_QUOTES) . '</textarea>
      <label class="memo__lb">🔄 진행 상황</label>
      <textarea class="memo__g" data-k="process" rows="4" placeholder="지금 어디까지 왔는지">' . htmlspecialchars($proc, ENT_QUOTES) . '</textarea>
      <div class="memo__st" id="memoGoalStat"></div>
      <a class="memo__full" href="/admin_memo.php">넓은 화면에서 보기 →</a>
    </div>
    <div class="memo__pane" data-p="todo">
      <div class="memo__add">
        <input id="memoNew" placeholder="할 일을 적고 엔터" maxlength="200">
        <button type="button" id="memoAdd">추가</button>
      </div>
      <div id="memoList"></div>
    </div>
  </div>
</div>

<script>
(function(){
  var CSRF  = ' . $J($csrf) . ';
  var HERE  = ' . $J($here) . ';
  var TODOS = ' . $J($todos) . ';
  var NOTE  = ' . $J($note) . ';

  var fab   = document.getElementById("memoFab");
  var panel = document.getElementById("memoPanel");
  var text  = document.getElementById("memoText");
  var stat  = document.getElementById("memoStat");
  var list  = document.getElementById("memoList");
  if (!fab || !panel) return;

  text.value = NOTE;

  function post(body, done){
    var fd = new FormData();
    for (var k in body) fd.append(k, body[k]);
    fd.append("csrf", CSRF);
    fetch(location.pathname + location.search, { method:"POST", body:fd, credentials:"same-origin" })
      .then(function(r){ return r.json(); })
      .then(function(j){ if (done) done(j); })
      .catch(function(){ if (done) done(null); });
  }

  /* 열고 닫기 */
  fab.addEventListener("click", function(){
    panel.classList.toggle("on");
    if (panel.classList.contains("on")) text.focus();
  });
  document.getElementById("memoClose").addEventListener("click", function(){
    panel.classList.remove("on");
  });
  document.addEventListener("keydown", function(e){
    if (e.key === "Escape" && panel.classList.contains("on")) panel.classList.remove("on");
  });

  /* 탭 */
  document.querySelectorAll(".memo__tab").forEach(function(t){
    t.addEventListener("click", function(){
      document.querySelectorAll(".memo__tab").forEach(function(x){ x.classList.remove("on"); });
      t.classList.add("on");
      document.querySelectorAll(".memo__pane").forEach(function(p){
        p.classList.toggle("on", p.getAttribute("data-p") === t.getAttribute("data-p"));
      });
    });
  });

  /* 메모장 — 타자를 멈추면 저장합니다 */
  var timer = null;
  text.addEventListener("input", function(){
    clearTimeout(timer);
    stat.textContent = "…";
    timer = setTimeout(function(){
      post({ __memo_act:"note", text: text.value }, function(j){
        stat.textContent = (j && j.ok) ? ("저장됨 " + (j.at || "").slice(5,16))
                                       : "저장하지 못했습니다";
      });
    }, 600);
  });

  /* 목표 — 타자를 멈추면 저장합니다 */
  var gstat = document.getElementById("memoGoalStat");
  var gtimers = {};
  document.querySelectorAll(".memo__g").forEach(function(el){
    el.addEventListener("input", function(){
      var k = el.getAttribute("data-k");
      clearTimeout(gtimers[k]);
      if (gstat) gstat.textContent = "…";
      gtimers[k] = setTimeout(function(){
        post({ __memo_act:"goal", key:k, text: el.value }, function(j){
          if (gstat) gstat.textContent = (j && j.ok) ? "저장됨" : "저장하지 못했습니다";
        });
      }, 600);
    });
  });

  /* 할 일 */
  function esc(s){ return String(s==null?"":s)
    .replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;"); }

  function draw(){
    if (!TODOS.length){ list.innerHTML = "<div class=\'memo__empty\'>적어둔 할 일이 없습니다.</div>"; return; }
    list.innerHTML = TODOS.map(function(t, i){
      var from = t.from ? (" · " + esc(t.from)) : "";
      return "<div class=\'memo__li" + (t.done ? " done" : "") + "\'>" +
        "<input type=\'checkbox\' data-i=\'" + i + "\'" + (t.done ? " checked" : "") + ">" +
        "<div class=\'tx\'>" + esc(t.text) +
        "<div class=\'mt\'>" + esc(t.at || "") + from + "</div></div>" +
        "<button class=\'rm\' type=\'button\' data-d=\'" + i + "\'>×</button></div>";
    }).join("");
  }
  draw();

  list.addEventListener("change", function(e){
    var i = e.target.getAttribute("data-i");
    if (i === null) return;
    post({ __memo_act:"todo_toggle", i: i }, function(j){
      if (j && j.ok){ TODOS = j.todos || []; draw(); badge(); }
    });
  });
  list.addEventListener("click", function(e){
    var i = e.target.getAttribute("data-d");
    if (i === null) return;
    post({ __memo_act:"todo_del", i: i }, function(j){
      if (j && j.ok){ TODOS = j.todos || []; draw(); badge(); }
    });
  });

  function add(){
    var el = document.getElementById("memoNew");
    var v  = (el.value||"").trim();
    if (v === "") return;
    post({ __memo_act:"todo_add", text: v, from: HERE }, function(j){
      if (j && j.ok){ TODOS = j.todos || []; el.value = ""; draw(); badge(); el.focus(); }
      else if (j && j.error) alert(j.error);
    });
  }
  document.getElementById("memoAdd").addEventListener("click", add);
  document.getElementById("memoNew").addEventListener("keydown", function(e){
    if (e.key === "Enter"){ e.preventDefault(); add(); }
  });

  function badge(){
    var n = TODOS.filter(function(t){ return !t.done; }).length;
    var b = fab.querySelector(".cnt");
    if (n > 0){
      if (!b){ b = document.createElement("span"); b.className = "cnt"; fab.appendChild(b); }
      b.textContent = n;
    } else if (b) b.remove();
    var tab = document.querySelector(".memo__tab[data-p=todo]");
    if (tab) tab.textContent = "할 일" + (n > 0 ? " (" + n + ")" : "");
  }
})();
</script>
';

  return substr($html, 0, $pos) . $ui . substr($html, $pos);
});
