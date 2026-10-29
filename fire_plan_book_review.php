<?php
if(!defined('FP_DOCUMENT_AUTHORIZED')||empty($bookManage)||$shareReadOnly||!isset($bookHidden)){http_response_code(404);exit;}
?>
<section class="fpr-final-review <?=$overview['reviewed']?'is-reviewed':''?>" aria-labelledby="fprReviewTitle">
 <div><h2 id="fprReviewTitle"><?=$overview['reviewed']?'✓ 최종 확인 완료':'최종 확인'?></h2><p><?=$overview['reviewed']?'최종 확인 '.h($plan['final_review']['at']??''):($overview['complete']?'시설·담당자·대피방법이 실제 현장과 맞는지 확인해 주세요.':'위 미작성 섹션을 채우면 최종 확인을 저장할 수 있어요.')?></p></div>
 <?php if(!$overview['reviewed']):?><form method="post" action="<?=h($bookPost)?>"><?php $bookHidden('review');?><label><input type="checkbox" name="confirmed" value="1" required <?=$overview['complete']?'':'disabled'?>> 건물 현황과 계획서 내용을 확인했습니다.</label><button class="fpr-manage-primary" <?=$overview['complete']?'':'disabled'?>>최종 확인 저장</button></form><?php endif;?>
</section>
