<?php
$PAGE_TITLE = '서비스';
$ACTIVE     = 'service';
require __DIR__ . '/_header.php';

/* ── 서비스 기능 목록 (여기만 고치면 화면이 바뀝니다) ── */
$features = [
  ['icon'=>'🏢', 'title'=>'건축물대장 조회와 기본정보 관리', 'desc'=>'주소로 건축물대장을 조회하고 대상 건물과 동을 선택합니다. 불러온 정보를 확인·수정하고 다른 업무 서식에 활용할 수 있습니다.'],
  ['icon'=>'🧯', 'title'=>'동별 소방시설 현황', 'desc'=>'관리할 동을 선택하고 각 동에 설치된 소방시설을 기록합니다. 저장된 시설 현황을 업무수행 기록 작성에 활용합니다.'],
  ['icon'=>'📝', 'title'=>'문답으로 작성하는 소방계획서', 'desc'=>'작성 연도와 반영할 자료를 선택하면 저장된 정보를 불러옵니다. 질문을 따라 내용을 보완하고 계획서를 작성·인쇄합니다.'],
  ['icon'=>'🗂', 'title'=>'월별 업무수행 기록', 'desc'=>'해당 월의 소방안전관리 업무 내용을 작성하고 보관합니다. 등록된 소방시설을 바탕으로 필요한 확인 내용을 기록합니다.'],
  ['icon'=>'👥', 'title'=>'자위소방대와 교육·훈련 기록', 'desc'=>'자위소방대 편성, 교육 및 소방훈련 내용을 한곳에서 작성·관리합니다. 필요한 서류는 인쇄해 활용할 수 있습니다.'],
  ['icon'=>'📍', 'title'=>'집결지와 소방차 진입로', 'desc'=>'지도에서 비상 집결지와 소방차 진입로를 각각 지정합니다. 저장한 위치를 팝업으로 확인하고 인쇄 자료에도 활용합니다.'],
  ['icon'=>'💬', 'title'=>'담당 매니저에게 작성 도움 요청', 'desc'=>'기본정보·소방시설 현황·소방계획서에서 어려운 항목을 담당 매니저에게 요청합니다. 매니저는 요청 항목을 확인해 작성을 돕고 완료 상태를 공유합니다.'],
  ['icon'=>'🗺', 'title'=>'매니저 거래처 관리와 사전등록', 'desc'=>'거래처를 지도에서 확인하고 사용승인월별로 조회합니다. 가입 전 건물 기본정보를 사전등록한 뒤 유저의 연결 요청을 수락하며 이어줄 수 있습니다.'],
];

/* ── 요금제 ── */
require_once __DIR__.'/annual_plan.php';
$priceYearly=AP_PRICE;
?>

<style>
/* 서비스 페이지 전용 — 기존 카드/랩 스타일 위에 최소한만 더합니다 */
.svc-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px}
.svc-card{display:flex;gap:12px;align-items:flex-start}
.svc-card .svc-ico{font-size:22px;line-height:1;flex-shrink:0;margin-top:2px}
.svc-card h3{margin:0 0 5px;font-size:15px}
.svc-card p{margin:0;font-size:13.5px;line-height:1.7;opacity:.85}

.svc-sec-t{margin:44px 0 6px;font-size:19px;font-weight:800}
.svc-sec-d{margin:0 0 18px;font-size:13.5px;opacity:.75;line-height:1.7}

