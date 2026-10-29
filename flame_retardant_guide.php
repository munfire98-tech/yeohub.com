<?php
// Included only after FireDesk.php resolves the authorized building context.
if (!isset($bi) || !is_array($bi) || !isset($buildingDongs)) return;
require_once __DIR__.'/flame_retardant_plan_common.php';
$frgSeed = ['name'=>(string)($bi['name']??''),'dongs'=>frgp_dongs($bi),'declaredCount'=>(int)($siteDongCount??1),
 'year'=>(int)($bookYear??date('Y')),'api'=>$url('/flame_retardant_api.php'),'canSave'=>!empty($isPro)||is_admin()];
?>
<link rel="stylesheet" href="/flame_retardant_guide.css?v=20261015-plan6-2">
<dialog id="flame-retardant-guide" aria-labelledby="frg-title">
 <header class="frg-head"><div><span class="frg-eyebrow">건물 안전 가이드</span><h2 id="frg-title">방염 확인</h2></div><button type="button" data-frg-close aria-label="방염 안내 닫기">닫기 <span aria-hidden="true">×</span></button></header>
 <nav class="frg-tabs" aria-label="방염 안내 선택"><button type="button" data-frg-tab="check" aria-pressed="true">우리 건물 대상 확인</button><button type="button" data-frg-tab="items" aria-pressed="false">방염 물품 알아보기</button></nav>
 <div class="frg-scroll">
  <section data-frg-panel="check">
   <div class="frg-building"><div><strong data-frg-building-name></strong><p data-frg-saved></p></div><label data-frg-dong-label hidden>확인할 동<select data-frg-dong></select></label></div>
   <p class="frg-dong-note" data-frg-dong-note hidden></p>
   <p class="frg-plan-year" data-frg-plan-year></p>
   <div class="frg-progress" aria-label="확인 순서"><span data-frg-dot="0">1 용도</span><i></i><span data-frg-dot="1">2 추가 조건</span><i></i><span data-frg-dot="2">3 결과</span></div>
   <section class="frg-step" data-frg-step="0">
    <h3 tabindex="-1">실제로 사용하는 용도를 골라 주세요</h3><p>해당하는 용도를 모두 선택하세요. 복합 건물은 입점 시설도 확인해 주세요.</p>
    <div class="frg-use-grid" data-frg-uses role="group" aria-label="실제 사용 용도"></div>
    <p class="frg-note">건물 명칭이나 ‘근린생활시설’이라는 대분류만으로는 적용 여부를 정할 수 없습니다.</p>
   </section>
   <section class="frg-step" data-frg-step="1" hidden>
    <h3 tabindex="-1">영업 형태와 층수를 확인해 주세요</h3><p>선택한 동을 기준으로 답해 주세요. 모르는 항목은 ‘모르겠어요’를 선택해도 됩니다.</p>
    <fieldset><legend>다중이용업소에 해당하나요?</legend><p>영업 종류·면적·층 등 법정 요건에 따라 구분됩니다. 안전시설등 완비증명서나 연결 업체를 통해 확인할 수 있습니다.</p><div class="frg-choices" data-frg-question="multi"><button type="button" data-value="yes" aria-pressed="false">해당해요</button><button type="button" data-value="no" aria-pressed="false">해당하지 않아요</button><button type="button" data-value="unknown" aria-pressed="false">모르겠어요</button></div></fieldset>
    <fieldset><legend>이 건물의 층수는 몇 층인가요?</legend><p data-frg-floor-hint>영업장이 있는 층이 아니라 건물의 층수입니다.</p><div class="frg-choices" data-frg-question="height"><button type="button" data-value="low" aria-pressed="false">10층 이하</button><button type="button" data-value="high" aria-pressed="false">11층 이상</button><button type="button" data-value="unknown" aria-pressed="false">모르겠어요</button></div></fieldset>
    <fieldset data-frg-apartment hidden><legend>아파트등에 해당하나요?</legend><p>11층 이상 기준의 제외 여부를 확인합니다. 아파트 안의 다른 용도 시설·다중이용업소는 별도로 확인해야 합니다.</p><div class="frg-choices" data-frg-question="apartment"><button type="button" data-value="yes" aria-pressed="false">아파트등이에요</button><button type="button" data-value="no" aria-pressed="false">아파트등이 아니에요</button><button type="button" data-value="unknown" aria-pressed="false">모르겠어요</button></div></fieldset>
   </section>
   <section class="frg-step" data-frg-step="2" hidden>
    <div class="frg-result" data-frg-result aria-live="polite"><span class="frg-eyebrow">선택한 조건으로 확인한 결과</span><h3 tabindex="-1" data-frg-result-title></h3><p data-frg-result-copy></p><ul data-frg-reasons></ul></div>
    <div class="frg-save-box"><strong data-frg-save-title></strong><p>선택한 동의 대상 여부와 확인 근거를 저장합니다. 기존 방염물품·필증·유지관리 내용은 유지됩니다.</p><p data-frg-save-status role="status" aria-live="polite"></p></div>
    <div class="frg-result-next"><h4>어떤 물품을 확인하면 되나요?</h4><p>커튼·블라인드, 카펫·벽지, 천장·벽 부착물 등은 설치 목적과 재질에 따라 확인합니다.</p><button type="button" data-frg-items>방염 대상물품 보기 <span aria-hidden="true">→</span></button></div>
    <p class="frg-note">실제 용도·설치 범위는 현장 자료로 확인해 주세요. 저장되는 것은 대상 여부를 확인한 내용이며, 실제 방염물품 설치·필증 확인이나 법적 적합 판정을 뜻하지 않습니다.</p>
   </section>
  </section>
  <section data-frg-panel="items" hidden>
   <div class="frg-items-intro"><span class="frg-eyebrow">방염 대상물품</span><h3 tabindex="-1">불이 쉽게 번지지 않도록 확인해요</h3><p>방염 적용 대상시설에서 실내장식 등의 목적으로 설치하는 물품을 확인합니다. 물품을 누르면 조건과 예외를 볼 수 있어요.</p></div>
   <div class="frg-items">
    <details><summary><span>01</span><div><b>커튼·블라인드</b><small>창문에 설치하는 커튼류</small></div></summary><p>창문에 설치하는 커튼류에는 블라인드가 포함됩니다. 제조·가공 과정의 방염처리와 방염성능검사 표시를 확인하세요.</p></details>
    <details><summary><span>02</span><div><b>카펫·벽지</b><small>벽지는 재질과 두께도 확인</small></div></summary><p>카펫과 벽지류가 해당합니다. 다만 두께가 2mm 미만인 종이벽지는 제외됩니다. 종이가 아닌 벽지에 이 예외를 그대로 적용하지 마세요.</p></details>
    <details><summary><span>03</span><div><b>전시·무대용 합판·목재·섬유판</b><small>전시 또는 무대에 사용하는 물품</small></div></summary><p>전시용·무대용 합판, 목재, 섬유판이 해당합니다. 합판·목재류는 불가피하게 설치 현장에서 방염처리하는 경우도 포함됩니다.</p></details>
    <details><summary><span>04</span><div><b>암막·무대막·일부 스크린</b><small>영화관·가상체험 체육시설업의 스크린 포함</small></div></summary><p>암막과 무대막, 영화상영관 및 법령상 가상체험 체육시설업에 설치하는 스크린이 포함됩니다. 모든 스크린을 동일하게 분류하지 않고 설치 용도를 확인합니다.</p></details>
    <details><summary><span>05</span><div><b>소파·의자</b><small>특정 업종과 재질에 해당하는 경우</small></div></summary><p>섬유류·합성수지류 등을 원료로 한 소파·의자 중 단란주점·유흥주점·노래연습장 영업장에 설치하는 물품이 해당합니다. 모든 사무용 의자가 의무 대상인 것은 아닙니다.</p></details>
    <details><summary><span>06</span><div><b>천장·벽 부착 장식물</b><small>종이·합성수지·섬유류, 합판·목재 등</small></div></summary><p>천장이나 벽에 부착·설치하는 두께 2mm 이상 종이류, 합성수지·섬유류를 주원료로 한 물품, 합판·목재 등을 확인합니다.</p><p>이 범주에서는 가구류, 너비 10cm 이하의 반자돌림대 등과 내부 마감재료는 제외됩니다. 내부 마감재는 별도의 불연·준불연 등 기준을 확인해야 합니다.</p></details>
    <details><summary><span>07</span><div><b>간이 칸막이·흡음재·방음재</b><small>천장·벽에 부착·설치하는 물품의 범위</small></div></summary><p>공간 구획용 간이 칸막이(접이식 등 이동 가능한 벽체나 천장·반자까지 구획하지 않는 벽체), 흡음재·방음재와 관련 커튼이 포함됩니다. 가구류·내부 마감재료 등의 제외 범위도 함께 확인하세요.</p></details>
   </div>
   <div class="frg-advice"><b>‘의무 대상’과 ‘사용 권장’은 달라요</b><p>일부 시설의 침구류·소파·의자, 천장·벽에 부착하는 가구류 등은 제31조제3항에 따라 방염제품 사용을 권장할 수 있습니다. 앞의 의무 대상 조건과 구분해서 확인하세요.</p></div>
   <div class="frg-checklist"><h4>설치·교체 전에 확인하세요</h4><p>① 대상 용도·물품 확인 → ② 방염성능검사 표시·서류 확인 → ③ 설치 장소와 증빙 보관</p><p>현장 방염처리가 필요한 경우 검사 절차와 적용 범위를 시공업체·연결 업체 또는 관할 소방서에 확인해 주세요.</p></div>
  </section>
  <footer class="frg-law">기준 확인: 2026.10.15 · 「소방시설 설치 및 관리에 관한 법률」 제20조·제21조 및 시행령 <a href="https://law.go.kr/LSW/lsLinkCommonInfo.do?chrClsCd=010202&amp;lsJoLnkSeq=1032685411" target="_blank" rel="noopener noreferrer">제30조</a> · <a href="https://www.law.go.kr/LSW/lsSideInfoP.do?docCls=jo&amp;joBrNo=00&amp;joNo=0031&amp;lsiSeq=290451&amp;urlMode=lsScJoRltInfoR" target="_blank" rel="noopener noreferrer">제31조</a></footer>
 </div>
 <footer class="frg-actions"><button type="button" data-frg-back>이전</button><span data-frg-action-hint role="status"></span><button type="button" class="frg-primary" data-frg-next>다음</button><button type="button" class="frg-primary" data-frg-save hidden>6번에 저장</button></footer>
</dialog>
<script type="application/json" id="frg-data"><?=json_encode($frgSeed,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?></script>
<script src="/flame_retardant_rules.js?v=20261015-plan6-2" defer></script>
<script src="/flame_retardant_guide.js?v=20261015-partner-1-connected-1-inquiry-1" defer></script>
