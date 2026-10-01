<?php
// Included by clients_mini.php only after the manager workspace is available.
if(empty($msReady))return;
$introScope=hash('sha256',(string)($headerUid??mg_uid()));
?>
<link rel="stylesheet" href="/manager_intro.css?v=5">
<dialog id="manager-intro" class="mi-dialog" aria-labelledby="mi-title" data-scope="<?=htmlspecialchars($introScope,ENT_QUOTES,'UTF-8')?>">
  <div class="mi-content">
    <header class="mi-head">
      <div><span class="mi-eyebrow">매니저 이용 안내</span><h2 id="mi-title">매니저 리워드</h2><p data-mi-subtitle>구독 유저의 매월 업무수행기록 작성이 매니저의 리워드로 이어집니다.</p></div>
      <button type="button" class="mi-close" data-mi-close aria-label="안내 닫기">×</button>
    </header>
    <div class="mi-body">
    <section data-mi-rewards class="mi-rewards" aria-label="매니저 리워드 안내">
      <div class="mi-reward-hero"><span class="mi-reward-tag">현장의 도움을 플랫폼에서</span><h3>현장에서 하던 도움을<br>더 정확하고 간단하게, 리워드까지.</h3><p>매니저와 연결된 유저가 구독을 유지하며<br>그달 업무수행기록을 작성·저장하면 1,500원 상당의 파이어 마일리지가 지급됩니다.</p></div>
      <div class="mi-help-time"><strong>유저 1명당 예상 도움 시간 약 10분</strong><p>미리 입력한 기본정보를 활용해 필요한 항목의 작성을 도와주세요.<br>도움 시간은 건물 규모와 작성 상태에 따라 달라질 수 있습니다.</p></div>
      <div class="mi-reward-stats"><div><span>그달 기록을 작성한 구독 지점 1곳당</span><strong>1,500원 <small>상당</small></strong><p>구독한 달부터 달력 월 기준, 매월 1회 지급</p></div><div><span>70개 지점이 그달 기록을 모두 작성하면</span><strong>10만 5천원 <small>상당</small></strong><p>해당 월 지급되는 파이어 마일리지 합계</p></div></div>
      <div class="mi-renewal-reward"><strong>구독을 유지하면, 매월 작성 리워드도 계속</strong><p>유저가 구독을 유지하는 동안, 해당 월 업무수행기록을 작성한 달마다 지급됩니다. 미작성 월은 지급하지 않으며 같은 달의 반복 수정·재구독으로 중복 지급되지 않습니다.</p></div>
      <section class="mi-promo" aria-label="사전등록 고객 프로모션"><strong>사전등록 고객에게는 구독 할인 혜택까지</strong><p>건물을 사전등록한 뒤 가입한 유저와 연결해 주세요.<br>프로모션 대상 유저는 PRO를 할인된 금액으로 구독할 수 있습니다.</p><div><del>연 69,000원</del><b>연 59,000원</b><span>연 10,000원 할인</span></div><small>프로모션 기간 내 가입 및 사전등록 연결을 완료한 고객에게 적용됩니다. 프로모션 기간 중에는 대상 고객이 구독 종료 후 다시 구독해도 연 59,000원이 적용됩니다. 프로모션 종료 후에도 구독을 계속 유지하면 할인 가격이 유지되며, 종료 후 구독이 끊긴 상태에서 다시 구독하면 당시 정상가가 적용됩니다.</small></section>
      <p class="mi-cashout">파이어마일리지는 현금으로도 출금이 가능합니다.</p>
      <p class="mi-reward-note">구독과 소방계획서 작성 완료 조건을 모두 충족한 경우의 예시입니다. </p>
    </section>
    <section data-mi-comic hidden aria-label="유저에게 플랫폼 안내하기">
    <svg class="mi-defs" aria-hidden="true" width="0" height="0" xmlns="http://www.w3.org/2000/svg"><defs>
      <symbol id="mi-manager" viewBox="0 0 110 150"><path d="M13 148v-35q0-28 28-31h28q28 3 28 31v35" fill="#244f72"/><path d="m42 80 13 24 14-24" fill="#fff"/><path d="m51 97 4 7 4-7 3 35-7 10-7-10" fill="#36b8b0"/><rect x="45" y="68" width="20" height="23" rx="9" fill="#edb794"/><ellipse cx="55" cy="46" rx="29" ry="33" fill="#f6c8a7"/><path d="M26 48Q13 8 48 5q41-8 39 43l-10-17q-27 5-33-5L30 49" fill="#233c50"/><path d="M44 48h1m20 0h1" stroke="#253847" stroke-width="4" stroke-linecap="round"/><path d="M47 62q8 7 16 0" fill="none" stroke="#b76f55" stroke-width="2.5" stroke-linecap="round"/><rect x="68" y="110" width="17" height="12" rx="3" fill="#f1fafb"/><path d="m71 117 3 2 6-6" fill="none" stroke="#25a499" stroke-width="2"/></symbol>
      <symbol id="mi-owner" viewBox="0 0 110 150"><path d="M12 148v-32q0-29 28-34h30q28 5 28 34v32" fill="#ecb456"/><path d="m42 82 13 17 14-17" fill="#fff3dc"/><rect x="45" y="68" width="20" height="23" rx="9" fill="#e8ad8b"/><ellipse cx="55" cy="46" rx="29" ry="33" fill="#f6c8a7"/><path d="M26 45Q16 8 51 7q38-5 36 38l-11-17q-13 8-37 0L29 46" fill="#654638"/><path d="M45 48h1m19 0h1" stroke="#46362c" stroke-width="3" stroke-linecap="round"/><rect x="33" y="40" width="19" height="16" rx="6" stroke="#604d44" fill="none" stroke-width="2"/><rect x="59" y="40" width="19" height="16" rx="6" stroke="#604d44" fill="none" stroke-width="2"/><path d="M52 46h7M47 63q8 5 16-1" fill="none" stroke="#604d44" stroke-width="2" stroke-linecap="round"/><path d="M35 119h40" stroke="#d09740" stroke-width="3"/></symbol>
      <symbol id="mi-doc" viewBox="0 0 70 88"><rect x="3" y="3" width="64" height="82" rx="9" fill="white" stroke="#a7becb" stroke-width="2"/><rect x="15" y="17" width="40" height="6" rx="3" fill="#a7becb"/><path d="M17 36h36M17 48h36M17 60h23" stroke="#d5e3e9" stroke-width="4" stroke-linecap="round"/></symbol>
    </defs></svg>
    <div class="mi-panels">
      <article class="mi-panel" data-mi-panel aria-label="1컷 계획서 확인">
        <div class="mi-chapter"><span>01</span> 계획서 확인</div>
        <div class="mi-bubble mi-bubble--manager"><b>매니저</b>소방계획서가 아직이라면,<br>쉽게 작성하는 방법을<br>안내해 드릴게요.</div>
        <div class="mi-scene mi-scene--one" aria-hidden="true"><svg viewBox="0 0 300 160"><path d="M14 149h272" stroke="#c8d9e3" stroke-width="2"/><use href="#mi-manager" x="17" y="8" width="105" height="143"/><use href="#mi-owner" x="177" y="8" width="105" height="143"/><use href="#mi-doc" x="123" y="63" width="49" height="62"/><text x="146" y="43" text-anchor="middle" font-size="28" font-weight="800" fill="#c49344">?</text></svg></div>
        <div class="mi-bubble mi-bubble--owner"><b>건물관리자</b>아직 준비를 못 했어요.<br>어떻게 시작하면 되나요?</div>
      </article>
      <article class="mi-panel" data-mi-panel aria-label="2컷 플랫폼 안내">
        <div class="mi-chapter"><span>02</span> 플랫폼 안내</div>
        <div class="mi-bubble mi-bubble--manager"><b>매니저</b>기본정보 등 필요한 정보는<br>제가 미리 입력해 둘게요.<br>확인하며 작성하시면 돼요.</div>
        <div class="mi-scene mi-scene--two" aria-hidden="true"><svg viewBox="0 0 300 160"><use href="#mi-manager" x="0" y="29" width="83" height="114"/><use href="#mi-owner" x="217" y="29" width="83" height="114"/><rect x="79" y="31" width="142" height="98" rx="10" fill="#fff" stroke="#4c7f94" stroke-width="3"/><path d="M70 133h160l-10 10H80Z" fill="#739eaf"/><rect x="91" y="43" width="118" height="12" rx="4" fill="#e3f1f1"/><rect x="92" y="66" width="69" height="17" rx="6" fill="#d1e8ed"/><rect x="130" y="93" width="78" height="17" rx="6" fill="#d9efe1"/><path d="m185 71 5 5 10-11" stroke="#299680" fill="none" stroke-width="3" stroke-linecap="round"/></svg></div>
        <div class="mi-bubble mi-bubble--owner"><b>건물관리자</b>미리 준비된 정보가 있으니<br>어렵지 않게 시작하겠네요!</div>
      </article>
      <article class="mi-panel" data-mi-panel aria-label="3컷 함께 작성">
        <div class="mi-chapter"><span>03</span> 함께 작성</div>
        <div class="mi-bubble mi-bubble--manager"><b>매니저</b>가입 후 제 매니저 코드로<br>연결을 요청해 주세요.<br>남은 항목은 함께 작성해요.</div>
        <div class="mi-scene mi-scene--three" aria-hidden="true"><svg viewBox="0 0 300 160"><use href="#mi-manager" x="23" y="15" width="98" height="134"/><use href="#mi-owner" x="179" y="15" width="98" height="134"/><use href="#mi-doc" x="121" y="67" width="57" height="72"/><circle cx="151" cy="43" r="22" fill="#2b9c82"/><path d="m140 43 7 7 14-15" stroke="white" fill="none" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path d="m106 28-8-7m98 7 8-7m-55-6v-8" stroke="#8ac4b5" stroke-width="3" stroke-linecap="round"/></svg></div>
        <div class="mi-bubble mi-bubble--owner"><b>건물관리자</b>그럼 플랫폼에 가입하고<br>연결을 요청해 볼게요!</div>
      </article>
    </div>
    <nav class="mi-pager" aria-label="만화 컷 이동"><button type="button" data-mi-prev aria-label="이전 컷">←</button><span data-mi-count aria-live="polite">1 / 3</span><button type="button" data-mi-next aria-label="다음 컷">다음 컷 →</button></nav>
    <div class="mi-message"><strong>“소방계획서.com에서 시작해 보세요.<br class="mi-mobile-break"> 필요한 정보는 미리 준비해 드릴게요.”</strong><p>작성 도움 요청은 매니저 연결 및 PRO 이용 상태에 따라 제공됩니다.</p></div>
    </section>
    </div>
    <div class="mi-foot"><label class="mi-mute" for="mi-mute"><input id="mi-mute" type="checkbox" data-mi-mute><span>다음부터 자동으로 보지 않기</span></label><div class="mi-actions"><button type="button" class="mi-primary mi-register-cta" data-mi-register>첫 건물 사전등록하기 <span aria-hidden="true">→</span></button><button type="button" class="mi-secondary" data-mi-back hidden>리워드 안내</button><button type="button" class="mi-secondary" data-mi-continue>유저 안내 방법 보기 →</button><button type="button" class="mi-secondary" data-mi-start hidden>거래처 확인하기 <span aria-hidden="true">→</span></button></div></div>
  </div>
</dialog>
<script src="/manager_intro.js?v=20261001-worklog-reward" defer></script>
