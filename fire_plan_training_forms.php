<?php
if(!defined('FP_DOCUMENT_AUTHORIZED')||!isset($bundle)){http_response_code(404);exit;}
?>
<div class="fp-training-records">
 <h3>법정보관 교육·훈련 기록부</h3>
 <p class="fp-training-caption"><?=fp_plan_year($plan)?>년 자료 반영 시점의 원본 기록입니다. 예정 일정은 위 계획표에서, 실제 실시 내용은 아래 기록부에서 확인합니다.</p>
 <?php foreach(['jawi_education'=>['자위소방대 교육·훈련','fire_plan_jawi_form.php'],'training'=>['소방훈련·교육','fire_plan_train_form.php']] as $formGroup=>[$formLabel,$formTemplate]):
 $formRecords=(array)($bundle[$formGroup]['appendix']['forms']??[]); ?>
 <?php if(!$formRecords):?><p class="fp-training-empty"><?=h($formLabel)?>: 반영된 기록부가 없습니다. 해당 연도의 기록을 작성·저장한 뒤 ‘최신정보 반영하기’에서 이 자료를 선택해 주세요.</p><?php endif;?>
 <?php foreach($formRecords as $record):if(!is_array($record))continue; ?>
 <div class="fp-training-form <?= $formGroup==='training'?'fp-training-form--train':'fp-training-form--jawi' ?>">
  <p class="fp-training-form-label"><?=h($formLabel)?> · 기록 <?=h($record['id']??'')?> · 저장 <?=h(substr((string)($record['updated_at']??''),0,10))?></p>
  <?php require __DIR__.'/'.$formTemplate; ?>
 </div>
 <?php endforeach;endforeach; ?>
</div>
