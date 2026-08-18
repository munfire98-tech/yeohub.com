<?php
/* =============================================================
   admin_login.php — 관리자 전용 로그인 (비공개 주소)
   ─────────────────────────────────────────────────────────────
   공개 화면 어디에도 이 페이지로 가는 링크가 없습니다. 주소를 아는
   사람만 들어올 수 있습니다. 로그인 처리는 index.php 의 기존 핸들러
   (action=login)를 그대로 재사용합니다 — 로직을 중복하지 않습니다.

   관리자 계정 자체를 새로 추가·삭제하려면 admin_accounts.php 를 쓰세요
   (로그인한 관리자만 접근 가능).
   ============================================================= */
declare(strict_types=1);
ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) { session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']); }
session_start();

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

/* 이미 로그인된 관리자면 바로 이동 */
$isAdmin = (!empty($_SESSION['is_admin'])) || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
if ($isAdmin) { header('Location: /building_manager.php'); exit; }

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$err = isset($_GET['err']);
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>관리자 로그인</title>
<style>
  :root{--bg:#0e0f15;--card:#15161d;--bd:#2a2c38;--inp:#20222c;--inp-bd:#2e3140;
    --fg:#f1f2f6;--mut:#8a90a3;--brand:#5b5ff2;--brand2:#4c50e0;--err:#f87171}
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:var(--bg);color:var(--fg);font-family:Inter,ui-sans-serif,system-ui,"Apple SD Gothic Neo",sans-serif;
    min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  .card{width:100%;max-width:360px;background:var(--card);border:1px solid var(--bd);
    border-radius:18px;padding:32px 28px}
  h1{font-size:19px;font-weight:800;margin-bottom:6px}
  .sub{font-size:12.5px;color:var(--mut);margin-bottom:24px}
  .field{margin-bottom:14px}
  .field label{display:block;font-size:12px;font-weight:600;color:var(--mut);margin-bottom:6px}
  .inp{width:100%;padding:12px 14px;border-radius:10px;border:1px solid var(--inp-bd);
    background:var(--inp);color:var(--fg);font-size:14px;outline:none;font-family:inherit}
  .inp::placeholder{color:#5c6072}
  .inp:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(91,95,242,.18)}
  .btn{width:100%;padding:13px 16px;border-radius:11px;border:0;background:var(--brand);
    color:#fff;font-size:14px;font-weight:700;cursor:pointer;margin-top:6px;font-family:inherit}
  .btn:hover{background:var(--brand2)}
  .err{background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.25);color:var(--err);
    border-radius:9px;padding:10px 13px;font-size:12.5px;margin-bottom:16px}
  .back{display:block;text-align:center;margin-top:18px;font-size:12.5px;color:var(--mut);text-decoration:none}
  .back:hover{color:var(--fg)}
</style>
</head>
<body>
  <div class="card">
    <h1>관리자 로그인</h1>
    <div class="sub">관리자 전용 페이지입니다</div>
    <?php if ($err): ?><div class="err">아이디 또는 비밀번호가 올바르지 않습니다.</div><?php endif; ?>
    <form method="post" action="/index.php" autocomplete="off">
      <input type="hidden" name="action" value="login">
      <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
      <input type="hidden" name="redirect_back" value="/admin_login.php?err=1">
      <div class="field">
        <label>아이디</label>
        <input class="inp" name="user" placeholder="관리자 아이디" required autocomplete="username">
      </div>
      <div class="field">
        <label>비밀번호</label>
        <input class="inp" type="password" name="pass" placeholder="비밀번호" required autocomplete="current-password">
      </div>
      <button class="btn" type="submit">로그인</button>
    </form>
    <a class="back" href="/index.php">← 메인으로</a>
  </div>
</body>
</html>
