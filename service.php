<?php
$PAGE_TITLE = '서비스';
$ACTIVE     = 'service';
require __DIR__ . '/_header.php';

/* ── 서비스 기능 목록 (여기만 고치면 화면이 바뀝니다) ── */
$features = [
  ['icon'=>'🏢', 'title'=>'건축물대장 자동 조회',
   'desc'=>'건물명이나 주소만 검색하면 연면적·층수·구조·용도·사용승인일이 자동으로 채워집니다. 일일이 옮겨 적을 필요가 없습니다.'],
  ['icon'=>'📋', 'title'=>'건물 기본정보 한 번만 입력',
   'desc'=>'한 번 입력한 대상물 정보를 소방계획서·업무수행기록표 등 여러 서식이 함께 씁니다. 같은 내용을 반복해서 적지 않습니다.'],
  ['icon'=>'📝', 'title'=>'소방계획서 작성',
   'desc'=>'저장된 건물 정보를 불러와 소방계획서를 작성합니다. 대상물 개요는 자동으로 채워집니다.'],
  ['icon'=>'🗂', 'title'=>'법정 업무수행 기록표',
   'desc'=>'작동점검·종합점검 등 법정 서식을 양식에 맞춰 작성하고 보관합니다.'],
  ['icon'=>'🧯', 'title'=>'자위소방대 편성표',
   'desc'=>'명단을 붙여넣으면 대장·부대장·활동조가 자동으로 배치됩니다. 인원이 바뀌어도 금방 다시 만듭니다.'],
  ['icon'=>'🚪', 'title'=>'피난 시뮬레이션',
   'desc'=>'건물 구조를 바탕으로 피난 동선을 그려 교육·훈련 자료로 활용합니다.'],
  ['icon'=>'🔔', 'title'=>'사용승인월별 조회',
   'desc'=>'사용승인일 기준으로 이번 달 점검 대상을 모아 봅니다. 매년 돌아오는 일정을 미리 챙길 수 있습니다.'],
   ['icon'=>'🔔', 'title'=>'외국인 소방교육자료',
   'desc'=>'외국 언어표기로 소방교육 자료를 제공합니다'],
];

/* ── 요금제 ── */
$priceMonthly = 1900;
$priceYearly  = 19000;
$yearlyCompare = $priceMonthly * 12;                 // 22,800원
$yearlySave    = $yearlyCompare - $priceYearly;      // 3,800원
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
.plan-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;align-items:stretch}
.plan{position:relative;display:flex;flex-direction:column;gap:12px}
.plan__name{font-size:13px;font-weight:700;opacity:.75}
.plan__price{display:flex;align-items:baseline;gap:5px;flex-wrap:wrap}
.plan__num{font-size:30px;font-weight:900;letter-spacing:-.02em}
.plan__unit{font-size:13px;opacity:.7}
.plan__sub{font-size:12.5px;opacity:.7;line-height:1.6;min-height:34px}
.plan__list{margin:0;padding:0;list-style:none;display:grid;gap:7px;font-size:13px}
.plan__list li{display:flex;gap:7px;align-items:flex-start;line-height:1.6}
.plan__list li::before{content:'✓';font-weight:800;opacity:.6;flex-shrink:0}
.plan__cta{display:block;text-align:center;padding:11px 16px;border-radius:10px;
  font-size:14px;font-weight:700;text-decoration:none;margin-top:auto}
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
    <p>소방안전관리자를 위한 업무 플랫폼입니다. 건물 정보를 한 번만 입력하면
       소방계획서부터 점검 기록까지 이어서 관리할 수 있습니다.</p>
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

  <!-- 요금제 -->
  <h2 class="svc-sec-t">요금 안내</h2>
  <p class="svc-sec-d">모든 기능을 제한 없이 사용합니다. 월 구독과 연 구독 중에 고르시면 됩니다.</p>

  <div class="plan-grid">
    <!-- 월 구독 -->
    <div class="card plan">
      <div class="plan__name">월 구독</div>
      <div class="plan__price">
        <span class="plan__num"><?=number_format($priceMonthly)?></span>
        <span class="plan__unit">원 / 월</span>
      </div>
      <div class="plan__sub">부담 없이 시작해 보고 싶을 때. 언제든 해지할 수 있습니다.</div>
      <ul class="plan__list">
        <li>모든 기능 사용</li>
        <li>거래처 등록 200곳까지</li>
        <li>건축물대장 자동 조회</li>
        <li>서식·문서 보관</li>
      </ul>
      <a class="plan__cta btn" href="/clients_mini.php?view=subscribe">월 구독 시작하기</a>
    </div>

    <!-- 연 구독 -->
    <div class="card plan plan--year">
      <span class="plan__badge">2개월 무료</span>
      <div class="plan__name">연 구독</div>
      <div class="plan__price">
        <span class="plan__num"><?=number_format($priceYearly)?></span>
        <span class="plan__unit">원 / 년</span>
        <span class="plan__was"><?=number_format($yearlyCompare)?>원</span>
      </div>
      <div class="plan__sub">
        월 구독으로 1년 쓰면 <?=number_format($yearlyCompare)?>원 —
        연 구독은 <b><?=number_format($yearlySave)?>원 더 저렴</b>합니다(2개월분 무료).
      </div>
      <ul class="plan__list">
        <li>월 구독의 모든 기능</li>
        <li>2개월분 무료 (연 <?=number_format($yearlySave)?>원 절약)</li>
        <li>1년 동안 요금 변동 없음</li>
        <li>결제 한 번으로 관리 간편</li>
      </ul>
      <a class="plan__cta btn" href="/clients_mini.php?view=subscribe">연 구독 시작하기</a>
    </div>
  </div>

  <div class="svc-note">
    · 표시 금액은 1인 계정 기준입니다.<br>
    · 구독 기간에는 새로 추가되는 기능도 추가 비용 없이 함께 사용하실 수 있습니다.<br>
    · 해지하셔도 입력하신 자료는 삭제되지 않으며, 다시 구독하면 이어서 사용할 수 있습니다.
  </div>

  <!-- 자주 묻는 질문 -->
  <h2 class="svc-sec-t">자주 묻는 질문</h2>
  <div class="svc-faq">
    <div class="card">
      <h4>건축물대장이 조회되지 않는 건물도 있나요?</h4>
      <p>학교·관공서 등 공공기관 건물이나 신축·미등록 건물은 조회되지 않을 수 있습니다.
         이 경우 주소와 좌표는 저장되며 나머지 정보는 직접 입력하시면 됩니다.
         조회가 안 되는 건물은 서비스 안에서 바로 문의하실 수 있습니다.</p>
    </div>
    <div class="card">
      <h4>조회된 건축물 정보는 정확한가요?</h4>
      <p>공공데이터(건축물대장) 기준으로 제공되며 실제 현황과 다를 수 있습니다.
         참고용으로 사용하시고, 중요한 내용은 대장 원본과 대조해 주세요.</p>
    </div>
    <div class="card">
      <h4>중간에 요금제를 바꿀 수 있나요?</h4>
      <p>월 구독에서 연 구독으로, 또는 그 반대로 변경하실 수 있습니다.
         변경 시점 이후 기간부터 적용됩니다.</p>
    </div>
    <div class="card">
      <h4>여러 명이 함께 쓸 수 있나요?</h4>
      <p>현재 요금은 1인 계정 기준입니다. 팀 단위 이용은 문의해 주시면 안내해 드리겠습니다.</p>
    </div>
  </div>

</main>

<?php require __DIR__ . '/_footer.php'; ?>
