<?php
// Shared by the authorized standalone printer and the saved fire-plan document.
if(!defined('EPL_DOCUMENT_AUTHORIZED')||!isset($rows,$building)||!function_exists('h')){http_response_code(404);exit;}
(static function(array $rows,array $building):void {
?>
<div class="epl-output">
<?php foreach($rows as$row):$evacDocument=$row['published'];?><article class="epl-document"><div class="epl-doc-head"><p>피난계획</p><h1><?=h($row['published_title']??$row['title'])?></h1><p><?=h($building['name']??'')?> · <?=h($row['scope']['name'])?> | 지상 <?=(int)$row['scope']['above']?>층 · 지하 <?=(int)$row['scope']['below']?>층</p><p><?=h($building['address']??'')?> · 저장 <?=h($row['published_at'])?></p><?php if($row['archived']):?><strong>이전 계획 · 현재 사용하는 계획이 아닙니다.</strong><?php endif;?></div>
    <?php require_once __DIR__.'/evacuation_location_common.php';echo epl_location_html(epl_plan_locations($evacDocument,$building)); ?>
    <h2>공통 대응</h2><dl class="epl-review-grid"><?php foreach(['alarm_method'=>'상황 전파','reporter'=>'119 신고 담당','controller'=>'피난 지휘','assembly_confirmed'=>'최종 집결지','headcount_method'=>'인원 확인 방법','special_notes'=>'주의사항']as$field=>$label):?><div><dt><?=h($label)?></dt><dd><?=nl2br(h($evacDocument[$field]??''))?:'—'?></dd></div><?php endforeach;?></dl>
    <?php if(isset($evacDocument['stairs'])&&is_array($evacDocument['stairs'])):?><h2>계단 <?=count($evacDocument['stairs'])?>개</h2><p><?=h($evacDocument['stairs']?implode(' · ',array_column($evacDocument['stairs'],'name')):'계단 없음')?></p><?php endif;?>
    <h2>층별 대피방법</h2><?php foreach($evacDocument['floors']??[]as$floor=>$data):?><section class="epl-print-floor"><h3><?=h(epl_floor_label((string)$floor))?></h3><dl class="epl-review-grid"><?php foreach(['use'=>'용도','occupants'=>'최대 재실인원','vulnerable'=>'피난 지원 대상','primary_route'=>'주 대피경로','alternate_route'=>'대체 대피경로','guide'=>'피난유도 담당','checker'=>'잔류자 확인 담당']as$field=>$label):?><div class="<?=in_array($field,['primary_route','alternate_route','vulnerable'],true)?'wide':''?>"><dt><?=h($label)?></dt><dd><?=nl2br(h($data[$field]??''))?><?=$field==='occupants'?'명':''?></dd></div><?php endforeach;?></dl></section><?php endforeach;?></article><?php endforeach;?>
</div>
<?php })($rows,$building); ?>
