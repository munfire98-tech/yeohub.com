(()=>{'use strict';
 const source=document.currentScript;if(!source)return;
 const csrf=source.dataset.csrf,key='manager-return-v1:'+source.dataset.stateKey;
 let state=null,navigating=false,restored=false;
 try{const saved=JSON.parse(sessionStorage.getItem(key)||'null');sessionStorage.removeItem(key);if(saved&&Number.isFinite(saved.at)&&Date.now()-saved.at>=0&&Date.now()-saved.at<7200000)state=saved;}catch{}
 // The existing return endpoint includes this anchor. Restore the saved scroll
 // position instead of letting the browser jump to the sidebar after load.
 if(state&&location.hash==='#manager-sidebar')history.replaceState(history.state,'',location.pathname+location.search);
 const readers={};
 function capture(){
  const saved={at:Date.now(),scrollY:window.scrollY,panels:{}};
  document.querySelectorAll('#manager-sidebar .ms-content[id]').forEach(el=>saved.panels[el.id]=el.scrollTop);
  for(const [name,read] of Object.entries(readers)){try{saved[name]=read();}catch{}}
  try{sessionStorage.setItem(key,JSON.stringify(saved));}catch{}
 }
 function complete(){
  if(!state||restored)return;restored=true;
  requestAnimationFrame(()=>requestAnimationFrame(()=>{
   for(const [id,top] of Object.entries(state.panels||{})){const el=document.getElementById(id);if(el&&el.closest('#manager-sidebar')&&Number.isFinite(top))el.scrollTop=Math.max(0,top);}
   if(Number.isFinite(state.scrollY))window.scrollTo({top:Math.max(0,state.scrollY),behavior:'instant'});
  }));
 }
 window.managerNavigation={state,register:(name,read)=>{readers[name]=read;},complete};
 document.addEventListener('click',event=>{
  const link=event.target.closest('a[href]');if(!link||event.defaultPrevented||event.button!==0||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;
  const url=new URL(link.href,location.href),uid=url.searchParams.get('uid');
  if(url.origin!==location.origin||url.pathname!=='/manager_view.php'||!uid||!/^[A-Za-z0-9_-]{1,64}$/.test(uid))return;
  // An ordinary click posts through the existing permission/CSRF checks in this tab.
  // Without JS or a CSRF token, the same-tab link still opens the existing entry page.
  if(!csrf)return;event.preventDefault();if(navigating)return;navigating=true;
  capture();
  const form=document.createElement('form');form.method='post';form.action='/manager_view.php';form.target='_self';form.hidden=true;form.dataset.managerNavigation='1';
  for(const [name,value] of Object.entries({action:'edit',uid,csrf,destination:'building'})){const input=document.createElement('input');input.type='hidden';input.name=name;input.value=value;form.append(input);}
  document.body.append(form);form.submit();
 });
 window.addEventListener('pageshow',event=>{navigating=false;document.querySelectorAll('form[data-manager-navigation]').forEach(form=>form.remove());if(event.persisted){try{sessionStorage.removeItem(key);}catch{}}});
})();
