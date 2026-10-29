<?php
declare(strict_types=1);
// Display only the team snapshot already authorized for this shared annual plan.
if (!defined('FP_DOCUMENT_AUTHORIZED') || empty($shareReadOnly) || !isset($plan, $bundle) || !function_exists('h')) { http_response_code(404); exit; }
$teamText = static fn($v): string => is_scalar($v) ? trim((string)$v) : '';
$teamRows = [];
foreach ((array)($bundle['team']['appendix']['members'] ?? []) as $row) {
    if (!is_array($row)) continue;
    $name = $teamText($row['name'] ?? '');
    if ($name === '') continue;
    // Keep assignments separate: a shared name alone is not a person's identity.
    $teamRows[] = ['name'=>$name, 'role'=>$teamText($row['role'] ?? ''), 'task'=>$teamText($row['task'] ?? ''), 'dept'=>$teamText($row['dept'] ?? '')];
}
$teamAssembly = $teamText($plan['sections']['5']['data']['assembly'] ?? '');
$teamImported = $teamText($plan['source_bundle']['imported_at'] ?? '');
?>
<section class="fp-team-app" aria-labelledby="fp-team-title">
  <div class="fp-team-brand"><span>소방계획서.com</span><span>구성원 안내 · 읽기 전용</span></div>
  <header class="fp-team-header">
    <p class="fp-team-year"><?=fp_plan_year($plan)?>년 소방계획서</p>
    <h1 id="fp-team-title"><?=h($bldName)?></h1>
    <p>내 임무를 확인하고, 함께 안전을 준비하세요.</p>
    <div class="fp-team-meta"><span class="fp-team-state<?=$overview['reviewed']?'':' is-draft'?>"><?=$overview['reviewed']?'작성 내용 최종 확인 완료':'작성 중인 계획서'?></span><?php if(!empty($plan['updated_at'])):?><span>최근 저장 <?=h($plan['updated_at'])?></span><?php endif;?></div>
  </header>
  <nav class="fp-team-nav" aria-label="공유 내용 선택" hidden>
    <button type="button" data-team-view="team" aria-controls="fp-team-content" aria-pressed="true">내 임무 확인</button>
    <button type="button" data-team-view="document" aria-controls="fp-shared-document" aria-pressed="false">전체 소방계획서</button>
  </nav>
  <div id="fp-team-content">
    <div class="fp-team-heading"><h2>자신의 이름을 눌러주세요</h2><?php if($teamRows):?><span>역할 <?=count($teamRows)?>개</span><?php endif;?></div>
    <p class="fp-team-guide">소속·역할을 함께 확인하세요. 겸임이라면 해당하는 임무를 모두 확인해 주세요.</p>
    <?php if($teamRows):?>
      <label class="fp-team-search" hidden><span>이름 또는 역할 찾기</span><input type="search" id="fp-team-search" autocomplete="off" placeholder="예: 이름, 피난유도반" maxlength="100"></label>
      <p class="fp-team-search-result" role="status" aria-live="polite" hidden></p>
      <div class="fp-team-list">
      <?php foreach($teamRows as $i=>$person):?>
        <details class="fp-team-person" data-team-search="<?=h($person['name'].' '.$person['role'].' '.$person['dept'])?>">
          <summary>
            <span class="fp-team-avatar" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="8" r="3.3"/><path d="M5 21v-3a7 7 0 0 1 14 0v3"/></svg></span>
            <span class="fp-team-person-label"><strong><?=h($person['name'])?></strong><span><?=h($person['role']?:'역할 확인 필요')?><?php if($person['dept']!==''):?> · <?=h($person['dept'])?><?php endif;?></span></span>
            <span class="fp-team-chevron" aria-hidden="true">⌄</span>
          </summary>
          <div class="fp-team-duty"><h3>나의 담당 임무</h3>
            <?php if($person['task']!==''):?><p><?=h($person['task'])?></p><?php else:?><p class="fp-team-missing">아직 구체적인 임무가 입력되지 않았습니다. 소방안전관리자에게 확인해 주세요.</p><?php endif;?>
            <small>소방계획서에 반영된 편성표 기준</small>
          </div>
        </details>
      <?php endforeach;?>
      </div>
      <p class="fp-team-no-match" hidden>일치하는 이름이나 역할이 없습니다. 다른 검색어로 찾아보세요.</p>
    <?php else:?>
      <div class="fp-team-empty"><strong>이름별 편성 정보가 아직 없어요</strong><p>소방안전관리자에게 자위소방대 편성표를 계획서에 반영해 달라고 요청해 주세요.</p><a href="#section-9" data-team-document>계획서의 자위소방대 내용 보기 <span aria-hidden="true">↗</span></a></div>
    <?php endif;?>
    <section class="fp-team-assembly" aria-labelledby="fp-team-assembly-title"><span class="fp-team-assembly-icon" aria-hidden="true">◎</span><div><h2 id="fp-team-assembly-title">대피 후 집결지</h2><p><?=h($teamAssembly?:'집결지가 아직 입력되지 않았습니다. 소방안전관리자에게 확인해 주세요.')?></p><a href="#section-5" data-team-document>피난계획 자세히 보기 <span aria-hidden="true">↗</span></a></div></section>
    <p class="fp-team-source">이 안내는 공유된 계획서에 저장된 내용입니다.<?php if($teamImported!==''):?><br>편성표 등 자료 반영 <?=h($teamImported)?><?php endif;?></p>
  </div>
</section>
