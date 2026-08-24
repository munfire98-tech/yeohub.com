<?php
// train.php — 자위소방대 및 초기대응체계 교육·훈련 실시 결과 기록부 목록
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

require_once __DIR__ . '/jawi_db.php';
$nick = $_SESSION['nickname'] ?? '사용자';

/* 상단 알림 아이콘의 빨간 점에 쓸 미확인 개수.
   user_key.php 가 없거나 회원을 특정 못 해도 안전하게 0 으로 둡니다. */
$unreadCount = 0;
if (is_file(__DIR__ . '/user_key.php')) {
  require_once __DIR__ . '/user_key.php';
  if (function_exists('app_user_key')) {
    $__uid = app_user_key();
    if ($__uid !== '') {
      $__nf = __DIR__ . '/data/notifications/' . $__uid . '.json';
      if (is_file($__nf)) {
        $__nl = json_decode((string)@file_get_contents($__nf), true);
        if (is_array($__nl)) { foreach ($__nl as $__n) { if (empty($__n['read'])) $unreadCount++; } }
      }
    }
  }
}

/* 새 기록 */
if (($_GET['new'] ?? '') === '1') {
  $id = jw_create();
  header('Location: /jawi_edit.php?id=' . urlencode($id)); exit;
}
/* 삭제 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'delete') {
  jw_csrf_check();
  jw_delete((string)($_POST['id'] ?? ''));
  header('Location: /jawi.php'); exit;
}

$list = jw_list();

/* 기록이 하나뿐이면 굳이 목록을 보여줄 이유가 없으니 바로 그 기록으로 들어갑니다.
   (?stay=1 을 붙이면 목록에 머무릅니다) */
if (count($list) === 1 && !isset($_GET['stay']) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
  $only = (string)($list[0]['id'] ?? '');
  if ($only !== '') { header('Location: /jawi_chat.php?id=' . urlencode($only)); exit; }
}
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>자위소방대 교육·훈련 기록부 — TWORIX</title>
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

/* ── 계정 아이콘 (건물관리 · 결제 · 알림 · 프로필) — 다른 페이지와 같은 모양 ── */
.nw-icons{display:flex;align-items:center;gap:6px}
.nw-icobtn{position:relative;display:flex;align-items:center;justify-content:center;
  width:38px;height:38px;border-radius:10px;border:1px solid transparent;background:transparent;
  color:var(--mut2);cursor:pointer;font-family:inherit;transition:.14s;text-decoration:none}
