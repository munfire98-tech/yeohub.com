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
 *
 * 로그인한 사람만 보는 내부 페이지(구독·알림 등)에서 아이콘+프로필 드롭다운
 * 네비를 쓰려면 아래처럼 $NAV_MODE 를 지정합니다:
 *
 *   $NAV_MODE = 'account';
 *   $IS_LOGGED_IN = true;
 *   $ACCOUNT_NICK = $_SESSION['nickname'] ?? '사용자';
 *   $ACCOUNT_IS_ADMIN = is_admin();
 *   require __DIR__ . '/_header.php';
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

/* ── 계정 모드 (아이콘 + 프로필 드롭다운) ── $NAV_MODE='account' 일 때 사용 */
.nw-icons{display:flex;align-items:center;gap:6px}
.nw-icobtn{position:relative;display:flex;align-items:center;justify-content:center;
  width:38px;height:38px;border-radius:10px;border:1px solid transparent;background:transparent;
  color:var(--mut2);cursor:pointer;font-family:inherit;transition:.14s;text-decoration:none}
.nw-icobtn:hover{background:var(--bg2);border-color:var(--bd)}
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
.nw-pop__item:hover{background:var(--bg2)}
.nw-pop__item svg{width:16px;height:16px;color:var(--mut2);flex-shrink:0}
.nw-pop__item--danger{color:var(--danger)}
.nw-pop__item--danger svg{color:var(--danger)}
.nw-pop__div{height:1px;background:var(--bd);margin:6px 2px}
@media(max-width:680px){ .nw-pop{right:-8px} }
</style>
</head>
<body>

<?php
  /* 계정 모드 — 로그인한 사람만 보는 내부 페이지(구독·알림 등)에서 씁니다.
     로그인 여부는 이 파일이 다시 판단하지 않고, 페이지가 이미 확인한 값을
     그대로 받습니다(판단 기준이 서로 어긋나는 걸 막기 위함).

       $NAV_MODE = 'account';
       $IS_LOGGED_IN = true;              // 이 페이지는 이미 로그인 필수라 항상 true
       $ACCOUNT_NICK = $_SESSION['nickname'] ?? '사용자';
       $ACCOUNT_IS_ADMIN = is_admin();     // 페이지에 이미 있는 함수 그대로 사용
       $ACCOUNT_CTA_HTML = '';             // (선택) 아이콘 왼쪽에 넣을 버튼
       require __DIR__ . '/_header.php';
  */
  $NAV_MODE = $NAV_MODE ?? 'default';
  if ($NAV_MODE === 'account'):
    $__isLoggedIn = $IS_LOGGED_IN ?? false;
    $__nick       = $ACCOUNT_NICK ?? ($_SESSION['nickname'] ?? '사용자');
    $__isAdmin    = $ACCOUNT_IS_ADMIN ?? false;
    $__cta        = $ACCOUNT_CTA_HTML ?? '';
    /* 안 읽은 알림 개수 — 페이지가 안 넘겨주면 여기서 직접 계산 */
    $__unread = $ACCOUNT_UNREAD ?? null;
    if ($__unread === null) {
      $__unread = 0;
      if ($__isLoggedIn && function_exists('app_user_key')) {
        $__uid = app_user_key();
        if ($__uid !== '') {
          $__nf = __DIR__ . '/data/notifications/' . $__uid . '.json';
          if (is_file($__nf)) {
            $__nl = json_decode((string)@file_get_contents($__nf), true);
            if (is_array($__nl)) { foreach ($__nl as $__n) { if (empty($__n['read'])) $__unread++; } }
          }
        }
      }
    }
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    $__csrf = $_SESSION['csrf'];
?>
<nav class="nav">
  <div class="nav__inner">
    <a class="nav__brand" href="/index.php">소방계획서.com<?php if ($__isAdmin): ?> <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;background:#fff7ed;color:#b45309">관리자</span><?php endif; ?></a>
    <?php if (!$__isLoggedIn): ?>
      <div class="nav__actions">
        <a class="btn btn--primary" href="/index.php">로그인</a>
      </div>
    <?php else: ?>
      <div class="nav__actions">
        <?php if ($__cta !== ''): ?><?=$__cta?><?php endif; ?>
        <div class="nw-icons">
          <a class="nw-icobtn" href="/subscribe_page.php" title="결제·구독">
            <svg viewBox="0 0 24 24" fill="none"><path d="M3 7a2 2 0 012-2h13a2 2 0 012 2v2H3V7z" stroke="currentColor" stroke-width="1.8"/><path d="M3 9v8a2 2 0 002 2h13a2 2 0 002-2V9" stroke="currentColor" stroke-width="1.8"/><path d="M16 14h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
          </a>
          <a class="nw-icobtn" href="/notifications.php" title="알림">
            <svg viewBox="0 0 24 24" fill="none"><path d="M6 9a6 6 0 1112 0c0 4 1.5 5.5 1.5 5.5H4.5S6 13 6 9z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M10 18a2 2 0 004 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            <?php if ($__unread > 0): ?><span class="nw-dot"></span><?php endif; ?>
          </a>
          <div class="nw-profile" id="navProfile">
            <button type="button" class="nw-avatar<?= $__isAdmin ? ' admin' : '' ?>" id="navAvatarBtn"
              onclick="document.getElementById('navPop').classList.toggle('show')">
              <?=h(mb_substr($__nick, 0, 1))?>
            </button>
            <div class="nw-pop" id="navPop">
              <div class="nw-pop__head">
                <div class="nw-pop__name"><?=h($__nick)?>님</div>
                <div class="nw-pop__sub"><?= $__isAdmin ? '관리자' : '건물 소방안전관리자' ?></div>
              </div>
              <div class="nw-pop__list">
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
                <a class="nw-pop__item nw-pop__item--danger" href="/?logout=1&csrf=<?=h($__csrf)?>"
                   onclick="return confirm('로그아웃할까요?');">
                  <svg viewBox="0 0 24 24" fill="none"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 17l5-5-5-5M21 12H9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  로그아웃
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</nav>
<script>
  document.addEventListener('click', function(e){
    var wrap = document.getElementById('navProfile');
    var pop  = document.getElementById('navPop');
    if (wrap && pop && !wrap.contains(e.target)) pop.classList.remove('show');
  });
</script>
<?php else: ?>
<!-- NAV (기본 — FAQ/서비스/블로그 링크 + 로그인 버튼) -->
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
<?php endif; ?>
