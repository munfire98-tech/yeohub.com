<?php
$PAGE_TITLE = '서비스 안내';
$ACTIVE = 'service';
require_once __DIR__ . '/annual_plan.php';
require __DIR__ . '/_header.php';

$proFeatures = [
  ['name'=>'소방계획서', 'desc'=>'건물정보와 작성 자료를 불러와 계획서를 작성·수정하고 인쇄합니다.'],
  ['name'=>'매월 업무수행기록', 'desc'=>'소방시설 현황을 바탕으로 해당 월의 업무 내용을 작성하고 기록을 관리합니다.'],
  ['name'=>'자위소방대 편성', 'desc'=>'대원과 담당 임무를 정리하고 교육·훈련 기록에 활용합니다.'],
  ['name'=>'교육·훈련 기록', 'desc'=>'자위소방대 교육과 소방훈련·교육 실시 결과를 작성하고 보관합니다.'],
  ['name'=>'피난계획', 'desc'=>'층별 대피방법과 집결 절차를 정리하고 비상 집결지·소방차 진입로를 관리합니다.'],
  ['name'=>'비화재보 기록', 'desc'=>'발생 내용과 조치 사항을 기록하고 연결 업체와 함께 확인합니다.'],
  ['name'=>'연결 업체와 협업', 'desc'=>'업무 현황을 공유하고 어려운 작성 항목에 도움을 요청합니다.'],
  ['name'=>'소방교육자료', 'desc'=>'소화기와 옥내소화전 사용법 등 교육영상을 확인합니다.'],
  ['name'=>'기록 보관·서류 인쇄', 'desc'=>'업무별 작성·완료 상태를 확인하고 필요한 서류를 인쇄하거나 PDF로 저장합니다.'],
];
$comparison = [
  ['name'=>'담당·로컬 파트너 연결, QR 가입', 'free'=>true],
  ['name'=>'사전등록 건물정보 불러오기', 'free'=>true],
  ['name'=>'기본정보 작성·수정·저장', 'free'=>true],
  ['name'=>'동별 소방시설 현황 작성·수정·저장', 'free'=>true],
  ['name'=>'소방안전관리자 현황표 인쇄', 'free'=>true],
  ['name'=>'안내 페이지의 문서 샘플 보기', 'free'=>true],
  ['name'=>'소방계획서·피난계획 작성', 'free'=>false],
  ['name'=>'자위소방대 편성·교육·훈련 기록', 'free'=>false],
  ['name'=>'매월 업무수행기록·비화재보 기록', 'free'=>false],
  ['name'=>'연결 업체에게 업무 작성 문의', 'free'=>false],
  ['name'=>'전체 소방교육자료·서류 전체 인쇄', 'free'=>false],
];
?>

