<?php
if(!defined('FP_DOCUMENT_AUTHORIZED')||empty($bookView)||empty($plan)){http_response_code(404);exit;}
$readerLabels=[
 '1'=>['일반현황','위치 · 규모 · 소방안전관리자'],
 '2'=>['소방·안전시설 현황','소방 · 방화 · 전기 · 가스시설'],
 '3'=>['자체점검·대응대책','점검 일정과 화재 대응'],
 '4'=>['점검·정비계획','소방 · 피난 · 방화시설 관리'],
 '5'=>['피난계획','피난경로 · 집결지 · 취약자'],
 '6'=>['방화구획·방염관리','구획 · 마감재 · 방염물품'],
 '7'=>['관리 권원 분리','관리 주체별 안전관리'],
 '8'=>['공동 안전관리','공동 관리와 협의 사항'],
 '9'=>['자위소방대 조직·임무','편성 조직과 대원별 역할'],
 '10'=>['화기작업 안전관리','작업 전 안전조치와 감독'],
 '11'=>['소방훈련·교육 계획','교육 · 훈련 예정 일정'],
 '12'=>['위험물 관리','저장 · 취급 시 안전관리'],
 '13'=>['업무수행 기록','매월 기록과 유지관리'],
 '14'=>['화재 초기대응','경보 · 초기소화 · 피난유도'],
 '15'=>['소방서 요청사항','추가 확인·관리 사항'],
];
$readerReferences=0;
foreach([['basic','dongs'],['basic','managers'],['monthly','facilities'],['evacuation','floors'],['monthly','records'],['incidents','records']] as [$group,$key]){
 if(!empty($bundle[$group]['appendix'][$key]))$readerReferences++;
}
?>
<main class="fpr-catalog" id="plan-contents" aria-labelledby="fpr-title">
 <header class="fpr-heading">
  <div><p class="fpr-kicker">FIRE SAFETY PLAN · <?=$bookYear?></p><h1 id="fpr-title"><?=$bookYear?>년 소방계획서</h1><p class="fpr-building"><?=h($bldName)?></p></div>
 </header>
 <?php if(!empty($bookManage)&&!$shareReadOnly)require __DIR__.'/fire_plan_book_manage.php'; ?>
 <div class="fpr-intro"><p><?=!empty($bookManage)&&!$shareReadOnly?'미작성 섹션을 눌러 남은 내용을 작성하세요.':'확인할 섹션을 선택해 주세요.'?></p><span><i aria-hidden="true"></i> <?=$overview['done']?>개 항목 입력됨<?php if($overview['skipped']):?> · <?=$overview['skipped']?>개 해당 없음<?php endif;?></span></div>
 <nav class="fpr-grid" aria-label="소방계획서 섹션 목차">
  <?php foreach($items as $code=>$title):$st=$overview['sections'][$code];$label=$readerLabels[(string)$code]??[$title,'저장된 계획 확인'];$state=$st['skip']?'skip':($st['done']?'filled':'draft');$caption=$st['skip']?'해당 없음':($st['done']?'입력됨':($st['filled']?'작성 중':((string)$code==='6'&&!empty($plan['sections']['6']['data']['_flame_guide']['checks'])?'방염 확인 반영':'입력 전')));?>
   <a class="fpr-card is-<?=$state?>" href="<?=!empty($bookManage)&&!$shareReadOnly&&!$st['done']&&!$st['skip']?h($bookLink('fire_plan_chat.php',['id'=>$plan['id'],'year'=>$bookYear,'section'=>(string)$code,'from_book'=>'1'])):'#section-'.h($code)?>" <?php if(!empty($bookManage)&&!$shareReadOnly):?>data-reader-write-url="<?=h($bookLink('fire_plan_chat.php',['id'=>$plan['id'],'year'=>$bookYear,'section'=>(string)$code,'from_book'=>'1']))?>" <?=!$st['done']&&!$st['skip']?'data-reader-needs-write="true"':''?><?php endif;?> data-reader-select="<?=h($code)?>" data-reader-title="<?=h($label[0])?>" aria-label="<?=h($code.'. '.$title.' · '.$caption)?>">
    <span class="fpr-card-top"><span class="fpr-number"><?=str_pad((string)$code,2,'0',STR_PAD_LEFT)?></span><span class="fpr-status"><i aria-hidden="true"></i><?=!empty($bookManage)&&!$shareReadOnly&&!$st['done']&&!$st['skip']?'남은 '.count($st['missing']).'개':$caption?></span></span>
    <strong><?=h($label[0])?></strong><span class="fpr-description"><?=h($label[1])?></span><span class="fpr-arrow" aria-hidden="true">↗</span>
   </a>
  <?php endforeach;?>
  <a class="fpr-card fpr-reference" href="#plan-references" data-reader-select="references" data-reader-title="반영 자료" aria-label="반영 자료 <?=$readerReferences?>종 확인">
   <span class="fpr-card-top"><span class="fpr-number" aria-hidden="true"><svg viewBox="0 0 20 20" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M5 3h9v12H5zM3 6H2v12h10v-1M8 7h4M8 10h4"/></svg></span><span class="fpr-status"><?=$readerReferences?>종 반영</span></span>
   <strong>반영 자료</strong><span class="fpr-description">동별 현황 · 편성 · 피난 · 월 기록</span><span class="fpr-arrow" aria-hidden="true">↗</span>
  </a>
 </nav>
 <?php if(!empty($bookManage)&&!$shareReadOnly)require __DIR__.'/fire_plan_book_review.php'; ?>
 <p class="fpr-footnote">이 연도 계획서에 저장된 내용을 보여드립니다. ‘입력됨’은 항목 입력 상태이며, 최종 확인 여부는 상단에 표시됩니다.</p>
</main>
<nav class="fpr-readerbar" aria-label="선택한 섹션 탐색" hidden>
 <button type="button" class="fpr-back" data-reader-back><span aria-hidden="true">‹</span> 목차로</button>
 <div class="fpr-reader-title"><small><?=$bookYear?>년 소방계획서</small><strong data-reader-heading></strong></div>
 <?php if(!empty($bookManage)&&!$shareReadOnly):?><a class="fpr-reader-edit" data-reader-edit href="#" hidden>내용 작성·수정 ↗</a><?php endif;?>
 <div class="fpr-reader-arrows"><span data-reader-position></span><button type="button" data-reader-prev aria-label="이전 섹션">‹</button><button type="button" data-reader-next aria-label="다음 섹션">›</button></div>
</nav>
<p class="fpr-announcement" role="status" aria-live="polite" data-reader-announce></p>
