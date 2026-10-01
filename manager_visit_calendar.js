(()=>{
 const host=document.querySelector('.mm-management-actions');if(!host)return;
 const el=(tag,text,cls)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=text;if(cls)n.className=cls;return n;};
 const dayKey=d=>`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
 const today=()=>dayKey(new Date());let month=today().slice(0,7),selected=today(),rows=[],dayMeta={},daysReady=false,buildings=[],loading=false,busy=false,request=0,trigger=null;
 const pendingMemoGlow=new Set();
 const opener=el('button','방문 일정 모드','mvc-open');opener.type='button';opener.setAttribute('aria-haspopup','dialog');host.prepend(opener);
 const dialog=el('dialog',undefined,'mvc-dialog mvc-planner');dialog.setAttribute('aria-labelledby','mvc-title');
 dialog.innerHTML='<header class="mvc-header"><div><small>VISIT CALENDAR</small><h2 id="mvc-title">방문 일정 모드</h2><p>거래처를 선택하고 달력 날짜로 끌어 놓으세요. 날짜를 눌러 등록할 수도 있습니다.</p></div><button type="button" class="mvc-close" aria-label="방문 일정 닫기">×</button></header><div class="mvc-toolbar"><div><button type="button" data-shift="-1" aria-label="이전 달">‹</button><strong class="mvc-month"></strong><button type="button" data-shift="1" aria-label="다음 달">›</button><button type="button" class="mvc-today">오늘</button></div><span>● 예정 <i>● 완료</i></span></div><p class="mvc-notice" role="status"></p><div class="mvc-layout"><section><div class="mvc-week"></div><div class="mvc-grid" aria-label="월 방문 달력"></div></section><aside class="mvc-side"><div class="mvc-dayhead"><h3></h3><button type="button" class="mvc-add">＋ 일정</button></div><div class="mvc-daylist"></div><form class="mvc-form" hidden><h3 class="mvc-formtitle">방문 일정 추가</h3><label>거래처<select name="uid" required></select></label><div class="mvc-fields"><label>방문 예정일<input type="date" name="date" required></label><label>예정 시간<input type="time" name="time"></label></div><label>상태<select name="status"><option value="planned">방문 예정</option><option value="completed">방문 완료</option><option value="cancelled">일정 취소</option></select></label><label class="mvc-actual" hidden>실제 방문일<input type="date" name="visited_date"></label><label>방문 메모<textarea name="memo" rows="3" maxlength="2000" placeholder="방문 목적이나 확인한 내용을 남겨 주세요."></textarea></label><p class="mvc-error" role="alert"></p><div class="mvc-formactions"><button type="button" class="mvc-edit-close">닫기</button><button type="submit" class="mvc-save">저장</button></div></form></aside></div>';
 document.body.append(dialog);
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
  try{const res=await fetch('/manager_visits.php',{method:'POST',credentials:'same-origin',body});const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.error||'날짜 설정을 저장하지 못했습니다.');dayMeta[date]=data.day;if(data.day.memo)pendingMemoGlow.add(date);else pendingMemoGlow.delete(date);fillDayNotes();q('.mvc-day-note-status').textContent='저장했습니다.';}
  catch(error){q('.mvc-day-note-status').textContent=error.message||'연결을 확인하고 다시 저장해 주세요.';}
  finally{busy=false;render();}
 };
 q('.mvc-day-close').onclick=()=>{if(canCloseDay())dayDialog.close();};
 dayDialog.addEventListener('cancel',e=>{if(!canCloseDay())e.preventDefault();});
 dayDialog.addEventListener('close',()=>{daySnapshot.date='';if(dialog.open)load();if(dialog.open)dialog.querySelector('.mvc-cell.is-selected')?.focus({preventScroll:true});});
 function openDay(){if(!dayDialog.open){fillDayNotes();dayDialog.showModal();}q('.mvc-day-close').focus();q('.mvc-batch-result').textContent='';}

 let approvalOnly=false;
 const chosen=new Set(),locationCache=new Map();let visitMap=null,visitMarkers=null,mapReady=false,batchToken=null,batchFingerprint='';
 const left=el('section',undefined,'mvc-map-panel');
 left.innerHTML='<div class="mvc-map-title"><div><small>거래처 지도</small><h3>방문할 곳을 선택하세요</h3></div><div class="mvc-map-actions"><button type="button" class="mvc-unselect" hidden>선택 해제</button><button type="button" class="mvc-fit">전체 보기</button></div></div><div class="mvc-plan-map" aria-label="방문 거래처 선택 지도"></div><div class="mvc-map-legend"><b class="scheduled">● 방문 등록</b><b class="completed">● 방문 완료</b><b class="month">● 이달 다른 날짜</b><b class="chosen">● 선택 중</b></div>';

 const layout=q('.mvc-layout'),right=el('section',undefined,'mvc-calendar-panel');while(layout.firstChild)right.append(layout.firstChild);layout.append(left,right);
 let dragSelection=null,ignoreClickUntil=0;
 const dragHint=el('button','달력으로 끌기','mvc-drag-handle');dragHint.type='button';dragHint.draggable=true;dragHint.hidden=true;q('.mvc-map-actions').prepend(dragHint);
 const toast=el('div',undefined,'mvc-toast');toast.hidden=true;toast.setAttribute('role','status');dialog.append(toast);
 function showToast(message,undoToken=null){
  toast.replaceChildren(el('span',message));toast.hidden=false;
  if(undoToken){const undo=el('button','실행 취소');undo.type='button';undo.onclick=async()=>{
    if(busy)return;busy=true;undo.disabled=true;
    try{const res=await fetch('/manager_visits.php',{method:'POST',credentials:'same-origin',body:new URLSearchParams({act:'undo_batch',batch_token:undoToken,csrf:typeof CSRF!=='undefined'?CSRF:''})});const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.error||'실행 취소하지 못했습니다.');await load();showToast(data.undone+'곳 방문 등록을 되돌렸습니다.');}
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
 function showDragHint(handle){
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
  const records=rows.filter(r=>r.uid===uid&&r.status!=='cancelled');
  if(records.some(r=>r.status==='completed'&&r.visited_date===selected))return 'completed';
  if(records.some(r=>r.date===selected))return 'scheduled';
  if(records.some(r=>r.date.startsWith(month)||(r.status==='completed'&&r.visited_date.startsWith(month))))return 'month';
  return '';
 }
 function paintVisitMarker(marker){
  const state=visitState(marker.visitUid),picked=chosen.has(marker.visitUid),node=marker.getElement()?.querySelector('.mvc-pin');if(!node)return;
  const approved=approvalBuildings().find(b=>b.uid===marker.visitUid);
  markApproval(node,approved);
  marker.setZIndexOffset(picked?200000:approved?100000:0);
  let approvalTag=node.querySelector('.mvc-pin-approval');
  if(!approvalTag){approvalTag=el('small','','mvc-pin-approval');node.append(approvalTag);}
  approvalTag.textContent=approvalLabel(approved);approvalTag.hidden=!approved;
  for(const value of ['scheduled','completed','month'])node.classList.toggle('visit-'+value,state===value);
  let grip=node.querySelector('.mvc-pin-grip');if(picked&&!grip){grip=el('button',undefined,'mvc-pin-grip');grip.innerHTML='<svg class="mvc-mouse-icon" viewBox="0 0 28 38" aria-hidden="true" focusable="false"><rect x="3" y="2" width="22" height="34" rx="11"/><path class="mvc-mouse-left" d="M14 3C8 3 4 7 4 13v4h10Z"/><path class="mvc-mouse-seam" d="M14 3v14M4 17h20"/><path class="mvc-mouse-wheel" d="M14 9v4"/></svg><span>끌기</span><span class="mvc-grip-arrow" aria-hidden="true">↗</span>';grip.type='button';grip.draggable=true;grip.setAttribute('aria-label','선택한 거래처를 달력으로 끌기');grip.title='이 버튼을 누른 채 달력 날짜로 끌어 놓으세요';bindDragHandle(grip);node.append(grip);}if(grip){grip.hidden=!picked;grip.disabled=busy||loading;}
  node.classList.toggle('chosen',picked);node.querySelector('b').textContent=picked?'✓':state==='completed'?'✓':state?'●':'＋';
  let tag=node.querySelector('.mvc-pin-status');if(!tag){tag=el('small',undefined,'mvc-pin-status');node.append(tag);}
  tag.textContent=state==='completed'?'방문 완료':state==='scheduled'?'방문 등록':state==='month'?'이달 등록':'';tag.hidden=!state;
  marker.getElement()?.setAttribute('aria-pressed',String(picked));
 }
 function refreshSelection(){
  q('.mvc-unselect').hidden=!chosen.size;dragHint.hidden=!chosen.size;dragHint.disabled=busy||loading;dragHint.textContent=chosen.size+'곳 달력으로 끌기';
  q('.mvc-map-title h3').textContent='거래처 지도';
  
  batch.hidden=!chosen.size;
  q('.mvc-selected-names').textContent=buildings.filter(b=>chosen.has(b.uid)).map(b=>b.name).join(' · ');
  const save=q('.mvc-batch-save');save.disabled=busy||loading||!chosen.size;save.textContent=busy?'저장 중…':chosen.size+'곳 방문 등록';
  if(visitMarkers)visitMarkers.eachLayer(paintVisitMarker);
 }
 function drawVisitMap(fit=false){
  if(!visitMap)return;visitMarkers.clearLayers();const bounds=[];
  const approvalIds=new Set(approvalBuildings().map(b=>b.uid));
  buildings.forEach(b=>{
   if(approvalOnly&&!approvalIds.has(b.uid))return;
   const p=point(b);if(!p)return;bounds.push(p);const html=el('div',undefined,'mvc-pin'+(chosen.has(b.uid)?' chosen':''));html.append(el('b',chosen.has(b.uid)?'✓':'＋'),el('span',b.name));
   const marker=L.marker(p,{icon:L.divIcon({className:'mvc-map-marker',html,iconSize:null,iconAnchor:[14,18]}),title:b.name});marker.visitUid=b.uid;marker.on('click',()=>toggleBuilding(b.uid));marker.addTo(visitMarkers);paintVisitMarker(marker);
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
  if(!dialog.open)return;
  if(typeof L==='undefined'){q('.mvc-plan-map').textContent='지도를 불러오지 못했습니다. 달력에서 날짜를 누르고 일정 추가로 거래처를 선택해 주세요.';return;}
  if(!visitMap){visitMap=L.map(q('.mvc-plan-map'),{scrollWheelZoom:true}).setView([37.65,126.8],10);L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a>'}).addTo(visitMap);visitMarkers=L.featureGroup().addTo(visitMap);visitMap.on('zoomend',spreadVisitPins);if(typeof ResizeObserver!=='undefined')new ResizeObserver(()=>{if(dialog.open)visitMap.invalidateSize({pan:false});}).observe(q('.mvc-plan-map'));}
  requestAnimationFrame(()=>{visitMap.invalidateSize();drawVisitMap(!mapReady);mapReady=true;});
 }
 q('.mvc-fit').onclick=()=>drawVisitMap(true);q('.mvc-unselect').onclick=()=>{if(!busy){chosen.clear();refreshSelection();}};
 q('.mvc-batch-save').onclick=()=>saveBatch(selected,[...chosen].sort(),false);
 async function saveBatch(date,ids,fromDrag){
  if(busy||loading||!ids.length)return;
  selected=date;const memo=fromDrag?'':q('.mvc-batch-memo').value.trim(),fingerprint=JSON.stringify([ids,date,memo]);
  if(fingerprint!==batchFingerprint){batchFingerprint=fingerprint;batchToken=Array.from(crypto.getRandomValues(new Uint8Array(16)),b=>b.toString(16).padStart(2,'0')).join('');}
  busy=true;refreshSelection();q('.mvc-batch-result').textContent='';if(fromDrag)showToast('방문 일정을 저장하고 있습니다…');
  const body=new URLSearchParams({act:'batch',uids:JSON.stringify(ids),date,memo,batch_token:batchToken,csrf:typeof CSRF!=='undefined'?CSRF:''});
  try{const res=await fetch('/manager_visits.php',{method:'POST',credentials:'same-origin',body});const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.error||'일정을 저장하지 못했습니다.');ids.forEach(uid=>chosen.delete(uid));batchFingerprint='';q('.mvc-batch-memo').value='';await load();const message=date.replaceAll('-','.')+' · '+data.created+'곳 등록 완료'+(data.skipped?' (기존 일정 '+data.skipped+'곳 제외)':'');q('.mvc-batch-result').textContent=message;showToast(message,data.created?data.undo_token:null);if(dayDialog.open&&!dayDirty())dayDialog.close();}
  catch(e){q('.mvc-batch-result').textContent=e.message||'연결 상태를 확인해 주세요.';showToast(e.message||'저장하지 못했습니다. 다시 시도해 주세요.');}
  finally{busy=false;render();}
 }
 document.addEventListener('manager-building-locations',e=>{for(const p of e.detail?.points||[])locationCache.set(p.uid,[p.lat,p.lng]);if(dialog.open){ensureVisitMap();render();}});

 const approvalSummary=el('button','','mvc-approval-summary');approvalSummary.type='button';approvalSummary.setAttribute('aria-pressed','false');q('.mvc-toolbar').append(approvalSummary);
 approvalSummary.onclick=()=>{if(busy||loading)return;approvalOnly=!approvalOnly;render();drawVisitMap(true);};
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
 const statusText={planned:'예정',completed:'완료',cancelled:'취소'};
 for(const d of ['일','월','화','수','목','금','토'])q('.mvc-week').append(el('span',d));
 function notice(text=''){q('.mvc-notice').textContent=text;if(dayDialog.open&&text&&text!=='일정을 불러오고 있습니다.')q('.mvc-batch-result').textContent=text;}
 function listed(date){return rows.filter(r=>r.status!=='cancelled'&&(r.date===date||(r.status==='completed'&&r.visited_date===date))).sort((a,b)=>(a.time||'99').localeCompare(b.time||'99')||a.name.localeCompare(b.name,'ko'));}
 function render(){
  syncDayNotes();refreshSelection();
  const approved=approvalBuildings(),approvalByUid=new Map(approved.map(b=>[b.uid,b]));
  const annualCount=approved.filter(b=>approvalKind(b)==='approval').length;
  approvalSummary.textContent='사용승인월 '+annualCount+'곳 · 6개월 주기 '+(approved.length-annualCount)+'곳'+(approvalOnly?' · 전체 보기':' · 모아 보기');
  approvalSummary.setAttribute('aria-pressed',String(approvalOnly));approvalSummary.disabled=busy||loading;
  approvalSummary.title=approvalOnly?'누르면 지도에서 전체 거래처를 표시합니다.':'누르면 사용승인월 또는 6개월 주기에 해당하는 거래처를 모아 봅니다. 사용승인 월 기준 참고 표시이며, 점검 대상 여부를 판정하지 않습니다.';
  const [y,m]=month.split('-').map(Number);q('.mvc-month').textContent=`${y}년 ${m}월`;q('.mvc-dayhead h3').textContent=selected.replaceAll('-','.');q('.mvc-add').disabled=loading||busy;
  const grid=q('.mvc-grid');grid.replaceChildren();const first=new Date(y,m-1,1),days=new Date(y,m,0).getDate();grid.style.gridTemplateRows='repeat('+Math.ceil((first.getDay()+days)/7)+',minmax(0,1fr))';
  for(let i=0;i<first.getDay();i++)grid.append(el('div',undefined,'mvc-empty'));
  for(let d=1;d<=days;d++){
   const key=dayKey(new Date(y,m-1,d)),cell=el('button',undefined,'mvc-cell');cell.type='button';cell.classList.toggle('is-selected',key===selected);cell.classList.toggle('is-today',key===today());cell.setAttribute('aria-pressed',String(key===selected));cell.setAttribute('aria-label',key+' 일정 '+listed(key).length+'건');const dateLine=el('span',undefined,'mvc-date-line');dateLine.append(el('b',String(d)));cell.append(dateLine);
   const meta=dayMeta[key]||{};cell.classList.toggle('is-holiday',!!meta.holiday);
   cell.setAttribute('aria-label',key+(meta.holiday?' 휴일':'')+(meta.memo?' 메모 있음':'')+' 방문 일정 '+listed(key).length+'건');
   if(meta.holiday||meta.memo){const flags=el('span',undefined,'mvc-day-flags');flags.setAttribute('aria-hidden','true');if(meta.holiday)flags.append(el('span','휴','mvc-day-off-mark'));if(meta.memo){const icon=el('span',undefined,'mvc-day-memo-mark');icon.innerHTML='<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 3h12v10l-4 4H4zM12 17v-4h4M7 7h6M7 10h4"/></svg>';icon.append(el('span','메모'));cell.title=meta.memo;cell.setAttribute('aria-description','날짜 메모: '+meta.memo);if(pendingMemoGlow.has(key)&&!dayDialog.open&&daysReady){icon.classList.add('is-just-saved');pendingMemoGlow.delete(key);}flags.append(icon);}dateLine.append(flags);}
   const items=listed(key);items.slice(0,2).forEach(r=>{const chip=el('span',(r.status==='completed'&&r.visited_date===key?'✓ ':r.time?r.time+' ':'')+r.name,'mvc-chip '+r.status);markApproval(chip,approvalByUid.get(r.uid));cell.append(chip);});
   if(items.length>2){const more=el('small','외 '+(items.length-2)+'건');if(items.slice(2).some(r=>approvalByUid.has(r.uid))){more.classList.add('mvc-approval-more');more.textContent+=' · 주기 해당 포함';}cell.append(more);}
   bindDropDay(cell,key);cell.onclick=()=>{if(busy||loading||!daysReady||Date.now()<ignoreClickUntil)return;selected=key;form.hidden=true;render();openDay();};grid.append(cell);
  }
  const list=q('.mvc-daylist');list.replaceChildren();
  if(!listed(selected).length)list.append(el('p',loading?'일정을 불러오는 중입니다.':'등록된 방문 일정이 없습니다.','mvc-empty-copy'));
  listed(selected).forEach(r=>{
   const card=el('article',undefined,'mvc-record');markApproval(card,approvalByUid.get(r.uid));if(approvalByUid.has(r.uid))card.append(el('small',approvalLabel(approvalByUid.get(r.uid))+' · 사용승인일 '+(approvalByUid.get(r.uid).approval_date||approvalByUid.get(r.uid).approval_month+'월'),'mvc-approval-label'));card.append(el('small',(r.time||'시간 미지정')+' · '+statusText[r.status],r.status),el('strong',r.name),el('span',r.status==='completed'?'실제 방문 '+r.visited_date:'등록일 '+r.date));if(r.memo)card.append(el('p',r.memo));
   const actions=el('div',undefined,'mvc-record-actions'),change=el('button','메모·수정'),remove=el('button','등록 해제','mvc-remove');change.type=remove.type='button';change.disabled=remove.disabled=busy||loading;change.onclick=()=>edit(r);remove.onclick=()=>cancelVisit(r);actions.append(change,remove);card.append(actions);list.append(card);
  });
 }
 async function cancelVisit(row){
  if(busy)return;busy=true;form.hidden=true;render();q('.mvc-batch-result').textContent='';
  const body=new URLSearchParams({id:row.id,revision:String(row.revision),uid:row.uid,date:row.date,time:row.time||'',status:'cancelled',visited_date:'',memo:row.memo||'',csrf:typeof CSRF!=='undefined'?CSRF:''});
  try{const response=await fetch('/manager_visits.php',{method:'POST',credentials:'same-origin',body});const data=await response.json();if(!response.ok||!data.ok)throw new Error(data.error||'등록을 해제하지 못했습니다.');await load();q('.mvc-batch-result').textContent=row.name+' 방문 등록을 해제했습니다.';}
  catch(error){q('.mvc-batch-result').textContent=error.message||'연결 상태를 확인해 주세요.';}
  finally{busy=false;render();}
 }
 async function load(){
  const id=++request;loading=true;daysReady=false;rows=[];dayMeta={};notice('일정을 불러오고 있습니다.');render();
  try{const res=await fetch('/manager_visits.php?month='+encodeURIComponent(month),{credentials:'same-origin',cache:'no-store'});const data=await res.json();if(id!==request)return;if(!res.ok||!data.ok)throw new Error(data.error||'일정을 불러오지 못했습니다.');rows=data.visits;dayMeta=data.days||{};daysReady=true;notice('');}
  catch(e){if(id===request)notice(e.message||'연결 상태를 확인해 주세요.');}
  finally{if(id===request){loading=false;render();}}
 }
 function syncActual(){const complete=field('status').value==='completed';q('.mvc-actual').hidden=!complete;field('visited_date').required=complete;field('visited_date').max=today();if(complete&&!field('visited_date').value)field('visited_date').value=today();}
 function edit(row=null,uid=''){
  if(busy)return;editing=row;form.reset();form.hidden=false;q('.mvc-error').textContent='';q('.mvc-formtitle').textContent=row?'방문 일정 수정':'방문 일정 추가';
  const select=field('uid');select.replaceChildren();const blank=el('option','거래처를 선택해 주세요');blank.value='';select.append(blank);
  for(const b of buildings){const opt=el('option',b.name+(b.address?' · '+b.address:''));opt.value=b.uid;select.append(opt);}
  if(row&&!buildings.some(b=>b.uid===row.uid)){const opt=el('option',row.name+' (기존 방문 기록)');opt.value=row.uid;select.append(opt);}
  select.value=row?.uid||uid;field('date').value=row?.date||selected;field('time').value=row?.time||'';field('status').value=row?.status||'planned';field('visited_date').value=row?.visited_date||'';field('memo').value=row?.memo||'';syncActual();select.focus();
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
 guide.innerHTML='<div class="mvc-guide-content"><div class="mvc-guide-top"><span>VISIT PLANNER</span><button type="button" class="mvc-guide-close" aria-label="사용 안내 닫기">×</button></div><h2 id="mvc-guide-title">방문 일정, 이렇게 등록하세요</h2><p>지도에서 고르고, 달력에 놓으면 끝입니다.</p><ol><li><b>1</b><div><strong>방문할 거래처를 선택하세요</strong><span>지도에서 여러 곳을 선택할 수 있어요.</span></div></li><li><b>2</b><div><strong>거래처 오른쪽의 ‘끌기’ 버튼을 잡으세요</strong><span>마우스 왼쪽 버튼으로 ‘끌기’를 누른 채 날짜로 끌고, 날짜 위에서 놓으세요.</span><figure class="mvc-drag-demo" aria-label="파란 거래처 카드 오른쪽 끌기 버튼을 잡아 달력 날짜로 끄는 예시"><div class="mvc-demo-row"><div class="mvc-demo-marker"><i>✓</i><div>예시 거래처</div><em class="mvc-demo-grip"><svg class="mvc-mouse-icon" viewBox="0 0 28 38" aria-hidden="true" focusable="false"><rect x="3" y="2" width="22" height="34" rx="11"/><path class="mvc-mouse-left" d="M14 3C8 3 4 7 4 13v4h10Z"/><path class="mvc-mouse-seam" d="M14 3v14M4 17h20"/><path class="mvc-mouse-wheel" d="M14 9v4"/></svg><span>끌기</span><span class="mvc-grip-arrow" aria-hidden="true">↗</span></em></div><div class="mvc-demo-arrow" aria-hidden="true">→</div><div class="mvc-demo-day"><small>달력</small><b>15</b></div></div><div class="mvc-demo-motion" aria-hidden="true"><div class="mvc-demo-traveler"><div class="mvc-demo-carried">✓ 거래처</div><svg class="mvc-mouse-icon" viewBox="0 0 28 38" aria-hidden="true" focusable="false"><rect x="3" y="2" width="22" height="34" rx="11"/><path class="mvc-mouse-left" d="M14 3C8 3 4 7 4 13v4h10Z"/><path class="mvc-mouse-seam" d="M14 3v14M4 17h20"/><path class="mvc-mouse-wheel" d="M14 9v4"/></svg></div></div><figcaption><b>마우스 왼쪽 버튼을 누른 채 → 날짜에서 놓기</b><div class="mvc-demo-steps" aria-hidden="true"><span>① 왼쪽 버튼을 꾹 눌러요</span><span>② 누른 채 달력으로 끌어요</span><span>③ 날짜 위에서 놓아요</span></div></figcaption></figure><span>여러 곳을 선택했다면 위쪽 ‘○곳 달력으로 끌기’ 버튼을 잡아 한 번에 옮길 수도 있어요.</span></div></li><li><b>3</b><div><strong>날짜를 눌러 기록을 관리하세요</strong><span>메모 수정, 방문 완료, 등록 해제가 가능해요.</span></div></li></ol><p class="mvc-guide-note">끌기가 어려우면 거래처를 선택한 뒤 날짜를 눌러 등록하세요. 잘못 놓았을 때는 ‘실행 취소’로 되돌릴 수 있어요.</p><button type="button" class="mvc-guide-start">확인 · 일정 만들기</button></div>';
 document.body.append(guide);
 const dismissGuide=()=>guide.close();guide.querySelector('.mvc-guide-close').onclick=dismissGuide;guide.querySelector('.mvc-guide-start').onclick=dismissGuide;
 guide.addEventListener('close',()=>{if(dialog.open){q('.mvc-fit').focus({preventScroll:true});ensureVisitMap();}});
 function positionHandDemo(){
  if(!guide.open)return;
  const demo=guide.querySelector('.mvc-drag-demo'),grip=demo.querySelector('.mvc-demo-grip'),day=demo.querySelector('.mvc-demo-day');
  const frame=demo.getBoundingClientRect(),from=grip.getBoundingClientRect(),to=day.getBoundingClientRect();
  demo.style.setProperty('--hand-x',(from.left+from.width/2-frame.left-26)+'px');
  demo.style.setProperty('--hand-y',(from.top+from.height/2-frame.top-14)+'px');
  demo.style.setProperty('--hand-dx',(to.left+to.width/2-from.left-from.width/2)+'px');
  demo.style.setProperty('--hand-dy',(to.top+to.height/2-from.top-from.height/2)+'px');
 }
 if(typeof ResizeObserver!=='undefined')new ResizeObserver(positionHandDemo).observe(guide.querySelector('.mvc-drag-demo'));
 window.addEventListener('resize',positionHandDemo);
 function showGuide(){if(!guide.open){guide.showModal();guide.querySelector('.mvc-guide-start').focus({preventScroll:true});positionHandDemo();}}

 async function open(uid=''){
  if(!dialog.open){trigger=document.activeElement;dialog.showModal();lockPageScroll();showGuide();}form.hidden=true;await load();if(!dialog.open)return;
  if(!buildings.length){try{const res=await fetch('/manager_buildings.php',{credentials:'same-origin',cache:'no-store'});const data=await res.json();if(res.ok&&data.ok)buildings=data.buildings;}catch{notice('거래처 목록을 불러오지 못했습니다. 달력을 다시 열어 주세요.');}}
  if(uid&&dialog.open)chosen.add(uid);if(dialog.open){ensureVisitMap();render();}
 }
 opener.onclick=()=>open();q('.mvc-close').onclick=()=>{if(!busy)dialog.close();};dialog.addEventListener('cancel',e=>{if(busy)e.preventDefault();});dialog.addEventListener('close',()=>{if(guide.open)guide.close();if(dayDialog.open)dayDialog.close();unlockPageScroll();trigger?.focus({preventScroll:true});});
 dialog.querySelectorAll('[data-shift]').forEach(b=>b.onclick=()=>{if(busy)return;const [y,m]=month.split('-').map(Number),next=new Date(y,m-1+Number(b.dataset.shift),1);if(next.getFullYear()<2000||next.getFullYear()>2100)return;month=dayKey(next).slice(0,7);selected=month+'-01';form.hidden=true;load();drawVisitMap(approvalOnly);});
 q('.mvc-today').onclick=()=>{if(busy)return;selected=today();month=selected.slice(0,7);form.hidden=true;load();drawVisitMap(approvalOnly);};q('.mvc-add').onclick=()=>edit();q('.mvc-edit-close').onclick=()=>{if(!busy)form.hidden=true;};field('status').onchange=syncActual;
 form.onsubmit=async e=>{
  e.preventDefault();if(busy||!form.reportValidity())return;busy=true;syncDayNotes();q('.mvc-error').textContent='';const body=new URLSearchParams(new FormData(form));body.set('csrf',typeof CSRF!=='undefined'?CSRF:'');if(editing){body.set('id',editing.id);body.set('revision',String(editing.revision));}
  form.querySelectorAll('button,input,select,textarea').forEach(n=>n.disabled=true);q('.mvc-save').textContent='저장 중…';
  try{const res=await fetch('/manager_visits.php',{method:'POST',credentials:'same-origin',body});const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.error||'저장하지 못했습니다.');if(!dayDialog.open){selected=data.visit.date;month=selected.slice(0,7);}form.hidden=true;await load();q('.mvc-batch-result').textContent=data.visit.date.replaceAll('-','.')+' 방문 일정을 저장했습니다.';}
  catch(e){q('.mvc-error').textContent=e.message||'연결 상태를 확인해 주세요.';}
  finally{busy=false;form.querySelectorAll('button,input,select,textarea').forEach(n=>n.disabled=false);q('.mvc-save').textContent='저장';render();}
 };
 document.addEventListener('manager-buildings-updated',e=>{buildings=Array.isArray(e.detail?.buildings)?e.detail.buildings:[];for(const uid of chosen)if(!buildings.some(b=>b.uid===uid))chosen.delete(uid);if(dialog.open){ensureVisitMap();render();}});
 document.addEventListener('manager-visit-open',e=>open(e.detail?.uid||''));
})();
