<?php
// admin_memo.php — 관리자 전용 메모 (목표/프로세스 + 할 일 체크리스트)
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

// 관리자만 접근
if (!is_admin()) { header('Location: /login.php'); exit; }

$FILE = __DIR__ . '/data/admin_memo.json';
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

$data = load_json($FILE);
$saved = false;

// 저장 처리 (전체 폼을 한 번에 저장)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
  if (!hash_equals($CSRF, $_POST['csrf'] ?? '')) { http_response_code(403); exit('CSRF'); }

  // 할 일: 제목 배열 + 체크 배열을 합쳐 정리
  $todoTexts = $_POST['todo_text'] ?? [];
  $todoDone  = $_POST['todo_done'] ?? [];   // 체크된 인덱스만 값이 옴
  $todos = [];
  foreach ($todoTexts as $i => $t) {
    $t = trim((string)$t);
    if ($t === '') continue;
    $todos[] = ['text' => $t, 'done' => isset($todoDone[$i])];
  }

  $data = [
    'big_goal'   => trim($_POST['big_goal'] ?? ''),
    'small_goal' => trim($_POST['small_goal'] ?? ''),
    'process'    => trim($_POST['process'] ?? ''),
    'todos'      => $todos,
    'updated'    => date('Y-m-d H:i'),
  ];
  save_json($FILE, $data);
  $saved = true;
}

