<?php
/* =============================================================
   notifications.php — 알림 (틀)
   ─────────────────────────────────────────────────────────────
   상단 네비 종 아이콘에서 들어옵니다. 지금은 틀만 있고, 실제 알림을
   쌓는 로직(점검일 임박, 결제 실패, 관리자 확인완료 등)은 나중에
   각 기능에서 notif_push() 를 호출하도록 붙이면 됩니다.

   저장 위치: data/notifications/{회원키}.json

   화면은 _header.php / _footer.php 를 그대로 씁니다 (blog.php·service.php 와 동일 구조).
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

$PAGE_TITLE = '알림';
$NAV_MODE = 'account';
$IS_LOGGED_IN = true;                          // 이 페이지는 이미 위에서 로그인 필수 처리했으므로 항상 true
$ACCOUNT_NICK = $_SESSION['nickname'] ?? '사용자';
$ACCOUNT_IS_ADMIN = is_admin();
$ACCOUNT_UNREAD = $unread;                     // 위에서 이미 계산한 값을 그대로 재사용
require __DIR__ . '/_header.php';
?>
<style>
/* 알림 페이지 전용 — service.php/blog.php 와 같은 방식: 기존 .wrap/.card/.btn 위에 최소한만 더합니다 */
.ntf-head{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px}
.ntf-cnt{font-size:12px;font-weight:800;background:#fdeceb;color:var(--danger);
  border-radius:999px;padding:3px 10px}
.ntf-actions{margin-left:auto;display:flex;gap:8px}

.ntf-list{background:var(--card);border:1px solid var(--bd);border-radius:14px;overflow:hidden}
.ntf-item{display:flex;gap:12px;padding:15px 16px;border-bottom:1px solid var(--bd);text-decoration:none;
  color:inherit;position:relative}
.ntf-item:last-child{border-bottom:0}
.ntf-item:hover{background:var(--bg2)}
.ntf-item.unread{background:#eff6ff}
.ntf-dot{width:7px;height:7px;border-radius:50%;background:var(--brand);flex-shrink:0;margin-top:6px}
.ntf-dot.read{background:transparent}
.ntf-body{flex:1;min-width:0}
.ntf-title{font-size:13.5px;font-weight:700;margin-bottom:3px;color:var(--fg)}
.ntf-desc{font-size:12.5px;color:var(--mut2);line-height:1.6}
.ntf-time{font-size:11px;color:var(--mut);white-space:nowrap;flex-shrink:0}

.ntf-empty{text-align:center;padding:60px 20px;color:var(--mut)}
.ntf-empty .ico{font-size:30px;margin-bottom:10px}
.ntf-empty h3{font-size:16px;font-weight:800;color:var(--mut2);margin-bottom:6px}
.ntf-empty p{font-size:13px;line-height:1.7}
</style>

<header class="page-head">
  <div class="page-head__inner">
    <div class="page-head__label"><span></span> 알림</div>
    <h1>알림</h1>
    <p>점검일이 다가오거나 처리할 일이 생기면 여기로 알려드립니다.</p>
  </div>
</header>

<main class="wrap">
  <div class="card">
    <div class="ntf-head">
      <?php if ($unread > 0): ?><span class="ntf-cnt"><?=$unread?>개 안 읽음</span><?php endif; ?>
      <div class="ntf-actions">
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
      <div class="ntf-empty">
        <div class="ico">🔔</div>
        <h3>아직 알림이 없습니다</h3>
        <p>점검일이 다가오거나 처리할 일이 생기면 여기로 알려드릴게요.</p>
      </div>
    <?php else: ?>
      <div class="ntf-list">
        <?php foreach ($items as $n): ?>
          <a class="ntf-item<?= empty($n['read']) ? ' unread' : '' ?>"
             href="<?= !empty($n['link']) ? h($n['link']) : '#' ?>"
             <?php if (empty($n['read'])): ?>
               onclick="fetch('/notifications.php',{method:'POST',body:new URLSearchParams({csrf:<?=json_encode($CSRF)?>,act:'read_one',id:<?=json_encode($n['id']??'')?>})})"
             <?php endif; ?>>
            <span class="ntf-dot<?= empty($n['read']) ? '' : ' read' ?>"></span>
            <div class="ntf-body">
              <div class="ntf-title"><?=h($n['title'] ?? '')?></div>
              <?php if (!empty($n['body'])): ?><div class="ntf-desc"><?=h($n['body'])?></div><?php endif; ?>
            </div>
            <span class="ntf-time"><?=h($n['at'] ?? '')?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php require __DIR__ . '/_footer.php'; ?>
