<?php
declare(strict_types=1);
$policyConfig=require __DIR__.'/site_policy_config.php';
$policyTitle='사업자정보';$policySubtitle='서비스 운영자와 고객지원 정보를 안내합니다.';
$policyNav=[['business','운영자 정보'],['contact','고객지원']];
$escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
ob_start(); ?>
<section id="business"><h2><span>01</span>운영자 정보</h2><dl class="business-grid">
<?php foreach(['business_name'=>'상호','representative'=>'대표자','business_number'=>'사업자등록번호','sales_number'=>'통신판매업 신고번호','address'=>'사업장 주소','phone'=>'고객센터 전화','email'=>'고객센터 이메일'] as $key=>$label): ?>
<div><dt><?=$escape($label)?></dt><dd><?=$escape($policyConfig[$key]?:'확인 후 등록 예정')?></dd></div>
<?php endforeach; ?></dl><aside class="review">첨부된 푸터 정보를 기준으로 작성했습니다. 상호의 등록명 일치 여부와 사업장 상세 주소를 확인해야 합니다.</aside></section>
<section id="contact"><h2><span>02</span>고객지원</h2><p>계정, 구독 결제, 매니저 연결 및 출금 신청 관련 문의를 접수합니다. 문의에는 계정 아이디와 문의 내용을 적어 주세요. 비밀번호, API 키, 카드번호 전체는 보내지 마세요.</p><a class="contact-button" href="mailto:<?=$escape($policyConfig['email_link'])?>">이메일 문의하기 →</a><aside class="review">이 주소의 메일 발송 인증과 메일 수신 기능은 별개입니다. 고객 문의를 받을 수 있는 수신함 또는 전달 설정을 확인한 후 고객센터 주소로 확정하세요.</aside></section>
<?php $policyBody=ob_get_clean();require __DIR__.'/site_policy_layout.php';
