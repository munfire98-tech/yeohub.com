<?php
// fire_plan_new.php — 새 계획서: 용도 선택
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

require_once __DIR__ . '/fire_plan_db.php';
$nick   = $_SESSION['nickname'] ?? '사용자';
$usages = fp_usages();

/* 용도 선택 → 계획서 생성 → 위저드로 이동 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  fp_csrf_check();
  $code = (string)($_POST['usage'] ?? '');
  if (isset($usages[$code])) {
    $newId = fp_create_plan($code);
    header('Location: /fire_plan_edit.php?id='.$newId.'&s=1.1');
    exit;
  }
}
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>새 소방계획서 — TWORIX</title>
<style>
:root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--bd2:#d4dbe6;--fg:#1a2436;--mut:#7a8699;--mut2:#56627a;--brand:#2563eb;--brand2:#1d4ed8;--accent:#0891b2}
*{box-sizing:border-box;margin:0;padding:0}
html,body{background:var(--bg);color:var(--fg);font-family:Inter,ui-sans-serif,system-ui,"Apple SD Gothic Neo",sans-serif;line-height:1.6}
a{text-decoration:none}
.nav{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.9);backdrop-filter:blur(12px);border-bottom:1px solid var(--bd)}
.nav__inner{max-width:1120px;margin:0 auto;padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;gap:16px}
.nav__brand{font-weight:800;font-size:22px;color:var(--fg);letter-spacing:.5px}
.nav__right{display:flex;align-items:center;gap:12px;font-size:14px;color:var(--mut2)}
.btn{display:inline-flex;align-items:center;padding:8px 16px;border-radius:9px;border:1px solid var(--bd2);background:#fff;color:var(--fg);font-size:13px;font-weight:600}
.btn:hover{border-color:var(--brand);color:var(--brand2)}
.wrap{max-width:1120px;margin:0 auto;padding:40px 24px 80px}
.crumb{font-size:13px;color:var(--mut2);margin-bottom:18px}
.crumb a{color:var(--mut2)}.crumb a:hover{color:var(--brand2)}
h1{font-size:26px;font-weight:700;letter-spacing:-.5px;margin-bottom:6px}
p.sub{color:var(--mut2);font-size:14.5px;margin-bottom:26px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px}
.ucard{background:var(--card);border:1.5px solid var(--bd);border-radius:14px;padding:18px 16px;cursor:pointer;transition:.15s;text-align:left;font-family:inherit;width:100%}
.ucard:hover{border-color:var(--brand);box-shadow:0 6px 18px rgba(37,99,235,.12);transform:translateY(-2px)}
.ucard .cat{font-size:11.5px;color:var(--accent);font-weight:700}
.ucard .nm{font-size:16.5px;font-weight:700;margin-top:2px;color:var(--fg)}
.ucard .ex{font-size:12px;color:var(--mut);margin-top:6px}
</style>
</head>
<body>

<nav class="nav">
  <div class="nav__inner">
    <a class="nav__brand" href="/index.php">TWORIX</a>
    <div class="nav__right">
      <span><?=h($nick)?>님</span>
      <a class="btn" href="/fire_plan.php">← 목록</a>
    </div>
  </div>
</nav>

<main class="wrap">
  <div class="crumb"><a href="/building_manager.php">건물 소방안전관리</a> › <a href="/fire_plan.php">소방계획서</a> › 새 계획서</div>
  <h1>어떤 용도의 건축물인가요?</h1>
  <p class="sub">용도를 선택하면 해당 서식으로 작성이 시작됩니다. 공통 항목은 한 번만 입력하면 전체 서식에 자동 반영됩니다.</p>

  <form method="post" id="usageForm">
    <input type="hidden" name="csrf" value="<?=h(fp_csrf())?>">
    <input type="hidden" name="usage" id="usageInput">
    <div class="grid">
      <?php foreach ($usages as $code => $u): ?>
        <button type="button" class="ucard" onclick="pick('<?=h($code)?>')">
          <div class="cat"><?=h($u['cat'])?></div>
          <div class="nm"><?=h($u['nm'])?></div>
          <div class="ex"><?=h($u['ex'])?></div>
        </button>
      <?php endforeach; ?>
    </div>
  </form>
</main>

<script>
function pick(code){
  document.getElementById('usageInput').value = code;
  document.getElementById('usageForm').submit();
}
</script>
<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>
</html>
