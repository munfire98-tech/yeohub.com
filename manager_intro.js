(()=>{
 'use strict';
 const dialog=document.getElementById('manager-intro');
 if(!dialog||typeof dialog.showModal!=='function')return;
 const openers=[...document.querySelectorAll('[data-manager-intro-open]')];
 // Preserve the user's explicit mute choice, but no longer use the old daily "seen" flag.
 const key='manager-intro-v1:'+dialog.dataset.scope;
 const panels=[...dialog.querySelectorAll('[data-mi-panel]')];
 const mobile=matchMedia('(max-width:719px)');
 const mute=dialog.querySelector('[data-mi-mute]'),prev=dialog.querySelector('[data-mi-prev]'),next=dialog.querySelector('[data-mi-next]');
 const read=s=>{try{return localStorage.getItem(key+s);}catch{return null;}};
 const write=(s,v)=>{try{localStorage.setItem(key+s,v);}catch{}};
 const rewards=dialog.querySelector('[data-mi-rewards]'),comic=dialog.querySelector('[data-mi-comic]'),forward=dialog.querySelector('[data-mi-continue]'),back=dialog.querySelector('[data-mi-back]'),start=dialog.querySelector('[data-mi-start]');
 function stage(showComic){rewards.hidden=showComic;comic.hidden=!showComic;forward.hidden=showComic;back.hidden=!showComic;start.hidden=!showComic;dialog.querySelector('#mi-title').textContent=showComic?'소방계획서 작성, 먼저 제안해 주세요':'매니저 리워드';dialog.querySelector('[data-mi-subtitle]').textContent=showComic?'필요한 정보를 미리 준비해, 유저가 쉽게 시작하도록 안내해 주세요.':'유저의 구독과 계획서 작성 완료가 매니저의 리워드로 이어집니다.';dialog.querySelector('.mi-body').scrollTop=0;dialog.querySelector('[data-mi-close]').focus({preventScroll:true});}
 forward.addEventListener('click',()=>stage(true));back.addEventListener('click',()=>stage(false));
 let index=0,returnFocus=null,shownThisVisit=false,timer=null;
 function render(){panels.forEach((p,i)=>{p.hidden=mobile.matches&&i!==index;});prev.disabled=index===0;next.disabled=index===panels.length-1;dialog.querySelector('[data-mi-count]').textContent=`${index+1} / ${panels.length}`;}
 function open(trigger){if(dialog.open)return;returnFocus=trigger||document.activeElement;index=0;render();stage(false);mute.checked=read(':muted')==='1';dialog.showModal();shownThisVisit=true;dialog.querySelector('[data-mi-close]').focus({preventScroll:true});}
 function queueAuto(){
  if(timer!==null)clearTimeout(timer);
  timer=setTimeout(()=>{timer=null;if(shownThisVisit||document.visibilityState!=='visible'||read(':muted')==='1'||document.querySelector('dialog[open]'))return;open(openers[0]);},500);
 }
 openers.forEach(b=>b.addEventListener('click',()=>open(b)));
 dialog.querySelector('[data-mi-close]').addEventListener('click',()=>dialog.close());
 dialog.querySelector('[data-mi-register]')?.addEventListener('click',()=>{
   const entry=document.querySelector('.mm-draft-add[data-address-open]');
   if(entry)returnFocus=entry;
   dialog.close();
   requestAnimationFrame(()=>{if(entry){entry.scrollIntoView({behavior:'auto',block:'center'});entry.click();}else location.assign('/manager_addresses.php?new=1');});
 });
 mute.addEventListener('change',()=>write(':muted',mute.checked?'1':'0'));
 prev.addEventListener('click',()=>{index=Math.max(0,index-1);render();});next.addEventListener('click',()=>{index=Math.min(panels.length-1,index+1);render();});
 mobile.addEventListener('change',render);
 dialog.addEventListener('click',e=>{if(e.target!==dialog)return;const b=dialog.getBoundingClientRect();if(e.clientX<b.left||e.clientX>b.right||e.clientY<b.top||e.clientY>b.bottom)dialog.close();});
 dialog.addEventListener('close',()=>{if(returnFocus instanceof HTMLElement&&returnFocus.isConnected)returnFocus.focus({preventScroll:true});});
 dialog.querySelector('[data-mi-start]').addEventListener('click',()=>{const users=document.querySelector('[data-ms-tab="users"]');if(users)returnFocus=users;dialog.close();if(users){users.click();users.scrollIntoView({behavior:'auto',block:'center'});users.focus({preventScroll:true});}});
 // Wait for an existing dialog to close, without repeated polling or reopening after dismissal.
 document.addEventListener('close',e=>{if(e.target!==dialog&&!shownThisVisit)queueAuto();},true);
 document.addEventListener('visibilitychange',()=>{if(!shownThisVisit)queueAuto();});
 window.addEventListener('pageshow',e=>{if(e.persisted){shownThisVisit=false;queueAuto();}});
 queueAuto();
})();
