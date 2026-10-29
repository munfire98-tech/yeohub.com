(()=>{
 'use strict';
 const root=document.getElementById('manager-quickmemo');
 if(!root||root.dataset.ready)return;root.dataset.ready='1';
 // Forms embedded in dialogs use their parent's memo, never a second floating control.
 if(window.self!==window.top){root.hidden=true;return;}
 const panel=root.querySelector('.mqm-panel'),toggle=root.querySelector('.mqm-toggle'),close=root.querySelector('.mqm-close'),ta=root.querySelector('textarea'),status=root.querySelector('.mqm-state'),button=root.querySelector('.mqm-save');
 const scope=root.dataset.scope,key='manager-quickmemo-open-v1:'+scope,endpoint='/manager_quickmemo.php';
 let ready=false,loading=false,saving=false,blocked=false,last='',revision=0,csrf='',timer=null;
 function state(text,type=''){status.textContent=text;root.dataset.state=type;}
 function dirty(){return ready&&ta.value!==last;}
 function showMemoEnd(){
  // Scroll only; focusing or selecting here can raise the iPhone keyboard.
  requestAnimationFrame(()=>{if(panel.hidden||!ready)return;ta.scrollTop=ta.scrollHeight;requestAnimationFrame(()=>{if(!panel.hidden&&ready)ta.scrollTop=ta.scrollHeight;});});
 }
 async function response(options){
  const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),12000);
  try{const r=await fetch(endpoint,{credentials:'same-origin',cache:'no-store',...options,signal:controller.signal});const j=await r.json();
   if(!r.ok||!j.ok){const e=new Error(j.error||'저장하지 못했습니다. 다시 시도해 주세요.');e.code=r.status;throw e;}
   if(j.scope!==scope){const e=new Error('로그인 정보가 바뀌었습니다. 새로고침해 주세요.');e.code=403;throw e;}
   return j;
  }finally{clearTimeout(timeout);}
 }
 async function load(){
  if(ready||loading)return;loading=true;button.disabled=true;state('메모를 불러오는 중…');
  try{const j=await response();last=j.memo.text;revision=j.memo.revision;csrf=j.csrf;ta.value=last;ta.disabled=false;ready=true;button.textContent='저장';state(j.memo.updated?j.memo.updated+' 저장됨':'입력을 멈추면 자동 저장됩니다.','saved');showMemoEnd();}
  catch(e){state(e.message==='Failed to fetch'?'연결을 확인한 뒤 다시 시도해 주세요.':e.message,'error');button.textContent='다시 시도';}
  finally{loading=false;button.disabled=false;}
 }
 async function save(){
  clearTimeout(timer);if(!ready)return load();if(saving||blocked||!dirty())return;
  if(ta.value.length>10000){state('메모는 10,000자 이내로 입력해 주세요.','error');return;}
  const value=ta.value;saving=true;button.disabled=true;state('저장 중…','saving');
  try{const body=new URLSearchParams({csrf,scope,text:value,revision:String(revision)});const j=await response({method:'POST',body,keepalive:new Blob([body.toString()]).size<60000});last=value;revision=j.memo.revision;state(dirty()?'입력 중…':j.memo.updated+' 저장됨',dirty()?'':'saved');button.textContent='저장';}
  catch(e){if(e.code===403||e.code===409)blocked=true;state(e.name==='AbortError'?'응답이 늦습니다. 저장을 다시 눌러 주세요.':e.message,'error');}
  finally{saving=false;button.disabled=blocked;if(dirty()&&!blocked&&root.dataset.state!=='error')timer=setTimeout(save,500);}
 }
 function open(value){if(!value)ta.blur();panel.hidden=!value;syncScreen();toggle.setAttribute('aria-expanded',String(value));try{localStorage.setItem(key,value?'1':'0');}catch{}if(value){if(ready)showMemoEnd();else load();}else{save();const returnTo=toggle.getClientRects().length?toggle:(document.querySelector('dialog.mvc-planner[open] [data-work-memo]')||document.querySelector('[data-dock-memo]'));returnTo?.focus({preventScroll:true});}}
 toggle.addEventListener('click',()=>open(panel.hidden));close.addEventListener('click',()=>open(false));
 root.addEventListener('keydown',e=>{if(e.key==='Escape'&&!panel.hidden){e.preventDefault();e.stopPropagation();open(false);}});
 ta.addEventListener('input',()=>{clearTimeout(timer);if(blocked)return;state(dirty()?'입력 중…':'저장됨',dirty()?'':'saved');timer=setTimeout(save,800);});
 button.addEventListener('click',()=>ready?save():load());
 window.addEventListener('beforeunload',e=>{if(dirty()){e.preventDefault();e.returnValue='';}});
 document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='hidden'&&dirty())save();});
 window.addEventListener('pagehide',()=>{
  if(!dirty()||saving||blocked)return;
  // A bounded, scoped request also covers mobile navigation that skips beforeunload.
  const body=new URLSearchParams({csrf,scope,text:ta.value,revision:String(revision)});
  if(new Blob([body.toString()]).size<60000)fetch(endpoint,{method:'POST',credentials:'same-origin',body,keepalive:true}).catch(()=>{});
 });
 const mobileScreen=matchMedia('(max-width:760px), (pointer:coarse) and (max-height:500px)');
 let screenMetrics=null,memoLock=null;
 function lockMemoScreen(inline){
  if(inline&&!memoLock){
   const planner=root.closest('dialog.mvc-dialog[open]');
   const target=planner||document.body,props=planner?['overflow','overscroll-behavior']:['position','top','left','width','overflow','overscroll-behavior'];
   memoLock={target,styles:props.map(p=>[p,target.style.getPropertyValue(p),target.style.getPropertyPriority(p)]),x:scrollX,y:scrollY,scrollTop:target.scrollTop};
   if(!planner){target.style.setProperty('position','fixed');target.style.setProperty('top',-memoLock.y+'px');target.style.setProperty('left',-memoLock.x+'px');target.style.setProperty('width','100%');}
   target.style.setProperty('overflow','hidden');target.style.setProperty('overscroll-behavior','none');
   const lock=memoLock;lock.onScroll=()=>{if(root.classList.contains('mqm-inline')&&target!==document.body&&target.scrollTop!==lock.scrollTop)target.scrollTop=lock.scrollTop;};target.addEventListener('scroll',lock.onScroll,{passive:true});
  }else if(!inline&&memoLock){
   const old=memoLock;memoLock=null;old.target.removeEventListener('scroll',old.onScroll);old.styles.forEach(([p,v,priority])=>v?old.target.style.setProperty(p,v,priority):old.target.style.removeProperty(p));old.target.scrollTop=old.scrollTop;
   if(old.target===document.body)window.scrollTo({left:old.x,top:old.y,behavior:'instant'});
  }
 }
 function syncScreen(){
  const inline=mobileScreen.matches&&!panel.hidden&&document.body.classList.contains('cm-has-dock');
  root.classList.toggle('mqm-inline',inline);document.body.classList.toggle('mqm-mobile-open',inline);
  close.textContent=inline?'‹':'×';close.setAttribute('aria-label',inline?'메모에서 돌아가기':'메모 접기');
  lockMemoScreen(inline);
  if(!inline){screenMetrics=null;root.classList.remove('mqm-keyboard-open');return;}
  const planner=root.closest('dialog.mvc-planner[open]'),nav=(planner||document).querySelector(planner?'.mvc-mobile-tabs.cm-common-nav':'.cm-bottom-nav');
  const v=window.visualViewport,visualTop=v?.offsetTop||0,visualHeight=v?.height||innerHeight;
  // Capture the normal header/dock once. Their rects move when iOS pans to the caret.
  if(!screenMetrics){const rect=nav?.getBoundingClientRect();const top=planner?Math.max(0,planner.getBoundingClientRect().top):Math.max(0,document.querySelector('body>.nav')?.getBoundingClientRect().bottom||0);screenMetrics={height:visualHeight,top:Math.max(0,top-visualTop),bottom:rect?.height?Math.max(0,visualTop+visualHeight-rect.top):80};}
  const keyboard=screenMetrics.height-visualHeight>120;
  const topInset=keyboard?0:screenMetrics.top,bottomInset=keyboard?0:screenMetrics.bottom;
  root.classList.toggle('mqm-keyboard-open',keyboard);
  root.style.setProperty('--mqm-screen-top',(visualTop+topInset)+'px');
  root.style.setProperty('--mqm-screen-height',Math.max(0,visualHeight-topInset-bottomInset)+'px');
 }
 // Scroll the memo itself; do not let a swipe pull the page or planner behind it.
 let touchY=null;
 root.addEventListener('touchstart',e=>{touchY=e.touches[0]?.clientY??null;},{passive:true});
 root.addEventListener('touchmove',e=>{
  if(!root.classList.contains('mqm-inline')||touchY===null)return;const y=e.touches[0]?.clientY;if(y===undefined)return;
  const dy=y-touchY;touchY=y;const inText=e.target===ta;
  if(!inText||(dy>0&&ta.scrollTop<=0)||(dy<0&&ta.scrollTop+ta.clientHeight>=ta.scrollHeight-1))e.preventDefault();
 },{passive:false});
 ta.addEventListener('focus',()=>{root.classList.add('mqm-editing');viewport();requestAnimationFrame(viewport);});
 ta.addEventListener('blur',()=>{root.classList.remove('mqm-editing');requestAnimationFrame(viewport);});
 window.addEventListener('orientationchange',()=>{screenMetrics=null;requestAnimationFrame(viewport);});
 // Navigate between bottom-menu screens without leaving a memo overlay behind.
 window.addEventListener('click',e=>{
  const menu=e.target.closest?.('.cm-common-nav button,.cm-common-nav a');
  if(menu&&!menu.disabled&&!menu.matches('[data-dock-memo]')&&root.classList.contains('mqm-inline'))open(false);
 },true);
 mobileScreen.addEventListener('change',()=>{screenMetrics=null;syncScreen();});
 function viewport(){
  const v=window.visualViewport,footer=root.closest('dialog.mvc-planner')?.querySelector('.mvc-mobile-footer');
  const floor=footer?footer.getBoundingClientRect().height:0;
  root.style.setProperty('--mqm-visit-footer',floor+'px');
  root.style.setProperty('--mqm-width',Math.min(document.documentElement.clientWidth,v?v.width:innerWidth)+'px');
  root.style.setProperty('--mqm-height',Math.max(0,(v?v.height:innerHeight)-floor)+'px');
  root.style.setProperty('--mqm-keyboard',Math.max(0,v?innerHeight-v.height-v.offsetTop:0)+'px');
  syncScreen();
 }
 // Native modal dialogs make the page behind them inert. Move the existing memo
 // into the active visit dialog so its text, save queue and open state stay intact.
 const home=root.parentNode,homeNext=root.nextSibling;
 const footerObserver=typeof ResizeObserver!=='undefined'?new ResizeObserver(viewport):null;
 function syncVisitHost(){
  const target=document.querySelector('dialog.mvc-day-dialog[open]')||document.querySelector('dialog.mvc-planner[open]');
  const parent=target||home;
  if(root.parentNode!==parent){
   lockMemoScreen(false);screenMetrics=null;
   if(target)target.append(root);else home.insertBefore(root,homeNext?.parentNode===home?homeNext:null);
  }
  footerObserver?.disconnect();
  const footer=target?.querySelector('.mvc-mobile-footer');if(footer)footerObserver?.observe(footer);
  viewport();
 }
 new MutationObserver(changes=>{
  if(changes.some(change=>change.target.matches('dialog.mvc-dialog')))syncVisitHost();
 }).observe(document.body,{subtree:true,attributes:true,attributeFilter:['open']});
 syncVisitHost();
 window.visualViewport?.addEventListener('resize',viewport);window.visualViewport?.addEventListener('scroll',viewport);window.addEventListener('resize',viewport);viewport();
 try{if(!mobileScreen.matches&&localStorage.getItem(key)==='1')open(true);}catch{}
})();
