<?php
declare(strict_types=1);
// Included only after the private or token-based reader authorizes a supplied snapshot.
if (!defined('FP_DOCUMENT_AUTHORIZED') || !isset($plan) || !is_array($plan)) { http_response_code(404); exit; }
$shareReadOnly=$shareReadOnly??false;
// Keep printing out of the modal iframe and the interactive section reader.
// The calling route still authorizes the same saved plan before this template runs.
$printView=($_GET['print_view']??'')==='1';
if($printView)$bookView=false;
if(!function_exists('h')){function h($s):string{return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}}
$overview=fpo_status($plan);$bundle=(array)($plan['source_bundle']['groups']??[]);
$usages  = fp_usages();
$usage   = $usages[$plan['usage_code']] ?? ['nm'=>$plan['usage_code'],'cat'=>''];
$items   = fp_sections()['1']['items'] ?? [];
$s1      = (array)($plan['sections']['1']['data'] ?? []);   // 일반현황 (표지·머리말에 사용)
$skips   = fp_skip_rules($s1);

$bldName = $plan['building_name'] ?: '(대상명 미입력)';
/* 작성일: 사용자가 항목1에서 지정한 날짜 (없으면 오늘) */
$pd = trim((string)($plan['plan_date'] ?? ''));
$ts = ($pd !== '' && strtotime($pd)) ? strtotime($pd) : time();
$today = date('Y년 m월 d일', $ts);

