(()=>{
 const panel=document.getElementById('ms-users');if(!panel)return;
 const originals=new Map([...panel.querySelectorAll('[data-ms-uid]')].map(e=>[e.dataset.msUid,e]));
 const old=document.createElement('div');old.hidden=true;old.id='mb-legacy';while(panel.firstChild)old.append(panel.firstChild);panel.append(old);
 const root=document.createElement('section');root.className='mb-workspace';panel.append(root);
 root.innerHTML='<div class="mb-filters" aria-label="건물 상태"></div><label class="mb-search"><span class="ms-sr">건물명 또는 주소 검색</span><input type="search" placeholder="건물명 · 주소 검색"></label><div class="mb-summary"><span role="status"></span><button type="button" hidden>전체 목록으로</button></div><div class="mb-list"></div>';
 const filters=root.querySelector('.mb-filters'),input=root.querySelector('input'),summary=root.querySelector('[role=status]'),back=root.querySelector('.mb-summary button'),list=root.querySelector('.mb-list');
 const filterHost=document.getElementById('manager-map-filters');if(filterHost){
   filterHost.appendChild(filters);
   if(typeof L!=='undefined'&&typeof map!=='undefined'&&map){
     const filterControl=L.control({position:'topright'});
     filterControl.onAdd=()=>{L.DomEvent.disableClickPropagation(filterHost);L.DomEvent.disableScrollPropagation(filterHost);return filterHost;};
     filterControl.addTo(map);
   }
 }
 const labels={all:'전체',pre:'사전등록',linked:'매니저 연결',pro:'PRO 이용'};let rows=[],status='all',selected=null,month='all';
 const kind=r=>r.preregistered?'pre':r.subscription?.active?'pro':'linked';
 function matching(r){return(status==='all'||kind(r)===status)&&(month==='all'||(month==='unknown'?!r.approval_month:String(r.approval_month)===month))&&[r.name,r.address].join(' ').toLocaleLowerCase().includes(input.value.trim().toLocaleLowerCase());}
 function emit(fit=false){document.dispatchEvent(new CustomEvent('manager-building-filter',{detail:{fit,selected: selected,uids:rows.filter(r=>matching(r)).map(r=>r.uid)}}));}
 function scrollToMap(animate=true){
   requestAnimationFrame(()=>{
     const target=document.querySelector('.main-cols')||document.querySelector('.focus-map');if(!target)return;
     let topInset=12;
     // Measure visible top bars rather than assuming one desktop header height.
     document.querySelectorAll('nav.nav,header,.new-header,[role="banner"]').forEach(el=>{
       const style=getComputedStyle(el),rect=el.getBoundingClientRect();
       const pinnedTop=parseFloat(style.top);
       if((style.position==='fixed'||style.position==='sticky')&&style.display!=='none'&&style.visibility!=='hidden'&&rect.height>0&&Number.isFinite(pinnedTop)&&pinnedTop>=0&&pinnedTop<window.innerHeight/2){
         topInset=Math.max(topInset,pinnedTop+rect.height+12);
       }
     });
     const rect=target.getBoundingClientRect();
     const top=Math.max(0,window.scrollY+rect.top-topInset);
     if(Math.abs(window.scrollY-top)<3)return;
     window.scrollTo({top,behavior:!animate||matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});
   });
 }
 function choose(uid,fromMap=false){selected=uid;render();if(!fromMap)document.dispatchEvent(new CustomEvent('manager-map-focus',{detail:{uid}}));scrollToMap();}

 function deleteDraft(r,trigger){
   const dialog=document.createElement('dialog');dialog.className='mb-delete-dialog';
   dialog.innerHTML='<form method="dialog"><h2>사전등록을 삭제할까요?</h2><strong class="mb-delete-name"></strong><p>목록과 지도에서 제외됩니다. 등록 자료는 복구 가능한 삭제 상태로 보관됩니다.</p><p class="mb-delete-error" role="alert" hidden></p><div class="mb-delete-actions"><button type="button" class="mb-delete-cancel">취소</button><button type="button" class="mb-delete-confirm">사전등록 삭제</button></div></form>';
   dialog.querySelector('.mb-delete-name').textContent=r.name||'이름 미입력';
   const cancel=dialog.querySelector('.mb-delete-cancel'),confirm=dialog.querySelector('.mb-delete-confirm'),error=dialog.querySelector('.mb-delete-error');let busy=false;
   cancel.onclick=()=>dialog.close();
   dialog.addEventListener('cancel',e=>{if(busy)e.preventDefault();});
   dialog.addEventListener('close',()=>{dialog.remove();trigger.focus();});
   confirm.onclick=async()=>{
     if(busy)return;
     busy=true;cancel.disabled=true;confirm.disabled=true;confirm.textContent='삭제 중…';error.hidden=true;
     try{
       const csrf=typeof CSRF!=='undefined'?CSRF:document.querySelector('input[name="csrf"]')?.value;
       if(!csrf)throw new Error('새로고침한 후 다시 시도해 주세요.');
       const body=new URLSearchParams({act:'delete',id:r.address_id,csrf});
       const response=await fetch('/manager_addresses.php',{method:'POST',credentials:'same-origin',body});
       const result=await response.json();
       if(!response.ok||!result.ok)throw new Error(result.error||'삭제하지 못했습니다. 다시 시도해 주세요.');
       // Reload server-owned data so counts, list, map and any open detail agree.
       location.reload();
     }catch(e){
       error.textContent=e instanceof SyntaxError?'응답을 확인하지 못했습니다. 새로고침 후 목록을 확인해 주세요.':e.message||'연결을 확인한 후 다시 시도해 주세요.';error.hidden=false;
       busy=false;cancel.disabled=false;confirm.disabled=false;confirm.textContent='사전등록 삭제';
     }
   };
   document.body.append(dialog);dialog.showModal();cancel.focus();
 }
 function draftMenu(r){
   const menu=document.createElement('details');menu.className='mb-draft-menu';
   const toggle=document.createElement('summary');toggle.textContent='⋯';toggle.setAttribute('aria-label',(r.name||'사전등록 건물')+' 더보기');
   const del=document.createElement('button');del.type='button';del.textContent='사전등록 삭제';
   del.onclick=()=>{menu.open=false;deleteDraft(r,toggle);};
   menu.append(toggle,del);return menu;
 }
 function render(fit=false){
 filters.replaceChildren();Object.keys(labels).forEach(k=>{const b=document.createElement('button');b.type='button';const label=document.createElement('span');label.className='mb-filter-label';
if(k!=='all'){const icon=document.createElement('span');icon.className='mb-status-symbol mb-symbol-'+k;icon.textContent=k==='pre'?'P':k==='pro'?'S':'C';icon.setAttribute('aria-hidden','true');label.append(icon);}
const caption=document.createElement('span');caption.textContent=labels[k];label.append(caption);
const count=document.createElement('span');count.className='mb-filter-count';count.textContent=String(rows.filter(r=>k==='all'||kind(r)===k).length);b.append(label,count);b.setAttribute('aria-pressed',String(k===status));b.onclick=()=>{status=k;selected=null;render(true);scrollToMap();};filters.append(b);});
 const visible=rows.filter(r=>matching(r)&&(!selected||r.uid===selected));summary.textContent=visible.length+'개 건물';back.hidden=!selected;list.replaceChildren();
 if(!visible.length){const e=document.createElement('p');e.className='ms-empty';e.textContent='조건에 맞는 건물이 없습니다.';list.append(e);}
 visible.forEach(r=>{const card=document.createElement('article');card.className='mb-card';const select=document.createElement('button');select.type='button';select.className='mb-select';const name=document.createElement('strong');name.textContent=r.name||'이름 미입력';const badge=document.createElement('span');badge.className='mb-badge mb-'+kind(r);{const symbol=document.createElement('span');symbol.className='mb-status-symbol mb-symbol-'+kind(r);symbol.textContent=kind(r)==='pre'?'P':kind(r)==='pro'?'S':'C';symbol.setAttribute('aria-hidden','true');badge.append(symbol);}badge.append(document.createTextNode(labels[kind(r)]));badge.title=kind(r)==='linked'?'매니저 연결 완료 · 현재 PRO 구독 없음':labels[kind(r)];select.append(name,badge);select.onclick=()=>choose(r.uid);const addr=document.createElement('p');addr.textContent=r.address||'주소 미입력';card.append(select,addr);if(kind(r)==='linked'){const note=document.createElement('small');note.className='mb-unsubscribed';note.textContent='매니저 연결 완료 · PRO 미구독';card.append(note);}
 const actions=document.createElement('div');actions.className='ms-actions';if(r.preregistered){const a=document.createElement('a');a.className='ms-button';a.href='/manager_addresses.php?id='+encodeURIComponent(r.address_id);a.dataset.addressOpen='1';a.textContent='기본정보 수정';actions.append(a);}else{const source=originals.get(r.uid)?.querySelector('.ms-actions');if(source){const clone=source.cloneNode(true);clone.querySelectorAll('[data-ms-map]').forEach(b=>b.remove());actions.append(...clone.childNodes);}else{const a=document.createElement('a');a.className='ms-button';a.href='/manager_view.php?uid='+encodeURIComponent(r.uid);a.target='_blank';a.rel='noopener';a.textContent='유저 화면';actions.append(a);}}
 const mapBtn=document.createElement('button');mapBtn.className='ms-button';mapBtn.type='button';mapBtn.textContent='지도 보기';mapBtn.onclick=()=>choose(r.uid);actions.append(mapBtn);if(r.preregistered)actions.append(draftMenu(r));card.append(actions);list.append(card);});emit(fit);
 }
 document.addEventListener('manager-building-clear-selection',()=>{selected=null;render(true);});
 input.oninput=()=>{selected=null;render();};back.onclick=()=>{selected=null;render(true);scrollToMap();};
 document.addEventListener('manager-buildings-updated',e=>{rows=e.detail.buildings||[];if(!rows.some(r=>r.uid===selected))selected=null;render();});
 document.addEventListener('manager-building-selected',e=>{const r=rows.find(r=>r.uid===e.detail.uid);if(!r)return;if(!matching(r)){status='all';input.value='';}document.getElementById('ms-tab-users')?.click();choose(r.uid,true);});
 document.addEventListener('manager-month-filter',e=>{month=String(e.detail.month||'all');selected=null;render();});
 document.addEventListener('manager-buildings-unavailable',()=>{rows=[];selected=null;render();summary.textContent='목록을 불러오지 못했습니다. 새로고침해 주세요.';});
 summary.textContent='건물 목록을 불러오고 있습니다.';
 // Start with the same all-buildings framing and workspace alignment as “전체”.
 // Run only on entry; periodic server refreshes must not pull the page back.
 function alignInitialWorkspace(){render(true);scrollToMap(false);}
 if(document.readyState==='complete')alignInitialWorkspace();
 else window.addEventListener('load',alignInitialWorkspace,{once:true});

})();