$bigGoal   = $data['big_goal'] ?? '';
$smallGoal = $data['small_goal'] ?? '';
$process   = $data['process'] ?? '';
$todos     = $data['todos'] ?? [];
$updated   = $data['updated'] ?? '';
$nick = $_SESSION['nickname'] ?? '관리자';
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>관리자 메모 — TWORIX</title>
<style>
:root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--bd2:#d4dbe6;--fg:#1a2436;--mut:#7a8699;--mut2:#56627a;--brand:#2563eb;--brand2:#1d4ed8;--accent:#0891b2;--ok:#16a34a}
*{box-sizing:border-box;margin:0;padding:0}
html,body{background:var(--bg);color:var(--fg);font-family:Inter,ui-sans-serif,system-ui,"Apple SD Gothic Neo",sans-serif;line-height:1.6}
a{text-decoration:none}
.nav{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.9);backdrop-filter:blur(12px);border-bottom:1px solid var(--bd)}
.nav__inner{max-width:900px;margin:0 auto;padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;gap:16px}
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
.page-head__inner{position:relative;max-width:900px;margin:0 auto;padding:40px 24px 32px}
.badge{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;border:1px solid var(--bd2);background:#fff;color:var(--mut2);font-size:12px;margin-bottom:12px}
.badge span{width:6px;height:6px;border-radius:50%;background:var(--accent);display:inline-block}
.page-head h1{font-size:clamp(24px,3.5vw,32px);font-weight:700;letter-spacing:-.5px;margin-bottom:6px}
.page-head p{color:var(--mut2);font-size:14px}
.wrap{max-width:900px;margin:0 auto;padding:28px 24px 90px}
.section{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:22px 24px;margin-bottom:18px}
.section h2{font-size:16px;font-weight:800;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.section h2 .em{font-size:18px}
.goal2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.field{display:flex;flex-direction:column;gap:6px}
.field label{font-size:12px;color:var(--mut2);font-weight:700}
textarea,.inp{width:100%;padding:12px 13px;border:1px solid var(--bd2);border-radius:10px;font-size:14px;font-family:inherit;background:#f8fafc;color:var(--fg);resize:vertical}
textarea:focus,.inp:focus{outline:none;border-color:var(--brand);background:#fff}
.big textarea{min-height:80px}
.small textarea{min-height:80px}
.process textarea{min-height:90px}

/* 할 일 */
.todos{display:flex;flex-direction:column;gap:8px}
.todo{display:flex;align-items:center;gap:10px;border:1px solid var(--bd);border-radius:10px;padding:8px 10px;background:#fff}
.todo input[type=checkbox]{width:18px;height:18px;flex-shrink:0;cursor:pointer}
.todo input[type=text]{flex:1;border:0;background:transparent;font-size:14px;font-family:inherit;color:var(--fg);outline:none}
.todo.done input[type=text]{text-decoration:line-through;color:var(--mut)}
.todo .del{border:0;background:transparent;color:var(--mut);cursor:pointer;font-size:18px;line-height:1;padding:2px 6px}
.todo .del:hover{color:#dc2626}
.addbtn{margin-top:10px;align-self:flex-start}

.savebar{position:fixed;left:0;right:0;bottom:0;background:rgba(255,255,255,.95);backdrop-filter:blur(8px);border-top:1px solid var(--bd);padding:12px 24px;z-index:40}
.savebar__inner{max-width:900px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:12px}
.savebar .meta{font-size:12px;color:var(--mut)}
.toast{background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;border-radius:9px;padding:10px 14px;font-size:13px;margin-bottom:16px}
@media(max-width:680px){.nav__inner,.page-head__inner{padding-left:16px;padding-right:16px}.goal2{grid-template-columns:1fr}}
</style>
</head>
<body>

<nav class="nav">
  <div class="nav__inner">
    <a class="nav__brand" href="/index.php">TWORIX</a>
    <div class="nav__right">
      <span><?=h($nick)?>님 · 관리자</span>
      <a class="btn" href="/index.php">← 메인</a>
      <a class="btn" href="/logout.php">로그아웃</a>
    </div>
  </div>
</nav>

<header class="page-head">
  <div class="page-head__inner">
    <div class="badge"><span></span> 관리자 전용 메모</div>
    <h1>목표 · 프로세스 · 할 일</h1>
    <p>목표와 진행 계획을 적고, 할 일을 체크하며 관리하세요.</p>
  </div>
</header>

<main class="wrap">

  <?php if ($saved): ?><div class="toast">✓ 저장되었습니다. <?=h($updated)?></div><?php endif; ?>

  <form method="post" id="memoForm">
    <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
    <input type="hidden" name="action" value="save">

    <!-- 목표 -->
    <div class="section">
      <h2><span class="em">🎯</span> 목표</h2>
      <div class="goal2">
        <div class="field big"><label>큰 목표</label>
          <textarea name="big_goal" placeholder="이루고 싶은 큰 방향·목표"><?=h($bigGoal)?></textarea>
        </div>
        <div class="field small"><label>작은 목표</label>
          <textarea name="small_goal" placeholder="지금 집중할 작은 목표들"><?=h($smallGoal)?></textarea>
        </div>
      </div>
    </div>

    <!-- 프로세스 -->
    <div class="section process">
      <h2><span class="em">🧭</span> 프로세스</h2>
      <div class="field">
        <textarea name="process" placeholder="목표를 이루기 위한 단계·진행 방식을 적어두세요"><?=h($process)?></textarea>
      </div>
    </div>

    <!-- 할 일 체크리스트 -->
    <div class="section">
      <h2><span class="em">✅</span> 할 일 체크리스트</h2>
      <div class="todos" id="todos">
        <?php if (empty($todos)): ?>
          <div class="todo">
            <input type="checkbox" name="todo_done[0]">
            <input type="text" name="todo_text[0]" placeholder="할 일을 입력하세요">
            <button type="button" class="del" onclick="delTodo(this)">×</button>
          </div>
        <?php else: foreach ($todos as $i => $t): ?>
          <div class="todo <?= !empty($t['done'])?'done':'' ?>">
            <input type="checkbox" name="todo_done[<?=$i?>]" <?= !empty($t['done'])?'checked':'' ?> onchange="toggleDone(this)">
            <input type="text" name="todo_text[<?=$i?>]" value="<?=h($t['text'])?>" placeholder="할 일을 입력하세요">
            <button type="button" class="del" onclick="delTodo(this)">×</button>
          </div>
        <?php endforeach; endif; ?>
      </div>
      <button type="button" class="btn addbtn" onclick="addTodo()">+ 할 일 추가</button>
    </div>
  </form>
</main>

<div class="savebar">
  <div class="savebar__inner">
    <span class="meta"><?= $updated ? '마지막 저장: '.h($updated) : '아직 저장 전' ?></span>
    <button class="btn btn--primary" type="button" onclick="document.getElementById('memoForm').requestSubmit()">💾 저장하기</button>
  </div>
</div>

<script>
  let todoIndex = <?= max(count($todos), 1) ?>;
  function addTodo(){
    const box = document.getElementById('todos');
    const i = todoIndex++;
    const div = document.createElement('div');
    div.className = 'todo';
    div.innerHTML =
      '<input type="checkbox" name="todo_done['+i+']" onchange="toggleDone(this)">' +
      '<input type="text" name="todo_text['+i+']" placeholder="할 일을 입력하세요">' +
      '<button type="button" class="del" onclick="delTodo(this)">×</button>';
    box.appendChild(div);
    div.querySelector('input[type=text]').focus();
  }
  function delTodo(btn){
    const row = btn.closest('.todo');
    const box = document.getElementById('todos');
    row.remove();
    if (box.children.length === 0) addTodo();
  }
  function toggleDone(cb){
    cb.closest('.todo').classList.toggle('done', cb.checked);
  }
</script>

<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>
</html>