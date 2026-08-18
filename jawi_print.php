<?php
/* =============================================================
   jawi_print.php — 자위소방대 및 초기대응체계 교육·훈련 실시 결과 기록부
   화재의 예방 및 안전관리에 관한 법률 시행규칙 [별지 제13호서식]
   ─────────────────────────────────────────────────────────────
   앞쪽: 대상물·소방안전관리자·자위소방대·초기대응체계·교육훈련 결과
   뒤쪽: 교육·훈련 참석확인 (연번 1~50)
   ============================================================= */
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

require_once __DIR__ . '/jawi_db.php';

$id  = (string)($_GET['id'] ?? '');
$rec = $id !== '' ? jw_load($id) : null;
if (!$rec) { header('Location: /jawi.php'); exit; }
$d = (array)($rec['data'] ?? []);

$v   = fn(string $k) => h((string)($d[$k] ?? ''));
$chk = fn(bool $on) => $on ? '☑' : '☐';

/* 소방안전관리자 4줄 */
$mgrs = (array)($d['mgrs'] ?? []);
while (count($mgrs) < 4) $mgrs[] = [];

/* 참석자 50명 (앞 25 / 뒤 25) */
$att = (array)($d['attend'] ?? []);
while (count($att) < 50) $att[] = [];
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>자위소방대 교육·훈련 기록부 — 인쇄</title>
<style>
*{box-sizing:border-box}
body{margin:0;background:#f5f7fb;color:#111;
  font-family:"Malgun Gothic","Apple SD Gothic Neo",system-ui,sans-serif;font-size:12px}
