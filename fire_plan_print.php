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
:root{--bd:#333;--bd2:#999;--mut:#777;--brand:#2563eb}
*{box-sizing:border-box;margin:0;padding:0}
body{background:#eef1f6;color:#111;
  font-family:"Malgun Gothic","맑은 고딕",system-ui,"Apple SD Gothic Neo",sans-serif;line-height:1.6}

/* 화면 전용 툴바 */
.topbar{position:sticky;top:0;z-index:10;background:#fff;border-bottom:1px solid #dde3ec;
  padding:12px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.topbar .ttl{font-size:15px;font-weight:800}
.topbar .ttl small{font-weight:400;color:var(--mut);margin-left:8px;font-size:12.5px}
.tb-btns{display:flex;gap:8px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:9px;
  border:1px solid #cfd7e3;background:#fff;color:#1a2436;font-size:13.5px;font-weight:700;
  cursor:pointer;font-family:inherit;text-decoration:none}
.btn:hover{border-color:var(--brand);color:var(--brand)}
.btn--primary{background:var(--brand);border-color:var(--brand);color:#fff}
.btn--primary:hover{background:#1d4ed8;color:#fff}
.hint{max-width:820px;margin:14px auto 0;padding:0 12px;font-size:12.5px;color:var(--mut);text-align:center}

/* 문서(A4) */
.doc{max-width:820px;margin:18px auto;background:#fff;padding:24mm 18mm;
  box-shadow:0 10px 40px rgba(20,40,80,.10);border-radius:2px}

/* 표지 */
.cover{text-align:center;padding:40px 0 30px;border-bottom:3px double var(--bd);margin-bottom:26px}
.cover .cat{font-size:13px;color:var(--mut);letter-spacing:2px;margin-bottom:16px}
.cover h1{font-size:34px;font-weight:800;letter-spacing:10px;margin-bottom:8px}
.cover .bld{font-size:19px;font-weight:700;margin:22px 0 6px}
.cover .date{font-size:13.5px;color:#444;margin-top:14px}
.cover .law{font-size:11.5px;color:var(--mut);margin-top:20px;line-height:1.7}

/* 항목 */
.sec{margin-bottom:22px;page-break-inside:avoid}
.sec__h{display:flex;align-items:center;gap:9px;margin-bottom:8px;padding-bottom:5px;border-bottom:2px solid var(--bd)}
.sec__no{flex-shrink:0;width:26px;height:26px;background:#333;color:#fff;border-radius:5px;
  display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800}
.sec__t{font-size:15.5px;font-weight:800;letter-spacing:-.3px}
.sec__skip{margin-left:auto;font-size:11.5px;color:#888;border:1px solid #ddd;border-radius:20px;padding:2px 10px}

/* 데이터 표 */
table.d{width:100%;border-collapse:collapse;font-size:12.5px}
table.d th,table.d td{border:1px solid var(--bd2);padding:6px 9px;vertical-align:middle}
table.d th{background:#f2f4f8;font-weight:700;text-align:center;white-space:nowrap;width:96px}
table.d th.w2{width:120px}
table.d td{word-break:break-all}
.tag{display:inline-block;background:#eef4ff;border:1px solid #cfe0ff;color:#1d4ed8;
  border-radius:4px;padding:2px 7px;font-size:11.5px;margin:2px 3px 2px 0;white-space:nowrap}
.none{color:#aaa}
.para{white-space:pre-wrap;font-size:12.5px;line-height:1.8;padding:8px 10px;
  border:1px solid var(--bd2);border-radius:3px;min-height:44px;background:#fcfdff}
.empty-note{font-size:12px;color:#b45309;background:#fff7ed;border:1px solid #fed7aa;
  border-radius:4px;padding:7px 10px}

/* 인쇄 */
@media print{
  body{background:#fff}
  .topbar,.hint{display:none !important}
  .doc{max-width:none;margin:0;padding:0;box-shadow:none;border-radius:0}
  .cover{page-break-after:always;border-bottom:0;
    height:calc(297mm - 32mm);display:flex;flex-direction:column;
    align-items:center;justify-content:center;padding:0}
  .cover h1{border-top:3px double var(--bd);border-bottom:3px double var(--bd);
    padding:18px 40px;margin-bottom:0}
  .sec{page-break-inside:avoid}
  @page{size:A4;margin:16mm 14mm}
}
@media(max-width:680px){
  .doc{padding:20px 16px;margin:10px}
  .cover h1{font-size:26px;letter-spacing:6px}
  table.d th{width:80px;font-size:11.5px}
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
    <div class="cat"><?=h($usage['cat'] ?? '')?></div>

    <h1>소방계획서</h1>

    <div class="bld"><?=h($bldName)?></div>

    <?php if (!empty($s1['addr'])): ?>
        <div style="font-size:13px;color:#555">
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
            <strong>「화재의 예방 및 안전관리에 관한 법률 시행령」 
            <strong>제27조제1항에 따른 필수 기재사항</strong>
            을 모두 포함하여 작성되었으며,
            소방계획서 작성시 법령에서 요구하는 사항을 모두 포함하도록 구성하였습니다.
        </p>

        <div class="law-ref">
            <strong>　</strong><br><br>

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
        <?php foreach ($rows as $i => $rn): $n = $i+1; ?>
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

  <div style="margin-top:30px;padding-top:14px;border-top:1px solid #ccc;
    font-size:11px;color:#888;text-align:center;line-height:1.7">
    본 소방계획서는 「화재의 예방 및 안전관리에 관한 법률 시행령」 제27조 제1항에 따라 작성되었습니다.<br>
    작성일 <?=h($today)?> · 소방안전관리자 <?=h($s1['mgr_name'] ?? '________')?> (서명)
  </div>

</div>
<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>
</html>
