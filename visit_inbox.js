(()=>{
 'use strict';
 let dialog,trigger,csrf='',items=[],next=null,unread=0,busy=false,lastRefresh=0,refreshing=false,requestId=0,revision='',queuedRefresh=false,group='all',groups={},detailItem=null;
 const q=s=>dialog.querySelector(s),el=(tag,text,cls)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=text;if(cls)n.className=cls;return n;};
 const channel=typeof BroadcastChannel==='function'?new BroadcastChannel('partner-inbox'):null;
 const demo=()=>location.pathname.startsWith('/partner_demo_app.php/');
 async function request(body,offset=0,counts=false){
  const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),12000);
  try{const r=await fetch('/visit_push_api.php'+(body?'':'?inbox=1&group='+encodeURIComponent(group)+'&offset='+offset+(counts?'&counts=1':'')),{credentials:'same-origin',cache:'no-store',signal:controller.signal,...(body?{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({...body,csrf})}:{})});const d=await r.json();if(!r.ok||!d.ok){const e=Error(d.error||'알림을 불러오지 못했습니다.');e.status=r.status;throw e;}return d;}finally{clearTimeout(timer);}
 }
 function badges(count){
  window.visitAppBadgeSet?.(count);
  unread=count;document.querySelectorAll('[data-visit-inbox-button]').forEach(b=>{const badge=b.querySelector('.vin-badge');badge.hidden=!count;badge.textContent=count>99?'99+':String(count);b.setAttribute('aria-label','알림함 · 미확인 '+count+'건');});
  if(dialog){q('.vin-unread').textContent='전체 미확인 '+count+'건';q('.vin-read-all').disabled=busy||!(groups[group]?.unread??count);q('.vin-read-all').textContent=group==='all'?'모두 확인':'이 분류 모두 확인';
   dialog.querySelectorAll('[data-vin-group]').forEach(b=>{const key=b.dataset.vinGroup,n=groups[key]?.unread||0;b.setAttribute('aria-pressed',String(key===group));b.disabled=busy;const badge=b.querySelector('b');badge.hidden=!n;badge.textContent=n>99?'99+':String(n);});}
 }
 window.visitInboxRefresh=async(force=false)=>{
  if(demo())return;if(refreshing){if(force)queuedRefresh=true;return;}if((!force&&Date.now()-lastRefresh<30000))return;
  refreshing=true;try{const d=await request(null,0,true);lastRefresh=Date.now();groups=d.groups||{};badges(d.unread);if(d.revision!==revision){revision=d.revision;if(dialog?.open&&!busy)await load();}}catch(e){if(e.status===401)badges(0);}finally{refreshing=false;if(queuedRefresh){queuedRefresh=false;window.visitInboxRefresh(true);}}
 };
 function date(value){if(!value)return '미정';return value.replaceAll('-','.');}
 const statusLabels={changed:'날짜 변경',completed:'완료됨',cancelled:'취소됨',deleted:'삭제됨',review:'확인 필요'};
 function render(){
  const list=q('.vin-list');list.replaceChildren();
  if(detailItem){renderDetail(list);badges(unread);return;}
  if(!items.length)list.append(el('p','표시할 알림이 없습니다.','vin-empty'));
  for(const item of items){
   const b=el('button',undefined,'vin-item'+(item.read?' is-read':' is-unread'));b.type='button';b.disabled=busy;
   const top=el('div',undefined,'vin-item-top');top.append(el('strong',item.name),el('span',item.read?'확인':'미확인','vin-read-label'));b.append(top,el('span',item.label,'vin-item-label'));
   if(item.kind==='digest')b.append(el('small',item.description,'vin-date'));
   else if(item.kind==='connection')b.append(el('small',item.description||'가입·구독·연결 알림','vin-date'));
   else if(item.kind==='test')b.append(el('small','테스트 알림입니다.','vin-date'));
   else b.append(el('small',item.status==='changed'?'당시 '+date(item.original_due)+' → 현재 '+date(item.due):'일정 '+date(item.due||item.original_due),'vin-date'));
   const bottom=el('div',undefined,'vin-item-bottom');bottom.append(el('time',new Intl.DateTimeFormat('ko-KR',{month:'numeric',day:'numeric',hour:'2-digit',minute:'2-digit',timeZone:'Asia/Seoul'}).format(new Date(item.created*1000))));
   if(statusLabels[item.status])bottom.append(el('span',statusLabels[item.status],'vin-state '+item.status));
   if(item.delivery==='failed')bottom.append(el('span','푸시 전송 실패 · 알림함에 보관','vin-delivery'));
   b.append(bottom);b.onclick=()=>openItem(item);
   const row=el('div',undefined,'vin-row'),remove=el('button','삭제','vin-delete');remove.type='button';remove.disabled=busy;remove.setAttribute('aria-label',item.name+' · '+item.label+' 알림 삭제');remove.onclick=()=>deleteItem(item);row.append(b,remove);list.append(row);
  }
  q('.vin-more').hidden=next===null;q('.vin-more').disabled=busy;badges(unread);
 }
 async function load(offset=0){
  const id=++requestId;q('.vin-error').textContent='';q('.vin-loading').hidden=false;
  try{const d=await request(null,offset);if(id!==requestId||!dialog.open)return;csrf=d.csrf;groups=d.groups||{};revision=d.revision||'';next=d.next;items=offset?[...new Map([...items,...d.items].map(r=>[r.key,r])).values()]:d.items;badges(d.unread);q('.vin-info').textContent='최근 90일 · '+d.total+'건 · 항목을 누르면 확인 처리됩니다.';render();}
  catch(e){if(id===requestId)q('.vin-error').textContent=e.name==='AbortError'?'연결이 지연됩니다. 새로고침을 눌러 주세요.':e.message;}
  finally{if(id===requestId)q('.vin-loading').hidden=true;}
 }
 function renderDetail(list){
  const item=detailItem;
  const back=el('button','‹ 알림 목록','vin-detail-back');back.type='button';back.onclick=()=>{detailItem=null;load();};
  const card=el('article',undefined,'vin-detail');
  card.append(el('small',item.kind==='connection'?'가입·구독·연결':'업무 알림'),el('h3',item.name),el('strong',item.label),el('p',item.description||({deleted:'삭제된 일정입니다.',cancelled:'취소된 일정입니다.'}[item.status])||'알림 내용을 확인했습니다.'));
  if(item.created)card.append(el('time',new Intl.DateTimeFormat('ko-KR',{dateStyle:'medium',timeStyle:'short',timeZone:'Asia/Seoul'}).format(new Date(item.created*1000))));
  list.append(back,card);q('.vin-more').hidden=true;q('.vin-info').textContent='알림 상세';
 }
 async function currentItem(id){
  const r=await fetch('/visit_push_api.php?inbox_item='+encodeURIComponent(id),{credentials:'same-origin',cache:'no-store'}),d=await r.json();
  if(!r.ok||!d.ok)throw Error(d.error||'알림을 열지 못했습니다.');csrf=d.csrf;return d.item;
 }
 async function openItem(item,direct=false){
  if(busy)return;if(!dialog)create();busy=true;if(!direct)render();q('.vin-error').textContent='';
  try{item=await currentItem(item.id);await request({act:'inbox_read',id:item.id});item.read=true;announce();window.visitInboxRefresh(true);if(item.action_url){trigger=null;dialog.close();const a=el('a');a.href=item.action_url;a.dataset.addressOpen='1';a.hidden=true;document.body.append(a);a.click();a.remove();}else if(item.event&&item.event!=='test'){trigger=null;dialog.close();document.dispatchEvent(new CustomEvent('manager-visit-detail-open',{detail:{key:item.event}}));}else{detailItem=item;if(!dialog.open)showInbox();render();q('.vin-list').scrollTop=0;}}
  catch(e){if(direct&&!dialog.open){items=[];detailItem=null;showInbox();}q('.vin-error').textContent=e.message;}finally{busy=false;if(dialog.open)render();}
 }
 function announce(){channel?.postMessage({changed:Date.now()});try{localStorage.setItem('partner-inbox-changed',String(Date.now())+Math.random());}catch{}}
 async function deleteItem(item){
  if(busy)return;busy=true;render();q('.vin-error').textContent='';
  try{await request({act:'inbox_delete',id:item.id});announce();await load();}
  catch(e){q('.vin-error').textContent=e.message;}finally{busy=false;if(dialog.open)render();}
 }
 function create(){
  dialog=el('dialog',undefined,'vin-dialog');dialog.setAttribute('aria-labelledby','vin-title');
  dialog.innerHTML='<header><div><small>파트너센터 · 업무모드</small><h2 id="vin-title">알림함 <span class="vin-unread"></span></h2></div><button type="button" class="vin-close" aria-label="알림함 닫기">×</button></header><nav class="vin-groups" aria-label="알림 분류"><button type="button" data-vin-group="all" aria-pressed="true">전체 <b hidden></b></button><button type="button" data-vin-group="visit" aria-pressed="false">방문일정 <b hidden></b></button><button type="button" data-vin-group="connection" aria-pressed="false">가입·구독·연결 <b hidden></b></button></nav><div class="vin-actions"><button type="button" class="vin-read-all">모두 확인</button><button type="button" class="vin-refresh">새로고침</button><button type="button" class="vin-settings">알림 설정</button></div><p class="vin-info">최근 90일 알림</p><div class="vin-status"><p class="vin-loading" role="status" hidden>알림을 불러오는 중…</p><p class="vin-error" role="status" aria-live="polite"></p></div><div class="vin-list"></div><button type="button" class="vin-more" hidden>더 보기</button>';
  document.body.append(dialog);q('.vin-close').onclick=()=>{if(!busy)dialog.close();};dialog.addEventListener('cancel',e=>{if(busy)e.preventDefault();});dialog.addEventListener('close',()=>{requestId++;trigger?.focus({preventScroll:true});});
  dialog.querySelectorAll('[data-vin-group]').forEach(b=>b.onclick=()=>{if(busy||group===b.dataset.vinGroup)return;group=b.dataset.vinGroup;detailItem=null;items=[];next=null;q('.vin-list').scrollTop=0;render();load();});
  q('.vin-more').onclick=()=>load(next||0);q('.vin-refresh').onclick=async()=>{if(busy)return;try{if(detailItem){detailItem=await currentItem(detailItem.id);render();}else await load();}catch(e){q('.vin-error').textContent=e.message;}};q('.vin-settings').onclick=()=>{if(busy)return;dialog.close();window.visitPushOpen?.(trigger);};
  q('.vin-read-all').onclick=async()=>{if(busy)return;busy=true;render();try{await request({act:'inbox_read_all',group});announce();await load();}catch(e){q('.vin-error').textContent=e.message;}finally{busy=false;render();}};
 }
 function positionInlineInbox(){
  if(!dialog?.classList.contains('vin-inline'))return;
  const planner=document.querySelector('dialog.mvc-planner[open]');
  const nav=(planner||document).querySelector(planner?'.mvc-mobile-tabs.cm-common-nav':'.cm-bottom-nav');
  const navRect=nav?.getBoundingClientRect();
  const top=planner?Math.max(0,planner.getBoundingClientRect().top):Math.max(0,document.querySelector('body>.nav')?.getBoundingClientRect().bottom||0);
  const bottom=navRect?.height?navRect.top:window.innerHeight-80;
  dialog.style.setProperty('--vin-screen-top',top+'px');
  dialog.style.setProperty('--vin-screen-height',Math.max(0,bottom-top)+'px');
 }
 function showInbox(){
  const inline=matchMedia('(max-width:760px), (pointer:coarse) and (max-height:500px)').matches;
  dialog.classList.toggle('vin-inline',inline);
  const parent=inline?document.querySelector('dialog.mvc-planner[open]')||document.body:document.body;
  if(dialog.parentNode!==parent)parent.append(dialog);
  q('.vin-close').textContent=inline?'‹':'×';
  q('.vin-close').setAttribute('aria-label',inline?'알림에서 돌아가기':'알림함 닫기');
  if(inline){positionInlineInbox();dialog.show();}else dialog.showModal();
 }
 window.addEventListener('resize',positionInlineInbox);
 window.visitInboxOpen=async button=>{
  if(demo())return;if(!dialog)create();if(dialog.open)return;trigger=button;group=button?.closest('.cm-common-nav')?'all':button?.classList.contains('mvc-inbox-open')||button?.classList.contains('mvc-inbox-mobile')?'visit':'connection';detailItem=null;items=[];next=null;showInbox();render();await load();
 };
 const noticeLabel='<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a6 6 0 0 0-6 6v5l-2 3h16l-2-3V9a6 6 0 0 0-6-6Zm-2 17h4"/></svg>';
 document.querySelectorAll('[data-ms-open="notifications"]').forEach(b=>{b.dataset.visitInboxButton='1';b.setAttribute('aria-label','알림함');b.innerHTML=noticeLabel+'<b class="vin-badge" hidden></b>';});
 document.addEventListener('click',e=>{
  const menu=e.target.closest('.cm-common-nav a,.cm-common-nav button');
  if(menu&&!menu.disabled&&!menu.matches('[data-visit-inbox-button]')&&dialog?.open&&dialog.classList.contains('vin-inline')){
   if(busy){e.preventDefault();e.stopImmediatePropagation();return;}
   trigger=null;dialog.close();
  }
  const b=e.target.closest('[data-visit-inbox-button]');if(b&&!b.disabled){e.preventDefault();e.stopImmediatePropagation();window.visitInboxOpen(b);}},true);
 channel?.addEventListener('message',()=>window.visitInboxRefresh(true));
 window.addEventListener('storage',e=>{if(e.key==='partner-inbox-changed')window.visitInboxRefresh(true);});
 navigator.serviceWorker?.addEventListener('message',e=>{if(e.data?.type==='VISIT_INBOX_CHANGED')window.visitInboxRefresh(true);});
 document.addEventListener('manager-buildings-updated',()=>window.visitInboxRefresh());
 setInterval(()=>{if(!document.hidden)window.visitInboxRefresh(true);},15000);
 window.addEventListener('focus',()=>window.visitInboxRefresh(true));
 window.visitInboxRefresh(true);
 const inboxParams=new URLSearchParams(location.search),deepItem=inboxParams.get('notification_item')||inboxParams.get('inbox_item');
 // Wait for the planner/address/digest handlers, then open the target without fetching an inbox page.
 if(deepItem){const start=()=>{if(!demo())openItem({id:deepItem},true);};if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});else queueMicrotask(start);}
 else if(inboxParams.get('inbox_group')==='connection')queueMicrotask(()=>window.visitInboxOpen(document.querySelector('[data-ms-tab=notifications]')||document.querySelector('[data-visit-inbox-button]')));
 document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible')window.visitInboxRefresh(true);});
})();