.topbar{position:sticky;top:0;z-index:10;background:#fff;border-bottom:1px solid #e3e8f0;
  padding:10px 16px;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.topbar a,.topbar button{padding:8px 15px;border:1px solid #d4dbe6;border-radius:8px;background:#fff;
  font-size:13px;font-weight:600;text-decoration:none;color:#1a2436;cursor:pointer;font-family:inherit}
.topbar .pri{background:#2563eb;border-color:#2563eb;color:#fff}
.topbar .sp{flex:1}
.sheet{background:#fff;max-width:196mm;margin:14px auto;padding:10mm;
  box-shadow:0 2px 14px rgba(0,0,0,.08)}
.law{font-size:10.5px;margin-bottom:3px}
.title{text-align:center;font-size:19px;font-weight:800;letter-spacing:2px;margin:4px 0 6px}
.guide{display:flex;justify-content:space-between;font-size:10px;margin-bottom:4px}
table{width:100%;border-collapse:collapse;table-layout:fixed}
th,td{border:1px solid #000;padding:3px 5px;font-size:11px;vertical-align:middle;
  word-break:break-all;line-height:1.5}
th{background:#f2f4f8;font-weight:700;text-align:center}
.lb{background:#f2f4f8;font-weight:700;text-align:center;width:80px}
.lb2{background:#f2f4f8;font-weight:700;text-align:center}
.big{height:52px;vertical-align:top}
.big2{height:78px;vertical-align:top}
.c{text-align:center}
.foot{margin-top:10px;text-align:center;font-size:10px;color:#444}
.pbreak{break-before:page;page-break-before:always}
.secline{margin-top:9px}
@media print{
  .topbar{display:none}
  body{background:#fff;font-size:11px}
  .sheet{box-shadow:none;margin:0;max-width:none;padding:0;width:auto}
  @page{size:A4;margin:12mm}
  th,td{font-size:10px;padding:2px 4px}
  .title{font-size:17px}
}
</style>
</head>
<body>

<div class="topbar">
  <a href="/jawi.php?stay=1">← 목록</a>
  <a href="/jawi_chat.php?id=<?=h(rawurlencode($id))?>">💬 문답으로 고치기</a>
  <a href="/jawi_edit.php?id=<?=h(rawurlencode($id))?>">표로 고치기</a>
  <span class="sp"></span>
  <button class="pri" onclick="window.print()">🖨 인쇄 · PDF 저장</button>
</div>

<div class="sheet">
  <div class="law">■ 화재의 예방 및 안전관리에 관한 법률 시행규칙 [별지 제13호서식]</div>
  <div class="title">자위소방대 및 초기대응체계 교육·훈련 실시 결과 기록부</div>
  <div class="guide">
    <span>※ [ ]에는 해당되는 곳에 √표를 합니다.</span><span>(앞쪽)</span>
  </div>

  <!-- 작성일자 / 작성자 -->
  <table>
    <colgroup><col style="width:80px"><col><col style="width:80px"><col style="width:120px"><col style="width:60px"></colgroup>
    <tr>
      <td class="lb">작성일자</td><td><?=$v('write_date')?></td>
      <td class="lb">작성자</td><td><?=$v('writer')?></td><td class="c">(서명)</td>
    </tr>
  </table>

  <!-- 소방안전관리대상물 -->
  <table class="secline">
    <colgroup><col style="width:80px"><col style="width:80px"><col><col style="width:80px"><col style="width:130px"></colgroup>
    <tr>
      <td class="lb" rowspan="5">소방안전<br>관리대상물</td>
      <td class="lb2">대상명</td><td><?=$v('site_name')?></td>
      <td class="lb2">대표자</td><td><?=$v('rep')?></td>
    </tr>
    <tr>
      <td class="lb2">소재지</td><td><?=$v('address')?></td>
      <td class="lb2">전화번호</td><td><?=$v('tel')?></td>
    </tr>
    <tr>
      <td class="lb2" rowspan="3">근무인원</td>
      <th colspan="2">평일</th><th colspan="1">휴일</th>
    </tr>
    <tr>
      <td class="c">주간 <?=$v('wd_day')?></td>
      <td class="c">야간 <?=$v('wd_night')?></td>
      <td class="c">주간 <?=$v('hd_day')?> / 야간 <?=$v('hd_night')?></td>
    </tr>
    <tr>
      <td class="lb2">등급</td>
      <td colspan="2" class="c">
        <?=$chk(($d['grade'] ?? '') === '특급')?> 특급 &nbsp;
        <?=$chk(($d['grade'] ?? '') === '1급')?> 1급 &nbsp;
        <?=$chk(($d['grade'] ?? '') === '2급')?> 2급 &nbsp;
        <?=$chk(($d['grade'] ?? '') === '3급')?> 3급
      </td>
    </tr>
  </table>

  <!-- 소방안전관리자 -->
  <table class="secline">
    <colgroup><col style="width:80px"><col><col style="width:80px"><col style="width:80px"><col style="width:90px"><col style="width:100px"><col style="width:70px"></colgroup>
    <tr>
      <td class="lb" rowspan="5">소방안전<br>관리자</td>
      <th>성명</th><th>선임일자</th><th>보유자격</th><th>자격구분</th><th>연락처</th><th>비고</th>
    </tr>
    <?php foreach (array_slice($mgrs, 0, 4) as $m): $t = (string)($m['type'] ?? ''); ?>
      <tr>
        <td><?=h((string)($m['name'] ?? ''))?></td>
        <td class="c"><?=h((string)($m['appt'] ?? ''))?></td>
        <td class="c"><?=h((string)($m['qual'] ?? ''))?></td>
        <td class="c"><?=$chk($t === '주')?> 주 <?=$chk($t === '보조')?> 보조</td>
        <td class="c"><?=h((string)($m['tel'] ?? ''))?></td>
        <td><?=h((string)($m['note'] ?? ''))?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <!-- 자위소방대 -->
  <table class="secline">
    <colgroup><col style="width:80px"><col style="width:90px"><col><col><col><col></colgroup>
    <tr>
      <td class="lb" rowspan="4">자위소방대</td>
      <th>총원(명)</th><th>대장성명</th><th>부대장(명)</th><th>통보연락(명)</th><th>초기소화(명)</th>
    </tr>
    <tr>
      <td class="c"><?=$v('jawi_total')?></td><td class="c"><?=$v('jawi_chief')?></td>
      <td class="c"><?=$v('jawi_vice')?></td><td class="c"><?=$v('jawi_call')?></td>
      <td class="c"><?=$v('jawi_fire')?></td>
    </tr>
    <tr>
      <th>대장연락처</th><th>피난유도(명)</th><th>비상연락(명)</th><th colspan="2"></th>
    </tr>
    <tr>
      <td class="c"><?=$v('jawi_chief_tel')?></td><td class="c"><?=$v('jawi_guide')?></td>
      <td class="c"><?=$v('jawi_emer')?></td><td colspan="2"></td>
    </tr>
  </table>

  <!-- 초기대응체계 -->
  <table class="secline">
    <colgroup><col style="width:80px"><col style="width:90px"><col></colgroup>
    <tr><td class="lb" rowspan="2">초기대응체계</td>
      <td class="lb2">조직구성</td><td><?=$v('init_org')?></td></tr>
    <tr><td class="lb2">총원(명)</td><td class="c"><?=$v('init_total')?></td></tr>
  </table>

  <!-- 교육·훈련 결과 -->
  <table class="secline">
    <tr><th colspan="7">교육 · 훈련 결과</th></tr>
  </table>
  <table>
    <colgroup><col style="width:80px"><col><col><col><col><col><col></colgroup>
    <tr>
      <td class="lb" rowspan="3">인원</td>
      <th colspan="3">자위소방대</th><th colspan="3">초기대응</th>
    </tr>
    <tr>
      <th>총원(명)</th><th>참석</th><th>미참석</th>
      <th>총원(명)</th><th>참석</th><th>미참석</th>
    </tr>
    <tr>
      <td class="c"><?=$v('jawi_total')?></td><td class="c"><?=$v('jawi_join')?></td><td class="c"><?=$v('jawi_absent')?></td>
      <td class="c"><?=$v('init_total')?></td><td class="c"><?=$v('init_join')?></td><td class="c"><?=$v('init_absent')?></td>
    </tr>
  </table>
  <table>
    <colgroup><col style="width:80px"><col></colgroup>
    <tr><td class="lb">일시/장소</td>
      <td><?=$v('edu_date')?><?= trim((string)($d['edu_place'] ?? '')) !== '' ? ' / ' . $v('edu_place') : '' ?></td></tr>
    <tr><td class="lb">주요내용</td><td class="big2"><?=nl2br($v('edu_content'))?></td></tr>
    <tr><td class="lb">보완사항</td><td class="big"><?=nl2br($v('edu_fix'))?></td></tr>
    <tr><td class="lb">조치사항</td><td class="big"><?=nl2br($v('edu_action'))?></td></tr>
  </table>

  <div class="foot">210mm×297mm[백상지(80g/㎡) 또는 중질지(80g/㎡)]</div>
</div>

<!-- ═══════════ 뒤쪽 : 참석확인 ═══════════ -->
<div class="sheet pbreak">
  <div class="guide"><span>□ 교육·훈련 참석확인</span><span>(뒤쪽)</span></div>
  <table>
    <colgroup>
      <col style="width:44px"><col style="width:110px"><col><col style="width:52px">
      <col style="width:44px"><col style="width:110px"><col><col style="width:52px">
    </colgroup>
    <tr>
      <th>연번</th><th>직책</th><th>성명</th><th>확인</th>
      <th>연번</th><th>직책</th><th>성명</th><th>확인</th>
    </tr>
    <?php for ($i = 0; $i < 25; $i++):
      $L = (array)($att[$i] ?? []); $R = (array)($att[$i + 25] ?? []); ?>
      <tr>
        <td class="c"><?=$i + 1?></td>
        <td><?=h((string)($L['role'] ?? ''))?></td>
        <td><?=h((string)($L['name'] ?? ''))?></td>
        <td class="c"><?=h((string)($L['ok'] ?? ''))?></td>
        <td class="c"><?=$i + 26?></td>
        <td><?=h((string)($R['role'] ?? ''))?></td>
        <td><?=h((string)($R['name'] ?? ''))?></td>
        <td class="c"><?=h((string)($R['ok'] ?? ''))?></td>
      </tr>
    <?php endfor; ?>
  </table>
</div>

</body>
</html>
