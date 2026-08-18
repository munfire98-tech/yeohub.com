<?php
/* =============================================================
   whoami.php — 진단 화면
   ─────────────────────────────────────────────────────────────
   1) 로그인이 세션에 어떤 이름으로 회원 아이디를 넣는지 확인
   2) 예전 kakao_guest 공용 폴더에 데이터가 섞여 있는지 점검

   확인이 끝나면 이 파일은 서버에서 지우세요.
   ============================================================= */
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) { session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']); }
session_start();

require_once __DIR__ . '/user_key.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function is_admin(): bool {
  return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
function is_logged_in(): bool { return is_admin() || !empty($_SESSION['is_user']); }
if (!is_logged_in()) { header('Location: /index.php'); exit; }

$dbg = app_user_key_debug();
$key = $dbg['key'];

/* 값이 그대로 보이면 곤란한 것들은 가려서 보여준다 */
function mask(string $v): string {
  $n = strlen($v);
  if ($n <= 4) return str_repeat('•', $n);
  return substr($v, 0, 2) . str_repeat('•', max(2, $n - 4)) . substr($v, -2);
}

/* 예전 공용 폴더 점검 */
$buckets = [];
foreach ([['건물 기본정보','data/building'], ['업무수행 기록표','data/worklog'], ['자위소방대','data/fireplan']] as [$label, $rel]) {
  $dir = __DIR__ . '/' . $rel;
  $row = ['label'=>$label, 'rel'=>$rel, 'guest'=>false, 'guestFiles'=>0, 'users'=>0];
  if (is_dir($dir)) {
    foreach (scandir($dir) ?: [] as $e) {
      if ($e === '.' || $e === '..') continue;
      if (!is_dir($dir . '/' . $e)) continue;
      $row['users']++;
      if ($e === 'kakao_guest' || $e === 'guest') {
        $row['guest'] = true;
        $files = glob($dir . '/' . $e . '/*') ?: [];
        $row['guestFiles'] = count($files);
      }
    }
  }
  $buckets[] = $row;
}
$anyGuest = false;
foreach ($buckets as $b) if ($b['guest'] && $b['guestFiles'] > 0) $anyGuest = true;
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>진단 — TWORIX</title>
<style>
:root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--bd2:#d4dbe6;--fg:#1a2436;
  --mut:#7a8699;--mut2:#56627a;--brand:#2563eb;--brand2:#1d4ed8}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--fg);line-height:1.65;
  font-family:Inter,system-ui,"Apple SD Gothic Neo",sans-serif}
