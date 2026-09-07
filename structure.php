<?php
/* =============================================================
   structure.php — 데이터 구조 확인 (관리자 전용)
   ─────────────────────────────────────────────────────────────
   서버에 실제로 무엇이 어떻게 저장되어 있는지 보여줍니다.

   ★ 개인정보는 표시하지 않습니다.
     이메일·전화번호·비밀번호 해시는 읽지도 않고, 개수와 구조만 셉니다.

   확인이 끝나면 이 파일은 지우셔도 됩니다.
   ============================================================= */
declare(strict_types=1);

if (!ini_get('date.timezone')) date_default_timezone_set('Asia/Seoul');
ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']);
session_start();

function is_admin(): bool {
  return (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'])
      || (!empty($_SESSION['ID_OK']) && $_SESSION['ID_OK'] == 1);
}
if (!is_admin()) {
  http_response_code(403);
  exit('<meta charset="utf-8"><p style="font-family:sans-serif;padding:40px">관리자만 볼 수 있습니다. <a href="/admin_login.php">로그인</a></p>');
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function kb(int $b): string {
  if ($b < 1024) return $b . ' B';
  if ($b < 1024*1024) return number_format($b/1024, 1) . ' KB';
  return number_format($b/1024/1024, 2) . ' MB';
}

$ROOT = __DIR__;
$DATA = $ROOT . '/data';

/* ── 1. 회원 수와 members.json 상태 ── */
$membersFile = $DATA . '/members.json';
$memberCount = 0; $membersSize = 0; $membersOk = false;
if (is_file($membersFile)) {
  $membersSize = (int)@filesize($membersFile);
  $raw = @file_get_contents($membersFile);
  $arr = json_decode((string)$raw, true);
  if (is_array($arr)) { $membersOk = true; $memberCount = count($arr); }
}
/* 회원 1명당 평균 크기 → 앞으로 얼마나 커질지 예측 */
$perMember = $memberCount > 0 ? (int)round($membersSize / $memberCount) : 0;

/* ── 2. 기능별 폴더에 회원 폴더가 몇 개씩 있는지 ── */
$features = [
  'building'      => ['건물 기본정보',    'info.json'],
  'worklog'       => ['업무수행 기록표',  'm{연월}.json'],
  'fireplan'      => ['소방계획서',       '_index.json + 계획서'],
  'jawi'          => ['자위소방대 편성표', '_index.json + 편성표'],
  'train'         => ['훈련·교육 기록',   '_index.json + 기록'],
  'subscribe'     => ['구독·결제',        'subscription.json'],
  'notifications' => ['알림',             '{회원}.json'],
];
$featStat = [];
foreach ($features as $dir => [$label, $shape]) {
  $path = $DATA . '/' . $dir;
  $userDirs = 0; $files = 0; $bytes = 0;
  if (is_dir($path)) {
    foreach (glob($path . '/*') ?: [] as $entry) {
      if (is_dir($entry)) {
        $userDirs++;
        foreach (glob($entry . '/*.json') ?: [] as $f) { $files++; $bytes += (int)@filesize($f); }
      } elseif (is_file($entry)) {
        $files++; $bytes += (int)@filesize($entry);
      }
    }
  }
  $featStat[$dir] = ['label'=>$label, 'shape'=>$shape, 'exists'=>is_dir($path),
                     'users'=>$userDirs, 'files'=>$files, 'bytes'=>$bytes];
}

/* ── 3. data 폴더 최상위에 있는 공용 JSON 파일 ── */
$rootJsons = [];
foreach (glob($DATA . '/*.json') ?: [] as $f) {
  $rootJsons[] = ['name'=>basename($f), 'size'=>(int)@filesize($f)];
}
usort($rootJsons, fn($a,$b) => $b['size'] <=> $a['size']);

/* ── 4. 보안·안전 점검 ── */
$htaccess = is_file($DATA . '/.htaccess');
$dataWritable = is_writable($DATA);
$checks = [
  ['data 폴더 웹 접근 차단', $htaccess,
   $htaccess ? '.htaccess 있음' : '.htaccess 없음 — 회원 정보가 웹에서 열릴 수 있습니다'],
  ['data 폴더 쓰기 권한', $dataWritable,
   $dataWritable ? '정상' : '쓰기 불가 — 저장이 실패합니다'],
  ['members.json 읽기', $membersOk,
   $membersOk ? '정상 (JSON 파싱 성공)' : '파일이 없거나 형식 오류'],
];

/* ── 4-1. 회원 한 명을 골라 그 사람 데이터를 전부 훑어봅니다 ──
   ?uid=tttt 처럼 주소에 붙이면 그 회원을 봅니다. 없으면 첫 번째 회원. */
$allUids = [];
foreach ($features as $dir => $_) {
  $base = $DATA . '/' . $dir;
  foreach (glob($base . '/*', GLOB_ONLYDIR) ?: [] as $d) $allUids[basename($d)] = true;
  foreach (glob($base . '/*.json') ?: [] as $f) {
    $n = basename($f, '.json');
    if ($n !== '_index' && $n !== 'inquiries') $allUids[$n] = true;
  }
}
$allUids = array_keys($allUids);
sort($allUids);

$pickUid = trim((string)($_GET['uid'] ?? ''));
if ($pickUid !== '' && !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $pickUid)) $pickUid = '';
if ($pickUid === '' || !in_array($pickUid, $allUids, true)) {
  $pickUid = $allUids[0] ?? '';
}

/* 고른 회원의 파일을 기능별로 모읍니다 */
$mine = [];
if ($pickUid !== '') {
  foreach ($features as $dir => [$label, $shape]) {
    $rows = [];
    $udir = $DATA . '/' . $dir . '/' . $pickUid;
    if (is_dir($udir)) {
      foreach (glob($udir . '/*') ?: [] as $f) {
        if (!is_file($f)) continue;
        $rows[] = ['path' => 'data/' . $dir . '/' . $pickUid . '/' . basename($f),
                   'name' => basename($f), 'size' => (int)@filesize($f),
                   'time' => (int)@filemtime($f), 'full' => $f];
      }
    }
    $single = $DATA . '/' . $dir . '/' . $pickUid . '.json';
    if (is_file($single)) {
      $rows[] = ['path' => 'data/' . $dir . '/' . $pickUid . '.json',
                 'name' => basename($single), 'size' => (int)@filesize($single),
                 'time' => (int)@filemtime($single), 'full' => $single];
    }
    usort($rows, fn($a,$b) => strcmp($a['name'], $b['name']));
    $mine[$dir] = ['label'=>$label, 'rows'=>$rows];
  }
}

/* 파일 안이 어떤 모양인지 — 값이 아니라 '항목 이름'만 뽑습니다(개인정보 미표시) */
function peek_keys(string $file, int $limit = 12): array {
  $raw = @file_get_contents($file);
  if ($raw === false) return [];
  $a = json_decode($raw, true);
  if (!is_array($a)) return [];
  $isList = array_keys($a) === range(0, count($a) - 1);
  if ($isList) {
    $first = $a[0] ?? null;
    if (is_array($first)) return array_merge(['(목록 ' . count($a) . '건)'], array_slice(array_keys($first), 0, $limit));
    return ['(목록 ' . count($a) . '건)'];
  }
  return array_slice(array_keys($a), 0, $limit);
}

/* ── 5. 현재 세션 (내가 누구로 보이는지) ── */
$sessionInfo = [
  'member_id' => $_SESSION['member_id'] ?? '(없음)',
  'nickname'  => $_SESSION['nickname'] ?? '(없음)',
  'role'      => $_SESSION['role'] ?? '(없음)',
  'is_admin'  => !empty($_SESSION['is_admin']) ? 'true' : 'false',
];
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>데이터 구조 확인</title>
<style>
  :root{--bg:#f5f7fb;--card:#fff;--bd:#e3e8f0;--fg:#1a2436;--mut:#7a8699;--mut2:#56627a;
    --brand:#2563eb;--ok:#16a34a;--warn:#b45309;--danger:#dc2626}
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:var(--bg);color:var(--fg);padding:24px 20px 70px;line-height:1.6;
    font-family:Inter,ui-sans-serif,system-ui,"Apple SD Gothic Neo",sans-serif}
  .wrap{max-width:860px;margin:0 auto}
  h1{font-size:21px;font-weight:800;margin-bottom:5px}
  .sub{font-size:13px;color:var(--mut);margin-bottom:22px}
  .card{background:var(--card);border:1px solid var(--bd);border-radius:13px;
    padding:18px 20px;margin-bottom:14px}
  .card h2{font-size:14px;font-weight:800;margin-bottom:14px}
  table{width:100%;border-collapse:collapse;font-size:13px}
  th,td{padding:8px 6px;border-bottom:1px solid #eef2f7;text-align:left;vertical-align:top}
  th{color:var(--mut);font-size:11.5px;font-weight:700}
  td.num{text-align:right;font-variant-numeric:tabular-nums}
  code{background:#f1f5f9;padding:2px 6px;border-radius:5px;font-size:12px}
  .chk{display:flex;align-items:flex-start;gap:9px;padding:9px 0;border-bottom:1px solid #eef2f7;font-size:13px}
  .chk:last-child{border-bottom:0}
  .chk .ic{flex:0 0 18px;width:18px;height:18px;border-radius:50%;color:#fff;font-size:11px;
    font-weight:900;display:flex;align-items:center;justify-content:center;margin-top:2px}
  .ic.ok{background:var(--ok)} .ic.bad{background:var(--danger)}
  .chk b{font-weight:700} .chk span{color:var(--mut2);font-size:12px;display:block}
  .tree{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:12.5px;
    line-height:1.85;background:#f8fafc;border-radius:10px;padding:15px 17px;overflow-x:auto}
  .tree .d{color:var(--brand);font-weight:600}
  .tree .c{color:var(--mut)}
  .big{display:flex;gap:26px;flex-wrap:wrap;margin-bottom:6px}
  .big div{min-width:110px}
  .big b{display:block;font-size:24px;font-weight:800;line-height:1.25}
  .big span{font-size:11.5px;color:var(--mut)}
  .warn{background:#fffbeb;border:1px solid #f6d8a8;color:#92400e;border-radius:10px;
    padding:12px 14px;font-size:12.5px;line-height:1.75;margin-top:12px}
  .note{font-size:12px;color:var(--mut);line-height:1.75;margin-top:10px}
  .split{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px}
  .split__col{background:#f8fafc;border-radius:11px;padding:14px 15px}
  .split__t{margin-bottom:7px}
  .pill{font-size:11px;font-weight:800;padding:3px 10px;border-radius:999px}
  .pill--shared{background:#fef3c7;color:#92400e}
  .pill--own{background:#dcfce7;color:#15803d}
  .split__d{font-size:12px;color:var(--mut2);line-height:1.65;margin-bottom:11px}
  .flist{display:flex;flex-direction:column;gap:5px}
  .frow{display:flex;align-items:center;justify-content:space-between;gap:10px;
    background:#fff;border:1px solid var(--bd);border-radius:8px;padding:7px 11px}
  .fname{font-family:ui-monospace,Consolas,monospace;font-size:12px;color:var(--fg)}
  .fsize{font-size:11px;color:var(--mut);white-space:nowrap}
  .fempty{font-size:12px;color:var(--mut);padding:8px 2px}
  .sample{margin-top:4px}
  .sample__t{font-size:12.5px;font-weight:700;color:var(--mut2);margin-bottom:9px}
  @media(max-width:640px){.split{grid-template-columns:1fr}}
  .uidbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px;
    background:#f8fafc;border-radius:10px;padding:11px 13px}
  .uidbar label{font-size:12.5px;font-weight:700;color:var(--mut2)}
  .uidbar select{font-family:ui-monospace,Consolas,monospace;font-size:13px;padding:6px 10px;
    border:1px solid var(--bd);border-radius:8px;background:#fff;color:var(--fg);min-width:150px}
  .uidbar__n{font-size:11.5px;color:var(--mut);margin-left:auto}
  .mine__sum{font-size:13px;color:var(--mut2);margin-bottom:13px}
  .mine__sum b{color:var(--fg)}
  .fgroup{margin-bottom:13px}
  .fgroup__h{display:flex;align-items:baseline;gap:9px;margin-bottom:6px;flex-wrap:wrap}
  .fgroup__label{font-size:13px;font-weight:700}
  .fgroup__dir{font-family:ui-monospace,Consolas,monospace;font-size:11.5px;color:var(--brand)}
  .fitem{background:#f8fafc;border-radius:9px;padding:9px 12px;margin-bottom:5px}
  .fitem__top{display:flex;align-items:baseline;justify-content:space-between;gap:12px;flex-wrap:wrap}
  .fitem__path{font-family:ui-monospace,Consolas,monospace;font-size:12px;color:var(--fg);word-break:break-all}
  .fitem__meta{font-size:11px;color:var(--mut);white-space:nowrap}
  .fitem__keys{display:flex;flex-wrap:wrap;gap:4px;margin-top:7px}
  .kchip{font-size:10.5px;color:var(--mut2);background:#fff;border:1px solid var(--bd);
    border-radius:5px;padding:2px 7px;font-family:ui-monospace,Consolas,monospace}
</style>
</head>
<body>
<div class="wrap">
  <h1>데이터 구조 확인</h1>
  <div class="sub">서버에 실제로 저장된 상태입니다. 개인정보는 표시하지 않고 개수와 크기만 셉니다.</div>

  <!-- 1. 한눈에 -->
  <div class="card">
    <h2>한눈에 보기</h2>
    <div class="big">
      <div><b><?=number_format($memberCount)?></b><span>가입 회원</span></div>
      <div><b><?=h(kb($membersSize))?></b><span>members.json 크기</span></div>
      <div><b><?=$perMember ? h(kb($perMember)) : '-'?></b><span>회원 1명당</span></div>
    </div>
    <?php if ($perMember > 0): ?>
      <div class="note">
        이 속도라면 회원 1,000명일 때 약 <b><?=h(kb($perMember*1000))?></b>,
        5,000명일 때 약 <b><?=h(kb($perMember*5000))?></b>가 됩니다.
      </div>
      <?php if ($membersSize > 300*1024): ?>
        <div class="warn">
          <b>members.json 이 커지고 있습니다.</b><br>
          로그인할 때마다 이 파일 전체를 읽고 쓰기 때문에, 계속 커지면 느려집니다.
          <code>last_login</code> 갱신을 별도 파일로 분리하는 것을 권합니다.
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- 2. 폴더 구조 -->
  <div class="card">
    <h2>데이터가 나뉘는 방식</h2>

    <div class="split">
      <div class="split__col">
        <div class="split__t"><span class="pill pill--shared">전체 공용</span></div>
        <p class="split__d">모든 회원이 한 파일을 같이 씁니다. 로그인할 때마다 통째로 읽고 씁니다.</p>
        <div class="flist">
          <?php foreach ($rootJsons as $j): ?>
            <div class="frow">
              <span class="fname"><?=h($j['name'])?></span>
              <span class="fsize"><?=h(kb($j['size']))?></span>
            </div>
          <?php endforeach; ?>
          <?php if (!$rootJsons): ?><div class="fempty">아직 파일이 없습니다</div><?php endif; ?>
        </div>
      </div>

      <div class="split__col">
        <div class="split__t"><span class="pill pill--own">회원별 분리</span></div>
        <p class="split__d">회원마다 자기 폴더를 씁니다. 남의 데이터를 건드릴 일이 없습니다.</p>
        <div class="flist">
          <?php foreach ($featStat as $dir => $st): if (!$st['exists']) continue; ?>
            <div class="frow">
              <span class="fname"><?=h($dir)?>/</span>
              <span class="fsize"><?=$st['users']?>명</span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </div>

  <!-- 2-1. 회원 한 명의 데이터 전부 보기 -->
  <div class="card">
    <h2>회원 한 명의 데이터 따라가기</h2>

    <?php if (!$allUids): ?>
      <div class="fempty">아직 저장된 회원 데이터가 없습니다.</div>
    <?php else: ?>
      <form method="get" class="uidbar">
        <label for="uidsel">회원 선택</label>
        <select id="uidsel" name="uid" onchange="this.form.submit()">
          <?php foreach ($allUids as $u): ?>
            <option value="<?=h($u)?>" <?=$u===$pickUid?'selected':''?>><?=h($u)?></option>
          <?php endforeach; ?>
        </select>
        <span class="uidbar__n">전체 <?=count($allUids)?>명</span>
      </form>

      <div class="mine">
        <?php
          $totalFiles = 0; $totalBytes = 0;
          foreach ($mine as $d) { foreach ($d['rows'] as $r) { $totalFiles++; $totalBytes += $r['size']; } }
        ?>
        <div class="mine__sum">
          <code><?=h($pickUid)?></code> 님의 데이터 —
          파일 <b><?=$totalFiles?>개</b> · 합계 <b><?=h(kb($totalBytes))?></b>
        </div>

        <?php foreach ($mine as $dir => $d): ?>
          <?php if (!$d['rows']) continue; ?>
          <div class="fgroup">
            <div class="fgroup__h">
              <span class="fgroup__label"><?=h($d['label'])?></span>
              <span class="fgroup__dir">data/<?=h($dir)?>/</span>
            </div>
            <?php foreach ($d['rows'] as $r): ?>
              <div class="fitem">
                <div class="fitem__top">
                  <span class="fitem__path"><?=h($r['path'])?></span>
                  <span class="fitem__meta"><?=h(kb($r['size']))?> · <?=$r['time'] ? date('Y-m-d H:i', $r['time']) : '-'?></span>
                </div>
                <?php $keys = peek_keys($r['full']); if ($keys): ?>
                  <div class="fitem__keys">
                    <?php foreach ($keys as $k): ?><span class="kchip"><?=h($k)?></span><?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>

        <?php if ($totalFiles === 0): ?>
          <div class="fempty">이 회원은 아직 저장된 데이터가 없습니다.</div>
        <?php endif; ?>
      </div>

      <div class="note">
        회색 칩은 그 파일 안에 들어 있는 <b>항목 이름</b>입니다. 값(실제 내용)은 표시하지 않습니다.<br>
        회원을 바꿔가며 보시면, 같은 구조로 사람마다 폴더가 나뉘어 있는 것을 확인하실 수 있습니다.
      </div>
    <?php endif; ?>

  </div>

  <!-- 3. 기능별 상세 -->
  <div class="card">
    <h2>기능별 저장 현황</h2>
    <table>
      <tr><th>폴더</th><th>무엇을 담나</th><th>파일 형태</th>
          <th style="text-align:right">회원</th><th style="text-align:right">파일</th><th style="text-align:right">용량</th></tr>
      <?php foreach ($featStat as $dir => $st): ?>
        <tr>
          <td><code><?=h($dir)?></code></td>
          <td><?=h($st['label'])?></td>
          <td style="color:var(--mut);font-size:12px"><?=h($st['shape'])?></td>
          <td class="num"><?= $st['exists'] ? $st['users'] : '-' ?></td>
          <td class="num"><?= $st['exists'] ? $st['files'] : '-' ?></td>
          <td class="num"><?= $st['exists'] ? h(kb($st['bytes'])) : '<span style="color:var(--mut)">없음</span>' ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <div class="note">
      회원별로 폴더가 갈라져 있어, 회원이 늘어도 각자 폴더만 열면 됩니다. 이 구조는 확장에 문제없습니다.
    </div>
  </div>

  <!-- 4. 안전 점검 -->
  <div class="card">
    <h2>안전 점검</h2>
    <?php foreach ($checks as [$label, $ok, $desc]): ?>
      <div class="chk">
        <span class="ic <?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '✓' : '!' ?></span>
        <div><b><?=h($label)?></b><span><?=h($desc)?></span></div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- 5. 내 세션 -->
  <div class="card">
    <h2>지금 내 세션</h2>
    <table>
      <?php foreach ($sessionInfo as $k => $v): ?>
        <tr><td style="color:var(--mut);width:130px"><?=h($k)?></td><td><code><?=h($v)?></code></td></tr>
      <?php endforeach; ?>
    </table>
    <div class="note">
      <code>member_id</code> 가 모든 데이터 폴더의 이름이 됩니다.
      이 값이 비어 있으면 아무 데이터도 읽거나 쓰지 않습니다(남의 자료가 섞이는 것을 막는 장치입니다).
    </div>
  </div>

</div>
</body>
</html>
