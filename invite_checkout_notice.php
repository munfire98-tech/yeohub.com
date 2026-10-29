<?php
$iqMe=$promoMembers[$UID]??[];
if(($iqMe['joined_via']??'')==='manager_qr'):
$iqNeedsEmail=empty($iqMe['email_ok'])||!empty($iqMe['email_verification_required']);
?>
<section class="checkout-welcome" aria-label="가입 및 구독 진행">
<span class="checkout-kicker"><?=$status==='active'?'PRO 이용 준비 완료':'가입 · 건물 연결 완료'?></span>
<h2><?=$status==='active'?'구독이 완료되었습니다':'이제 구독을 시작하세요'?></h2>
<?php if($status==='active'): ?>
<p><?=$iqNeedsEmail?'이메일 인증만 마치면 계정 복구도 안심할 수 있어요.':'소방안전관리 업무를 시작해 보세요.'?></p>
<div class="checkout-actions">
<?php if($iqNeedsEmail): ?><button type="button" class="checkout-primary" data-checkout-email>이메일 인증하기</button><a class="checkout-secondary" href="/FireDesk.php" target="_top">건물관리로 이동</a>
<?php else: ?><a class="checkout-primary" href="/FireDesk.php" target="_top">건물관리 시작하기 →</a><?php endif; ?>
</div>
<?php else: ?>
<p>카드를 등록한 뒤, 요금을 확인하고 결제해 주세요.</p>
<dl class="checkout-connection"><div><dt>우리 건물</dt><dd><?=h($iqMe['nickname']??'')?></dd></div><div><dt>연결 업체</dt><dd><?=h($promoManagerName?:'연결 상태 확인 필요')?></dd></div></dl>
<ol class="checkout-steps"><li><span>1</span>카드 등록</li><li><span>2</span>구독 결제</li><li><span>3</span>이메일 인증</li></ol>
<?php endif; ?>
</section>
<?php if($status==='active'&&$iqNeedsEmail): ?>
<dialog class="checkout-email-dialog" id="checkout-email-dialog" aria-labelledby="checkout-email-title"><header><strong id="checkout-email-title">이메일 인증</strong><button type="button" autofocus>닫기 ×</button></header><iframe title="이메일 인증" data-src="/invite_email.php?embed=1"></iframe></dialog>
<script>
(()=>{
 const dialog=document.getElementById('checkout-email-dialog'),frame=dialog.querySelector('iframe');let previous,overflow;
 document.querySelector('[data-checkout-email]').onclick=()=>{previous=document.activeElement;overflow=document.body.style.overflow;frame.src=frame.dataset.src;dialog.showModal();document.body.style.overflow='hidden';};
 dialog.querySelector('button').onclick=()=>dialog.close();
 dialog.addEventListener('close',()=>{document.body.style.overflow=overflow;previous?.focus();});
 window.addEventListener('message',e=>{if(e.origin===location.origin&&e.source===frame.contentWindow&&e.data?.type==='email-verification-close'){dialog.close();location.replace('/subscribe_page.php'+location.search);}});
})();
</script>
<?php endif; endif; ?>
