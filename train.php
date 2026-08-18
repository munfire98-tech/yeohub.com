<?php
// train.php — 소방훈련·교육 실시 결과 기록부 목록
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

require_once __DIR__ . '/train_db.php';
$nick = $_SESSION['nickname'] ?? '사용자';

/* 새 기록 */
if (($_GET['new'] ?? '') === '1') {
  $id = tr_create();
  header('Location: /train_edit.php?id=' . urlencode($id)); exit;
}
/* 삭제 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'delete') {
  tr_csrf_check();
  tr_delete((string)($_POST['id'] ?? ''));
  header('Location: /train.php'); exit;
}

$list = tr_list();
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>소방훈련·교육 기록부 — TWORIX</title>
<style>
:root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--bd2:#d4dbe6;--fg:#1a2436;
  --mut:#7a8699;--mut2:#56627a;--brand:#2563eb;--brand2:#1d4ed8;--accent:#0891b2}
*{box-sizing:border-box;margin:0;padding:0}
html,body{background:var(--bg);color:var(--fg);font-family:Inter,system-ui,"Apple SD Gothic Neo",sans-serif;line-height:1.6}
a{text-decoration:none}
.nav{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.92);backdrop-filter:blur(10px);border-bottom:1px solid var(--bd)}
.nav__in{max-width:960px;margin:0 auto;padding:0 20px;height:56px;display:flex;align-items:center;justify-content:space-between;gap:12px}
.brand{font-weight:800;font-size:21px}
.nav__r{display:flex;align-items:center;gap:10px;font-size:14px;color:var(--mut2)}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 15px;border-radius:9px;border:1px solid var(--bd2);background:#fff;color:var(--fg);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit}
.btn:hover{border-color:var(--brand);color:var(--brand2)}
.btn--primary{background:var(--brand);border-color:var(--brand);color:#fff}
.btn--primary:hover{background:var(--brand2);color:#fff}
.btn--danger:hover{border-color:#dc2626;color:#dc2626}
.head{border-bottom:1px solid var(--bd);background:linear-gradient(180deg,#fbfcff,#eef3fb)}
.head__in{max-width:960px;margin:0 auto;padding:40px 20px 34px}
.crumb{font-size:13px;color:var(--mut2);margin-bottom:10px}
.crumb a{color:var(--mut2)}
.badge{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;border:1px solid var(--bd2);background:#fff;color:var(--mut2);font-size:12px;margin-bottom:12px}
.badge span{width:6px;height:6px;border-radius:50%;background:var(--accent)}
.head h1{font-size:28px;font-weight:700;letter-spacing:-.4px}
.head p{color:var(--mut2);font-size:14.5px;margin-top:6px}
.wrap{max-width:960px;margin:0 auto;padding:28px 20px 80px}
.info{display:flex;gap:12px;background:#f0f7ff;border:1px solid #cfe0ff;border-radius:12px;padding:14px 16px;margin-bottom:20px;font-size:13px;color:var(--brand2);line-height:1.7}
.info b{color:var(--brand2)}
.card{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:22px}
.toolbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:12px;flex-wrap:wrap}
.toolbar h2{font-size:18px;font-weight:700}
.empty{text-align:center;color:var(--mut2);padding:44px 20px}
.empty h3{font-size:17px;color:var(--fg);margin-bottom:8px}
.rec{display:flex;align-items:center;gap:14px;padding:15px 6px;border-top:1px solid var(--bd);flex-wrap:wrap}
.rec:first-of-type{border-top:0}
.rec__main{flex:1;min-width:200px}
.rec__name{font-weight:700;font-size:15px}
.rec__name a{color:var(--fg)}.rec__name a:hover{color:var(--brand2)}
.rec__meta{font-size:12.5px;color:var(--mut2);margin-top:2px}
.tag{display:inline-block;font-size:11px;border-radius:999px;padding:2px 9px;font-weight:700;background:#eef2ff;color:var(--brand2)}
</style>
</head>
<body>

<nav class="nav">
  <div class="nav__in">
    <a class="brand" href="/index.php">TWORIX</a>
    <div class="nav__r">
      <span><?=h($nick)?>님</span>
      <a class="btn" href="/building_manager.php">← 메인</a>
      <a class="btn" href="/logout.php">로그아웃</a>
    </div>
  </div>
</nav>

<header class="head">
  <div class="head__in">
    <div class="crumb"><a href="/building_manager.php">건물 소방안전관리</a> › 소방훈련·교육 기록부</div>
    <div class="badge"><span></span> 별지 제28호서식</div>
    <h1>소방훈련·교육 실시 결과 기록부</h1>
    <p>실시한 소방훈련·교육의 결과를 법정서식으로 기록하고 보관합니다.</p>
  </div>
</header>

<main class="wrap">
  <div class="info">
    <div>ℹ️</div>
    <div>
      소방훈련·교육을 실시하면 그 결과를 이 기록부에 작성하고 <b>실시한 날부터 2년간 보관</b>해야 합니다.
      실시하지 않거나 기록하지 않으면 과태료가 부과될 수 있습니다.
      <span style="color:var(--mut2)">(화재예방법 시행규칙 제36조·별지 제28호서식)</span>
    </div>
  </div>

  <div class="card">
    <div class="toolbar">
      <h2>기록부 목록</h2>
      <a class="btn btn--primary" href="/train_chat.php">💬 문답으로 작성하기</a>
      <a class="btn" href="/train.php?new=1">+ 표로 작성</a>
    </div>

    <?php if (!$list): ?>
      <div class="empty">
        <h3>아직 작성된 기록이 없습니다</h3>
        <p>‘문답으로 작성하기’를 누르면 질문에 답하는 것만으로 서식이 채워집니다.</p>
      </div>
    <?php else: foreach ($list as $r): ?>
      <div class="rec">
        <div class="rec__main">
          <div class="rec__name">
            <a href="/train_edit.php?id=<?=h($r['id'])?>"><?=h($r['title'] ?: '(대상명 미입력)')?></a>
            <?php if (!empty($r['train_date'])): ?><span class="tag"><?=h($r['train_date'])?></span><?php endif; ?>
          </div>
          <div class="rec__meta">최근 수정 <?=h(substr((string)($r['updated_at'] ?? ''),0,16))?></div>
        </div>
        <a class="btn" href="/train_chat.php?id=<?=h($r['id'])?>">💬 문답</a>
        <a class="btn" href="/train_edit.php?id=<?=h($r['id'])?>">표로 수정</a>
        <a class="btn" href="/train_print.php?id=<?=h($r['id'])?>">🖨️ 출력</a>
        <form method="post" onsubmit="return confirm('이 기록을 삭제할까요? 되돌릴 수 없습니다.')">
          <input type="hidden" name="act" value="delete">
          <input type="hidden" name="id" value="<?=h($r['id'])?>">
          <input type="hidden" name="csrf" value="<?=h(tr_csrf())?>">
          <button class="btn btn--danger" type="submit">삭제</button>
        </form>
      </div>
    <?php endforeach; endif; ?>
  </div>
</main>

<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>
</html>
