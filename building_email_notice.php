<?php
if(is_admin()||defined('MANAGER_VIEW_UID')||defined('MANAGER_FULL_DASHBOARD')||!empty($_SESSION['_imp'])||!empty($_SESSION['_mge_actor']))return;
require_once __DIR__.'/manager_common.php';
$emailNoticeUid=mg_uid();
if($emailNoticeUid===''||$emailNoticeUid!==$viewUid)return;
$emailNoticeMember=mg_members()[$emailNoticeUid]??[];
if(!mg_active($emailNoticeMember,'building')||(!empty($emailNoticeMember['email_ok'])&&empty($emailNoticeMember['email_verification_required'])))return;
?>
<style>
#building-email-notice{position:fixed;inset:50% auto auto 50%;transform:translate(-50%,-50%);margin:0;padding:0;width:min(460px,calc(100vw - 24px));height:min(700px,calc(100vh - 24px));height:min(700px,calc(100dvh - 24px));max-width:none;max-height:none;box-sizing:border-box;overflow:hidden;border:1px solid #e0e7ef;border-radius:22px;background:white;box-shadow:0 20px 70px #152b4933}
#building-email-notice[open]{display:flex;flex-direction:column}#building-email-notice::backdrop{background:#142b4c80}#building-email-notice header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:17px 22px;flex-shrink:0;font:600 14px system-ui;color:#40536b}#building-email-notice button{border:1px solid #e1e7ef;border-radius:9px;background:#f6f8fb;padding:8px 12px;color:#52657d;cursor:pointer;font:14px system-ui}#building-email-notice iframe{display:block;flex:1;width:100%;min-height:0;border:0;background:white}
</style>
<dialog id="building-email-notice" aria-labelledby="building-email-notice-title">
<header><span id="building-email-notice-title">이메일 인증</span><button type="button" autofocus>닫기 ×</button></header>
<iframe title="이메일 주소 확인 및 인증번호 입력" data-src="/invite_email.php?embed=1"></iframe>
</dialog>
<script>
(()=>{
 const dialog=document.getElementById('building-email-notice'),frame=dialog.querySelector('iframe');let shown=false,prior,scrollLock=null;
 function lockBackground(){
  const body=document.body,root=document.documentElement;
  const bodyKeys=['position','top','left','right','width','overflow'],rootKeys=['overflow','overscroll-behavior','scroll-behavior'];
  const snapshot=(style,keys)=>keys.map(key=>[key,style.getPropertyValue(key),style.getPropertyPriority(key)]);
  scrollLock={x:window.scrollX,y:window.scrollY,body:snapshot(body.style,bodyKeys),root:snapshot(root.style,rootKeys)};
  root.style.setProperty('scroll-behavior','auto','important');
  root.style.setProperty('overflow','hidden');root.style.setProperty('overscroll-behavior','none');
  body.style.setProperty('position','fixed');body.style.setProperty('top',-scrollLock.y+'px');body.style.setProperty('left',-scrollLock.x+'px');body.style.setProperty('right','0');body.style.setProperty('width','100%');body.style.setProperty('overflow','hidden');
 }
 function unlockBackground(){
  if(!scrollLock)return;
  const saved=scrollLock;scrollLock=null;
  const restore=(style,rows)=>rows.forEach(([key,value,priority])=>{if(value)style.setProperty(key,value,priority);else style.removeProperty(key);});
  restore(document.body.style,saved.body);
  restore(document.documentElement.style,saved.root.filter(([key])=>key!=='scroll-behavior'));
  if(prior?.isConnected)prior.focus({preventScroll:true});
  window.scrollTo({left:saved.x,top:saved.y,behavior:'instant'});
  // Allow native dialog focus restoration to finish before restoring smooth scrolling.
  requestAnimationFrame(()=>{
   if(!document.querySelector('dialog[open]'))window.scrollTo({left:saved.x,top:saved.y,behavior:'instant'});
   restore(document.documentElement.style,saved.root.filter(([key])=>key==='scroll-behavior'));
  });
 }
 const observer=new MutationObserver(showWhenReady);
 function showWhenReady(){
  if(shown||document.querySelector('dialog[open]'))return;
  shown=true;observer.disconnect();prior=document.activeElement;lockBackground();
  frame.src=frame.dataset.src;
  try{dialog.showModal();}catch(e){unlockBackground();throw e;}
 }
 dialog.querySelector('button').onclick=()=>dialog.close();
 dialog.addEventListener('close',unlockBackground);
 window.addEventListener('message',e=>{
  if(e.origin===location.origin&&e.source===frame.contentWindow&&e.data?.type==='email-verification-close')dialog.close();
 });
 function start(){observer.observe(document.body,{subtree:true,attributes:true,attributeFilter:['open'],childList:true});showWhenReady();}
 if(document.readyState==='complete')start();else window.addEventListener('load',start,{once:true});
})();
</script>
