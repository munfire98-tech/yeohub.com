<?php
// select_role.php — 카카오 로그인 사용자의 유형(대행업체/건물관리자) 선택
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) { session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']); }
session_start();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function is_admin(): bool {
  return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
function is_logged_in(): bool {
  return is_admin() || !empty($_SESSION['is_user']);
}
function role_landing(string $role): string {
  return $role === 'building' ? '/building_manager.php' : '/clients_mini.php';
}

// 카카오 로그인 사용자만 이용 (member는 가입 때 이미 선택함)
if (!is_logged_in() || empty($_SESSION['kakao_id'])) {
  header('Location: /index.php'); exit;
}

$kakaoId = (string)$_SESSION['kakao_id'];

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

$ROLES_FILE = __DIR__ . '/data/kakao_roles.json';
function load_roles(string $f): array {
  if (!file_exists($f)) return [];
  $r = @file_get_contents($f); if ($r === false || trim($r) === '') return [];
  $a = json_decode($r, true); return is_array($a) ? $a : [];
}
function save_roles(string $f, array $arr): bool {
  if (!is_dir(dirname($f))) @mkdir(dirname($f), 0775, true);
  $tmp = $f . '.tmp';
  file_put_contents($tmp, json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
  return @rename($tmp, $f);
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($CSRF, $_POST['csrf'] ?? '')) {
    $error = '잘못된 요청입니다. 새로고침 후 다시 시도하세요.';
  } else {
    $role  = ($_POST['role'] ?? '') === 'building' ? 'building' : 'agency';
    $email = trim($_POST['email'] ?? '');
    $phone = preg_replace('/[^0-9]/', '', trim($_POST['phone'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = '올바른 이메일 주소를 입력하세요. (아이디·비밀번호 찾기에 사용됩니다)';
    } elseif ($phone !== '' && !preg_match('/^01[016789][0-9]{7,8}$/', $phone)) {
      $error = '휴대폰 번호 형식이 올바르지 않습니다. (예: 01012345678)';
    } else {
      $all = load_roles($ROLES_FILE);
      // 새 형식: 객체로 저장 (role + 연락처). 기존 문자열 형식과 혼재해도 읽는 쪽에서 호환 처리
      $all[$kakaoId] = ['role' => $role, 'email' => $email, 'phone' => $phone];
      if (save_roles($ROLES_FILE, $all)) {
        $_SESSION['role'] = $role;
        header('Location: ' . role_landing($role)); exit;
      } else {
        $error = '저장에 실패했습니다. 잠시 후 다시 시도하세요.';
      }
    }
  }
}

$nick = $_SESSION['nickname'] ?? '사용자';
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>사용자 유형 선택 — TWORIX</title>
<style>
:root{
  --bg:#f5f7fb; --card:#fff; --bd:#e3e8f0; --bd2:#d4dbe6;
  --fg:#1a2436; --mut:#7a8699; --mut2:#56627a;
  --brand:#2563eb; --brand2:#1d4ed8; --accent:#0891b2;
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100vh;background:var(--bg);color:var(--fg);
  font-family:Inter,ui-sans-serif,system-ui,"Apple SD Gothic Neo",sans-serif;line-height:1.6;
  display:flex;align-items:center;justify-content:center;padding:24px}
.wrap{width:100%;max-width:480px}
.brand{text-align:center;font-weight:800;font-size:24px;letter-spacing:.5px;margin-bottom:6px}
.lead{text-align:center;color:var(--mut2);font-size:15px;margin-bottom:24px}
.lead b{color:var(--fg)}
.card{background:var(--card);border:1px solid var(--bd);border-radius:18px;padding:26px;
  box-shadow:0 20px 50px rgba(20,40,80,.08)}
.card h2{font-size:18px;font-weight:700;margin-bottom:4px}
.card .sub{color:var(--mut2);font-size:13px;margin-bottom:18px}
.roles{display:flex;flex-direction:column;gap:12px;margin-bottom:20px}
.role{display:flex;align-items:flex-start;gap:12px;border:1.5px solid var(--bd2);border-radius:12px;
  padding:16px;cursor:pointer;transition:.15s}
.role:hover{border-color:var(--brand)}
.role input{margin-top:3px}
.role .t{font-weight:700;font-size:15px}
.role .d{color:var(--mut2);font-size:13px}
.role:has(input:checked){border-color:var(--brand);background:#f0f5ff}
.contact{display:flex;flex-direction:column;gap:6px;margin-bottom:20px}
.clabel{font-size:12px;color:var(--mut2);font-weight:700;margin-top:6px}
.cinp{padding:12px 13px;border:1px solid var(--bd2);border-radius:10px;font-size:14px;font-family:inherit;background:#f8fafc}
.cinp:focus{outline:none;border-color:var(--brand);background:#fff}
button{width:100%;background:var(--brand);color:#fff;border:0;border-radius:11px;
  padding:13px;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit;transition:.15s}
button:hover{background:var(--brand2)}
.err{background:#fff7ed;border:1px solid #fed7aa;color:#c2410c;border-radius:9px;padding:10px 12px;font-size:13px;margin-bottom:14px}
.out{display:block;text-align:center;margin-top:16px;color:var(--mut);font-size:12px}
.out a{color:var(--mut2)}
</style>
</head>
<body>
<div class="wrap">
  <div class="brand">TWORIX</div>
  <p class="lead"><b><?=h($nick)?></b>님, 어떤 유형으로 이용하시나요?<br>한 번만 선택하면 다음부터 자동으로 연결됩니다.</p>
  <div class="card">
    <h2>사용자 유형 선택</h2>
    <div class="sub">선택에 따라 보여지는 화면이 달라집니다.</div>
    <?php if ($error): ?><div class="err"><?=h($error)?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
      <div class="roles">
        <label class="role">
          <input type="radio" name="role" value="building" checked>
          <span>
            <span class="t">건물 소방안전관리자</span><br>
            <span class="d">건물의 소방계획서·업무 수행 기록 등을 관리합니다.</span>
          </span>
        </label>
        <label class="role">
          <input type="radio" name="role" value="agency">
          <span>
            <span class="t">대행업체</span><br>
            <span class="d">소방 업무를 대행하는 사업자용 화면을 이용합니다.</span>
          </span>
        </label>
      </div>
      <div class="contact">
        <label class="clabel">이메일 (필수)</label>
        <input class="cinp" type="email" name="email" required placeholder="아이디·비밀번호 찾기에 사용됩니다" value="<?=h($_POST['email'] ?? '')?>">
        <label class="clabel">휴대폰 번호 (선택)</label>
        <input class="cinp" type="tel" name="phone" placeholder="01012345678 (숫자만)" value="<?=h($_POST['phone'] ?? '')?>">
      </div>
      <button type="submit">선택 완료</button>
    </form>
    <span class="out"><a href="/logout.php">다른 계정으로 로그인</a></span>
  </div>
</div>
</body>
</html>
