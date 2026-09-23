(()=>{
 const panel=document.getElementById('manager-approval-panel');if(!panel)return;
 const months=panel.querySelector('[data-approval-months]'),status=panel.querySelector('[data-approval-status]');
 const sidebar=document.getElementById('manager-sidebar'),alertBox=document.getElementById('manager-request-alert'),badge=document.querySelector('[data-request-badge]');
 const entries=[...sidebar.querySelectorAll('[data-ms-uid]')],search=sidebar.querySelector('#ms-search'),empty=sidebar.querySelector('[data-ms-no-results]');
 let rows=[],selected='all',baseline=null,activeTab='users';
 const monthResults=sidebar.querySelector('[data-month-results]');
 function filter(){const q=(search?.value||'').trim().toLocaleLowerCase();let count=0;const byUid=new Map(rows.map(r=>[r.uid,r]));entries.forEach(el=>{if(el.dataset.msStatus==='pending'){el.hidden=false;return;}const r=byUid.get(el.dataset.msUid);const matches=r&&true;el.hidden=!(matches&&el.dataset.msName.toLocaleLowerCase().includes(q));if(!el.hidden)count++;const date=el.querySelector('[data-ms-approval]');if(date)date.textContent=r?'사용승인일 '+(r.approval_date||'미입력'):'연결 상태를 확인해 주세요.';});if(empty)empty.hidden=count>0;renderMonth();status.textContent=(selected==='all'?'전체':selected==='unknown'?'사용승인일 미입력':selected+'월 사용승인')+' · '+rows.filter(monthMatch).length+'개';}
 function monthMatch(r){return selected==='all'||(selected==='unknown'?!r.approval_month:String(r.approval_month)===selected);}
 function renderMonth(){monthResults.replaceChildren();let n=0;rows.filter(monthMatch).forEach(r=>{const source=entries.find(el=>el.dataset.msUid===r.uid);if(!source)return;const copy=source.cloneNode(true);copy.hidden=false;copy.removeAttribute('data-ms-uid');copy.querySelectorAll('details').forEach(el=>el.remove());monthResults.append(copy);n++;});if(!n){const msg=document.createElement('p');msg.className='ms-empty';msg.textContent='해당 월의 담당 건물이 없습니다.';monthResults.append(msg);}}
 monthResults.addEventListener('click',e=>{const b=e.target.closest('[data-ms-map]');if(b){document.dispatchEvent(new CustomEvent('manager-map-focus',{detail:{uid:b.dataset.msMap}}));document.getElementById('map')?.scrollIntoView({behavior:'smooth',block:'center'});}});
 function syncMap(){document.dispatchEvent(new CustomEvent('manager-month-filter',{detail:{month:activeTab==='months'?selected:'all'}}));}
 document.addEventListener('manager-tab-changed',e=>{activeTab=e.detail.tab;syncMap();});
 activeTab=sidebar.querySelector('[data-ms-tab][aria-selected="true"]')?.dataset.msTab||'users';
 search?.addEventListener('input',filter);
 const node=(tag,text,cls)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=text;if(cls)e.className=cls;return e;};
 function render(){
  months.replaceChildren();
  const select=node('select');select.setAttribute('aria-label','사용승인월 선택');select.className='ms-month-select';
  ['all',...Array.from({length:12},(_,i)=>String(i+1)),'unknown'].forEach(key=>{
   const count=rows.filter(r=>key==='all'||(key==='unknown'?!r.approval_month:String(r.approval_month)===key)).length;
   const option=node('option',(key==='all'?'전체 보기':key==='unknown'?'사용승인일 미입력':key+'월')+' · '+count+'개');option.value=key;select.append(option);
  });
  select.value=selected;
  select.addEventListener('change',()=>{selected=select.value;filter();syncMap();});
  months.append(select);
  filter();
 }

 let helpUnread=0,disconnectUnread=0,noticeBusy=false;
 function helpChanged(data){helpUnread=(data?.rows||[]).filter(r=>r.status==='pending'&&r.connection_active).length;updateBadge();}
 document.addEventListener('manager-help-updated',e=>helpChanged(e.detail));
 helpChanged(window.managerHelp?.getState());
 const noticeHost=sidebar.querySelector('#manager-disconnect-notices');
 function updateBadge(){const n=Number(sidebar.dataset.pendingCount??sidebar.dataset.initialPending??0)+disconnectUnread+helpUnread;if(badge){badge.hidden=n===0;badge.textContent=String(n);badge.setAttribute('aria-label','확인할 알림 '+n+'건');}}
 async function loadDisconnect(id){
  if(!noticeHost||noticeBusy)return;noticeBusy=true;
  try{
   const options={credentials:'same-origin',cache:'no-store'};
   if(id){options.method='POST';options.body=new URLSearchParams({id,csrf:typeof CSRF==='string'?CSRF:''});}
   const response=await fetch('/manager_notifications.php',options);const data=await response.json();if(!response.ok||!data.ok)throw Error();
   disconnectUnread=Number(data.unread)||0;updateBadge();noticeHost.replaceChildren();
   for(const n of data.notifications||[]){
    if(n.read)continue;
    const card=node('article',undefined,'ms-user');const head=node('div',undefined,'ms-user-head');head.append(node('strong',n.name),node('span',n.kind==='disconnected'?'연결 해제':'요청 취소','ms-badge'));
    card.append(head,node('p',n.kind==='disconnected'?'유저가 매니저 연결을 해제했습니다. 건물 화면에 접근할 수 없습니다.':'유저가 연결 요청을 취소했습니다.'));
    const date=new Date(n.at);card.append(node('p',Number.isNaN(date.getTime())?n.at:date.toLocaleString('ko-KR')));
    {const actions=node('div',undefined,'ms-actions');const b=node('button','확인 완료','ms-button ms-button--primary');b.type='button';b.addEventListener('click',()=>loadDisconnect(n.id));actions.append(b);card.append(actions);}noticeHost.append(card);
   }
  }catch{let msg=noticeHost.querySelector('[data-notice-error]');if(!msg){msg=node('p','연결 해제 알림을 불러오지 못했습니다. 새로고침해 주세요.','ms-empty');msg.dataset.noticeError='1';noticeHost.prepend(msg);}}
  finally{noticeBusy=false;}
 }
 loadDisconnect();
 document.addEventListener('manager-buildings-updated',()=>loadDisconnect());
 document.addEventListener('manager-tab-changed',e=>{if(e.detail.tab==='notifications')loadDisconnect();});
 function requests(keys){
  if(!Array.isArray(keys))return;
  sidebar.dataset.pendingCount=String(keys.length);updateBadge();
  sidebar.querySelectorAll('[data-notification-key]').forEach(el=>{el.hidden=!keys.includes(el.dataset.notificationKey);});
  if(!alertBox)return;
  const known=[...sidebar.querySelectorAll('[data-notification-key]')].map(el=>el.dataset.notificationKey);
  const changed=keys.some(k=>!known.includes(k));
  const noRequests=sidebar.querySelector('[data-notifications-empty]');if(noRequests)noRequests.hidden=keys.length>0;
  baseline=keys.slice();alertBox.replaceChildren();alertBox.hidden=!changed||keys.length===0;
  if(changed&&keys.length){alertBox.append(node('strong',(changed?'새 요청이 도착했습니다!':'수락 대기 중인 요청')+' '+keys.length+'건'),node('span','요청을 확인하고 수락 또는 거절해 주세요.'));const button=node('button','요청 확인 · 새로고침');button.type='button';button.addEventListener('click',()=>{try{sessionStorage.setItem('manager-workspace-tab','notifications');}catch{}location.hash='manager-sidebar';location.reload();});alertBox.append(button);}
 }
 document.addEventListener('manager-buildings-updated',e=>{rows=Array.isArray(e.detail?.buildings)?e.detail.buildings:[];render();syncMap();requests(e.detail?.pending_keys);});
 document.addEventListener('manager-buildings-unavailable',()=>{rows=[];months.replaceChildren();monthResults.replaceChildren();entries.forEach(el=>{if(el.dataset.msStatus==='accepted')el.hidden=true;});status.textContent='담당 유저 정보를 확인하지 못했습니다. 로그인 상태를 확인하고 새로고침해 주세요.';if(alertBox)alertBox.hidden=true;if(badge)badge.hidden=true;});
})();
