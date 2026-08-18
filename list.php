<?php
// list.php — 단일파일 통합판 (관리자 버튼/삭제 CSRF/검색 포함)
declare(strict_types=1);
/* ── 30일 로그인 유지 ── */
$SESSION_TTL = 60 * 60 * 24 * 30;
ini_set('session.cookie_httponly', '1');
ini_set('session.gc_maxlifetime', (string)$SESSION_TTL);
ini_set('session.cookie_lifetime', (string)$SESSION_TTL);
if (PHP_VERSION_ID >= 70300) {
  session_set_cookie_params([
    'lifetime' => $SESSION_TTL,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
}
session_start();

/* ─────────────────────────────────────────────
   최소 헬퍼 (inc/util.php 없이도 동작)
   ───────────────────────────────────────────── */
function is_admin(): bool {
  return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * read_posts:
 * - $dir 안의 *.json 파일을 읽어 정렬해서 반환
 * - 파일 구조 예시:
 *   {
 *     "title":"제목","region":"지역","distance_km":12,
 *     "difficulty":"중","created_at":"2025-08-20 10:00:00",
 *     "tags":"태그,콤마","body":"본문..."
 *   }
 * - created_at 없으면 파일 mtime 사용
 */
function read_posts(string $dir): array {
  if (!is_dir($dir)) return [];
  $out = [];
  foreach (scandir($dir) ?: [] as $f) {
    if ($f === '.' || $f === '..') continue;
    $path = $dir . DIRECTORY_SEPARATOR . $f;
    if (!is_file($path)) continue;
    // JSON만 대상으로 삼음(확장자 무시해도 되지만 안전하게 체크)
    if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'json') continue;

    $raw = @file_get_contents($path);
    if ($raw === false) continue;
    $j = json_decode($raw, true);
    if (!is_array($j)) continue;

    $j['__file'] = basename($path);
    if (empty($j['created_at'])) {
      $j['created_at'] = date('Y-m-d H:i:s', @filemtime($path) ?: time());
    }
    if (empty($j['title'])) $j['title'] = '(제목 없음)';
    $out[] = $j;
  }
  // 최신 순
  usort($out, fn($a,$b)=> strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
  return $out;
}

/* ─────────────────────────────────────────────
   CSRF 토큰 (관리자일 때만 발급)
   ───────────────────────────────────────────── */
if (is_admin() && empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

/* ─────────────────────────────────────────────
   데이터 로드 + 검색
   ───────────────────────────────────────────── */
$postsDir = __DIR__ . '/data/posts';
$posts  = read_posts($postsDir);
$region = trim($_GET['region'] ?? '');
if ($region !== '') {
  $posts = array_values(array_filter(
    $posts,
    fn($p) => mb_stripos((string)($p['region'] ?? ''), $region) !== false
  ));
}
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>전체 글 - 트레킹 공유</title>
<style>
  :root {
    --bg: #0f172a; --bg-2: #0b1220; --card: #111827; --border: #1f2937;
    --text: #e5e7eb; --muted: #9ca3af; --link: #93c5fd; --btn-bg: #1e293b; --btn-bd: #334155;
  }
  * { box-sizing: border-box; }
  body { font-family: Arial, sans-serif; background: var(--bg); color: var(--text); margin: 0; line-height: 1.6; }
  header, .wrap { max-width: 980px; margin: 0 auto; padding: 16px; }
  header { background: var(--bg-2); position: sticky; top: 0; z-index: 10; border-bottom: 1px solid var(--border); }
  h1 { margin: 0; font-size: 22px; }
  nav a { color: var(--link); text-decoration: none; }
  nav a:hover { text-decoration: underline; }
  .filter { display: flex; gap: 8px; align-items: center; margin: 12px 0 8px; }
  .filter input { padding: 8px; border-radius: 8px; border: 1px solid var(--btn-bd); background: var(--bg); color: var(--text); }
  .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 16px; margin: 12px 0; }
  .meta { color: var(--muted); font-size: 13px; margin: 4px 0 8px; }
  .btn { padding: 8px 12px; border-radius: 8px; border: 1px solid var(--btn-bd); background: var(--btn-bg); color: var(--text); display: inline-block; text-decoration: none; }
  .btn + .btn { margin-left: 8px; }
  .chip{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid var(--btn-bd);background:var(--btn-bg);color:var(--text);text-decoration:none}
  footer { background: #1e293b; color: #cbd5e1; padding: 20px; font-size: 14px; text-align: center; }
  footer a { color: var(--link); }
</style>
</head>
<body>
<header>
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
    <div>
        <h1 style="display:inline">🥾 전체 글</h1>
      <?php if (is_admin()): ?>
        <!-- 관리자용 빠른 이동 -->
        <a href="/clients.php" class="chip" style="margin-left:8px">거래처</a>
        <a href="/clients.php?new=1#newClient" class="chip" style="margin-left:6px">＋ 거래처</a>
        <a href="/clients_mini.php" class="chip" style="margin-left:6px">미니 대시보드</a>
      <?php endif; ?>
    </div>
    <nav>
      <a href="/index.php">홈</a> · <a href="/write.php">글 쓰기</a>
      <?php if (is_admin()): ?>
        · <a href="/logout.php">로그아웃</a>
      <?php else: ?>
        · <a href="/login.php">관리자 로그인</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<div class="wrap">
  <form class="filter" method="get">
    <label for="region">지역 검색:</label>
    <input id="region" type="text" name="region" value="<?=h($region)?>" placeholder="예: 설악, 지리산">
    <button class="btn" type="submit">검색</button>
    <?php if ($region!==''): ?><a class="btn" href="/list.php">초기화</a><?php endif; ?>
  </form>

  <?php if (!$posts): ?>
    <p>아직 글이 없습니다. <a href="/write.php">첫 글을 작성해 보세요.</a></p>
  <?php endif; ?>

  <?php foreach ($posts as $p): ?>
    <div class="card">
      <h3 style="margin:0 0 6px">
        <a href="/view.php?id=<?=h($p['__file'])?>" style="color: var(--link); text-decoration: none;">
          <?=h((string)($p['title'] ?? '(제목 없음)'))?>
        </a>
      </h3>

      <div class="meta">
        지역: <?=h((string)($p['region'] ?? ''))?> ·
        거리: <?=h((string)($p['distance_km'] ?? ''))?>km ·
        난이도: <?=h((string)($p['difficulty'] ?? ''))?> ·
        작성: <?=h((string)($p['created_at'] ?? ''))?>
      </div>

      <?php if (!empty($p['tags'])): ?>
        <div class="meta">태그: <?=h((string)$p['tags'])?></div>
      <?php endif; ?>

      <p style="margin: 8px 0 12px;">
        <?=nl2br(h(mb_strimwidth((string)($p['body'] ?? ''), 0, 120, '...', 'UTF-8')))?>
      </p>

      <!-- 액션 버튼 -->
      <a class="btn" href="/view.php?id=<?=h($p['__file'])?>">자세히 보기</a>
      <?php if (is_admin() && !empty($_SESSION['csrf'])): ?>
        <a class="btn"
           href="/delete.php?id=<?=h($p['__file'])?>&csrf=<?=h($_SESSION['csrf'])?>"
           onclick="return confirm('정말 삭제하시겠습니까?');">삭제</a>
      <?php endif; ?>
      <!-- (선택) 수정 버튼 사용 시 edit.php 구현 후 주석 해제
      <a class="btn" href="/edit.php?id=<?=h($p['__file'])?>">수정</a>
      -->
    </div>
  <?php endforeach; ?>
</div>

<footer>
  그냥고고 / 대표:문현권 / (06211) 서울특별시 강남구 역삼동 56-5355 유인빌딩24 서관 909호 /
  E-Mail: <a href="mailto:gnggceo@gngg.net">gnggceo@gngg.net</a><br>
  사업자등록번호: 25517-81-36347 / 통신판매업신고번호: 2025514-서울강남-02098호 /
  개인정보보호책임자: 유석환 (<a href="mailto:m3353@mmals3.kr">m3353@mmals3.kr</a>)
</footer>
<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>
</html>
