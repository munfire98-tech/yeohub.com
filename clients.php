<?php
// clients.php — 등록/목록/편집/코멘트(추가/삭제) + 업무(할 일) 패널 + 접힘형 신규등록 폼 + 업무 개수 배지 + CSRF + 검색 + 디버그
declare(strict_types=1);

/* ── 30일 로그인 유지 ── */
$SESSION_TTL = 60 * 60 * 24 * 30;
ini_set('session.cookie_httponly', '1');
ini_set('session.gc_maxlifetime', (string)$SESSION_TTL);
ini_set('session.cookie_lifetime', (string)$SESSION_TTL);
if (PHP_VERSION_ID >= 70300) {
  session_set_cookie_params([
    'lifetime' => $SESSION_TTL,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
}
session_start();

/* ── 헬퍼 ── */
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function is_admin(): bool {
  return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
function uuidv4(): string {
  $d = random_bytes(16);
  $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
  $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}
function read_json(string $file): array {
  if (!file_exists($file)) return [];
  $raw = @file_get_contents($file);
  if ($raw === false || trim($raw)==='') return [];
  $arr = json_decode($raw, true);
  return is_array($arr) ? $arr : [];
}
function write_json(string $file, array $arr): bool {
  if (!is_dir(dirname($file))) @mkdir(dirname($file), 0775, true);

  $json = json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
  if ($json === false) return false;
  if (is_file($file) && @file_get_contents($file) === $json) return true;

  if (is_file($file)) {
    $backupDir = dirname($file).'/_backups';
    if (!is_dir($backupDir)) @mkdir($backupDir, 0775, true);
    @copy($file, $backupDir.'/'.basename($file).'.'.date('Ymd_His').'.bak');
  }

  $tmp = $file.'.'.bin2hex(random_bytes(4)).'.tmp';
  if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;
  return @rename($tmp, $file);
}

/* ── 디버그: /clients.php?debug=1 ── */
if (isset($_GET['debug'])) {
  header('Content-Type: text/plain; charset=utf-8');
  echo "FILE: ", __FILE__, "\n";
  echo "session_id: ", session_id(), "\n\n";
  echo "\$_SESSION:\n"; var_export($_SESSION);
  echo "\n\nis_admin(): ", is_admin() ? 'true' : 'false', "\n";
  exit;
}

/* ── 접근 제한 ── */
if (!is_admin()) {
  http_response_code(403);
  echo "<!doctype html><meta charset='utf-8'><body style='background:#0f172a;color:#e5e7eb;font-family:Arial'>
        관리자만 접근 가능 · <a style='color:#93c5fd' href='/login.php'>로그인</a> ·
        <a style='color:#93c5fd' href='/clients.php?debug=1'>디버그</a><?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>";
  exit;
}

/* ── CSRF ── */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

/* ── 데이터 파일 ── */
$DATA_DIR     = __DIR__ . '/data';
$CLIENTS_FILE = $DATA_DIR . '/clients.json';
$TASKS_FILE   = $DATA_DIR . '/tasks.json';
if (!is_dir($DATA_DIR)) @mkdir($DATA_DIR, 0775, true);
if (!file_exists($CLIENTS_FILE)) file_put_contents($CLIENTS_FILE, json_encode([], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
if (!file_exists($TASKS_FILE))   file_put_contents($TASKS_FILE,   json_encode([], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));

$clients = read_json($CLIENTS_FILE);
$tasks   = read_json($TASKS_FILE);

/* ── 기존 코멘트 cid 보정(코멘트 삭제용) ── */
$cidFixed = false;
foreach ($clients as &$cx) {
  if (!isset($cx['comments']) || !is_array($cx['comments'])) continue;
  foreach ($cx['comments'] as &$cm) {
    if (empty($cm['cid'])) { $cm['cid'] = uuidv4(); $cidFixed = true; }
  }
}
unset($cx, $cm);
if ($cidFixed) write_json($CLIENTS_FILE, $clients);

/* ── POST 액션 ── */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST') {
  $action = $_POST['action'] ?? '';
  $csrf   = $_POST['csrf'] ?? '';
  if (!hash_equals($CSRF, (string)$csrf)) { http_response_code(400); exit('CSRF 검증 실패'); }

  /* 고객 생성 */
  if ($action === 'create') {
    $name    = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $birth   = trim($_POST['birth'] ?? '');
    $sm_name = trim($_POST['safety_name'] ?? '');
    $sm_birth= trim($_POST['safety_birth'] ?? '');
    $note0   = trim($_POST['note'] ?? '');

    if ($name === '') { header('Location: /clients.php?err=noname&new=1#newClient'); exit; }

    $item = [
      'id'            => uuidv4(),
      'name'          => $name,
      'address'       => $address,
      'phone'         => $phone,
      'birth'         => $birth,
      'safety_name'   => $sm_name,
      'safety_birth'  => $sm_birth,
      'created'       => date('Y-m-d H:i:s'),
      'comments'      => []
    ];
    if ($note0 !== '') {
      $item['comments'][] = ['cid'=>uuidv4(),'at'=>date('Y-m-d H:i:s'),'by'=>'admin','text'=>$note0];
    }
    array_unshift($clients, $item);
    write_json($CLIENTS_FILE, $clients);
    header('Location: /clients.php?ok=created#'.$item['id']); exit;
  }

  /* 고객 편집 */
  if ($action === 'edit') {
    $id      = $_POST['id'] ?? '';
    $name    = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $birth   = trim($_POST['birth'] ?? '');
    $sm_name = trim($_POST['safety_name'] ?? '');
    $sm_birth= trim($_POST['safety_birth'] ?? '');

    if ($id==='' || $name==='') { header('Location: /clients.php?err=bad#'.$id); exit; }

    foreach ($clients as &$c){
      if ($c['id'] === $id){
        $c['name']         = $name;
        $c['address']      = $address;
        $c['phone']        = $phone;
        $c['birth']        = $birth;
        $c['safety_name']  = $sm_name;
        $c['safety_birth'] = $sm_birth;
        write_json($CLIENTS_FILE, $clients);
        break;
      }
    }
    unset($c);
    header('Location: /clients.php?ok=edited#'.$id); exit;
  }

  /* 코멘트 추가/삭제 */
  if ($action === 'comment') {
    $id   = $_POST['id'] ?? '';
    $text = trim($_POST['text'] ?? '');
    if ($id !== '' && $text !== '') {
      foreach ($clients as &$c){
        if ($c['id'] === $id){
          if (!isset($c['comments']) || !is_array($c['comments'])) $c['comments'] = [];
          $c['comments'][] = ['cid'=>uuidv4(),'at'=>date('Y-m-d H:i:s'),'by'=>'admin','text'=>$text];
          write_json($CLIENTS_FILE, $clients);
          break;
        }
      }
      unset($c);
    }
    header('Location: /clients.php?ok=commented#'.$id); exit;
  }
  if ($action === 'del_comment') {
    $id  = $_POST['id']  ?? '';
    $cid = $_POST['cid'] ?? '';
    $idx = isset($_POST['idx']) ? (int)$_POST['idx'] : -1;
    foreach ($clients as &$c){
      if ($c['id'] === $id && isset($c['comments']) && is_array($c['comments'])) {
        if ($cid !== '') {
          $c['comments'] = array_values(array_filter($c['comments'], fn($cm)=> ($cm['cid'] ?? '') !== $cid));
        } elseif ($idx >= 0 && $idx < count($c['comments'])) {
          array_splice($c['comments'], $idx, 1);
        }
        write_json($CLIENTS_FILE, $clients);
        break;
      }
    }
    unset($c);
    header('Location: /clients.php?ok=comment_deleted#'.$id); exit;
  }

  /* 고객 삭제 */
  if ($action === 'delete') {
    $id = $_POST['id'] ?? '';
    if ($id !== '') {
      $clients = array_values(array_filter($clients, fn($c)=> $c['id'] !== $id));
      write_json($CLIENTS_FILE, $clients);
    }
    header('Location: /clients.php?ok=deleted'); exit;
  }

  /* ── 업무(할 일) 액션 ── */
  if ($action === 'task_create') {
    $title = trim($_POST['title'] ?? '');
    $due   = trim($_POST['due'] ?? ''); // YYYY-MM-DD
    if ($title !== '') {
      array_unshift($tasks, [
        'id'      => uuidv4(),
        'title'   => $title,
        'due'     => $due,
        'done'    => false,
        'created' => date('Y-m-d H:i:s'),
      ]);
      write_json($TASKS_FILE, $tasks);
    }
    header('Location: /clients.php#tasks'); exit;
  }
  if ($action === 'task_toggle') {
    $id = $_POST['id'] ?? '';
    foreach ($tasks as &$t) {
      if ($t['id'] === $id) { $t['done'] = !empty($t['done']) ? false : true; break; }
    }
    unset($t);
    write_json($TASKS_FILE, $tasks);
    header('Location: /clients.php#tasks'); exit;
  }
  if ($action === 'task_delete') {
    $id = $_POST['id'] ?? '';
    $tasks = array_values(array_filter($tasks, fn($t)=> $t['id'] !== $id));
    write_json($TASKS_FILE, $tasks);
    header('Location: /clients.php#tasks'); exit;
  }
  if ($action === 'task_clear_done') {
    $tasks = array_values(array_filter($tasks, fn($t)=> empty($t['done'])));
    write_json($TASKS_FILE, $tasks);
    header('Location: /clients.php#tasks'); exit;
  }
}

/* ── 검색 ── */
$q = trim($_GET['q'] ?? '');
$view = $clients;
if ($q !== '') {
  $qq = mb_strtolower($q);
  $view = array_values(array_filter($clients, function($c) use($qq){
    $hay = mb_strtolower(
      ($c['name']??'').' '.($c['address']??'').' '.($c['phone']??'').' '.($c['birth']??'').' '.
      ($c['safety_name']??'').' '.($c['safety_birth']??'')
    );
    return mb_strpos($hay, $qq) !== false;
  }));
}

/* ── 업무 정렬(미완료 우선, 마감일 오름차순, 미지정은 뒤로) ── */
$tasks_view = $tasks;
usort($tasks_view, function($a,$b){
  $ad = !empty($a['done']); $bd = !empty($b['done']);
  if ($ad !== $bd) return $ad <=> $bd; // done=false(0)가 먼저
  $da = $a['due'] ?? ''; $db = $b['due'] ?? '';
  if ($da === $db) return 0;
  if ($da==='' || $db==='') return $da==='' ? 1 : -1;
  return strcmp($da, $db);
});

/* ── 업무 카운트 (미완료/전체) ── */
$task_total = count($tasks);
$task_open  = 0;
foreach ($tasks as $t) { if (empty($t['done'])) $task_open++; }

/* ── 신규등록 폼 기본 접힘/자동 펼침 조건 ── */
$should_open_new = (isset($_GET['new']) || isset($_GET['err']) || count($clients)===0);
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>거래처 관리</title>
<style>
  :root{
    --bg:#0b1220;--bg2:#0f172a;--card:#0c1222;--card2:#0f172a;--bd:#1f2a3a;--bd-strong:#254060;
    --fg:#e5e7eb;--mut:#94a3b8;--link:#93c5fd;--btn:#2563eb;--warn:#ef4444;--ok:#22c55e;
    --chip:#0b1728;--chipbd:#254060;--accent:#3b82f6;--stripe:#1d4ed8;
  }
  *{box-sizing:border-box}
  body{background:linear-gradient(180deg,var(--bg2),var(--bg));color:var(--fg);font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial;margin:0}
  header,.wrap{max-width:1100px;margin:0 auto;padding:16px}
  header{position:sticky;top:0;background:rgba(10,17,31,.6);border-bottom:1px solid var(--bd);backdrop-filter:saturate(130%) blur(4px);z-index:10}
  h1,h2,h3{margin:0}
  a{color:var(--link);text-decoration:none}
  a:hover{text-decoration:underline}
  .grid{display:grid;grid-template-columns:420px 1fr;gap:16px}
  @media (max-width: 980px){ .grid{grid-template-columns:1fr} }

  .card{background:var(--card2);border:1px solid var(--bd);border-radius:16px;padding:16px;box-shadow:0 10px 30px rgba(0,0,0,.25)}
  .card.alt{background:var(--card)}

  label{display:block;font-size:12px;color:var(--mut);margin:8px 0 6px}
  input,textarea{width:100%;padding:12px;border-radius:12px;border:1px solid var(--bd);background:#0a1324;color:#e5e7eb}
  input::placeholder, textarea::placeholder{color:#6b7280}
  .btn{display:inline-flex;align-items:center;gap:6px;background:var(--btn);color:white;padding:10px 14px;border-radius:12px;border:0;cursor:pointer}
  .btn.ghost{background:transparent;border:1px solid var(--bd);color:#e5e7eb}
  .btn.del{background:var(--warn)}
  .mut{color:var(--mut);font-size:12px}
  .chip{display:inline-flex;align-items:center;gap:6px;font-size:12px;background:var(--chip);border:1px solid var(--chipbd);padding:6px 10px;border-radius:999px}
  .sep{height:1px;background:var(--bd);margin:12px 0}

  /* ── 거래처 카드(구분 라인 강화) ── */
  .client{position:relative;background:var(--card);border:1px solid var(--bd-strong);border-radius:16px;padding:16px;margin:14px 0;box-shadow:0 8px 26px rgba(0,10,20,.35)}
  .client::before{content:"";position:absolute;left:-1px;top:-1px;bottom:-1px;width:6px;border-top-left-radius:16px;border-bottom-left-radius:16px;background:linear-gradient(180deg,var(--stripe),rgba(59,130,246,0))}
  .client:hover{border-color:#3b82f6}
  .client-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;border-bottom:1px dashed var(--bd-strong);padding-bottom:10px;margin-bottom:10px}
  .kvs{display:grid;grid-template-columns:110px 1fr;gap:6px 10px;font-size:13px}
  .kvs .k{color:#93a4bd}
  .comments{margin-top:6px}
  .comment{border:1px solid var(--bd);border-radius:12px;padding:10px;background:#0b1728;margin-bottom:8px}
  .comment-head{display:flex;justify-content:space-between;gap:8px;align-items:center}
  .comment-del{border:0;background:transparent;color:#fda4af;cursor:pointer;font-size:12px}

  /* 편집(details) */
  details.edit{margin-top:10px}
  details.edit > summary{list-style:none}
  details.edit > summary::-webkit-details-marker{display:none}
  details.edit > summary.btn{display:inline-flex}
  .edit-box{border:1px dashed var(--bd);border-radius:12px;padding:12px;margin-top:10px;background:#0a1528}

  /* ── 업무(할 일) 패널 ── */
  details.tasks{position:relative}
  details.tasks > summary{list-style:none}
  details.tasks > summary::-webkit-details-marker{display:none}
  .tasks-panel{
    position:absolute; right:0; margin-top:8px; width:360px; max-width:88vw;
    background:#0b1728; border:1px solid var(--bd); border-radius:14px; padding:12px;
    box-shadow:0 16px 40px rgba(0,0,0,.45);
  }
  .task-row{display:flex; align-items:center; gap:8px; padding:8px; border:1px solid var(--bd); border-radius:10px; background:#0a1324; margin:6px 0}
  .task-row.done{opacity:.6; text-decoration:line-through}
  .task-title{flex:1}
  .task-due{font-size:12px; color:#93a4bd}

  /* ── 접힘(Accordion) ── */
  details.fold > summary {
    cursor:pointer; display:flex; justify-content:space-between; align-items:center;
    list-style:none; padding:12px 14px; margin:-4px 0 8px;
    border:1px dashed var(--bd); border-radius:12px; background:#0a1528; color:var(--fg)
  }
  details.fold > summary::-webkit-details-marker{display:none}
  details.fold[open] > summary{ border-style:solid; background:#0b1728 }
  details.fold .fold-body{ margin-top:10px }

  /* ── 업무 개수 배지 ── */
  .badge{
    display:inline-flex; align-items:center; gap:4px;
    padding:2px 8px; border-radius:999px; font-size:12px;
    background:#0b1728; border:1px solid var(--chipbd); color:#e5e7eb;
  }
</style>
</head>
<body>
<header>
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
    <div style="display:flex;align-items:center;gap:12px">
      <h1 style="font-size:20px">🗂️ 거래처 관리</h1>
      <a href="/list.php" class="chip">← 글 목록</a>
      <?php if(isset($_GET['ok'])): ?>
        <span class="chip" style="border-color:#14532d;background:#0b1f16;color:#86efac">
          <?php
            $ok = $_GET['ok'];
            echo $ok==='comment_deleted' ? '코멘트 삭제 완료' :
                 ($ok==='edited' ? '수정 완료' :
                 ($ok==='created' ? '저장 완료' :
                 ($ok==='deleted' ? '삭제 완료' : '완료')));
          ?>
        </span>
      <?php endif; ?>
    </div>

    <div style="display:flex;align-items:center;gap:10px">
      <form method="get" style="display:flex;gap:8px;margin:0">
        <input type="text" name="q" placeholder="이름·주소·전화·생일·안전관리자 검색" value="<?=h($q)?>" style="min-width:280px">
        <button class="btn" type="submit">검색</button>
        <?php if($q!==''): ?><a href="/clients.php" class="btn ghost">초기화</a><?php endif; ?>
      </form>

      <!-- 우측 상단: 업무(할 일) 패널 (배지 포함) -->
      <details id="tasks" class="tasks">
        <summary class="btn ghost">
          업무 <span class="badge"><?=h((string)$task_open)?>/<?=h((string)$task_total)?></span> ▾
        </summary>
        <div class="tasks-panel">
          <h3 style="margin:0 0 8px;font-size:14px">업무 목록</h3>

          <!-- 신규 업무 추가 -->
          <form method="post" style="display:grid;gap:8px;margin-bottom:8px">
            <input type="hidden" name="action" value="task_create">
            <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
            <input name="title" placeholder="무엇을 해야 하나요?" required>
            <div style="display:flex;gap:8px;align-items:center">
              <input type="date" name="due" style="flex:1;min-width:160px">
              <button class="btn" style="white-space:nowrap">추가</button>
            </div>
          </form>

          <?php
            $undone = array_values(array_filter($tasks_view, fn($t)=>empty($t['done'])));
            $done   = array_values(array_filter($tasks_view, fn($t)=>!empty($t['done'])));
          ?>
          <div class="mut" style="margin-bottom:6px">미완료 <?=count($undone)?> · 완료 <?=count($done)?></div>

          <!-- 미완료 -->
          <?php foreach($undone as $t): ?>
            <div class="task-row">
              <form method="post" style="margin:0">
                <input type="hidden" name="action" value="task_toggle">
                <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
                <input type="hidden" name="id" value="<?=h($t['id'])?>">
                <input type="checkbox" onchange="this.form.submit()">
              </form>
              <div class="task-title"><?=h((string)$t['title'])?></div>
              <div class="task-due"><?=h((string)($t['due'] ?: ''))?></div>
              <form method="post" onsubmit="return confirm('삭제할까요?');" style="margin:0">
                <input type="hidden" name="action" value="task_delete">
                <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
                <input type="hidden" name="id" value="<?=h($t['id'])?>">
                <button class="btn del" title="삭제">삭제</button>
              </form>
            </div>
          <?php endforeach; ?>

          <!-- 완료 -->
          <?php if ($done): ?>
            <div class="sep"></div>
            <div class="mut" style="margin-bottom:6px">완료됨</div>
            <?php foreach($done as $t): ?>
              <div class="task-row done">
                <form method="post" style="margin:0">
                  <input type="hidden" name="action" value="task_toggle">
                  <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
                  <input type="hidden" name="id" value="<?=h($t['id'])?>">
                  <input type="checkbox" checked onchange="this.form.submit()">
                </form>
                <div class="task-title"><?=h((string)$t['title'])?></div>
                <div class="task-due"><?=h((string)($t['due'] ?: ''))?></div>
                <form method="post" onsubmit="return confirm('삭제할까요?');" style="margin:0">
                  <input type="hidden" name="action" value="task_delete">
                  <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
                  <input type="hidden" name="id" value="<?=h($t['id'])?>">
                  <button class="btn del" title="삭제">삭제</button>
                </form>
              </div>
            <?php endforeach; ?>

            <form method="post" style="text-align:right;margin-top:6px">
              <input type="hidden" name="action" value="task_clear_done">
              <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
              <button class="btn ghost">완료 항목 비우기</button>
            </form>
          <?php endif; ?>
        </div>
      </details>

      <!-- 접힘형 신규등록 바로 열기 -->
      <a href="/clients.php?new=1#newClient" class="btn ghost">＋ 거래처</a>
    </div>
  </div>
</header>

<div class="wrap grid">
  <!-- 접힘형: 신규 등록 폼 -->
  <details id="newClient" class="card alt fold" <?= $should_open_new ? 'open' : '' ?>>
    <summary class="fold-summary">＋ 새 거래처 등록 <span class="mut">가끔만 쓰는 영역</span></summary>
    <div class="fold-body">
      <h2 style="margin:0 0 8px">새 거래처 등록</h2>
      <p class="mut" style="margin-top:0">이름은 필수 · 생년월일은 <b>YYYY-MM-DD</b> 권장</p>
      <form method="post">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="csrf" value="<?=h($CSRF)?>">

        <label>거래처명 *</label>
        <input name="name" required placeholder="예) (주)그냥고고 / 김트레킹">

        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <div style="flex:1;min-width:220px">
            <label>주소</label>
            <input name="address" placeholder="예) 서울시 강남구 …">
          </div>
          <div style="flex:1;min-width:220px">
            <label>전화번호</label>
            <input name="phone" placeholder="010-0000-0000">
          </div>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <div style="flex:1;min-width:220px">
            <label>거래처 생년월일</label>
            <input name="birth" placeholder="YYYY-MM-DD" pattern="\d{4}-\d{2}-\d{2}">
          </div>
        </div>

        <div class="sep"></div>
        <h3 style="font-size:14px;margin:0 0 6px">안전관리자</h3>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <div style="flex:1;min-width:220px">
            <label>안전관리자 이름</label>
            <input name="safety_name" placeholder="예) 홍길동">
          </div>
          <div style="flex:1;min-width:220px">
            <label>안전관리자 생년월일</label>
            <input name="safety_birth" placeholder="YYYY-MM-DD" pattern="\d{4}-\d{2}-\d{2}">
          </div>
        </div>

        <label>첫 코멘트 (선택)</label>
        <textarea name="note" rows="3" placeholder="메모, 약속, 특이사항 등"></textarea>

        <div style="display:flex;gap:10px;margin-top:12px">
          <button class="btn">등록</button>
          <a class="btn ghost" href="/clients.php">새로고침</a>
        </div>
      </form>
    </div>
  </details>

  <!-- 목록 -->
  <div class="card">
    <h2 style="margin:0 0 10px">거래처 목록 <span class="mut">(<?=count($view)?>)</span></h2>

    <?php if (!$view): ?>
      <div class="mut">검색 결과가 없습니다.</div>
    <?php endif; ?>

    <?php foreach($view as $c): ?>
      <section class="client" id="<?=h($c['id'])?>">
        <div class="client-head">
          <div>
            <div style="font-weight:800;font-size:16px;letter-spacing:.2px"><?=h((string)$c['name'])?></div>
            <div class="mut" style="margin-top:4px">등록: <?=h((string)($c['created'] ?? '-'))?></div>
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <details class="edit">
              <summary class="btn ghost">편집</summary>
              <div class="edit-box">
                <form method="post" style="display:grid;gap:10px">
                  <input type="hidden" name="action" value="edit">
                  <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
                  <input type="hidden" name="id" value="<?=h($c['id'])?>">

                  <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <div style="flex:1;min-width:220px">
                      <label>거래처명 *</label>
                      <input name="name" required value="<?=h((string)$c['name'])?>">
                    </div>
                    <div style="flex:1;min-width:220px">
                      <label>전화번호</label>
                      <input name="phone" value="<?=h((string)($c['phone'] ?? ''))?>">
                    </div>
                  </div>

                  <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <div style="flex:1;min-width:220px">
                      <label>주소</label>
                      <input name="address" value="<?=h((string)($c['address'] ?? ''))?>">
                    </div>
                    <div style="flex:1;min-width:220px">
                      <label>거래처 생년월일</label>
                      <input name="birth" value="<?=h((string)($c['birth'] ?? ''))?>" placeholder="YYYY-MM-DD" pattern="\d{4}-\d{2}-\d{2}">
                    </div>
                  </div>

                  <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <div style="flex:1;min-width:220px">
                      <label>안전관리자 이름</label>
                      <input name="safety_name" value="<?=h((string)($c['safety_name'] ?? ''))?>">
                    </div>
                    <div style="flex:1;min-width:220px">
                      <label>안전관리자 생년월일</label>
                      <input name="safety_birth" value="<?=h((string)($c['safety_birth'] ?? ''))?>" placeholder="YYYY-MM-DD" pattern="\d{4}-\d{2}-\d{2}">
                    </div>
                  </div>

                  <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <button class="btn">저장</button>
                    <button type="button" class="btn ghost" onclick="this.closest('details').open=false">취소</button>
                  </div>
                </form>
              </div>
            </details>

            <form method="post" onsubmit="return confirm('이 거래처를 삭제할까요?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
              <input type="hidden" name="id" value="<?=h($c['id'])?>">
              <button class="btn del">거래처 삭제</button>
            </form>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
          <!-- 기본정보 -->
          <div class="card" style="background:#0b1728;border-color:var(--bd);box-shadow:none">
            <h3 style="font-size:14px;margin-bottom:6px">기본정보</h3>
            <div class="kvs">
              <div class="k">전화</div><div><?=h((string)($c['phone'] ?? '-'))?></div>
              <div class="k">주소</div><div><?=h((string)($c['address'] ?? '-'))?></div>
              <div class="k">생년월일</div><div><?=h((string)($c['birth'] ?? '-'))?></div>
            </div>
          </div>

          <!-- 안전관리자 -->
          <div class="card" style="background:#0b1728;border-color:var(--bd);box-shadow:none">
            <h3 style="font-size:14px;margin-bottom:6px">안전관리자</h3>
            <div class="kvs">
              <div class="k">이름</div><div><?=h((string)($c['safety_name'] ?? '-'))?></div>
              <div class="k">생년월일</div><div><?=h((string)($c['safety_birth'] ?? '-'))?></div>
            </div>
          </div>
        </div>

        <!-- 코멘트 -->
        <div class="comments">
          <h3 style="font-size:14px;margin:12px 0 6px">코멘트</h3>
          <?php if(!empty($c['comments'])): ?>
            <?php foreach($c['comments'] as $i => $cm): ?>
              <div class="comment">
                <div class="comment-head">
                  <span class="chip"><?=h((string)($cm['by'] ?? 'admin'))?> · <?=h((string)($cm['at'] ?? ''))?></span>
                  <form method="post" onsubmit="return confirm('이 코멘트를 삭제할까요?');" style="margin:0">
                    <input type="hidden" name="action" value="del_comment">
                    <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
                    <input type="hidden" name="id" value="<?=h($c['id'])?>">
                    <input type="hidden" name="cid" value="<?=h((string)($cm['cid'] ?? ''))?>">
                    <input type="hidden" name="idx" value="<?=h((string)$i)?>">
                    <button class="comment-del" title="코멘트 삭제">🗑 삭제</button>
                  </form>
                </div>
                <div style="margin-top:6px"><?=nl2br(h((string)($cm['text'] ?? '')))?></div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="mut">코멘트 없음</div>
          <?php endif; ?>

          <form method="post" style="margin-top:8px;display:flex;gap:8px;align-items:flex-start">
            <input type="hidden" name="action" value="comment">
            <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
            <input type="hidden" name="id" value="<?=h($c['id'])?>">
            <input name="text" placeholder="코멘트 추가" style="flex:1">
            <button class="btn" style="white-space:nowrap">추가</button>
          </form>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
</div>
<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>
</html>
