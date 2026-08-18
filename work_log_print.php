<?php
// work_log_print.php — 특정 연도의 업무 수행 기록표를 모아서 인쇄/PDF
declare(strict_types=1);

if (!ini_get('date.timezone')) { date_default_timezone_set('Asia/Seoul'); }
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

require_once __DIR__ . '/user_key.php';
$uidKey = app_user_key();
if ($uidKey === '') { die('<meta charset="utf-8">' . app_user_key_notice()); }
$uidKey = preg_replace('/[^A-Za-z0-9_]/', '_', (string)$uidKey);
$BASE   = __DIR__ . '/data/worklog/' . $uidKey;

function load_json(string $f): array {
  if (!file_exists($f)) return [];
  $r = @file_get_contents($f); if ($r===false || trim($r)==='') return [];
  $a = json_decode($r, true); return is_array($a) ? $a : [];
}

$year = $_GET['year'] ?? date('Y');
if (!preg_match('/^\d{4}$/', $year)) $year = date('Y');

$fixed = load_json($BASE . '/building.json');

// 해당 연도의 작성된 달 수집 (1~12월 순)
$records = [];
for ($m = 1; $m <= 12; $m++) {
  $key = sprintf('%s-%02d', $year, $m);
  $rec = load_json($BASE . '/m' . $key . '.json');
  if (!empty($rec)) $records[$key] = $rec;
}

$nick = $_SESSION['nickname'] ?? '사용자';
$adminView = is_admin() && trim((string)($_GET['uid'] ?? '')) !== '';
$adminQuery = $adminView ? ('uid=' . rawurlencode($uidKey)) : '';
$url = function(string $path) use ($adminQuery): string {
  if ($adminQuery === '') return $path;
  return $path . (strpos($path, '?') === false ? '?' : '&') . $adminQuery;
};

