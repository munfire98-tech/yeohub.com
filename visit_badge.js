/* Home-screen badge mirrors the authorized visit inbox; never asks for permission. */
(()=>{
 'use strict';
 if(window.visitAppBadgeSet||location.pathname.startsWith('/partner_demo_app.php/'))return;
 let revision=0,loading=false,last=0,chain=Promise.resolve();
 const supported=()=>typeof navigator.setAppBadge==='function';
 window.visitAppBadgeSet=count=>{
  revision++;if(!supported())return Promise.resolve();
  count=Math.max(0,Math.trunc(Number(count)||0));
  // Serialize native calls so a late update cannot overwrite a newer read count.
  chain=chain.catch(()=>{}).then(()=>count?navigator.setAppBadge(count):typeof navigator.clearAppBadge==='function'?navigator.clearAppBadge():navigator.setAppBadge(0)).catch(()=>{});
  return chain;
 };
 async function refresh(force=false){
  if(!supported()||loading||(!force&&Date.now()-last<30000))return;
  loading=true;const version=revision,controller=new AbortController(),timer=setTimeout(()=>controller.abort(),10000);
  try{
   const r=await fetch('/visit_push_api.php?inbox=1&counts=1',{credentials:'same-origin',cache:'no-store',signal:controller.signal});
   if(version!==revision)return;
   if(r.status===401){await window.visitAppBadgeSet(0);return;}
   if(!r.ok)return;const d=await r.json();
   if(d.ok&&version===revision){last=Date.now();await window.visitAppBadgeSet(d.unread);}
  }catch{}finally{clearTimeout(timer);loading=false;}
 }
 window.visitAppBadgeRefresh=refresh;
 document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible')refresh(true);});
 window.addEventListener('pageshow',()=>refresh(true));
 if('serviceWorker' in navigator){
  navigator.serviceWorker.addEventListener('controllerchange',()=>refresh(true));
  // Update only our existing worker; do not install or subscribe from shared pages.
  navigator.serviceWorker.getRegistration('/').then(r=>{if(r?.active&&new URL(r.active.scriptURL).pathname==='/visit_push_sw.js')r.update().catch(()=>{});}).catch(()=>{});
 }
 refresh();
})();
