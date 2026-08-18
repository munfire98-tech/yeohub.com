<?php
/* =============================================================
   assist_log_view.php — 사람들이 무엇을 물어봤는지 보는 화면
   ─────────────────────────────────────────────────────────────
   "못 답한 질문"을 보고 assist_flows.php 의 assist_faq() 에
   답을 추가해 나가면 도우미가 점점 촘촘해집니다.
   ============================================================= */
declare(strict_types=1);

if (!ini_get('date.timezone')) { date_default_timezone_set('Asia/Seoul'); }
ini_set('session.cookie_httponly', '1');
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/* TWORIX 의 기존 관리자 판정과 같은 방식 */
function is_admin(): bool {
  return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
if (!is_admin()) { header('Location: /index.php'); exit; }

const LOG_FILE = __DIR__ . '/data/assist_log.json';

/* 기록 비우기 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'clear') {
  if (!empty($_SESSION['csrf']) && hash_equals((string)$_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
    @unlink(LOG_FILE);
  }
  header('Location: assist_log_view.php'); exit;
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

$all = [];
if (is_file(LOG_FILE)) {
  $a = json_decode((string)@file_get_contents(LOG_FILE), true);
  if (is_array($a)) $all = $a;
}
$all = array_reverse($all);

$tab = (string)($_GET['tab'] ?? 'miss');
if (!in_array($tab, ['miss', 'faq', 'done', 'all'], true)) $tab = 'miss';

$counts = ['miss' => 0, 'faq' => 0, 'done' => 0];
foreach ($all as $r) {
  $k = (string)($r['kind'] ?? '');
  if (isset($counts[$k])) $counts[$k]++;
}

$rows = $tab === 'all' ? $all : array_values(array_filter($all, fn($r) => ($r['kind'] ?? '') === $tab));

/* 같은 질문이 몇 번 나왔는지 — 자주 나오는 것부터 답을 만들면 효율이 좋다 */
$freq = [];
foreach ($rows as $r) {
  $t = trim((string)($r['text'] ?? ''));
  if ($t === '') continue;
  $freq[$t] = ($freq[$t] ?? 0) + 1;
}
arsort($freq);

/* 서식별 완료 횟수 */
$formNames = ['worklog' => '업무수행 기록표', 'train' => '소방훈련·교육 기록부',
              'jawi' => '자위소방대 편성표', 'plan' => '소방계획서'];
$doneBy = [];
foreach ($all as $r) {
  if (($r['kind'] ?? '') !== 'done') continue;
  $t = (string)($r['text'] ?? '');
  $doneBy[$t] = ($doneBy[$t] ?? 0) + 1;
}
arsort($doneBy);
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>도우미 질문 기록 — TWORIX</title>
<style>
:root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--bd2:#d4dbe6;--fg:#1a2436;
  --mut:#7a8699;--mut2:#56627a;--brand:#2563eb;--brand2:#1d4ed8}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--fg);line-height:1.65;
  font-family:Inter,system-ui,"Apple SD Gothic Neo",sans-serif}