// 한 장 렌더링 함수
function render_sheet(string $monthKey, array $rec, array $fixed): string {
  $it = fn($k,$sub) => $rec[$k][$sub] ?? '';
  $rp = fn($k) => $rec['report'][$k] ?? '';
  [$y,$mo] = explode('-', $monthKey);
  $monthLabel = $y.'년 '.(int)$mo.'월';
  $rows = [['sobang','소방시설'],['pinan','피난방화시설'],['hwagi','화기취급감독'],['etc','기타사항']];
  ob_start(); ?>
  <div class="sheet">
    <div class="law">■ 화재의 예방 및 안전관리에 관한 법률 시행규칙 [별지 제12호서식]</div>
    <div class="title">소방안전관리자 업무 수행 기록표</div>
    <div class="guide">※ [ ]에는 해당되는 곳에 √표를 합니다. &nbsp;&nbsp;(<?=h($monthLabel)?>)</div>
    <table class="f"><colgroup><col style="width:110px"><col><col style="width:80px"><col></colgroup>
      <tr><td class="lbl">수행일자</td><td><?=h($it('','') ?: ($rec['date'] ?? ''))?></td>
          <td class="lbl">수행자</td><td>
            <div class="perfp">
              <span><?=h($rec['performer'] ?? ($fixed['performer'] ?? ''))?></span>
              <?php if (!empty($rec['sign'])): ?>
                <img class="signp" src="<?=h($rec['sign'])?>" alt="서명">
              <?php endif; ?>
            </div>
          </td></tr>
    </table>
    <table class="f" style="margin-top:-1px"><colgroup><col style="width:110px"><col style="width:80px"><col><col style="width:70px"><col></colgroup>
      <tr><td class="lbl" rowspan="3">소방안전<br>관리대상물</td><td class="lbl">상호</td><td><?=h($fixed['sangho'] ?? '')?></td>
          <td class="lbl">등급</td><td>
            <?php foreach (['특급','1급','2급','3급'] as $gg): ?><span class="chk"><?= (($fixed['grade'] ?? '')===$gg)?'&#9745;':'&#9744;' ?> <?=$gg?></span><?php endforeach; ?>
          </td></tr>
      <tr><td class="lbl">소재지</td><td colspan="3"><?=h($fixed['address'] ?? '')?></td></tr>
      <tr><td class="lbl center" style="font-size:11px">지하/지상층·면적·동수</td>
          <td colspan="3">지하 <?=h($fixed['floor_b'] ?? '')?>층 · 지상 <?=h($fixed['floor_a'] ?? '')?>층 · 연면적 <?=h($fixed['area_t'] ?? '')?>㎡ · 바닥면적 <?=h($fixed['area_f'] ?? '')?>㎡ · <?=h($fixed['dongsu'] ?? '')?>동</td></tr>
    </table>
    <table class="f" style="margin-top:-1px"><colgroup><col style="width:110px"><col><col style="width:96px"><col style="width:150px"></colgroup>
      <tr><th>항 목</th><th>확인내용</th><th>확인결과</th><th>조치사항</th></tr>
      <?php foreach ($rows as [$k,$label]): ?>
        <tr><td class="lbl"><?=$label?></td>
            <td><?=nl2br(h($it($k,'note')))?></td>
            <td class="res">
              <div><?= $it($k,'result')==='양호'?'&#9745;':'&#9744;' ?> 양호</div>
              <div><?= $it($k,'result')==='불량'?'&#9745;':'&#9744;' ?> 불량</div>
            </td>
            <td><?=nl2br(h($it($k,'action')))?></td></tr>
      <?php endforeach; ?>
    </table>
    <table class="f" style="margin-top:-1px"><colgroup><col style="width:110px"><col style="width:130px"><col><col style="width:150px"></colgroup>
      <tr><td class="lbl" rowspan="2">불량사항<br>개선보고</td><td class="lbl">보고일시</td>
          <td><span class="chkline">보고방법 <?php foreach (['대면','서면','정보통신'] as $mm): ?><span class="chk"><?= $rp('method')===$mm?'&#9745;':'&#9744;' ?> <?=$mm?></span><?php endforeach; ?></span></td>
          <td class="lbl">보고받은 사람</td></tr>
      <tr><td><?=h($rp('when'))?></td>
          <td><span class="chkline">조치방법 <?php foreach (['이전','제거','수리·교체','기타'] as $fx): ?><span class="chk"><?= $rp('fix')===$fx?'&#9745;':'&#9744;' ?> <?=$fx?></span><?php endforeach; ?></span></td>
          <td><?=h($rp('person'))?></td></tr>
    </table>
    <div class="footnote">※ 작성요령<br>1. 월 1회 이상 작성 &nbsp; 2. 소방계획서·점검표의 점검항목 참고 &nbsp; 3. 특성에 따라 기타사항 추가 &nbsp; 4. 수신기·제어반·가압송수장치 중점 확인</div>
  </div>
  <?php
  return ob_get_clean();
}
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=h($year)?>년 업무 수행 기록 모음 — TWORIX</title>
<style>
:root{--bg:#f5f7fb;--bd:#e3e8f0;--bd2:#d4dbe6;--fg:#1a2436;--mut2:#56627a;--brand:#2563eb;--brand2:#1d4ed8}
*{box-sizing:border-box;margin:0;padding:0}
html,body{background:var(--bg);color:var(--fg);font-family:Inter,ui-sans-serif,system-ui,"Apple SD Gothic Neo",sans-serif;line-height:1.5}
a{text-decoration:none}
.topbar{background:#fff;border-bottom:1px solid var(--bd);padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;gap:12px;position:sticky;top:0;z-index:20}
.topbar .brand{font-weight:800;font-size:20px;letter-spacing:.5px}
.topbar .actions{display:flex;gap:8px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:9px;border:1px solid var(--bd2);background:#fff;color:var(--fg);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit}
.btn:hover{border-color:var(--brand);color:var(--brand2)}
.btn--primary{background:var(--brand);border-color:var(--brand);color:#fff}
.hint{max-width:900px;margin:14px auto 0;color:var(--mut2);font-size:13px;padding:0 8px}
.empty{max-width:900px;margin:40px auto;text-align:center;color:var(--mut2)}
.sheet{max-width:900px;margin:16px auto;background:#fff;border:1px solid var(--bd);border-radius:8px;padding:24px 28px;box-shadow:0 10px 30px rgba(20,40,80,.06)}
.law{font-size:12px;color:#333;margin-bottom:6px}
.title{text-align:center;font-size:23px;font-weight:800;letter-spacing:5px;margin:6px 0 8px}
.guide{font-size:11px;color:#333;margin-bottom:10px}
table.f{width:100%;border-collapse:collapse;table-layout:fixed}
table.f td,table.f th{border:1px solid #333;padding:6px 8px;font-size:13px;vertical-align:middle;word-break:break-all}
table.f th{background:#f3f4f6;font-weight:700;text-align:center}
.lbl{background:#f3f4f6;font-weight:700;text-align:center}
.center{text-align:center}
.chk{display:inline-flex;align-items:center;gap:3px;margin-right:8px;font-size:13px}
.chkline{display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap}
.res div{font-size:13px;margin:2px 0}
.footnote{font-size:11px;color:#333;margin-top:10px;line-height:1.6}
.perfp{display:flex;align-items:center;gap:8px}
.signp{height:30px;max-width:110px;width:auto;object-fit:contain;flex-shrink:0}

/* ── 모바일 ── */
@media screen and (max-width:640px){
  .topbar{padding:0 14px;height:auto;min-height:54px;flex-wrap:wrap;gap:8px;padding-top:8px;padding-bottom:8px}
  .topbar .brand{font-size:18px}
  .topbar .actions{width:100%;display:grid;grid-template-columns:auto 1fr;gap:6px}
  .topbar .actions .btn{justify-content:center;padding:10px 8px;font-size:12.5px}
  .hint,.empty{margin-left:12px;margin-right:12px;font-size:12.5px}
  .sheet{margin:12px;padding:16px 14px;border-radius:10px}
  .title{font-size:17px;letter-spacing:2px}
  .law,.guide{font-size:10.5px}
  table.f,table.f tbody,table.f tr,table.f td,table.f th{display:block;width:auto!important}
  table.f{margin-top:10px!important;border:1px solid #333;border-radius:8px;overflow:hidden}
  table.f colgroup{display:none}
  table.f tr{border-bottom:1px solid #333}
  table.f tr:last-child{border-bottom:0}
  table.f td,table.f th{border:0;border-bottom:1px solid #d8dde6;padding:9px 11px;font-size:13.5px}
  table.f tr>td:last-child,table.f tr>th:last-child{border-bottom:0}
  table.f th{background:#eef1f6;font-size:12px;text-align:left}
  .lbl{background:#f3f4f6;text-align:left!important;font-size:12px;color:#4a5568;padding:7px 11px!important}
  .lbl br{display:none}
  .res{display:flex;gap:16px}
  .res div{font-size:14px}
  .chk{font-size:13px;margin-right:10px}
  .perfp{flex-wrap:wrap}
  .signp{height:38px;max-width:150px}
  .footnote{font-size:10.5px}
}
@media print{
  .topbar,.hint{display:none !important}
  body{background:#fff}
  .sheet{box-shadow:none;border:0;margin:0;max-width:none;border-radius:0;padding:0;page-break-after:always}
  .sheet:last-of-type{page-break-after:auto}
  .signp{height:26px !important;max-width:95px !important;width:auto !important}
  @page{size:A4;margin:12mm}

  /* 한 장에 맞추기 — 전체 압축 */
  .sheet{font-size:10.5px}
  .title{font-size:18px;letter-spacing:3px;margin:2px 0 4px}
  .law,.guide{font-size:9px;margin-bottom:3px}
  table.f{page-break-inside:avoid !important;break-inside:avoid !important}
  table.f tr,table.f td,table.f th{page-break-inside:avoid !important;break-inside:avoid !important}
  table.f td,table.f th{padding:3px 5px;font-size:10.5px;line-height:1.25}
  .res div{font-size:10px;margin:0}
  .chk{font-size:10px;margin-right:5px;white-space:nowrap}
  .chkline{display:flex;flex-wrap:nowrap;align-items:center;gap:6px;white-space:nowrap}
  .footnote{font-size:8.5px;margin-top:6px;line-height:1.45}
  .perfp{gap:5px}
}
</style>
</head>
<body>
<div class="topbar">
  <a class="brand" href="/index.php">TWORIX</a>
  <div class="actions">
  <a class="btn" href="<?=h($url('/work_log.php'))?>">← 목록</a>
    <button class="btn btn--primary" type="button" onclick="window.print()">🖨 <?=h($year)?>년 전체 PDF / 인쇄</button>
  </div>
</div>

<?php if (empty($records)): ?>
  <div class="empty"><?=h($year)?>년에 작성된 기록이 없습니다.</div>
<?php else: ?>
  <div class="hint"><?=h($year)?>년 작성 기록 <?=count($records)?>건입니다. <b>PDF / 인쇄</b>를 누르고 <b>대상: PDF로 저장</b>을 선택하면 <?=count($records)?>장짜리 PDF 하나로 저장됩니다.</div>
  <?php foreach ($records as $key => $rec) { echo render_sheet($key, $rec, $fixed); } ?>
<?php endif; ?>

</body>
</html>
