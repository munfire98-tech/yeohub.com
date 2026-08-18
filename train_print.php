<?php
// train_print.php — 소방훈련·교육 실시 결과 기록부 (별지 제28호서식) 인쇄/PDF
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

require_once __DIR__ . '/train_db.php';
$rec = tr_load((string)($_GET['id'] ?? ''));
if (!$rec) { header('Location: /train.php'); exit; }
$d = $rec['data'] ?? [];
$mgrs = $d['mgrs'] ?? [];

function val($d,$k){ $x=trim((string)($d[$k]??'')); return $x===''?'<span class="e"></span>':h($x); }
function chk($on){ return $on ? '☑' : '☐'; }
$grade = $d['t_grade'] ?? '';
$fireKind = $d['fire_kind'] ?? '';
$fireTypes = $d['fire_types'] ?? [];
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>소방훈련·교육 실시 결과 기록부 — 출력</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#eef1f6;color:#111;font-family:"Malgun Gothic","맑은 고딕",system-ui,sans-serif;line-height:1.5}
.topbar{position:sticky;top:0;z-index:10;background:#fff;border-bottom:1px solid #dde3ec;
  padding:12px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.topbar .t{font-size:14px;font-weight:800}
.topbar .t small{font-weight:400;color:#777;margin-left:8px;font-size:12px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:9px;
  border:1px solid #cfd7e3;background:#fff;color:#1a2436;font-size:13.5px;font-weight:700;cursor:pointer;font-family:inherit;text-decoration:none}
.btn:hover{border-color:#2563eb;color:#2563eb}
.btn--primary{background:#0f766e;border-color:#0f766e;color:#fff}
.btn--primary:hover{background:#0d5f59;color:#fff}
.hint{max-width:800px;margin:14px auto 0;padding:0 12px;font-size:12.5px;color:#777;text-align:center}

.doc{max-width:800px;margin:18px auto;background:#fff;padding:20mm 16mm;box-shadow:0 10px 40px rgba(20,40,80,.10)}
.formno{font-size:11px;color:#555;border-bottom:1px solid #333;padding-bottom:4px;margin-bottom:10px}
.title{text-align:center;font-size:20px;font-weight:800;letter-spacing:2px;margin-bottom:4px}
.note{font-size:11px;color:#555;text-align:right;margin-bottom:6px}
.sec{font-size:13px;font-weight:800;background:#333;color:#fff;padding:4px 10px;margin:16px 0 0}

table{width:100%;border-collapse:collapse;font-size:12px}
th,td{border:1px solid #555;padding:5px 7px;vertical-align:middle}
th{background:#f2f4f8;font-weight:700;text-align:center;white-space:nowrap}
.lb{background:#f2f4f8;font-weight:700;text-align:center;white-space:nowrap;width:84px}
.e{display:inline-block;min-width:40px;min-height:14px}
.big td{height:52px;vertical-align:top}
.cap{font-size:11.5px;color:#333}
.chkline{font-size:12px}

@media print{
  body{background:#fff}
  .topbar,.hint{display:none !important}
  .doc{max-width:none;margin:0;padding:0;box-shadow:none}
  @page{size:A4;margin:14mm 12mm}
  .sec{-webkit-print-color-adjust:exact;print-color-adjust:exact}
  th,.lb{-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .pgbreak{page-break-before:always}
}
.sub{background:#f2f4f8;font-weight:700;text-align:center;font-size:11.5px;padding:4px}
.tri{vertical-align:top;font-size:11.5px;line-height:1.6;height:110px}
.ptbl{margin-top:0}
.pcell{height:150px;text-align:center;vertical-align:middle;padding:4px;width:50%}
.pcell img{max-width:100%;max-height:145px;object-fit:contain}
.pcap{text-align:center;font-weight:700;background:#f2f4f8;font-size:11.5px;padding:4px}
.pbreak{height:0}
@media print{
  .pbreak{break-before:page;page-break-before:always;height:0}
  .pcell{height:170px}
  .pcell img{max-height:165px}
}</style>
</head>
<body>

<div class="topbar">
  <div class="t">소방훈련·교육 실시 결과 기록부 <small><?=h($d['t_name'] ?? '')?></small></div>
  <div style="display:flex;gap:8px">
    <a class="btn" href="/train_edit.php?id=<?=h($rec['id'])?>">← 수정</a>
    <button class="btn btn--primary" onclick="window.print()">🖨️ 인쇄 / PDF 저장</button>
  </div>
</div>
<div class="hint"><b>PDF로 저장</b>하려면 인쇄를 누른 뒤 대상(프린터)에서 <b>‘PDF로 저장’</b>을 선택하세요.</div>

<div class="doc">
  <div class="formno">■ 화재의 예방 및 안전관리에 관한 법률 시행규칙 [별지 제28호서식]</div>
  <div class="title">소방훈련ㆍ교육 실시 결과 기록부</div>
  <div class="note">※ ☐에는 해당되는 곳에 ☑ 표시</div>

  <!-- 대상물 -->
  <table>
    <tr><td class="lb">대상명</td><td><?=val($d,'t_name')?></td>
        <td class="lb">용도</td><td><?=val($d,'t_use')?></td></tr>
    <tr><td class="lb">대표자</td><td><?=val($d,'t_rep')?></td>
        <td class="lb">전화번호</td><td><?=val($d,'t_tel')?></td></tr>
    <tr><td class="lb">주소</td><td colspan="3"><?=val($d,'t_addr')?></td></tr>
    <tr><td class="lb">등급</td><td colspan="3" class="chkline">
      <?=chk($grade==='특급')?> 특급　<?=chk($grade==='1급')?> 1급　<?=chk($grade==='2급')?> 2급　<?=chk($grade==='3급')?> 3급
    </td></tr>
  </table>

  <!-- 소방안전관리자 -->
  <div class="sec">소방안전관리자</div>
  <table>
    <tr><th>성명</th><th>선임일자</th><th>보유자격</th><th>자격구분</th><th>연락처</th></tr>
    <?php for ($i=0;$i<4;$i++): $m=$mgrs[$i]??[]; $ty=$m['type']??''; ?>
    <tr>
      <td style="text-align:center"><?=h($m['name']??'')?: '<span class="e"></span>'?></td>
      <td style="text-align:center"><?=h($m['appt']??'')?: '<span class="e"></span>'?></td>
      <td style="text-align:center"><?=h($m['qual']??'')?: '<span class="e"></span>'?></td>
      <td style="text-align:center" class="chkline"><?=chk($ty==='주')?>주 <?=chk($ty==='보조')?>보조</td>
      <td style="text-align:center"><?=h($m['tel']??'')?: '<span class="e"></span>'?></td>
    </tr>
    <?php endfor; ?>
  </table>

  <!-- 소방훈련 결과 -->
  <div class="sec">소방훈련 결과</div>
  <table>
    <tr><td class="lb">일시</td><td><?=val($d,'fire_date')?></td>
        <td class="lb">장소</td><td><?=val($d,'fire_place')?></td></tr>
    <tr><td class="lb">구분</td><td class="chkline"><?=chk($fireKind==='자체훈련')?> 자체훈련　<?=chk($fireKind==='합동훈련')?> 합동훈련</td>
        <td class="lb">훈련교관</td><td><?=val($d,'fire_teacher')?></td></tr>
    <tr><td class="lb">참석결과</td><td colspan="3">
      참석대상 <b><?=val($d,'fire_target')?></b>명　·　참석 <b><?=val($d,'fire_join')?></b>명　·　미참석 <b><?=val($d,'fire_absent')?></b>명
    </td></tr>
    <tr><td class="lb">훈련보조재료</td><td colspan="3"><?=val($d,'fire_material')?></td></tr>
    <tr><td class="lb" rowspan="2">훈련내용</td>
        <td class="sub"><?=chk(in_array('소화훈련',$fireTypes,true))?> 소화훈련</td>
        <td class="sub"><?=chk(in_array('통보훈련',$fireTypes,true))?> 통보훈련</td>
        <td class="sub"><?=chk(in_array('피난훈련',$fireTypes,true))?> 피난훈련</td></tr>
    <tr class="big">
        <td class="tri"><?=nl2br(val($d,'fire_c_sohwa'))?></td>
        <td class="tri"><?=nl2br(val($d,'fire_c_tongbo'))?></td>
        <td class="tri"><?=nl2br(val($d,'fire_c_pinan'))?></td></tr>
    <?php if (trim((string)($d['fire_content'] ?? '')) !== ''): ?>
    <tr><td class="lb">그 밖의<br>훈련내용</td><td colspan="3"><?=nl2br(val($d,'fire_content'))?></td></tr>
    <?php endif; ?>
    <tr class="big"><td class="lb">훈련성과</td><td colspan="3"><?=nl2br(val($d,'fire_result'))?></td></tr>
    <tr class="big"><td class="lb">문제점</td><td colspan="3"><?=nl2br(val($d,'fire_problem'))?></td></tr>
    <tr class="big"><td class="lb">개선계획</td><td colspan="3"><?=nl2br(val($d,'fire_improve'))?></td></tr>
  </table>

  <!-- 소방교육 결과 -->
  <div class="sec">소방교육 결과</div>
  <table>
    <tr><td class="lb">일시</td><td><?=val($d,'edu_date')?></td>
        <td class="lb">장소</td><td><?=val($d,'edu_place')?></td></tr>
    <tr><td class="lb">교육강사</td><td><?=val($d,'edu_teacher')?></td>
        <td class="lb">참석결과</td><td>
          대상 <b><?=val($d,'edu_target')?></b>　참석 <b><?=val($d,'edu_join')?></b>　미참석 <b><?=val($d,'edu_absent')?></b>
        </td></tr>
    <tr class="big"><td class="lb">교육내용</td><td colspan="3"><?=nl2br(val($d,'edu_content'))?></td></tr>
    <tr class="big"><td class="lb">교육성과</td><td colspan="3"><?=nl2br(val($d,'edu_result'))?></td></tr>
    <tr class="big"><td class="lb">문제점</td><td colspan="3"><?=nl2br(val($d,'edu_problem'))?></td></tr>
    <tr class="big"><td class="lb">개선계획</td><td colspan="3"><?=nl2br(val($d,'edu_improve'))?></td></tr>
  </table>

  <!-- 소방훈련·교육 관련사진 (서식 뒤쪽) -->
  <?php
    $photos = $d['photos'] ?? [];
    $pLabels = ['fire1'=>'소방훈련','fire2'=>'소방훈련','edu1'=>'소방교육','edu2'=>'소방교육'];
    $hasPhoto = false;
    foreach ($pLabels as $pk => $pl) { if (trim((string)($photos[$pk] ?? '')) !== '') { $hasPhoto = true; break; } }
  ?>
  <div class="pbreak"></div>
  <div class="sec">소방훈련·교육 관련사진</div>
  <table class="ptbl">
    <tr>
      <?php foreach (['fire1','fire2'] as $pk): $u = tr_photo_url((string)($photos[$pk] ?? '')); ?>
        <td class="pcell"><?php if ($u !== ''): ?><img src="<?=h($u)?>" alt=""><?php endif; ?></td>
      <?php endforeach; ?>
    </tr>
    <tr><td class="pcap">소방훈련</td><td class="pcap">소방훈련</td></tr>
    <tr>
      <?php foreach (['edu1','edu2'] as $pk): $u = tr_photo_url((string)($photos[$pk] ?? '')); ?>
        <td class="pcell"><?php if ($u !== ''): ?><img src="<?=h($u)?>" alt=""><?php endif; ?></td>
      <?php endforeach; ?>
    </tr>
    <tr><td class="pcap">소방교육</td><td class="pcap">소방교육</td></tr>
  </table>
  <div style="margin-top:14px;font-size:11px;color:#666;text-align:center">
    작성일 <?=h(substr((string)($rec['updated_at'] ?? ''),0,10))?> · 210mm×297mm
  </div>
</div>
<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>
</html>
