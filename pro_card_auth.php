<?php
declare(strict_types=1);
session_start();
header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');header('X-Frame-Options: DENY');
if(empty($_SESSION['is_user'])||!empty($_SESSION['_imp'])){http_response_code(403);exit('카드 등록은 본인 계정에서 진행해 주세요.');}
require_once __DIR__.'/toss_billing.php';
if(!tb_ready()){http_response_code(503);exit('카드 등록 설정을 확인해 주세요. 이 창을 닫고 다시 시도해 주세요.');}
?><!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>카드 등록 · 토스페이먼츠</title>
<style>body{margin:0;background:#f5f7fb;color:#24344c;font:14px/1.7 system-ui}.loading{min-height:100vh;min-height:100dvh;display:grid;place-content:center;text-align:center;padding:24px;box-sizing:border-box}.actions{display:flex;justify-content:center;gap:10px}button{font:inherit;padding:10px 18px;border:1px solid #dae2ed;background:white;border-radius:9px;cursor:pointer}[hidden]{display:none!important}</style></head><body>
<div class="loading"><p id="status" role="status">토스 카드 등록창을 여는 중입니다…</p><div class="actions" id="actions" hidden><button id="retry" type="button">다시 시도</button><button type="button" onclick="window.close()">닫기</button></div></div>
<script src="https://js.tosspayments.com/v2/standard"></script>
<script>
const status=document.getElementById('status'),actions=document.getElementById('actions');let busy=false;
async function startCardAuth(){
 if(busy)return;busy=true;actions.hidden=true;status.textContent='토스 카드 등록창을 여는 중입니다…';
 try{
  if(typeof TossPayments!=='function')throw new Error('카드 등록창을 불러오지 못했습니다. 다시 시도해 주세요.');
  const payment=TossPayments(<?=json_encode(tb_client_key(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>).payment({customerKey:<?=json_encode(tb_customer_key(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>});
  // Navigate this authentication popup itself; never nest a desktop iframe in it.
  await payment.requestBillingAuth({method:'CARD',windowTarget:'self',successUrl:location.origin+'/toss_billing_return.php?pro_popup=1',failUrl:location.origin+'/toss_billing_return.php?pro_popup=1'});
 }catch(e){
  status.textContent=['USER_CANCEL','PAY_PROCESS_CANCELED'].includes(e.code)?'카드 등록을 취소했습니다.':(e.message||'카드 등록창을 열지 못했습니다.');
  actions.hidden=false;busy=false;
 }
}
document.getElementById('retry').onclick=startCardAuth;
startCardAuth();
</script></body></html>
