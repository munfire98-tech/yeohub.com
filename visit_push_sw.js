/* No page caching: account pages always come from the server. */
self.addEventListener('install',()=>self.skipWaiting());
self.addEventListener('activate',e=>e.waitUntil(self.clients.claim()));
async function visitBadgeRefresh(){
 if(typeof self.navigator.setAppBadge!=='function')return;
 const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),10000);
 try{
  const r=await fetch('/visit_push_api.php?inbox=1&counts=1',{credentials:'include',cache:'no-store',redirect:'error',signal:controller.signal});
  if(r.status===401){await visitBadgeClear();return;}
  if(!r.ok)return;const d=await r.json();
  if(d.ok)await self.navigator.setAppBadge(Math.max(0,Math.trunc(Number(d.unread)||0)));
 }catch{}finally{clearTimeout(timer);}
}
async function visitBadgeClear(){
 try{if(typeof self.navigator.clearAppBadge==='function')await self.navigator.clearAppBadge();else if(typeof self.navigator.setAppBadge==='function')await self.navigator.setAppBadge(0);}catch{}
}
self.addEventListener('push',event=>{
 event.waitUntil((async()=>{
  let data;try{data=event.data?.json();}catch{return;}
  if(!/^[a-f0-9]{48}$/.test(data?.message||''))return;
  // Re-check the authenticated account, device, access and current schedule before display.
  const response=await fetch('/visit_push_api.php?message='+data.message,{credentials:'include',cache:'no-store',redirect:'error'});
  if(!response.ok){if(response.status===401)await visitBadgeClear();return;}
  const result=await response.json();if(!result.ok)return;const n=result.notification;
  await self.registration.showNotification(n.title,{body:n.body,icon:'/assets/app-icons/sobang-192-v1.png',badge:'/assets/app-icons/sobang-32-v1.png',tag:result.tag,data:{message:data.message,url:'/visit_push_open.php?message='+data.message},renotify:false});
  await visitBadgeRefresh();
  const windows=await self.clients.matchAll({type:'window',includeUncontrolled:true});
  windows.forEach(client=>client.postMessage({type:'VISIT_INBOX_CHANGED'}));
 })());
});
self.addEventListener('notificationclick',event=>{
 event.notification.close();event.waitUntil((async()=>{
  const message=event.notification.data?.message;const raw=/^[a-f0-9]{48}$/.test(message||'')?'/visit_push_open.php?message='+message:(event.notification.data?.url||'/partner_center.php?visit_notice=test'),url=new URL(raw,self.location.origin);
  if(url.origin!==self.location.origin||!['/visit_push_open.php','/partner_center.php'].includes(url.pathname))return;
  const windows=await self.clients.matchAll({type:'window',includeUncontrolled:true});
  for(const client of windows)if('navigate' in client){try{const next=await client.navigate(url.href);await (next||client).focus();return;}catch{/* A closing/stale window must not prevent the notification from opening. */}}
  await self.clients.openWindow(url.href);
 })());
});
self.addEventListener('message',e=>{if(e.data?.type==='VISIT_PUSH_CLEAR')e.waitUntil(Promise.all([visitBadgeClear(),self.registration.getNotifications().then(items=>items.forEach(n=>n.close()))]));});
