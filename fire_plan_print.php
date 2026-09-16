<?php
// fire_plan_print.php — 소방계획서 인쇄 / PDF 저장
//   입력한 15개 법정항목(시행령 제27조 1항)을 하나의 문서로 조립합니다.
declare(strict_types=1);

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

require_once __DIR__ . '/fire_plan_db.php';

$plan = fp_load_plan((string)($_GET['id'] ?? ''));
if (!$plan) { header('Location: /fire_plan.php'); exit; }
$planId  = (string)$plan['id'];
$usages  = fp_usages();
$usage   = $usages[$plan['usage_code']] ?? ['nm'=>$plan['usage_code'],'cat'=>''];
$items   = fp_sections()['1']['items'] ?? [];
$s1      = fp_get_section($planId, '1');   // 일반현황 (표지·머리말에 사용)
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
function chkList(array $sel): string {
  $sel = array_values(array_filter($sel, fn($x)=>trim((string)$x)!==''));
  if (!$sel) return '<span class="none">해당없음</span>';
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
<title><?=h($bldName)?> 소방계획서 — TWORIX</title>
<style>
:root{--brand:#2563eb;--brand-dark:#17345f;--ink:#172033;--mut:#667085;--line:#d9e2ee;--line-dark:#b9c5d4;--soft:#f4f7fb;--ok:#047857;--warn:#b45309}
*{box-sizing:border-box;margin:0;padding:0}
html{background:#edf2f7}
body{background:#edf2f7;color:var(--ink);font-family:Pretendard,"Noto Sans KR","Malgun Gothic","맑은 고딕",system-ui,sans-serif;line-height:1.6;-webkit-font-smoothing:antialiased}

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
.cover .date{margin-top:12px;color:#536176;font-size:12.5px;font-weight:650}
.law-box{max-width:620px;margin-top:38px;padding:15px 17px;border:1px solid #d9e3ef;border-radius:9px;background:rgba(244,248,253,.9);color:#59677a;font-size:10.5px;line-height:1.7;text-align:left}
.law-title{margin-bottom:5px;color:#2d4f79;font-size:10.5px;font-weight:850}
.law-box strong{color:#314e72}
.law-ref{margin-top:7px;padding-top:7px;border-top:1px solid #dfe6ee;color:#748094;font-size:9.5px}

/* 항목 */
.sec{margin-bottom:18px;padding:0;overflow:hidden;border:1px solid var(--line);border-radius:9px;background:#fff;break-inside:avoid-page;page-break-inside:avoid}
.sec__h{display:flex;align-items:center;gap:10px;min-height:46px;padding:9px 12px;background:linear-gradient(90deg,#eef4fb,#f8fafc);border-bottom:1px solid var(--line)}
.sec__no{display:flex;width:27px;height:27px;flex-shrink:0;align-items:center;justify-content:center;border-radius:7px;background:var(--brand-dark);color:#fff;font-size:12px;font-weight:850}
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

@media(max-width:680px){
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
  .cover-mark{margin-bottom:20mm}.cover .cat{font-size:8.5pt}.cover h1{font-size:28pt}.cover .bld{margin-top:12mm;font-size:16pt}.cover .addr,.cover .date{font-size:9pt}
  .law-box{margin-top:17mm;padding:4mm 5mm;border-radius:1.5mm;background:#f4f7fb;font-size:7.5pt}.law-title{font-size:8pt}.law-ref{font-size:7pt}
  .sec{margin-bottom:4mm;border-color:#cbd5e1;border-radius:1.5mm;box-shadow:none;break-inside:avoid-page;page-break-inside:avoid}
  .sec__h{min-height:11mm;padding:2.2mm 3mm;background:#edf3f9;border-bottom-color:#cbd5e1}
  .sec__no{width:7mm;height:7mm;border-radius:1.5mm;font-size:8pt}.sec__t{font-size:10.5pt}.sec__skip{font-size:7.2pt}
  table.d{width:calc(100% - 6mm);margin:3mm;border-radius:1mm;font-size:8.3pt}
  table.d th,table.d td{padding:1.8mm 2.2mm}table.d th{width:24mm;font-size:8pt}table.d th.w2{width:29mm}
  .tag{padding:.4mm 1.5mm;font-size:7.2pt}.para{min-height:12mm;margin:3mm!important;padding:2.5mm 3mm;font-size:8.3pt}.empty-note{margin:3mm!important;padding:2mm 3mm;font-size:8pt}
  .doc-foot{margin-top:6mm;padding-top:3mm;font-size:7.2pt;break-inside:avoid}
}
</style>
</head>
<body>

<div class="topbar">
  <div class="ttl">소방계획서 <small><?=h($bldName)?> · <?=h($usage['nm'])?></small></div>
  <div class="tb-btns">
    <a class="btn" href="/fire_plan_edit.php?id=<?=h($planId)?>&s=1">← 수정하기</a>
    <button class="btn btn--primary" onclick="window.print()">🖨️ 인쇄 / PDF 저장</button>
  </div>
</div>
<div class="hint">
  <b>PDF로 저장하려면</b> 인쇄를 누른 뒤 대상(프린터)에서 <b>‘PDF로 저장’</b>을 선택하세요.
</div>

<div class="doc">

  <!-- ── 표지 ── -->
 <div class="cover">
    <div class="cover-mark"><strong>YEOHUB</strong><span></span><small>FIRE SAFETY DOCUMENT</small></div>
    <div class="cat"><?=h($usage['cat'] ?? '')?></div>

    <h1>소방계획서</h1>

    <div class="bld"><?=h($bldName)?></div>

    <?php if (!empty($s1['addr'])): ?>
        <div class="addr">
            <?=h($s1['addr'])?>
        </div>
    <?php endif; ?>

    <div class="date"><?=h($today)?></div>

    <div class="law-box">

        <div class="law-title">
            ■ 법적 작성기준
        </div>

        <p>
            본 소방계획서는
            <strong>「화재의 예방 및 안전관리에 관한 법률 시행령」 제27조제1항</strong>에 따른 필수 기재사항
            을 모두 포함하여 작성되었으며,
            소방계획서 작성시 법령에서 요구하는 사항을 모두 포함하도록 구성하였습니다.
        </p>

        <div class="law-ref">
            본 계획서의 적합성은 소방청 권장서식의 형식이 아닌
            「화재의 예방 및 안전관리에 관한 법률 시행령」
            제27조제1항에 따른 필수 기재사항의 포함 여부를 기준으로 작성되었습니다.
        </div>

    </div>
</div>

  <!-- ── 15개 항목 ── -->
  <?php foreach ($items as $code => $title):
    $code = (string)$code;
    $d    = fp_get_section($planId, $code);
    $isSkipped = isset($skips[$code]);
  ?>
  <section class="sec">
    <div class="sec__h">
      <div class="sec__no"><?=h($code)?></div>
      <div class="sec__t"><?=h($title)?></div>
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
        <tr><th>승강기</th><td colspan="3"><?=chkList($d['elev'] ?? [])?></td></tr>
        <tr><th>주차장</th><td colspan="3"><?=chkList($d['park'] ?? [])?>
          <?php if (($d['ev'] ?? '없음')==='있음'): ?><span class="tag">✓ 전기차충전소</span><?php endif; ?></td></tr>
        <tr><th>계 단</th><td colspan="3"><?=chkList($d['stairs'] ?? [])?></td></tr>
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
          공공기관 <b><?=h($d['public'] ?? '해당없음')?></b> ·
          권원분리 <b><?=h($d['split'] ?? '해당없음')?></b> ·
          공동관리 <b><?=h($d['joint'] ?? '해당없음')?></b> ·
          위험물 <b><?=h($d['hazmat'] ?? '해당없음')?></b>
        </td></tr>
        <tr><th>화재보험</th><td colspan="3">
          <?php if (($d['ins'] ?? '미가입') === '가입'): ?>
            <?=v($d['ins_co'] ?? '')?> · 기간 <?=v($d['ins_term'] ?? '')?> ·
            대인 <?=v($d['ins_life'] ?? '')?> · 대물 <?=v($d['ins_prop'] ?? '')?>
          <?php else: ?><span class="none">미가입</span><?php endif; ?>
        </td></tr>
      </table>

    <?php elseif ($code === '2'): /* ── 소방시설 현황 (서식 1.4) ── */ ?>
      <table class="d">
        <tr><th>소화설비</th><td><?=chkList($d['fire_ext'] ?? [])?></td></tr>
        <tr><th>경보설비</th><td><?=chkList($d['alarm'] ?? [])?></td></tr>
        <tr><th>피난구조<br>설비</th><td><?=chkList($d['escape'] ?? [])?></td></tr>
        <tr><th>소화용수<br>설비</th><td><?=chkList($d['water'] ?? [])?></td></tr>
        <tr><th>소화활동<br>설비</th><td><?=chkList($d['active'] ?? [])?></td></tr>
        <tr><th>기타시설</th><td><?=chkList($d['etc_fac'] ?? [])?></td></tr>
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
      <table class="d">
        <tr><th>피난층</th><td colspan="3"><?=v($d['floor_exit'] ?? '')?></td></tr>
        <tr><th>피난경로</th><td colspan="3"><div style="white-space:pre-wrap"><?=v($d['route'] ?? '')?></div></td></tr>
        <tr><th>피난기구</th><td colspan="3"><?=chkList($d['evac'] ?? [])?></td></tr>
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

    <?php elseif ($code === '6'): /* ── 방화구획·방염 ── */ ?>
      <table class="d">
        <tr><th>방화구획<br>기준</th><td><?=chkList($d['bkchk'] ?? [])?></td></tr>
        <tr><th>구획설비</th><td><?=chkList($d['bkdoor'] ?? [])?></td></tr>
        <tr><th>제연설비</th><td><?=chkList($d['smoke'] ?? [])?></td></tr>
        <tr><th>내부 마감재</th><td><?=v($d['finish'] ?? '')?></td></tr>
        <tr><th>방염물품</th><td><?=chkList($d['flame'] ?? [])?>
          <span class="tag">성능검사 필증 <?=h($d['flame_cert'] ?? '없음')?></span></td></tr>
        <?php if (!empty($d['memo'])): ?>
          <tr><th>유지관리<br>계획</th><td><div style="white-space:pre-wrap"><?=h($d['memo'])?></div></td></tr>
        <?php endif; ?>
      </table>

    <?php elseif ($code === '9'): /* ── 자위소방대 (편성표 별도) ── */ ?>
      <table class="d">
        <tr><th>편성 유형</th><td>
          <?php if (!empty($plan['jawi_type'])): ?>
            <b><?=h($plan['jawi_type']==='PUBLIC'?'공공기관형':'Type-'.$plan['jawi_type'])?></b>
            <span style="color:#777;font-size:11.5px">(연면적·근무인원 기준 자동 판정)</span>
          <?php else: ?><span class="none">-</span><?php endif; ?>
        </td></tr>
        <tr><th>편성표</th><td>
          자위소방대 편성표는 <b>별도 문서</b>로 관리·출력합니다.
          <span style="color:#777;font-size:11.5px">(TWORIX ▸ 자위소방대 편성표)</span>
        </td></tr>
        <?php if (!empty($d['memo'])): ?>
          <tr><th>비 고</th><td><div style="white-space:pre-wrap"><?=h($d['memo'])?></div></td></tr>
        <?php endif; ?>
      </table>

    <?php elseif ($code === '11'): /* ── 소방훈련·교육 ── */ ?>
      <table class="d">
        <tr><th>구 분</th><th>실시 시기</th><th>대 상</th><th>방 법</th></tr>
        <?php foreach (['소방훈련','소방교육','신규자 교육'] as $i => $rn): $n = $i+1; ?>
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
        <div class="empty-note">⚠ 아직 작성되지 않았습니다. 편집 화면에서 내용을 입력해 주세요.</div>
      <?php else: ?>
        <div class="para"><?=h($m)?></div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>

  <div class="doc-foot">
    본 소방계획서는 「화재의 예방 및 안전관리에 관한 법률 시행령」 제27조 제1항에 따라 작성되었습니다.<br>
    작성일 <?=h($today)?> · 소방안전관리자 <?=h($s1['mgr_name'] ?? '________')?> (서명)
  </div>

</div>
<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>
</html>