.nw-icobtn:hover{background:var(--bg);border-color:var(--bd)}
.nw-icobtn svg{width:19px;height:19px}
.nw-dot{position:absolute;top:7px;right:7px;width:7px;height:7px;border-radius:50%;
  background:#ef4444;border:1.5px solid #fff}
.nw-profile{position:relative}
.nw-avatar{width:36px;height:36px;border-radius:50%;border:0;cursor:pointer;font-family:inherit;
  background:linear-gradient(135deg,var(--brand),var(--accent));color:#fff;font-size:13px;font-weight:800;
  display:flex;align-items:center;justify-content:center;transition:.14s}
.nw-avatar:hover{filter:brightness(1.06)}
.nw-avatar.admin{background:linear-gradient(135deg,#f59e0b,#ea580c)}
.nw-pop{position:absolute;top:calc(100% + 10px);right:0;width:220px;background:var(--card);
  border:1px solid var(--bd);border-radius:14px;box-shadow:0 14px 34px rgba(16,24,38,.14);
  padding:8px;z-index:90;display:none}
.nw-pop.show{display:block}
.nw-pop__head{padding:11px 12px 12px;border-bottom:1px solid var(--bd)}
.nw-pop__name{font-size:14px;font-weight:800;color:var(--fg)}
.nw-pop__sub{font-size:11.5px;color:var(--mut);margin-top:2px}
.nw-pop__list{padding:6px 0 0}
.nw-pop__item{display:flex;align-items:center;gap:10px;width:100%;padding:9px 10px;border-radius:9px;
  border:0;background:transparent;color:var(--fg);font-size:13px;font-weight:600;font-family:inherit;
  cursor:pointer;text-align:left;text-decoration:none}
.nw-pop__item:hover{background:var(--bg)}
.nw-pop__item svg{width:16px;height:16px;color:var(--mut2);flex-shrink:0}
.nw-pop__item--danger{color:#dc2626}
.nw-pop__item--danger svg{color:#dc2626}
.nw-pop__div{height:1px;background:var(--bd);margin:6px 2px}
@media(max-width:680px){ .nw-pop{right:-8px} }
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
    <a class="brand" href="/index.php">소방계획서.com</a>
    <div class="nav__r">
      <div class="nw-icons">
        <a class="nw-icobtn" href="/building_manager.php" title="건물 관리">
          <svg viewBox="0 0 24 24" fill="none"><path d="M4 21V5a1 1 0 011-1h8a1 1 0 011 1v16" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M14 10h5a1 1 0 011 1v10" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M7 8h1M11 8h1M7 12h1M11 12h1M7 16h1M11 16h1M17 14h1M17 18h1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        </a>
        <a class="nw-icobtn" href="/subscribe_page.php" title="결제·구독">
          <svg viewBox="0 0 24 24" fill="none"><path d="M3 7a2 2 0 012-2h13a2 2 0 012 2v2H3V7z" stroke="currentColor" stroke-width="1.8"/><path d="M3 9v8a2 2 0 002 2h13a2 2 0 002-2V9" stroke="currentColor" stroke-width="1.8"/><path d="M16 14h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        </a>
        <a class="nw-icobtn" href="/notifications.php" title="알림">
          <svg viewBox="0 0 24 24" fill="none"><path d="M6 9a6 6 0 1112 0c0 4 1.5 5.5 1.5 5.5H4.5S6 13 6 9z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M10 18a2 2 0 004 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
          <?php if ($unreadCount > 0): ?><span class="nw-dot"></span><?php endif; ?>
        </a>
        <div class="nw-profile" id="navProfile">
          <button type="button" class="nw-avatar<?= is_admin() ? ' admin' : '' ?>" id="navAvatarBtn"
            onclick="document.getElementById('navPop').classList.toggle('show')">
            <?=h(mb_substr($nick, 0, 1))?>
          </button>
          <div class="nw-pop" id="navPop">
            <div class="nw-pop__head">
              <div class="nw-pop__name"><?=h($nick)?>님</div>
              <div class="nw-pop__sub"><?= is_admin() ? '관리자' : '건물 소방안전관리자' ?></div>
            </div>
            <div class="nw-pop__list">
              <a class="nw-pop__item" href="/building_manager.php">
                <svg viewBox="0 0 24 24" fill="none"><path d="M4 21V5a1 1 0 011-1h8a1 1 0 011 1v16" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M14 10h5a1 1 0 011 1v10" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                건물 관리
              </a>
              <a class="nw-pop__item" href="/settings.php">
                <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M19.4 15a1.7 1.7 0 00.34 1.87l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.7 1.7 0 00-1.87-.34 1.7 1.7 0 00-1 1.55V21a2 2 0 11-4 0v-.09a1.7 1.7 0 00-1-1.56 1.7 1.7 0 00-1.87.34l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.7 1.7 0 00.34-1.87 1.7 1.7 0 00-1.55-1H3a2 2 0 110-4h.09a1.7 1.7 0 001.55-1 1.7 1.7 0 00-.34-1.87l-.06-.06a2 2 0 112.83-2.83l.06.06a1.7 1.7 0 001.87.34H9a1.7 1.7 0 001-1.55V3a2 2 0 114 0v.09a1.7 1.7 0 001 1.55 1.7 1.7 0 001.87-.34l.06-.06a2 2 0 112.83 2.83l-.06.06a1.7 1.7 0 00-.34 1.87V9a1.7 1.7 0 001.55 1H21a2 2 0 110 4h-.09a1.7 1.7 0 00-1.55 1z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
                내 정보
              </a>
              <a class="nw-pop__item" href="/subscribe_page.php">
                <svg viewBox="0 0 24 24" fill="none"><path d="M3 7a2 2 0 012-2h13a2 2 0 012 2v2H3V7z" stroke="currentColor" stroke-width="1.8"/><path d="M3 9v8a2 2 0 002 2h13a2 2 0 002-2V9" stroke="currentColor" stroke-width="1.8"/></svg>
                결제·구독
              </a>
              <a class="nw-pop__item" href="/notifications.php">
                <svg viewBox="0 0 24 24" fill="none"><path d="M6 9a6 6 0 1112 0c0 4 1.5 5.5 1.5 5.5H4.5S6 13 6 9z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                알림
              </a>
              <div class="nw-pop__div"></div>
              <a class="nw-pop__item nw-pop__item--danger" href="/?logout=1"
                 onclick="return confirm('로그아웃할까요?');">
                <svg viewBox="0 0 24 24" fill="none"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 17l5-5-5-5M21 12H9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                로그아웃
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</nav>

<script>
  /* 프로필 드롭다운: 바깥을 누르면 닫힘 */
  document.addEventListener('click', function(e){
    var wrap = document.getElementById('navProfile');
    var pop  = document.getElementById('navPop');
    if (wrap && pop && !wrap.contains(e.target)) pop.classList.remove('show');
  });
</script>

<header class="head">
  <div class="head__in">
    <div class="crumb"><a href="/building_manager.php">건물 소방안전관리</a> › 자위소방대 교육·훈련 기록부</div>
    <div class="badge"><span></span> 별지 제13호서식</div>
    <h1>자위소방대 및 초기대응체계 교육·훈련 실시 결과 기록부</h1>
    <p>실시한 소방훈련·교육의 결과를 법정서식으로 기록하고 보관합니다.</p>
  </div>
</header>

<main class="wrap">
  <div class="info">
    <div>ℹ️</div>
    <div>
      자위소방대와 초기대응체계 대원에게 교육·훈련을 실시하면 그 결과를 이 기록부에 남깁니다.
      실시하지 않거나 기록하지 않으면 과태료가 부과될 수 있습니다.
      <span style="color:var(--mut2)">(화재예방법 시행규칙 별지 제13호서식)</span>
    </div>
  </div>

  <div class="card">
    <div class="toolbar">
      <h2>기록부 목록</h2>
      <a class="btn btn--primary" href="/jawi_chat.php">💬 문답으로 작성하기</a>
      <a class="btn" href="/jawi.php?new=1">+ 표로 작성</a>
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
            <a href="/jawi_edit.php?id=<?=h($r['id'])?>"><?=h($r['title'] ?: '(대상명 미입력)')?></a>
            <?php if (!empty($r['edu_date'])): ?><span class="tag"><?=h($r['edu_date'])?></span><?php endif; ?>
          </div>
          <div class="rec__meta">최근 수정 <?=h(substr((string)($r['updated_at'] ?? ''),0,16))?></div>
        </div>
        <a class="btn" href="/jawi_chat.php?id=<?=h($r['id'])?>">💬 문답</a>
        <a class="btn" href="/jawi_edit.php?id=<?=h($r['id'])?>">표로 수정</a>
        <a class="btn" href="/jawi_print.php?id=<?=h($r['id'])?>">🖨️ 출력</a>
        <form method="post" onsubmit="return confirm('이 기록을 삭제할까요? 되돌릴 수 없습니다.')">
          <input type="hidden" name="act" value="delete">
          <input type="hidden" name="id" value="<?=h($r['id'])?>">
          <input type="hidden" name="csrf" value="<?=h(jw_csrf())?>">
          <button class="btn btn--danger" type="submit">삭제</button>
        </form>
      </div>
    <?php endforeach; endif; ?>
  </div>
</main>

<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>
</html>