/* 값이 비었는지 판정 */
function isEmptyVal($v): bool {
  if (is_array($v)) return count(array_filter($v, fn($x)=>trim((string)$x)!=='')) === 0;
  return trim((string)$v) === '';
}
/* 체크박스 목록 출력 (선택된 것만 · 없으면 '해당없음') */
function chkList(array $sel, bool $confirmed=false): string {
  $sel = array_values(array_filter($sel, fn($x)=>trim((string)$x)!==''));
  if (!$sel) return '<span class="none">'.($confirmed?'해당없음':'미입력 · 확인 필요').'</span>';
  return implode('', array_map(fn($x)=>'<span class="tag">✓ '.h($x).'</span>', $sel));
}
/* 값 없으면 - 표시 */
function v($x): string {
  $x = trim((string)$x);
  return $x === '' ? '<span class="none">-</span>' : h($x);
}
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=h($bldName)?> 소방계획서 — 소방계획서.com</title>
<style>
:root{--brand:#2563eb;--brand-dark:#17345f;--ink:#172033;--mut:#667085;--line:#d9e2ee;--line-dark:#b9c5d4;--soft:#f4f7fb;--ok:#047857;--warn:#b45309}
*{box-sizing:border-box;margin:0;padding:0}
html{background:#edf2f7}
body{background:#edf2f7;color:var(--ink);font-family:Pretendard,"Noto Sans KR","Malgun Gothic","맑은 고딕",system-ui,sans-serif;line-height:1.6;-webkit-font-smoothing:antialiased}

.fp-floor-heading{margin:18px 12px 8px;font-size:13px;color:var(--brand-dark);break-after:avoid;page-break-after:avoid}
.fp-floor-note{margin:8px 12px 14px;color:var(--mut);font-size:10.5px}
table.fp-floor-table{table-layout:fixed}table.fp-floor-table th{width:auto;white-space:normal}table.fp-floor-table th:nth-child(1){width:21%}table.fp-floor-table th:nth-child(2){width:16%}table.fp-floor-table th:nth-child(3){width:27%}table.fp-floor-table th:nth-child(4){width:18%}table.fp-floor-table th:nth-child(5){width:18%}

/* 화면 전용 툴바 */
.topbar{position:sticky;top:0;z-index:10;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;padding:12px 22px;background:rgba(255,255,255,.94);border-bottom:1px solid #dde4ed;backdrop-filter:blur(12px)}
.topbar .ttl{font-size:15px;font-weight:850;color:#15243a}
.topbar .ttl small{margin-left:8px;color:var(--mut);font-size:12.5px;font-weight:500}
.tb-btns{display:flex;gap:8px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:9px 15px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;color:#344054;font:inherit;font-size:13px;font-weight:750;text-decoration:none;cursor:pointer;transition:.15s}
.btn:hover{border-color:var(--brand);color:var(--brand)}
.btn--primary{background:var(--brand);border-color:var(--brand);color:#fff}
.btn--primary:hover{background:#1d4ed8;color:#fff}
.hint{max-width:880px;margin:14px auto 0;padding:0 14px;color:var(--mut);font-size:12px;text-align:center}

/* 화면 문서 */
.doc{max-width:880px;margin:18px auto 44px;padding:18mm 16mm;background:#fff;border:1px solid #e0e6ef;border-radius:12px;box-shadow:0 18px 55px rgba(27,45,75,.1)}

/* 표지 */
.cover{position:relative;display:flex;min-height:540px;flex-direction:column;align-items:center;justify-content:center;margin-bottom:28px;padding:50px 34px 42px;overflow:hidden;border:1px solid #dbe4ef;border-radius:12px;background:linear-gradient(180deg,#f8fbff 0,#fff 38%);text-align:center}
.cover::before{position:absolute;top:0;left:0;width:100%;height:8px;background:linear-gradient(90deg,var(--brand-dark),var(--brand),#60a5fa);content:""}
.cover-mark{display:flex;align-items:center;gap:9px;margin-bottom:42px;color:var(--brand-dark)}
.cover-mark strong{font-size:13px;font-weight:900;letter-spacing:.13em}
.cover-mark span{width:1px;height:12px;background:#bdc9d8}
.cover-mark small{color:#708096;font-size:9px;font-weight:750;letter-spacing:.09em}
.cover .cat{margin-bottom:14px;color:#67809f;font-size:11.5px;font-weight:750;letter-spacing:.14em}
.cover h1{position:relative;margin-bottom:0;padding:15px 34px;border-top:2px solid var(--brand-dark);border-bottom:2px solid var(--brand-dark);color:#102642;font-size:36px;font-weight:850;letter-spacing:.22em;line-height:1.35}
.cover .bld{margin:28px 0 6px;color:#192c48;font-size:20px;font-weight:800;letter-spacing:-.03em}
.cover .addr{max-width:560px;color:#64748b;font-size:12.5px;line-height:1.55}
.cover-service-principle{width:100%;max-width:620px;margin:20px 0 0;padding:16px 20px;border-top:2px solid #496c98;border-bottom:1px solid #dbe4ef;background:#f5f8fc;color:#455a73;text-align:center;word-break:keep-all;overflow-wrap:anywhere}
.cover-service-principle .principle-label{display:block;margin-bottom:8px;color:#365d8b;font-size:10px;font-weight:800;letter-spacing:.08em}
.cover-service-principle p{margin:0;font-size:12px;line-height:1.85}.cover-service-principle .principle-line{display:block}.cover-service-principle strong{color:#203e63;font-weight:800}
.cover .date{margin-top:12px;color:#536176;font-size:12.5px;font-weight:650}
.law-box{max-width:620px;margin-top:38px;padding:15px 17px;border:1px solid #d9e3ef;border-radius:9px;background:rgba(244,248,253,.9);color:#59677a;font-size:10.5px;line-height:1.7;text-align:left}
.law-title{margin-bottom:5px;color:#2d4f79;font-size:10.5px;font-weight:850}
.law-box strong{color:#314e72}
.law-box>p+p{margin-top:8px}
.law-review{margin-top:11px;padding-top:10px;border-top:1px solid #dfe6ee}
.law-review p{font-size:9.5px;line-height:1.7}
.law-ref{margin-top:7px;padding-top:7px;border-top:1px solid #dfe6ee;color:#748094;font-size:9.5px}

/* 항목 */
.sec{margin-bottom:18px;padding:0;overflow:hidden;border:1px solid var(--line);border-radius:9px;background:#fff;break-inside:avoid-page;page-break-inside:avoid}
.sec__h{display:flex;align-items:center;gap:10px;min-height:46px;padding:9px 12px;background:linear-gradient(90deg,#eef4fb,#f8fafc);border-bottom:1px solid var(--line)}
.sec__no{display:flex;width:27px;height:27px;flex-shrink:0;align-items:center;justify-content:center;border-radius:7px;background:var(--brand-dark);color:#fff;font-size:12px;font-weight:850}
.fp-appendix .sec__no{width:auto;min-width:48px;padding:0 7px;white-space:nowrap;font-size:10px}
.sec__t{color:#18314f;font-size:14px;font-weight:850;letter-spacing:-.025em}
.sec__skip{margin-left:auto;padding:3px 9px;border:1px solid #d9dee6;border-radius:999px;background:#fff;color:#8791a0;font-size:10.5px;white-space:nowrap}
.sec>table.d,.sec>.para,.sec>.empty-note,.sec>div:not(.sec__h){margin:12px}

/* 데이터 표 */
table.d{width:calc(100% - 24px);margin:12px;border:1px solid var(--line-dark);border-collapse:separate;border-spacing:0;border-radius:7px;overflow:hidden;font-size:11.7px}
table.d th,table.d td{padding:7px 9px;border:0;border-right:1px solid var(--line);border-bottom:1px solid var(--line);vertical-align:middle}
table.d tr:last-child>*{border-bottom:0}
table.d tr>*:last-child{border-right:0}
table.d th{width:96px;background:#f2f5f9;color:#34445a;font-weight:750;text-align:center;white-space:nowrap}
table.d th.w2{width:120px}
table.d td{color:#243247;word-break:break-word;overflow-wrap:anywhere}
.tag{display:inline-flex;align-items:center;margin:2px 4px 2px 0;padding:2px 7px;border:1px solid #cfe0fa;border-radius:999px;background:#eff5ff;color:#2457a5;font-size:10.5px;font-weight:650;white-space:nowrap}
.none{color:#9aa4b2}
.para{min-height:48px;padding:11px 13px;border:1px solid var(--line);border-radius:7px;background:#fbfcfe;color:#2d3b4f;font-size:11.8px;line-height:1.75;white-space:pre-wrap;overflow-wrap:anywhere}
.empty-note{padding:9px 11px;border:1px solid #f1d5a8;border-radius:7px;background:#fffbeb;color:var(--warn);font-size:11px}
.doc-foot{margin-top:26px;padding:14px 12px 0;border-top:1px solid #cbd5e1;color:#7a8595;font-size:10.5px;line-height:1.7;text-align:center}

@media screen and (max-width:680px){
  .topbar{padding:10px 12px}.topbar .ttl small{display:none}.tb-btns{width:100%}.tb-btns .btn{flex:1}
  .doc{margin:10px;padding:18px 13px;border-radius:10px}
  .cover{min-height:460px;padding:42px 18px 32px}.cover-mark{margin-bottom:30px}.cover h1{padding:12px 20px;font-size:27px;letter-spacing:.15em}.cover .bld{font-size:18px}
  .law-box{margin-top:28px}.sec__h{align-items:flex-start}.sec__skip{white-space:normal;text-align:right}
  table.d{font-size:10.8px}table.d th{width:76px;padding:6px 5px;font-size:10.5px;white-space:normal}table.d th.w2{width:88px}table.d td{padding:6px}
}

/* A4 인쇄 */
@page{size:A4 portrait;margin:13mm 12mm 14mm}
@media print{
  html,body{width:100%;min-height:auto;background:#fff}
  body{font-family:"Noto Sans KR","Malgun Gothic",sans-serif;color:#111827;font-size:9pt;line-height:1.45;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .topbar,.hint,[class*="quickmemo"],[id*="quickmemo"]{display:none!important}
  .doc{max-width:none;margin:0;padding:0;border:0;border-radius:0;box-shadow:none}
  .cover{min-height:calc(297mm - 27mm);margin:0;padding:16mm 15mm;border:1px solid #cbd5e1;border-radius:0;background:#fff;break-after:page;page-break-after:always}
  .cover::before{height:4mm}
  .cover-service-principle{margin-top:5mm;padding:4mm 5mm;border-top:0.5mm solid #496c98;break-inside:avoid;page-break-inside:avoid}
  .cover-service-principle .principle-label{font-size:8pt;margin-bottom:2mm}.cover-service-principle p{font-size:9pt;line-height:1.8}
  .cover-mark{margin-bottom:20mm}.cover .cat{font-size:8.5pt}.cover h1{font-size:28pt}.cover .bld{margin-top:12mm;font-size:16pt}.cover .addr,.cover .date{font-size:9pt}
  .law-box{margin-top:17mm;padding:4mm 5mm;border-radius:1.5mm;background:#f4f7fb;font-size:7.5pt}.law-title{font-size:8pt}.law-ref{font-size:7pt}
  .law-box>p+p{margin-top:2.5mm}.law-review{margin-top:3mm;padding-top:3mm}.law-review p{font-size:7pt}
  .sec{margin-bottom:4mm;border-color:#cbd5e1;border-radius:1.5mm;box-shadow:none;break-inside:avoid-page;page-break-inside:avoid}
  .sec__h{min-height:11mm;padding:2.2mm 3mm;background:#edf3f9;border-bottom-color:#cbd5e1}
  .sec__no{width:7mm;height:7mm;border-radius:1.5mm;font-size:8pt}.sec__t{font-size:10.5pt}.sec__skip{font-size:7.2pt}
  table.d{width:calc(100% - 6mm);margin:3mm;border-radius:1mm;font-size:8.3pt}
  table.d th,table.d td{padding:1.8mm 2.2mm}table.d th{width:24mm;font-size:8pt}table.d th.w2{width:29mm}
  .tag{padding:.4mm 1.5mm;font-size:7.2pt}.para{min-height:12mm;margin:3mm!important;padding:2.5mm 3mm;font-size:8.3pt}.empty-note{margin:3mm!important;padding:2mm 3mm;font-size:8pt}
  .doc-foot{margin-top:6mm;padding-top:3mm;font-size:7.2pt;break-inside:avoid}
}
</style>
<link rel="stylesheet" href="/fire_plan_document.css?v=20261010-blue-1">
<?php if($shareReadOnly&&!$printView): ?>
<link rel="stylesheet" href="/fire_plan_team_view.css?v=20261010-1">
<script src="/fire_plan_team_view.js?v=20261010-1" defer></script>
<meta name="description" content="공유된 소방계획서에서 자신의 이름과 역할을 선택해 담당 임무를 확인하세요.">
<?php endif; ?>
<?php if(!empty($bookView)): ?>
<link rel="stylesheet" href="/fire_plan_book.css?v=20261010-2">
<link rel="stylesheet" href="/fire_plan_reader.css?v=20261013-sections-2">
<script src="/fire_plan_reader.js?v=20261021-manage-1" defer></script>
<link rel="stylesheet" href="/fire_plan_share.css?v=20261010-1">
<script src="/fire_plan_share.js?v=20261015-partner-1" defer></script>
<?php endif; ?>
<?php require __DIR__.'/evacuation_location_assets.php'; ?>
<link rel="stylesheet" href="/evacuation_plan_document.css?v=20261021-kakao-1">
<?php if(!empty($bookView)&&!$shareReadOnly):?><link rel="stylesheet" href="/fire_plan_book_manage.css?v=20261021-1"><?php endif;?>
<link rel="stylesheet" href="/fire_plan_team_document.css?v=20261021-1">
<link rel="stylesheet" href="/fire_plan_training_forms.css?v=20261021-1">
<link rel="stylesheet" href="/fire_plan_print_layout.css?v=20261015-safari-2">
<script src="/fire_plan_print.js?v=20261021-kakao-1" defer></script>
</head>
<body class="<?=!empty($bookView)?'fp-book-reader ':''?><?=$shareReadOnly?'fp-shared':(($_GET['modal']??'')==='1'?'fp-embedded':'')?>">
<?php if($shareReadOnly&&!$printView) require __DIR__.'/fire_plan_team_view.php'; ?>

<?php if($printView): ?>
<div class="topbar">
  <div class="ttl"><?=h(fp_plan_year($plan))?>년 소방계획서 <small>인쇄용 문서 · <?=h($bldName)?></small></div>
  <div class="tb-btns"><button class="btn btn--primary" onclick="window.fpPrintDocument ? window.fpPrintDocument() : window.print()">인쇄 / PDF 저장</button></div>
</div>
<?php elseif(!empty($bookView)): require __DIR__.'/fire_plan_book_toolbar.php'; else: ?>
<div class="topbar">
  <div class="ttl">소방계획서 <small><?=h($bldName)?> · <?=h($s1['main_use']??'')?></small></div>
  <div class="tb-btns">
    <?php if (!$shareReadOnly): ?><a class="btn" href="<?=h($backUrl)?>">← 완성 현황</a><?php else: ?><button type="button" class="btn fp-team-back" data-team-back hidden>← 내 임무 확인</button><span class="btn" style="cursor:default;background:#eff6ff;color:#1d4ed8">읽기 전용 · <?=fp_plan_year($plan)?>년</span><?php endif; ?>
    <button class="btn btn--primary" onclick="window.fpPrintDocument ? window.fpPrintDocument() : window.print()">🖨️ 인쇄 / PDF 저장</button>
  </div>
</div>
<?php endif; ?>
<?php if(!empty($bookView))require __DIR__.'/fire_plan_reader.php'; ?>
<div class="hint">
  <?php if ($shareReadOnly): ?><p>최근 저장 <?=h($plan['updated_at']??'')?> · 열 때마다 이 연도의 최신 저장 내용을 보여드립니다.</p><?php endif; ?>
  <p>표지·목차 → 본문 1~15번 → 별첨 순서로 인쇄합니다. 각 항목은 새 A4 페이지에서 시작하며, 긴 내용은 다음 장으로 이어집니다.</p>
  <b>PDF로 저장하려면</b> 인쇄를 누른 뒤 대상(프린터)에서 <b>‘PDF로 저장’</b>을 선택하세요.
</div>

<div class="doc" id="fp-shared-document">

  <!-- ── 표지 ── -->
 <div class="cover">
    <div class="cover-mark"><strong>소방계획서.com</strong><span></span><small>소방안전관리 계획</small></div>
    <div class="cat"><?=fp_plan_year($plan)?>년 연간 소방계획</div>

    <h1>소방계획서</h1>

    <div class="bld"><?=h($bldName)?></div>

    <?php if (!empty($s1['addr'])): ?>
        <div class="addr">
            <?=h($s1['addr'])?>
        </div>
    <?php endif; ?>

    <div class="date"><?=h($today)?></div>

    <div class="law-box">
      <div class="law-title">작성 근거</div>
      <p>공개된 「소방계획서 작성매뉴얼」의 ‘소방계획서 작성 시 주의사항’에서는 대상물의 특성에 따라 권장서식을 수정·추가하거나 자체서식을 첨부할 수 있도록 안내하고 있으며, 「화재의 예방 및 안전관리에 관한 법률 시행령」 제27조제1항에 따른 사항을 포함하도록 명시하고 있습니다.</p>
      <p>본 소방계획서는 <strong>위 권장서식의 작성 안내를 참고하여, 「화재의 예방 및 안전관리에 관한 법률」 제24조 및 같은 법 시행령 제27조제1항에서 정한 작성사항을 근거로 구성하였습니다.</strong></p>
      <div class="law-review">
        <div class="law-title"><?=$overview['reviewed']?'✓ 작성 내용 최종 확인 완료':'작성 중인 계획서 · 최종 확인 전'?></div>
        <p>대상물에 해당하는 작성사항과 실제 시설·인원·피난경로를 확인하여 내용을 보완하고, 변경사항이 생기면 갱신해 주세요.</p>
      </div>
      <div class="law-ref">항목 채움 <?=$overview['filled']?> / <?=$overview['total']?>개 · 자료 <?=count($bundle)?>종 반영<?php if(!empty($plan['source_bundle']['imported_at'])):?> · <?=h($plan['source_bundle']['imported_at'])?> 기준<?php endif;?></div>
    </div>
    <div class="cover-service-principle"><span class="principle-label">서비스 운영 원칙</span><p><span class="principle-line">소방계획서.com은 <strong>관련 법령에서 정한 필수 사항을 정확히 반영</strong>하는 것을 기본 원칙으로 삼고,</span> <span class="principle-line"><strong>소방청의 권장사항에 최대한 부합</strong>하도록 서비스를 지속적으로 점검하고 개선하고 있습니다.</span></p></div>
</div>

  <section class="fp-doc-summary">
    <p class="fp-doc-kicker">계획서 구성</p><h2>우리 건물 안전관리 한눈에 보기</h2>
    <div class="fp-doc-stats"><div><small>소방안전관리자</small><strong><?=v($s1['mgr_name']??'')?></strong><span><?=v($s1['mgr_tel']??'')?></span></div><div><small>관리 등급</small><strong><?=v($s1['grade']??'')?></strong><span><?=v($s1['main_use']??'')?></span></div><div><small>대피 후 집결지</small><strong><?=v($plan['sections']['5']['data']['assembly']??'')?></strong><span>저장된 피난계획 기준</span></div></div>
    <h3>계획서 구성</h3><ol class="fp-doc-toc"><?php foreach($items as $n=>$title):$st=$overview['sections'][$n];?><li><span><?=h($n)?>. <?=h($title)?></span><small><?=$st['skip']?'해당 없음':($st['done']?'내용 입력':'확인 필요')?></small></li><?php endforeach;?></ol>
    <?php if($bundle):?><div class="fp-doc-source"><h3>반영한 자료</h3><p><?=h(implode(' · ',array_column($bundle,'title')))?></p><small>자료 반영 시점의 내용이 보관됩니다. 원본 자료의 변경은 다시 반영한 뒤 확인해 주세요.</small></div><?php endif;?>
  </section>
  <!-- ── 15개 항목 ── -->
  <?php foreach ($items as $code => $title):
    $code = (string)$code;
    $d    = (array)($plan['sections'][$code]['data'] ?? []);
    $isSkipped = isset($skips[$code]);
  ?>
  <section class="sec" id="section-<?=h($code)?>">
    <div class="sec__h">
      <div class="sec__no"><?=h($code)?></div>
      <div class="sec__t"><?=h($title)?></div>
      <?php if(!$isSkipped&&!$overview['sections'][$code]['done']):?><div class="sec__skip">확인 필요</div><?php endif;?>
      <?php if ($isSkipped): ?><div class="sec__skip"><?=h($skips[$code])?></div><?php endif; ?>
    </div>

    <?php if ($isSkipped): ?>
      <div class="para"><span class="none">해당사항 없음</span></div>

    <?php elseif ($code === '1'): /* ── 일반현황 (서식 1.1) ── */ ?>
      <table class="d">
        <tr><th>명 칭</th><td colspan="3"><?=v($d['name'] ?? '')?></td></tr>
        <tr><th>도로명주소</th><td colspan="3"><?=v($d['addr'] ?? '')?></td></tr>
        <tr>
          <th>대표자</th><td><?=v($d['rep_name'] ?? '')?> <?=!empty($d['rep_tel'])?'('.h($d['rep_tel']).')':''?></td>
          <th class="w2">소방안전관리자</th><td><?=v($d['mgr_name'] ?? '')?> <?=!empty($d['mgr_tel'])?'('.h($d['mgr_tel']).')':''?></td>
        </tr>
        <tr>
          <th>대상물 급수</th><td><?=v($d['grade'] ?? '')?></td>
          <th class="w2">주용도</th><td><?=v($d['main_use'] ?? '')?></td>
        </tr>
        <tr>
          <th>연면적</th><td><?=!empty($d['area'])? h($d['area']).' ㎡' : '<span class="none">-</span>'?></td>
          <th class="w2">건축면적</th><td><?=!empty($d['bld_area'])? h($d['bld_area']).' ㎡' : '<span class="none">-</span>'?></td>
        </tr>
        <tr>
          <th>층수 / 높이</th><td><?=v($d['floors'] ?? '')?> <?=!empty($d['height'])?' / '.h($d['height']).'m':''?></td>
          <th class="w2">구조 / 지붕</th><td><?=v($d['structure'] ?? '')?> <?=!empty($d['roof'])?' / '.h($d['roof']):''?></td>
        </tr>
        <tr>
          <th>사용승인일</th><td><?=v($d['approval'] ?? '')?></td>
          <th class="w2">수신기 위치</th><td><?=v($d['recv_loc'] ?? '')?></td>
        </tr>
        <tr><th>승강기</th><td colspan="3"><?=chkList($d['elev'] ?? [],in_array('elev',(array)($d['_chat_answers']??[]),true))?></td></tr>
        <tr><th>주차장</th><td colspan="3"><?=chkList($d['park'] ?? [],in_array('park',(array)($d['_chat_answers']??[]),true))?>
          <?php if (($d['ev'] ?? '없음')==='있음'): ?><span class="tag">✓ 전기차충전소</span><?php endif; ?></td></tr>
        <tr><th>계 단</th><td colspan="3"><?=chkList($d['stairs'] ?? [],in_array('stairs',(array)($d['_chat_answers']??[]),true))?></td></tr>
        <tr>
          <th>운영시간<br>(평일)</th><td>주간 <?=v($d['wd_day'] ?? '')?><br>야간 <?=v($d['wd_night'] ?? '')?></td>
          <th class="w2">운영시간<br>(휴일)</th><td>주간 <?=v($d['hd_day'] ?? '')?><br>야간 <?=v($d['hd_night'] ?? '')?></td>
        </tr>
        <tr><th>인원현황</th><td colspan="3">
          근무 <b><?=v($d['staff'] ?? '')?></b>명 ·
          거주 <b><?=v($d['resident'] ?? '')?></b>명 ·
          최대수용 <b><?=v($d['use_cnt'] ?? '')?></b>명
          <?php if (!empty($plan['jawi_type'])): ?>
            <span class="tag">자위소방대 <?=h($plan['jawi_type']==='PUBLIC'?'공공기관형':'Type-'.$plan['jawi_type'])?></span>
          <?php endif; ?>
        </td></tr>
        <tr><th>해당 여부</th><td colspan="3">
          공공기관 <b><?=h($d['public'] ?? '미확인')?></b> ·
          권원분리 <b><?=h($d['split'] ?? '미확인')?></b> ·
          공동관리 <b><?=h($d['joint'] ?? '미확인')?></b> ·
          위험물 <b><?=h($d['hazmat'] ?? '미확인')?></b>
        </td></tr>
        <tr><th>화재보험</th><td colspan="3">
          <?php if (($d['ins'] ?? '미가입') === '가입'): ?>
            <?=v($d['ins_co'] ?? '')?> · 기간 <?=v($d['ins_term'] ?? '')?> ·
            대인 <?=v($d['ins_life'] ?? '')?> · 대물 <?=v($d['ins_prop'] ?? '')?>
          <?php else: ?><span class="none"><?=h(($d['ins']??'')==='미가입'?'미가입':'미확인')?></span><?php endif; ?>
        </td></tr>
      </table>


      <h3 class="fp-floor-heading">층별 면적·용도 현황</h3>
      <?php $generalFloorDongs=array_values(array_filter((array)($bundle['basic']['appendix']['dongs']??[]),static fn($r)=>is_array($r)&&!empty($r['floors']))); ?>
      <?php if(!$generalFloorDongs): ?>
        <p class="empty-note">반영된 층별 자료가 없습니다. 기본정보에서 건축물대장 층별 현황을 확인한 뒤 ‘최신정보 반영하기’로 기본정보를 반영해 주세요.</p>
      <?php else: ?>
        <table class="d fp-floor-table">
          <thead><tr><th scope="col">동 · 층</th><th scope="col">면적(㎡)</th><th scope="col">용도</th><th scope="col">구조</th><th scope="col">자료</th></tr></thead>
          <tbody>
          <?php foreach($generalFloorDongs as $floorDong):foreach((array)$floorDong['floors'] as $floorRow):if(!is_array($floorRow))continue; ?>
            <tr><td><b><?=v($floorDong['name']??'')?></b><br><?=v($floorRow['label']??'')?></td>
              <td><?=v(($floorRow['area']??'')!==''?$floorRow['area']:'자료 없음')?></td>
              <td><?=v($floorRow['use']??'')?></td><td><?=v($floorRow['structure']??'')?></td>
              <td><?=h(($floorRow['source']??'')==='manual'?'직접 입력':(($floorRow['source']??'')==='registry'?'건축물대장':'자료 없음'))?><?=!empty($floorRow['excluded'])?'<br>면적 제외 항목 포함':''?></td></tr>
          <?php endforeach;endforeach; ?>
          </tbody>
        </table>
        <p class="fp-floor-note">기본정보를 반영한 시점의 자료입니다. 같은 층에 여러 용도가 있으면 층별 면적을 합산해 표시하며, 연면적 산정 값과 다를 수 있습니다. 직접 수정한 값은 ‘직접 입력’으로 표시합니다.</p>
      <?php endif; ?>

    <?php elseif ($code === '2'): /* ── 소방시설 현황 (서식 1.4) ── */ ?>
      <table class="d">
        <tr><th>소화설비</th><td><?=chkList($d['fire_ext'] ?? [],in_array('fire_ext',(array)($d['_chat_answers']??[]),true))?></td></tr>
        <tr><th>경보설비</th><td><?=chkList($d['alarm'] ?? [],in_array('alarm',(array)($d['_chat_answers']??[]),true))?></td></tr>
        <tr><th>피난구조<br>설비</th><td><?=chkList($d['escape'] ?? [],in_array('escape',(array)($d['_chat_answers']??[]),true))?></td></tr>
        <tr><th>소화용수<br>설비</th><td><?=chkList($d['water'] ?? [],in_array('water',(array)($d['_chat_answers']??[]),true))?></td></tr>
        <tr><th>소화활동<br>설비</th><td><?=chkList($d['active'] ?? [],in_array('active',(array)($d['_chat_answers']??[]),true))?></td></tr>
        <tr><th>기타시설</th><td><?=chkList($d['etc_fac'] ?? [],in_array('etc_fac',(array)($d['_chat_answers']??[]),true))?></td></tr>
        <?php if (!empty($d['memo'])): ?>
          <tr><th>비 고</th><td><div style="white-space:pre-wrap"><?=h($d['memo'])?></div></td></tr>
        <?php endif; ?>
      </table>

    <?php elseif ($code === '3' || $code === '4'): /* ── 점검·정비 계획 ── */ ?>
      <?php $isSelf = ($code === '3');
            $rows = $isSelf ? ['작동점검','종합점검','외관점검'] : ['소방시설','피난시설','방화시설']; ?>
      <table class="d">
        <tr><th>구 분</th><th>실시 시기</th><th>담당자</th><th>비 고</th></tr>
        <?php foreach ($rows as $i => $rn): $n = $i+1;
          if ($isSelf && $n === 2 && !fp_comprehensive($s1,$d)) continue;
        ?>
        <tr>
          <th><?=h($rn)?></th>
          <td><?=v($d["r{$n}_when"] ?? '')?></td>
          <td><?=v($d["r{$n}_who"] ?? '')?></td>
          <td><?=v($d["r{$n}_note"] ?? '')?></td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php if (!empty($d['memo'])): ?>
        <div style="margin-top:8px"><div class="para"><?=h($d['memo'])?></div></div>
      <?php endif; ?>

    <?php elseif ($code === '5'): /* ── 피난계획 ── */ ?>
      <?php $evacPrint=(array)($bundle['evacuation']['appendix']['document']??[]); ?>
      <?php if(!empty($evacPrint['plans'])): ?>
        <div class="fp-evacuation-print">
        <?php
        require_once __DIR__.'/evacuation_plan_list_common.php';
        if(!defined('EPL_DOCUMENT_AUTHORIZED'))define('EPL_DOCUMENT_AUTHORIZED',true);
        (static function(array $snapshot):void {$rows=$snapshot['plans'];$building=$snapshot['building'];require __DIR__.'/evacuation_plan_document.php';})($evacPrint);
        ?>
        </div>
        <?php $evacExtra=[];foreach(fp_chat_schema()['5'] as $field=>$spec){$value=$d[$field]??null;if(($value!==null&&$value!==''&&$value!==[])&&(!array_key_exists($field,(array)($d['_source_values']??[]))||$value!==$d['_source_values'][$field]))$evacExtra[$field]=[$spec['label'],$value];} ?>
        <?php if($evacExtra): ?><p class="fp-floor-note">소방계획서에서 별도로 작성한 추가 내용</p><table class="d"><?php foreach($evacExtra as [$extraLabel,$extraValue]): ?><tr><th><?=h($extraLabel)?></th><td class="preserve"><?=is_array($extraValue)?chkList($extraValue):v($extraValue)?></td></tr><?php endforeach; ?></table><?php endif; ?>
      <?php else: ?>
      <table class="d">
        <tr><th>피난층</th><td colspan="3"><?=v($d['floor_exit'] ?? '')?></td></tr>
        <tr><th>피난경로</th><td colspan="3"><div style="white-space:pre-wrap"><?=v($d['route'] ?? '')?></div></td></tr>
        <tr><th>피난기구</th><td colspan="3"><?=chkList($d['evac'] ?? [],in_array('evac',(array)($d['_chat_answers']??[]),true))?></td></tr>
        <tr>
          <th>화재안전<br>취약자</th>
          <td colspan="3">
            인원 <b><?=v($d['weak_cnt'] ?? '')?></b>명 · 위치 <?=v($d['weak_loc'] ?? '')?>
            <?php if (!empty($d['weak_plan'])): ?>
              <div style="white-space:pre-wrap;margin-top:5px;padding-top:5px;border-top:1px dashed #ddd"><?=h($d['weak_plan'])?></div>
            <?php endif; ?>
          </td>
        </tr>
        <tr><th>집결지</th><td colspan="3"><?=v($d['assembly'] ?? '')?></td></tr>
      </table>

        <p class="empty-note">피난계획 카드의 인쇄 문서를 포함하려면 ‘사용 계획’으로 저장한 뒤 ‘최신정보 반영하기’에서 피난계획을 반영해 주세요.</p>
      <?php endif; ?>

    <?php elseif ($code === '6'): /* ── 방화구획·방염 ── */ ?>
      <?php require_once __DIR__.'/flame_retardant_plan_common.php'; $flameChecks=frgp_checks($d); ?>
      <table class="d">
        <?php foreach($flameChecks as $flameCheck):?><tr><th>방염 적용<br>확인</th><td><div style="white-space:pre-wrap;overflow-wrap:anywhere"><?=h(frgp_text($flameCheck))?></div></td></tr><?php endforeach;?>
        <?php if($flameChecks):?><tr><th>확인 범위</th><td><small class="none">저장된 동의 선택 조건에 따른 확인 내용입니다. 미확인 동은 별도로 확인하며, 실제 설치 물품과 방염성능검사 필증은 아래 현황에서 확인합니다.</small></td></tr><?php endif;?>
        <tr><th>방화구획<br>기준</th><td><?=chkList($d['bkchk'] ?? [],in_array('bkchk',(array)($d['_chat_answers']??[]),true))?></td></tr>
        <tr><th>구획설비</th><td><?=chkList($d['bkdoor'] ?? [],in_array('bkdoor',(array)($d['_chat_answers']??[]),true))?></td></tr>
        <tr><th>제연설비</th><td><?=chkList($d['smoke'] ?? [],in_array('smoke',(array)($d['_chat_answers']??[]),true))?></td></tr>
        <tr><th>내부 마감재</th><td><?=v($d['finish'] ?? '')?></td></tr>
        <tr><th>방염물품</th><td><?=chkList($d['flame'] ?? [],in_array('flame',(array)($d['_chat_answers']??[]),true))?>
          <span class="tag">성능검사 필증 <?=h($d['flame_cert'] ?? '미확인')?></span></td></tr>
        <?php if (!empty($d['memo'])): ?>
          <tr><th>유지관리<br>계획</th><td><div style="white-space:pre-wrap"><?=h($d['memo'])?></div></td></tr>
        <?php endif; ?>
      </table>

    <?php elseif ($code === '9'): /* 자위소방대 조직 및 대원별 임무 */ ?>
      <?php $teamMembers=array_values(array_filter((array)($bundle['team']['appendix']['members']??[]),'is_array')); ?>
      <table class="d">
        <tr><th>편성 유형</th><td>
          <?php if (!empty($plan['jawi_type'])): ?>
            <b><?=h($plan['jawi_type']==='PUBLIC'?'공공기관형':'Type-'.$plan['jawi_type'])?></b>
            <span style="color:#777;font-size:11.5px">(연면적·근무인원 기준 자동 판정)</span>
          <?php else: ?><span class="none">-</span><?php endif; ?>
        </td></tr>
      </table>
      <?php if(!empty($bundle['team']['appendix']['document'])): $teamDocument=$bundle['team']['appendix']['document'];require __DIR__.'/fire_plan_team_document.php'; ?>
      <?php elseif($teamMembers): ?>
      <table class="d"><thead><tr><th scope="col">조직 · 역할</th><th scope="col">성명</th><th scope="col">연락처</th><th scope="col">임무</th></tr></thead><tbody>
        <?php foreach($teamMembers as $member): ?><tr><td><?=v($member['role']??'')?></td><td><?=v($member['name']??'')?></td><td><?=v($member['tel']??'')?></td><td class="preserve"><?=v($member['task']??'')?></td></tr><?php endforeach; ?>
      </tbody></table>
      <p class="fp-floor-note">이전에 반영한 대원별 임무입니다. 조직도와 편성표 전체를 포함하려면 ‘최신정보 반영하기’에서 자위소방대 편성을 다시 반영해 주세요.</p>
      <?php else: ?>
      <p class="empty-note">반영된 편성·임무 자료가 없습니다. 자위소방대 편성표를 저장한 뒤 ‘최신정보 반영하기’에서 자위소방대 편성을 반영해 주세요.</p>
      <?php endif; ?>
      <?php if(!empty($d['memo'])&&(!$teamMembers||($d['memo']!==($d['_source_values']['memo']??null)))): ?>
        <div class="para"><?=h($d['memo'])?></div>
      <?php endif; ?>

    <?php elseif ($code === '11'): /* ── 소방훈련·교육 ── */ ?>
      <?php $scheduled=(array)($d['_training_schedule']['events']??[]); if($scheduled): require_once __DIR__.'/fire_plan_schedule_common.php'; ?>
      <p><b>연간 교육·훈련 예정 일정</b> <small>※ 실제 실시 결과는 해당 기록부에 별도 작성합니다.</small></p>
      <table class="d"><tr><th>구분</th><th>예정 시기</th><th>대상 · 장소</th><th>교육·훈련 내용</th></tr>
      <?php foreach($scheduled as $event):?><tr><th><?=h(fpsc_kinds()[$event['kind']]??'교육·훈련')?></th><td><?=h(fpsc_label($event))?></td><td><?=h($event['who'])?><br><small><?=h($event['place']?:'장소 미정')?></small></td><td><?=h($event['content'])?></td></tr><?php endforeach;?></table>
      <?php endif;?>
      <table class="d">
        <tr><th>구 분</th><th>실시 시기</th><th>대 상</th><th>방 법</th></tr>
        <?php foreach (['소방훈련','소방교육','신규자 교육'] as $i => $rn): $n = $i+1; if($scheduled&&$n<3)continue; ?>
        <tr>
          <th><?=h($rn)?></th>
          <td><?=v($d["t{$n}_when"] ?? '')?></td>
          <td><?=v($d["t{$n}_who"] ?? '')?></td>
          <td><?=v($d["t{$n}_how"] ?? '')?></td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php if (!empty($d['memo'])): ?>
        <div style="margin-top:8px"><div class="para"><?=h($d['memo'])?></div></div>
      <?php endif; ?>

      <?php require __DIR__.'/fire_plan_training_forms.php'; ?>

    <?php elseif ($code === '14'): /* ── 화재 초기대응 ── */ ?>
      <table class="d">
        <tr><th>① 화재경보</th><td><?=v($d['s1'] ?? '')?></td></tr>
        <tr><th>② 화재신고</th><td><?=v($d['s2'] ?? '')?></td></tr>
        <tr><th>③ 초기소화</th><td><?=v($d['s3'] ?? '')?></td></tr>
        <tr><th>④ 피난유도</th><td><?=v($d['s4'] ?? '')?></td></tr>
        <tr><th>⑤ 인원확인</th><td><?=v($d['s5'] ?? '')?></td></tr>
        <?php if (!empty($d['memo'])): ?>
          <tr><th>비 고</th><td><div style="white-space:pre-wrap"><?=h($d['memo'])?></div></td></tr>
        <?php endif; ?>
      </table>

    <?php else: /* ── 메모형 항목 (7·8·10·12·13·15) ── */ ?>
      <?php $m = trim((string)($d['memo'] ?? '')); ?>
      <?php if ($m === ''): ?>
        <div class="empty-note"><?=in_array('memo',(array)($d['_chat_answers']??[]),true)?'확인한 추가 기재사항 없음':'아직 작성되지 않았습니다. 내용을 확인해 주세요.'?></div>
      <?php else: ?>
        <div class="para"><?=h($m)?></div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>

  <span id="plan-references" aria-hidden="true"></span>
  <?php require __DIR__.'/fire_plan_appendix.php'; ?>
  <?php if(!empty($bookView)&&empty($readerReferences)):?><p class="fpr-no-references">아직 반영한 상세 자료가 없습니다.<br>계획서에 자료를 반영하면 여기에서 확인할 수 있어요.</p><?php endif;?>
  <div class="doc-foot">작성일 <?=h($today)?> · 소방안전관리자 <?=h($s1['mgr_name'] ?? '________')?> (서명)<br>소방계획서.com · <?=fp_plan_year($plan)?>년 안전관리 계획</div>

</div>

<?php if (!$shareReadOnly&&!$printView) require_once __DIR__.'/admin_quickmemo_widget.php'; ?>
</body>
</html>
