<?php
// Included only after the authenticated plan loader has selected the current user's document.
if(!isset($bundle,$plan)||!function_exists('h')){http_response_code(404);exit;}
$appendixNumber=0;
$multiUse=(array)($bundle['basic']['appendix']['multi_use']??[]);
$dongs=$bundle['basic']['appendix']['dongs']??[];
$managers=$bundle['basic']['appendix']['managers']??[];
$facilities=$bundle['monthly']['appendix']['facilities']??[];
$monthly=$bundle['monthly']['appendix']['records']??[];
$incidents=$bundle['incidents']['appendix']['records']??[];
$floors=empty($bundle['evacuation']['appendix']['document']['plans'])?($bundle['evacuation']['appendix']['floors']??[]):[];
?>
<?php if($dongs||$managers||$facilities||$monthly||$floors||$incidents||$multiUse):?>
<div class="fp-appendix-title"><p class="fp-doc-kicker">반영 자료</p><h2>동별 현황과 반영 자료</h2><p>자료 반영 시점에 저장한 상세 현황입니다. 기본정보에 저장된 규모와 동별 면적을 함께 확인하세요.</p></div>
<?php endif;?>
<?php if($dongs):?><section class="sec fp-appendix" data-plan-section="1"><div class="sec__h"><div class="sec__no">별첨 <?=++$appendixNumber?></div><div class="sec__t">관리하는 동별 건물 현황</div></div><table class="d"><thead><tr><th>동 이름</th><th>용도</th><th>층수</th><th>연면적(㎡)</th><th>구조</th></tr></thead><tbody><?php foreach($dongs as $r):?><tr><td><b><?=h($r['name'])?></b><?=!empty($r['primary'])?'<br><span class="tag">기준동</span>':''?></td><td><?=v($r['use'])?></td><td>지상 <?=v($r['above'])?>층<br>지하 <?=v($r['below'])?>층</td><td><?=v($r['area'])?></td><td><?=v($r['structure'])?></td></tr><?php endforeach;?></tbody></table></section><?php endif;?>
<?php if($managers):?><section class="sec fp-appendix" data-plan-section="1"><div class="sec__h"><div class="sec__no">별첨 <?=++$appendixNumber?></div><div class="sec__t">소방안전관리자 현황</div></div><table class="d"><thead><tr><th>구분</th><th>성명</th><th>연락처</th><th>선임일</th></tr></thead><tbody><?php foreach($managers as $r):?><tr><td><?=v($r['type']??'')?></td><td><?=v($r['name']??'')?></td><td><?=v($r['tel']??'')?></td><td><?=v($r['appt']??'')?></td></tr><?php endforeach;?></tbody></table></section><?php endif;?>
<?php if($facilities):?><section class="sec fp-appendix" data-plan-section="2"><div class="sec__h"><div class="sec__no">별첨 <?=++$appendixNumber?></div><div class="sec__t">동별 소방시설 상세</div></div><table class="d"><thead><tr><th>동 이름</th><th>설치 시설 · 수신기 · 수량 · 위치</th><th>확인 현황</th></tr></thead><tbody><?php foreach($facilities as $r):?><tr><td><b><?=h($r['name'])?></b></td><td class="preserve"><?=v($r['summary']?:'설치 있음으로 표시한 시설 없음')?></td><td>설치 <?=$r['present']?>종<br><?=$r['unknown']?'미확인 '.(int)$r['unknown'].'종':'전체 확인'?></td></tr><?php endforeach;?></tbody></table></section><?php endif;?>
<?php if($floors):?><section class="sec fp-appendix" data-plan-section="5"><div class="sec__h"><div class="sec__no">별첨 <?=++$appendixNumber?></div><div class="sec__t">동별 · 층별 피난계획</div></div><table class="d"><thead><tr><th>동 · 층</th><th>재실인원</th><th>주 대피경로 / 대체 경로</th><th>피난유도 · 잔류 확인</th></tr></thead><tbody><?php foreach($floors as $r):?><tr><td><b><?=v($r['label']??'')?></b></td><td><?=v($r['occupants']??'')?>명</td><td class="preserve"><?=v($r['primary_route']??'')?><?php if(!empty($r['alternate_route'])):?><br>대체: <?=h($r['alternate_route'])?><?php endif;?></td><td>유도: <?=v($r['guide']??'')?><br>확인: <?=v($r['checker']??'')?></td></tr><?php endforeach;?></tbody></table></section><?php endif;?>
<?php if($monthly):?><section class="sec fp-appendix" data-plan-section="13"><div class="sec__h"><div class="sec__no">별첨 <?=++$appendixNumber?></div><div class="sec__t"><?=fp_plan_year($plan)?>년 저장된 업무수행 기록 현황</div></div><table class="d"><thead><tr><th>기록 월</th><th>수행일</th><th>작성자</th></tr></thead><tbody><?php foreach($monthly as $r):?><tr><td><?=(int)$r['month']?>월</td><td><?=h($r['date'])?></td><td><?=h($r['performer'])?></td></tr><?php endforeach;?></tbody></table><p class="empty-note">월별 기록의 내용과 서명은 원본 업무수행 기록표에서 확인합니다.</p></section><?php endif;?>

<?php if($incidents):?><section class="sec fp-appendix" data-plan-section="14"><div class="sec__h"><div class="sec__no">별첨 <?=++$appendixNumber?></div><div class="sec__t"><?=fp_plan_year($plan)?>년 비화재보 발생 및 조치 기록</div></div><table class="d"><thead><tr><th>일시 · 위치</th><th>추정 원인</th><th>조치 내용</th><th>처리 상태</th></tr></thead><tbody><?php foreach($incidents as $r):?><tr><td><?=h(str_replace('T',' ',$r['occurred_at']))?><br><?=h($r['location'])?></td><td class="preserve"><?=v($r['cause'])?></td><td class="preserve"><?=v($r['action'])?></td><td><?=v($r['status'])?></td></tr><?php endforeach;?></tbody></table></section><?php endif;?>

<?php if($multiUse): ?>
      <section class="sec fp-appendix" data-plan-section="1" id="appendix-multi-use"><div class="sec__h"><div class="sec__no">별첨 <?=++$appendixNumber?></div><div class="sec__t">다중이용업소 현황</div></div>
      <?php if(!empty($multiUse['rows'])): ?>
      <table class="d fp-floor-table"><thead><tr><th>업소명</th><th>업종</th><th>영업층</th></tr></thead><tbody>
      <?php foreach($multiUse['rows'] as $shop): ?>
      <tr><td><?=v($shop['name']??'')?></td><td><?=v($shop['type']??'')?></td><td><?=v($shop['location']??'')?><?=!empty($shop['location_stale'])?'<br><b>위치 재확인 필요</b>':''?></td></tr>
      <?php endforeach; ?></tbody></table>
      <?php else: ?><p class="empty-note"><?=($multiUse['state']??'')==='none'?'다중이용업소 없음 (직접 확인)':'다중이용업소 유무 미확인 · 현황에서 있음·없음을 선택해 주세요.'?></p><?php endif; ?>

</section>
<?php endif; ?>
