<?php
if(!defined('FP_DOCUMENT_AUTHORIZED')||empty($bookManage)||$shareReadOnly||!isset($plan,$bookLink)){http_response_code(404);exit;}
$bookPost=$bookLink('fire_plan.php',['id'=>$plan['id'],'year'=>$bookYear]);
$bookHidden=static function(string $action)use($plan){?><input type="hidden" name="csrf" value="<?=h(fp_csrf())?>"><input type="hidden" name="revision" value="<?=h(fpo_hash($plan))?>"><input type="hidden" name="act" value="<?=h($action)?>"><input type="hidden" name="return_book" value="1"><?php };
$bookPending=$overview['total']-$overview['filled'];
?>
<section class="fpr-workbench" aria-label="소방계획서 작성 관리">
 <div class="fpr-workbench-head"><div><strong>작성한 자료를 모아 계획서를 완성하세요</strong><p>기본정보·소방시설 자동 반영 → 남은 섹션 작성 → 최종 확인</p></div><span><?=$overview['filled']===0&&!$bundle?'자료 반영 전':$overview['percent'].'%'?></span></div>
 <?php if($bookError):?><p class="fpr-manage-error" role="alert"><?=h($bookError)?></p><?php endif;?>
 <?php if(($_GET['saved']??'')==='1'):?><p class="fpr-manage-success" role="status">최신자료를 반영했습니다. 남은 내용을 확인해 주세요.</p><?php endif;?>
 <?php if($bookSourceError):?><p class="fpr-manage-error" role="alert"><?=h($bookSourceError)?></p><?php endif;?>
 <form method="post" action="<?=h($bookPost)?>" class="fpr-import-form">
  <?php $bookHidden('import');?><input type="hidden" name="source_revision" value="<?=h($bookSources['fingerprint']??'')?>">
  <div class="fpr-workbench-actions"><button type="submit" class="fpr-manage-primary" <?=$bookSources?'':'disabled'?>>↻ 최신자료 반영하기</button><span><?=$bookPending?'남은 질문 '.$bookPending.'개':'모든 질문 입력됨'?></span></div>
  <details class="fpr-import-options"><summary>반영할 자료 확인 · 선택 <span><?=count($bundle)?>종 반영 중</span></summary><div class="fpr-import-grid">
  <?php $bookSelected=!empty($plan['source_selection_set'])?(array)($plan['source_selection']??[]):array_keys(array_filter((array)($bookSources['groups']??[]),static fn($g)=>$g['available'])); ?>
  <?php foreach((array)($bookSources['groups']??[]) as $key=>$group):?><label><input type="checkbox" name="sources[]" value="<?=h($key)?>" <?=in_array($key,$bookSelected,true)&&$group['available']?'checked':''?> <?=$group['available']?'':'disabled'?>><span><b><?=h($group['title'])?></b><small><?=h(!$group['available']?'저장된 자료 없음':(isset($bundle[$key])&&($bundle[$key]['fingerprint']??'')===$group['fingerprint']?'반영됨':'반영할 자료 있음'))?></small></span></label><?php endforeach;?>
  </div><p>선택한 자료를 현재 저장 내용으로 갱신합니다. 직접 수정한 답변은 유지됩니다. 선택을 해제하면 그 자료에서 자동 반영한 값은 제외됩니다.</p></details>
 </form>
</section>