<style>
.svc-page{padding-top:32px;word-break:keep-all;overflow-wrap:anywhere}
.svc-hero p{max-width:700px;word-break:keep-all}
.svc-jump{display:flex;flex-wrap:wrap;gap:8px;margin-top:22px}
.svc-jump a{padding:8px 13px;border:1px solid #dbe4f0;border-radius:9px;background:#fff;font-size:13px;font-weight:650}
.svc-section{margin-top:42px;scroll-margin-top:80px}
.svc-section:first-child{margin-top:0}
.svc-section h2{font-size:23px;line-height:1.45;letter-spacing:-.5px;margin-bottom:8px}
.svc-lead{color:var(--mut2);font-size:14px;line-height:1.8;margin-bottom:20px}
.svc-plans{display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:stretch}
.svc-plan{min-width:0;background:#fff;border:1px solid var(--bd);border-radius:18px;padding:26px;display:flex;flex-direction:column}
.svc-plan--pro{border-color:#9cbaef;background:linear-gradient(150deg,#f1f6ff,#fff 65%)}
.svc-badge{display:inline-flex;align-self:flex-start;align-items:center;padding:4px 10px;border-radius:7px;font-size:12px;font-weight:750;background:#eaf3ef;color:#26745d;margin-bottom:16px}
.svc-plan--pro .svc-badge{color:#245dd4;background:#e6efff}
.svc-plan h3{font-size:21px;letter-spacing:-.5px;line-height:1.5}
.svc-price{display:flex;flex-wrap:wrap;align-items:baseline;gap:8px;margin:10px 0 13px}
.svc-price strong{font-size:36px;line-height:1.2;letter-spacing:-1px}
.svc-price span{font-size:13px;color:var(--mut2)}
.svc-plan-copy{font-size:14px;color:var(--mut2);line-height:1.8}
.svc-list{list-style:none;display:grid;gap:10px;margin:20px 0 0;font-size:14px}
.svc-list li{position:relative;padding-left:21px;line-height:1.7}
.svc-list li::before{content:'✓';position:absolute;left:0;color:#26745d;font-weight:800}
.svc-plan--pro .svc-list li::before{color:var(--brand)}
.svc-plan-end{margin-top:auto;padding-top:22px}
.svc-plan-note{padding-top:15px;border-top:1px solid var(--bd);font-size:13px;color:var(--mut2);line-height:1.8}
.svc-plan-note b{display:block;color:var(--fg);margin-bottom:3px}
.svc-offer{margin-top:18px;padding:18px 20px;border:1px solid #dce7ef;border-radius:12px;background:#edf4fa;font-size:13px;color:var(--mut2);line-height:1.9}
.svc-offer strong{display:block;color:var(--fg);font-size:14px;margin-bottom:4px}
.svc-compare{border:1px solid var(--bd);border-radius:15px;overflow:hidden;background:#fff}
.svc-table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:14px}
.svc-table caption{position:absolute;width:1px;height:1px;padding:0;overflow:hidden;clip-path:inset(50%);white-space:nowrap}
.svc-table col.svc-status-col{width:96px}
.svc-table th,.svc-table td{padding:15px 18px;border-bottom:1px solid #edf0f5;text-align:center;vertical-align:middle}
.svc-table thead th{background:#f7f9fc;font-weight:750;color:var(--mut2)}
.svc-table thead th:last-child{background:#edf4ff;color:#245dd4}
.svc-table th:first-child{text-align:left}
.svc-table tbody th{font-weight:550;line-height:1.7}
.svc-table tbody tr:last-child>*{border-bottom:0}
.svc-yes{display:inline-flex;align-items:center;justify-content:center;min-width:42px;padding:3px 7px;border-radius:6px;color:#247258;background:#edf7f1;font-size:12px;font-weight:700;white-space:nowrap}
.svc-table td:last-child .svc-yes{color:#245dd4;background:#edf4ff}
.svc-no{color:#7a8699;font-size:12px;white-space:nowrap}
.svc-note{margin-top:12px;color:var(--mut2);font-size:12px;line-height:1.85}
.svc-features{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.svc-feature{background:#fff;border:1px solid var(--bd);border-radius:12px;padding:20px;min-width:0}
.svc-feature h3{font-size:16px;margin-bottom:7px;line-height:1.5}
.svc-feature p{font-size:13px;color:var(--mut2);line-height:1.8}
.svc-step-list{counter-reset:svc-steps;list-style:none;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.svc-step-list li{counter-increment:svc-steps;position:relative;padding:20px;border:1px solid var(--bd);border-radius:13px;background:#fff}
.svc-step-list li::before{content:counter(svc-steps);display:flex;align-items:center;justify-content:center;width:30px;height:30px;background:#eaf1ff;color:#245dd4;border-radius:9px;font-weight:800;margin-bottom:13px}
.svc-step-list h3{font-size:16px;margin-bottom:7px}
.svc-step-list p{font-size:13px;line-height:1.8;color:var(--mut2)}
.svc-manager{padding:24px;background:#f0f6f4;border:1px solid #d5e6df;border-radius:15px}
.svc-manager .svc-lead{margin-bottom:0}
.svc-manager .svc-list{grid-template-columns:repeat(3,minmax(0,1fr));margin-top:16px}
.svc-sample{background:#fff;border:1px solid var(--bd);border-radius:14px;overflow:hidden}
.svc-sample summary{cursor:pointer;display:list-item;list-style-position:inside;padding:18px 20px;font-size:15px;font-weight:700;color:#245dd4}
.svc-sample summary:focus-visible,.svc-faq summary:focus-visible,.svc-jump a:focus-visible{outline:3px solid #739fee;outline-offset:-3px;border-radius:9px}
.svc-sample[open] summary{border-bottom:1px solid var(--bd)}
.svc-sample-body{padding:20px}
.svc-sample-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.svc-paper{padding:20px;border:1px solid #dce3ed;border-radius:10px;background:#fafcfe}
.svc-paper small{font-size:11px;color:#6d7f98;letter-spacing:.4px;font-weight:700}
.svc-paper h3{font-size:17px;margin:6px 0 15px;padding-bottom:12px;border-bottom:2px solid #263e61}
.svc-paper dl{display:grid;grid-template-columns:auto 1fr;gap:9px 14px;font-size:13px}
.svc-paper dt{color:var(--mut2)}
.svc-paper dd{font-weight:600;margin:0}
.svc-paper p{font-size:13px;line-height:1.9;margin-top:14px;color:var(--mut2)}
.svc-faq{display:grid;gap:10px}
.svc-faq details{border:1px solid var(--bd);background:#fff;border-radius:12px;overflow:hidden}
.svc-faq summary{padding:17px 20px;cursor:pointer;font-size:14px;font-weight:650;list-style-position:inside}
.svc-faq details p{padding:0 20px 19px;color:var(--mut2);font-size:13px;line-height:1.9}
@media(max-width:800px){.svc-features{grid-template-columns:repeat(2,minmax(0,1fr))}.svc-step-list{grid-template-columns:1fr}.svc-step-list li{padding-left:65px}.svc-step-list li::before{position:absolute;left:19px;top:19px}.svc-manager .svc-list{grid-template-columns:1fr;gap:7px}}
@media(max-width:600px){.svc-page{padding:24px 16px 50px}.svc-hero .page-head__inner{padding:32px 16px 28px}.svc-hero h1{font-size:28px;line-height:1.35}.svc-hero p{font-size:14px;line-height:1.8}.svc-section{margin-top:32px}.svc-section h2{font-size:21px}.svc-plans,.svc-sample-grid{grid-template-columns:1fr}.svc-plan{padding:22px 20px}.svc-price strong{font-size:32px}.svc-features{grid-template-columns:1fr}.svc-feature{padding:18px}.svc-table{font-size:13px}.svc-table col.svc-status-col{width:57px}.svc-table th,.svc-table td{padding:12px 9px}.svc-table th:first-child{padding-left:13px}.svc-yes{min-width:34px;padding:3px 5px;font-size:11px}.svc-no{font-size:11px}.svc-manager{padding:20px}.svc-sample-body{padding:14px}.svc-paper{padding:17px}.svc-offer{padding:16px}.svc-jump a{padding:8px 10px;font-size:12px}}
</style>

<header class="page-head svc-hero">
  <div class="page-head__inner">
    <div class="page-head__label"><span></span> 서비스 안내</div>
    <h1>건물정보 준비부터<br>매월 소방안전 기록까지</h1>
    <p>무료로 건물정보를 준비하고 파트너와 연결하세요.<br>PRO를 구독하면 소방계획서부터 매월 업무기록까지 한곳에서 작성·관리할 수 있습니다.</p>
    <nav class="svc-jump" aria-label="서비스 안내 바로가기"><a href="#service-plans">무료 · PRO</a><a href="#service-compare">기능 비교</a><a href="#service-manager">파트너 이용</a><a href="#service-samples">문서 샘플</a></nav>
  </div>
</header>

<main class="wrap svc-page">
  <section class="svc-section" id="service-plans" aria-labelledby="service-plans-title">
    <h2 id="service-plans-title">준비는 무료로, 기록 관리는 PRO로</h2>
    <p class="svc-lead">건물 기본정보와 소방시설 현황은 구독 전에도 작성·수정·저장할 수 있습니다.</p>
    <div class="svc-plans">
      <article class="svc-plan">
        <span class="svc-badge">기본 제공</span><h3>무료로 시작하기</h3>
        <div class="svc-price"><strong>무료</strong><span>건물정보 준비 · 파트너 연결</span></div>
        <p class="svc-plan-copy">파트너를 연결하고, 내 건물의 정보를 먼저 정리해 보세요.</p>
        <ul class="svc-list">
          <li>담당·로컬 파트너 연결 및 QR 가입</li>
          <li>사전등록 건물정보 불러오기</li>
          <li>건축물대장 조회와 기본정보 작성·수정·저장</li>
          <li>동별 소방시설 현황 작성·수정·저장</li>
          <li>소방안전관리자 현황표 인쇄·PDF 저장</li>
          <li><a href="#service-samples">소방계획서·업무기록 샘플 보기</a></li>
        </ul>
        <div class="svc-plan-end"><p class="svc-plan-note"><b>파트너 연결부터 시작하세요</b>건물정보 작성은 파트너 연결 완료 후 이용할 수 있습니다. 파트너 연결만으로 결제되지는 않습니다.</p></div>
      </article>
      <article class="svc-plan svc-plan--pro">
        <span class="svc-badge">프로모션 대상 고객</span><h3>소방안전 기록관리 PRO</h3>
        <div class="svc-price"><strong><?=number_format(AP_PROMO_PRICE)?>원</strong><span>12개월 이용권</span></div>
        <p class="svc-plan-copy">연결 업체와 함께 소방계획서 작성·수정, 업무수행기록, 교육자료 및 문서 보관 기능을 이용하세요.</p>
        <ul class="svc-list">
          <li>무료 기본 기능 모두 포함</li>
          <li>소방계획서·피난계획 작성·수정</li>
          <li>매월 업무수행기록·비화재보 기록 관리</li>
          <li>자위소방대 편성 및 교육·훈련 기록</li>
          <li>연결 업체와 업무 공유·작성 문의</li>
          <li>전체 소방교육자료·서류 전체 인쇄·PDF 저장</li>
        </ul>
        <div class="svc-plan-end"><p class="svc-plan-note"><b>로그인 후 페이지에서 구독을 이용하세요.</b>건물관리 화면의 ‘PRO 이용 안내’에서 적용 금액을 확인하고 카드 등록·구독을 진행할 수 있습니다. 12개월마다 자동갱신되며, 다음 결제는 구독 관리에서 해제할 수 있습니다.</p></div>
      </article>
    </div>
    <div class="svc-offer"><strong>프로모션 기간에 구독하고, 연 <?=number_format(AP_PROMO_PRICE)?>원 혜택을 이어가세요.</strong>사전등록 거래처 또는 로컬 파트너 연결이 완료된 프로모션 대상 고객에게 적용됩니다. 소방안전관리 업무를 이어가며 구독을 유지하는 동안, 프로모션 종료 후에도 매년 같은 금액으로 이용할 수 있습니다. 프로모션 기간 중에는 대상 고객이 구독 종료 후 다시 구독해도 같은 가격이 적용됩니다.<br><?=h(ap_promotion_period_text())?> 최종 적용 금액과 다음 결제일은 결제 화면에서 확인해 주세요.</div>
  </section>

  <section class="svc-section" id="service-compare" aria-labelledby="service-compare-title">
    <h2 id="service-compare-title">무료와 PRO, 어디까지 이용할 수 있나요?</h2>
    <p class="svc-lead">정보 준비부터 실제 업무 기록까지, 필요한 기능을 확인하세요.</p>
    <div class="svc-compare"><table class="svc-table"><caption>건물관리 회원의 무료·PRO 기능 비교</caption><colgroup><col><col class="svc-status-col"><col class="svc-status-col"></colgroup>
      <thead><tr><th scope="col">이용 기능</th><th scope="col">무료</th><th scope="col">PRO</th></tr></thead>
      <tbody><?php foreach ($comparison as $item): ?><tr><th scope="row"><?=h($item['name'])?></th><td><?php if ($item['free']): ?><span class="svc-yes">포함</span><?php else: ?><span class="svc-no">미포함</span><?php endif; ?></td><td><span class="svc-yes">포함</span></td></tr><?php endforeach; ?></tbody>
    </table></div>
    <p class="svc-note">건물관리 회원 기준입니다. 기본정보·시설 현황 작성은 파트너 연결 완료 후, PRO 업무는 연결과 구독 완료 후 이용할 수 있습니다. 작성 문의은 연결된 업체에게 전달되며, 실제 지원 범위와 일정은 연결 업체와 확인해 주세요.</p>
  </section>

  <section class="svc-section" aria-labelledby="service-features-title">
    <h2 id="service-features-title">PRO에서 이어지는 소방안전 업무</h2>
    <p class="svc-lead">한 번 입력한 건물정보를 활용해 반복 입력을 줄이고, 필요한 기록을 함께 관리합니다.</p>
    <div class="svc-features"><?php foreach ($proFeatures as $feature): ?><article class="svc-feature"><h3><?=h($feature['name'])?></h3><p><?=h($feature['desc'])?></p></article><?php endforeach; ?></div>
  </section>

  <section class="svc-section" aria-labelledby="service-start-title">
    <h2 id="service-start-title">이렇게 시작하세요</h2>
    <p class="svc-lead">현장에서 받은 QR이나 파트너 코드로 간편하게 연결할 수 있습니다.</p>
    <ol class="svc-step-list">
      <li><h3>파트너와 연결</h3><p>건물 초대 QR로 가입하면 해당 건물과 파트너가 연결됩니다. 일반 가입 후에는 파트너 코드로 요청하거나 로컬 파트너를 선택하고 수락을 기다려 주세요.</p></li>
      <li><h3>무료로 건물정보 준비</h3><p>사전등록 정보를 확인하거나 건축물대장 조회·직접 입력으로 기본정보와 시설 현황을 정리합니다. 현황표도 인쇄해 보세요.</p></li>
      <li><h3>PRO로 기록 작성 시작</h3><p>이용 조건과 금액을 확인한 뒤 카드 등록·구독을 진행합니다. 소방계획서와 월별 기록을 작성하고 업무별 진행 상태를 확인하세요.</p></li>
    </ol>
  </section>

  <section class="svc-section svc-manager" id="service-manager" aria-labelledby="service-manager-title">
    <span class="svc-badge">파트너 기본 기능 · 무료</span><h2 id="service-manager-title">파트너는 거래처부터 준비하세요</h2>
    <p class="svc-lead">파트너 센터에서 건물 사전등록·지도 관리·방문일정을 이용할 수 있습니다. 유저의 PRO 구독과는 별개입니다.</p>
    <ul class="svc-list"><li>건물 사전등록과 기본정보 준비</li><li>지도에서 거래처·연결 상태 확인</li><li>방문일정·메모·방문 기록 관리</li><li>건물별 초대 QR로 유저 연결</li><li>담당 유저의 업무 현황 확인</li><li>거래처 요약·용도·연면적 확인</li></ul>
    <p class="svc-note">연결된 유저의 PRO 업무 작성·지원 기능은 해당 유저의 구독 상태에 따라 이용할 수 있습니다.</p>
  </section>

  <section class="svc-section" id="service-samples" aria-labelledby="service-samples-title">
    <h2 id="service-samples-title">어떤 기록을 작성하나요?</h2>
    <p class="svc-lead">구독 전에 문서에 담기는 내용을 간단한 예시로 살펴보세요.</p>
    <details class="svc-sample"><summary>소방계획서·업무수행기록 샘플 펼치기</summary><div class="svc-sample-body">
      <div class="svc-sample-grid">
        <article class="svc-paper"><small>가상 건물 · 내용 예시</small><h3>소방계획서</h3><dl><dt>대상명</dt><dd>예시빌딩</dd><dt>건물 현황</dt><dd>지상 3층 · 지하 1층</dd><dt>담당자</dt><dd>예시 관리자</dd></dl><p>건물 기본정보 → 소방시설 현황 → 자위소방대 편성 → 피난계획 → 교육·훈련 계획</p></article>
        <article class="svc-paper"><small>가상 건물 · 내용 예시</small><h3>월별 업무수행기록</h3><dl><dt>작성 월</dt><dd>예시 연도 10월</dd><dt>업무 내용</dt><dd>소화기 위치·외관 확인</dd><dt>조치 기록</dt><dd>주변 적치물 정리</dd></dl><p>작성할 월 선택 → 시설별 업무 내용 기록 → 확인·조치 사항 작성 → 저장·인쇄</p></article>
      </div><p class="svc-note">이 예시는 서비스 이해를 위한 요약이며, 실제 법정 서식이나 점검 결과가 아닙니다. 실제 기록은 해당 건물의 현황과 수행한 업무에 맞게 작성해 주세요.</p>
    </div></details>
  </section>

  <section class="svc-section" aria-labelledby="service-faq-title">
    <h2 id="service-faq-title">자주 묻는 질문</h2>
    <div class="svc-faq">
      <details><summary>무료로 입력한 정보는 구독하면 다시 작성해야 하나요?</summary><p>아니요. 같은 계정에 저장한 기본정보와 소방시설 현황을 이어서 활용할 수 있습니다. PRO 구독 후 필요한 업무 기록을 추가로 작성하세요.</p></details>
      <details><summary>파트너 연결이나 카드 등록만으로 결제되나요?</summary><p>파트너 연결만으로는 결제되지 않으며, 카드 등록만으로도 결제되지 않습니다. 로그인 후 ‘PRO 이용 안내’에서 최종 금액과 자동갱신 조건을 확인하고 구독을 시작해 주세요.</p></details>
      <details><summary>건축물대장이 조회되지 않거나 여러 동이 있으면 어떻게 하나요?</summary><p>조회되지 않거나 실제 현황과 다르면 기본정보를 직접 입력·수정할 수 있습니다. 여러 동이 있는 경우 관리할 동과 기본정보를 표시할 기준동을 선택하고, 동별 소방시설 현황을 작성하세요. 조회 결과가 실제 대상 건물과 같은지 먼저 확인해 주세요.</p></details>
      <details><summary>자동갱신을 중단할 수 있나요?</summary><p>자동갱신을 해제하면 다음 결제만 중단되고 이미 결제한 기간까지 이용할 수 있습니다. 로그인 후 구독 관리 화면에서 이용기간과 다음 결제일을 확인해 주세요.</p></details>
      <details><summary>프로모션이 끝나도 <?=number_format(AP_PROMO_PRICE)?>원으로 이용할 수 있나요?</summary><p>프로모션 대상 고객이 기간 내 구독을 시작하고 계속 유지하면, 프로모션 종료 후에도 매년 <?=number_format(AP_PROMO_PRICE)?>원이 적용됩니다. 프로모션 기간 중에는 구독 종료 후 다시 구독해도 같은 가격이 적용됩니다. 프로모션 종료 후 구독이 끊긴 상태에서 다시 구독하면 당시 요금이 적용됩니다. 최종 금액은 결제 화면에서 확인해 주세요.</p></details>
    </div>
  </section>
</main>

<?php require __DIR__ . '/_footer.php'; ?>
