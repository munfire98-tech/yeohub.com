<?php
if(!defined('FP_DOCUMENT_AUTHORIZED')||empty($bookView)){http_response_code(404);exit;}
?>
<header class="fp-bookbar" aria-label="연도별 소방계획서">
  <div class="fp-bookbar__row">
    <nav class="fp-book-years" aria-label="계획서 연도 선택">
      <?php if($bookIndex>0): ?><a class="fp-book-year-arrow" href="<?=h($bookLink('fire_plan_book.php',['year'=>$bookYears[$bookIndex-1]]))?>" aria-label="이전 연도 <?=h($bookYears[$bookIndex-1])?>년">‹</a><?php else: ?><span class="fp-book-year-arrow" aria-hidden="true">‹</span><?php endif; ?>
      <div class="fp-book-year-list">
        <?php foreach($bookYears as $year): ?><a class="fp-book-year<?=$year===$bookYear?' is-current':''?>" href="<?=h($bookLink('fire_plan_book.php',['year'=>$year]))?>"<?=$year===$bookYear?' aria-current="page"':''?>><?=h($year)?>년<?php if($year===$bookCurrentYear): ?><small>올해</small><?php endif; ?></a><?php endforeach; ?>
      </div>
      <?php if($bookIndex<count($bookYears)-1): ?><a class="fp-book-year-arrow" href="<?=h($bookLink('fire_plan_book.php',['year'=>$bookYears[$bookIndex+1]]))?>" aria-label="다음 연도 <?=h($bookYears[$bookIndex+1])?>년">›</a><?php else: ?><span class="fp-book-year-arrow" aria-hidden="true">›</span><?php endif; ?>
    </nav>
    <span class="fp-book-status<?=$bookStatus==='확인 완료'?' is-reviewed':''?>"><?=h($bookStatus)?></span>
  </div>
  <?php if($plan): ?>
  <div class="fp-bookbar__row fp-bookbar__row--actions">
    <p class="fp-book-saved">최근 저장 <time><?=h($plan['updated_at']??'')?></time></p>
    <div class="fp-book-actions">
      <button type="button" class="fp-book-action" data-fps-open data-fps-year="<?=h($bookYear)?>" data-fps-endpoint="<?=h($bookLink('fire_plan_share_api.php'))?>" aria-haspopup="dialog"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 3h6v6H3zM15 3h6v6h-6zM3 15h6v6H3zM15 15h3v3h-3zM21 14v7h-6M12 3v6M12 12h3"/></svg>QR · 공유</button>
      <button type="button" class="fp-book-action is-primary" onclick="window.fpPrintDocument ? window.fpPrintDocument() : window.print()"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 8V3h12v5M6 17H3V9h18v8h-3M6 14h12v7H6zM17 11h1"/></svg>인쇄 / PDF</button>
    </div>
  </div>
  <?php endif; ?>
</header>
