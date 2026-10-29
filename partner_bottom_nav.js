(()=>{
 'use strict';
 const dock=document.querySelector('.cm-bottom-nav'),workspace=document.getElementById('manager-sidebar');
 if(!dock||!workspace)return;
 document.body.classList.add('cm-has-dock');
 // Delegation also covers the menu cloned into work mode.
 let pressed=null,pressTimer=0,pressedAt=0,syncFrame=0;
 function releasePress(immediate=false){
  clearTimeout(pressTimer);const button=pressed;if(!button)return;
  const delay=immediate?0:Math.max(0,110-(performance.now()-pressedAt));
  pressTimer=setTimeout(()=>{button.classList.remove('cm-is-pressed');if(pressed===button)pressed=null;},delay);
 }
 document.addEventListener('pointerdown',event=>{
  const button=event.target.closest?.('.cm-common-nav button,.cm-common-nav a');
  if(!button||button.disabled||event.isPrimary===false||event.button!==0)return;
  if(pressed)pressed.classList.remove('cm-is-pressed');clearTimeout(pressTimer);
  pressed=button;pressedAt=performance.now();button.classList.add('cm-is-pressed');
 },{capture:true,passive:true});
 document.addEventListener('pointerup',()=>releasePress(),{capture:true,passive:true});
 document.addEventListener('pointercancel',()=>releasePress(true),{capture:true,passive:true});
 window.addEventListener('blur',()=>releasePress(true));
 function syncDockSelection(){
  syncFrame=0;
  const selector=document.querySelector('dialog[data-address-dialog][open]')?'[data-address-open]':
   document.querySelector('dialog.vin-dialog[open],dialog.vp-dialog[open]')?'[data-visit-inbox-button]':
   memoToggle?.getAttribute('aria-expanded')==='true'?'[data-dock-memo]':
   document.querySelector('dialog.mvc-planner[open]')?'[data-dock-work]':'[data-dock-workspace]';
  document.querySelectorAll('.cm-common-nav').forEach(nav=>nav.querySelectorAll('a,button').forEach(button=>button.dataset.dockSelected=String(button.matches(selector))));
 }
 const queueDockSelection=()=>{if(!syncFrame)syncFrame=requestAnimationFrame(syncDockSelection);};
 new MutationObserver(queueDockSelection).observe(document.body,{subtree:true,attributes:true,attributeFilter:['open','aria-expanded']});
 document.addEventListener('click',queueDockSelection,{capture:true,passive:true});
 queueDockSelection();

 const header=document.querySelector('body>.nav'),mapElement=document.getElementById('map');
 let lastMapSize='';
 function sizeShell(){
  if(header)document.body.style.setProperty('--cm-topbar',header.getBoundingClientRect().height+'px');
  const height=dock.getBoundingClientRect().height;if(height)document.body.style.setProperty('--cm-dock-height',height+'px');
 }
 if(typeof ResizeObserver!=='undefined'){
  const shellObserver=new ResizeObserver(sizeShell);if(header)shellObserver.observe(header);shellObserver.observe(dock);
  if(mapElement)new ResizeObserver(()=>{const rect=mapElement.getBoundingClientRect(),key=rect.width+':'+rect.height;if(key===lastMapSize)return;lastMapSize=key;requestAnimationFrame(()=>window.dispatchEvent(new Event('resize')));}).observe(mapElement);
 }
 window.addEventListener('resize',sizeShell);sizeShell();

 const memoButton=dock.querySelector('[data-dock-memo]'),memoToggle=document.querySelector('#manager-quickmemo .mqm-toggle');
 if(memoButton&&memoToggle){
  memoButton.addEventListener('click',()=>memoToggle.click());
  const syncMemo=()=>memoButton.setAttribute('aria-expanded',memoToggle.getAttribute('aria-expanded')||'false');
  new MutationObserver(syncMemo).observe(memoToggle,{attributes:true,attributeFilter:['aria-expanded']});syncMemo();
 }else if(memoButton)memoButton.hidden=true;

 function cycleCenterFilter(){
  const order=['pro','linked','all','pre'];
  const filters=document.querySelector('#manager-map-filters .mb-filters');if(!filters)return;
  const current=filters.querySelector('[aria-pressed="true"]')?.dataset.mbFilter;
  const next=order[(order.indexOf(current)+1)%order.length];
  filters.querySelector('[data-mb-filter="'+next+'"]')?.click();
 }
 const workspaceButton=dock.querySelector('[data-dock-workspace]');
 workspaceButton.addEventListener('click',()=>{
  if(workspaceButton.dataset.dockSelected==='true')cycleCenterFilter();
  window.scrollTo({top:0,behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});
  const heading=document.querySelector('.cm-map-heading h2');
  if(heading){heading.setAttribute('tabindex','-1');heading.focus({preventScroll:true});}
 });
 dock.querySelector('[data-dock-work]').addEventListener('click',()=>{
  const opener=document.querySelector('.mm-management-actions .mvc-open');
  if(opener)opener.click();
 });
})();