a{text-decoration:none;color:inherit}
.nav{background:#fff;border-bottom:1px solid var(--bd)}
.nav__in{max-width:900px;margin:0 auto;padding:0 20px;height:56px;
  display:flex;align-items:center;justify-content:space-between}
.brand{font-weight:800;font-size:21px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 15px;border-radius:9px;
  border:1px solid var(--bd2);background:#fff;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit}
.btn:hover{border-color:var(--brand);color:var(--brand2)}
.btn--sm{padding:6px 12px;font-size:12.5px}
.wrap{max-width:900px;margin:0 auto;padding:28px 20px 70px}
h1{font-size:25px;font-weight:800;letter-spacing:-.3px}
.lead{color:var(--mut2);font-size:14.5px;margin-top:7px;max-width:60ch}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:22px 0}
.kpi{background:#fff;border:1px solid var(--bd);border-radius:13px;padding:15px 17px}
.kpi__k{font-size:12px;color:var(--mut2);font-weight:600}
.kpi__v{font-size:27px;font-weight:800;margin-top:3px;letter-spacing:-.5px}
.kpi--warn{border-color:#fdba74;background:#fff7ed}
.kpi--warn .kpi__v{color:#c2410c}
.tabs{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:16px}
.tab{padding:8px 15px;border:1px solid var(--bd2);border-radius:999px;background:#fff;
  font-size:13.5px;font-weight:600;color:var(--mut2)}
.tab.on{background:var(--brand);border-color:var(--brand);color:#fff}
.panel{background:#fff;border:1px solid var(--bd);border-radius:14px;padding:20px}
.panel+.panel{margin-top:16px}
.panel h2{font-size:16px;font-weight:800;margin-bottom:4px}
.panel .sub{font-size:13px;color:var(--mut2);margin-bottom:14px}
.item{display:flex;gap:12px;align-items:flex-start;padding:11px 0;border-top:1px solid var(--bd)}
.item:first-of-type{border-top:0}
.item__n{flex-shrink:0;min-width:34px;text-align:center;font-size:12px;font-weight:800;
  padding:2px 8px;border-radius:999px;background:#eef2ff;color:var(--brand2)}
.item__n--hot{background:#fef2f2;color:#dc2626}
.item__t{flex:1;font-size:14.5px;word-break:break-word}
.item__d{font-size:12px;color:var(--mut);white-space:nowrap}
.empty{text-align:center;color:var(--mut2);padding:40px 20px;font-size:14.5px}
.tip{display:flex;gap:11px;background:#f0f7ff;border:1px solid #cfe0ff;border-radius:12px;
  padding:14px 16px;font-size:13.5px;color:var(--brand2);line-height:1.7;margin-top:18px}
.bar{height:6px;border-radius:3px;background:#eef2ff;overflow:hidden;margin-top:5px}
.bar i{display:block;height:100%;background:var(--brand)}
</style>
</head>
<body>

<nav class="nav">
  <div class="nav__in">
    <a class="brand" href="/index.php">TWORIX</a>
    <div style="display:flex;gap:8px">
      <a class="btn" href="/assist.php">도우미 열기 ↗</a>
      <a class="btn" href="/admin_members.php">← 관리자</a>
    </div>
  </div>
</nav>

<main class="wrap">
  <h1>서식 작성 도우미 — 질문 기록</h1>
  <p class="lead">사람들이 무엇을 물어봤는지 모아둡니다.
    <b>못 답한 질문</b>을 보고 <code>assist_flows.php</code>의 <code>assist_faq()</code>에 답을 추가하면
    도우미가 점점 촘촘해집니다.</p>

  <div class="cards">
    <div class="kpi <?= $counts['miss'] ? 'kpi--warn' : '' ?>">
      <div class="kpi__k">못 답한 질문</div>
      <div class="kpi__v"><?=$counts['miss']?></div>
    </div>
    <div class="kpi">
      <div class="kpi__k">답한 질문</div>
      <div class="kpi__v"><?=$counts['faq']?></div>
    </div>
    <div class="kpi">
      <div class="kpi__k">서식 완성</div>
      <div class="kpi__v"><?=$counts['done']?></div>
    </div>
  </div>

  <?php if ($doneBy): ?>
  <div class="panel">
    <h2>어떤 서식을 많이 쓰나요</h2>
    <p class="sub">끝까지 진행해 문장을 받아 간 횟수입니다.</p>
    <?php $max = max($doneBy); foreach ($doneBy as $k => $n): ?>
      <div style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;font-size:14px">
          <span><?=h($formNames[$k] ?? $k)?></span>
          <b><?=$n?>회</b>
        </div>
        <div class="bar"><i style="width:<?=max(4, (int)round($n / $max * 100))?>%"></i></div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="panel" style="margin-top:16px">
    <div class="tabs">
      <a class="tab <?= $tab === 'miss' ? 'on' : '' ?>" href="?tab=miss">못 답한 질문 (<?=$counts['miss']?>)</a>
      <a class="tab <?= $tab === 'faq'  ? 'on' : '' ?>" href="?tab=faq">답한 질문 (<?=$counts['faq']?>)</a>
      <a class="tab <?= $tab === 'done' ? 'on' : '' ?>" href="?tab=done">서식 완성 (<?=$counts['done']?>)</a>
      <a class="tab <?= $tab === 'all'  ? 'on' : '' ?>" href="?tab=all">전체</a>
    </div>

    <?php if (!$freq): ?>
      <div class="empty">
        <?php if ($tab === 'miss'): ?>
          답을 못 한 질문이 아직 없습니다. 좋은 신호입니다.
        <?php else: ?>
          아직 기록이 없습니다.
        <?php endif; ?>
      </div>
    <?php else: ?>
      <?php $i = 0; foreach ($freq as $text => $n): if (++$i > 100) break; ?>
        <div class="item">
          <span class="item__n <?= $n >= 3 ? 'item__n--hot' : '' ?>"><?=$n?></span>
          <span class="item__t"><?=h($formNames[$text] ?? $text)?></span>
        </div>
      <?php endforeach; ?>
      <?php if (count($freq) > 100): ?>
        <p class="sub" style="margin:14px 0 0">…외 <?=count($freq) - 100?>건 더 있습니다.</p>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($tab === 'miss' && $freq): ?>
      <div class="tip">
        <div>💡</div>
        <div>숫자가 <b>3 이상</b>인 질문부터 답을 만드세요. 자주 나오는 것 몇 개만 채워도
          "못 답함"이 눈에 띄게 줄어듭니다.</div>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($all): ?>
  <form method="post" style="margin-top:18px"
        onsubmit="return confirm('기록을 모두 지울까요? 되돌릴 수 없습니다.')">
    <input type="hidden" name="act" value="clear">
    <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
    <button class="btn btn--sm" type="submit">기록 모두 지우기</button>
  </form>
  <?php endif; ?>
</main>

<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>
</html>