.wrap{max-width:760px;margin:0 auto;padding:36px 20px 80px}
h1{font-size:25px;font-weight:800;letter-spacing:-.3px}
.lead{color:var(--mut2);font-size:14.5px;margin-top:8px}
.box{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:20px;margin-top:18px}
.box h2{font-size:16px;font-weight:800;margin-bottom:12px}
.mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:13px}
.big{font-size:22px;font-weight:800;font-family:ui-monospace,monospace;letter-spacing:-.5px}
.ok{color:#15803d}.bad{color:#dc2626}.warn{color:#b45309}
.row{display:flex;justify-content:space-between;gap:14px;padding:9px 0;
  border-top:1px solid var(--bd);font-size:14px;flex-wrap:wrap}
.row:first-of-type{border-top:0}
.k{color:var(--mut2);font-size:13px}
.pill{display:inline-block;font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px}
.pill--ok{background:#f0fdf4;color:#15803d}
.pill--bad{background:#fef2f2;color:#dc2626}
.pill--warn{background:#fff7ed;color:#b45309}
.alert{display:flex;gap:11px;border-radius:12px;padding:15px 17px;font-size:14px;line-height:1.7;margin-top:18px}
.alert--bad{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
.alert--ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}
.alert--warn{background:#fff7ed;border:1px solid #fed7aa;color:#92400e}
.alert b{display:block;margin-bottom:3px}
code{background:#eef2f7;padding:2px 6px;border-radius:5px;font-size:13px;
  font-family:ui-monospace,monospace}
.tags{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
.tag{font-size:12px;background:#eef2f7;color:var(--mut2);padding:3px 9px;
  border-radius:6px;font-family:ui-monospace,monospace}
</style>
</head>
<body>
<div class="wrap">

  <h1>로그인 · 데이터 격리 진단</h1>
  <p class="lead">회원마다 데이터가 제대로 나뉘는지 확인합니다. 확인이 끝나면 이 파일은 서버에서 지우세요.</p>

  <div class="box">
    <h2>1. 지금 내 회원 구분 키</h2>
    <?php if ($key !== ''): ?>
      <p class="big ok"><?=h($key)?></p>
      <p class="lead">이 이름으로 <code>data/building/<?=h($key)?>/</code> 에 저장됩니다.</p>
    <?php else: ?>
      <p class="big bad">찾지 못함</p>
      <p class="lead">로그인이 회원 아이디를 세션에 넣지 않았거나, 제가 찾는 이름과 다릅니다.</p>
    <?php endif; ?>

    <?php if ($dbg['matched']): ?>
      <div style="margin-top:14px">
        <div class="k">찾은 키</div>
        <?php foreach ($dbg['matched'] as $k => $v): ?>
          <div class="row">
            <span class="mono"><?=h($k)?></span>
            <span class="mono"><?=h(mask($v))?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div style="margin-top:16px">
      <div class="k">세션에 들어 있는 이름 전체</div>
      <div class="tags">
        <?php foreach ($dbg['all'] as $k): ?><span class="tag"><?=h($k)?></span><?php endforeach; ?>
      </div>
    </div>
  </div>

  <?php if ($key === ''): ?>
    <div class="alert alert--bad">
      <div>✕</div>
      <div>
        <b>회원을 특정하지 못했습니다</b>
        위 “세션에 들어 있는 이름 전체”에서 회원 아이디로 보이는 것을 찾아,
        <code>user_key.php</code> 의 <code>UK_SESSION_KEYS</code> 맨 앞에 그 이름을 추가하세요.
        예를 들어 <code>'mem_no'</code> 였다면 이렇게요.
        <div class="mono" style="margin-top:8px;background:#fff;padding:10px 12px;border-radius:8px">
          const UK_SESSION_KEYS = ['mem_no', 'member_id', 'uid', ...];
        </div>
      </div>
    </div>
  <?php else: ?>
    <div class="alert alert--ok">
      <div>✓</div>
      <div><b>정상입니다</b>이 계정은 자기 폴더에 저장됩니다. 다른 계정으로도 로그인해
        이 값이 <b>서로 다르게</b> 나오는지 한 번 더 확인해 보세요.</div>
    </div>
  <?php endif; ?>

  <div class="box">
    <h2>2. 예전 공용 폴더 점검</h2>
    <p class="lead" style="margin-bottom:12px">
      수정 전에는 회원을 특정하지 못하면 모두 <code>kakao_guest</code> 로 묶였습니다.
      그 폴더에 데이터가 있다면 여러 회원의 내용이 섞여 있을 수 있습니다.</p>

    <?php foreach ($buckets as $b): ?>
      <div class="row">
        <span><?=h($b['label'])?> <span class="k mono"><?=h($b['rel'])?></span></span>
        <span>
          <?php if ($b['guest'] && $b['guestFiles'] > 0): ?>
            <span class="pill pill--bad">kakao_guest 에 파일 <?=$b['guestFiles']?>개</span>
          <?php elseif ($b['guest']): ?>
            <span class="pill pill--warn">kakao_guest 폴더만 있음(비어 있음)</span>
          <?php else: ?>
            <span class="pill pill--ok">깨끗함</span>
          <?php endif; ?>
          <span class="k">폴더 <?=$b['users']?>개</span>
        </span>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($anyGuest): ?>
    <div class="alert alert--warn">
      <div>⚠️</div>
      <div>
        <b>섞인 데이터가 남아 있습니다</b>
        <code>kakao_guest</code> 폴더 안의 내용이 누구 것인지 확인하세요.
        수정본을 올린 뒤에는 아무도 이 폴더를 쓰지 않으므로, 주인을 찾으면 해당 회원 폴더로 옮기고
        폴더는 지우시면 됩니다. 주인을 알 수 없으면 그냥 지우고 회원에게 다시 입력받는 편이 안전합니다.
        <div class="mono" style="margin-top:8px;background:#fff;padding:10px 12px;border-radius:8px">
          data/building/kakao_guest/<br>
          data/worklog/kakao_guest/<br>
          data/fireplan/kakao_guest/
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="box">
    <h2>3. 기본정보 채움 정도</h2>
    <?php
      $ok = false;
      if (is_file(__DIR__ . '/building_info.php')) {
        require_once __DIR__ . '/building_info.php';
        if (function_exists('bi_progress')) { $p = bi_progress(); $ok = true; }
      }
    ?>
    <?php if ($ok): ?>
      <p class="big"><?=$p['percent']?>%
        <span class="k" style="font-size:14px;font-weight:400"><?=$p['filled']?>/<?=$p['total']?> 항목</span></p>
      <?php if ($p['missing']): ?>
        <div style="margin-top:10px">
          <div class="k">아직 비어 있는 항목</div>
          <div class="tags"><?php foreach ($p['missing'] as $m): ?><span class="tag"><?=h($m)?></span><?php endforeach; ?></div>
        </div>
      <?php else: ?>
        <p class="lead ok">모두 입력되었습니다.</p>
      <?php endif; ?>
    <?php else: ?>
      <p class="lead">수정본 <code>building_info.php</code> 를 아직 올리지 않으셨습니다.</p>
    <?php endif; ?>
  </div>

  <p class="lead" style="margin-top:22px">
    <a href="/building_manager.php" style="color:var(--brand2);font-weight:600">← 건물 소방안전관리로 돌아가기</a>
  </p>
</div>
<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>
</html>
