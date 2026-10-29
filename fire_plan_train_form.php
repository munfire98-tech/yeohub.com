<?php
if(!defined('FP_DOCUMENT_AUTHORIZED')||!isset($record)){http_response_code(404);exit;}
(static function(array $record):void {
$d=(array)($record['data']??[]);$rec=$record;$mgrs=(array)($d['mgrs']??[]);
$value=static function($d,$k){$x=trim((string)($d[$k]??''));return $x===''?'<span class="e"></span>':h($x);};
$check=fn($on)=>$on?'☑':'☐';$photo=static function($key)use($record){$u=(string)($record['photo_images'][$key]??'');return preg_match('~^data:image/(?:jpeg|png|gif|webp);base64,[A-Za-z0-9+/=]+$~D',$u)?$u:'';};
$grade=$d['t_grade']??'';$fireKind=$d['fire_kind']??'';$fireTypes=(array)($d['fire_types']??[]);
?>
<div class="sheet">
  <div class="formno">■ 화재의 예방 및 안전관리에 관한 법률 시행규칙 [별지 제28호서식]</div>
  <div class="title">소방훈련ㆍ교육 실시 결과 기록부</div>
  <div class="note">※ ☐에는 해당되는 곳에 ☑ 표시</div>

  <!-- 대상물 -->
  <table>
    <tr><td class="lb">대상명</td><td><?=$value($d,'t_name')?></td>
        <td class="lb">용도</td><td><?=$value($d,'t_use')?></td></tr>
    <tr><td class="lb">대표자</td><td><?=$value($d,'t_rep')?></td>
        <td class="lb">전화번호</td><td><?=$value($d,'t_tel')?></td></tr>
    <tr><td class="lb">주소</td><td colspan="3"><?=$value($d,'t_addr')?></td></tr>
    <tr><td class="lb">등급</td><td colspan="3" class="chkline">
      <?=$check($grade==='특급')?> 특급　<?=$check($grade==='1급')?> 1급　<?=$check($grade==='2급')?> 2급　<?=$check($grade==='3급')?> 3급
    </td></tr>
  </table>

  <!-- 소방안전관리자 -->
  <div class="form-section-title">소방안전관리자</div>
  <table>
    <tr><th>성명</th><th>선임일자</th><th>보유자격</th><th>자격구분</th><th>연락처</th></tr>
    <?php for ($i=0;$i<4;$i++): $m=$mgrs[$i]??[]; $ty=$m['type']??''; ?>
    <tr>
      <td style="text-align:center"><?=h($m['name']??'')?: '<span class="e"></span>'?></td>
      <td style="text-align:center"><?=h($m['appt']??'')?: '<span class="e"></span>'?></td>
      <td style="text-align:center"><?=h($m['qual']??'')?: '<span class="e"></span>'?></td>
      <td style="text-align:center" class="chkline"><?=$check($ty==='주')?>주 <?=$check($ty==='보조')?>보조</td>
      <td style="text-align:center"><?=h($m['tel']??'')?: '<span class="e"></span>'?></td>
    </tr>
    <?php endfor; ?>
  </table>

  <!-- 소방훈련 결과 -->
  <div class="form-section-title">소방훈련 결과</div>
  <table>
    <tr><td class="lb">일시</td><td><?=$value($d,'fire_date')?></td>
        <td class="lb">장소</td><td><?=$value($d,'fire_place')?></td></tr>
    <tr><td class="lb">구분</td><td class="chkline"><?=$check($fireKind==='자체훈련')?> 자체훈련　<?=$check($fireKind==='합동훈련')?> 합동훈련</td>
        <td class="lb">훈련교관</td><td><?=$value($d,'fire_teacher')?></td></tr>
    <tr><td class="lb">참석결과</td><td colspan="3">
      참석대상 <b><?=$value($d,'fire_target')?></b>명　·　참석 <b><?=$value($d,'fire_join')?></b>명　·　미참석 <b><?=$value($d,'fire_absent')?></b>명
    </td></tr>
    <tr><td class="lb">훈련보조재료</td><td colspan="3"><?=$value($d,'fire_material')?></td></tr>
    <tr><td class="lb" rowspan="2">훈련내용</td>
        <td class="sub"><?=$check(in_array('소화훈련',$fireTypes,true))?> 소화훈련</td>
        <td class="sub"><?=$check(in_array('통보훈련',$fireTypes,true))?> 통보훈련</td>
        <td class="sub"><?=$check(in_array('피난훈련',$fireTypes,true))?> 피난훈련</td></tr>
    <tr class="big">
        <td class="tri"><?=nl2br($value($d,'fire_c_sohwa'))?></td>
        <td class="tri"><?=nl2br($value($d,'fire_c_tongbo'))?></td>
        <td class="tri"><?=nl2br($value($d,'fire_c_pinan'))?></td></tr>
    <?php if (trim((string)($d['fire_content'] ?? '')) !== ''): ?>
    <tr><td class="lb">그 밖의<br>훈련내용</td><td colspan="3"><?=nl2br($value($d,'fire_content'))?></td></tr>
    <?php endif; ?>
    <tr class="big"><td class="lb">훈련성과</td><td colspan="3"><?=nl2br($value($d,'fire_result'))?></td></tr>
    <tr class="big"><td class="lb">문제점</td><td colspan="3"><?=nl2br($value($d,'fire_problem'))?></td></tr>
    <tr class="big"><td class="lb">개선계획</td><td colspan="3"><?=nl2br($value($d,'fire_improve'))?></td></tr>
  </table>

  <!-- 소방교육 결과 -->
  <div class="form-section-title">소방교육 결과</div>
  <table>
    <tr><td class="lb">일시</td><td><?=$value($d,'edu_date')?></td>
        <td class="lb">장소</td><td><?=$value($d,'edu_place')?></td></tr>
    <tr><td class="lb">교육강사</td><td><?=$value($d,'edu_teacher')?></td>
        <td class="lb">참석결과</td><td>
          대상 <b><?=$value($d,'edu_target')?></b>　참석 <b><?=$value($d,'edu_join')?></b>　미참석 <b><?=$value($d,'edu_absent')?></b>
        </td></tr>
    <tr class="big"><td class="lb">교육내용</td><td colspan="3"><?=nl2br($value($d,'edu_content'))?></td></tr>
    <tr class="big"><td class="lb">교육성과</td><td colspan="3"><?=nl2br($value($d,'edu_result'))?></td></tr>
    <tr class="big"><td class="lb">문제점</td><td colspan="3"><?=nl2br($value($d,'edu_problem'))?></td></tr>
    <tr class="big"><td class="lb">개선계획</td><td colspan="3"><?=nl2br($value($d,'edu_improve'))?></td></tr>
  </table>

  <!-- 소방훈련·교육 관련사진 (서식 뒤쪽) -->
  <?php
    $photos = $d['photos'] ?? [];
    $pLabels = ['fire1'=>'소방훈련','fire2'=>'소방훈련','edu1'=>'소방교육','edu2'=>'소방교육'];
    $hasPhoto = false;
    foreach ($pLabels as $pk => $pl) { if (trim((string)($photos[$pk] ?? '')) !== '') { $hasPhoto = true; break; } }
  ?>
  <div class="pbreak"></div>
  <div class="form-section-title">소방훈련·교육 관련사진</div>
  <table class="ptbl">
    <tr>
      <?php foreach (['fire1','fire2'] as $pk): $u = $photo($pk); ?>
        <td class="pcell"><?php if ($u !== ''): ?><img src="<?=h($u)?>" alt=""><?php endif; ?></td>
      <?php endforeach; ?>
    </tr>
    <tr><td class="pcap">소방훈련</td><td class="pcap">소방훈련</td></tr>
    <tr>
      <?php foreach (['edu1','edu2'] as $pk): $u = $photo($pk); ?>
        <td class="pcell"><?php if ($u !== ''): ?><img src="<?=h($u)?>" alt=""><?php endif; ?></td>
      <?php endforeach; ?>
    </tr>
    <tr><td class="pcap">소방교육</td><td class="pcap">소방교육</td></tr>
  </table>
  <div style="margin-top:14px;font-size:11px;color:#666;text-align:center">
    작성일 <?=h(substr((string)($rec['updated_at'] ?? ''),0,10))?> · 210mm×297mm
  </div>
</div>
<?php if(!empty($record['missing_photos'])):?><p class="form-image-warning">일부 원본 사진을 반영하지 못했습니다. 원본 교육·훈련 기록의 사진을 확인해 주세요.</p><?php endif;?>
<?php })($record); ?>
