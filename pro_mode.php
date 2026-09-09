<?php
declare(strict_types=1);
ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) { session_set_cookie_params(['httponly'=>true, 'samesite'=>'Lax']); }
session_start();
$isAdmin = !empty($_SESSION['is_admin']) || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
if (!$isAdmin && empty($_SESSION['is_user'])) { header('Location: /index.php'); exit; }
function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$embedded = ($_GET['embed'] ?? '') === '1';
$query = [];
if ($embedded) $query['embed'] = '1';
if ($isAdmin && isset($_GET['uid']) && is_string($_GET['uid']) && trim($_GET['uid']) !== '') {
    $query['uid'] = trim($_GET['uid']);
}
$subscribeUrl = '/subscribe_page.php' . ($query ? '?' . http_build_query($query) : '');
$mainQuery = $query;
unset($mainQuery['embed']);
$mainUrl = '/building_manager.php' . ($mainQuery ? '?' . http_build_query($mainQuery) : '');
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PRO 업무 모드</title>
  <style>
    *{box-sizing:border-box}
    body{margin:0;background:#fff;color:#303746;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Noto Sans KR",sans-serif;line-height:1.65}
    .pro-page{max-width:820px;margin:0 auto;padding:36px 24px 40px}
    .back{display:inline-block;margin-bottom:24px;color:#64748b;font-size:12px;text-decoration:none}
    .badge{display:inline-block;padding:3px 8px;border-radius:5px;background:#f3eff9;color:#69568e;font-size:10px;font-weight:700;letter-spacing:.06em}
    h1{font-size:25px;line-height:1.4;margin:12px 0 8px;letter-spacing:-.04em}
    .intro{margin:0;color:#697382;font-size:14px}
    .features{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:28px 0}
    .feature{border:1px solid #e6e8ee;border-radius:10px;padding:20px}
    .number{color:#8a7b9e;font-size:10px;font-weight:600}
    h2{font-size:14px;margin:8px 0 6px;letter-spacing:-.02em}
    .feature p{margin:0;color:#697382;font-size:12px;overflow-wrap:anywhere}
    .soon{display:inline-block;margin-left:5px;padding:1px 5px;border-radius:4px;background:#f3f4f6;color:#737b88;font-size:9px;font-weight:500;vertical-align:middle}
    .subscription{display:flex;align-items:center;justify-content:space-between;gap:20px;border-top:1px solid #eceef2;padding-top:22px}
    .subscription p{margin:0;color:#697382;font-size:12px}
    .subscription strong{display:block;margin-bottom:3px;color:#394150;font-size:14px}
    .subscribe{display:inline-flex;justify-content:center;align-items:center;gap:16px;min-height:42px;padding:10px 16px;border:1px solid #e0d9ec;border-radius:8px;background:#f3eff9;color:#69568e;font-size:12px;font-weight:600;text-decoration:none;white-space:nowrap}
    .subscribe:hover{background:#ece6f4;border-color:#cfc2e0}
    a:focus-visible{outline:2px solid #a78bfa;outline-offset:3px}
    @media(max-width:560px){.pro-page{padding:24px 18px}.features{grid-template-columns:1fr;gap:10px}.feature{padding:16px}.subscription{align-items:stretch;flex-direction:column;gap:14px}h1{font-size:22px}}
  </style>
</head>
<body>
  <main class="pro-page">
    <?php if (!$embedded): ?><a class="back" href="<?=h($mainUrl)?>">← 메인으로</a><?php endif; ?>
    <header>
      <span class="badge">PRO</span>
      <h1>필요한 업무를, 조금 더 편하게.</h1>
      <p class="intro">기본 업무에 서류 출력과 교육·피난 준비 기능을 더하는 PRO 업무 모드입니다.</p>
    </header>
    <section class="features" aria-label="PRO 확장 기능">
      <article class="feature"><span class="number">01 / PRINT</span><h2>서류 전체 인쇄</h2><p>작성한 소방안전관리 서류를 모아 한 번에 출력하세요.</p></article>
      <article class="feature"><span class="number">02 / EVACUATION</span><h2>피난 시뮬레이션</h2><p>건물의 피난 상황을 시뮬레이션으로 살펴보고 대피 준비에 활용하세요.</p></article>
      <article class="feature"><span class="number">03 / EDUCATION</span><h2>소방교육 자료</h2><p>소방교육을 준비할 때 필요한 자료를 확인하세요.</p></article>
      <article class="feature"><span class="number">04 / NOTIFICATION</span><h2>자동 업무 알림 <span class="soon">준비 중</span></h2><p>매월 업무 현황에 맞춘 안내와 자위소방대 대원별 임무 전달을 준비하고 있습니다. 카카오톡 발송은 아직 제공되지 않습니다.</p></article>
    </section>
    <footer class="subscription">
      <p><strong>내 업무에 필요한 기능인가요?</strong>요금제와 구독 상태는 구독 안내에서 확인할 수 있어요.</p>
      <a class="subscribe" href="<?=h($subscribeUrl)?>" target="_self">구독 안내 <span aria-hidden="true">→</span></a>
    </footer>
  </main>
</body>
</html>
