(()=>{
 'use strict';
 const dialog=document.getElementById('manager-intro');
 if(!dialog||typeof dialog.showModal!=='function')return;
 const openers=[...document.querySelectorAll('[data-manager-intro-open]')];
 // Preserve the existing explicit do-not-show choice.
 const key='manager-intro-v1:'+dialog.dataset.scope;
 const panels=[...dialog.querySelectorAll('[data-mi-panel]')];
 const mobile=matchMedia('(max-width:719px)');
 const mute=dialog.querySelector('[data-mi-mute]'),prev=dialog.querySelector('[data-mi-prev]'),next=dialog.querySelector('[data-mi-next]');
 const read=s=>{try{return localStorage.getItem(key+s);}catch{return null;}};
 const write=(s,v)=>{try{localStorage.setItem(key+s,v);}catch{}};
 const tabs=[...dialog.querySelectorAll('[data-mi-tab]')];
 const sections={video:dialog.querySelector('[data-mi-video]'),comic:dialog.querySelector('[data-mi-comic]'),rewards:dialog.querySelector('[data-mi-rewards]')};
 const player=dialog.querySelector('[data-mi-player]');
 const pause=()=>{if(player&&!player.paused)player.pause();};
 const controls=dialog.querySelector('[data-mi-comic-controls]');
 const close=dialog.querySelector('[data-mi-close]');
 const descriptions={video:['등록부터 꾸준한 관리까지','등록·구독 후에도 유저의 업무를 함께 살펴보세요.'],comic:['그림으로 따라가는 6단계','한 컷씩 확인하고, 현장에서 함께 시작해 보세요.'],rewards:['도움이 쌓이면, 마일리지도 함께','첫 시작 혜택과 매월 적립 조건을 확인하세요.']};
 function stage(name){
  if(!sections[name])return;
  pause();
  for(const [id,section] of Object.entries(sections))section.hidden=id!==name;
  for(const tab of tabs){const active=tab.dataset.miTab===name;tab.setAttribute('aria-selected',String(active));tab.tabIndex=active?0:-1;}
  controls.hidden=name!=='comic';
  dialog.dataset.stage=name;
  dialog.querySelector('#mi-title').textContent=descriptions[name][0];
  dialog.querySelector('[data-mi-subtitle]').textContent=descriptions[name][1];
  dialog.querySelector('.mi-body').scrollTop=0;
 }
 tabs.forEach((tab,i)=>{
  tab.addEventListener('click',()=>stage(tab.dataset.miTab));
  tab.addEventListener('keydown',e=>{
   let target;
   if(e.key==='ArrowRight')target=(i+1)%tabs.length;
   else if(e.key==='ArrowLeft')target=(i+tabs.length-1)%tabs.length;
   else if(e.key==='Home')target=0;
   else if(e.key==='End')target=tabs.length-1;
   else return;
   e.preventDefault();stage(tabs[target].dataset.miTab);tabs[target].focus();
  });
 });
 dialog.querySelectorAll('[data-mi-stage]').forEach(button=>button.addEventListener('click',()=>{stage(button.dataset.miStage);tabs.find(tab=>tab.dataset.miTab===button.dataset.miStage)?.focus({preventScroll:true});}));
 const mediaError=()=>{dialog.querySelector('[data-mi-video-error]').hidden=false;};
 player?.addEventListener('error',mediaError);
 player?.querySelector('source')?.addEventListener('error',mediaError);
 let index=0,returnFocus=null,shownThisVisit=false,timer=null,inventoryKnown=false,needsConnectionGuide=false;
 document.addEventListener('partner-tour-open',()=>{shownThisVisit=true;clearTimeout(timer);if(dialog.open)dismiss();});
 document.addEventListener('manager-map-navigation',()=>{shownThisVisit=true;clearTimeout(timer);if(dialog.open)dismiss();});
 // Partners without a linked user get the building example first. The guide remains
 // available from its button; never cover the example with a second auto popup.
 document.addEventListener('manager-buildings-updated',e=>{inventoryKnown=true;needsConnectionGuide=Array.isArray(e.detail.buildings)&&!e.detail.buildings.some(row=>!row.demo&&!row.preregistered);queueAuto();});
 document.addEventListener('manager-buildings-unavailable',()=>{inventoryKnown=true;needsConnectionGuide=false;queueAuto();});
 function render(){panels.forEach((p,i)=>{p.hidden=mobile.matches&&i!==index;});prev.disabled=index===0;next.disabled=index===panels.length-1;dialog.querySelector('[data-mi-count]').textContent=`${index+1} / ${panels.length}`;}
 function dismiss(){pause();dialog.close();}
 function open(trigger){if(dialog.open)return;returnFocus=trigger||document.activeElement;index=0;render();stage('video');mute.checked=read(':muted')==='1';dialog.showModal();shownThisVisit=true;close.focus({preventScroll:true});}
 function queueAuto(){
  if(timer!==null)clearTimeout(timer);
  timer=setTimeout(()=>{timer=null;if(!inventoryKnown||needsConnectionGuide||shownThisVisit||document.visibilityState!=='visible'||read(':muted')==='1'||document.querySelector('dialog[open]'))return;open(openers[0]);},500);
 }
 openers.forEach(b=>b.addEventListener('click',()=>open(b)));
 close.addEventListener('click',dismiss);
 dialog.addEventListener('cancel',pause);
 dialog.querySelectorAll('[data-mi-register]').forEach(button=>button.addEventListener('click',e=>{
   e.preventDefault();
   const entry=document.querySelector('.mm-draft-add[data-address-open]');
   if(entry)returnFocus=entry;
   dismiss();
   requestAnimationFrame(()=>{if(entry){entry.scrollIntoView({behavior:'auto',block:'center'});entry.click();}else location.assign('/manager_addresses.php?new=1');});
 }));
 dialog.querySelector('[data-mi-visits]')?.addEventListener('click',()=>{
   dismiss();
   requestAnimationFrame(()=>document.dispatchEvent(new CustomEvent('manager-visit-open')));
 });
 mute.addEventListener('change',()=>write(':muted',mute.checked?'1':'0'));
 prev.addEventListener('click',()=>{index=Math.max(0,index-1);render();dialog.querySelector('.mi-body').scrollTop=0;});next.addEventListener('click',()=>{index=Math.min(panels.length-1,index+1);render();dialog.querySelector('.mi-body').scrollTop=0;});
 mobile.addEventListener('change',render);
 dialog.addEventListener('click',e=>{if(e.target!==dialog)return;const b=dialog.getBoundingClientRect();if(e.clientX<b.left||e.clientX>b.right||e.clientY<b.top||e.clientY>b.bottom)dismiss();});
 dialog.addEventListener('close',()=>{pause();if(returnFocus instanceof HTMLElement&&returnFocus.isConnected)returnFocus.focus({preventScroll:true});});
 dialog.querySelector('[data-mi-start]').addEventListener('click',()=>{const users=document.querySelector('[data-ms-tab="users"]');if(users)returnFocus=users;dismiss();if(users){users.click();users.scrollIntoView({behavior:'auto',block:'center'});users.focus({preventScroll:true});}});
 document.addEventListener('close',e=>{if(e.target!==dialog&&!shownThisVisit)queueAuto();},true);
 document.addEventListener('visibilitychange',()=>{if(document.visibilityState!=='visible')pause();else if(!shownThisVisit)queueAuto();});
 window.addEventListener('pagehide',pause);
 window.addEventListener('pageshow',e=>{if(e.persisted){shownThisVisit=false;queueAuto();}});
 queueAuto();
})();
