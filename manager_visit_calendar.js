(()=>{
 const host=document.querySelector('.mm-management-actions');if(!host)return;
 const el=(tag,text,cls)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=text;if(cls)n.className=cls;return n;};
 const dayKey=d=>`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
 const today=()=>dayKey(new Date());let month=today().slice(0,7),selected=today(),rows=[],dayMeta={},daysReady=false,buildings=[],loading=false,busy=false,request=0,trigger=null;
 let followupRows={},followupEvents=[];
 let reportRows=[],linkedReports={},legalHolidays={},holidayStale=false,reportNotices=[],reportOverdue=0;
 const completionSelection=new Set();let completionDate='',completionActual=today();
 let demoMode=false,buildingsReady=false,demoRows=[],demoDays={},demoSequence=0;
 const walkthroughDemo=location.pathname.startsWith('/partner_demo_app.php/')&&document.body.hasAttribute('data-tour-result-page')&&Array.isArray(window.partnerWalkthroughBuildings);
 const demoBuildings=walkthroughDemo?window.partnerWalkthroughBuildings:[
  {uid:'demo_visit_1',name:'[예시] 햇살빌딩',address:'방문일정 체험용 가상 거래처',lat:37.658,lng:126.768,approval_month:Number(today().slice(5,7)),approval_date:'2020-'+today().slice(5,7)+'-15'},
  {uid:'demo_visit_2',name:'[예시] 푸른상가',address:'방문일정 체험용 가상 거래처',lat:37.666,lng:126.779},
  {uid:'demo_visit_3',name:'[예시] 늘봄센터',address:'방문일정 체험용 가상 거래처',lat:37.651,lng:126.785}
 ];
 async function visitFetch(url,options={}){
  if(!demoMode){
   const body=options.body;
   if(body&&[body.get('uid')||'',body.get('uids')||''].some(v=>v.includes('demo_visit_')))throw Error('예시 거래처는 실제 일정에 저장할 수 없습니다.');
   return fetch(url,options);
  }
  const reply=data=>({ok:true,json:async()=>({ok:true,...data})});
  if(options.method!=='POST'){
   if(new URL(url,location.origin).searchParams.has('review_visits')){const items=demoRows.filter(r=>r.status==='planned'&&r.date<today()).sort((a,b)=>a.date.localeCompare(b.date));return reply({items,total:items.length});}
   const m=new URL(url,location.origin).searchParams.get('month');
   return reply({visits:demoRows.filter(r=>r.date.startsWith(m)),days:Object.fromEntries(Object.entries(demoDays).filter(([date])=>date.startsWith(m)))});
  }
  const b=options.body,act=b.get('act'),date=b.get('date');
  if(act==='save_day'){const day={holiday:b.get('holiday')==='1',memo:b.get('memo')||'',revision:(demoDays[date]?.revision||0)+1};demoDays[date]=day;return reply({day});}
  if(act==='confirm_visits'){
   const refs=JSON.parse(b.get('visits')||'[]');
   for(const ref of refs){const row=demoRows.find(r=>r.id===ref.id);if(!row||row.revision!==ref.revision)throw Error('변경된 예시 일정을 다시 확인해 주세요.');}
   refs.forEach(ref=>{const row=demoRows.find(r=>r.id===ref.id);row.status=b.get('status');row.visited_date=row.status==='completed'?b.get('visited_date'):'';row.revision++;});return reply({updated:refs.length});
  }
  if(act==='undo_batch'){const before=demoRows.length;demoRows=demoRows.filter(r=>r.batch!==b.get('batch_token'));return reply({undone:before-demoRows.length});}
  function make(uid){const building=demoBuildings.find(item=>item.uid===uid);if(!building)throw Error('예시 거래처를 선택해 주세요.');return {id:'demo_record_'+(++demoSequence),revision:1,uid,name:building.name,address:building.address,date,time:'',status:'planned',visited_date:'',memo:b.get('memo')||''};}
  if(act==='batch'){
   const token=b.get('batch_token');let created=0,skipped=0;
   for(const uid of JSON.parse(b.get('uids')||'[]')){
    if(demoRows.some(r=>r.uid===uid&&r.date===date&&!['cancelled','missed'].includes(r.status))){skipped++;continue;}
    demoRows.push({...make(uid),batch:token});created++;
   }
   return reply({created,skipped,undo_token:token});
  }
  const id=b.get('id'),old=demoRows.find(r=>r.id===id);
  const visit=old?{...old,revision:old.revision+1}:make(b.get('uid'));
  for(const key of ['date','time','status','visited_date','memo'])visit[key]=b.get(key)||'';
  if(old)demoRows[demoRows.indexOf(old)]=visit;else demoRows.push(visit);
  return reply({visit});
 }
 const pendingMemoGlow=new Set();
 const opener=el('button','업무모드','mvc-open');opener.type='button';opener.setAttribute('aria-haspopup','dialog');host.prepend(opener);
 const dialog=el('dialog',undefined,'mvc-dialog mvc-planner');dialog.setAttribute('aria-labelledby','mvc-title');
 dialog.innerHTML='<header class="mvc-header"><div><small>WORK MODE</small><h2 id="mvc-title">업무모드</h2><p>거래처를 선택하고 달력 날짜로 끌어 놓으세요. 날짜를 눌러 등록할 수도 있습니다.</p></div><button type="button" class="mvc-close" aria-label="업무모드 닫기">×</button></header><div class="mvc-toolbar"><div><button type="button" data-shift="-1" aria-label="이전 달">‹</button><strong class="mvc-month"></strong><button type="button" data-shift="1" aria-label="다음 달">›</button><button type="button" class="mvc-today">오늘</button></div><span>● 방문 <i>● 완료</i> <b class="mvc-report-key">◆ 보고서 접수</b> <b class="mvc-work-key">▣ 공사</b> <b class="mvc-filing-key">▤ 이행보고</b></span></div><p class="mvc-notice" role="status"></p><div class="mvc-layout"><section><div class="mvc-week"></div><div class="mvc-grid" aria-label="월 방문 달력"></div></section><aside class="mvc-side"><div class="mvc-dayhead"><h3></h3><button type="button" class="mvc-add">＋ 일정</button></div><div class="mvc-daylist"></div><form class="mvc-form" hidden><h3 class="mvc-formtitle">방문 일정 추가</h3><label>거래처<select name="uid" required></select></label><div class="mvc-fields"><label>방문 예정일<input type="date" name="date" required></label><label>예정 시간<input type="time" name="time"></label></div><label>상태<select name="status"><option value="planned">방문 예정</option><option value="completed">방문 완료</option><option value="missed">미방문 · 등록 해제</option><option value="cancelled">일정 취소</option></select></label><label class="mvc-actual" hidden>실제 방문일<input type="date" name="visited_date"></label><label>실제 점검 종료일 <small>점검한 경우에만 입력</small><input type="date" name="inspection_end"></label><label>방문 메모<textarea name="memo" rows="3" maxlength="2000" placeholder="방문 목적이나 확인한 내용을 남겨 주세요."></textarea></label><p class="mvc-error" role="alert"></p><div class="mvc-formactions"><button type="button" class="mvc-edit-close">닫기</button><button type="submit" class="mvc-save">저장</button></div></form></aside></div>';
 document.body.append(dialog);
 const loadStatus=el('span','','mvc-load-status');loadStatus.setAttribute('role','status');dialog.append(loadStatus);
 const loadIndicator=el('span',undefined,'mvc-load-indicator');loadIndicator.setAttribute('aria-hidden','true');dialog.querySelector('.mvc-header').append(loadIndicator);

 const pushButton=el('button','알림 받기','mvc-push-open');pushButton.type='button';pushButton.setAttribute('aria-haspopup','dialog');dialog.querySelector('.mvc-header>div').after(pushButton);pushButton.onclick=()=>window.visitPushOpen?.(pushButton);
 const inboxButton=el('button',undefined,'mvc-inbox-open');inboxButton.type='button';inboxButton.dataset.visitInboxButton='1';inboxButton.setAttribute('aria-haspopup','dialog');inboxButton.innerHTML='<span class="vin-label">알림</span><b class="vin-badge" hidden></b>';inboxButton.querySelector('b').hidden=true;dialog.querySelector('.mvc-header>div').after(inboxButton);inboxButton.onclick=()=>window.visitInboxOpen?.(inboxButton);

 const demoBanner=el('div',undefined,'mvc-demo-banner');demoBanner.hidden=true;demoBanner.innerHTML='<strong>예시 거래처로 방문일정을 체험해 보세요</strong><span>가상 거래처 3곳입니다. 햇살빌딩은 이번 달 사용승인월 표시 예시입니다. 일정·메모는 서버에 저장되지 않으며 새로고침하면 사라집니다.</span>';dialog.querySelector('.mvc-toolbar').before(demoBanner);if(walkthroughDemo)demoBanner.querySelector('span').textContent='사전등록한 건물을 포함한 가상 거래처 40곳입니다. 일정·메모는 서버에 저장되지 않습니다.';
 const mobileExample=el('span','예시 체험','mvc-mobile-example');mobileExample.title='가상 거래처입니다. 실제 일정에 저장되지 않습니다.';dialog.querySelector('.mvc-header>div').append(mobileExample);
 function applyBuildings(list){
  if(!Array.isArray(list))return;
  if(busy){setTimeout(()=>applyBuildings(list),150);return;}
  buildingsReady=true;const nextDemo=walkthroughDemo||list.length===0,changed=demoMode!==nextDemo;
  pushButton.disabled=mobilePushButton.disabled=inboxButton.disabled=mobileInboxButton.disabled=taskButton.disabled=mobileTaskButton.disabled=walkthroughDemo;pushButton.title=mobilePushButton.title=walkthroughDemo?'예시 체험에서는 실제 알림을 신청하지 않습니다.':'이 기기의 일정 알림 설정';
  demoMode=nextDemo;buildings=nextDemo?demoBuildings.map(b=>({...b})):list;demoBanner.hidden=!nextDemo;dialog.classList.toggle('is-demo',nextDemo);
  for(const uid of chosen)if(!buildings.some(b=>b.uid===uid))chosen.delete(uid);
  if(changed){request++;rows=[];dayMeta={};daysReady=false;demoRows=[];demoDays={};form.hidden=true;if(dayDialog.open)dayDialog.close();}
  if(changed){followupRows={};followupEvents=[];reportRows=[];linkedReports={};legalHolidays={};reportNotices=[];reportOverdue=0;}
  if(dialog.open){ensureVisitMap();render();if(changed)load();}
 }

 const dayDialog=el('dialog',undefined,'mvc-dialog mvc-day-dialog');dayDialog.setAttribute('aria-labelledby','mvc-day-title');
 dayDialog.innerHTML='<header class="mvc-header"><div><small>VISIT DETAILS</small><div class="mvc-day-title-row"><h2 id="mvc-day-title">방문 일정 관리</h2><label class="mvc-holiday-toggle"><input type="checkbox" class="mvc-day-holiday"><span>휴일 표시</span></label></div></div><button type="button" class="mvc-day-close" aria-label="날짜별 일정 닫기">×</button></header>';
 dayDialog.append(dialog.querySelector('.mvc-side'));document.body.append(dayDialog);
 const q=s=>dialog.querySelector(s)||dayDialog.querySelector(s),form=q('form');let editing=null;
 const batch=el('section',undefined,'mvc-batch');batch.innerHTML='<div class="mvc-selected-names"></div><textarea class="mvc-batch-memo" maxlength="2000" rows="2" placeholder="선택한 거래처에 남길 메모 (선택)" aria-label="공통 방문 메모"></textarea><button type="button" class="mvc-batch-save">방문 등록</button>';
 q('.mvc-daylist').before(batch);const result=el('p','','mvc-batch-result');result.setAttribute('role','status');batch.after(result);
 const dayNotes=el('section',undefined,'mvc-day-notes');
 dayNotes.innerHTML='<label for="mvc-day-memo">날짜 메모 <small>이 날짜에 남기는 메모</small></label><textarea id="mvc-day-memo" rows="2" maxlength="2000" placeholder="휴무 사유나 오늘 기억할 내용을 남겨 주세요."></textarea><div class="mvc-day-note-actions"><span class="mvc-day-note-status" role="status" aria-live="polite"></span><button type="button" class="mvc-day-note-save">날짜 설정 저장</button></div>';
 q('.mvc-dayhead').after(dayNotes);
 // Common phrases append to the existing memo without replacing typed client names.
 for(const textarea of [q('#mvc-day-memo'),q('.mvc-batch-memo'),form.querySelector('textarea[name="memo"]')]){
  const shortcuts=el('div',undefined,'mvc-memo-shortcuts'),hint=el('span','','mvc-memo-shortcut-hint');hint.setAttribute('role','status');
  shortcuts.setAttribute('role','group');shortcuts.setAttribute('aria-label','메모 문구 추가');
  for(const phrase of ['보고서 접수','이행완료 접수']){
   const button=el('button','＋ '+phrase);button.type='button';button.setAttribute('aria-label',phrase+' 문구를 메모 끝에 추가');
   button.onclick=()=>{
    if(busy||loading||textarea.disabled)return;
    const addition=(textarea.value&&!/\s$/.test(textarea.value)?' ':'')+phrase;
    if(textarea.maxLength>=0&&textarea.value.length+addition.length>textarea.maxLength){hint.textContent='메모가 길어 문구를 추가할 수 없습니다.';return;}
    textarea.value+=addition;hint.textContent='';textarea.dispatchEvent(new Event('input',{bubbles:true}));
    textarea.focus({preventScroll:true});textarea.setSelectionRange(textarea.value.length,textarea.value.length);textarea.scrollTop=textarea.scrollHeight;
   };
   shortcuts.append(button);
  }
  shortcuts.append(hint);
  const label=textarea.closest('label');if(label)label.after(shortcuts);else textarea.after(shortcuts);
  textarea.addEventListener('input',()=>{hint.textContent='';});
 }

 let daySnapshot={date:'',holiday:false,memo:'',revision:0};
 const dayDirty=()=>daySnapshot.date!==''&&(q('.mvc-day-holiday').checked!==daySnapshot.holiday||q('#mvc-day-memo').value!==daySnapshot.memo);
 function fillDayNotes(){
  const saved=dayMeta[selected]||{};daySnapshot={date:selected,holiday:!!saved.holiday,memo:saved.memo||'',revision:saved.revision||0};
  q('.mvc-day-holiday').checked=daySnapshot.holiday;q('#mvc-day-memo').value=daySnapshot.memo;q('.mvc-day-note-status').textContent='';syncDayNotes();
 }
 function syncDayNotes(){
  q('.mvc-day-note-save').disabled=busy||loading||!daysReady||!dayDirty();
  q('.mvc-day-holiday').disabled=q('#mvc-day-memo').disabled=busy||loading||!daysReady;
 }
 function canCloseDay(){return !busy&&(!dayDirty()||confirm('저장하지 않은 날짜 설정이 있습니다. 변경 내용을 버리고 닫을까요?'));}
 for(const control of [q('.mvc-day-holiday'),q('#mvc-day-memo')])control.addEventListener('input',()=>{q('.mvc-day-note-status').textContent=dayDirty()?'저장 전 변경사항':'';syncDayNotes();});
 q('.mvc-day-note-save').onclick=async()=>{
  if(busy||loading||!daysReady||!dayDirty())return;
  const date=daySnapshot.date,body=new URLSearchParams({act:'save_day',date,holiday:q('.mvc-day-holiday').checked?'1':'0',memo:q('#mvc-day-memo').value,revision:String(daySnapshot.revision),csrf:typeof CSRF!=='undefined'?CSRF:''});
  busy=true;render();q('.mvc-day-note-status').textContent='저장 중…';
  try{const res=await visitFetch('/manager_visits.php',{method:'POST',credentials:'same-origin',body});const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.error||'날짜 설정을 저장하지 못했습니다.');dayMeta[date]=data.day;if(data.day.memo)pendingMemoGlow.add(date);else pendingMemoGlow.delete(date);fillDayNotes();q('.mvc-day-note-status').textContent='저장했습니다.';}
  catch(error){q('.mvc-day-note-status').textContent=error.message||'연결을 확인하고 다시 저장해 주세요.';}
  finally{busy=false;render();}
 };
 q('.mvc-day-close').onclick=()=>{if(canCloseDay())dayDialog.close();};
 dayDialog.addEventListener('cancel',e=>{if(!canCloseDay())e.preventDefault();});
 dayDialog.addEventListener('close',()=>{daySnapshot.date='';if(dialog.open)load();if(dialog.open)dialog.querySelector('.mvc-cell.is-selected')?.focus({preventScroll:true});});
 function openDay(){if(!dayDialog.open){fillDayNotes();dayDialog.showModal();}q('.mvc-day-close').focus();q('.mvc-batch-result').textContent='';}

 let approvalOnly=false,unregisteredOnly=false,mapViewMode='names',mapVisitDate='';
 try{if(localStorage.getItem('manager-visit-map-view')==='icons')mapViewMode='icons';}catch{}
 const chosen=new Set(),locationCache=new Map();let visitMap=null,visitMarkers=null,mapReady=false,batchToken=null,batchFingerprint='';
 const left=el('section',undefined,'mvc-map-panel');
 left.innerHTML='<div class="mvc-map-title"><div><small>거래처 지도</small><h3>방문할 곳을 선택하세요</h3></div><div class="mvc-map-actions"><button type="button" class="mvc-unselect" hidden>선택 해제</button><button type="button" class="mvc-fit">전체 보기</button></div></div><div class="mvc-plan-map" aria-label="방문 거래처 선택 지도"></div><div class="mvc-map-legend"><b class="scheduled">● 방문 등록</b><b class="completed">● 방문 완료</b><b class="month">● 이달 다른 날짜</b><b class="chosen">● 선택 중</b></div>';

 const layout=q('.mvc-layout'),right=el('section',undefined,'mvc-calendar-panel');while(layout.firstChild)right.append(layout.firstChild);layout.append(left,right);
 const viewToggle=el('button','Aa','mvc-view-toggle');viewToggle.type='button';
 viewToggle.setAttribute('aria-label','건물 이름 표시');
 function paintMapView(){
  q('.mvc-plan-map').classList.toggle('mvc-icons',mapViewMode==='icons');
  viewToggle.setAttribute('aria-pressed',String(mapViewMode==='names'));
  viewToggle.title=mapViewMode==='names'?'아이콘 보기로 전환':'이름 보기로 전환';
 }
 viewToggle.onclick=()=>{
  mapViewMode=mapViewMode==='names'?'icons':'names';
  try{localStorage.setItem('manager-visit-map-view',mapViewMode);}catch{}
  clearGhost();paintMapView();visitMarkers?.eachLayer(marker=>marker.closeTooltip());
 };
 paintMapView();
 let dragSelection=null,ignoreClickUntil=0;
 const dragHint=el('button','달력으로 끌기','mvc-drag-handle');dragHint.type='button';dragHint.draggable=true;dragHint.hidden=true;q('.mvc-map-actions').prepend(dragHint);
 const mobileMedia=window.matchMedia('(max-width:900px), (pointer:coarse) and (max-height:500px)');
 let mobileView='map';
 const viewTabs=el('nav',undefined,'mvc-view-tabs');viewTabs.setAttribute('aria-label','업무모드 보기');
 const mapTab=el('button','지도'),calendarTab=el('button','달력');mapTab.type=calendarTab.type='button';viewTabs.append(mapTab,calendarTab);q('.mvc-header').after(viewTabs);
 const mobileTabs=el('nav',undefined,'mvc-mobile-tabs cm-common-nav');mobileTabs.setAttribute('aria-label','파트너센터 메뉴');
 // Clone one shared menu definition, keeping each screen's active item distinct.
 const sharedMenu=document.querySelector('.cm-bottom-nav');
 if(sharedMenu)for(const child of sharedMenu.children)mobileTabs.append(child.cloneNode(true));
 else{mobileTabs.classList.remove('cm-common-nav');mobileTabs.append(mapTab,calendarTab);}
 const centerButton=mobileTabs.querySelector('[data-dock-workspace]'),workButton=mobileTabs.querySelector('[data-dock-work]'),memoButton=mobileTabs.querySelector('[data-dock-memo]');
 const mobileInboxButton=mobileTabs.querySelector('[data-visit-inbox-button]')||el('button');
 // Detached compatibility controls retain the desktop/tutorial capability checks.
 const mobilePushButton=el('button'),mobileTaskButton=el('button');
 const taskButton=el('button','할 일','mvc-tasks-open');taskButton.type='button';taskButton.setAttribute('aria-haspopup','dialog');taskButton.onclick=()=>window.workTasksOpen?.(taskButton);q('.mvc-header>div').after(taskButton);
 centerButton?.removeAttribute('aria-current');workButton?.setAttribute('aria-current','location');
 if(centerButton)centerButton.onclick=()=>{if(busy)return;q('.mvc-close').click();};
 if(workButton){workButton.removeAttribute('aria-haspopup');workButton.onclick=()=>{if(busy)return;switchMobileView(mobileView==='map'?'calendar':'map');};}
 const memoToggle=document.querySelector('#manager-quickmemo .mqm-toggle');
 if(memoButton){
  memoButton.dataset.workMemo='1';
  if(memoToggle){memoButton.onclick=()=>memoToggle.click();const syncMemo=()=>memoButton.setAttribute('aria-expanded',memoToggle.getAttribute('aria-expanded')||'false');new MutationObserver(syncMemo).observe(memoToggle,{attributes:true,attributeFilter:['aria-expanded']});syncMemo();}
  else{memoButton.disabled=true;memoButton.title='이 화면에서는 메모를 사용할 수 없습니다.';}
 }
 dialog.append(mobileTabs);

 const mobileFooter=el('div',undefined,'mvc-mobile-footer'),mobileHint=el('p'),mobileNext=el('button');mobileNext.type='button';mobileHint.setAttribute('role','status');mobileFooter.append(mobileHint,mobileNext);dialog.append(mobileFooter);
 function updateMobileView(){
  if(mobileMedia.matches)q('.mvc-toolbar').prepend(viewTabs);else q('.mvc-header').after(viewTabs);
  dialog.dataset.mobileView=mobileView;
  if(workButton){workButton.disabled=busy;workButton.title=mobileView==='map'?'다시 누르면 달력 보기':'다시 누르면 지도 보기';workButton.setAttribute('aria-label','업무모드 · 현재 '+(mobileView==='map'?'지도':'달력')+' · '+workButton.title);}
  mapTab.setAttribute('aria-pressed',String(mobileView==='map'));calendarTab.setAttribute('aria-pressed',String(mobileView==='calendar'));
  mapTab.disabled=calendarTab.disabled=busy;
  mobileHint.textContent=chosen.size?'선택한 거래처 '+chosen.size+'곳':mobileView==='map'?'지도에서 방문할 거래처를 눌러 주세요.':'날짜를 눌러 방문일정과 메모를 확인하세요.';
  mobileNext.textContent=mobileView==='map'?(chosen.size?'선택한 '+chosen.size+'곳 · 날짜 정하기':'거래처를 선택해 주세요'):'지도에서 거래처 '+(chosen.size?'더 선택하기':'선택하기');
  mobileNext.disabled=busy||loading||(mobileView==='map'&&!chosen.size);
  mobileHint.title=buildings.filter(b=>chosen.has(b.uid)).map(b=>b.name).join(' · ');
 }
 function switchMobileView(view){
  mobileView=view;clearGhost();updateMobileView();
  if(view==='map'&&dialog.open)requestAnimationFrame(()=>{if(visitMap)visitMap.invalidateSize({pan:false});else ensureVisitMap();});
 }
 mapTab.onclick=()=>switchMobileView('map');calendarTab.onclick=()=>switchMobileView('calendar');
 mobileNext.onclick=()=>switchMobileView(mobileView==='map'?'calendar':'map');
 mobileMedia.addEventListener('change',()=>{updateMobileView();clearGhost();if(dialog.open)requestAnimationFrame(()=>{if(!mobileMedia.matches||mobileView==='map')ensureVisitMap();});});
 updateMobileView();
 const toast=el('div',undefined,'mvc-toast');toast.hidden=true;toast.setAttribute('role','status');dialog.append(toast);
 function showToast(message,undoToken=null){
  toast.replaceChildren(el('span',message));toast.hidden=false;
  if(undoToken){const undo=el('button','실행 취소');undo.type='button';undo.onclick=async()=>{
    if(busy)return;busy=true;undo.disabled=true;
    try{const res=await visitFetch('/manager_visits.php',{method:'POST',credentials:'same-origin',body:new URLSearchParams({act:'undo_batch',batch_token:undoToken,csrf:typeof CSRF!=='undefined'?CSRF:''})});const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.error||'실행 취소하지 못했습니다.');await load();showToast(data.undone+'곳 방문 등록을 되돌렸습니다.');}
    catch(e){showToast(e.message||'연결 상태를 확인해 주세요.',undoToken);}
    finally{busy=false;render();}
   };toast.append(undo);}
  const close=el('button','×','mvc-toast-close');close.type='button';close.setAttribute('aria-label','등록 안내 닫기');close.onclick=()=>{if(!busy)toast.hidden=true;};toast.append(close);
 }

 const mouseMarkup='<svg class="mvc-mouse-icon" viewBox="0 0 28 38" aria-hidden="true" focusable="false"><rect x="3" y="2" width="22" height="34" rx="11"/><path class="mvc-mouse-left" d="M14 3C8 3 4 7 4 13v4h10Z"/><path class="mvc-mouse-seam" d="M14 3v14M4 17h20"/><path class="mvc-mouse-wheel" d="M14 9v4"/></svg>';
 let ghost=null,ghostAnimation=null;
 function clearGhost(){ghostAnimation?.cancel();ghostAnimation=null;ghost?.remove();ghost=null;}
 function makeGhost(handle){
  const card=el('div',undefined,'mvc-route-ghost');card.setAttribute('aria-hidden','true');
  card.innerHTML=mouseMarkup;const copy=el('div');
  const name=handle.closest('.mvc-pin')?.querySelector('span')?.textContent||'선택한 거래처';
  copy.append(el('strong',chosen.size>1?chosen.size+'곳 함께 이동':name),el('small','누른 채 달력 날짜로'));card.append(copy);dialog.append(card);
  const origin=handle.getBoundingClientRect(),frame=dialog.getBoundingClientRect();
  card.style.left=(origin.left-frame.left+dialog.scrollLeft)+'px';card.style.top=(origin.top-frame.top+dialog.scrollTop)+'px';
  return card;
 }
 function showDragHint(handle){if(mobileMedia.matches)return;
  clearGhost();if(!dialog.open||handle.disabled||!chosen.size||busy||loading||dragSelection||matchMedia('(prefers-reduced-motion: reduce)').matches||matchMedia('(pointer: coarse)').matches)return;
  const target=dialog.querySelector('.mvc-cell.is-selected')||dialog.querySelector('.mvc-cell');if(!target)return;
  const from=handle.getBoundingClientRect(),to=target.getBoundingClientRect();if(!from.width||!to.width)return;
  ghost=makeGhost(handle);const card=ghost;
  const dx=to.left+to.width/2-from.left-card.offsetWidth/2,dy=to.top+to.height/2-from.top-card.offsetHeight/2;
  ghostAnimation=card.animate([{transform:'translate(0,0) scale(.96)',opacity:0,offset:0},{transform:'translate(0,0) scale(1)',opacity:.8,offset:.18},{transform:`translate(${dx}px,${dy}px) scale(.96)`,opacity:.65,offset:.8},{transform:`translate(${dx}px,${dy}px) scale(.9)`,opacity:0,offset:1}],{duration:1800,easing:'ease-in-out'});
  ghostAnimation.onfinish=()=>{if(ghost===card)clearGhost();};
 }
 dialog.addEventListener('close',clearGhost);dialog.addEventListener('scroll',clearGhost);window.addEventListener('resize',clearGhost);
 function endDrag(){clearGhost();dialog.querySelectorAll('.mvc-grip-held').forEach(n=>n.classList.remove('mvc-grip-held'));dragSelection=null;dialog.classList.remove('mvc-dragging');dialog.querySelectorAll('.mvc-drop-target').forEach(n=>n.classList.remove('mvc-drop-target'));}
 function bindDragHandle(handle){
  handle.addEventListener('pointerenter',()=>showDragHint(handle));
  handle.addEventListener('focus',()=>showDragHint(handle));
  handle.addEventListener('mousedown',e=>e.stopPropagation());handle.addEventListener('pointerdown',e=>e.stopPropagation());handle.addEventListener('click',e=>{e.stopPropagation();notice('달력으로 끌어 놓거나 날짜를 눌러 등록해 주세요.');});
  handle.addEventListener('dragstart',e=>{
   if(busy||loading||!chosen.size){e.preventDefault();return;}
   clearGhost();handle.classList.add('mvc-grip-held');dragSelection={ids:[...chosen].sort(),token:String(Date.now())+'-'+Math.random()};e.dataTransfer.effectAllowed='copy';e.dataTransfer.setData('text/plain',dragSelection.token);dialog.classList.add('mvc-dragging');
   ghost=makeGhost(handle);ghost.classList.add('mvc-actual-ghost');void ghost.offsetWidth;e.dataTransfer.setDragImage(ghost,18,18);const preview=ghost;requestAnimationFrame(()=>{if(ghost===preview)clearGhost();});
  });handle.addEventListener('dragend',endDrag);
 }
 bindDragHandle(dragHint);
 function bindDropDay(cell,key){
  cell.addEventListener('dragover',e=>{if(!dragSelection||busy||loading)return;e.preventDefault();e.dataTransfer.dropEffect='copy';dialog.querySelectorAll('.mvc-drop-target').forEach(n=>{if(n!==cell)n.classList.remove('mvc-drop-target');});cell.classList.add('mvc-drop-target');});
  cell.addEventListener('dragleave',e=>{if(!cell.contains(e.relatedTarget))cell.classList.remove('mvc-drop-target');});
  cell.addEventListener('drop',e=>{
   if(!dragSelection||busy||loading||e.dataTransfer.getData('text/plain')!==dragSelection.token)return;
   e.preventDefault();e.stopPropagation();const ids=dragSelection.ids.slice();endDrag();ignoreClickUntil=Date.now()+400;saveBatch(key,ids,true);
  });
 }

 function point(b){const p=locationCache.get(b.uid)||[b.lat,b.lng];return p.every(v=>v!==null&&v!==undefined&&v!==''&&Number.isFinite(Number(v)))&&Math.abs(Number(p[0]))<=90&&Math.abs(Number(p[1]))<=180&&(Number(p[0])!==0||Number(p[1])!==0)?p.map(Number):null;}
 function toggleBuilding(uid){if(busy)return;const adding=!chosen.has(uid);if(!adding)chosen.delete(uid);else{if(chosen.size>=50){notice('한 번에 최대 50곳까지 선택할 수 있습니다.');return;}chosen.add(uid);}refreshSelection();clearGhost();if(adding)requestAnimationFrame(()=>{visitMarkers?.eachLayer(marker=>{if(marker.visitUid===uid&&chosen.has(uid)){const handle=marker.getElement()?.querySelector('.mvc-pin-grip');if(handle)showDragHint(handle);}});});}
 function visitState(uid){
  const records=rows.filter(r=>r.uid===uid&&!['cancelled','missed'].includes(r.status));
  if(records.some(r=>r.status==='completed'&&(r.visited_date===selected||r.date===selected)))return 'completed';
  if(records.some(r=>r.date===selected&&r.status==='planned'))return 'scheduled';
  if(records.some(r=>r.date.startsWith(month)||(r.status==='completed'&&r.visited_date.startsWith(month))))return 'month';
  return '';
 }

 function visitDates(uid){return [...new Set(rows.filter(r=>r.uid===uid&&!['cancelled','missed'].includes(r.status)&&r.date.startsWith(month)).map(r=>r.date))].sort();}
 function shortVisitDates(uid){return visitDates(uid).map(d=>Number(d.slice(5,7))+'/'+Number(d.slice(8,10))).join(' · ');}
 const registrationSummary=el('span','','mvc-registration-summary');registrationSummary.setAttribute('role','status');
 const registrationBar=el('div',undefined,'mvc-registration-bar'),unregisteredButton=el('button','미등록 보기','mvc-unregistered-filter');unregisteredButton.type='button';unregisteredButton.setAttribute('aria-pressed','false');registrationBar.append(registrationSummary,unregisteredButton);q('.mvc-map-title').after(registrationBar);
 const mapTitle=q('.mvc-map-title'),mapActions=q('.mvc-map-actions');
 function arrangeMapControls(){
  if(mobileMedia.matches){registrationBar.append(mapActions);mapTitle.hidden=true;}
  else{mapTitle.append(mapActions);mapTitle.hidden=false;}
 }
 mobileMedia.addEventListener('change',arrangeMapControls);arrangeMapControls();

 // DATE_MAP_MODEL_START
 function visitsOnMapDate(visits,date){return visits.filter(r=>r.date===date&&!['cancelled','missed'].includes(r.status));}
 function visitMapStats(visits,date){
  const grouped=new Map();for(const r of visitsOnMapDate(visits,date)){if(!grouped.has(r.uid))grouped.set(r.uid,[]);grouped.get(r.uid).push(r);}
  let completed=0;for(const list of grouped.values())if(list.every(r=>r.status==='completed'))completed++;
  return {uids:new Set(grouped.keys()),total:grouped.size,completed,pending:grouped.size-completed};
 }
 // DATE_MAP_MODEL_END
 const dateFilters=el('nav',undefined,'mvc-date-map-filters');dateFilters.setAttribute('aria-label','방문 지도 필터');
 const allMapButton=el('button','전체'),todayMapButton=el('button','오늘 방문'),dateMapStatus=el('p','','mvc-date-map-status');
 allMapButton.type=todayMapButton.type='button';dateMapStatus.setAttribute('role','status');
 dateFilters.append(allMapButton,unregisteredButton,todayMapButton);registrationBar.after(dateFilters,dateMapStatus);
 const dayMapButton=el('button','방문 지도 보기','mvc-day-map-open');dayMapButton.type='button';q('.mvc-dayhead').after(dayMapButton);
 allMapButton.onclick=()=>{if(busy||loading)return;mapVisitDate='';unregisteredOnly=false;approvalOnly=false;chosen.clear();render();drawVisitMap(true);};
 todayMapButton.onclick=()=>showDateMap(today());
 dayMapButton.onclick=()=>showDateMap(selected);
 async function showDateMap(date){
  if(busy||loading||!daysReady)return;if(dayDialog.open&&!canCloseDay())return;
  if(dayDialog.open)dayDialog.close();chosen.clear();mapVisitDate=date;selected=date;unregisteredOnly=false;approvalOnly=false;form.hidden=true;
  if(month!==date.slice(0,7)){month=date.slice(0,7);await load();}
  render();switchMobileView('map');ensureVisitMap();requestAnimationFrame(()=>{visitMap?.invalidateSize({pan:false});drawVisitMap(true);});
 }
 unregisteredButton.onclick=()=>{if(busy||loading||!daysReady)return;unregisteredOnly=!unregisteredOnly;mapVisitDate='';if(unregisteredOnly)approvalOnly=false;render();drawVisitMap(true);};
 // UNREGISTERED_MODEL_START
 function registeredVisitIds(visits,selectedMonth){return new Set(visits.filter(r=>!['cancelled','missed'].includes(r.status)&&r.date?.startsWith(selectedMonth+'-')).map(r=>r.uid));}
 // UNREGISTERED_MODEL_END
 function updateRegistrationSummary(){
  const targets=new Set(buildings.map(b=>b.uid)),registered=new Set([...registeredVisitIds(rows,month)].filter(uid=>targets.has(uid)));
  registrationSummary.textContent=loading?'일정 확인 중…':'전체 '+targets.size+'개 · 등록 '+registered.size+'개 · 미등록 '+(targets.size-registered.size)+'개';
  unregisteredButton.textContent=unregisteredOnly?'전체 거래처 보기':'미등록 '+(targets.size-registered.size)+'곳 보기';unregisteredButton.disabled=busy||loading||!daysReady;unregisteredButton.setAttribute('aria-pressed',String(unregisteredOnly));unregisteredButton.title='선택한 월에 방문일정이 없는 거래처만 지도에서 봅니다. 접수·이행보고 일정만 있는 거래처도 포함합니다.';
  const stats=visitMapStats(rows,mapVisitDate||today());
  allMapButton.setAttribute('aria-pressed',String(!mapVisitDate&&!unregisteredOnly&&!approvalOnly));
  todayMapButton.textContent=mapVisitDate&&mapVisitDate!==today()?Number(mapVisitDate.slice(5,7))+'/'+Number(mapVisitDate.slice(8))+' 방문 '+stats.total:'오늘 방문'+(month===today().slice(0,7)?' '+visitMapStats(rows,today()).total:'');
  todayMapButton.setAttribute('aria-pressed',String(!!mapVisitDate));allMapButton.disabled=todayMapButton.disabled=busy||loading||!daysReady;
  dateMapStatus.hidden=!mapVisitDate;
  dateMapStatus.textContent=mapVisitDate?(loading?'일정 확인 중…':mapVisitDate.replaceAll('-','.')+' · 방문 예정 '+stats.pending+'곳 · 완료 '+stats.completed+'곳'+(!stats.total?' · 등록된 방문이 없습니다.':'')):'';
  dayMapButton.textContent=Number(selected.slice(5,7))+'/'+Number(selected.slice(8))+' 방문 지도 · '+visitMapStats(rows,selected).total+'곳 →';dayMapButton.disabled=busy||loading||!daysReady;
  registrationSummary.title=month+' · 현재 담당 거래처 기준, 방문 예정일이 이달인 거래처를 중복 없이 집계합니다. 접수·이행완료보고만 등록한 거래처는 포함하지 않습니다.';
 }
 function paintVisitMarker(marker){
  const state=visitState(marker.visitUid),picked=chosen.has(marker.visitUid),node=marker.getElement()?.querySelector('.mvc-pin');if(!node)return;
  const approved=approvalBuildings().find(b=>b.uid===marker.visitUid);
  let dateTag=node.querySelector('.mvc-pin-dates');if(!dateTag){dateTag=el('span','','mvc-pin-dates');node.querySelector('.mvc-pin-name')?.after(dateTag);}
  const dates=shortVisitDates(marker.visitUid);dateTag.textContent=dates?'| '+dates:'';dateTag.hidden=!dates;dateTag.title=dates;dateTag.setAttribute('aria-label',dates?'방문 예정 '+dates:'');
  const fullName=(marker.visitName||'')+(dates?' | '+dates:'');node.title=fullName+(approved?' · '+approvalLabel(approved)+' · 사용승인일 '+(approved.approval_date||approved.approval_month+'월'):'');marker.setTooltipContent?.(el('span',fullName));
  markApproval(node,approved);
  marker.setZIndexOffset(picked?200000:approved?100000:0);
  let approvalTag=node.querySelector('.mvc-pin-approval');
  if(!approvalTag){approvalTag=el('small','','mvc-pin-approval');node.append(approvalTag);}
  approvalTag.textContent=approvalLabel(approved);approvalTag.hidden=!approved;
  for(const value of ['scheduled','completed','missed','month'])node.classList.toggle('visit-'+value,state===value);
  let grip=node.querySelector('.mvc-pin-grip');if(picked&&!grip){grip=el('button',undefined,'mvc-pin-grip');grip.innerHTML='<svg class="mvc-mouse-icon" viewBox="0 0 28 38" aria-hidden="true" focusable="false"><rect x="3" y="2" width="22" height="34" rx="11"/><path class="mvc-mouse-left" d="M14 3C8 3 4 7 4 13v4h10Z"/><path class="mvc-mouse-seam" d="M14 3v14M4 17h20"/><path class="mvc-mouse-wheel" d="M14 9v4"/></svg><span>끌기</span><span class="mvc-grip-arrow" aria-hidden="true">↗</span>';grip.type='button';grip.draggable=true;grip.setAttribute('aria-label','선택한 거래처를 달력으로 끌기');grip.title='이 버튼을 누른 채 달력 날짜로 끌어 놓으세요';bindDragHandle(grip);node.append(grip);}if(grip){grip.hidden=!picked;grip.disabled=busy||loading;}
  node.classList.toggle('chosen',picked);node.querySelector('b').textContent=picked?'✓':state==='completed'?'✓':state?'●':'＋';
  const status=[marker.visitName,approvalLabel(approved),picked?'선택됨':'',state==='completed'?'방문 완료':state==='scheduled'?'방문 등록':state==='missed'?'미방문 확인':state==='month'?'이달 등록':''].filter(Boolean).join(' · ');
  marker.getElement()?.setAttribute('aria-label',status);
  marker.setTooltipContent(el('span',marker.visitName));
  let tag=node.querySelector('.mvc-pin-status');if(!tag){tag=el('small',undefined,'mvc-pin-status');node.append(tag);}
  tag.textContent=state==='completed'?'방문 완료':state==='scheduled'?'방문 등록':state==='missed'?'미방문 확인':state==='month'?'이달 등록':'';tag.hidden=!state;
  marker.getElement()?.setAttribute('aria-pressed',String(picked));
 }
 function refreshSelection(){
  updateMobileView();updateRegistrationSummary();
  q('.mvc-unselect').hidden=!chosen.size;dragHint.hidden=!chosen.size;dragHint.disabled=busy||loading;dragHint.textContent=chosen.size+'곳 달력으로 끌기';
  q('.mvc-map-title h3').textContent='거래처 지도';
  
  batch.hidden=!chosen.size;
  q('.mvc-selected-names').textContent=buildings.filter(b=>chosen.has(b.uid)).map(b=>b.name).join(' · ');
  const save=q('.mvc-batch-save');save.disabled=busy||loading||!chosen.size;save.textContent=busy?'저장 중…':chosen.size+'곳 방문 등록';
  if(visitMarkers){if(unregisteredOnly||mapVisitDate)drawVisitMap(false);else visitMarkers.eachLayer(paintVisitMarker);}
 }
 function drawVisitMap(fit=false){
  if(!visitMap)return;visitMarkers.clearLayers();if((unregisteredOnly||mapVisitDate)&&(!daysReady||loading))return;const bounds=[],registered=registeredVisitIds(rows,month),dated=mapVisitDate?visitMapStats(rows,mapVisitDate).uids:null;
  const approvalIds=new Set(approvalBuildings().map(b=>b.uid));
  buildings.forEach(b=>{
   if(dated&&!dated.has(b.uid))return;
   if(approvalOnly&&!approvalIds.has(b.uid))return;
   if(unregisteredOnly&&registered.has(b.uid))return;
   const p=point(b);if(!p)return;bounds.push(p);const html=el('div',undefined,'mvc-pin'+(chosen.has(b.uid)?' chosen':''));html.append(el('b',chosen.has(b.uid)?'✓':'＋'),el('span',b.name,'mvc-pin-name'));
   const kind=b.preregistered?'P':b.subscription?.active?'S':b.demo?'':(b.uid.startsWith('demo_visit_')?'':'C');
   const icon=el('i',kind||'▤','mvc-pin-icon');icon.dataset.kind=kind;icon.setAttribute('aria-hidden','true');html.prepend(icon);
   const marker=L.marker(p,{icon:L.divIcon({className:'mvc-map-marker',html,iconSize:null,iconAnchor:[14,18]}),title:b.name});marker.visitUid=b.uid;marker.visitName=b.name;
   marker.bindTooltip(el('span',b.name),{className:'mvc-name-tooltip',direction:'top',offset:[6,-36],opacity:1});
   marker.on('click',()=>{if(mapVisitDate){selected=mapVisitDate;render();openDay();requestAnimationFrame(()=>{[...q('.mvc-daylist').querySelectorAll('[data-visit-uid]')].find(card=>card.dataset.visitUid===b.uid)?.scrollIntoView({block:'nearest'});});return;}toggleBuilding(b.uid);if(chosen.has(b.uid))marker.openTooltip();else marker.closeTooltip();});marker.addTo(visitMarkers);paintVisitMarker(marker);
  });
  if(fit&&bounds.length)visitMap.fitBounds(bounds,{padding:[65,65],maxZoom:15});
  spreadVisitPins();
 }
 function spreadVisitPins(){
  if(!visitMarkers)return;const groups=new Map();
  visitMarkers.eachLayer(marker=>{if(!marker.originalPoint)marker.originalPoint=marker.getLatLng();const p=marker.originalPoint,key=p.lat.toFixed(6)+','+p.lng.toFixed(6);if(!groups.has(key))groups.set(key,[]);groups.get(key).push(marker);});
  groups.forEach(items=>{items.forEach((marker,i)=>{const p=visitMap.project(marker.originalPoint,visitMap.getZoom());marker.setLatLng(visitMap.unproject(L.point(p.x,p.y+(i-(items.length-1)/2)*58),visitMap.getZoom()));});});
 }
 function ensureVisitMap(){
  if(!dialog.open||(mobileMedia.matches&&mobileView!=='map'))return;
  if(typeof L==='undefined'){q('.mvc-plan-map').textContent='지도를 불러오지 못했습니다. 달력에서 날짜를 누르고 일정 추가로 거래처를 선택해 주세요.';return;}
  if(!visitMap){visitMap=L.map(q('.mvc-plan-map'),{scrollWheelZoom:true}).setView([37.65,126.8],10);if(window.partnerBasemap)window.partnerBasemap.add(visitMap);else L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,referrerPolicy:'strict-origin-when-cross-origin',attribution:'&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a>'}).addTo(visitMap);visitMarkers=L.featureGroup().addTo(visitMap);
   const viewControl=L.control({position:'topright'});viewControl.onAdd=()=>{const wrap=el('div',undefined,'mvc-view-control');wrap.append(viewToggle);L.DomEvent.disableClickPropagation(wrap);L.DomEvent.disableScrollPropagation(wrap);return wrap;};viewControl.addTo(visitMap);
   visitMap.on('zoomend',spreadVisitPins);if(typeof ResizeObserver!=='undefined')new ResizeObserver(()=>{if(dialog.open)visitMap.invalidateSize({pan:false});}).observe(q('.mvc-plan-map'));}
  requestAnimationFrame(()=>{visitMap.invalidateSize();drawVisitMap(!mapReady);mapReady=true;});
 }
 // Presentation controls are available only inside the isolated walkthrough.
 if(location.pathname==='/partner_demo_app.php/partner_walkthrough.php'){
  dialog.addEventListener('pwt-visit-camera',e=>{
   if(!visitMap||!visitMarkers)return;
   if(e.detail?.pan){visitMap.panBy(e.detail.pan,{animate:false});return;}
   const candidates=visitMarkers.getLayers().filter(m=>buildings.find(b=>b.uid===m.visitUid)?.preregistered);
   if(!candidates.length)return;
   const anchor=candidates[0];const nearest=candidates.slice().sort((a,b)=>anchor.getLatLng().distanceTo(a.getLatLng())-anchor.getLatLng().distanceTo(b.getLatLng())).slice(0,3);
   visitMap.fitBounds(nearest.map(m=>m.getLatLng()),{padding:[65,80],maxZoom:16,animate:false});
   // Only select markers that remain in the viewport; names are shown on hover.
   nearest.forEach(m=>{m.getElement().dataset.tourVisitTarget='1';});
  });
 }
 q('.mvc-fit').onclick=()=>drawVisitMap(true);q('.mvc-unselect').onclick=()=>{if(!busy){chosen.clear();refreshSelection();}};
 q('.mvc-batch-save').onclick=()=>saveBatch(selected,[...chosen].sort(),false);
 async function saveBatch(date,ids,fromDrag){
  if(busy||loading||!ids.length)return;
  selected=date;const memo=fromDrag?'':q('.mvc-batch-memo').value.trim(),fingerprint=JSON.stringify([ids,date,memo]);
  if(fingerprint!==batchFingerprint){batchFingerprint=fingerprint;batchToken=Array.from(crypto.getRandomValues(new Uint8Array(16)),b=>b.toString(16).padStart(2,'0')).join('');}
  busy=true;refreshSelection();q('.mvc-batch-result').textContent='';if(fromDrag)showToast('방문 일정을 저장하고 있습니다…');
  const body=new URLSearchParams({act:'batch',uids:JSON.stringify(ids),date,memo,batch_token:batchToken,csrf:typeof CSRF!=='undefined'?CSRF:''});
  try{const res=await visitFetch('/manager_visits.php',{method:'POST',credentials:'same-origin',body});const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.error||'일정을 저장하지 못했습니다.');ids.forEach(uid=>chosen.delete(uid));batchFingerprint='';q('.mvc-batch-memo').value='';await load();const message=date.replaceAll('-','.')+' · '+data.created+'곳 등록 완료'+(data.skipped?' (기존 일정 '+data.skipped+'곳 제외)':'');q('.mvc-batch-result').textContent=message;showToast(message,data.created?data.undo_token:null);if(dayDialog.open&&!dayDirty())dayDialog.close();offerReports(data.proposals);}
  catch(e){q('.mvc-batch-result').textContent=e.message||'연결 상태를 확인해 주세요.';showToast(e.message||'저장하지 못했습니다. 다시 시도해 주세요.');}
  finally{busy=false;render();}
 }
 document.addEventListener('manager-building-locations',e=>{for(const p of e.detail?.points||[])locationCache.set(p.uid,[p.lat,p.lng]);if(dialog.open){ensureVisitMap();render();}});

 const approvalSummary=el('button','','mvc-approval-summary');approvalSummary.type='button';approvalSummary.setAttribute('aria-pressed','false');q('.mvc-toolbar').append(approvalSummary);
 approvalSummary.onclick=()=>{if(busy||loading)return;approvalOnly=!approvalOnly;mapVisitDate='';if(approvalOnly)unregisteredOnly=false;render();drawVisitMap(true);};
 function approvalKind(building){
  const approval=Number(building?.approval_month),target=Number(month.slice(5,7));
  if(!Number.isInteger(approval)||approval<1||approval>12)return '';
  const offset=(target-approval+12)%12;
  return offset===0?'approval':offset===6?'half-year':'';
 }
 function approvalLabel(building){
  const kind=approvalKind(building);
  return kind==='approval'?'사용승인월':kind==='half-year'?'6개월 주기':'';
 }
 function approvalBuildings(){
  return [...new Map(buildings.filter(b=>approvalKind(b)).map(b=>[b.uid,b])).values()];
 }
 function markApproval(node,building){
  node.classList.toggle('mvc-approval-glow',Boolean(building));
  node.classList.toggle('mvc-half-year',approvalKind(building)==='half-year');
  if(!building){node.removeAttribute('title');return;}
  node.title=approvalLabel(building)+' · 사용승인일 '+(building.approval_date||building.approval_month+'월')+' (월 기준 참고 표시)';
 }
 const field=n=>form.elements.namedItem(n);
 const statusText={planned:'예정',completed:'완료',missed:'미방문 확인',cancelled:'취소'};
 for(const d of ['일','월','화','수','목','금','토'])q('.mvc-week').append(el('span',d));
 function notice(text=''){
  const isLoading=text==='일정을 불러오고 있습니다.';
  dialog.classList.toggle('mvc-is-loading',isLoading);
  loadStatus.textContent=isLoading?text:'';
  q('.mvc-layout').setAttribute('aria-busy',String(isLoading));
  q('.mvc-notice').textContent=isLoading?'':text;
  if(dayDialog.open&&text&&!isLoading)q('.mvc-batch-result').textContent=text;
 }
 function listed(date){return rows.filter(r=>!['cancelled','missed'].includes(r.status)&&(r.date===date||(r.status==='completed'&&r.visited_date===date))).sort((a,b)=>(a.time||'99').localeCompare(b.time||'99')||a.name.localeCompare(b.name,'ko'));}

 const reportDialog=el('dialog',undefined,'mvc-dialog mvc-report-dialog');reportDialog.setAttribute('aria-labelledby','mvc-report-title');
 reportDialog.innerHTML='<header class="mvc-header"><div><small>INSPECTION REPORT</small><h2 id="mvc-report-title">보고서 접수</h2></div><button type="button" class="mvc-report-close" aria-label="접수 일정 닫기">×</button></header><form class="mvc-report-form"><p class="mvc-report-intro"></p><div class="mvc-report-dates"><div><span class="mvc-report-basis-label">점검 예정일</span><strong class="mvc-report-basis"></strong></div><div><span class="mvc-report-deadline-label">보고 마감일</span><strong class="mvc-report-deadline"></strong></div></div><p class="mvc-report-data-warning" role="status"></p><label>접수 예정일<input name="report_date" type="date" required min="2000-01-01" max="2099-12-31"></label><div class="mvc-report-auto-row"><span class="mvc-report-date-mode"></span><button type="button" class="mvc-report-auto">마감일로 자동 설정</button></div><p class="mvc-report-late" role="status"></p><label class="mvc-report-edit-only">접수 상태<select name="report_status"><option value="planned">미접수</option><option value="completed">접수 완료</option><option value="cancelled">일정 취소</option></select></label><button type="button" class="mvc-report-complete mvc-report-edit-only">✓ 접수 완료로 변경</button><label class="mvc-report-actual" hidden>실제 접수완료일<input name="received_date" type="date"><small class="mvc-received-help">접수 예정일·보고 마감일과 별도로, 실제 접수한 날짜를 입력하세요. 후속 공사·보고 일정은 이 날짜로 계산합니다.</small></label><label class="mvc-report-edit-only">메모<textarea name="report_memo" rows="2" maxlength="2000"></textarea></label><details class="mvc-report-rule"><summary>마감일 계산 기준</summary><p>점검 종료일 다음 날부터 토요일·일요일·공휴일을 제외한 15일입니다. 실제 종료일을 입력하기 전에는 방문 예정일을 기준으로 계산합니다. 접수 예정일은 변경할 수 있으며, 이 기능은 일정 관리용입니다.</p><a href="https://www.law.go.kr/lsLinkCommonInfo.do?chrClsCd=010202&amp;lspttninfSeq=122999" target="_blank" rel="noopener">시행규칙 제23조 제2·3항</a> · <a href="https://www.data.go.kr/data/15012690/openapi.do" target="_blank" rel="noopener">한국천문연구원 공휴일 자료</a></details><p class="mvc-report-error" role="alert"></p><div class="mvc-report-actions"><button type="button" class="mvc-report-cancel">취소</button><button type="submit" class="mvc-report-submit">등록</button></div></form>';
 document.body.append(reportDialog);
 const rq=s=>reportDialog.querySelector(s),rf=reportDialog.querySelector('form');
 let reportContext=null,reportQueue=[],reportDateMode='auto',reportBusy=false;
 const reportField=n=>rf.elements.namedItem(n);
 const reportHealth=el('p','','mvc-report-health');reportHealth.setAttribute('role','status');q('.mvc-layout').before(reportHealth);
 const datedReports=date=>reportRows.filter(r=>!['cancelled','missed'].includes(r.status)&&(r.date===date||(r.status==='completed'&&r.received_date===date)));
 function renderReportNotices(){
  reportHealth.textContent=holidayStale?'공식 공휴일 자료 갱신 확인 중 · 계산에 사용한 자료는 접수 일정에서 확인하세요.':'';
 }
 async function reportRequest(values){
  const body=new URLSearchParams({...values,csrf:typeof CSRF!=='undefined'?CSRF:''});
  const res=await visitFetch('/manager_visits.php',{method:'POST',credentials:'same-origin',body}),data=await res.json();
  if(!res.ok||!data.ok)throw Error(data.error||'접수 일정을 처리하지 못했습니다.');return data;
 }
 function updateReportForm(){
  if(!reportContext)return;const r=reportContext.row,date=reportField('report_date').value,completed=reportField('report_status').value==='completed';
  rq('.mvc-report-date-mode').textContent=reportDateMode==='auto'?'마감일 변경 시 자동으로 이동':'직접 지정한 날짜 유지';
  rq('.mvc-report-auto').disabled=reportBusy||!r.deadline||r.calendar_ready===false||r.ready===false;
  rq('.mvc-report-complete').hidden=reportContext.proposal||completed;rq('.mvc-report-actual').hidden=reportContext.proposal||!completed;reportField('received_date').required=!reportContext.proposal&&completed;reportField('received_date').max=today();if(!reportContext.proposal)rq('.mvc-report-submit').textContent=completed?'접수 완료 저장':'저장';
  rq('.mvc-report-late').textContent=completed?(r.deadline&&reportField('received_date').value>r.deadline?'실제 접수완료일이 보고 마감일을 지났습니다.':''):(r.deadline&&date>r.deadline?'마감일을 넘는 접수 예정일입니다. 일정 수정이 필요합니다.':'');
 }
 function showReport(r,proposal=false){
  if(reportBusy)return;reportContext={row:r,proposal};reportDateMode=proposal?'auto':(r.date_mode||'manual');rf.reset();
  rq('#mvc-report-title').textContent=proposal?'보고서 접수 일정도 등록할까요?':'보고서 접수 · '+r.name;
  rq('.mvc-report-intro').textContent=proposal?(r.name+' 거래처의 사용승인월에 방문일정을 등록했습니다. 보고서 접수 일정도 함께 등록할까요?'):r.name+' · 방문일정과 연결된 접수 일정입니다.';
  rq('.mvc-report-basis-label').textContent=r.basis_type==='actual'?'실제 점검 종료일':'점검 예정일';rq('.mvc-report-basis').textContent=r.basis;
  rq('.mvc-report-deadline-label').textContent=r.basis_type==='actual'?'보고 마감일':'예상 보고 마감일';rq('.mvc-report-deadline').textContent=r.deadline||'공휴일 자료 확인 대기';
  rq('.mvc-report-data-warning').textContent=(r.calendar_ready===false||r.ready===false)?'공식 공휴일 자료가 준비되지 않아 마감일을 확정하지 않았습니다. 접수 예정일은 직접 지정할 수 있습니다.':(r.calendar_stale||r.stale)?'공휴일 자료 갱신을 확인 중입니다. 보관된 공식 자료로 계산한 날짜입니다.':'';
  reportField('report_date').value=proposal?(r.deadline||''):r.date;reportField('report_status').value=proposal?'planned':r.status;reportField('received_date').value=r.received_date||'';reportField('report_memo').value=r.memo||'';
  if(proposal&&!r.deadline)reportDateMode='manual';
  rq('.mvc-report-submit').textContent=proposal?'등록':reportField('report_status').value==='completed'?'접수 완료 저장':'저장';rq('.mvc-report-cancel').textContent=proposal?'취소 · 방문일정만 유지':'닫기';rq('.mvc-report-error').textContent='';
  rf.querySelectorAll('.mvc-report-edit-only').forEach(n=>n.hidden=proposal);updateReportForm();if(!reportDialog.open)reportDialog.showModal();rq('.mvc-report-submit').focus({preventScroll:true});
 }
 function completeReport(r){showReport(r);if(!reportContext)return;reportField('report_status').value='completed';reportField('received_date').value=r.received_date||today();updateReportForm();reportField('received_date').focus({preventScroll:true});}
 rq('.mvc-report-complete').onclick=()=>{if(reportContext&&!reportBusy)completeReport(reportContext.row);};
 function offerReports(proposals){if(demoMode||!proposals?.length)return;reportQueue=proposals.slice();showReport(reportQueue.shift(),true);}
 function nextReport(){reportContext=null;if(reportQueue.length)showReport(reportQueue.shift(),true);else reportDialog.close();}
 rq('.mvc-report-cancel').onclick=()=>{if(!reportBusy)nextReport();};rq('.mvc-report-close').onclick=()=>{if(!reportBusy){reportQueue=[];reportDialog.close();}};
 reportDialog.addEventListener('cancel',e=>{if(reportBusy)e.preventDefault();else reportQueue=[];});reportDialog.addEventListener('close',()=>{reportContext=null;reportQueue=[];});
 reportField('received_date').oninput=updateReportForm;
 reportField('report_date').oninput=()=>{reportDateMode='manual';updateReportForm();};reportField('report_status').onchange=()=>{if(reportField('report_status').value==='completed'&&!reportField('received_date').value)reportField('received_date').value=today();updateReportForm();};
 rq('.mvc-report-auto').onclick=()=>{reportDateMode='auto';reportField('report_date').value=reportContext.row.deadline;updateReportForm();};
 rf.onsubmit=async e=>{
  e.preventDefault();if(reportBusy||!reportContext||!rf.reportValidity())return;
  const {row:r,proposal}=reportContext,values={act:proposal?'report_create':'report_save',visit_id:r.visit_id,date:reportField('report_date').value,date_mode:reportDateMode};
  if(!proposal)Object.assign(values,{revision:String(r.revision),status:reportField('report_status').value,received_date:reportField('received_date').value,memo:reportField('report_memo').value});
  reportBusy=busy=true;rf.querySelectorAll('button,input,select,textarea').forEach(n=>n.disabled=true);rq('.mvc-report-error').textContent='';
  try{const data=await reportRequest(values);await load();showToast(data.report.name+' · '+(data.report.status==='completed'?data.report.received_date+' 접수 완료':data.report.date+' 보고서 접수 '+(proposal?'등록':'저장'))+(data.followup_updated?' · 후속 일정 갱신':''));if(data.followup_offer){linkedReports[data.followup_offer]=data.report;if(data.followup)followupRows[data.followup_offer]=data.followup;}reportBusy=busy=false;nextReport();if(data.followup_offer)openFollowup(data.followup_offer);}
  catch(error){rq('.mvc-report-error').textContent=error.message;}
  finally{reportBusy=busy=false;rf.querySelectorAll('button,input,select,textarea').forEach(n=>n.disabled=false);updateReportForm();render();}
 };
 async function previewReport(visit){
  if(busy)return;busy=true;render();try{const data=await reportRequest({act:'report_preview',visit_id:visit.id});showReport(data.proposal,true);}catch(e){showToast(e.message);}finally{busy=false;render();}
 }

 const followDialog=el('dialog',undefined,'mvc-dialog mvc-report-dialog mvc-followup-dialog');followDialog.setAttribute('aria-labelledby','mvc-follow-title');
 followDialog.innerHTML='<header class="mvc-header"><div><small>WORK &amp; COMPLETION REPORT</small><h2 id="mvc-follow-title">공사·이행완료보고 일정</h2></div><button type="button" class="mvc-follow-close" aria-label="공사·보고 일정 닫기">×</button></header><form id="mvc-follow-form" class="mvc-report-form mvc-follow-form"><p class="mvc-follow-intro"></p><fieldset class="mvc-work-period"><legend>공사기간</legend><label><input type="radio" name="work_period" value="10" checked>10일</label><label><input type="radio" name="work_period" value="20">20일</label><label><input type="radio" name="work_period" value="custom">직접 날짜</label></fieldset><div class="mvc-work-options"><label><input name="include_received" type="checkbox" checked>접수 당일을 포함</label><label><input name="work_business_only" type="checkbox">공사기간에서 주말·공휴일 제외</label></div><label class="mvc-work-custom" hidden>공사 완료기한 직접 지정<input type="date" name="custom_work_due" min="2000-01-01" max="2099-12-31"></label><div class="mvc-follow-dates"><div><span>실제 접수완료일 · 계산 기준</span><strong class="mvc-follow-received"></strong></div><div><span>선택한 공사기간</span><strong class="mvc-follow-period"></strong><small class="mvc-follow-period-rule"></small></div><div><span>이행계획일(공사 완료기한)</span><strong class="mvc-follow-work"></strong></div><div><span class="mvc-follow-filing-label">이행완료보고 제출 마감일</span><strong class="mvc-follow-filing"></strong><small>완료기한 다음 날부터 평일 10일</small></div></div><p class="mvc-follow-health" role="status"></p><div class="mvc-follow-edit-only"><label>실제 공사 완료일 <small>입력하면 이 날짜 다음 날부터 보고 기한 재계산</small><input name="actual_work_date" type="date"></label><label>보고 상태<select name="follow_status"><option value="planned">진행 중</option><option value="completed">이행완료보고 완료</option><option value="cancelled">일정 취소</option></select></label><label class="mvc-follow-filed" hidden>실제 이행완료보고일<input name="filed_date" type="date"></label><label>메모<textarea name="follow_memo" maxlength="2000" rows="2"></textarea></label></div><p class="mvc-follow-note">실제 공사 완료일을 입력하면 보고 마감이 다시 계산됩니다.</p><details class="mvc-follow-rule"><summary>계산 기준·통보기한 안내</summary><p>공사기간 옵션과 보고 기한의 계산 기준은 별개입니다. 보고 기한은 다음 날부터 토요일·일요일·공휴일을 제외한 10일로 계산합니다. 실제 완료일 입력 전에는 공사 완료기한을 기준으로 한 예상 마감입니다. 관할 소방서가 통보한 공사 완료기한을 확인해 주세요.</p></details><p class="mvc-follow-error" role="alert"></p><button type="button" class="mvc-follow-refresh">다시 계산</button><div class="mvc-report-actions"><button type="button" class="mvc-follow-cancel">취소</button><button type="submit" class="mvc-report-submit mvc-follow-submit">등록</button></div></form>';
 const followActions=followDialog.querySelector('.mvc-report-actions');followDialog.append(followActions);followDialog.querySelector('.mvc-follow-submit').setAttribute('form','mvc-follow-form');
 document.body.append(followDialog);
 const fq=s=>followDialog.querySelector(s),followForm=fq('form'),ff=n=>followForm.elements.namedItem(n);
 let followId='',followExisting=null,followPreview=null,followSequence=0,followBusy=false;
 function followValues(){return {visit_id:followId,work_period:ff('work_period').value,include_received:ff('include_received').checked?'1':'0',work_business_only:ff('work_business_only').checked?'1':'0',custom_work_due:ff('custom_work_due').value,actual_work_date:ff('actual_work_date').value};}
 function updateFollowForm(){
  const custom=ff('work_period').value==='custom',complete=ff('follow_status').value==='completed';fq('.mvc-work-custom').hidden=!custom;ff('custom_work_due').required=custom;fq('.mvc-work-options').hidden=custom;
  fq('.mvc-follow-filed').hidden=!complete;ff('filed_date').required=complete;ff('actual_work_date').required=complete;ff('actual_work_date').max=ff('filed_date').max=today();
 }
 async function previewFollowup(){
  updateFollowForm();const seq=++followSequence;followPreview=null;fq('.mvc-follow-submit').disabled=true;fq('.mvc-follow-error').textContent='';
  if(ff('work_period').value==='custom'&&!ff('custom_work_due').value){fq('.mvc-follow-health').textContent='공사 완료기한을 선택해 주세요.';fq('.mvc-follow-work').textContent=fq('.mvc-follow-filing').textContent='날짜 선택 대기';return;}
  fq('.mvc-follow-health').textContent='두 기한을 계산하고 있습니다…';
  try{const data=await reportRequest({act:'followup_preview',...followValues()});if(seq!==followSequence||!followDialog.open)return;const p=data.preview;followPreview=p;
   fq('.mvc-follow-received').textContent=p.received_date;fq('.mvc-follow-period').textContent=p.work_period==='custom'?'직접 지정':p.work_period+'일';fq('.mvc-follow-period-rule').textContent=p.work_period==='custom'?'선택한 날짜 유지':(p.include_received?'접수일 포함':'접수일 제외')+' · '+(p.work_business_only?'평일 기준':'주말·공휴일 포함');
   fq('.mvc-follow-work').textContent=p.work_due||'계산 대기';fq('.mvc-follow-filing').textContent=p.filing_due||'공휴일 자료 확인 대기';fq('.mvc-follow-filing-label').textContent=p.filing_basis_type==='actual'?'이행완료보고 제출 마감일':'이행완료보고 제출 마감일 · 예상';fq('.mvc-follow-dates>div:last-child small').textContent=p.filing_basis_type==='actual'?'실제 공사 완료일 다음 날부터 평일 10일':'공사 완료기한 다음 날부터 평일 10일';
   fq('.mvc-follow-health').textContent=!p.calendar_ready?'공식 공휴일 자료가 준비되지 않아 등록할 수 없습니다. 잠시 후 다시 열어 주세요.':p.calendar_stale?'보관된 공식 공휴일로 계산했습니다. 최신 자료 갱신을 확인 중입니다.':'';fq('.mvc-follow-submit').disabled=!p.calendar_ready||followBusy;
  }catch(e){if(seq===followSequence){fq('.mvc-follow-error').textContent=e.message;fq('.mvc-follow-health').textContent='';}}
 }
 function openFollowup(id){
  if(followBusy)return;followId=id;followExisting=followupRows[id]||null;followPreview=null;followForm.reset();const r=followExisting;fq('#mvc-follow-title').textContent=r?'공사·이행완료보고 일정 수정':'이행완료보고서 제출 일정을 등록할까요?';fq('.mvc-follow-intro').textContent=(r?.name||linkedReports[id]?.name||'거래처')+' · 저장된 실제 접수완료일을 기준으로 공사기간을 선택해 주세요.';
  ff('work_period').value=r?.work_period||'10';ff('include_received').checked=r?r.include_received:true;ff('work_business_only').checked=r?!!r.work_business_only:false;ff('custom_work_due').value=r?.custom_work_due||'';ff('actual_work_date').value=r?.actual_work_date||'';ff('follow_status').value=r?.status||'planned';ff('filed_date').value=r?.filed_date||'';ff('follow_memo').value=r?.memo||'';
  fq('.mvc-follow-edit-only').hidden=!r;fq('.mvc-follow-submit').textContent=r?'저장':'등록';fq('.mvc-follow-received').textContent=r?.received_date||linkedReports[id]?.received_date||'확인 중';fq('.mvc-follow-work').textContent=fq('.mvc-follow-filing').textContent='계산 중';if(!followDialog.open)followDialog.showModal();previewFollowup();
 }
 fq('.mvc-follow-refresh').onclick=previewFollowup;
 for(const n of ['work_period','include_received','work_business_only','custom_work_due','actual_work_date']){
  const field=ff(n);if(field instanceof RadioNodeList){for(const input of field)input.addEventListener('change',previewFollowup);}else field.addEventListener('change',previewFollowup);
 }
 ff('follow_status').onchange=()=>{updateFollowForm();if(ff('follow_status').value==='completed'&&!ff('filed_date').value)ff('filed_date').value=today();};
 fq('.mvc-follow-close').onclick=fq('.mvc-follow-cancel').onclick=()=>{if(!followBusy)followDialog.close();};followDialog.addEventListener('cancel',e=>{if(followBusy)e.preventDefault();});followDialog.addEventListener('close',()=>{followSequence++;followPreview=null;});
 followForm.onsubmit=async e=>{
  e.preventDefault();if(followBusy||!followPreview?.calendar_ready||!followForm.reportValidity())return;const values={act:followExisting?'followup_save':'followup_create',...followValues(),report_revision:String(followPreview.report_revision),expected_work_due:followPreview.work_due,expected_filing_due:followPreview.filing_due,status:ff('follow_status').value,filed_date:ff('filed_date').value,memo:ff('follow_memo').value};if(followExisting)values.revision=String(followPreview.revision);
  followBusy=busy=true;fq('.mvc-follow-submit').disabled=true;followForm.querySelectorAll('button,input,select,textarea').forEach(n=>n.disabled=true);fq('.mvc-follow-error').textContent='';
  try{await reportRequest(values);await load();followDialog.close();showToast('공사 완료기한과 이행완료보고 마감을 저장했습니다.');}
  catch(err){fq('.mvc-follow-error').textContent=err.message;}
  finally{followBusy=busy=false;followForm.querySelectorAll('button,input,select,textarea').forEach(n=>n.disabled=false);fq('.mvc-follow-submit').disabled=!followPreview?.calendar_ready;render();}
 };
 // SUMMARY_MODEL_START
 function summaryModel(visits,reports,events,days,selectedMonth,now,targets=[],followups=[]){
  const inMonth=d=>typeof d==='string'&&d.startsWith(selectedMonth+'-');
  const tasks=[],schedules=[],pending=[],received=[],fulfillment=[];
  const targetIds=new Set(targets.map(b=>b.uid));
  const registered=new Set(visits.filter(r=>targetIds.has(r.uid)&&!['cancelled','missed'].includes(r.status)&&inMonth(r.date)).map(r=>r.uid));
  const unregistered=targetIds.size-registered.size;
  for(const r of visits)if(r.status==='planned'&&inMonth(r.date)&&r.date<now)tasks.push({type:'visit',id:r.id,date:r.date,name:r.name,label:'방문 완료 여부 확인',urgent:true});
  for(const r of new Map(reports.map(r=>[r.visit_id,r])).values()){
   if(r.status==='cancelled'||![r.date,r.deadline,r.received_date].some(inMonth))continue;
   const item={type:'report',id:r.visit_id,date:r.status==='completed'?r.received_date:r.date,name:r.name,label:r.status==='completed'?'보고서 접수 완료':'보고서 접수 예정',detail:'보고 마감 '+(r.deadline||'계산 대기'),completed:r.status==='completed'};
   schedules.push(item);
   (r.status==='completed'?received:pending).push({...item,deadline:r.deadline,needs_reschedule:!!r.needs_reschedule,urgent:r.status==='planned'&&(!!r.needs_reschedule||!r.deadline||r.deadline<now)});
   if(r.status==='planned'&&(r.needs_reschedule||!r.deadline||r.deadline<now))tasks.push({...item,label:r.needs_reschedule?'접수 예정일 수정 필요':!r.deadline?'보고 마감일 계산 확인 필요':'보고서 접수 여부 확인',urgent:true});
  }
  for(const r of events){
   if(r.status==='cancelled'||!inMonth(r.date))continue;
   const item={type:'follow',id:r.visit_id,date:r.date,name:r.name,label:r.kind==='work'?'공사 완료기한':'이행완료보고 마감',completed:r.status==='completed'};
   schedules.push(item);
   if(r.status==='planned'&&(r.needs_review||r.date<now))tasks.push({...item,label:r.needs_review?'공사·보고 일정 재확인 필요':r.kind==='work'?'공사 완료 여부 확인':'이행완료보고 제출 확인',urgent:true});
  }
  for(const r of followups)if(r.status==='planned'&&r.suspended&&r.needs_review&&[r.received_date,r.work_due,r.filing_due].some(inMonth))tasks.push({type:'follow',id:r.visit_id,date:r.work_due||r.received_date,name:r.name,label:r.review_reason||'접수 완료 상태 확인 필요',urgent:true});
  const sort=(a,b)=>a.date.localeCompare(b.date)||a.name.localeCompare(b.name,'ko');tasks.sort(sort);schedules.sort(sort);
  for(const r of followups){
   if(r.status==='cancelled'||![r.received_date,r.work_due,r.filing_due,r.filed_date].some(inMonth))continue;
   fulfillment.push({type:'follow',id:r.visit_id,name:r.name,date:r.work_due||r.received_date,work_due:r.work_due,filing_due:r.filing_due,actual_work_date:r.actual_work_date,filed_date:r.filed_date,completed:r.status==='completed',estimated:r.filing_basis_type!=='actual',warning:r.suspended?(r.review_reason||'접수 상태 확인 필요'):r.needs_review?(r.review_reason||'일정 재확인 필요'):'',urgent:r.status==='planned'&&(r.needs_review||r.suspended||(!r.actual_work_date&&r.work_due<now)||r.filing_due<now)});
  }
  pending.sort(sort);received.sort(sort);fulfillment.sort(sort);
  return {tasks,schedules,pending,received,fulfillment,unregistered,total:targetIds.size,registered:registered.size};
 }
 // SUMMARY_MODEL_END
 const summaryButton=el('button','요약','mvc-summary-open');summaryButton.innerHTML='<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="3"/><path d="M8 8h8M8 12h8M8 16h5"/></svg><span>요약</span>';summaryButton.type='button';summaryButton.setAttribute('aria-haspopup','dialog');q('.mvc-header>div').after(summaryButton);
 const summaryDialog=el('dialog',undefined,'mvc-dialog mvc-summary-dialog');summaryDialog.setAttribute('aria-labelledby','mvc-summary-title');
 summaryDialog.innerHTML='<header class="mvc-header"><div><small>방문일정</small><h2 id="mvc-summary-title">이번 달 업무 요약</h2></div><button type="button" class="mvc-summary-close" aria-label="업무 요약 닫기">×</button></header><div class="mvc-summary-body mvc-summary-brief"><p class="mvc-summary-scope"></p><div class="mvc-summary-counts"></div><section><h3>접수해야 할 보고서 <small class="mvc-summary-pending-count"></small></h3><div class="mvc-summary-pending"></div></section><section class="mvc-summary-work-section"><h3>공사 · 이행완료 기한 <small class="mvc-summary-fulfillment-count"></small></h3><div class="mvc-summary-fulfillment"></div></section><section class="mvc-summary-done-section"><h3>접수 완료 <small class="mvc-summary-received-count"></small></h3><div class="mvc-summary-received"></div></section></div>';

 document.body.append(summaryDialog);const sq=s=>summaryDialog.querySelector(s);
 sq('.mvc-summary-close').onclick=()=>summaryDialog.close();summaryDialog.addEventListener('close',()=>{if(dialog.open)summaryButton.focus({preventScroll:true});});
 function summaryGo(item){
  summaryDialog.close();
  if(item.type==='report'){const r=reportRows.find(r=>r.visit_id===item.id)||linkedReports[item.id];if(r)showReport(r);return;}
  if(item.type==='follow'){openFollowup(item.id);return;}
  selected=item.date;render();openDay();
 }
 let summaryCategory='pending';
 function selectSummaryCategory(key){
  summaryCategory=key;
  for(const category of ['pending','fulfillment','received']){
   sq('.mvc-summary-'+category).closest('section').hidden=category!==key;
   const control=sq('.mvc-summary-stat.'+category);control?.setAttribute('aria-pressed',String(category===key));
  }
 }
 // SUMMARY_URGENCY_START
 function summaryUrgency(d,warning,done,now){
  if(done)return {label:'완료',tone:'done',rank:4};
  const days=d?Math.round((Date.parse(d+'T00:00:00Z')-Date.parse(now+'T00:00:00Z'))/86400000):null;
  if(days!==null&&days<0)return {label:-days+'일 경과',tone:'overdue',rank:0};
  if(days===0)return {label:'오늘 마감',tone:'today',rank:1};
  if(warning||days===null||!Number.isFinite(days))return {label:'확인 필요',tone:'alert',rank:2};
  return {label:'D−'+days,tone:days<=3?'soon':'waiting',rank:days<=3?2:3};
 }
 // SUMMARY_URGENCY_END
 function paintSummary(){
  const model=summaryModel(rows,[...Object.values(linkedReports),...reportRows],followupEvents,dayMeta,month,today(),buildings,Object.values(followupRows));
  const date=d=>d?(d.slice(0,4)!==month.slice(0,4)?d.slice(0,4)+'.':'')+Number(d.slice(5,7))+'/'+Number(d.slice(8,10)):'계산 대기';
  sq('#mvc-summary-title').textContent=month.replace('-','년 ')+'월 요약';
  const scope=sq('.mvc-summary-scope');scope.replaceChildren(el('span','방문일정 미등록'),el('strong',model.unregistered+'곳'),el('small','전체 '+model.total+'곳'));
  const counts=sq('.mvc-summary-counts');counts.replaceChildren();
  counts.setAttribute('role','group');counts.setAttribute('aria-label','보고서 요약 분류');
  for(const [n,label,key] of [[model.pending.length,'접수할 보고서','pending'],[model.fulfillment.filter(r=>!r.completed).length,'공사·이행 기한','fulfillment'],[model.received.length,'접수 완료','received']]){
   const card=el('button',undefined,'mvc-summary-stat '+key);card.type='button';card.setAttribute('aria-pressed',String(summaryCategory===key));card.setAttribute('aria-controls','mvc-summary-panel-'+key);card.append(el('span',label),el('strong',String(n)));
   const urgentItems=key==='pending'?model.pending.filter(r=>summaryUrgency(r.deadline,r.needs_reschedule,false,today()).rank<=2):key==='fulfillment'?model.fulfillment.filter(r=>!r.completed&&summaryUrgency(!r.actual_work_date?r.work_due:r.filing_due,!!r.warning,false,today()).rank<=2):[];
   if(urgentItems.length){card.classList.add('has-urgent');card.append(el('em','긴급·확인 '+urgentItems.length+'건'));}
   card.onclick=()=>selectSummaryCategory(key);counts.append(card);
  }
  function group(key,items,empty,describe){
   const list=sq('.mvc-summary-'+key);list.id='mvc-summary-panel-'+key;list.replaceChildren();sq('.mvc-summary-'+key+'-count').textContent=items.length+'건';
   if(!items.length)list.append(el('p',empty,'mvc-summary-empty'));
   const ranked=items.map(item=>{const info=describe(item);return {item,info,badge:summaryUrgency(info.due,info.warning,item.completed,today())};}).sort((a,b)=>a.badge.rank-b.badge.rank||(a.info.due||a.item.date).localeCompare(b.info.due||b.item.date));
   for(const {item,info,badge} of ranked){
    const b=el('button',undefined,'mvc-summary-item mvc-summary-row urgency-'+badge.tone+(['overdue','today','alert','soon'].includes(badge.tone)?' is-urgent':''));b.type='button';
    const head=el('div',undefined,'mvc-summary-row-head'),name=el('strong',item.name),status=el('span',(info.badgePrefix&&!item.completed&&!info.warning?info.badgePrefix+' ':'')+badge.label,'mvc-summary-status '+badge.tone);head.append(name,status,el('span','›','mvc-summary-chevron'));b.append(head);
    const dates=el('div',undefined,'mvc-summary-row-dates');for(const [label,value] of info.dates){const part=el('span');part.append(el('small',label),el('b',date(value)));dates.append(part);}b.append(dates);
    if(info.warning)b.append(el('small',info.warning,'mvc-summary-row-warning'));
    b.onclick=()=>summaryGo(item);list.append(b);
   }
  }
  group('pending',model.pending,'접수 대기 중인 보고서가 없습니다.',r=>({due:r.deadline,dates:[['접수 예정',r.date],['보고 마감',r.deadline]],warning:r.needs_reschedule?'접수 예정일을 조정해 주세요.':!r.deadline?'보고 마감일 계산을 확인해 주세요.':r.deadline<today()?'실제 접수 여부를 확인해 주세요.':''}));
  group('received',model.received,'접수 완료한 보고서가 없습니다.',r=>({dates:[['실제 접수',r.date]]}));
  group('fulfillment',model.fulfillment,'진행 중인 공사·이행보고 일정이 없습니다.',r=>({due:!r.actual_work_date?r.work_due:r.filing_due,badgePrefix:!r.actual_work_date?'공사':'보고',dates:[[r.actual_work_date?'공사 완료':'공사 기한',r.actual_work_date||r.work_due],[r.completed?'보고 접수':r.estimated?'예상 제출':'보고 제출',r.completed?r.filed_date:r.filing_due]],warning:r.warning||(!r.completed&&r.urgent?'공사·보고 완료 여부를 확인해 주세요.':'')}));

  selectSummaryCategory(summaryCategory);
 }
 summaryButton.onclick=async()=>{
  if(busy||loading)return;summaryButton.disabled=true;await load();summaryButton.disabled=false;if(!dialog.open)return;
  if(!daysReady){showToast('일정을 불러오지 못했습니다. 다시 시도해 주세요.');return;}
  summaryCategory='pending';paintSummary();summaryDialog.showModal();
 };
 const datedFollowups=date=>followupEvents.filter(r=>r.date===date);
 function renderFollowupCards(){
  const list=q('.mvc-daylist');for(const event of datedFollowups(selected)){
   const r=followupRows[event.visit_id];if(!r)continue;const label=event.kind==='work'?'이행계획일(공사 완료기한)':'이행완료보고 마감',card=el('article',undefined,'mvc-record mvc-follow-record '+event.kind);
   card.append(el('small',(event.kind==='work'?'▣ ':'▤ ')+label+' · '+(event.status==='completed'?'완료':'예정')),el('strong',event.name),el('span','실제 접수완료일 '+r.received_date),el('span','공사 완료기한 '+r.work_due),el('span',(r.filing_basis_type==='actual'?'이행완료보고 마감 ':'예상 이행완료보고 마감 ')+r.filing_due));
   if(r.actual_work_date)card.append(el('span','실제 공사 완료 '+r.actual_work_date));if(r.filed_date)card.append(el('span','실제 이행완료보고 '+r.filed_date));if(r.needs_review)card.append(el('b',r.review_reason||'변경된 일정 재확인 필요','mvc-report-late'));
   const edit=el('button','공사·보고 일정 수정');edit.type='button';edit.disabled=busy||loading;edit.onclick=()=>openFollowup(event.visit_id);card.append(edit);list.append(card);
  }
 }
 function calendarCycleCounts(visits,approvalByUid){
  const counts={approval:0,'half-year':0};
  for(const uid of new Set(visits.filter(r=>!['cancelled','missed'].includes(r.status)).map(r=>r.uid))){
   const kind=approvalKind(approvalByUid.get(uid));if(kind)counts[kind]++;
  }
  return counts;
 }
 function render(){
  syncDayNotes();refreshSelection();renderReportNotices();summaryButton.disabled=busy||loading;if(summaryDialog.open)paintSummary();
  const approved=approvalBuildings(),approvalByUid=new Map(approved.map(b=>[b.uid,b]));
  const annualCount=approved.filter(b=>approvalKind(b)==='approval').length;
  const annualStat=el('span',undefined,'mvc-approval-stat is-annual'),halfStat=el('span',undefined,'mvc-approval-stat is-half');
  annualStat.append(el('span','사용승인월'),el('b',String(annualCount)));halfStat.append(el('span','6개월 주기'),el('b',String(approved.length-annualCount)));
  approvalSummary.replaceChildren(annualStat,halfStat,el('span',approvalOnly?'전체 보기':'모아 보기','mvc-approval-action'));

  approvalSummary.setAttribute('aria-label','사용승인월 '+annualCount+'곳 · 6개월 주기 '+(approved.length-annualCount)+'곳 · '+(approvalOnly?'전체 보기':'모아 보기'));
  approvalSummary.setAttribute('aria-pressed',String(approvalOnly));approvalSummary.disabled=busy||loading;
  approvalSummary.title=approvalOnly?'누르면 지도에서 전체 거래처를 표시합니다.':'누르면 사용승인월 또는 6개월 주기에 해당하는 거래처를 모아 봅니다. 사용승인 월 기준 참고 표시이며, 점검 대상 여부를 판정하지 않습니다.';
  const [y,m]=month.split('-').map(Number);q('.mvc-month').textContent=`${y}년 ${m}월`;q('.mvc-dayhead h3').textContent=selected.replaceAll('-','.');q('.mvc-add').disabled=loading||busy;
  const grid=q('.mvc-grid');grid.replaceChildren();const first=new Date(y,m-1,1),days=new Date(y,m,0).getDate();grid.style.gridTemplateRows='repeat('+Math.ceil((first.getDay()+days)/7)+',minmax(0,1fr))';
  for(let i=0;i<first.getDay();i++)grid.append(el('div',undefined,'mvc-empty'));
  for(let d=1;d<=days;d++){
   const key=dayKey(new Date(y,m-1,d)),cell=el('button',undefined,'mvc-cell');cell.type='button';cell.classList.toggle('is-selected',key===selected);cell.classList.toggle('is-today',key===today());cell.setAttribute('aria-pressed',String(key===selected));cell.setAttribute('aria-label',key+' 일정 '+listed(key).length+'건');const dateLine=el('span',undefined,'mvc-date-line');dateLine.append(el('b',String(d)));cell.append(dateLine);
   const meta=dayMeta[key]||{},legal=legalHolidays[key];cell.classList.toggle('is-holiday',!!meta.holiday||!!legal);if(legal)cell.title=legal;
   cell.setAttribute('aria-label',key+(meta.holiday?' 휴일':'')+(meta.memo?' 메모 있음':'')+' 방문 '+listed(key).length+'건 · 접수 '+datedReports(key).length+'건');
   if(meta.holiday||meta.memo){const flags=el('span',undefined,'mvc-day-flags');flags.setAttribute('aria-hidden','true');if(meta.holiday)flags.append(el('span','휴','mvc-day-off-mark'));if(meta.memo){const icon=el('span',undefined,'mvc-day-memo-mark');icon.innerHTML='<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 3h12v10l-4 4H4zM12 17v-4h4M7 7h6M7 10h4"/></svg>';icon.append(el('span','메모'));cell.title=meta.memo;cell.setAttribute('aria-description','날짜 메모: '+meta.memo);if(pendingMemoGlow.has(key)&&!dayDialog.open&&daysReady){icon.classList.add('is-just-saved');pendingMemoGlow.delete(key);}flags.append(icon);}dateLine.append(flags);}
   const items=[...listed(key),...datedReports(key).map(r=>({...r,kind:'report'})),...datedFollowups(key)];items.slice(0,2).forEach(r=>{const isReport=r.kind==='report',isFollow=['work','filing'].includes(r.kind),prefix=isReport?'◆ 접수 ':r.kind==='work'?'▣ 공사 ':r.kind==='filing'?'▤ 이행 ':r.status==='completed'&&r.visited_date===key?'✓ ':r.time?r.time+' ':'';const chip=el('span',isFollow?(r.kind==='work'?'▣ ':'▤ ')+r.name+' · '+(r.kind==='work'?'공사 완료기한':'이행완료보고 마감'):prefix+r.name,'mvc-chip '+r.status+(isReport?' mvc-report-chip':isFollow?' mvc-follow-chip '+r.kind:''));if(isReport){chip.title=r.name+' · 보고서 접수 · 마감 '+(r.deadline||'계산 대기');chip.classList.toggle('needs-reschedule',r.needs_reschedule);}else if(isFollow)chip.title=r.name+' · '+(r.kind==='work'?'공사 완료기한':'이행완료보고 마감')+' '+r.date;else markApproval(chip,approvalByUid.get(r.uid));cell.append(chip);});
   if(datedFollowups(key).length)cell.setAttribute('aria-label',cell.getAttribute('aria-label')+' · 공사 '+datedFollowups(key).filter(r=>r.kind==='work').length+'건 · 이행 '+datedFollowups(key).filter(r=>r.kind==='filing').length+'건');
   if(items.length>2){const more=el('small','외 '+(items.length-2)+'건','mvc-day-more');if(items.slice(2).some(r=>approvalByUid.has(r.uid))){more.classList.add('mvc-approval-more');more.textContent+=' · 주기 해당 포함';}cell.append(more);}
   if(items.length){
    const count=el('span',[listed(key).length?'방문 '+listed(key).length:'',datedReports(key).length?'접수 '+datedReports(key).length:'',datedFollowups(key).filter(r=>r.kind==='work').length?'공사 '+datedFollowups(key).filter(r=>r.kind==='work').length:'',datedFollowups(key).filter(r=>r.kind==='filing').length?'이행 '+datedFollowups(key).filter(r=>r.kind==='filing').length:''].filter(Boolean).join(' · '),'mvc-day-count');
    if(items.every(r=>r.status==='completed'))count.classList.add('all-completed');
    if(datedReports(key).length)count.classList.add('has-report');
    if(items.some(r=>approvalByUid.has(r.uid)))count.classList.add('has-approval');
    count.title=items.map(r=>r.name).join(' · ');cell.append(count);
    const cycles=calendarCycleCounts(listed(key),approvalByUid),cycleLabels=[];
    const tags=el('span',undefined,'mvc-day-cycles');
    for(const [kind,label,fullLabel] of [['approval','승인월','사용승인월'],['half-year','6개월','6개월 주기']]){
     if(!cycles[kind])continue;
     const tag=el('span',label+' '+cycles[kind],'mvc-day-cycle '+kind);tag.title=fullLabel+' 대상 '+cycles[kind]+'곳';tag.setAttribute('aria-label',tag.title);tags.append(tag);cycleLabels.push(tag.title);
    }
    if(cycleLabels.length){cell.append(tags);cell.setAttribute('aria-label',cell.getAttribute('aria-label')+' · '+cycleLabels.join(' · '));}

   }
   bindDropDay(cell,key);cell.onclick=()=>{if(busy||loading||!daysReady||Date.now()<ignoreClickUntil)return;selected=key;form.hidden=true;render();openDay();};grid.append(cell);
  }
  const list=q('.mvc-daylist');list.replaceChildren();
  if(!listed(selected).length&&!datedReports(selected).length&&!datedFollowups(selected).length)list.append(el('p',loading?'일정을 불러오는 중입니다.':'등록된 방문 일정이 없습니다.','mvc-empty-copy'));
  const pendingVisits=listed(selected).filter(r=>r.status==='planned');
  if(completionDate!==selected){completionDate=selected;completionActual=today();completionSelection.clear();}
  const pendingIds=new Set(pendingVisits.map(r=>r.id));for(const id of completionSelection)if(!pendingIds.has(id))completionSelection.delete(id);
  if(pendingVisits.length>1){
   const tools=el('div',undefined,'mvc-completion-tools'),all=el('label'),check=el('input');check.type='checkbox';check.checked=completionSelection.size===pendingVisits.length;check.indeterminate=completionSelection.size>0&&!check.checked;check.disabled=busy||loading;
   all.append(check,el('span','전체 선택'));check.onchange=()=>{completionSelection.clear();if(check.checked)pendingVisits.slice(0,50).forEach(r=>completionSelection.add(r.id));render();};
   const actual=el('input');actual.type='date';actual.value=completionActual;actual.max=today();actual.onchange=()=>{completionActual=actual.value;};actual.setAttribute('aria-label','선택한 거래처의 실제 방문일');actual.disabled=busy||loading;
   const done=el('button','선택 '+completionSelection.size+'곳 완료','mvc-complete-button');done.type='button';done.disabled=busy||loading||!completionSelection.size;done.onclick=()=>confirmVisits(pendingVisits.filter(r=>completionSelection.has(r.id)),'completed',actual.value);
   tools.append(all,actual,done);list.append(tools);
  }
  listed(selected).forEach(r=>{
   const card=el('article',undefined,'mvc-record'+(r.status==='completed'?' mvc-visit-confirmed':r.date<today()?' mvc-visit-check':''));card.dataset.visitUid=r.uid;markApproval(card,approvalByUid.get(r.uid));if(approvalByUid.has(r.uid))card.append(el('small',approvalLabel(approvalByUid.get(r.uid))+' · 사용승인일 '+(approvalByUid.get(r.uid).approval_date||approvalByUid.get(r.uid).approval_month+'월'),'mvc-approval-label'));card.append(el('small',(r.time||'시간 미지정')+' · '+(r.status==='completed'?'✓ 방문 완료':r.status==='missed'?'미방문 확인':r.date<today()?'방문 확인 필요':'방문 예정'),r.status),el('strong',r.name),el('span',r.status==='completed'?'실제 방문 '+r.visited_date:'방문 예정 '+r.date));if(r.memo)card.append(el('p',r.memo));
   if(r.status==='planned'&&pendingVisits.length>1){const choice=el('label',undefined,'mvc-visit-select'),check=el('input');check.type='checkbox';check.checked=completionSelection.has(r.id);check.disabled=busy||loading;choice.append(check,el('span','방문 완료할 거래처 선택'));check.onchange=()=>{if(check.checked){if(completionSelection.size>=50){check.checked=false;showToast('한 번에 50곳까지 선택할 수 있습니다.');return;}completionSelection.add(r.id);}else completionSelection.delete(r.id);render();};card.prepend(choice);}
   const actions=el('div',undefined,'mvc-record-actions'),change=el('button','방문일·메모 수정'),remove=el('button','등록 해제','mvc-remove');change.type=remove.type='button';change.disabled=remove.disabled=busy||loading;change.onclick=()=>edit(r);remove.onclick=()=>cancelVisit(r);actions.append(change,remove);
   const complete=el('button',r.status==='completed'?'완료 취소':'✓ 방문 완료',r.status==='completed'?'mvc-complete-undo':'mvc-complete-button');complete.type='button';complete.disabled=busy||loading;complete.title=r.status==='completed'?'방문 여부 확인 전 상태로 되돌립니다.':'실제 방문일을 오늘로 저장합니다. 다른 날은 방문일·메모 수정에서 변경하세요.';complete.onclick=()=>confirmVisits([r],r.status==='completed'?'planned':'completed',today());actions.prepend(complete);
   const report=linkedReports[r.id],reportButton=el('button',report?'보고서 접수 · '+(report.status==='completed'?'완료':report.status==='cancelled'?'취소':'미접수'):'＋ 보고서 접수');reportButton.type='button';reportButton.disabled=busy||loading||demoMode;reportButton.onclick=()=>report?showReport(report):previewReport(r);actions.append(reportButton);
   if(r.inspection_end)card.append(el('small','실제 점검 종료일 '+r.inspection_end));card.append(actions);list.append(card);
  });
  renderReceiptCards();renderFollowupCards();
 }

 async function confirmVisits(visits,status,actual){
  if(busy||loading||!visits.length)return;if(status==='completed'&&(!/^\d{4}-\d{2}-\d{2}$/.test(actual)||actual>today())){showToast('실제 방문일은 오늘까지의 날짜로 선택해 주세요.');return;}
  busy=true;render();
  try{const body=new URLSearchParams({act:'confirm_visits',visits:JSON.stringify(visits.map(r=>({id:r.id,revision:r.revision}))),status,visited_date:actual,csrf:typeof CSRF==='string'?CSRF:''});const res=await visitFetch('/manager_visits.php',{method:'POST',credentials:'same-origin',body});const data=await res.json();if(!res.ok||!data.ok)throw Error(data.error||'방문 상태를 저장하지 못했습니다.');completionSelection.clear();await load();const message=status==='completed'?data.updated+'곳 방문 완료 · 실제 방문 '+actual:'방문 완료를 취소했습니다.';q('.mvc-batch-result').textContent=message;showToast(message);}
  catch(e){q('.mvc-batch-result').textContent=e.message;showToast(e.message);}
  finally{busy=false;render();}
 }

 function renderReceiptCards(){
  const list=q('.mvc-daylist');
  for(const r of datedReports(selected)){
   const card=el('article',undefined,'mvc-record mvc-report-record');card.append(el('small','◆ 보고서 접수 · '+(r.status==='completed'?'접수 완료':'미접수')),el('strong',r.name),el('span',(r.status==='completed'?'당초 접수 예정 ':'접수 예정 ')+r.date),el('span',(r.basis_type==='actual'?'보고 마감 ':'예상 보고 마감 ')+(r.deadline||'공휴일 자료 확인 대기')));
   if(r.status==='completed')card.append(el('span','접수완료일 '+r.received_date));
   if(r.needs_reschedule)card.append(el('b','마감일 초과 · 일정 수정 필요','mvc-report-late'));
   else if(r.status==='planned'&&r.deadline&&r.deadline<today())card.append(el('b','보고 마감 경과 · 접수 확인 필요','mvc-report-late'));
   if(r.calendar_stale&&r.status==='planned')card.append(el('small','공휴일 갱신 확인 중'));
   if(r.memo)card.append(el('p',r.memo));if(r.status==='completed'){const follow=el('button',followupRows[r.visit_id]?'공사·이행완료보고 일정':'＋ 공사·이행완료보고 일정');follow.type='button';follow.disabled=busy||loading;follow.onclick=()=>openFollowup(r.visit_id);card.append(follow);}
   const action=el('button',r.status==='planned'?'✓ 접수 완료':'접수 확인·수정');action.type='button';action.disabled=busy||loading;action.onclick=()=>r.status==='planned'?completeReport(r):showReport(r);card.append(action);if(r.status==='planned'){const edit=el('button','일정 수정');edit.type='button';edit.disabled=busy||loading;edit.onclick=()=>showReport(r);card.append(edit);}list.append(card);
  }
 }
 async function cancelVisit(row){
  if(busy)return;busy=true;form.hidden=true;render();q('.mvc-batch-result').textContent='';
  const body=new URLSearchParams({id:row.id,revision:String(row.revision),uid:row.uid,date:row.date,time:row.time||'',status:'cancelled',visited_date:'',memo:row.memo||'',csrf:typeof CSRF!=='undefined'?CSRF:''});
  try{const response=await visitFetch('/manager_visits.php',{method:'POST',credentials:'same-origin',body});const data=await response.json();if(!response.ok||!data.ok)throw new Error(data.error||'등록을 해제하지 못했습니다.');await load();q('.mvc-batch-result').textContent=row.name+' 방문 등록을 해제했습니다.';}
  catch(error){q('.mvc-batch-result').textContent=error.message||'연결 상태를 확인해 주세요.';}
  finally{busy=false;render();}
 }
 async function load(){
  const id=++request;loading=true;daysReady=false;rows=[];dayMeta={};notice('일정을 불러오고 있습니다.');render();
  try{const res=await visitFetch('/manager_visits.php?month='+encodeURIComponent(month),{credentials:'same-origin',cache:'no-store'});const data=await res.json();if(id!==request)return;if(!res.ok||!data.ok)throw new Error(data.error||'일정을 불러오지 못했습니다.');rows=data.visits;dayMeta=data.days||{};followupRows=data.followups||{};followupEvents=data.followup_events||[];reportRows=data.reports||[];linkedReports=data.linked_reports||{};legalHolidays=data.holidays||{};holidayStale=!demoMode&&!!data.holiday_stale;reportNotices=data.report_notices||[];reportOverdue=data.report_overdue||0;daysReady=true;notice('');}
  catch(e){if(id===request)notice(e.message||'연결 상태를 확인해 주세요.');}
  finally{if(id===request){loading=false;render();if(dialog.open&&!walkthroughDemo)window.visitInboxRefresh?.();}}
 }
 function syncActual(){const complete=field('status').value==='completed';q('.mvc-actual').hidden=!complete;field('visited_date').required=complete;field('visited_date').max=today();if(complete&&!field('visited_date').value)field('visited_date').value=today();}
 function edit(row=null,uid=''){
  if(busy)return;editing=row;form.reset();form.hidden=false;q('.mvc-error').textContent='';q('.mvc-formtitle').textContent=row?'방문 일정 수정':'방문 일정 추가';
  const select=field('uid');select.replaceChildren();const blank=el('option','거래처를 선택해 주세요');blank.value='';select.append(blank);
  for(const b of buildings){const opt=el('option',b.name+(b.address?' · '+b.address:''));opt.value=b.uid;select.append(opt);}
  if(row&&!buildings.some(b=>b.uid===row.uid)){const opt=el('option',row.name+' (기존 방문 기록)');opt.value=row.uid;select.append(opt);}
  select.value=row?.uid||uid;field('date').value=row?.date||selected;field('time').value=row?.time||'';field('status').value=row?.status||'planned';field('visited_date').value=row?.visited_date||'';field('memo').value=row?.memo||'';field('inspection_end').value=row?.inspection_end||'';field('inspection_end').max=today();syncActual();select.focus();
 }
 let scrollLock=null;
 function lockPageScroll(){
  if(scrollLock)return;
  const body=document.body,html=document.documentElement;
  const props=['position','top','left','width','overflow','padding-right','box-sizing'];
  scrollLock={x:window.scrollX,y:window.scrollY,body:props.map(p=>[p,body.style.getPropertyValue(p),body.style.getPropertyPriority(p)]),html:['overflow','scroll-behavior'].map(p=>[p,html.style.getPropertyValue(p),html.style.getPropertyPriority(p)])};
  const gap=Math.max(0,window.innerWidth-html.clientWidth),padding=parseFloat(getComputedStyle(body).paddingRight)||0;
  html.style.setProperty('overflow','hidden');body.style.setProperty('position','fixed');body.style.setProperty('top',-scrollLock.y+'px');body.style.setProperty('left',-scrollLock.x+'px');body.style.setProperty('width','100%');body.style.setProperty('box-sizing','border-box');body.style.setProperty('overflow','hidden');body.style.setProperty('padding-right',padding+gap+'px');
 }
 function unlockPageScroll(){
  if(!scrollLock)return;const saved=scrollLock;scrollLock=null;
  const restore=(style,entries)=>entries.forEach(([p,v,priority])=>v?style.setProperty(p,v,priority):style.removeProperty(p));
  restore(document.body.style,saved.body);restore(document.documentElement.style,saved.html);
  document.documentElement.style.setProperty('scroll-behavior','auto','important');window.scrollTo(saved.x,saved.y);
  restore(document.documentElement.style,saved.html);
 }
 const guide=el('dialog',undefined,'mvc-dialog mvc-guide-dialog');guide.setAttribute('aria-labelledby','mvc-guide-title');
 guide.innerHTML='<div class="mvc-guide-content"><div class="mvc-guide-top"><span>WORK MODE</span><button type="button" class="mvc-guide-close" aria-label="사용 안내 닫기">×</button></div><h2 id="mvc-guide-title">방문 일정, 이렇게 등록하세요</h2><p>지도에서 고르고, 달력에 놓으면 끝입니다.</p><ol><li><b>1</b><div><strong>방문할 거래처를 선택하세요</strong><span>지도에서 여러 곳을 선택할 수 있어요.</span></div></li><li><b>2</b><div><strong>거래처 오른쪽의 ‘끌기’ 버튼을 잡으세요</strong><span>마우스 왼쪽 버튼으로 ‘끌기’를 누른 채 날짜로 끌고, 날짜 위에서 놓으세요.</span><figure class="mvc-drag-demo" aria-label="파란 거래처 카드 오른쪽 끌기 버튼을 잡아 달력 날짜로 끄는 예시"><div class="mvc-demo-row"><div class="mvc-demo-marker"><i>✓</i><div>예시 거래처</div><em class="mvc-demo-grip"><svg class="mvc-mouse-icon" viewBox="0 0 28 38" aria-hidden="true" focusable="false"><rect x="3" y="2" width="22" height="34" rx="11"/><path class="mvc-mouse-left" d="M14 3C8 3 4 7 4 13v4h10Z"/><path class="mvc-mouse-seam" d="M14 3v14M4 17h20"/><path class="mvc-mouse-wheel" d="M14 9v4"/></svg><span>끌기</span><span class="mvc-grip-arrow" aria-hidden="true">↗</span></em></div><div class="mvc-demo-arrow" aria-hidden="true">→</div><div class="mvc-demo-day"><small>달력</small><b>15</b></div></div><div class="mvc-demo-motion" aria-hidden="true"><div class="mvc-demo-traveler"><div class="mvc-demo-carried">✓ 거래처</div><svg class="mvc-mouse-icon" viewBox="0 0 28 38" aria-hidden="true" focusable="false"><rect x="3" y="2" width="22" height="34" rx="11"/><path class="mvc-mouse-left" d="M14 3C8 3 4 7 4 13v4h10Z"/><path class="mvc-mouse-seam" d="M14 3v14M4 17h20"/><path class="mvc-mouse-wheel" d="M14 9v4"/></svg></div></div><figcaption><b>마우스 왼쪽 버튼을 누른 채 → 날짜에서 놓기</b><div class="mvc-demo-steps" aria-hidden="true"><span>① 왼쪽 버튼을 꾹 눌러요</span><span>② 누른 채 달력으로 끌어요</span><span>③ 날짜 위에서 놓아요</span></div></figcaption></figure><span>여러 곳을 선택했다면 위쪽 ‘○곳 달력으로 끌기’ 버튼을 잡아 한 번에 옮길 수도 있어요.</span></div></li><li><b>3</b><div><strong>날짜를 눌러 기록을 관리하세요</strong><span>메모 수정, 방문 완료, 등록 해제가 가능해요.</span></div></li></ol><p class="mvc-guide-note">끌기가 어려우면 거래처를 선택한 뒤 날짜를 눌러 등록하세요. 잘못 놓았을 때는 ‘실행 취소’로 되돌릴 수 있어요.</p><button type="button" class="mvc-guide-start">확인 · 일정 만들기</button></div>';
 const mobileGuide=el('div',undefined,'mvc-mobile-guide');
 mobileGuide.innerHTML='<ol><li><b>1</b><div><strong>지도에서 거래처를 선택하세요</strong><span>방문할 건물을 하나씩 누르면 여러 곳을 선택할 수 있습니다.</span></div></li><li><b>2</b><div><strong>아래의 ‘날짜 정하기’를 누르세요</strong><span>선택한 거래처는 유지되고 달력이 크게 열립니다.</span></div></li><li><b>3</b><div><strong>날짜를 선택하고 방문 등록</strong><span>메모를 남기고 ‘방문 등록’을 누르면 저장됩니다. 기존 일정도 날짜를 눌러 관리하세요.</span></div></li></ol>';
 guide.querySelector('.mvc-guide-start').before(mobileGuide);

 const guidePreferenceKey='manager-visit-guide-hidden-v1:'+(document.getElementById('manager-quickmemo')?.dataset.scope||'browser');
 let guideSuppressed=false;try{guideSuppressed=localStorage.getItem(guidePreferenceKey)==='1';}catch{}
 const guideChoice=el('label',undefined,'mvc-guide-never'),guideCheck=el('input');guideCheck.type='checkbox';guideCheck.checked=guideSuppressed;
 guideChoice.append(guideCheck,el('span','이제 익숙해요 · 다시 보지 않기'));guide.querySelector('.mvc-guide-start').before(guideChoice);
 guideCheck.addEventListener('change',()=>{guideSuppressed=guideCheck.checked;try{if(guideSuppressed)localStorage.setItem(guidePreferenceKey,'1');else localStorage.removeItem(guidePreferenceKey);}catch{}});
 const helpButton=el('button','?','mvc-guide-help');helpButton.type='button';helpButton.setAttribute('aria-label','방문일정 등록 방법 다시 보기');helpButton.setAttribute('aria-haspopup','dialog');helpButton.title='등록 방법';helpButton.onclick=()=>showGuide(true);q('.mvc-close').before(helpButton);
 document.body.append(guide);
 const dismissGuide=()=>guide.close();guide.querySelector('.mvc-guide-close').onclick=dismissGuide;guide.querySelector('.mvc-guide-start').onclick=dismissGuide;
 guide.addEventListener('close',()=>{if(dialog.open){q('.mvc-fit').focus({preventScroll:true});ensureVisitMap();}});
 function positionHandDemo(){
  if(!guide.open||mobileMedia.matches)return;
  const demo=guide.querySelector('.mvc-drag-demo'),grip=demo.querySelector('.mvc-demo-grip'),day=demo.querySelector('.mvc-demo-day');
  const frame=demo.getBoundingClientRect(),from=grip.getBoundingClientRect(),to=day.getBoundingClientRect();
  demo.style.setProperty('--hand-x',(from.left+from.width/2-frame.left-26)+'px');
  demo.style.setProperty('--hand-y',(from.top+from.height/2-frame.top-14)+'px');
  demo.style.setProperty('--hand-dx',(to.left+to.width/2-from.left-from.width/2)+'px');
  demo.style.setProperty('--hand-dy',(to.top+to.height/2-from.top-from.height/2)+'px');
 }
 if(typeof ResizeObserver!=='undefined')new ResizeObserver(positionHandDemo).observe(guide.querySelector('.mvc-drag-demo'));
 window.addEventListener('resize',positionHandDemo);
 function showGuide(force=false){if(guideSuppressed&&!force)return;guideCheck.checked=guideSuppressed;guide.classList.toggle('is-mobile-guide',mobileMedia.matches);guide.querySelector('.mvc-guide-content>p').textContent=mobileMedia.matches?'지도에서 선택하고, 날짜를 정해 등록하세요.':'지도에서 고르고, 달력에 놓으면 끝입니다.';if(!guide.open){guide.showModal();guide.querySelector('.mvc-guide-start').focus({preventScroll:true});positionHandDemo();}}

 const reviewDialog=el('dialog',undefined,'mvc-visit-review');reviewDialog.setAttribute('aria-labelledby','mvr-title');
 reviewDialog.innerHTML='<header><div><small class="mvr-progress"></small><h2 id="mvr-title">이 거래처에 방문하셨나요?</h2></div><button type="button" class="mvr-close" aria-label="나머지 방문 확인은 나중에">×</button></header><div class="mvr-body"><strong class="mvr-name"></strong><p class="mvr-date"></p><label>실제 방문일<input type="date" class="mvr-actual"></label><small class="mvr-hint">실제 방문일은 예정일이 기본입니다. ‘미방문’을 누르면 방문 등록을 해제하고 미등록 거래처로 분류합니다.</small><p class="mvr-error" role="status"></p><button type="button" class="mvr-reload" hidden>목록 다시 확인</button><div class="mvr-actions"><button type="button" class="mvr-missed">미방문</button><button type="button" class="mvr-done">✓ 방문 완료</button></div><button type="button" class="mvr-later">나머지는 나중에 확인</button></div>';
 document.body.append(reviewDialog);const vr=s=>reviewDialog.querySelector(s);let reviewQueue=[],reviewedCount=0,reviewTotal=0,reviewSaving=false,reviewVersion=0;
 function paintVisitReview(){
  const row=reviewQueue[0];if(!row)return;
  vr('.mvr-progress').textContent='지난 방문 일정 확인 · '+(reviewedCount+1)+' / '+reviewTotal;
  vr('.mvr-name').textContent=row.name;vr('.mvr-date').textContent='방문 예정일 '+row.date.replaceAll('-','.')+(row.time?' · '+row.time:'');
  vr('.mvr-actual').value=row.date;vr('.mvr-actual').max=today();vr('.mvr-error').textContent='';vr('.mvr-reload').hidden=true;
 }
 async function fetchVisitReview(){
  const version=++reviewVersion;
  const response=await visitFetch('/manager_visits.php?review_visits=1',{credentials:'same-origin',cache:'no-store'}),data=await response.json();
  if(!response.ok||!data.ok)throw Error(data.error||'지난 방문 일정을 불러오지 못했습니다.');
  if(version!==reviewVersion||!dialog.open)return false;
  reviewQueue=data.items||[];reviewTotal=reviewedCount+Number(data.total||0);
  if(!reviewQueue.length)return false;paintVisitReview();return true;
 }
 async function startVisitReview(){
  if(walkthroughDemo)return false;reviewedCount=0;
  try{if(!await fetchVisitReview())return false;if(!dialog.open)return false;reviewDialog.showModal();vr('.mvr-done').focus({preventScroll:true});return true;}
  catch(e){notice(e.message);return false;}
 }
 async function answerVisitReview(status){
  const row=reviewQueue[0];if(reviewSaving||!row)return;
  const actual=vr('.mvr-actual').value;if(status==='completed'&&(!vr('.mvr-actual').reportValidity()||!actual||actual>today())){vr('.mvr-error').textContent='실제 방문일을 오늘까지의 날짜로 선택해 주세요.';return;}
  reviewSaving=true;reviewDialog.querySelectorAll('button,input').forEach(n=>n.disabled=true);vr('.mvr-error').textContent='저장 중…';
  try{
   const body=new URLSearchParams({act:'confirm_visits',visits:JSON.stringify([{id:row.id,revision:row.revision}]),status,visited_date:status==='completed'?actual:'',csrf:typeof CSRF==='string'?CSRF:''});
   const res=await visitFetch('/manager_visits.php',{method:'POST',credentials:'same-origin',body}),data=await res.json();if(!res.ok||!data.ok)throw Error(data.error||'방문 상태를 저장하지 못했습니다.');
   reviewedCount++;reviewQueue.shift();
   if(!reviewQueue.length&&reviewedCount<reviewTotal)await fetchVisitReview();
   if(reviewQueue.length)paintVisitReview();else{reviewDialog.close();showToast('지난 방문 일정 '+reviewedCount+'건을 확인했습니다.');}
   await load();
  }catch(e){vr('.mvr-error').textContent=e.message;vr('.mvr-reload').hidden=false;}
  finally{reviewSaving=false;reviewDialog.querySelectorAll('button,input').forEach(n=>n.disabled=false);}
 }
 const pauseVisitReview=()=>{if(!reviewSaving){reviewVersion++;reviewDialog.close();}};
 vr('.mvr-close').onclick=vr('.mvr-later').onclick=pauseVisitReview;reviewDialog.addEventListener('cancel',e=>{e.preventDefault();pauseVisitReview();});
 vr('.mvr-done').onclick=()=>answerVisitReview('completed');vr('.mvr-missed').onclick=()=>answerVisitReview('missed');
 vr('.mvr-reload').onclick=async()=>{if(reviewSaving)return;try{if(!await fetchVisitReview())reviewDialog.close();}catch(e){vr('.mvr-error').textContent=e.message;}};

 // Confirmation itself never writes: YES opens the existing receipt editor; NO is local to this entry.
 const receiptReview=el('dialog',undefined,'mvc-dialog mvc-receipt-review');receiptReview.setAttribute('aria-labelledby','mvc-receipt-review-title');
 receiptReview.innerHTML='<header class="mvc-header"><div><small>미접수 보고서 확인</small><h2 id="mvc-receipt-review-title">접수하셨습니까?</h2></div><button type="button" class="mrr-close" aria-label="접수 확인 닫기">×</button></header><div class="mrr-body"><p>접수 예정일이 지난 보고서입니다. 실제 접수 여부를 각각 확인해 주세요.</p><div class="mrr-list"></div><p class="mrr-error" role="status"></p><button type="button" class="mrr-later">나중에 확인</button></div>';
 document.body.append(receiptReview);const rr=s=>receiptReview.querySelector(s);let receiptReviewActive=false,receiptReviewResume=false,receiptReviewVersion=0,receiptReviewItems=[];const receiptReviewSkipped=new Set();
 function paintReceiptReview(){
  rr('.mrr-list').replaceChildren();const pending=receiptReviewItems.filter(r=>!receiptReviewSkipped.has(r.visit_id));
  for(const r of pending){const card=el('article',undefined,'mrr-item');card.append(el('strong',r.name),el('small','접수 예정 '+r.date+' · 미접수'),el('small','보고 마감 '+(r.deadline||'계산 대기')));const actions=el('div',undefined,'mrr-actions'),no=el('button','NO · 미접수'),yes=el('button','YES · 접수했어요');no.type=yes.type='button';no.className='mrr-no';yes.className='mrr-yes';no.onclick=()=>{receiptReviewSkipped.add(r.visit_id);paintReceiptReview();if(!rr('.mrr-list').children.length){receiptReviewActive=false;receiptReview.close();showToast('미접수 상태를 유지합니다. 다음 업무모드 진입 시 다시 확인합니다.');}};yes.onclick=()=>{receiptReviewResume=true;receiptReview.close();completeReport(r);};actions.append(no,yes);card.append(actions);rr('.mrr-list').append(card);}
 }
 async function fetchReceiptReview(){
  const version=receiptReviewVersion;const res=await visitFetch('/manager_visits.php?review_reports=1',{credentials:'same-origin',cache:'no-store'}),data=await res.json();
  if(!res.ok||!data.ok)throw Error(data.error||'미접수 목록을 불러오지 못했습니다.');if(version!==receiptReviewVersion||!dialog.open)return false;
  receiptReviewItems=Array.isArray(data.items)?data.items:[];paintReceiptReview();return !!rr('.mrr-list').children.length;
 }
 async function startReceiptReview(){
  if(demoMode||walkthroughDemo)return false;receiptReviewVersion++;receiptReviewSkipped.clear();receiptReviewActive=true;receiptReviewResume=false;
  try{if(!await fetchReceiptReview()){receiptReviewActive=false;return false;}receiptReview.showModal();rr('.mrr-close').focus({preventScroll:true});return true;}catch(e){receiptReviewActive=false;showToast(e.message);return false;}
 }
 function pauseReceiptReview(){receiptReviewActive=receiptReviewResume=false;receiptReviewVersion++;receiptReview.close();}
 rr('.mrr-close').onclick=rr('.mrr-later').onclick=pauseReceiptReview;receiptReview.addEventListener('cancel',e=>{e.preventDefault();pauseReceiptReview();});
 async function resumeReceiptReview(){
  if(!receiptReviewActive||!receiptReviewResume||!dialog.open||reportDialog.open||followDialog.open)return;receiptReviewResume=false;
  try{if(await fetchReceiptReview()){receiptReview.showModal();rr('.mrr-close').focus({preventScroll:true});}else receiptReviewActive=false;}catch(e){receiptReviewActive=false;showToast(e.message);}
 }
 reportDialog.addEventListener('close',()=>queueMicrotask(resumeReceiptReview));followDialog.addEventListener('close',()=>queueMicrotask(resumeReceiptReview));

 async function open(uid='',review=true){
  const justOpened=!dialog.open;
  if(justOpened){trigger=document.activeElement;updateMobileView();dialog.showModal();lockPageScroll();}
  form.hidden=true;
  if(!buildingsReady){
   notice('거래처 목록을 확인하고 있습니다.');
   try{const res=await fetch('/manager_buildings.php',{credentials:'same-origin',cache:'no-store'});const data=await res.json();if(!res.ok||!data.ok||!Array.isArray(data.buildings))throw Error('목록 오류');applyBuildings(data.buildings);}
   catch{notice('거래처 목록을 불러오지 못했습니다. 달력을 다시 열어 주세요.');return;}
  }
  if(!dialog.open)return;
  await load();if(!dialog.open)return;
  if(!walkthroughDemo)window.visitInboxRefresh?.(true);
  if(uid&&buildings.some(b=>b.uid===uid))chosen.add(uid);ensureVisitMap();render();
  if(justOpened&&review&&!uid){if(!await startReceiptReview()&&!await startVisitReview()&&dialog.open)showGuide();}
 }

 opener.onclick=()=>open();q('.mvc-close').onclick=()=>{if(!busy)dialog.close();};dialog.addEventListener('cancel',e=>{if(busy)e.preventDefault();});dialog.addEventListener('close',()=>{receiptReviewActive=receiptReviewResume=false;receiptReviewVersion++;if(receiptReview.open)receiptReview.close();reviewVersion++;if(reviewDialog.open)reviewDialog.close();if(summaryDialog.open)summaryDialog.close();if(followDialog.open)followDialog.close();if(reportDialog.open)reportDialog.close();if(guide.open)guide.close();if(dayDialog.open)dayDialog.close();unlockPageScroll();trigger?.focus({preventScroll:true});});
 dialog.querySelectorAll('[data-shift]').forEach(b=>b.onclick=()=>{if(busy)return;const [y,m]=month.split('-').map(Number),next=new Date(y,m-1+Number(b.dataset.shift),1);if(next.getFullYear()<2000||next.getFullYear()>2100)return;mapVisitDate='';month=dayKey(next).slice(0,7);selected=month+'-01';form.hidden=true;load();drawVisitMap(approvalOnly);});
 q('.mvc-today').onclick=()=>{if(busy)return;if(mapVisitDate)mapVisitDate=today();selected=today();month=selected.slice(0,7);form.hidden=true;load();drawVisitMap(approvalOnly);};q('.mvc-add').onclick=()=>edit();q('.mvc-edit-close').onclick=()=>{if(!busy)form.hidden=true;};field('status').onchange=syncActual;
 form.onsubmit=async e=>{
  e.preventDefault();if(busy||!form.reportValidity())return;busy=true;syncDayNotes();q('.mvc-error').textContent='';const body=new URLSearchParams(new FormData(form));body.set('csrf',typeof CSRF!=='undefined'?CSRF:'');if(editing){body.set('id',editing.id);body.set('revision',String(editing.revision));}
  form.querySelectorAll('button,input,select,textarea').forEach(n=>n.disabled=true);q('.mvc-save').textContent='저장 중…';
  try{const res=await visitFetch('/manager_visits.php',{method:'POST',credentials:'same-origin',body});const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.error||'저장하지 못했습니다.');if(!dayDialog.open){selected=data.visit.date;month=selected.slice(0,7);}form.hidden=true;await load();q('.mvc-batch-result').textContent=data.visit.date.replaceAll('-','.')+' 방문 일정을 저장했습니다.';offerReports(data.proposals);}
  catch(e){q('.mvc-error').textContent=e.message||'연결 상태를 확인해 주세요.';}
  finally{busy=false;form.querySelectorAll('button,input,select,textarea').forEach(n=>n.disabled=false);q('.mvc-save').textContent='저장';render();}
 };
 setInterval(()=>{if(dialog.open&&!document.hidden&&!busy&&!loading&&!dayDialog.open&&!reportDialog.open&&!followDialog.open&&!guide.open&&!reviewDialog.open&&!receiptReview.open)load();},60000);
 document.addEventListener('manager-buildings-updated',e=>applyBuildings(e.detail?.buildings));
 document.addEventListener('manager-visit-open',e=>open(e.detail?.uid||''));
 // Notification URLs resolve to the current account's authorized planner records.
 const pushNotice=new URLSearchParams(location.search).get('visit_notice');
 async function openPushNotice(pushNotice){
  try{
   if(pushNotice?.startsWith('digest_')){await window.visitDigestOpen?.(pushNotice.slice(7));return;}
   if(pushNotice==='test'){await open('',false);if(guide.open)guide.close();return;}
   const res=await fetch('/visit_push_api.php?event='+encodeURIComponent(pushNotice),{credentials:'same-origin',cache:'no-store'}),data=await res.json();if(!res.ok||!data.ok)throw Error(data.error||'일정을 열지 못했습니다.');
   if(data.test_event){
    const item=data.test_event,event=item.test_schedule;
    await open('',false);if(guide.open)guide.close();month=event.date.slice(0,7);selected=event.date;await load();render();
    const preview=el('dialog',undefined,'mvc-dialog mvc-report-dialog');
    preview.setAttribute('aria-labelledby','mvc-test-event-title');
    const header=el('header',undefined,'mvc-header'),heading=el('div'),title=el('h2','가상 방문일정');title.id='mvc-test-event-title';
    heading.append(el('small','TEST · 알림 이동 확인'),title);const close=el('button','×');close.type='button';close.setAttribute('aria-label','가상 일정 닫기');header.append(heading,close);
    const body=el('div',undefined,'mvc-report-form'),dates=el('div',undefined,'mvc-report-dates'),dateBox=el('div'),timeBox=el('div');
    dateBox.append(el('span','방문 예정일'),el('strong',event.date));timeBox.append(el('span','방문 시간'),el('strong',event.time));dates.append(dateBox,timeBox);
    body.append(el('h3',event.name),dates,el('p','알림에서 해당 일정 상세로 이동했습니다. 실제 거래처·일정·통계에는 포함되지 않는 테스트입니다.'));
    preview.append(header,body);dialog.append(preview);close.onclick=()=>preview.close();preview.addEventListener('close',()=>preview.remove());preview.showModal();return;
   }
   await open('',false);if(guide.open)guide.close();month=data.event.due.slice(0,7);selected=data.event.due;await load();
   if(data.report)linkedReports[data.event.visit_id]=data.report;if(data.followup)followupRows[data.event.visit_id]=data.followup;
   if(data.event.kind==='report')showReport(data.report);else if(['work','filing'].includes(data.event.kind))openFollowup(data.event.visit_id);else{render();openDay();edit(data.visit);}
  }catch(e){notice(e.message);}
 }
 document.addEventListener('manager-visit-detail-open',e=>openPushNotice(e.detail?.key||''));
 if(pushNotice)queueMicrotask(()=>openPushNotice(pushNotice));
})();
