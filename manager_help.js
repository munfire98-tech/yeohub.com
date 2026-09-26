(function(){
'use strict';
const script=document.currentScript, uid=script.dataset.uid||'', manager=script.dataset.manager==='1', dashboard=script.dataset.dashboard==='1', reviewChat=script.dataset.reviewchat==='1', facilitiesPage=script.dataset.facilities==='1';
const firePlan=script.dataset.fireplan||'';
const host=document.createElement('section');host.className='mh-panel';script.before(host);
const style=document.createElement('style');style.textContent=`
.mh-panel{max-width:1120px;margin:18px auto;padding:18px 20px;border:1px solid #dce5ed;border-radius:16px;background:#fff;color:#192b3d;font:14px/1.6 system-ui;box-sizing:border-box}.mh-panel summary{cursor:pointer;font-weight:750;display:flex;gap:10px;align-items:center}.mh-panel summary::before{content:'▸';color:#0891b2}.mh-panel details[open]>summary::before{content:'▾'}.mh-count{border-radius:20px;background:#e6f5fa;color:#08768b;padding:2px 10px;font-size:12px}.mh-row{border-top:1px solid #edf1f5;padding:16px 0}.mh-row p{white-space:pre-wrap;margin:6px 0;overflow-wrap:anywhere}.mh-row small,.mh-muted{color:#687b8e}.mh-panel button,.mh-panel a.mh-link{border:1px solid #d7e4ec;border-radius:9px;background:#f3fafc;color:#08768b;padding:8px 12px;cursor:pointer;font:600 13px system-ui;display:inline-block;text-decoration:none}.mh-panel textarea{display:block;width:100%;box-sizing:border-box;min-height:65px;margin:10px 0;border:1px solid #dce5ed;border-radius:9px;padding:10px;font:14px system-ui}.mh-card-badge{display:inline-flex;align-items:center;flex:0 0 auto;white-space:nowrap;max-width:none;border-radius:12px;padding:3px 8px;background:#fff1da;color:#a65308;font:700 11px/1.5 system-ui}.mh-panel.mh-notices{border:0;box-shadow:none;background:transparent}.mh-panel .mh-notice{display:flex;align-items:center;gap:10px;padding:12px 14px;margin:6px 0;border:1px solid #e4eaf0;border-radius:10px;color:#34475a;text-decoration:none;font:500 13px/1.6 system-ui}.mh-notice>span:first-child{min-width:0;overflow-wrap:anywhere}.mh-notice-arrow{margin-left:auto;flex-shrink:0;color:#0891b2}.mh-chat-status{text-align:center;padding:10px!important;font-size:12px!important;background:#f7fafc!important}.mh-completion{padding:12px;border-radius:10px;background:#eaf8f2;color:#167459;font-weight:700}.mh-notice-content{min-width:0;flex:1}.mh-notice-content strong{display:block;color:#213b4f;font-size:14px}.mh-notice-content p{margin:4px 0;font-weight:600;font-size:12px}.mh-notice-content small{display:block;color:#6d7f8c;font-size:11px;line-height:1.7}.mh-notice-icon{display:flex;align-items:center;justify-content:center;width:30px;height:30px;flex-shrink:0;background:#fff1d9;color:#b87817;border-radius:10px;font-weight:800}.mh-panel .mh-notice{background:linear-gradient(130deg,#fff,#f4f9fc);padding:16px;box-shadow:0 3px 12px rgba(29,64,93,.04)}.mh-help-glow{border-color:#edbb69!important;box-shadow:0 0 0 1px rgba(245,158,11,.2),0 0 13px rgba(245,158,11,.17)!important}.mh-help-label{display:inline-flex!important;align-items:center;gap:5px;min-width:0}.mh-help-bell{position:relative;display:inline-flex;align-items:center;justify-content:center;vertical-align:middle;flex:0 0 20px;width:20px;height:20px;border-radius:50%;background:#fff3da;color:#b86b06;line-height:1}.mh-help-bell.mh-done-icon{box-sizing:border-box;flex:0 0 18px;width:18px;height:18px;align-self:center;border-radius:6px;background:#eaf6f0;color:#238064;border:1px solid #d0e8dc;box-shadow:none}.mh-help-label{column-gap:6px}.mh-help-bell svg{display:block;flex-shrink:0}.mh-help-bell i{position:absolute;right:0;top:0;width:5px;height:5px;border-radius:50%;background:#f28c24;border:1px solid white}@media print{.mh-help-bell{display:none}.mh-help-glow{box-shadow:none!important}}.mh-resolved{color:#15805d}.mh-tools{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0}.mh-panel [role=status]{color:#9f3535}@media(max-width:700px){.mh-panel{margin:12px;padding:15px}}@media print{.mh-panel{display:none}}`;
document.head.append(style);
let state=null,busy=false,refreshQueued=false,stateRevision=0;
function facilityRequest(r){return r.field==='__facilities';}
function fireRequest(r){return /^__fp_([0-9]+)_([0-9]+)_([A-Za-z0-9_]+)$/.exec(r.field||'');}
function fireLink(r){const m=fireRequest(r);return m?'/fire_plan_chat.php?id='+encodeURIComponent(m[1])+'&help_code='+encodeURIComponent(m[2])+'&help_key='+encodeURIComponent(m[3]):'/fire_plan.php';}
function pageRows(){return state.rows.filter(r=>firePlan?fireRequest(r)?.[1]===firePlan:facilitiesPage?facilityRequest(r):!facilityRequest(r)&&!fireRequest(r));}
function announce(){document.dispatchEvent(new CustomEvent('manager-help-updated',{detail:state||{rows:[]}}));}
function decorateDashboard(){
 if(!dashboard||!state)return;
 const links=[...document.querySelectorAll('a.card--setup,a.building-task,a.pstep,a.card--link')].filter(a=>/\/(?:building_setup(?:_chat)?|building_facilities|fire_plan(?:_chat|_new|_edit)?)\.php(?:[?#]|$)/.test(a.getAttribute('href')||''));
 links.forEach(a=>{
   const isFacility=/building_facilities\.php/.test(a.dataset.helpOriginalHref||a.getAttribute('href')||'');
   const isFire=/\/fire_plan(?:_chat|_new|_edit)?\.php/.test(a.dataset.helpOriginalHref||a.getAttribute('href')||'');
   const matching=state.rows.filter(r=>(isFire?!!fireRequest(r):!fireRequest(r)&&facilityRequest(r)===isFacility)&&r.connection_active);
   const pending=matching.filter(r=>r.status==='pending'),resolved=matching.filter(r=>r.status==='resolved');
   if(!a.dataset.helpOriginalHref)a.dataset.helpOriginalHref=a.getAttribute('href');
   a.querySelectorAll('[data-help-bell]').forEach(n=>n.remove());
   a.classList.toggle('mh-help-glow',pending.length>0);
   if(pending.length){
     const label=a.querySelector('.badge--setup,.pstep__label')||[...a.children].find(n=>n.tagName==='SPAN'&&(n.textContent.trim()==='기본정보'||n.textContent.trim().startsWith('소방시설')||n.textContent.trim()==='소방계획서'));
     const bell=node('span',undefined,'mh-help-bell');bell.dataset.helpBell='1';
     const description='작성 도움 요청 '+pending.length+'건: '+pending.map(r=>r.text).join(' / ');
     bell.setAttribute('role','img');bell.setAttribute('aria-label',description);bell.title=description;
     bell.innerHTML='<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg><i aria-hidden="true"></i>';
     if(label){label.classList.add('mh-help-label');label.append(bell);}else a.append(bell);
     a.href=isFire&&manager?fireLink(pending[0]):(isFire||isFacility)?a.dataset.helpOriginalHref:'/building_setup_chat.php?help_field='+encodeURIComponent(pending[0].field);
   }else{
     if(!manager&&resolved.length&&!a.matches('.building-task')){const label=a.querySelector('.badge--setup,.pstep__label')||[...a.children].find(n=>n.tagName==='SPAN'&&(n.textContent.trim()==='기본정보'||n.textContent.trim().startsWith('소방시설')||n.textContent.trim()==='소방계획서'));if(label){const done=node('span',undefined,'mh-help-bell mh-done-icon');done.innerHTML='<svg viewBox="0 0 20 20" width="13" height="13" fill="none" aria-hidden="true"><path d="m4.5 10 3.5 3.5 7.5-7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';label.classList.add('mh-help-label');done.dataset.helpBell='1';done.title='요청한 항목 작성이 모두 완료되었습니다.';done.setAttribute('role','img');done.setAttribute('aria-label',done.title);label.append(done);}}
     a.querySelectorAll('.mh-help-label').forEach(n=>{if(!n.querySelector('[data-help-bell]'))n.classList.remove('mh-help-label');});
     a.setAttribute('href',a.dataset.helpOriginalHref);
   }
 });
 host.hidden=true;
}

function renderNotices(){
 const users=new Map();
 state.rows.filter(r=>r.status==='pending'&&r.connection_active).forEach(r=>{if(!users.has(r.uid))users.set(r.uid,r);});
 host.classList.add('mh-notices');host.hidden=users.size===0;
 for(const r of users.values()){
   const link=node('a',undefined,'mh-notice');link.href='/manager_view.php?uid='+encodeURIComponent(r.uid);
   const content=node('div',undefined,'mh-notice-content');
   content.append(node('strong',r.building_name||r.name),node('p','작성 확인 요청이 도착했습니다.'),node('small','유저 화면에서 알림 표시가 있는 항목을 확인하세요.'));
   const icon=node('span','!','mh-notice-icon');icon.setAttribute('aria-hidden','true');
   link.append(icon,content,node('span','→','mh-notice-arrow'));
   host.append(link);
 }
 place();announce();
}

function place(){
 if(dashboard){decorateDashboard();return;}
 if(manager&&!uid){const notices=document.getElementById('ms-help')||document.getElementById('ms-notifications');if(notices){const disconnect=notices.querySelector('#manager-disconnect-notices');if(disconnect)disconnect.before(host);else notices.append(host);host.style.margin='10px 0';host.style.padding='0';}}
}
document.addEventListener('DOMContentLoaded',place,{once:true});

function node(tag,text,cls){const n=document.createElement(tag);if(text!==undefined)n.textContent=text;if(cls)n.className=cls;return n;}
async function api(data){const options={credentials:'same-origin',cache:'no-store'};
 if(data){options.method='POST';const body=new FormData();Object.entries({...data,csrf:state.csrf,uid}).forEach(([k,v])=>body.append(k,v));options.body=body;}
 const r=await fetch('/manager_help.php'+(uid?'?uid='+encodeURIComponent(uid):''),options);const j=await r.json();if(!r.ok||!j.ok)throw new Error(j.error||'요청을 확인하지 못했습니다.');return j;}
function render(){host.replaceChildren();
 if(firePlan){
  const rows=pageRows().filter(r=>r.connection_active),pending=rows.filter(r=>r.status==='pending');
  host.hidden=rows.length===0;
  host.append(node('strong',pending.length?'작성 도움 요청 '+pending.length+'건':'요청한 항목 작성 완료'));
  for(const r of pending){const a=node('a',r.text,'mh-link');const u=new URL(fireLink(r),location.origin);for(const key of ['embed','modal','uid']){const v=new URLSearchParams(location.search).get(key);if(v)u.searchParams.set(key,v);}a.href=u.pathname+u.search;a.style.display='block';a.style.marginTop='8px';host.append(a);}
  announce();return;
 }

 if((reviewChat&&manager)||facilitiesPage){
   const pending=pageRows().filter(r=>r.status==='pending'&&r.connection_active).length;
   host.classList.add('mh-chat-status');
   host.append(node('span',pending?(facilitiesPage?'소방시설 확인 요청 ':'작성 도움 요청 ')+pending+'건':pageRows().some(r=>r.status==='resolved'&&r.connection_active)?'✓ 요청한 항목 작성 완료':'작성 도움 요청 0건',pending?'':'mh-resolved'));
   announce();return;
 }
 if(!manager&&!dashboard){
   const pending=pageRows().filter(r=>r.status==='pending'&&r.connection_active).length;
   const done=pageRows().filter(r=>r.status==='resolved'&&r.connection_active).length;
   if(done)host.append(node('p',pending?'✓ '+done+'건 작성 완료 · '+pending+'건 확인 중':'✓ 요청한 항목 작성이 모두 완료되었습니다.','mh-completion'));
 }
if(manager&&!uid){renderNotices();return;}if(dashboard){place();announce();return;}const details=node('details');const pending=pageRows().filter(r=>r.status==='pending');
 details.open=manager&&pending.length>0;const title=node('summary','작성 도움 요청');title.append(node('span',pending.length+'건 미해결','mh-count'));details.append(title);
 const tools=node('div',undefined,'mh-tools');const refresh=node('button','새로고침');refresh.type='button';refresh.onclick=()=>load().catch(()=>{});tools.append(refresh);
 const toggle=node('button','해결된 요청 보기');toggle.type='button';tools.append(toggle);details.append(tools);
 const list=node('div');details.append(list);let showDone=false;
 function rows(){list.replaceChildren();const items=pageRows().filter(r=>showDone?r.status==='resolved':r.status==='pending');
 if(!items.length)list.append(node('p',showDone?'해결된 요청이 없습니다.':'미해결 요청이 없습니다.','mh-muted'));
 for(const r of items){const row=node('article',undefined,'mh-row');row.append(node('strong',r.name+' · '+(r.status==='resolved'?'해결 완료':'확인 요청')));
 row.append(node('p',r.text));row.append(node('small',new Date(r.created_at).toLocaleString('ko-KR')));
 if(!r.connection_active&&r.status==='pending')row.append(node('p','이전 연결의 요청입니다. 현재 매니저에게 다시 요청해 주세요.','mh-muted'));
 if(r.status==='resolved')row.append(node('p','처리 내용: '+r.reply,'mh-resolved'));
 if(manager&&r.status==='pending'){
 const a=node('a',uid?'기본정보 작성 화면 열기':'유저 화면 열기','mh-link');a.href=uid?'/building_setup_chat.php?help_field='+encodeURIComponent(r.field):'/manager_view.php?uid='+encodeURIComponent(r.uid);a.target='_top';const linkRow=node('div');linkRow.append(a);row.append(linkRow);
 const input=node('textarea');input.placeholder='정보를 저장한 뒤 처리 내용을 남겨 주세요.';input.maxLength=1000;input.setAttribute('aria-label',r.name+' 요청 처리 내용');row.append(input);
 const done=node('button','해결 완료');done.type='button';row.append(done);const error=node('p');error.setAttribute('role','status');row.append(error);
 done.onclick=async()=>{if(!input.value.trim()){error.textContent='처리 내용을 입력해 주세요.';return;}done.disabled=true;try{state=await api({action:'resolve',id:r.id,reply:input.value});render();if(window.parent!==window){try{window.parent.managerHelp?.refresh();}catch(e){}}}catch(e){error.textContent=e.message;done.disabled=false;}};
 }list.append(row);}}
 toggle.onclick=()=>{showDone=!showDone;toggle.textContent=showDone?'미해결 요청 보기':'해결된 요청 보기';rows();};rows();host.append(details);place();announce();
}
async function load(){if(busy){refreshQueued=true;return state;}if([...host.querySelectorAll('textarea')].some(n=>n.value.trim()))return state;busy=true;const revision=stateRevision;try{const fresh=await api();if(revision!==stateRevision){refreshQueued=true;return state;}state=fresh;render();return state;}catch(e){state=null;announce();host.hidden=false;host.replaceChildren(node('p',e.message));const retry=node('button','다시 확인');retry.onclick=()=>load().catch(()=>{});host.append(retry);throw e;}finally{busy=false;if(refreshQueued){refreshQueued=false;load().catch(()=>{});}}}
window.managerHelp={getState:()=>state,applyPlanPending(plan,rows){
 if(!state)return;
 const prefix='__fp_'+plan+'_',pending=new Set(rows.map(r=>r.field));stateRevision++;
 state.rows=state.rows.map(r=>r.field?.startsWith(prefix)&&r.status==='pending'&&!pending.has(r.field)?{...r,status:'resolved'}:r);render();
},refresh:()=>load().catch(()=>{}),ready:load().catch(()=>null),async request(field,text){if(!state)await load();
 if(state.pro_active===false&&state.mode!=='manager'){
   if(!window.proCollaboration){await new Promise((resolve,reject)=>{const el=document.createElement('script');el.src='/pro_collaboration.js?v=1';el.onload=resolve;el.onerror=()=>reject(new Error('구독 안내를 불러오지 못했습니다. 다시 시도해 주세요.'));document.head.append(el);});}
   window.proCollaboration.open();return null;
 }
 if(state.mode==='manager')throw new Error('매니저는 아래 요청함에서 처리해 주세요.');
 let consent='0';if(state.mode==='local'){
 if(!confirm('로컬매니저에게 연결을 요청하고 이 질문을 접수할까요?\n\n수락 후 건물 기본정보와 업무 기록을 열람·수정할 수 있도록 공유하는 데 동의합니다.'))return null;
 consent='1';}
 state=await api({action:'create',field,text,consent});render();return state;
}};
window.addEventListener('focus',()=>load().catch(()=>{}));
if('BroadcastChannel' in window){const channel=new BroadcastChannel('manager-plan-changes');channel.onmessage=e=>{if((e.data==='plan-deleted'||e.data==='plan-saved'))load().catch(()=>{});};}
setInterval(()=>{if(!document.hidden)load().catch(()=>{});},30000);
document.addEventListener('visibilitychange',()=>{if(!document.hidden)load().catch(()=>{});});
document.addEventListener('manager-tab-changed',e=>{if(e.detail.tab==='help')load().catch(()=>{});});
})();
