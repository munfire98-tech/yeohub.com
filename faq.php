<?php
$PAGE_TITLE = '자주 묻는 질문 | 소방계획서.com';
$ACTIVE = 'faq';
require __DIR__ . '/_header.php';
$faqEscape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$faqs=json_decode(<<<'FAQ_JSON'
[
  [
    "시작하기",
    "어떤 서비스인가요?",
    "건물관리자와 매니저가 소방안전 업무 기록을 함께 작성하고 관리하는 서비스입니다. 건물정보를 바탕으로 소방계획서, 편성표, 교육·훈련 및 매월 업무 기록을 작성하고 필요한 서식을 출력할 수 있습니다."
  ],
  [
    "시작하기",
    "처음 이용할 때 무엇부터 해야 하나요?",
    "건물관리자로 가입한 뒤 건물관리 화면에서 기본정보와 소방시설 현황을 등록해 주세요. 이후 자위소방대 편성, 매월 기록 등 화면에 안내된 업무를 순서대로 작성하면 됩니다."
  ],
  [
    "시작하기",
    "현장 점검이나 안전관리 업무를 대신 수행해 주나요?",
    "사이트는 기록 작성과 관리를 지원합니다. 현장 점검이나 조치 자체를 수행하거나 건물의 안전 상태를 인증하지 않습니다. 작성된 내용은 실제 업무와 일치하는지 확인한 뒤 사용해 주세요."
  ],
  [
    "업무 기록",
    "소방시설 현황은 어떻게 입력하나요?",
    "건물관리 화면의 2번 소방시설 현황을 누르면 팝업이 열립니다. 설치된 시설만 체크하고 저장하세요. 방화문과 방화셔터도 선택할 수 있습니다. 저장하면 체크하지 않은 항목은 설치 없음으로 기록됩니다."
  ],
  [
    "업무 기록",
    "시설을 체크하면 정상 작동하는 것으로 기록되나요?",
    "아닙니다. 소방시설 현황은 설치 여부만 기록합니다. 설치되어 있다는 사실과 정상 작동 여부는 다르며, 실제 확인한 결과는 해당 업무 기록에 작성해 주세요."
  ],
  [
    "업무 기록",
    "매월 기록의 기본 문구는 어떻게 만들어지나요?",
    "저장한 소방시설 현황을 바탕으로 주요설비 이름을 묶어 기본 문구를 준비합니다. 예를 들어 옥내소화전·스프링클러 상태 확인처럼 표시됩니다. 기타 설치설비는 묶어서 안내하며, 기본 문구 자체가 업무를 수행했다는 증명은 아닙니다."
  ],
  [
    "업무 기록",
    "시설현황을 바꾸면 기존 기록도 바뀌나요?",
    "기존에 작성한 월별 기록은 유지됩니다. 앞으로 사용할 자동 생성 기본 문구는 시설현황에 맞춰 갱신됩니다. 직접 수정한 기본 문구는 보존되며, 다시 가져오려면 시설현황 문구 넣기를 이용해 주세요."
  ],
  [
    "업무 기록",
    "소방계획서를 자동으로 완성해 주나요?",
    "등록된 건물정보와 문답을 활용해 작성을 돕습니다. 실제 건물과 조직에 맞는 정보인지 확인하고 필요한 내용을 작성해야 하며, 생성된 문서의 내용과 적용 서식은 사용 전에 검토해 주세요."
  ],
  [
    "업무 기록",
    "작성한 서식을 인쇄하거나 PDF로 저장할 수 있나요?",
    "인쇄 기능이 제공되는 업무 화면에서 인쇄 또는 PDF 버튼을 이용할 수 있습니다. 브라우저 인쇄창에서 PDF 저장을 선택할 수도 있습니다. 전체 인쇄 등 제공 범위는 현재 이용 중인 상품 안내를 확인해 주세요."
  ],
  [
    "매니저 연결",
    "담당 매니저는 어떻게 연결하나요?",
    "건물관리 화면의 담당 매니저 카드에서 전달받은 매니저 코드를 입력하고 정보 공유에 동의한 뒤 연결을 요청하세요. 매니저가 수락하면 연결이 완료됩니다."
  ],
  [
    "매니저 연결",
    "담당 매니저가 없으면 어떻게 하나요?",
    "담당 매니저 카드 아래의 로컬매니저 연결을 눌러 안내를 확인하고 요청할 수 있습니다. 지정된 로컬매니저가 수락해야 연결되며, 즉시 연결되거나 지역별 매니저가 자동 배정되는 방식은 아닙니다."
  ],
  [
    "매니저 연결",
    "연결된 매니저는 무엇을 할 수 있나요?",
    "담당 유저의 건물정보와 업무 진행 현황을 확인하고, 제공된 기능 범위에서 유저와 같은 건물관리 화면으로 기록을 작성·수정할 수 있습니다. 연결할 매니저와 공유되는 정보 범위를 확인해 주세요."
  ],
  [
    "매니저 연결",
    "매니저 연결을 해제하거나 요청을 취소할 수 있나요?",
    "담당 매니저 카드에서 요청 취소 또는 연결 해제를 할 수 있습니다. 연결 해제 후 매니저는 해당 건물 화면에 접근할 수 없으며, 해제·취소 사실은 매니저의 연결 알림에 표시됩니다."
  ],
  [
    "매니저 연결",
    "매니저는 연결 요청을 어디에서 확인하나요?",
    "매니저 화면의 워크스페이스에서 연결 알림을 확인하세요. 수락 대기 요청이 위쪽에 표시되며 수락 또는 거절할 수 있습니다. 연결 해제·요청 취소 알림은 확인 완료를 누르면 목록에서 사라집니다."
  ],
  [
    "구독",
    "PRO 구독료와 이용기간은 어떻게 되나요?",
    "현재 PRO 상품은 1년 단위 59,000원을 기준으로 운영합니다. 결제 전 구독 페이지에서 최종 금액, 제공 기능과 이용기간을 확인해 주세요."
  ],
  [
    "매니저 연결",
    "매니저 리워드는 무엇인가요?",
    "담당 유저의 업무 기록이 사전에 안내된 작성·관리 기준을 충족하고 완료 요건이 확인되었을 때 매니저에게 지급하는 보상입니다. 기록 작성과 관리를 지원한 활동을 보상하는 제도이며, 현장 점검 완료나 건물의 안전을 인증하는 의미는 아닙니다. 기록 완료 기준에 따른 지급 정책은 적용 시점과 세부 기준을 별도로 안내한 후 시행합니다."
  ],
  [
    "구독",
    "구독 취소나 환불은 어디에 요청하나요?",
    "고객센터에 계정 아이디와 결제일·주문번호 등 결제를 확인할 수 있는 정보를 알려주세요. 적용되는 약관과 관계 법령, 실제 제공·이용 내역을 확인해 안내합니다. 비밀번호나 카드번호 전체는 보내지 마세요."
  ],
  [
    "계정·문의",
    "아이디나 비밀번호를 잊어버렸어요.",
    "로그인 화면의 아이디 찾기 또는 비밀번호 재설정을 이용하세요. 가입한 이메일로 아이디 안내나 재설정 링크를 요청할 수 있습니다. 비밀번호 재설정 링크는 30분 동안 유효합니다."
  ],
  [
    "계정·문의",
    "계정 안내 메일이 오지 않아요.",
    "가입할 때 등록한 이메일이 맞는지 확인하고 스팸메일함도 확인해 주세요. 잠시 후에도 도착하지 않으면 고객센터로 문의해 주세요. 계정 보호를 위해 안내 화면에서 이메일의 가입 여부를 구분해 알려드리지는 않습니다."
  ],
  [
    "계정·문의",
    "회원 탈퇴와 개인정보 관련 요청은 어떻게 하나요?",
    "회원 탈퇴는 계정 설정에서 진행할 수 있습니다. 필요한 기록은 먼저 확인·출력해 주세요. 개인정보의 열람·정정·삭제 등 요청은 고객센터로 접수할 수 있으며, 보관 및 처리 기준은 개인정보처리방침을 확인해 주세요."
  ]
]
FAQ_JSON
,true);
$faqGroups=array_values(array_unique(array_column($faqs,0)));
?>
<style>
.faq-page{max-width:1000px;margin:0 auto;padding:38px 24px 20px}.faq-head{margin-bottom:26px}.faq-kicker{font-size:11px;color:var(--accent,#1687aa);letter-spacing:.1em;font-weight:750}.faq-head h1{font-size:32px;letter-spacing:-1px;margin:8px 0 10px;line-height:1.4}.faq-head p{font-size:14px;color:#7a879a;margin:0;line-height:1.8}.faq-search{display:block;width:100%;max-width:540px;border:1px solid #dce5ef;border-radius:12px;padding:14px 16px;margin:22px 0 18px;background:#fff;color:#263b54;font:inherit;font-size:14px;box-sizing:border-box}.faq-filters{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:28px}.faq-filter{border:1px solid #e1e8f1;padding:8px 14px;border-radius:30px;background:white;color:#728198;font:inherit;font-size:12px;cursor:pointer}.faq-filter[aria-pressed="true"]{background:#263e5b;border-color:#263e5b;color:#fff}.faq-page :focus-visible{outline:3px solid #97c1db;outline-offset:3px}.faq-group{margin-bottom:26px}.faq-group h2{font-size:14px;font-weight:750;color:#61748d;margin:0 0 10px}.faq-item{border:1px solid #e4eaf2;border-radius:12px;background:#fff;margin-bottom:8px;overflow:hidden}.faq-item summary{display:flex;align-items:center;gap:12px;list-style:none;cursor:pointer;padding:17px 19px;font-size:14px;font-weight:650;color:#2e415b;line-height:1.65}.faq-item summary::-webkit-details-marker{display:none}.faq-item summary::marker{content:''}.faq-item summary::before{content:'Q';color:#6987a7;font-size:13px;flex-shrink:0}.faq-item summary::after{content:'+';margin-left:auto;font-size:20px;font-weight:400;color:#8a9bb0}.faq-item[open]{border-color:#b9cee0}.faq-item[open] summary{background:#f7fafd}.faq-item[open] summary::after{content:'−'}.faq-answer{padding:0 19px 19px 42px;margin:0;color:#6c7e95;font-size:13px;line-height:1.95;word-break:keep-all;overflow-wrap:anywhere}.faq-item[open] .faq-answer{padding-top:14px}.faq-contact{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:22px 24px;margin-top:32px;border-radius:14px;background:#edf3f9}.faq-contact strong{font-size:14px;color:#314b69}.faq-contact p{font-size:12px;color:#8091a7;line-height:1.7;margin:6px 0 0}.faq-contact a{white-space:nowrap;background:#fff;border:1px solid #d4e0ed;border-radius:9px;padding:10px 14px;color:#3e6087;text-decoration:none;font-size:13px;font-weight:650}.faq-links{display:flex;gap:16px;flex-wrap:wrap;margin:18px 0;color:#8291a5;font-size:12px}.faq-links a{color:inherit;text-decoration:none}.faq-empty{text-align:center;padding:35px 10px;color:#7e8c9f}.faq-page [hidden]{display:none!important}@media(max-width:600px){.faq-page{padding:28px 18px 12px}.faq-head h1{font-size:27px}.faq-item summary{padding:15px;font-size:13px}.faq-answer{padding-left:36px;padding-right:15px}.faq-contact{align-items:flex-start;flex-direction:column;padding:20px}.faq-filters{gap:6px}.faq-filter{padding:7px 11px}}@media print{.faq-controls,.faq-contact{display:none}.faq-item .faq-answer{display:block!important}.faq-item{break-inside:avoid}}
</style>
<main class="faq-page">
<header class="faq-head"><span class="faq-kicker">HELP CENTER</span><h1>자주 묻는 질문</h1><p>기록 작성부터 매니저 연결까지,<br>소방계획서.com 이용에 필요한 내용을 확인하세요.</p></header>
<div class="faq-controls" hidden><input class="faq-search" type="search" id="faq-search" placeholder="궁금한 내용을 검색하세요. 예: 매니저, 시설, 구독" aria-label="자주 묻는 질문 검색"><div class="faq-filters" role="group" aria-label="질문 분류"><button class="faq-filter" type="button" data-category="" aria-pressed="true">전체</button><?php foreach($faqGroups as $group): ?><button class="faq-filter" type="button" data-category="<?=$faqEscape($group)?>" aria-pressed="false"><?=$faqEscape($group)?></button><?php endforeach; ?></div></div>
<?php foreach($faqGroups as $group): ?><section class="faq-group"><h2><?=$faqEscape($group)?></h2><?php foreach($faqs as [$category,$question,$answer]):if($category!==$group)continue; ?><details class="faq-item" data-category="<?=$faqEscape($category)?>"><summary><?=$faqEscape($question)?></summary><p class="faq-answer"><?=$faqEscape($answer)?></p></details><?php endforeach; ?></section><?php endforeach; ?>
<p id="faq-empty" class="faq-empty" role="status" hidden>검색 결과가 없습니다. 다른 단어로 검색하거나 고객센터에 문의해 주세요.</p>
<aside class="faq-contact"><div><strong>찾으시는 답변이 없나요?</strong><p>계정 아이디와 문의 내용을 준비해 주세요.<br>비밀번호나 API 키는 알려주지 마세요.</p></div><a href="tel:01057790918">고객센터 010-5779-0918</a></aside>
<nav class="faq-links" aria-label="서비스 정책"><a href="/privacy.php">개인정보처리방침</a><a href="/terms.php">이용약관</a><a href="/business_info.php">사업자정보</a></nav>
</main>
<script>
(()=>{const root=document.querySelector('.faq-page');if(!root)return;root.querySelector('.faq-controls').hidden=false;const search=root.querySelector('#faq-search'),items=[...root.querySelectorAll('.faq-item')],buttons=[...root.querySelectorAll('.faq-filter')];let selected='';function filter(){const q=search.value.trim().toLocaleLowerCase();let count=0;items.forEach(item=>{const show=(!selected||item.dataset.category===selected)&&item.textContent.toLocaleLowerCase().includes(q);item.hidden=!show;if(show)count++;});root.querySelectorAll('.faq-group').forEach(group=>{group.hidden=![...group.querySelectorAll('.faq-item')].some(item=>!item.hidden);});root.querySelector('#faq-empty').hidden=count>0;}search.addEventListener('input',filter);buttons.forEach(button=>button.addEventListener('click',()=>{selected=button.dataset.category;buttons.forEach(other=>other.setAttribute('aria-pressed',String(other===button)));filter();}));})();
</script>
<?php require __DIR__ . '/_footer.php'; ?>
