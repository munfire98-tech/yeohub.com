<?php
/**
 * _header.php — 공통 상단 (스타일 + 글로벌 네비게이션)
 * 각 페이지 맨 위에서 다음처럼 불러 씁니다:
 *
 *   <?php
 *     session_start();                 // 세션이 필요하면
 *     $PAGE_TITLE = 'FAQ';            // 탭 제목 (생략 가능)
 *     $ACTIVE     = 'faq';            // 현재 메뉴 강조 (faq|service|blog)
 *     require __DIR__ . '/_header.php';
 *   ?>
 *
 * 닫을 때는 require __DIR__ . '/_footer.php'; 를 호출합니다.
 */

if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
}
$PAGE_TITLE = $PAGE_TITLE ?? '';
$ACTIVE     = $ACTIVE ?? '';
$NAV = [
  'faq'     => ['label' => 'FAQ',    'href' => '/faq.php'],
  'service' => ['label' => '서비스', 'href' => '/service.php'],
  'blog'    => ['label' => '블로그', 'href' => '/blog.php'],
  'ar'      => ['label' => '피난시뮬레이터',     'href' => '/ar.php'],
];
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $PAGE_TITLE !== '' ? h($PAGE_TITLE) . ' — 소방계획서.com' : '소방계획서.com — 소방안전관리' ?></title>
<style>
:root{
  --bg:#f5f7fb; --bg2:#eef2f8; --card:#ffffff; --card2:#ffffff;
  --bd:#e3e8f0; --bd2:#d4dbe6;
  --fg:#1a2436; --mut:#7a8699; --mut2:#56627a;
  --brand:#2563eb; --brand2:#1d4ed8;
  --accent:#0891b2;
  --ok:#16a34a; --warn:#d97706; --danger:#dc2626;
  --fire:#ea580c;
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{background:var(--bg);color:var(--fg);font-family:Inter,ui-sans-serif,system-ui,"Apple SD Gothic Neo",sans-serif;line-height:1.6}
a{color:var(--brand2);text-decoration:none}
a:hover{color:#1e40af}
.nav{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.85);backdrop-filter:blur(12px) saturate(150%);border-bottom:1px solid var(--bd)}
.nav__inner{max-width:1120px;margin:0 auto;padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;gap:16px}
.nav__brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:22px;color:var(--fg);letter-spacing:.5px}
.nav__links{display:flex;align-items:center;gap:28px;list-style:none;margin:0 auto}
.nav__links a{color:var(--fg);font-size:16px;font-weight:600;transition:.15s}
.nav__links a:hover{color:var(--brand2)}
.nav__links a.active{color:var(--brand2)}
.nav__actions{display:flex;gap:8px;align-items:center}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:9px;border:1px solid var(--bd2);background:var(--card);color:var(--fg);font-size:13px;cursor:pointer;transition:.15s;text-decoration:none;font-family:inherit}
.btn:hover{border-color:var(--brand);background:#f0f5ff;color:var(--brand2)}
.btn--primary{background:var(--brand);border-color:var(--brand);color:#fff;font-weight:600}
.btn--primary:hover{background:var(--brand2);border-color:var(--brand2);color:#fff}
.btn--ghost{background:transparent;border-color:transparent;color:var(--mut2)}
.btn--ghost:hover{background:#eef2f8;border-color:var(--bd);color:var(--fg)}

/* 보고서 느낌 페이지 헤더 */
.page-head{position:relative;overflow:hidden;border-bottom:1px solid var(--bd);
  background:
    linear-gradient(rgba(37,99,235,.04) 1px,transparent 1px) 0 0/100% 28px,
    linear-gradient(90deg,rgba(37,99,235,.04) 1px,transparent 1px) 0 0/28px 100%,
    linear-gradient(180deg,#fbfcff,#eef3fb)}
.page-head::before{content:'';position:absolute;inset:0;pointer-events:none;
  background:radial-gradient(ellipse 760px 320px at 12% 0%,rgba(8,145,178,.10),transparent 70%)}
.page-head__inner{position:relative;max-width:1120px;margin:0 auto;padding:56px 24px 44px}
.page-head__label{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;border:1px solid var(--bd2);background:var(--card);color:var(--mut2);font-size:12px;margin-bottom:16px}
.page-head__label span{width:6px;height:6px;border-radius:50%;background:var(--accent);display:inline-block}
.page-head h1{font-size:clamp(26px,4vw,38px);font-weight:700;letter-spacing:-.5px;line-height:1.2;margin-bottom:10px}
.page-head p{color:var(--mut2);font-size:16px;max-width:560px}

/* 본문 공통 */
.wrap{max-width:1120px;margin:0 auto;padding:40px 24px 80px}
.card{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:22px 24px;margin-bottom:14px}
.card h3{font-size:18px;font-weight:700;margin-bottom:8px}
.card p{color:var(--mut2);font-size:15px}
.muted{color:var(--mut)}

footer{border-top:1px solid var(--bd);padding:28px 24px;color:var(--mut);font-size:13px;line-height:1.8}
footer .inner{max-width:1120px;margin:0 auto}
footer a{color:var(--mut2)}
footer a:hover{color:var(--fg)}
.nav__toggle{display:none;background:none;border:0;cursor:pointer;padding:8px;font-size:22px;line-height:1;color:var(--fg)}
@media(max-width:680px){
  .nav__inner{padding:0 16px}
  .nav__toggle{display:block}
  .nav__links{
    position:absolute;top:56px;left:0;right:0;margin:0;
    flex-direction:column;align-items:stretch;gap:0;
    background:#fff;border-bottom:1px solid var(--bd);
    box-shadow:0 12px 24px rgba(20,40,80,.08);
    display:none;
  }
  .nav__links.open{display:flex}
  .nav__links li{width:100%}
  .nav__links a{display:block;padding:14px 20px;border-top:1px solid var(--bd)}
  .nav__links li:first-child a{border-top:0}
  .page-head__inner{padding:40px 20px 32px}
}
</style>
</head>
<body>

<!-- NAV -->
<nav class="nav">
  <div class="nav__inner">
    <a class="nav__brand" href="/index.php">소방계획서.com</a>
    <ul class="nav__links" id="navLinks">
      <?php foreach ($NAV as $key => $item): ?>
        <li><a href="<?=h($item['href'])?>"<?= $ACTIVE === $key ? ' class="active"' : '' ?>><?=h($item['label'])?></a></li>
      <?php endforeach; ?>
    </ul>
    <div class="nav__actions">
      <a class="btn btn--primary" href="/index.php">로그인</a>
      <button class="nav__toggle" id="navToggle" aria-label="메뉴 열기" aria-expanded="false">☰</button>
    </div>
  </div>
</nav>
<script>
  (function(){
    var t = document.getElementById('navToggle');
    var m = document.getElementById('navLinks');
    if (t && m) {
      t.addEventListener('click', function(){
        var open = m.classList.toggle('open');
        t.setAttribute('aria-expanded', open ? 'true' : 'false');
        t.textContent = open ? '✕' : '☰';
      });
    }
  })();
</script>
