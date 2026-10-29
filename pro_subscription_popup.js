(()=>{
 let dialog,frame,sampleFrame,prior,selectPreview,activeFeature,dirty=false;
 function open(feature='plan'){
  feature=feature==='notifications'?'notifications':'plan';
  if(dialog){if(activeFeature===feature){dialog.showModal();if(feature==='notifications')selectPreview(true);return;}dialog.addEventListener('close',()=>open(feature),{once:true});dialog.close();return;}
  activeFeature=feature;
  prior=document.activeElement;dialog=document.createElement('dialog');dialog.className='pro-subscription-dialog';
  dialog.innerHTML='<header><div><small>SB.PLAN PRO</small><h2>PRO 이용 안내</h2></div><button type="button" aria-label="PRO 이용 안내 닫기">닫기</button></header><iframe title="PRO 카드 등록 및 구독 관리" src="/subscribe_page.php?embed=1&amp;pro_popup=1"></iframe>';
  const tabs=document.createElement('nav');tabs.className='pro-plan-tabs';tabs.setAttribute('aria-label','PRO 이용 안내 화면');tabs.setAttribute('role','tablist');
  tabs.innerHTML='<button type="button" role="tab" aria-selected="true" data-pro-guide>PRO 이용안내</button><button type="button" role="tab" aria-selected="false" data-pro-sample>소방계획서 출력 예시 <span class="pro-sample-dot" aria-hidden="true"></span></button>';
  if(feature==='notifications'){dialog.querySelector('h2').textContent='업무알림 PRO';tabs.querySelector('[data-pro-sample]').textContent='업무알림 예시';}
  dialog.querySelector('header').after(tabs);
  function selectSample(on){
   if(on&&!sampleFrame){sampleFrame=document.createElement('iframe');sampleFrame.title=feature==='notifications'?'업무알림 PRO 가상 예시':'가상 건물 소방계획서 완성 예시';sampleFrame.src=feature==='notifications'?'/building_notification_sample.html':'/fire_plan_sample.html';sampleFrame.setAttribute('sandbox','allow-scripts allow-same-origin allow-modals');dialog.append(sampleFrame);}
   frame.hidden=on;if(sampleFrame)sampleFrame.hidden=!on;
   if(on)tabs.dataset.previewSeen='1';
   tabs.querySelector('[data-pro-guide]').setAttribute('aria-selected',String(!on));tabs.querySelector('[data-pro-sample]').setAttribute('aria-selected',String(on));
  }
  selectPreview=selectSample;
  tabs.querySelector('[data-pro-guide]').onclick=()=>selectSample(false);tabs.querySelector('[data-pro-sample]').onclick=()=>selectSample(true);
  tabs.addEventListener('keydown',e=>{if(e.key!=='ArrowLeft'&&e.key!=='ArrowRight')return;e.preventDefault();const on=e.key==='ArrowRight';selectSample(on);tabs.querySelector(on?'[data-pro-sample]':'[data-pro-guide]').focus();});
  window.openProPlanPreview=()=>{selectSample(true);tabs.querySelector('[data-pro-sample]').focus();};
  frame=dialog.querySelector('iframe');dialog.querySelector('button').onclick=()=>dialog.close();
  dialog.addEventListener('close',()=>{dialog.remove();dialog=null;frame=null;sampleFrame=null;delete window.openProPlanPreview;prior?.focus();if(dirty)location.reload();});
  document.body.append(dialog);dialog.showModal();if(feature==='notifications')selectSample(true);
 }
 window.openProSubscription=open;
 document.addEventListener('click',e=>{
  if(e.target.closest('[data-pro-notification-preview]')){e.preventDefault();e.stopImmediatePropagation();open('notifications');return;}
  const link=e.target.closest('a[href]');if(!link||e.button!==0||e.ctrlKey||e.metaKey||e.shiftKey||e.altKey)return;
  const url=new URL(link.href,location.href);if(url.origin!==location.origin||url.pathname!=='/subscribe_page.php')return;
  e.preventDefault();e.stopImmediatePropagation();open();
 },true);
 window.addEventListener('message',e=>{if(e.origin!==location.origin)return;if(sampleFrame&&e.source===sampleFrame.contentWindow&&e.data?.type==='pro-guide-request'){selectPreview(false);return;}if(!frame||e.source!==frame.contentWindow)return;if(e.data?.type==='pro-subscription-updated')dirty=true;});
 if(new URL(location.href).searchParams.get('pro_popup')==='1'){
  const url=new URL(location.href);url.searchParams.delete('pro_popup');history.replaceState(null,'',url);open();
 }
})();
