(()=>{
 'use strict';
 if(location.pathname.startsWith('/partner_demo_app.php/'))return;
 const el=(tag,text,cls)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=text;if(cls)n.className=cls;return n;};
 let tasksDialog,digestDialog,csrf='',tasks=[],clients=[],busy=false,taskToken=0,digestToken=0,selectedSlot='',returnTaskFocus,returnDigestFocus;
 const channels=typeof BroadcastChannel==='function'?new BroadcastChannel('work-tasks'):null;
 const labels={overdue:'기한 초과·지난 일정',today:'오늘 처리',soon:'3일 내 마감',tomorrow:'내일 일정',carry:'이번 주에서 넘어온 미완료',weekend:'이번 주말',nextweek:'다음 주 일정'};
 const today=()=>new Intl.DateTimeFormat('sv-SE',{timeZone:'Asia/Seoul'}).format(new Date());
 async function api(query='',body){
  const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),12000);
  try{const r=await fetch('/visit_push_api.php'+query,{credentials:'same-origin',cache:'no-store',signal:controller.signal,...(body?{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({...body,csrf})}:{})});const d=await r.json();if(!r.ok||!d.ok)throw Error(d.error||'업무를 불러오지 못했습니다.');return d;}finally{clearTimeout(timer);}
 }
 function taskCreate(){
  tasksDialog=el('dialog',undefined,'wn-dialog wt-dialog');tasksDialog.setAttribute('aria-labelledby','wt-title');
  tasksDialog.innerHTML='<header><div><small>업무모드</small><h2 id="wt-title">마감일이 있는 할 일</h2></div><button type="button" class="wn-close" aria-label="할 일 닫기">×</button></header><p class="wn-note">직접 등록한 할 일만 요약 알림에 포함됩니다. 메모는 자동 발송하지 않습니다.</p><form class="wt-form"><input type="hidden" name="id"><input type="hidden" name="revision"><label>할 일<input name="title" maxlength="160" required placeholder="예: 보완 사진 제출하기"></label><div class="wt-fields"><label>마감일<input type="date" name="due" required></label><label>거래처<select name="uid"><option value="">공통 업무</option></select></label></div><div class="wt-actions"><button type="button" class="wt-reset">새 할 일</button><button type="submit" class="wt-save">할 일 등록</button></div></form><p class="wn-error" role="status" aria-live="polite"></p><label class="wt-completed"><input type="checkbox"> 완료한 할 일도 보기</label><div class="wt-list"></div>';
  document.body.append(tasksDialog);tasksDialog.querySelector('.wn-close').onclick=()=>{if(!busy)tasksDialog.close();};tasksDialog.addEventListener('cancel',e=>{if(busy)e.preventDefault();});tasksDialog.addEventListener('close',()=>returnTaskFocus?.focus?.({preventScroll:true}));
  tasksDialog.querySelector('.wt-reset').onclick=()=>editTask();tasksDialog.querySelector('.wt-completed input').onchange=paintTasks;
  tasksDialog.querySelector('form').onsubmit=e=>{e.preventDefault();const f=e.currentTarget;mutate({act:'task_save',id:f.elements.id.value,revision:Number(f.elements.revision.value),title:f.elements.title.value,due:f.elements.due.value,uid:f.elements.uid.value,client_name:f.elements.uid.selectedOptions[0]?.textContent||''},true);};
 }
 function editTask(row){
  const f=tasksDialog.querySelector('form');f.elements.id.value=row?.id||'';f.elements.revision.value=row?.revision||0;f.elements.title.value=row?.title||'';f.elements.due.value=row?.due||today();
  clientOptions(row?.uid||'',row?.client_name||'');
  f.querySelector('.wt-save').textContent=row?'변경 저장':'할 일 등록';if(row)f.elements.title.focus();
 }
 function clientOptions(uid,name=''){
  const select=tasksDialog.querySelector('form').elements.uid;select.replaceChildren(new Option('공통 업무',''));
  for(const c of clients)select.add(new Option(c.name||'거래처',c.uid));
  if(uid&&![...select.options].some(o=>o.value===uid))select.add(new Option(name||'거래처 확인 필요',uid));select.value=uid;
 }
 function paintTasks(){
  const list=tasksDialog.querySelector('.wt-list');list.replaceChildren();const show=tasksDialog.querySelector('.wt-completed input').checked;
  const visible=tasks.filter(r=>show||r.status==='planned');
  if(!visible.length)list.append(el('p','등록된 미완료 할 일이 없습니다.','wn-empty'));
  for(const row of visible){
   const card=el('article',undefined,'wt-item'+(row.status==='completed'?' is-completed':''));card.append(el('strong',row.title),el('small',(row.client_name?row.client_name+' · ':'')+row.due+(row.status==='planned'&&row.due<today()?' · 기한 초과':'')));
   const actions=el('div',undefined,'wt-row-actions');
   for(const [text,act] of [[row.status==='completed'?'다시 열기':'완료','status'],['수정','edit'],['삭제','delete']]){
    const b=el('button',text);b.type='button';b.disabled=busy;b.onclick=()=>act==='edit'?editTask(row):mutate({act:act==='delete'?'task_delete':'task_status',id:row.id,revision:row.revision,completed:row.status!=='completed'});actions.append(b);
   }card.append(actions);list.append(card);
  }
  tasksDialog.querySelectorAll('.wt-form button,.wt-form input,.wt-form select').forEach(b=>b.disabled=busy);
 }
 async function loadTasks(id=''){
  const token=++taskToken;try{const d=await api('?tasks=1');if(token!==taskToken)return;csrf=d.csrf;tasks=d.tasks;clients=d.clients||[];const select=tasksDialog.querySelector('form').elements.uid;clientOptions(select.value,select.selectedOptions[0]?.textContent||'');paintTasks();if(id){const r=tasks.find(r=>r.id===id);if(r)editTask(r);else tasksDialog.querySelector('.wn-error').textContent='완료·삭제되었거나 조회할 수 없는 할 일입니다.';}}catch(e){tasksDialog.querySelector('.wn-error').textContent=e.name==='AbortError'?'연결이 지연됩니다. 다시 열어 주세요.':e.message;}
 }
 async function mutate(body,reset=false){
  if(busy)return;busy=true;paintTasks();tasksDialog.querySelector('.wn-error').textContent='';
  try{await api('',body);await loadTasks();if(reset)editTask();channels?.postMessage({changed:Date.now()});try{localStorage.setItem('work-tasks-changed',String(Date.now())+Math.random());}catch{}changed();}
  catch(e){tasksDialog.querySelector('.wn-error').textContent=e.message;}
  finally{busy=false;paintTasks();}
 }
 window.workTasksOpen=async(button,id='')=>{
  if(!tasksDialog)taskCreate();if(!tasksDialog.open){returnTaskFocus=button||document.activeElement;tasksDialog.showModal();editTask();}tasksDialog.querySelector('.wn-error').textContent='';await loadTasks(id);
 };
 function digestCreate(){
  digestDialog=el('dialog',undefined,'wn-dialog wd-dialog');digestDialog.setAttribute('aria-labelledby','wd-title');digestDialog.innerHTML='<header><div><small>업무모드 · 일정 요약</small><h2 id="wd-title">업무 목록</h2></div><button type="button" class="wn-close" aria-label="업무 요약 닫기">×</button></header><p class="wn-note">완료·취소·삭제와 변경된 일정을 반영한 목록입니다.</p><p class="wn-error" role="status"></p><div class="wd-list"></div>';
  document.body.append(digestDialog);digestDialog.querySelector('.wn-close').onclick=()=>digestDialog.close();digestDialog.addEventListener('close',()=>{digestToken++;returnDigestFocus?.focus?.({preventScroll:true});});
 }
 async function loadDigest(){
  const token=++digestToken;try{
   const d=await api('?digest='+encodeURIComponent(selectedSlot));if(token!==digestToken||!digestDialog.open)return;
   digestDialog.querySelector('#wd-title').textContent=d.digest.title;digestDialog.querySelector('.wn-error').textContent='';const list=digestDialog.querySelector('.wd-list');list.replaceChildren();
   for(const [key,label] of Object.entries(labels)){
    const rows=d.digest.rows.filter(r=>r.bucket===key);if(!rows.length)continue;
    const section=el('section');section.append(el('h3',label+' · '+rows.length+'건'));
    for(const row of rows){
     const b=el('button',undefined,'wd-item urgency-'+row.urgent);b.type='button';b.append(el('small',row.due+' · '+row.label),el('strong',row.display),el('span',row.urgency_label||'업무 보기'));
     b.onclick=()=>{digestDialog.close();if(row.kind==='todo')window.workTasksOpen(null,row.key.slice(5));else document.dispatchEvent(new CustomEvent('manager-visit-detail-open',{detail:{key:row.key}}));};section.append(b);
    }list.append(section);
   }if(!d.digest.rows.length)list.append(el('p','모든 업무가 완료되었거나 일정이 변경되었습니다.','wn-empty'));
  }catch(e){if(token===digestToken)digestDialog.querySelector('.wn-error').textContent=e.name==='AbortError'?'연결이 지연됩니다. 잠시 후 다시 열어 주세요.':e.message;}
 }
 window.visitDigestOpen=async slot=>{
  if(!digestDialog)digestCreate();selectedSlot=slot;if(!digestDialog.open){returnDigestFocus=document.activeElement;digestDialog.showModal();}await loadDigest();
 };
 function changed(){window.visitInboxRefresh?.(true);if(digestDialog?.open)loadDigest();}
 channels?.addEventListener('message',()=>{if(tasksDialog?.open&&!busy)loadTasks();changed();});
 window.addEventListener('storage',e=>{if(e.key==='work-tasks-changed'){if(tasksDialog?.open&&!busy)loadTasks();changed();}});
 document.addEventListener('visibilitychange',()=>{if(!document.hidden){if(tasksDialog?.open&&!busy)loadTasks();if(digestDialog?.open)loadDigest();}});
 const initial=new URLSearchParams(location.search).get('work_digest');if(initial)queueMicrotask(()=>window.visitDigestOpen(initial));
})();
