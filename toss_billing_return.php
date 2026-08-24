<?php
/* =============================================================
   toss_billing_return.php — 카드 등록창에서 돌아오는 자리
   ─────────────────────────────────────────────────────────────
   성공: ?customerKey=...&authKey=...   → 빌링키 발급
   실패: ?code=...&message=...          → 사유 표시
   ============================================================= */
declare(strict_types=1);

if (!ini_get('date.timezone')) date_default_timezone_set('Asia/Seoul');
ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']);
session_start();

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function is_admin(): bool {
  return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
function is_logged_in(): bool { return is_admin() || !empty($_SESSION['is_user']); }
if (!is_logged_in()) { header('Location: /index.php'); exit; }

require_once __DIR__ . '/toss_billing.php';

$ok      = false;
$title   = '';
$detail  = '';

$failCode = trim((string)($_GET['code'] ?? ''));
if ($failCode !== '') {
  /* 사용자가 취소했거나 카드 인증이 실패한 경우 */
  $title  = ($failCode === 'PAY_PROCESS_CANCELED') ? '카드 등록을 취소하셨습니다' : '카드 등록에 실패했습니다';
  $detail = (string)($_GET['message'] ?? '') ?: '다시 시도해 주세요.';
} else {
  $authKey     = trim((string)($_GET['authKey'] ?? ''));
  $customerKey = trim((string)($_GET['customerKey'] ?? ''));

  if ($authKey === '' || $customerKey === '') {
    $title  = '필요한 정보가 오지 않았습니다';
    $detail = '카드 등록을 처음부터 다시 시도해 주세요.';
  } elseif ($customerKey !== tb_customer_key()) {
    /* 내 계정의 customerKey 와 다르면 중단합니다(다른 사람 요청일 수 있음) */
    $title  = '요청이 올바르지 않습니다';
    $detail = '구매자 정보가 일치하지 않습니다. 다시 시도해 주세요.';
  } else {
    $res = tb_issue_billing_key($authKey, $customerKey);
    if ($res['ok']) {
      $ok     = true;
      $title  = '카드가 등록되었습니다';
      $card   = tb_read()['card'] ?? [];
      $detail = trim(($card['company'] ?? '') . ' ' . ($card['number'] ?? ''));
      if ($detail === '') $detail = '이제 구독을 시작하실 수 있습니다.';
    } else {
      $title  = '카드 등록에 실패했습니다';
      $detail = $res['error'];
    }
  }
}
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?=h($title)?></title>
<style>
  :root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--fg:#1a2436;--mut:#7a8699;--mut2:#56627a;--brand:#2563eb}
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:var(--bg);color:var(--fg);min-height:100vh;display:flex;align-items:center;
    justify-content:center;padding:24px;line-height:1.6;
    font-family:Inter,ui-sans-serif,system-ui,"Apple SD Gothic Neo",sans-serif}
  .box{background:var(--card);border:1px solid var(--bd);border-radius:16px;
    padding:34px 30px 26px;max-width:400px;width:100%;text-align:center;
    box-shadow:0 12px 34px rgba(16,24,38,.08)}
  .ic{width:56px;height:56px;border-radius:50%;margin:0 auto 16px;
    display:flex;align-items:center;justify-content:center;font-size:26px}
  .ic--ok{background:#eefaf1}
  .ic--no{background:#fef2f2}
  h1{font-size:18px;font-weight:800;margin-bottom:8px}
  p{font-size:13.5px;color:var(--mut2);line-height:1.75;margin-bottom:22px;word-break:break-all}
  .btn{display:block;width:100%;padding:12px;border-radius:11px;font-size:14px;font-weight:700;
    text-decoration:none;background:var(--brand);color:#fff}
  .btn:hover{filter:brightness(1.08)}
  .btn--ghost{background:#fff;border:1px solid var(--bd);color:var(--mut2);margin-top:8px}
  .test{margin-top:16px;font-size:11.5px;color:var(--mut);background:#fffbeb;
    border:1px solid #f6d8a8;border-radius:8px;padding:8px 10px;color:#92400e}
</style>
</head>
<body>
<div class="box">
  <div class="ic <?= $ok ? 'ic--ok' : 'ic--no' ?>"><?= $ok ? '✓' : '!' ?></div>
  <h1><?=h($title)?></h1>
  <p><?=h($detail)?></p>

  <a class="btn" href="/subscribe_page.php">구독 페이지로</a>
  <?php if (!$ok): ?>
    <a class="btn btn--ghost" href="/building_manager.php">나중에 할게요</a>
  <?php endif; ?>

  <?php if (!tb_is_live()): ?>
    <div class="test">테스트 모드입니다. 실제로 결제되지 않습니다.</div>
  <?php endif; ?>
</div>
</body>
</html>
