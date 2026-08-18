<?php
/* =============================================================
   notifications.php — 알림 (틀)
   ─────────────────────────────────────────────────────────────
   상단 네비 종 아이콘에서 들어옵니다. 지금은 틀만 있고, 실제 알림을
   쌓는 로직(점검일 임박, 결제 실패, 관리자 확인완료 등)은 나중에
   각 기능에서 notif_push() 를 호출하도록 붙이면 됩니다.

   저장 위치: data/notifications/{회원키}.json
   ============================================================= */
declare(strict_types=1);
ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) { session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']); }
session_start();

if (is_file(__DIR__ . '/user_key.php')) require_once __DIR__ . '/user_key.php';
function is_admin(): bool {
  return (!empty($_SESSION['is_admin'])) || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
function is_logged_in(): bool { return is_admin() || !empty($_SESSION['is_user']); }
if (!is_logged_in()) { header('Location: /index.php'); exit; }

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function notif_uid(): string { return function_exists('app_user_key') ? app_user_key() : ''; }
function notif_file(): string {
  $uid = notif_uid();
  if ($uid === '') return '';
  $dir = __DIR__ . '/data/notifications';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  return $dir . '/' . $uid . '.json';
}
function notif_read(): array {
  $f = notif_file();
  if ($f === '' || !is_file($f)) return [];
  $r = json_decode((string)@file_get_contents($f), true);
  return is_array($r) ? $r : [];
}
function notif_write(array $list): bool {
  $f = notif_file();
  if ($f === '') return false;
  $tmp = $f . '.tmp';
  if (file_put_contents($tmp, json_encode($list, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
  return @rename($tmp, $f);
}
/** 다른 페이지에서 알림을 쌓을 때 쓰는 함수.
 *  예) notif_push($uid, 'D-DAY 임박', '○○빌딩 점검일이 3일 남았습니다.', '/work_log.php'); */
function notif_push(string $uid, string $title, string $body = '', string $link = ''): bool {
  if ($uid === '') return false;
  $dir = __DIR__ . '/data/notifications';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  $f = $dir . '/' . $uid . '.json';
  $list = is_file($f) ? (json_decode((string)@file_get_contents($f), true) ?: []) : [];
  array_unshift($list, [
    'id' => bin2hex(random_bytes(8)), 'title' => $title, 'body' => $body, 'link' => $link,
    'read' => false, 'at' => date('Y-m-d H:i:s'),
  ]);
  $list = array_slice($list, 0, 100);   // 최근 100개만 보관
  $tmp = $f . '.tmp';
  if (file_put_contents($tmp, json_encode($list, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
  return @rename($tmp, $f);
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

/* ── 액션 ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $act = $_POST['act'] ?? '';
  if (hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) {
    if ($act === 'read_all') {
      $list = notif_read();
      foreach ($list as &$n) { $n['read'] = true; }
      unset($n);
      notif_write($list);
    } elseif ($act === 'read_one') {
      $id = (string)($_POST['id'] ?? '');
      $list = notif_read();
      foreach ($list as &$n) { if (($n['id'] ?? '') === $id) $n['read'] = true; }
      unset($n);
      notif_write($list);
    } elseif ($act === 'clear') {
      notif_write([]);
    }
  }
  header('Location: /notifications.php'); exit;
}

$items = notif_read();
$unread = 0; foreach ($items as $n) if (empty($n['read'])) $unread++;
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>알림 — YEOHUB</title>
<style>
  :root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--bd2:#d4dbe6;--fg:#1a2436;--mut:#7a8699;--mut2:#56627a;
    --brand:#2563eb;--brand2:#1d4ed8;--danger:#dc2626;--danger-soft:#fdeceb}
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:var(--bg);color:var(--fg);font-family:Inter,ui-sans-serif,system-ui,"Apple SD Gothic Neo",sans-serif;
    padding:0 0 80px}
  .nav{background:rgba(255,255,255,.9);backdrop-filter:blur(12px);border-bottom:1px solid var(--bd);
    padding:0;position:sticky;top:0;z-index:10}
  .nav__inner{max-width:720px;margin:0 auto;padding:0 20px;height:56px;display:flex;align-items:center;gap:12px}
  .nav__inner a.brand{font-weight:800;font-size:18px;color:var(--fg);text-decoration:none}
  .nav__inner .back{margin-left:auto;font-size:12.5px;color:var(--mut);text-decoration:none}
  .wrap{max-width:720px;margin:0 auto;padding:26px 20px}
  .head{display:flex;align-items:center;gap:10px;margin-bottom:18px}
  h1{font-size:20px;font-weight:800}
  .cnt{font-size:12px;font-weight:800;background:var(--danger-soft);color:var(--danger);
    border-radius:999px;padding:3px 10px}
  .actions{margin-left:auto;display:flex;gap:8px}
  .btn{border:1px solid var(--bd2);background:#fff;color:var(--mut2);border-radius:9px;
    padding:7px 13px;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit;text-decoration:none}
  .btn:hover{border-color:var(--brand);color:var(--brand2)}

  .list{background:var(--card);border:1px solid var(--bd);border-radius:14px;overflow:hidden}
  .item{display:flex;gap:12px;padding:15px 16px;border-bottom:1px solid var(--bd);text-decoration:none;
    color:inherit;position:relative}
  .item:last-child{border-bottom:0}
  .item:hover{background:var(--bg)}
  .item.unread{background:#eff6ff}
  .item__dot{width:7px;height:7px;border-radius:50%;background:var(--brand);flex-shrink:0;margin-top:6px}
  .item__dot.read{background:transparent}
  .item__body{flex:1;min-width:0}
  .item__title{font-size:13.5px;font-weight:700;margin-bottom:3px}
  .item__desc{font-size:12.5px;color:var(--mut2);line-height:1.6}
  .item__time{font-size:11px;color:var(--mut);white-space:nowrap;flex-shrink:0}

  .empty{text-align:center;padding:70px 20px;color:var(--mut)}
  .empty__ico{font-size:32px;margin-bottom:12px}
  .empty h3{font-size:16px;font-weight:800;color:var(--mut2);margin-bottom:6px}
  .empty p{font-size:13px;line-height:1.7}
</style>
</head>
<body>

<div class="nav"><div class="nav__inner">
  <a class="brand" href="/index.php">YEOHUB</a>
  <a class="back" href="/building_manager.php">← 돌아가기</a>
</div></div>

<div class="wrap">
  <div class="head">
    <h1>알림</h1>
    <?php if ($unread > 0): ?><span class="cnt"><?=$unread?>개 안 읽음</span><?php endif; ?>
    <div class="actions">
      <?php if ($items): ?>
        <form method="post"><input type="hidden" name="csrf" value="<?=h($CSRF)?>">
          <input type="hidden" name="act" value="read_all">
          <button class="btn" type="submit">모두 읽음</button></form>
        <form method="post" onsubmit="return confirm('알림을 모두 지울까요?')">
          <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
          <input type="hidden" name="act" value="clear">
          <button class="btn" type="submit">전체 지우기</button></form>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!$items): ?>
    <div class="empty">
      <div class="empty__ico">🔔</div>
      <h3>아직 알림이 없습니다</h3>
      <p>점검일이 다가오거나 처리할 일이 생기면 여기로 알려드릴게요.</p>
    </div>
  <?php else: ?>
    <div class="list">
      <?php foreach ($items as $n): ?>
        <a class="item<?= empty($n['read']) ? ' unread' : '' ?>"
           href="<?= !empty($n['link']) ? h($n['link']) : '#' ?>"
           <?php if (empty($n['read'])): ?>
             onclick="fetch('/notifications.php',{method:'POST',body:new URLSearchParams({csrf:<?=json_encode($CSRF)?>,act:'read_one',id:<?=json_encode($n['id']??'')?>})})"
           <?php endif; ?>>
          <span class="item__dot<?= empty($n['read']) ? '' : ' read' ?>"></span>
          <div class="item__body">
            <div class="item__title"><?=h($n['title'] ?? '')?></div>
            <?php if (!empty($n['body'])): ?><div class="item__desc"><?=h($n['body'])?></div><?php endif; ?>
          </div>
          <span class="item__time"><?=h($n['at'] ?? '')?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

</body>
</html>
