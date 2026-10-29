<?php
// Included by partner_center.php only after the manager workspace is available.
if(empty($msReady))return;
$introScope=hash('sha256',(string)($headerUid??mg_uid()));
?>
<link rel="stylesheet" href="/manager_intro.css?v=20261006-no-mileage-v3">
<dialog id="manager-intro" class="mi-dialog" aria-labelledby="mi-title" data-scope="<?=htmlspecialchars($introScope,ENT_QUOTES,'UTF-8')?>">
  <div class="mi-content">
    <header class="mi-head">
      <div><span class="mi-eyebrow">파트너 이용 안내</span><h2 id="mi-title">등록부터 꾸준한 관리까지</h2><p data-mi-subtitle>등록·구독 후에도 유저의 업무를 함께 살펴보세요.</p></div>
      <button type="button" class="mi-close" data-mi-close aria-label="안내 닫기">×</button>
    </header>
    <nav class="mi-tabs" role="tablist" aria-label="이용 안내 방식">
      <button type="button" role="tab" id="mi-tab-video" aria-controls="mi-video-panel" aria-selected="true" data-mi-tab="video">▶ <span>48초 영상</span></button>
      <button type="button" role="tab" id="mi-tab-comic" aria-controls="mi-comic-panel" aria-selected="false" tabindex="-1" data-mi-tab="comic">6컷 안내</button>
      <button type="button" role="tab" id="mi-tab-rewards" aria-controls="mi-rewards-panel" aria-selected="false" tabindex="-1" data-mi-tab="rewards">리워드</button>
    </nav>
    <div class="mi-entry-bar"><span>건물 하나부터<br><b>간단하게 시작하세요</b></span><div class="mm-management-actions"><a href="/manager_addresses.php?new=1" class="mm-draft-add" data-mi-register aria-haspopup="dialog"><span aria-hidden="true">＋</span><span class="mm-draft-copy"><b>건물 사전등록</b><small>유저 연결 전에 미리 준비하세요</small></span></a></div></div>
    <div class="mi-body">
    <section id="mi-video-panel" data-mi-video role="tabpanel" aria-labelledby="mi-tab-video" class="mi-video-panel">
      <div class="mi-video-frame">
        <video data-mi-player controls playsinline preload="metadata" poster="/assets/manager-intro/partner-guide-48s-v1.jpg" aria-label="파트너 이용 안내 48초 영상">
          <source src="/media/partner-guide-48s-v1.mp4" type="video/mp4">
          <track kind="captions" src="/media/partner-guide-48s-v1.vtt" srclang="ko" label="한국어">
          이 브라우저에서는 영상을 재생할 수 없습니다.
        </video>
        <p class="mi-video-error" data-mi-video-error hidden>영상을 불러오지 못했습니다. <a href="/media/partner-guide-48s-v1.mp4" target="_blank" rel="noopener">영상 직접 열기</a> 또는 6컷 안내를 이용해 주세요.</p>
      </div>
      <div class="mi-video-guide">
        <span class="mi-video-eyebrow">이렇게 시작하면 됩니다</span><h3>등록부터 구독 이후까지,<br>파트너가 함께합니다.</h3>
        <ol><li><b>건물 사전등록</b><span>지도에서 건물 선택 → 유저 연결 · QR</span></li><li><b>유저 가입과 구독</b><span>QR로 가입 → 자동 연결 → 카드 등록·결제</span></li><li><b>시설 현황과 월 기록</b><span>시설을 저장하고, 유저가 실제 월 업무를 기록</span></li><li><b>구독 후 업무 확인·도움</b><span>유저 화면의 완료·미작성 상태를 보고 필요한 작성을 도와주세요.</span></li></ol>
      </div>
    </section>
    <section id="mi-rewards-panel" data-mi-rewards hidden class="mi-rewards" role="tabpanel" aria-labelledby="mi-tab-rewards">
      <div class="mi-reward-hero"><span class="mi-reward-tag">현장의 도움을 플랫폼에서</span><h3>현장에서 하던 도움을<br>더 정확하고 간단하게, 리워드까지.</h3><p>첫 실결제 구독자 5명까지, 첫 월 기록 완료 시<br><b>10,000마일리지</b>를 바로 적립해 드립니다.</p></div>
      <div class="mi-help-time"><strong>유저 1명당 예상 도움 시간 약 10분</strong><p>유저가 카드 등록·구독을 진행하는 동안, 파트너는 연결된 유저의 건물관리 화면에서 소방시설 현황을 체크하고 저장해 주세요.<br>도움 시간은 건물 규모와 작성 상태에 따라 달라질 수 있습니다.</p></div>
      <div class="mi-reward-stats"><div><span>파트너당 첫 구독자 5명 · 1명당</span><strong>10,000 <small>마일리지</small></strong><p>실결제 + 시설 현황 저장 + 첫 월 기록</p></div><div><span>이후 작성 월마다 · 최대 11회</span><strong>1,000 <small>마일리지</small></strong><p>첫 구독 기간 합계 최대 21,000마일리지</p></div></div><div class="mi-starter-flow"><span>건물 연결</span><span>실결제</span><span>시설 확인</span><span>첫 월 기록</span><b>10,000마일리지</b></div>
      <div class="mi-renewal-reward"><strong>첫 5곳의 시작부터, 매월 기록까지 함께</strong><p>5명이 첫 조건을 모두 완료하면 총 50,000마일리지가 먼저 적립됩니다. 첫 구독월을 포함한 12개월 안에서 이후 작성 월마다 추가 적립되며, 미작성 월은 이월하지 않습니다. 6번째 유저·기존 결제·다음 구독부터는 작성 월마다 1,500마일리지가 적립됩니다.</p></div>
      <section class="mi-quickstart" aria-labelledby="mi-quickstart-title">
        <h3 id="mi-quickstart-title">건물 하나부터, 편하게 시작해 보세요</h3>
        <div class="mi-quickstart-grid">
          <article><span class="mi-quickstart-step">01 · 건물 준비</span><h4>건물을 사전등록하고<br>자유롭게 이용해 보세요</h4><p>건물의 기본정보와 지도 위치를 저장하면 지도에 건물 카드가 생깁니다. 카드를 누르고 ‘유저 연결 · QR’을 선택해 초대 QR을 보여주세요.</p><button type="button" data-mi-register>건물 사전등록하기 <span aria-hidden="true">→</span></button></article>
          <article><span class="mi-quickstart-step">02 · 방문 계획</span><h4>방문일정 모드도<br>이용해 보세요</h4><p>지도에서 방문할 거래처를 고르고 달력에 일정을 등록해 보세요. 방문 메모와 완료 여부까지 한곳에서 관리할 수 있습니다.</p><p class="mi-quickstart-tip">PC에서는 달력으로 끌어 놓고, 모바일에서는 거래처 선택 후 날짜를 정하면 됩니다.</p><button type="button" data-mi-visits>방문일정 모드 열기 <span aria-hidden="true">→</span></button></article>
        </div>
      </section>
      <section class="mi-promo" aria-label="사전등록 고객 프로모션"><strong>사전등록 고객에게는 구독 할인 혜택까지</strong><p>건물을 사전등록한 뒤 가입한 유저와 연결해 주세요.<br>프로모션 대상 유저는 PRO를 할인된 금액으로 구독할 수 있습니다.</p><div><del>연 69,000원</del><b>연 59,000원</b><span>연 10,000원 할인</span></div><small>프로모션 기간 내 가입 및 사전등록 연결을 완료한 고객에게 적용됩니다. 프로모션 기간 중에는 대상 고객이 구독 종료 후 다시 구독해도 연 59,000원이 적용됩니다. 프로모션 종료 후에도 구독을 계속 유지하면 할인 가격이 유지되며, 종료 후 구독이 끊긴 상태에서 다시 구독하면 당시 정상가가 적용됩니다.</small></section>
      <p class="mi-cashout">1마일리지 = 1원 · 5,000마일리지면 5,000원 출금 신청</p>
      <p class="mi-reward-note">새 혜택은 <?=htmlspecialchars((new DateTimeImmutable(MR_STARTER_START))->format("Y.m.d H:i"),ENT_QUOTES,"UTF-8")?> 이후 첫 실결제 고객부터 배정됩니다. 첫 적립은 소방시설 현황 확인·저장 후 유저의 실제 월 기록 작성이 필요합니다. 같은 고객의 재가입·재구독으로 첫 혜택이 반복되지 않으며 테스트 결제는 제외됩니다. 현금 출금은 관리자 확인 후 송금합니다.</p>
    </section>
    <section id="mi-comic-panel" data-mi-comic hidden role="tabpanel" aria-labelledby="mi-tab-comic">
      <div class="mi-comic-ready"><div class="mi-comic-ready__copy"><strong>사전등록하면, 지도에 건물 카드가 생겨요</strong><p>기본정보와 지도 위치를 저장한 뒤 아래 순서로 QR을 열어 주세요.</p><ol class="mi-comic-route" aria-label="초대 QR 여는 순서"><li>사전등록</li><li>지도 건물 카드</li><li>유저 연결 · QR</li></ol></div></div>
      <div class="mi-panels mi-comic-panels">
        <article class="mi-panel mi-comic-panel" data-mi-panel aria-label="1컷 초대 QR 보여주기">
          <div class="mi-chapter"><span>01</span><h3>초대 QR 보여주기</h3></div>
          <figure class="mi-comic-art mi-comic-art--single"><img src="/assets/manager-intro/qr-scan-first-panel-v1.png" alt="파트너가 보여주는 QR을 여성이 휴대폰으로 촬영하며, 여성 휴대폰의 카메라 화면에도 QR과 스캔 테두리가 표시된 모습" width="1254" height="1254" decoding="async"></figure>
          <div class="mi-comic-line"><b>파트너</b><p>“카메라로 이 QR을 찍어 주세요.”</p></div>
          <p class="mi-comic-description">지도에 생긴 건물 카드를 누른 뒤 ‘유저 연결 · QR’을 선택하세요. 생성된 QR을 유저에게 보여주세요.</p>
        </article>
        <article class="mi-panel mi-comic-panel" data-mi-panel aria-label="2컷 가입하면 자동 연결">
          <div class="mi-chapter"><span>02</span><h3>가입하면 자동 연결</h3></div>
          <figure class="mi-comic-art" style="--mi-column:1;--mi-row:0"><img src="/assets/manager-intro/qr-reward-story-v2.png" alt="유저가 휴대폰으로 가입하고 건물과 파트너 연결을 확인하는 모습" width="1536" height="1024" decoding="async"></figure>
          <div class="mi-comic-line"><b>유저</b><p>“건물과 파트너가 바로 연결됐어요!”</p></div>
          <p class="mi-comic-description">초대 화면에서 가입하면 사전등록 건물정보와 연결 업체가 자동으로 연결됩니다.</p>
        </article>
        <article class="mi-panel mi-comic-panel" data-mi-panel aria-label="3컷 결제 카드 등록">
          <div class="mi-chapter"><span>03</span><h3>결제 카드 등록</h3></div>
          <figure class="mi-comic-art" style="--mi-column:2;--mi-row:0"><img src="/assets/manager-intro/qr-reward-story-v2.png" alt="유저가 자신의 휴대폰과 카드로 안전하게 카드 등록을 진행하는 모습" width="1536" height="1024" decoding="async"></figure>
          <div class="mi-comic-line"><b>유저</b><p>“안내에 따라 카드를 등록할게요.”</p></div>
          <p class="mi-comic-description">유저가 본인 휴대폰에서 토스페이먼츠 카드 인증을 진행합니다. 카드 등록만으로 결제되지는 않습니다.</p>
        </article>
        <article class="mi-panel mi-comic-panel" data-mi-panel aria-label="4컷 유저는 구독, 파트너는 시설 체크">
          <div class="mi-chapter"><span>04</span><h3>구독과 시설 체크</h3></div>
          <figure class="mi-comic-art" style="--mi-column:0;--mi-row:1"><img src="/assets/manager-intro/qr-reward-story-v2.png" alt="유저는 휴대폰으로 구독을 진행하고, 옆의 파트너는 별도 태블릿에서 소화기와 소화전 등 설치 시설을 체크하는 모습" width="1536" height="1024" decoding="async"></figure>
          <div class="mi-comic-line mi-comic-line--manager"><b>그동안 파트너는</b><p>“구독하시는 동안 시설 현황을 체크해 둘게요.”</p></div>
          <div class="mi-comic-description mi-comic-parallel"><p><b>유저</b> 적용 금액·갱신 조건 확인 후 구독 결제</p><p><b>파트너</b> 연결된 유저의 건물관리 화면 → 소방시설 현황 → 설치 시설 체크·저장</p></div>
        </article>
        <article class="mi-panel mi-comic-panel" data-mi-panel aria-label="5컷 그달 업무기록 저장">
          <div class="mi-chapter"><span>05</span><h3>그달 업무기록 저장</h3></div>
          <figure class="mi-comic-art" style="--mi-column:1;--mi-row:1"><img src="/assets/manager-intro/qr-reward-story-v2.png" alt="유저가 노트북에서 해당 월 업무수행기록을 작성하고 저장하는 모습" width="1536" height="1024" decoding="async"></figure>
          <div class="mi-comic-line"><b>유저</b><p>“이번 달 수행한 업무를 기록했어요.”</p></div>
          <p class="mi-comic-description">연동된 시설과 월 기록 기본 설정을 확인한 뒤, ‘매월 기록’에서 실제 수행일자·수행자·업무 내용·확인결과를 작성하고 저장합니다.</p>
        </article>
        <article class="mi-panel mi-comic-panel" data-mi-panel aria-label="6컷 파이어 마일리지 적립">
          <div class="mi-chapter"><span>06</span><h3>파이어 마일리지 적립</h3></div>
          <figure class="mi-comic-art" style="--mi-column:2;--mi-row:1"><img src="/assets/manager-intro/qr-reward-story-v2.png" alt="월 기록 저장 후 파트너의 휴대폰에 F 마일리지 적립 알림이 나타나는 모습" width="1536" height="1024" decoding="async"></figure>
          <div class="mi-comic-line"><b>파트너</b><p>“이번 달 작성 리워드가 쌓였네요!”</p></div>
          <p class="mi-comic-description">첫 5명 혜택 대상은 첫 조건 완료 시 10,000마일리지, 이후 작성 월마다 1,000마일리지입니다. 일반 월 기록은 1,500마일리지가 적립됩니다.</p>
        </article>
      </div>
      <div class="mi-comic-rule"><span class="mi-comic-rule__icon" aria-hidden="true">F</span><div><strong>구독 후에도, 업무 상황을 확인하고 도와주세요.</strong><p>파트너는 유저 화면에서 완료·미작성 상태를 살펴보고 필요한 업무의 작성을 도울 수 있습니다. 정상 결제한 유효 구독과 파트너 연결을 유지하고, 시설 현황 확인·저장 후 유저가 실제 수행한 해당 월 기록을 작성·저장해 주세요. 테스트 결제·미작성 월은 제외하며, 같은 월의 반복 수정·재구독으로 중복 적립되지 않습니다.</p></div></div>
    </section>
    </div>
    <nav class="mi-pager" hidden data-mi-comic-controls aria-label="만화 컷 이동"><button type="button" data-mi-prev aria-label="이전 컷">← 이전</button><span data-mi-count aria-live="polite">1 / 6</span><button type="button" data-mi-next aria-label="다음 컷">다음 컷 →</button></nav>
    <div class="mi-foot"><label class="mi-mute" for="mi-mute"><input id="mi-mute" type="checkbox" data-mi-mute><span>다음부터 자동으로 보지 않기</span></label><div class="mi-actions"><button type="button" class="mi-primary" data-mi-start>지도에서 시작하기 <span aria-hidden="true">→</span></button></div></div>
  </div>
</dialog>
<script src="/manager_intro.js?v=20261016-map-2" defer></script>
