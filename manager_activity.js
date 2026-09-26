(()=>{
 const panel=document.getElementById('manager-approval-panel');if(!panel)return;
 const subscriptionStyle=document.createElement('style');subscriptionStyle.textContent='.ms-subscription-notice{border-color:#a9ded2!important;background:linear-gradient(130deg,#f0fdfa,#fff)!important}.ms-subscription-notice .ms-badge{background:#0f766e;color:#fff}.ms-subscription-status{font-size:11px!important;color:#0f766e!important;font-weight:650;margin:7px 0!important;line-height:1.6}';document.head.append(subscriptionStyle);
 const months=panel.querySelector('[data-approval-months]'),status=panel.querySelector('[data-approval-status]');
 const sidebar=document.getElementById('manager-sidebar'),alertBox=document.getElementById('manager-connect-banner'),badge=document.querySelector('[data-request-badge]');
 const entries=[...sidebar.querySelectorAll('[data-ms-uid]')],search=sidebar.querySelector('#ms-search'),empty=sidebar.querySelector('[data-ms-no-results]');
 let rows=[],selected='all',baseline=null,activeTab='users';
 const monthResults=sidebar.querySelector('[data-month-results]');
 function subscriptionBadge(el,r){let badge=el.querySelector('[data-subscription-status]');if(!r?.subscription?.active){badge?.remove();return;}if(!badge){badge=node('p',undefined,'ms-subscription-status');badge.dataset.subscriptionStatus='1';el.querySelector('.ms-user-head')?.insertAdjacentElement('afterend',badge);}badge.textContent=(r.subscription.test?'TEST PRO':'PRO 협업 중')+' · 구독 시작 '+(r.subscription.started_at||'확인 필요');}
 function filter(){const q=(search?.value||'').trim().toLocaleLowerCase();let count=0;const byUid=new Map(rows.map(r=>[r.uid,r]));entries.forEach(el=>{if(el.dataset.msStatus==='pending'){el.hidden=false;return;}const r=byUid.get(el.dataset.msUid);subscriptionBadge(el,r);const matches=r&&true;el.hidden=!(matches&&el.dataset.msName.toLocaleLowerCase().includes(q));if(!el.hidden)count++;const date=el.querySelector('[data-ms-approval]');if(date)date.textContent=r?'사용승인일 '+(r.approval_date||'미입력'):'연결 상태를 확인해 주세요.';});if(empty)empty.hidden=count>0;renderMonth();status.textContent=(selected==='all'?'전체':selected==='unknown'?'사용승인일 미입력':selected+'월 사용승인')+' · '+rows.filter(monthMatch).length+'개';}
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
 function helpChanged(data){helpUnread=new Set((data?.rows||[]).filter(r=>r.status==='pending'&&r.connection_active).map(r=>r.uid)).size;const emptyHelp=sidebar.querySelector('[data-help-empty]');if(emptyHelp){emptyHelp.hidden=helpUnread>0;emptyHelp.textContent=data?'대기 중인 작성 요청이 없습니다.':'작성 요청을 확인하고 있습니다.';}updateBadge();}
 document.addEventListener('manager-help-updated',e=>helpChanged(e.detail));
 helpChanged(window.managerHelp?.getState());
 const noticeHost=sidebar.querySelector('#manager-disconnect-notices');
 function updateBadge(){if(badge){badge.hidden=disconnectUnread===0;badge.textContent=String(disconnectUnread);badge.setAttribute('aria-label','새 알림 '+disconnectUnread+'건');}const helpBadge=sidebar.querySelector('[data-help-badge]');if(helpBadge){helpBadge.hidden=helpUnread===0;helpBadge.textContent=String(helpUnread);helpBadge.setAttribute('aria-label','작성 요청 건물 '+helpUnread+'곳');}}
 async function loadDisconnect(id){
  if(!noticeHost||noticeBusy)return;noticeBusy=true;
  try{
   const options={credentials:'same-origin',cache:'no-store'};
   if(id){options.method='POST';options.body=new URLSearchParams({id,csrf:typeof CSRF==='string'?CSRF:''});}
   const response=await fetch('/manager_notifications.php',options);const data=await response.json();if(!response.ok||!data.ok)throw Error();
   disconnectUnread=Number(data.unread)||0;updateBadge();noticeHost.replaceChildren();const emptyNotice=sidebar.querySelector('[data-notices-empty]');if(emptyNotice)emptyNotice.hidden=(data.notifications||[]).some(n=>!n.read);
   for(const n of data.notifications||[]){
    if(n.read)continue;
    const subscribed=['subscription_started','subscription_renewed'].includes(n.kind);
    const card=node('article',undefined,subscribed?'ms-user ms-subscription-notice':'ms-user');const head=node('div',undefined,'ms-user-head');head.append(node('strong',n.name),node('span',subscribed?(n.kind==='subscription_renewed'?'PRO 구독 갱신':'PRO 구독 시작'):(n.kind==='disconnected'?'연결 해제':'요청 취소'),'ms-badge'));
    card.append(head,node('p',subscribed?'PRO 구독을 시작했습니다. 업무 기록을 함께 작성·관리할 수 있습니다.':(n.kind==='disconnected'?'유저가 매니저 연결을 해제했습니다. 건물 화면에 접근할 수 없습니다.':'유저가 연결 요청을 취소했습니다.')));
    if(subscribed&&n.started_at)card.append(node('p','구독 시작일 '+n.started_at,'ms-subscription-status'));
    const date=new Date(n.at);card.append(node('p',Number.isNaN(date.getTime())?n.at:date.toLocaleString('ko-KR')));
    {const actions=node('div',undefined,'ms-actions');const b=node('button','확인 완료','ms-button ms-button--primary');b.type='button';b.addEventListener('click',()=>loadDisconnect(n.id));actions.append(b);card.append(actions);}noticeHost.append(card);
   }
  }catch{let msg=noticeHost.querySelector('[data-notice-error]');if(!msg){msg=node('p','알림을 불러오지 못했습니다. 새로고침해 주세요.','ms-empty');msg.dataset.noticeError='1';noticeHost.prepend(msg);}}
  finally{noticeBusy=false;}
 }
 loadDisconnect();
 document.addEventListener('manager-buildings-updated',()=>loadDisconnect());
 document.addEventListener('manager-tab-changed',e=>{if(e.detail.tab==='notifications')loadDisconnect();});
 function requests(keys,items){
  if(!Array.isArray(keys)||!alertBox)return;
  sidebar.dataset.pendingCount=String(keys.length);
  alertBox.hidden=keys.length===0;
  alertBox.querySelector('[data-connect-count]').textContent=String(keys.length);
  const summary=alertBox.querySelector('[data-connect-summary]');
  const names=Array.isArray(items)?items.map(r=>String(r?.name||'').trim()).filter(Boolean):[];
  if(summary)summary.textContent=names.length?(names[0]+(keys.length>1?' 외 '+(keys.length-1)+'곳':'')+'에서 연결을 요청했습니다.'):'매니저 코드로 연결을 요청한 유저입니다.';
 }
 document.addEventListener('manager-buildings-updated',e=>{const buildings=Array.isArray(e.detail?.buildings)?e.detail.buildings:[];const draftCount=document.querySelector('[data-preregister-count]');if(draftCount)draftCount.textContent=String(buildings.filter(r=>r.preregistered).length);rows=buildings.filter(r=>!r.preregistered);render();syncMap();requests(e.detail?.pending_keys,e.detail?.pending_requests);});
 document.addEventListener('manager-buildings-unavailable',()=>{rows=[];months.replaceChildren();monthResults.replaceChildren();entries.forEach(el=>{if(el.dataset.msStatus==='accepted')el.hidden=true;});status.textContent='담당 유저 정보를 확인하지 못했습니다. 로그인 상태를 확인하고 새로고침해 주세요.';if(alertBox)alertBox.hidden=true;if(badge)badge.hidden=true;});
})();