/* 요금제 */
.plan-grid{max-width:620px;margin-inline:auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;align-items:stretch}
.plan{position:relative;display:flex;flex-direction:column;gap:12px}
.plan__name{font-size:13px;font-weight:700;opacity:.75}
.plan__price{display:flex;align-items:baseline;gap:5px;flex-wrap:wrap}
.plan__num{font-size:30px;font-weight:900;letter-spacing:-.02em}
.plan__unit{font-size:13px;opacity:.7}
.plan__sub{font-size:12.5px;opacity:.7;line-height:1.6;min-height:34px}
.plan__list{margin:0;padding:0;list-style:none;display:grid;gap:7px;font-size:13px}
.plan__list li{display:flex;gap:7px;align-items:flex-start;line-height:1.6}
.plan__list li::before{content:'✓';font-weight:800;opacity:.6;flex-shrink:0}
.plan__login-note{margin:8px 0 0;padding:14px 12px;border-top:1px solid #e2e8f0;text-align:center;font-size:14px;font-weight:700;line-height:1.7}
.plan__login-note small{display:block;margin-top:4px;font-size:12px;font-weight:400;opacity:.7}
.plan--year{border-color:#7fb069}
.plan__badge{position:absolute;top:-10px;right:14px;background:#7fb069;color:#fff;
  font-size:11px;font-weight:800;padding:4px 10px;border-radius:999px}
.plan__was{font-size:12.5px;opacity:.55;text-decoration:line-through}

.svc-note{margin-top:16px;font-size:12.5px;opacity:.72;line-height:1.8}
.svc-faq{display:grid;gap:10px;margin-top:10px}
.svc-faq h4{margin:0 0 4px;font-size:14px}
.svc-faq p{margin:0;font-size:13px;line-height:1.7;opacity:.82}
</style>

<header class="page-head">
  <div class="page-head__inner">
    <div class="page-head__label"><span></span> 서비스 안내</div>
    <h1>서비스</h1>
    <p>건물관리자와 담당 매니저가 업무 현황을 공유하고, 필요한 기록을 함께 작성·관리하는 공간입니다.
       건물 기본정보부터 소방계획서와 월별 업무 기록까지 한곳에서 이어갑니다.</p>
  </div>
</header>

<main class="wrap">

  <!-- 기능 -->
  <h2 class="svc-sec-t" style="margin-top:8px">이런 일을 할 수 있습니다</h2>
  <p class="svc-sec-d">반복 입력은 줄이고, 기록 관리는 쉽게. 실제 업무 순서대로 이어집니다.</p>
  <div class="svc-grid">
    <?php foreach ($features as $f): ?>
      <div class="card svc-card">
        <div class="svc-ico"><?=$f['icon']?></div>
        <div>
          <h3><?=h($f['title'])?></h3>
          <p><?=h($f['desc'])?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <h2 class="svc-sec-t">함께 작성하고 관리하는 방법</h2>
  <div class="svc-faq">
    <div class="card"><h4>1. 건물 정보를 등록합니다</h4><p>건축물대장을 조회하거나 직접 입력해 기본정보를 준비합니다. 건물의 실제 현황에 맞게 소방시설과 위치 정보를 정리합니다.</p></div>
    <div class="card"><h4>2. 담당 매니저와 연결합니다</h4><p>매니저 코드를 입력하면 가입 시 입력한 건물명으로 연결을 요청합니다. 매니저가 사전등록한 거래처를 연결하면 해당 기본정보가 유저 화면에 반영됩니다.</p></div>
    <div class="card"><h4>3. 기록을 작성하고 진행 상태를 확인합니다</h4><p>업무 카드에서 다음 업무와 완료 상태를 확인합니다. 작성 도움이 필요한 항목은 담당 매니저에게 요청하고, 처리 결과를 함께 확인합니다. 요청 기능의 이용 범위는 PRO 안내에서 확인해 주세요.</p></div>
  </div>

  <h2 class="svc-sec-t">PRO 이용 안내</h2>
  <p class="svc-sec-d">기록 작성과 담당 매니저의 업무 지원을 이어갈 수 있는 연간 구독입니다.</p>
  <div class="plan-grid">
    <div class="card plan plan--year">
      <span class="plan__badge">12개월 이용</span>
      <div class="plan__name">PRO 연간 구독</div>
      <div class="plan__price"><span class="plan__num"><?=number_format($priceYearly)?></span><span class="plan__unit">원 / 년</span></div>
      <div class="plan__sub">연 <?=number_format($priceYearly)?>원으로 12개월간 이용합니다.<br>자동갱신에 동의하면 매년 결제됩니다.</div>
      <ul class="plan__list">
        <li>담당 매니저와 업무 현황 공유 및 작성 도움 요청</li>
        <li>구독 상태와 다음 결제일 확인</li>
        <li>PRO 이용 안내에서 카드 등록과 구독 관리</li>
      </ul>
      <p class="plan__login-note">로그인 후 페이지에서 구독을 이용하세요.<small>건물관리 화면의 ‘PRO 이용 안내’에서 확인하실 수 있습니다.</small></p>
    </div>
  </div>
  <div class="svc-note">· 카드 등록만으로 결제되지는 않습니다. 결제 전 금액과 자동갱신 안내를 확인해 주세요.<br>· 자동갱신을 해제해도 이미 결제한 기간까지 이용할 수 있습니다.</div>

  <h2 class="svc-sec-t">자주 묻는 질문</h2>
  <div class="svc-faq">
    <div class="card"><h4>건축물대장이 조회되지 않으면 어떻게 하나요?</h4><p>조회 결과가 없거나 실제 현황과 다르면 기본정보를 직접 입력·수정할 수 있습니다. 조회된 정보도 대상 건물과 일치하는지 확인해 주세요.</p></div>
    <div class="card"><h4>여러 동의 소방시설을 각각 관리할 수 있나요?</h4><p>시설 현황에 포함할 동을 선택하고 동별로 설치된 설비를 기록할 수 있습니다. 동마다 다른 시설 현황을 구분해 작성하세요.</p></div>
    <div class="card"><h4>작성 방법을 잘 모르면 어떻게 하나요?</h4><p>담당 매니저가 연결되어 있고 요청 기능을 이용할 수 있는 경우, 해당 항목에서 작성 도움을 요청하세요. 매니저는 유저 화면의 알림 표시를 통해 요청한 내용을 확인할 수 있습니다.</p></div>
    <div class="card"><h4>매니저 연결만으로 구독이 시작되나요?</h4><p>매니저 연결과 PRO 구독은 별개입니다. 로그인 후 건물관리 화면의 ‘PRO 이용 안내’에서 이용 조건을 확인하고 구독을 진행해 주세요.</p></div>
    <div class="card"><h4>사전등록한 정보를 연결하면 어떻게 되나요?</h4><p>매니저가 연결 요청을 수락하며 사전등록 거래처를 선택하면 그 기본정보가 유저에게 반영됩니다. 기존 기본정보가 있어도 바뀔 수 있으므로 같은 건물인지 확인한 뒤 연결해 주세요.</p></div>
  </div>

</main>

<?php require __DIR__ . '/_footer.php'; ?>
