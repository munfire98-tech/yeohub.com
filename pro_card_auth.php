<?php
declare(strict_types=1);
/* Shared login retention: partner/admin 30d, building 90d. */
require_once __DIR__.'/login_persistence.php';

alp_session_start();
header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');header('X-Frame-Options: DENY');
if(empty($_SESSION['is_user'])||!empty($_SESSION['_imp'])){http_response_code(403);exit('카드 등록은 본인 계정에서 진행해 주세요.');}
require_once __DIR__.'/toss_billing.php';
$cardReady=tb_ready();
$inviteFlow=($_GET['invite_flow']??'')==='1';
$mobileFlow=($_GET['mobile_flow']??'')==='1';
$proPopup=($_GET['pro_popup']??'')==='1';
$authWindow=($_GET['auth_window']??'')==='1';
$returnQuery=$inviteFlow?'invite_flow=1':($mobileFlow?'mobile_flow=1'.($proPopup?'&pro_popup=1':''):'pro_popup=1');
if($authWindow)$returnQuery.='&auth_window=1';
$backUrl=$inviteFlow?'/subscribe_page.php?invite_flow=1':($proPopup?'/FireDesk.php?pro_popup=1':'/subscribe_page.php');
?><!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>카드 등록 · 토스페이먼츠</title>
<style>*{box-sizing:border-box}body{margin:0;background:#f5f7fb;color:#24344c;font:14px/1.7 system-ui}.card-auth-header{position:sticky;top:0;display:flex;align-items:center;justify-content:space-between;padding:calc(12px + env(safe-area-inset-top)) 18px 12px;border-bottom:1px solid #e0e7ef;background:#fff}.card-auth-header a{display:inline-flex;gap:6px;align-items:center;min-height:44px;color:#315d94;font-weight:650;text-decoration:none}.card-auth-header span{font-size:12px;color:#7d8da0}.loading{min-height:70dvh;display:flex;align-items:center;justify-content:center;padding:24px}.auth-card{width:100%;max-width:420px;padding:28px 24px;border:1px solid #dde5ef;border-radius:18px;background:white;text-align:center}.auth-card small{font-size:11px;color:#617d9c;letter-spacing:.1em}.auth-card h1{font-size:24px;letter-spacing:-.04em;margin:8px 0 12px}.auth-card p{color:#697c93;font-size:13px}.actions{display:flex;flex-direction:column;gap:10px;margin-top:24px}button,.auth-back{display:block;min-height:46px;font:600 14px/1.6 system-ui;padding:11px 18px;border:1px solid #dae2ed;background:white;border-radius:9px;cursor:pointer;text-decoration:none;color:#426080}button{background:#2d64bd;color:#fff;border-color:#2d64bd}button:disabled{opacity:.6;cursor:wait}[hidden]{display:none!important}</style></head><body>
<header class="card-auth-header"><a href="<?=htmlspecialchars($backUrl,ENT_QUOTES,'UTF-8')?>" data-auth-back><span aria-hidden="true">←</span> PRO 이용안내</a><span>카드 등록</span></header>
<main class="loading"><section class="auth-card"><small>SECURE CARD REGISTRATION</small><h1>카드를 안전하게 등록하세요</h1><p id="status" role="status"><?=$cardReady?'토스에서 카드 인증을 진행합니다. 카드 등록만으로 결제되지는 않습니다.':'카드 등록을 준비하지 못했습니다. 이용안내로 돌아가 잠시 후 다시 시도해 주세요.'?></p><p>카드 입력 화면은 별도 창에서 열립니다.<br>진행하지 않으려면 이 창을 닫아 기존 이용안내로 돌아가세요.</p><div class="actions" id="actions"><button id="retry" type="button" <?=$cardReady?'':'disabled'?>>토스 카드 인증 계속하기</button><a class="auth-back" href="<?=htmlspecialchars($backUrl,ENT_QUOTES,'UTF-8')?>" id="backToSubscription">PRO 이용안내로 돌아가기</a></div></section></main>
<script src="https://js.tosspayments.com/v2/standard"></script>
<script>
const status=document.getElementById('status'),actions=document.getElementById('actions');let busy=false;
async function startCardAuth(){
 if(busy)return;busy=true;document.getElementById('retry').disabled=true;status.textContent='토스 카드 등록창을 여는 중입니다…';
 try{
  if(typeof TossPayments!=='function')throw new Error('카드 등록창을 불러오지 못했습니다. 다시 시도해 주세요.');
  const payment=TossPayments(<?=json_encode(tb_client_key(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>).payment({customerKey:<?=json_encode(tb_customer_key(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>});
  // Navigate this authentication popup itself; never nest a desktop iframe in it.
  await payment.requestBillingAuth({method:'CARD',windowTarget:'self',successUrl:location.origin+'/toss_billing_return.php?'+<?=json_encode($returnQuery)?>,failUrl:location.origin+'/toss_billing_return.php?'+<?=json_encode($returnQuery)?>});
 }catch(e){
  status.textContent=['USER_CANCEL','PAY_PROCESS_CANCELED'].includes(e.code)?'카드 등록을 취소했습니다.':(e.message||'카드 등록창을 열지 못했습니다.');
  actions.hidden=false;busy=false;document.getElementById('retry').disabled=false;
 }
}
function returnToSubscription(event){
 if(<?=json_encode($authWindow||(!$inviteFlow&&!$mobileFlow))?> && window.opener && !window.opener.closed){event.preventDefault();window.close();return;}
 location.replace(<?=json_encode($backUrl)?>);event.preventDefault();
}
document.querySelectorAll('[data-auth-back],#backToSubscription').forEach(a=>a.addEventListener('click',returnToSubscription));
document.getElementById('retry').onclick=startCardAuth;
window.addEventListener('pageshow',function(event){
 if(event.persisted){busy=false;actions.hidden=false;document.getElementById('retry').disabled=!<?=json_encode($cardReady)?>;status.textContent='카드 인증을 마치지 않았습니다. 계속 진행하거나 이용안내로 돌아가세요.';}
});
// Mobile must have a stable return page, rather than auto-opening authentication again.
if(!<?=json_encode($mobileFlow||$inviteFlow)?> && <?=json_encode($cardReady)?>)startCardAuth();
</script></body></html>
