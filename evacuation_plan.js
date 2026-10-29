/* Saved plans and a guided conversation share one popup and the same saved data. */
(()=>{'use strict';
const B=JSON.parse(document.getElementById('eplBoot').textContent),app=document.getElementById('eplApp');
const common={alarm_method:'상황 전파',reporter:'119 신고 담당',controller:'피난 지휘',assembly_confirmed:'최종 집결지',headcount_method:'인원 확인 방법',special_notes:'주의사항'};
const fields={use:'용도',occupants:'최대 재실인원',vulnerable:'피난 지원 대상',primary_route:'주 대피경로',alternate_route:'대체 대피경로',guide:'피난유도 담당',checker:'잔류자 확인 담당'};
let plans=B.plans,mode='list',model=null,baseline='',busy=false,qIndex=0,viewId='',deleteId='',trash=false,pending=null,toastTimer;
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const clone=v=>JSON.parse(JSON.stringify(v));
const uid=n=>Array.from(crypto.getRandomValues(new Uint8Array(n)),x=>x.toString(16).padStart(2,'0')).join('');
const savedFields=['assembly_confirmed','controller','reporter','alarm_method','headcount_method'].filter(k=>B.savedContext?.[k]);
const savedSourceLabel=[savedFields.includes('assembly_confirmed')?'기본정보':'',savedFields.some(k=>k!=='assembly_confirmed')?'최근 자위소방대 편성표':''].filter(Boolean).join('와 ');
const blank=()=>({...Object.fromEntries(Object.keys(common).map(k=>[k,''])),stairs:null,floors:{}});
const keys=s=>{const a=[];for(let n=Number(s.below)||0;n>=1;n--)a.push('B'+n);for(let n=1;n<=(Number(s.above)||0);n++)a.push(n+'F');return a;};
const label=k=>k.startsWith('B')?'지하 '+k.slice(1)+'층':'지상 '+k.slice(0,-1)+'층';
const active=r=>r&&!r.deleted_at&&r.published&&!r.archived;
const current=()=>model?JSON.stringify({title:model.title,scope:model.scope,plan:model.plan}):'';
const dirty=()=>mode==='edit'&&current()!==baseline;
const hasDraftChanges=r=>JSON.stringify(r.draft)!==JSON.stringify(r.published)||r.title!==(r.published_title||r.title);
const status=r=>r.archived?['old','이전 계획']:!r.published?['draft','작성 중']:r.legacy?['old','기존 계획']:hasDraftChanges(r)?['draft','수정 중']:['active','사용 중'];
const icon=name=>({arrow:'<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 19 19 5M7 5h12v12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',trash:'<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v5m4-5v5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>'}[name]||'');
function toast(text,error=false){const el=document.getElementById('eplToast');clearTimeout(toastTimer);el.textContent=text;el.className='epl-toast show'+(error?' error':'');toastTimer=setTimeout(()=>el.classList.remove('show'),error?7000:3500);}
function mark(){window.recordModalDirty=dirty();const el=document.querySelector('[data-save-state]');if(el)el.textContent=dirty()?'아직 저장하지 않은 답변':model?.updated?'답변 저장됨 · '+model.updated.slice(11,16):'질문을 마칠 때마다 답변이 저장돼요';}
window.fpModalState=()=>({busy,dirty:dirty()});window.fpModalDiscard=()=>{baseline=current();window.recordModalDirty=false;};
window.eplPrintUrl=()=>{if(!['list','view','complete'].includes(mode)||trash)return null;const url=new URL(B.printUrl,location.origin);if(mode!=='list')url.searchParams.set('id',viewId);return (mode!=='list'?plans[viewId]?.published&&!plans[viewId]?.deleted_at:Object.values(plans).some(active))?url.href:null;};
window.addEventListener('beforeunload',e=>{if(busy||dirty()){e.preventDefault();e.returnValue='';}});
function scrollTop(){window.scrollTo({top:0,behavior:'instant'});}
function focusConfirmation(){
 const input=app.querySelector('#eplConfirm');if(!input||input.disabled)return;
 app.querySelector('.epl-final-check')?.scrollIntoView({block:'center',behavior:'auto'});
 input.focus({preventScroll:true});
}
function fitQuestionChoices(){
 if(mode!=='edit'||editorQuestion()?.type==='review')return;
 const panel=app.querySelector('.epl-question');
 const choices=panel?.querySelector('.epl-use-choices,.epl-count-choices,.epl-staff-roles,.epl-route-options,.epl-answer-choices,.epl-stair-counts,.epl-scope-choices,.epl-floor-shortcuts,.epl-saved-info,.epl-fields');
 if(!choices)return;
 const footer=app.querySelector('.epl-chat-footer'),bottom=Math.min(window.innerHeight,footer?.getBoundingClientRect().top??window.innerHeight);
 // Scroll only this iframe, and retain the question above its choices.
 const shift=Math.min(Math.max(0,panel.getBoundingClientRect().top-8),Math.max(0,choices.getBoundingClientRect().bottom-bottom+12));
 if(shift>0)window.scrollBy({top:shift,behavior:'instant'});
}
function focusQuestion(){
 scrollTop();
 if(editorQuestion()?.type==='review'&&!missing().length&&!model.confirmed){focusConfirmation();return;}
 app.querySelector('.epl-q-title')?.focus({preventScroll:true});
 fitQuestionChoices();requestAnimationFrame(fitQuestionChoices);
}
function scopeState(scopeId){
 const rows=Object.values(plans).filter(r=>r.scope.id===scopeId&&!r.deleted_at&&!r.archived).sort((a,b)=>String(b.updated||'').localeCompare(String(a.updated||'')));
 const published=rows.find(active),draft=rows.find(r=>!r.published||hasDraftChanges(r));
 return {published,draft,row:draft||published,kind:draft?'draft':published?'active':'empty',label:draft?(published?'수정 중':'작성 중'):published?'사용 중':'미작성'};
}
function scopeCounts(){const states=B.scopes.map(s=>scopeState(s.id));return {total:states.length,saved:states.filter(s=>s.published).length,draft:states.filter(s=>s.draft).length,empty:states.filter(s=>!s.row).length};}
function nextScope(){return B.scopes.find(s=>!scopeState(s.id).published)||B.scopes.find(s=>scopeState(s.id).draft);}
function openScope(id){
 const scope=B.scopes.find(s=>s.id===id);if(!scope)return;
 const state=scopeState(id);
 if(B.readOnly){if(state.published){viewId=state.published.id;mode='view';render();scrollTop();}return;}
 if(state.draft)start(state.draft.id);
 else if(state.published){viewId=state.published.id;mode='view';render();scrollTop();}
 else start(undefined,false,id);
}
function renderScopeProgress(){
 if(B.scopes.length<2)return '';
 const count=scopeCounts();
 return `<section class="epl-dong-progress" aria-label="동별 피난계획 현황"><div class="epl-dong-heading"><div><h2>동별 작성 현황</h2><p>기본정보의 ${count.total}개 동 · 각 동의 계획을 따로 작성해요.</p></div><strong>사용 계획 <b>${count.saved}</b> / ${count.total}</strong></div><div class="epl-dong-grid">${B.scopes.map(scope=>{const state=scopeState(scope.id);return `<button type="button" class="epl-dong-card ${state.kind}" data-action="scope-open" data-id="${esc(scope.id)}" ${B.readOnly&&!state.published?'disabled':''}><span class="epl-dong-name">${esc(scope.name)}${scope.primary?'<small>기준동</small>':''}</span><span class="epl-dong-floors">지상 ${esc(scope.above===''?'?':scope.above)}층 · 지하 ${esc(scope.below===''?'?':scope.below)}층</span><span class="epl-dong-bottom"><span class="epl-badge ${state.kind}">${state.label}</span><span>${B.readOnly?(state.published?'계획 보기':'미저장'):state.draft?'이어서 작성 →':state.published?'계획 보기 →':'작성 시작 →'}</span></span></button>`;}).join('')}</div></section>`;
}
function header(title,sub,action=''){return `<header class="epl-head"><div><div class="epl-eyebrow">${esc(B.building.name)}</div><h1>${title}</h1><p class="epl-sub">${sub}</p></div>${action}</header>`;}
function btn(text,action,kind='',extra=''){return `<button type="button" class="btn ${kind}" data-action="${action}" ${extra}>${text}</button>`;}
function printLink(id,text='인쇄·PDF'){const url=new URL(B.printUrl,location.origin);if(id)url.searchParams.set('id',id);return `<a class="btn small" href="${esc(url.pathname+url.search)}">${text}</a>`;}
function renderList(){
 const rows=Object.values(plans).filter(r=>trash?!!r.deleted_at:!r.deleted_at).sort((a,b)=>Number(a.archived)-Number(b.archived)||String(b.updated).localeCompare(String(a.updated)));
 const deleted=Object.values(plans).filter(r=>r.deleted_at).length;
 let html=header(trash?'삭제한 피난계획':'우리 건물 피난계획',trash?'필요한 계획은 다시 복원할 수 있어요.':'질문에 답하며 만들고, 저장한 계획을 한눈에 확인해요.',trash?btn('← 계획 목록','trash-close','quiet small'):B.readOnly?'':btn('＋ 새 계획','new','primary'));
 if(!trash)html+=renderScopeProgress();
 if(!rows.length)html+=`<section class="epl-empty"><div class="epl-empty-icon" aria-hidden="true">${icon(trash?'trash':'arrow')}</div><h2>${trash?'휴지통이 비어 있어요':'우리 건물의 대피방법, 함께 정해요'}</h2><p>${trash?'삭제한 계획이 여기에 표시됩니다.':'어려운 양식 대신 질문에 하나씩 답해 주세요.<br>건물 정보는 불러오고, 카드로 답하면 다음 질문으로 이어져요.'}</p>${!trash&&!B.readOnly?btn('질문에 답하며 시작하기','new','primary'):''}</section>`;
 else{
 html+=`<div class="epl-list-label">${trash?'삭제한':'저장된'} 계획 <b>${rows.length}</b></div><div class="epl-list">`;
 rows.forEach(r=>{const st=trash?['old','삭제됨']:status(r);html+=`<article class="epl-plan"><div class="epl-plan-main"><span class="epl-badge ${st[0]}">${st[1]}</span><h2>${esc(r.title)}</h2><p class="epl-sub">${esc(r.scope.name)} · ${keys(r.scope).length}개 층 · ${esc((r.updated||'').slice(0,10)||'기존 자료')}</p></div><div class="epl-actions">${trash?(B.readOnly?'':btn('복원하기','restore','small',`data-id="${esc(r.id)}"`)):`${r.published?btn('계획 보기','view','small',`data-id="${esc(r.id)}"`):''}${B.readOnly?'':btn(r.published?'답변 수정':'이어서 답하기','edit',r.published?'small':'primary small',`data-id="${esc(r.id)}"`)}${B.readOnly?'':btn('복사','copy','small quiet',`data-id="${esc(r.id)}"`)}${B.readOnly?'':btn(icon('trash')+'<span>삭제</span>','delete','small quiet epl-delete-btn',`data-id="${esc(r.id)}" aria-label="${esc(r.title)} 삭제"`)}`}</div></article>`;});
 html+='</div>';}
 html+='<div class="epl-list-foot epl-list-tools">';if(!trash&&deleted)html+=btn(icon('trash')+'휴지통 '+deleted,'trash','quiet small');else html+='<span></span>';
 if(!trash&&rows.some(active))html+=printLink('','사용 중 계획 전체 인쇄');html+='</div>';
 if(B.returnUrl)html+=`<div class="epl-list-foot"><a class="btn" href="${esc(B.returnUrl)}">← 소방계획서로 돌아가기</a></div>`;
 app.innerHTML=html;window.recordModalDirty=false;
}
function prepareFloors(){const next={};keys(model.scope).forEach(k=>{next[k]=model.plan.floors[k]||{label:label(k),...Object.fromEntries(Object.keys(fields).map(f=>[f,'']))};});model.plan.floors=next;}
function start(id,copy=false,scopeId=''){
 const row=plans[id];if(row?.deleted_at)return;
 const scope=clone(B.scopes.find(s=>s.id===scopeId)||B.scopes.find(s=>s.primary)||B.scopes[0]);
 if(row&&!copy)model={id:row.id,revision:row.revision,title:row.title,scope:clone(row.scope),plan:clone(row.draft),updated:row.updated,confirmed:false};
 else if(row)model={id:uid(12),revision:'',title:row.title+' (복사)',scope:clone(B.scopes.find(s=>s.id===row.scope.id)||scope),plan:clone(row.draft),updated:'',confirmed:false};
 else model={id:uid(12),revision:'',title:scope.name+' 피난계획',scope,plan:{...blank(),...B.prefill},updated:'',confirmed:false};
 Object.entries(B.prefill||{}).forEach(([field,value])=>{if(Object.hasOwn(common,field)&&!String(model.plan[field]??'').trim())model.plan[field]=value;});
 model.scope.above=String(model.scope.above??'');model.scope.below=String(model.scope.below??'');model.plan.floors=model.plan.floors||{};
 if(row&&!copy)prepareFloors();
 batchFloors=[];batchStep=-1;batchResume=false;floorReviewOpen=false;mode='edit';trash=false;qIndex=0;pending=null;baseline=current();
 if(row&&!copy){const all=questions(),first=all.findIndex((q,i)=>i>0&&q.type!=='review'&&!q.optional&&!answered(q));const savedIndex=all.findIndex(q=>q.type==='saved');qIndex=first<0&&!row.published?all.findIndex(q=>q.type==='review'):savedIndex>=0?savedIndex:first>0?first:1;if(first>=0&&all[first].type==='floors'){qIndex=first;openUnfinishedFloor();}}
 render();focusQuestion();if(copy)toast('복사한 답변입니다. 새 건물의 실제 대피방법을 다시 확인해 주세요.');
}
function questions(){
 const qs=[{id:'scope',type:'scope',group:0,title:B.scopes.length>1?'어느 건물의 계획을 만들까요?':'이 건물의 피난계획을 만들까요?',hint:'저장된 기본정보를 불러왔어요. 건물과 층수부터 확인해 주세요.'},
 {id:'stairs',type:'stairs',group:1,title:'이 동의 계단은 모두 몇 개인가요?',hint:'층마다 세지 않고, 지하·지상을 잇는 계단 한 줄기를 1개로 세어 주세요. 이름은 그대로 쓰거나 골라 바꿀 수 있어요.'},
 {id:'alarm_method',group:1,fields:['alarm_method'],title:'화재를 발견하면 어떻게 알리나요?',hint:'우리 건물에서 실제 사용하는 방법을 골라 주세요.',choices:['발신기 작동 후 비상방송','발신기 작동 후 육성 전파','비상방송 후 층별 연락'],placeholder:'상황 전파 방법을 적어 주세요'},
 {id:'reporter',group:1,fields:['reporter'],title:'119에 신고하고 소방대를 안내할 사람은 누구인가요?',hint:'저장된 담당자나 실제로 맡는 역할을 카드에서 골라 주세요.',choices:personChoices(),staff:true,placeholder:'예: 김OO 소방안전관리자'},
 {id:'controller',group:1,fields:['controller'],title:'전체 대피를 지휘할 사람은 누구인가요?',hint:'현장에서 지휘할 담당자 또는 직책을 정해 주세요.',choices:personChoices(),staff:true,placeholder:'이름 또는 직책'},
 {id:'assembly_confirmed',group:1,fields:['assembly_confirmed'],title:'대피 후에는 어디에 모이나요?',hint:'기본정보의 집결지를 확인하고, 정확한 위치를 적어 주세요.',placeholder:'예: 건물 동쪽 공터의 지정 집결지'},
 {id:'headcount_method',group:1,fields:['headcount_method'],title:'모두 대피했는지는 어떻게 확인하나요?',hint:'집결지에서 인원을 확인하고 보고하는 방법을 정해 주세요.',choices:['층별 담당자가 명부 확인 후 총괄 보고','부서별 책임자가 인원 확인 후 보고','담당자가 호명 후 미확인 인원 보고'],placeholder:'인원 확인 및 보고 방법'}];
 qs.push({id:'floors',type:'floors',group:2,fields:[],title:'같은 내용으로 작성할 층을 골라 주세요',hint:'지상·지하 또는 층 범위를 선택하고, 용도·인원·경로·담당자를 클릭으로 함께 정해요.'});
 qs.push({id:'special_notes',group:3,fields:['special_notes'],title:'마지막으로, 주의할 점이 있나요?',hint:'공사 중인 통로, 야간 대응 등 필요한 내용을 남겨 주세요.',optional:true,textarea:true,choices:['특이사항 없음'],placeholder:'없으면 다음으로 넘어가도 괜찮아요'},
 {id:'review',group:3,type:'review',title:'답변을 모아 피난계획을 만들었어요',hint:'현장과 다른 부분이 없는지 확인한 뒤 사용 계획으로 저장해 주세요.'});
 if(savedFields.length)qs.splice(1,0,{id:'saved',type:'saved',group:1,fields:savedFields,title:'이미 정한 내용은 불러왔어요',hint:savedSourceLabel+'에서 가져온 내용입니다. 맞는지만 확인하고 계속해 주세요.'});
 return qs.filter(q=>q.type||q.floor||!savedFields.includes(q.id));
}
const FG=window.EvacuationFloorGroups;
let batchFloors=[],batchStep=-1,batchResume=false,floorReviewOpen=false;
const floorSteps=[
 {name:'용도',fields:['use'],title:'선택한 층에는 어떤 공간이 있나요?',hint:'사무실과 창고처럼 함께 있는 공간을 모두 골라 주세요. 다시 누르면 선택이 해제돼요.',multi:true,choices:['사무실','상가·판매장','주차장','창고·기계실','숙박·주거','교육시설','공장·작업장','의료시설'],placeholder:'예: 사무실 / 창고·기계실 / 회의실'},
 {name:'인원',fields:['occupants'],title:'각 층에는 최대 몇 명이 있나요?',hint:'전체 합계가 아니라 층마다 있는 최대 인원입니다. 예: 20명을 고르면 선택한 각 층에 20명씩 적용해요.',number:true,choices:['0','10','20','30','40','50','100','200'],placeholder:'다른 인원'},
 {name:'도움 대상',fields:['vulnerable'],title:'대피할 때 도움이 필요한 사람이 있나요?',hint:'실제 이용자에게 맞는 답변을 골라 주세요. 층마다 다르면 나중에 해당 층만 바꿀 수 있어요.',choices:['없음','고령자 · 담당자 동행 지원','어린이 · 인솔자 동행 지원','거동 불편자 · 지정 지원자 도움'],placeholder:'지원 대상과 방법'},
 {name:'주 경로',fields:['primary_route'],title:'먼저 어느 길로 대피하나요?',hint:'선택한 층에서 실제로 연결되는 계단이나 출입구를 골라 주세요.',routes:true},
 {name:'대체 경로',fields:['alternate_route'],title:'그 길이 막히면 어느 길로 대피하나요?',hint:'주 대피경로와 다른 경로를 골라 주세요. 확인하지 못한 경로를 임의로 정하지 마세요.',routes:true},
 {name:'안내 담당',fields:['guide'],title:'앞에서 대피를 안내할 사람은 누구인가요?',hint:'저장된 담당자나 실제로 맡는 역할을 카드에서 골라 주세요.',staff:true},
 {name:'확인 담당',fields:['checker'],title:'남은 사람이 없는지 누가 확인하나요?',hint:'각 층의 잔류자를 확인할 담당자나 역할을 골라 주세요.',staff:true}
];
const floorSections=[{name:'용도',steps:[0]},{name:'인원',steps:[1]},{name:'도움 대상',steps:[2]},{name:'대피경로',steps:[3,4]},{name:'담당자',steps:[5,6]}];
function selectedFloors(){return keys(model.scope).filter(k=>batchFloors.includes(k));}
function floorComplete(k){return FG.complete(model.plan.floors[k],Object.keys(fields),routeProblem);}
function floorIncomplete(){return keys(model.scope).filter(k=>!floorComplete(k));}
function floorGroupLabel(){const a=selectedFloors();if(a.length===1)return label(a[0]);const all=keys(model.scope),indices=a.map(k=>all.indexOf(k));return a.length&&indices.every((n,i)=>!i||n===indices[i-1]+1)?label(a[0])+' ~ '+label(a[a.length-1]):a.length+'개 층';}
function editorQuestion(){const q=questions()[qIndex];return q?.type==='floors'&&batchStep>=0&&selectedFloors().length?{...floorSteps[batchStep],id:'floor-batch',group:2,floor:selectedFloors()[0],batch:true}:q;}
function questionRouteProblem(q,row){if(q.fields.length===1&&q.fields[0]==='primary_route'){const ref=row.primary_route_ref;return ref&&!routeText(ref)?'주 대피경로 다시 선택':'';}return routeProblem(row);}
function batchAnswered(q){return selectedFloors().every(k=>q.fields.every(f=>String(model.plan.floors[k]?.[f]??'').trim()!=='')&&(!q.routes||!questionRouteProblem(q,model.plan.floors[k])));}
function firstUnansweredStep(floorKeys){return floorSteps.findIndex(q=>floorKeys.some(k=>{const row=model.plan.floors[k]||{};return q.fields.some(f=>String(row[f]??'').trim()==='')||(q.routes&&questionRouteProblem(q,row));}));}
function openUnfinishedFloor(){
 const floor=floorIncomplete()[0];if(!floor)return false;
 batchFloors=[floor];batchStep=firstUnansweredStep(batchFloors);batchResume=true;floorReviewOpen=false;return true;
}
function writeAnswer(q,patch){if(q.batch)FG.apply(model.plan.floors,selectedFloors(),patch);else Object.entries(patch).forEach(([key,value])=>{if(value===undefined)delete values(q)[key];else values(q)[key]=value;});model.confirmed=false;mark();}
function renderFloorSummary(){return `<details class="epl-floor-summary" ${floorReviewOpen?'open':''}><summary>층별 확인 <b>${keys(model.scope).length-floorIncomplete().length} / ${keys(model.scope).length}개 층 작성</b></summary><p class="epl-sub">다른 내용이 있는 층은 해당 항목을 눌러 수정하세요.</p><div class="epl-floor-rows">${keys(model.scope).map(k=>{const row=model.plan.floors[k],done=floorComplete(k);return `<article class="epl-floor-row"><div class="epl-floor-row-head"><strong>${label(k)}</strong><span class="${done?'done':''}">${done?'✓ 작성됨':'작성 필요'}</span></div><div class="epl-floor-row-values">${floorSections.map(section=>{const i=section.steps[0],s={name:section.name,fields:section.steps.flatMap(step=>floorSteps[step].fields),number:i===1};const text=s.fields.map(f=>String(row?.[f]??'').trim()).filter(Boolean).join(' · ');return `<button type="button" data-action="floor-edit" data-floor="${k}" data-step="${i}" aria-label="${label(k)} ${s.name} 수정"><small>${s.name}</small><span>${esc(text||'선택 전')}${s.number&&text?'명':''}</span></button>`;}).join('')}</div></article>`;}).join('')}</div></details>`;}
function renderFloorHub(){
 const all=keys(model.scope),selected=selectedFloors(),options=all.map(k=>`<option value="${k}">${label(k)}</option>`).join('');
 return `<div class="epl-floor-progress"><b>${all.length-floorIncomplete().length}<small> / ${all.length}개 층 작성</small></b><span>같은 내용은 한 번에, 다른 층만 따로</span></div><div class="epl-floor-shortcuts">${[['above','전체 지상층'],['below','전체 지하층'],['missing','미작성 층 이어쓰기'],['all','전체 층'],['clear','선택 해제']].map(([v,t])=>btn(t,'floor-preset','small quiet',`data-preset="${v}" ${v==='below'&&!all.some(k=>k.startsWith('B'))?'disabled':''}`)).join('')}</div><details class="epl-floor-range"><summary>층 범위로 선택하기</summary><div><label>시작 층<select id="floorFrom">${options}</select></label><span>~</span><label>마지막 층<select id="floorTo">${options}</select></label>${btn('범위 선택','floor-range','small')}</div></details><div class="epl-floor-picks" aria-label="함께 작성할 층">${all.map(k=>`<button type="button" data-action="floor-toggle" data-floor="${k}" aria-pressed="${selected.includes(k)}" class="${selected.includes(k)?'selected':''}"><span>${label(k)}</span><small>${floorComplete(k)?'작성됨':'미작성'}</small></button>`).join('')}</div><div class="epl-floor-start"><p><b>${selected.length}개 층 선택</b><span>선택 후 고르는 항목만 바뀝니다.</span></p>${btn('함께 작성하기 →','floor-start','primary',''+(!selected.length?'disabled':''))}</div>${renderFloorSummary()}`;
}
function renderBatch(q){
 const selected=selectedFloors(),mixed=q.fields.some(field=>new Set(selected.map(k=>String(model.plan.floors[k]?.[field]??''))).size>1);
 return `${mixed?'<p class="epl-batch-mixed">'+(q.multi?'층마다 공간이 달라요. 기존 공간은 유지하고, 누른 공간만 함께 추가하거나 해제합니다. − 표시는 일부 층에만 선택된 공간이에요.':'층마다 작성한 내용이 달라요. 새로 고르면 함께 변경되고, 그대로 다음으로 넘어가면 각각의 내용이 유지됩니다.')+'</p>':''}${renderAnswers(q)}<details class="epl-batch-navigation"><summary>층·항목 변경 <span>⌄</span></summary><div class="epl-batch-banner ${batchResume?'is-resuming':''}"><div><strong>${esc(floorGroupLabel())}</strong><span>${batchResume?'남은 질문 이어쓰기 · '+(keys(model.scope).length-floorIncomplete().length)+'/'+keys(model.scope).length+'개 층 작성 완료':selected.length+'개 층에 함께 적용'+(q.number?' · 층마다 같은 인원':'')}</span></div>${btn('층 다시 선택','floor-hub','quiet small')}</div><div class="epl-batch-steps">${floorSections.map((section,i)=>`<button type="button" data-action="floor-step" data-step="${section.steps[0]}" aria-current="${section.steps.includes(batchStep)?'step':'false'}"><b>${i+1}</b><span>${section.name}</span></button>`).join('')}</div></details>`;
}
function renderStaffAnswers(q){
 const row=values(q),names=[...new Set(B.staff||[])],roles=['소방안전관리자','관리실 담당자','해당 층 근무자'],cards=[...new Set([...names.slice(0,6),...roles])];
 return `<div class="epl-staff-fields ${q.fields.length===1?'single':''}">${q.fields.map(field=>`<section><h3>${fields[field]||common[field]}</h3><div class="epl-staff-roles">${cards.map(name=>`<button type="button" data-action="field-choice" data-field="${field}" data-value="${esc(name)}" class="${row[field]===name?'selected':''}" aria-pressed="${row[field]===name}"><span>${esc(name)}</span><small>${names.includes(name)?'저장된 담당자':'역할로 지정'}</small></button>`).join('')}</div>${names.length>6?`<label class="epl-staff-select">명단 전체에서 선택<select data-person-field="${field}" aria-label="${fields[field]||common[field]} 명단에서 선택"><option value="">명단에서 선택하세요</option>${names.map(n=>`<option value="${esc(n)}" ${row[field]===n?'selected':''}>${esc(n)}</option>`).join('')}</select></label>`:''}<p class="epl-staff-selected">${esc(row[field]?'선택한 담당자: '+row[field]:'담당자를 선택해 주세요.')}</p><details class="epl-free-answer"><summary>직접 입력 · 수정 <span>＋</span></summary>${answerInput(q,field)}</details></section>`).join('')}</div>`;
}
async function batchNavigate(step,requireAnswer=false){
 const q=editorQuestion();if(requireAnswer&&!batchAnswered(q)){showError(q.multi?'선택한 각 층에 해당하는 공간을 한 개 이상 골라 주세요.':q.routes?'선택한 층의 주·대체 대피경로를 서로 다르게 골라 주세요.':'선택한 층의 답변을 골라 주세요.');return;}
 if(!await save())return;batchStep=step;if(step<0){floorReviewOpen=true;toast('층별 답변을 저장했습니다. 다른 층만 확인해 주세요.');}render();focusQuestion();
}
function autoQuestion(q){return !q.type&&!q.multi&&(!!q.choices?.length||q.routes||q.staff);}
function questionCue(q){
 if(q.multi)return '<p class="epl-flow-cue">여러 개 고른 뒤 아래 <b>선택 완료</b>를 눌러 주세요.</p>';
 if(autoQuestion(q))return '<p class="epl-flow-cue">'+(q.routes?'실제 이용할 경로를 고르면 다음 질문으로 넘어가요.':'카드를 고르면 저장 후 다음 질문으로 넘어가요.')+'</p>';
 return '';
}
function questionHelp(q){return `<details class="epl-question-help"><summary>설명 보기 <span>⌄</span></summary><p>${q.hint}</p></details>`;}

async function nextAnswer(){
 const q=editorQuestion();
 if(q.type==='floors'&&floorIncomplete().length){if(!await save())return;openUnfinishedFloor();render();focusQuestion();return;}
 if(!q.batch)return move(qIndex+1,true);
 if(!batchAnswered(q)){showError(q.multi?'선택한 각 층에 해당하는 공간을 한 개 이상 골라 주세요.':q.routes?'선택한 층의 대피경로를 확인해 주세요. 확인하지 않은 경로가 있으면 이어서 작성할 수 없습니다.':'선택한 층의 답변을 골라 주세요.');return;}
 if(!await save())return;
 const next=firstUnansweredStep(selectedFloors());
 if(next>=0){batchStep=next;}
 else if(openUnfinishedFloor()){toast(label(batchFloors[0])+'의 남은 질문을 이어서 작성해 주세요.');}
 else{batchStep=-1;batchResume=false;floorReviewOpen=true;qIndex++;toast('모든 층의 답변을 저장했어요. 마지막 질문만 남았어요.');}
 render();focusQuestion();
}

function personChoices(){return [...new Set([...B.staff,'소방안전관리자','관리실 담당자'])].filter(Boolean).slice(0,6);}
function values(q){if(q.batch)return FG.common(model.plan.floors,selectedFloors(),[...Object.keys(fields),'primary_route_ref','alternate_route_ref']);return q.floor?(model.plan.floors[q.floor]||{}):model.plan;}
function answered(q){return q.type==='floors'?floorIncomplete().length===0:q.type==='scope'?!!model.revision:q.type==='review'?false:q.type==='stairs'?(Array.isArray(model.plan.stairs)&&!stairsIssue()):q.routes?(q.fields.every(k=>String(values(q)[k]??'').trim()!=='')&&!routeProblem(values(q))):q.fields.every(k=>String(values(q)[k]??'').trim()!=='');}
function missing(){const out=[];questions().forEach((q,i)=>{if(q.type==='floors'){floorIncomplete().forEach(k=>{const step=firstUnansweredStep([k]);out.push({index:i,label:label(k)+' · '+(floorSteps[step]?.name||'답변 확인'),floor:k,step:Math.max(0,step)});});return;}if(q.type==='stairs'){if(!Array.isArray(model.plan.stairs)||stairsIssue())out.push({index:i,label:'계단 개수·이름 확인'});return;}if(q.routes){const issue=routeProblem(values(q));if(issue)out.push({index:i,label:label(q.floor)+' · '+issue});}if((q.type&&q.type!=='saved')||q.optional)return;q.fields.forEach(field=>{if(!String(values(q)[field]??'').trim())out.push({index:i,label:(q.floor?label(q.floor)+' · ':'')+(fields[field]||common[field])});});});return out;}
function renderScope(){
 const saved=!!model.revision;let html='';
 if(!saved&&B.scopes.length>1)html+=`<div class="epl-scope-choices">${B.scopes.map(s=>`<button type="button" class="epl-scope ${s.id===model.scope.id?'selected':''}" data-action="scope" data-id="${esc(s.id)}" aria-pressed="${s.id===model.scope.id}"><b>${esc(s.name)} ${s.primary?'<span class="epl-badge">기준동</span>':''}</b><small>지상 ${esc(s.above===''?'?':s.above)}층 · 지하 ${esc(s.below===''?'?':s.below)}층</small></button>`).join('')}</div>`;
 else html+=`<div class="epl-building-answer"><span class="epl-building-icon">${icon('arrow')}</span><div><b>${esc(model.scope.name)}</b><small>${esc(B.building.address)}</small></div><span class="epl-badge">불러온 정보</span></div>`;
 html+=`<div class="epl-fields two-small"><div class="epl-field wide"><label for="eplTitle">계획 이름</label><input id="eplTitle" data-model="title" maxlength="100" value="${esc(model.title)}"></div><div class="epl-field"><label for="eplAbove">지상 층수</label><input id="eplAbove" data-scope="above" type="number" inputmode="numeric" min="0" max="100" value="${esc(model.scope.above)}" ${saved?'readonly':''}></div><div class="epl-field"><label for="eplBelow">지하 층수</label><input id="eplBelow" data-scope="below" type="number" inputmode="numeric" min="0" max="30" value="${esc(model.scope.below)}" ${saved?'readonly':''}></div></div>`;
 if(saved)html+='<p class="epl-sub">층 구성이 바뀌었다면 목록에서 복사해 새 계획을 만들 수 있어요.</p>';
 return html;
}
function routeText(ref){
 if(ref==='pending')return '대체 대피경로 현장 확인 필요';
 const stair=(model.plan.stairs||[]).find(s=>s.id===ref),name=stair?.name||({direct_1:'출입구',direct_2:'다른 출입구'})[ref];
 return name?name+' → 건물 밖 → 지정 집결지':null;
}
function syncRouteText(){Object.values(model.plan.floors||{}).forEach(row=>['primary_route','alternate_route'].forEach(field=>{const ref=row[field+'_ref'],text=ref?routeText(ref):null;if(text!==null)row[field]=text;}));}
function routeProblem(row){
 for(const field of ['primary_route','alternate_route']){const ref=row[field+'_ref'];if(ref==='pending')return '대체 대피경로 현장 확인';if(ref&&!routeText(ref))return '변경된 계단의 대피경로 다시 선택';}
 if(row.primary_route_ref&&row.primary_route_ref===row.alternate_route_ref)return '주 경로와 다른 대체 경로 선택';return '';
}
function stairsIssue(){
 if(!Array.isArray(model.plan.stairs))return '';
 const names=new Set();for(const s of model.plan.stairs){const name=String(s.name||'').trim(),key=name.replace(/\s/g,'');if(!name||[...name].length>40||/[\x00-\x1f]/.test(name))return '계단 이름은 한 줄, 40자 이내로 입력해 주세요.';if(names.has(key))return '계단을 구분할 수 있도록 서로 다른 이름을 골라 주세요.';names.add(key);}return '';
}
function validateStairs(){const issue=stairsIssue();if(issue)throw Error(issue);}
function renderStairs(){
 const stairs=model.plan.stairs,count=Array.isArray(stairs)?stairs.length:null,presets=['주계단','보조계단','동쪽 계단','서쪽 계단','남쪽 계단','북쪽 계단','외부 계단'];
 let html=`<div class="epl-stair-counts">${[1,2,3,4].map(n=>`<button type="button" class="epl-stair-count ${count===n?'selected':''}" data-action="stair-count" data-count="${n}" aria-pressed="${count===n}"><b>${n}</b><span>개</span>${count===n?'<i aria-hidden="true">✓</i>':''}</button>`).join('')}</div><div class="epl-stair-other">${btn('계단 없음','stair-count','small '+(count===0?'primary':'quiet'),'data-count="0" aria-pressed="'+(count===0)+'"')}<details ${count>4?'open':''}><summary>5개 이상 · 직접 선택</summary><div class="epl-stair-custom-count"><label for="eplStairCount">전체 계단 수</label><select id="eplStairCount">${Array.from({length:16},(_,i)=>i+5).map(n=>`<option value="${n}" ${n===(count>4?count:5)?'selected':''}>${n}개</option>`).join('')}</select>${btn('개수 적용','stair-count-apply','small')}</div></details></div>`;
 if(count===null)return html+'<p class="epl-sub">개수를 고르면 계단 이름이 자동으로 만들어져요.</p>';
 if(!count)return html+'<div class="epl-hint">층별 질문에서 실제 이용하는 출입구를 골라 주세요.</div>';
 html+='<div class="epl-stair-names"><div class="epl-stair-names-head"><strong>계단 이름</strong><span>그대로 써도 돼요</span></div>';
 stairs.forEach((s,i)=>{const defaultName='계단 '+(i+1),options=[defaultName,...presets],custom=!options.includes(s.name);html+=`<div class="epl-stair-row"><span class="epl-stair-number" aria-hidden="true">${i+1}</span><div><label for="stair-name-${s.id}">${defaultName} 이름</label><select id="stair-name-${s.id}" data-stair-name="${s.id}" aria-label="${defaultName} 이름">${options.map(name=>`<option value="${esc(name)}" ${s.name===name?'selected':''} ${stairs.some(other=>other.id!==s.id&&other.name===name)?'disabled':''}>${esc(name)}${name===defaultName?' (기본)':''}</option>`).join('')}<option value="custom" ${custom?'selected':''}>직접 이름 입력</option></select><input type="text" data-stair-custom="${s.id}" value="${esc(s.name)}" maxlength="40" aria-label="${defaultName} 이름 직접 입력" placeholder="예: 중앙 계단" ${custom?'':'hidden'}></div></div>`;});
 html+='</div><p class="epl-sub">여기서 정한 이름을 각 층의 대피경로에서 선택해요.</p>';
 const affected=Object.entries(model.plan.floors||{}).filter(([,row])=>routeProblem(row)==='변경된 계단의 대피경로 다시 선택').map(([key])=>label(key));
 if(affected.length)html+=`<div class="epl-hint">계단 수가 바뀌었어요. ${esc(affected.join(' · '))}의 기존 경로는 남겨 두었으니, 층별 질문에서 다시 골라 주세요.</div>`;
 return html;
}
function renderRoutes(q){
 const row=values(q),stairs=model.plan.stairs||[],options=[...stairs.map(s=>({id:s.id,name:s.name,kind:'계단'})),{id:'direct_1',name:'출입구로 바로',kind:'건물 밖'},{id:'direct_2',name:'다른 출입구로 바로',kind:'건물 밖'}];
 let html=`<div class="epl-route-stairs"><span>${Array.isArray(model.plan.stairs)?'계단 '+stairs.length+'개 등록됨':'계단 개수를 먼저 정하면 선택지가 나와요'}</span>${btn(Array.isArray(model.plan.stairs)?'계단 설정':'계단 개수 정하기','stairs-jump','quiet small')}</div>`;
 html+='<div class="epl-route-fields '+(q.fields.length===1?'single':'')+'">';
 q.fields.forEach(field=>{const i=field==='primary_route'?0:1,ref=row[field+'_ref']||'',opposite=q.fields.length===1&&i===0?'':row[(i===0?'alternate_route':'primary_route')+'_ref'],text=row[field]||'',issue=ref==='pending'?'현장 확인 전에는 작성 중으로만 저장됩니다.':ref&&!routeText(ref)?'이전에 고른 계단이 목록에서 없어졌어요. 다른 경로를 선택해 주세요.':'';
 html+=`<section class="epl-route-field"><div class="epl-route-title"><span>${i+1}</span><div><h3>${i===0?'주 대피경로':'대체 대피경로'}</h3><p>${i===0?'먼저 이용할 경로':'주 경로가 막히면 이용할 다른 경로'}</p></div></div><div class="epl-route-options">${options.map(o=>`<button type="button" class="epl-route-option ${ref===o.id?'selected':''}" data-action="route-choice" data-route-field="${field}" data-floor="${q.floor}" data-ref="${o.id}" aria-pressed="${ref===o.id}" ${opposite===o.id?'disabled':''}><span>${esc(o.name)}</span><small>${opposite===o.id?'다른 경로로 선택됨':ref===o.id?'✓ 선택됨':o.kind}</small></button>`).join('')}</div><div class="epl-route-result"><small>${ref?'선택한 경로':text?'작성한 경로':'선택 전'}</small><p data-route-value="${field}">${esc(text||'위에서 경로를 골라 주세요.')}</p></div>${issue?`<p class="epl-route-warning">${esc(issue)}</p>`:''}<details class="epl-free-answer epl-route-custom" ${text&&!ref?'open':''}><summary>직접 경로 입력 · 수정 <span>＋</span></summary>${answerInput(q,field)}</details>${i===1?btn('대체 경로 확인 필요','route-choice','quiet small',`data-route-field="${field}" data-floor="${q.floor}" data-ref="pending"`):''}</section>`;
 });
 return html+'</div>'+renderRouteCopy(q);
}
function updateRouteSelection(){
 const q=editorQuestion();if(!q.routes)return;const row=values(q);
 app.querySelectorAll('.epl-route-option').forEach(button=>{const field=button.dataset.routeField,ref=button.dataset.ref,other=field==='primary_route'?'alternate_route':'primary_route',selected=row[field+'_ref']===ref;button.classList.toggle('selected',selected);button.setAttribute('aria-pressed',String(selected));button.disabled=!(q.fields.length===1&&field==='primary_route')&&row[other+'_ref']===ref;button.querySelector('small').textContent=button.disabled?'다른 경로로 선택됨':selected?'✓ 선택됨':ref.startsWith('stair_')?'계단':'건물 밖';});
 app.querySelectorAll('[data-route-value]').forEach(el=>{const value=row[el.dataset.routeValue]||'';el.textContent=value||'위에서 경로를 골라 주세요.';el.previousElementSibling.textContent=row[el.dataset.routeValue+'_ref']?'선택한 경로':value?'작성한 경로':'선택 전';const warning=el.closest('.epl-route-field').querySelector('.epl-route-warning');if(warning){const ref=row[el.dataset.routeValue+'_ref'];warning.hidden=ref!=='pending'&&(!ref||!!routeText(ref));}});
}

function renderFloorReference(q){
 if(!q.floor&&!['floors','scope'].includes(q.type))return '';
 const refs=B.floorReference?.[model.scope.id]||[],chosen=q.batch?selectedFloors():q.floor?[q.floor]:keys(model.scope);
 const rows=refs.filter(r=>chosen.includes(r.key));if(!rows.length)return '';
 const body=rows.map(r=>`<div><b>${esc(r.label)}</b><span>${r.area!==''?esc(Number(r.area).toLocaleString(undefined,{maximumFractionDigits:6}))+'㎡':'면적 자료 없음'}</span><small>${esc(r.use||'용도 미입력')} · ${r.source==='manual'?'직접 입력':r.source==='registry'?'대장 자료':'자료 없음'}</small></div>`).join('');
 return `<details class="epl-floor-reference" ${q.floor&&chosen.length===1?'open':''}><summary>층별 면적·용도 참고 <span>${rows.length}개 층</span></summary>${body}<p>기본정보에 저장된 자료입니다. 실제 재실인원과 대피경로는 현장에서 확인해 주세요.</p></details>`;
}
function answerInput(q,field){const v=values(q)[field]??'',id='field-'+(q.floor?q.floor+'-':'')+field;let placeholder=q.placeholder||'이름 또는 직책';
 if(field==='primary_route')placeholder='예: 사무실 → 동쪽 계단 → 1층 출입구 → 집결지';
 if(field==='alternate_route')placeholder='주 경로가 막혔을 때 이용할 실제 경로';
 const max=field==='special_notes'?1200:q.floor?(['guide','checker'].includes(field)?100:field==='use'?120:300):500;
 const attrs=`id="${id}" data-field="${field}" ${q.floor?`data-floor="${q.floor}"`:''} maxlength="${max}" placeholder="${esc(placeholder)}"`;
 const control=q.routes||q.textarea?`<textarea ${attrs} rows="3">${esc(v)}</textarea>`:`<input ${attrs} ${q.number?'type="number" inputmode="numeric" min="0" max="99999"':'type="text"'} ${q.staff?'list="eplStaff"':''} value="${esc(v)}">`;
 return `<div class="epl-field ${q.number?'epl-number-answer':''}"><label for="${id}">${q.fields.length===1?(q.number?'최대 재실인원':'내 답변'):(fields[field]||common[field])}</label><div class="epl-input-wrap">${control}${q.number?'<span class="epl-input-unit">명</span>':''}</div></div>`;
}
function renderChoices(q){if(!q.choices?.length)return '';const value=String(values(q)[q.fields[0]]??'');return `<div class="epl-answer-choices ${q.number?'epl-count-choices':q.choices.length>=4&&q.choices.every(v=>v.length<=12)?'compact':''}">${q.choices.map((v,i)=>`<button type="button" class="epl-choice ${value===v?'selected':''}" data-action="choice" data-value="${esc(v)}" aria-pressed="${value===v}"><span class="epl-choice-mark">${value===v?'✓':q.number?'':String(i+1).padStart(2,'0')}</span><span>${esc(v)}${q.number?'<small>명</small>':''}</span></button>`).join('')}</div>`;}
function useRows(q){return q.batch?selectedFloors().map(k=>model.plan.floors[k]):[values(q)];}
function renderUseChoices(q){
 const sets=useRows(q).map(row=>FG.useParts(row.use)),extra=[...new Set(sets.flat())].filter(v=>!q.choices.includes(v));
 const buttons=items=>items.map(value=>{const count=sets.filter(parts=>parts.includes(value)).length,state=count===sets.length?'true':count?'mixed':'false';return `<button type="button" class="epl-use-choice ${state==='true'?'selected':state==='mixed'?'mixed':''}" data-action="use-choice" data-value="${esc(value)}" aria-pressed="${state}"><span class="epl-use-check" aria-hidden="true">${state==='true'?'✓':state==='mixed'?'−':''}</span><span>${esc(value)}${state==='mixed'?'<small>일부 층에 선택됨</small>':''}</span></button>`;}).join('');
 return `<div class="epl-use-choices">${buttons(q.choices)}</div>${extra.length?`<div class="epl-use-existing"><p>직접 작성한 공간</p><div class="epl-use-choices">${buttons(extra)}</div></div>`:''}`;
}
function renderUses(q){return `<div data-use-choices>${renderUseChoices(q)}</div><details class="epl-free-answer"><summary>직접 입력 · 전체 수정 <span>＋</span></summary><p class="epl-sub">다른 공간은 / 로 구분해 적어 주세요.${q.batch?' 선택한 층의 용도를 입력한 내용으로 모두 바꿉니다.':''}</p>${answerInput(q,'use')}</details>`;}
function renderAnswers(q){
 if(q.multi)return renderUses(q);
 if(q.staff)return renderStaffAnswers(q);
 if(q.routes)return renderRoutes(q);
 const inputs=`<div class="epl-fields ${q.fields.length===2&&!q.routes?'':'epl-single'}">${q.fields.map(field=>answerInput(q,field)).join('')}</div>`;
 if(!q.choices?.length)return inputs+renderRouteCopy(q);
 const value=String(values(q)[q.fields[0]]??'');
 return renderChoices(q)+`<details class="epl-free-answer" ${value&&!q.choices.includes(value)?'open':''}><summary>${q.number?'직접 입력':'직접 입력 · 답변 수정'} <span>＋</span></summary>${inputs}</details>`;
}
function renderRouteCopy(q){if(q.batch)return '';const others=keys(model.scope).filter(k=>k!==q.floor);if(!q.routes||!others.length)return '';return `<details class="epl-copy"><summary>같은 경로를 다른 층에도 적용하기</summary><div class="epl-copy-body"><p class="epl-sub">선택한 층의 주·대체 경로를 바꿉니다. 인원과 담당자는 따로 답해 주세요.</p><div class="epl-checks">${others.map(k=>`<label><input type="checkbox" data-copy-source="${q.floor}" value="${k}">${label(k)}</label>`).join('')}</div>${btn('선택한 층에 적용','copy-routes','small',`data-floor="${q.floor}"`)}</div></details>`;}
function preview(plan,scope,locationHtml=B.locationHtml||''){return `${locationHtml}${Array.isArray(plan.stairs)?`<div class="epl-stair-summary"><b>계단 ${plan.stairs.length}개</b><span>${esc(plan.stairs.map(s=>s.name).join(' · ')||'계단 없음')}</span></div>`:''}<dl class="epl-review-grid">${Object.entries(common).map(([k,title])=>`<div class="${['headcount_method','special_notes'].includes(k)?'wide':''}"><dt>${title}</dt><dd>${esc(plan[k]||'—')}</dd></div>`).join('')}</dl>${keys(scope).map(k=>{const r=plan.floors[k]||{};return `<section class="epl-review-floor"><h3>${label(k)}</h3><dl class="epl-review-grid">${Object.entries(fields).map(([f,title])=>`<div class="${['vulnerable','primary_route','alternate_route'].includes(f)?'wide':''}"><dt>${title}</dt><dd>${esc(String(r[f]??'').trim()!==''?String(r[f])+(f==='occupants'?'명':''):'—')}</dd></div>`).join('')}</dl></section>`;}).join('')}`;}
function renderSavedInfo(q){return `${B.locationHtml||''}<div class="epl-saved-info">${q.fields.map(field=>{const linked=B.savedContext[field],value=String(model.plan[field]??''),same=value.trim()===linked.value.trim();return `<article class="epl-saved-item" data-saved-field="${field}"><div class="epl-saved-label"><span>${esc(common[field])}</span><small>${same?'불러옴':'작성한 내용 유지'}</small></div><p>${esc(value||'내용을 입력해 주세요')}</p><div class="epl-saved-source">${esc(same?linked.source:'피난계획에 작성한 내용')}</div><details class="epl-free-answer"><summary>내용 확인 · 수정 <span>＋</span></summary>${!same?`<div class="epl-source-update"><small>${esc(linked.source)}</small><p>${esc(linked.value)}</p>${btn('이 내용으로 반영','saved-value','small',`data-field="${field}"`)}</div>`:''}${answerInput({fields:[field],textarea:true,placeholder:'이 계획에서 사용할 내용을 입력해 주세요'},field)}</details></article>`;}).join('')}</div><p class="epl-saved-note">여기서 수정한 내용은 이 피난계획에만 적용됩니다.</p>`;}
function confirmationCopy(){return model.confirmed?'확인했어요. 아래에서 사용 계획으로 저장하세요.':'아래 확인 칸을 눌러야 사용 계획으로 저장할 수 있어요.';}
function reviewButtonText(){return missing().length?'미작성 항목 확인 →':model.confirmed?'사용 계획으로 저장':'마지막 확인으로 이동 ↑';}
function renderReview(){
 const miss=missing();
 const ready=miss.length?`<div class="epl-alert"><strong>${miss.length}개 항목만 더 답해 주세요</strong><div class="epl-missing">${miss.map(m=>btn(m.label,'jump','small',`data-index="${m.index}" ${m.floor?`data-floor="${m.floor}" data-step="${m.step}"`:''}`)).join('')}</div></div>`:`<div class="epl-ready"><span>✓</span><div><b>${esc(model.scope.name)}의 답변을 모두 작성했어요</b><p>마지막 확인 한 번이면 사용 계획으로 저장할 수 있어요.</p></div></div>`;
 return `${ready}<section class="epl-final-check ${model.confirmed?'is-confirmed':''} ${miss.length?'is-incomplete':''}" aria-labelledby="eplCheckTitle"><div class="epl-final-check-heading"><span>마지막 단계</span><h3 id="eplCheckTitle">현장과 같은지 확인해 주세요</h3><p id="eplCheckHelp" role="status">${miss.length?'미작성 항목을 마치면 마지막 확인을 할 수 있어요.':confirmationCopy()}</p></div><label class="epl-confirm" for="eplConfirm"><input id="eplConfirm" type="checkbox" aria-describedby="eplCheckHelp" ${model.confirmed?'checked':''} ${miss.length?'disabled':''}><span><b>실제 계단·출입구, 담당자와 집결지를 확인했습니다.</b><small>${esc(model.scope.name)}의 사용 계획으로 저장합니다.</small></span></label></section><details class="epl-review-details"><summary><span>작성한 계획 내용 확인</span><small>${keys(model.scope).length}개 층 · 펼쳐보기</small></summary><div class="epl-review-content">${preview(model.plan,model.scope)}</div></details>${Object.values(plans).some(r=>r.id!==model.id&&r.scope.id===model.scope.id&&active(r))?'<p class="epl-sub">기존 사용 계획은 이전 계획으로 보관됩니다.</p>':''}${B.scopes.length>1?'<p class="epl-dong-review-note">이 동만 저장됩니다. 다른 동은 저장 후 동별 현황에서 이어서 작성해 주세요.</p>':''}`;
}
function syncConfirmation(){
 const card=app.querySelector('.epl-final-check'),help=app.querySelector('#eplCheckHelp'),publish=app.querySelector('[data-action="publish"]');
 card?.classList.toggle('is-confirmed',model.confirmed);
 if(help)help.textContent=confirmationCopy();
 if(publish){publish.disabled=false;publish.textContent=reviewButtonText();}
}

function answerMenu(qs){return `<details class="epl-answer-menu"><summary><span>답변 목록</span><small>원하는 질문으로 이동</small></summary><div>${qs.map((q,i)=>`<button type="button" data-action="jump" data-index="${i}" class="${i===qIndex?'current':''}"><span>${q.type==='scope'?'건물 확인':q.type==='stairs'?'계단 개수·이름':q.type==='floors'?'층 묶음 작성·확인':q.type==='saved'?'기본정보·편성표 확인':q.type==='review'?'최종 확인':(q.floor?label(q.floor)+' · ':'')+q.fields.map(k=>fields[k]||common[k]).join(' / ')}</span><b>${answered(q)?'✓':'›'}</b></button>`).join('')}</div></details>`;}
function renderEdit(){
 const qs=questions();qIndex=Math.max(0,Math.min(qIndex,qs.length-1));const q=editorQuestion(),progress=Math.round(qIndex/(qs.length-1)*100);
 app.innerHTML=`<header class="epl-conversation-head"><div><span class="epl-eyebrow">피난계획 · 문답으로 작성</span><h1>${esc(model.scope.name)}</h1></div>${btn('← 목록','list','quiet small')}</header><div class="epl-journey"><div class="epl-progress" role="progressbar" aria-label="문답 진행" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${progress}"><span style="width:${progress}%"></span></div><div class="epl-question-meta"><span>${q.batch?esc(floorGroupLabel())+' · '+(batchStep+1)+' / '+floorSteps.length:q.floor?esc(model.scope.name)+' · '+label(q.floor):['건물 확인','공통 안내','층별 대피','마무리'][q.group]}</span><span>${q.batch?(keys(model.scope).length-floorIncomplete().length)+' / '+keys(model.scope).length+'개 층 완료':(qIndex+1)+' / '+qs.length}</span></div></div><div id="eplLeave" class="epl-alert" hidden><strong>아직 저장하지 않은 답변이 있어요</strong><div class="epl-actions">${btn('저장하고 목록','save-leave','small primary')}${btn('저장하지 않고 나가기','discard','small')}${btn('계속 답하기','stay','small quiet')}</div></div><section class="epl-question">${q.optional?'<span class="epl-badge old epl-optional-badge">선택 질문</span>':''}<h2 class="epl-q-title" tabindex="-1">${q.title}</h2><div id="eplError" class="epl-alert" role="alert" hidden></div>${questionCue(q)}${renderFloorReference(q)}<div class="epl-answer-area">${q.batch?renderBatch(q):q.type==='floors'?renderFloorHub():q.type==='scope'?renderScope():q.type==='stairs'?renderStairs():q.type==='saved'?renderSavedInfo(q):q.type==='review'?renderReview():renderAnswers(q)}</div>${questionHelp(q)}</section>${answerMenu(qs)}<datalist id="eplStaff">${B.staff.map(name=>`<option value="${esc(name)}"></option>`).join('')}</datalist><footer class="epl-chat-footer"><div class="epl-chat-footer-meta"><span data-save-state></span>${q.type||q.optional||q.batch?'':btn('나중에 답하기','skip','quiet small')}</div><div class="epl-chat-footer-actions">${btn(q.batch&&batchStep===0?'← 층 선택':'← 이전',q.batch?'batch-prev':'prev','quiet',qIndex===0?'disabled':'')}<div>${btn('저장','save','')}${btn(q.batch?(q.multi?'선택 완료 →':batchStep===floorSteps.length-1?'답변 완료 →':'이 답변으로 계속 →'):q.type==='floors'?(floorIncomplete().length?'미작성 층 이어쓰기 →':'층별 확인 완료 →'):q.type==='review'?reviewButtonText():q.type==='scope'?'이 건물로 시작 →':q.type==='saved'?'확인하고 계속 →':q.optional?'답변 확인하기 →':autoQuestion(q)?'이 답변으로 계속 →':'확인하고 다음 →',q.batch?'batch-next':q.type==='review'?'publish':'next','primary')}</div></div></footer>`;
 mark();
}
function renderComplete(){
 const row=plans[viewId];if(!row)return toList();
 const count=scopeCounts(),next=nextScope();
 app.innerHTML=header('사용 계획으로 저장했어요',esc(row.scope.name)+' · 저장 완료',btn('계획 목록','list','quiet small'))+`<section class="epl-complete-card"><span class="epl-complete-mark" aria-hidden="true">✓</span><h2>${esc(row.scope.name)} 피난계획이 준비됐어요</h2><p>${count.total>1?`전체 ${count.total}개 동 중 ${count.saved}개 동의 사용 계획이 있어요.`:'저장한 계획은 언제든 확인하거나 인쇄할 수 있어요.'}</p><div class="epl-actions">${btn('저장한 계획 보기','view','small',`data-id="${esc(row.id)}"`)}${printLink(row.id)}</div>${next&&!B.readOnly?`<div class="epl-next-dong"><span>다음 동 이어서 작성</span><h3>${esc(next.name)}</h3><p>${scopeState(next.id).draft?'작성하던 답변을 그대로 이어갑니다.':'기본정보의 층수를 불러와 이 동의 계획을 따로 만듭니다.'}</p>${btn(esc(next.name)+' '+(scopeState(next.id).draft?'이어서 작성 →':'작성 시작 →'),'scope-open','primary',`data-id="${esc(next.id)}"`)}</div>`:count.total>1?'<div class="epl-all-done">모든 동에 사용 계획이 저장되어 있어요.</div>':''}</section>${renderScopeProgress()}`;
 window.recordModalDirty=false;
}
function renderView(){const r=plans[viewId];if(!r?.published||r.deleted_at){mode='list';return renderList();}const st=status(r);
 app.innerHTML=header('저장된 피난계획',esc(r.scope.name),btn('← 목록','list','quiet small'))+`<section class="epl-panel"><div class="epl-detail-heading"><div><span class="epl-badge ${st[0]}">${st[1]}</span><h2 style="margin-top:7px">${esc(r.published_title||r.title)}</h2><p class="epl-sub">${esc(r.published_at||'기존 저장 자료')}</p></div><div class="epl-actions">${B.readOnly?'':btn('답변 수정','edit','small',`data-id="${esc(r.id)}"`)}${printLink(r.id)}${B.readOnly?'':btn('삭제','delete','small quiet epl-delete-btn',`data-id="${esc(r.id)}"`)}</div></div>${hasDraftChanges(r)&&!r.archived?'<div class="epl-hint">현재 사용 중인 저장본입니다. 수정한 답변은 마지막 확인·저장 후 반영됩니다.</div>':''}${r.legacy?'<div class="epl-hint">기존 계획을 그대로 가져왔습니다. 답변 수정을 눌러 현재 대피방법과 맞는지 확인해 주세요.</div>':''}${preview(r.published,r.scope,Object.hasOwn(B.locationByPlan||{},r.id)?B.locationByPlan[r.id]:(B.locationHtml||''))}</section>`;
}
function renderDelete(){const r=plans[deleteId];if(!r||r.deleted_at)return toList();app.innerHTML=header('계획 삭제','',btn('← 목록','list','quiet small'))+`<section class="epl-delete-card"><div class="epl-delete-symbol">${icon('trash')}</div><h2>이 계획을 삭제할까요?</h2><p class="epl-delete-name">${esc(r.title)}</p><p>목록과 인쇄 대상에서 제외됩니다.<br>잘못 삭제해도 휴지통에서 복원할 수 있어요.</p>${active(r)?'<div class="epl-hint">이 동의 사용 계획도 해제됩니다. 이미 작성한 소방계획서의 내용은 바뀌지 않습니다.</div>':''}<div id="eplError" class="epl-alert" role="alert" hidden></div><div class="epl-delete-actions">${btn('취소','list')}${btn('계획 삭제','confirm-delete','danger',`data-id="${esc(r.id)}"`)}</div></section>`;}
function render(){app.classList.toggle('is-writing',mode==='edit');if(B.error){app.innerHTML=header('피난계획','')+`<div class="epl-alert" role="alert">${esc(B.error)}</div>`;return;}if(mode==='list')renderList();else if(mode==='edit')renderEdit();else if(mode==='complete')renderComplete();else if(mode==='delete')renderDelete();else renderView();}
function showError(message){const el=document.getElementById('eplError');if(el){el.hidden=false;el.textContent=message;el.scrollIntoView({block:'nearest',behavior:'smooth'});}else toast(message,true);}
function validateScope(){if(!model.title.trim())throw Error('계획 이름을 입력해 주세요.');if(!/^\d+$/.test(model.scope.above)||!/^\d+$/.test(model.scope.below)||Number(model.scope.above)>100||Number(model.scope.below)>30||Number(model.scope.above)+Number(model.scope.below)<1)throw Error('지상·지하 층수를 확인해 주세요. 없는 층은 0으로 입력하면 됩니다.');prepareFloors();}
async function send(payload){
 if(busy||B.readOnly)return null;payload.csrf=B.csrf;
 const fingerprint=JSON.stringify(payload);if(!pending||pending.fingerprint!==fingerprint)pending={fingerprint,id:uid(16)};payload.request_id=pending.id;
 busy=true;window.recordModalPending=true;app.setAttribute('aria-busy','true');const disabled=Array.from(app.querySelectorAll('button,input,textarea,select')).map(el=>[el,el.disabled]);disabled.forEach(([el])=>el.disabled=true);
 const oldError=document.getElementById('eplError');if(oldError)oldError.hidden=true;
 const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),20000);
 try{
 const response=await fetch(B.endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(payload),signal:controller.signal});
 if(response.redirected)throw Error('로그인 상태를 확인할 수 없습니다. 답변을 복사해 두고 다시 로그인해 주세요.');
 let result;try{result=await response.json();}catch(_){throw Error('서버 응답을 확인하지 못했습니다. 화면의 내용은 유지되니 다시 시도해 주세요.');}
 if(!response.ok||!result.ok)throw Error(result.error||'요청을 완료하지 못했습니다. 다시 시도해 주세요.');
 plans=result.plans;pending=null;return plans[result.id];
 }catch(e){showError(e.name==='AbortError'?'응답이 늦습니다. 화면의 내용은 유지됩니다. 다시 시도하면 중복 없이 확인합니다.':e.message);return null;
 }finally{clearTimeout(timer);busy=false;window.recordModalPending=false;app.removeAttribute('aria-busy');disabled.forEach(([el,was])=>el.disabled=was);mark();}
}
async function save(action='draft'){
 try{validateScope();validateStairs();syncRouteText();}catch(e){showError(e.message);return false;}
 const r=await send({id:model.id,revision:model.revision,title:model.title,scope_id:model.scope.id,above:model.scope.above,below:model.scope.below,plan:model.plan,confirmed:!!model.confirmed,action});if(!r)return false;
 model.revision=r.revision;model.updated=r.updated;model.title=r.title;model.plan=clone(r.draft);model.scope=clone(r.scope);model.scope.above=String(model.scope.above);model.scope.below=String(model.scope.below);baseline=current();window.recordModalDirty=false;document.dispatchEvent(new Event('record-saved'));return true;
}
async function changeStatus(action,id){const r=plans[id];if(!r)return;const result=await send({id,revision:r.revision,action});if(!result)return;if(action==='restore')trash=false;toList();toast(action==='delete'?'계획을 삭제했습니다. 휴지통에서 복원할 수 있어요.':result.archived?'이전 계획으로 복원했습니다. 현재 사용 계획은 그대로 유지됩니다.':'계획을 복원했습니다.');}
function toList(){mode='list';model=null;baseline='';deleteId='';render();scrollTop();document.dispatchEvent(new Event('record-saved'));}
async function move(index,requireAnswer=false){const q=questions()[qIndex];if(requireAnswer&&(!q.type||q.type==='saved'||q.type==='stairs'||q.type==='floors')&&!q.optional&&!answered(q)){showError(q.type==='floors'?'아직 작성하지 않은 층이 '+floorIncomplete().length+'개 있어요. ‘작성 안 된 층’을 선택해 함께 작성하거나 ‘층별 확인’에서 필요한 항목을 골라 주세요.':q.type==='saved'?'비워둔 내용을 입력하거나 불러온 내용으로 반영해 주세요.':'답변을 선택하거나 직접 입력해 주세요. 나중에 답하기로 넘어갈 수도 있어요.');return;}if(!await save())return;qIndex=index;if(requireAnswer&&questions()[qIndex]?.type==='floors'){batchStep=-1;batchResume=false;if(floorIncomplete().length&&Object.values(model.plan.floors).some(row=>Object.keys(fields).some(f=>String(row[f]??'').trim()!=='')))openUnfinishedFloor();}render();focusQuestion();}
function updateChoiceState(){const q=editorQuestion(),value=String(values(q)[q.fields?.[0]]??'');if(q.multi){const box=app.querySelector('[data-use-choices]');if(box)box.innerHTML=renderUseChoices(q);return;}app.querySelectorAll('.epl-choice').forEach((el,i)=>{const selected=el.dataset.value===value;el.classList.toggle('selected',selected);el.setAttribute('aria-pressed',String(selected));el.querySelector('.epl-choice-mark').textContent=selected?'✓':q.number?'':String(i+1).padStart(2,'0');});}
app.addEventListener('input',e=>{if(mode!=='edit'||busy)return;const el=e.target;if(el.dataset.stairCustom){const stair=(model.plan.stairs||[]).find(s=>s.id===el.dataset.stairCustom);if(stair){stair.name=el.value;syncRouteText();model.confirmed=false;mark();}return;}if(el.dataset.field){const q=editorQuestion();writeAnswer(q,{[el.dataset.field]:el.value,...(['primary_route','alternate_route'].includes(el.dataset.field)?{[el.dataset.field+'_ref']:undefined}:{})});if(['primary_route','alternate_route'].includes(el.dataset.field))updateRouteSelection();model.confirmed=false;updateChoiceState();const savedCard=el.closest('[data-saved-field]');if(savedCard){savedCard.querySelector(':scope > p').textContent=el.value||'내용을 입력해 주세요';savedCard.querySelector('.epl-saved-label small').textContent='수정 중';savedCard.querySelector('.epl-saved-source').textContent='이 피난계획에서 수정한 내용';}}else if(el.dataset.model){model[el.dataset.model]=el.value;model.confirmed=false;}else if(el.dataset.scope){model.scope[el.dataset.scope]=el.value;model.confirmed=false;}mark();});
app.addEventListener('change',async e=>{if(busy)return;if(e.target.dataset.personField){if(e.target.value){writeAnswer(editorQuestion(),{[e.target.dataset.personField]:e.target.value});render();await nextAnswer();}return;}if(e.target.dataset.stairName){const id=e.target.dataset.stairName,s=(model.plan.stairs||[]).find(s=>s.id===id);if(!s)return;const box=e.target.closest('.epl-stair-row'),input=box.querySelector('[data-stair-custom]');if(e.target.value==='custom'){input.hidden=false;input.focus();return;}s.name=e.target.value;syncRouteText();model.confirmed=false;render();mark();return;}if(e.target.id==='eplConfirm'){model.confirmed=e.target.checked;syncConfirmation();}});
app.addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.isComposing&&mode==='edit'&&!busy&&e.target.matches('input[data-field]')&&editorQuestion().fields?.length===1){e.preventDefault();app.querySelector('[data-action="batch-next"],[data-action="next"]')?.click();}});
app.addEventListener('click',async e=>{
 const b=e.target.closest('button[data-action]');if(!b||busy)return;const act=b.dataset.action,id=b.dataset.id;
 if(B.readOnly&&['new','edit','copy','delete','confirm-delete','restore'].includes(act))return;
 if(act==='new')start();
 else if(act==='scope-open')openScope(id);
 else if(act==='edit'||act==='copy')start(id,act==='copy');
 else if(act==='view'){viewId=id;mode='view';render();scrollTop();}
 else if(act==='scope'){model.scope=clone(B.scopes.find(s=>s.id===id));model.scope.above=String(model.scope.above??'');model.scope.below=String(model.scope.below??'');model.title=model.scope.name+' 피난계획';render();}
 else if(act==='list'){if(dirty()){const el=document.getElementById('eplLeave');el.hidden=false;el.scrollIntoView({block:'center',behavior:'smooth'});}else toList();}
 else if(act==='stay')document.getElementById('eplLeave').hidden=true;
 else if(act==='discard')toList();
 else if(act==='trash'||act==='trash-close'){trash=act==='trash';toList();}
 else if(act==='delete'){deleteId=id;mode='delete';render();scrollTop();}
 else if(act==='confirm-delete')await changeStatus('delete',id);
 else if(act==='restore')await changeStatus('restore',id);
 else if(act==='floor-preset'){
  const all=keys(model.scope),v=b.dataset.preset;if(v==='missing'){if(openUnfinishedFloor()){render();focusQuestion();}else toast('모든 층의 답변을 작성했어요.');return;}batchResume=false;batchFloors=v==='all'?all:v==='above'?all.filter(k=>!k.startsWith('B')):v==='below'?all.filter(k=>k.startsWith('B')):v==='missing'?floorIncomplete():[];render();
 }
 else if(act==='floor-toggle'){const k=b.dataset.floor;batchFloors=batchFloors.includes(k)?batchFloors.filter(v=>v!==k):[...batchFloors,k];render();}
 else if(act==='floor-range'){batchFloors=FG.range(keys(model.scope),app.querySelector('#floorFrom').value,app.querySelector('#floorTo').value);render();}
 else if(act==='floor-start'){if(!selectedFloors().length)return;batchResume=false;batchStep=Math.max(0,firstUnansweredStep(selectedFloors()));render();focusQuestion();}
 else if(act==='floor-edit'){batchResume=false;batchFloors=[b.dataset.floor];batchStep=Number(b.dataset.step)||0;render();focusQuestion();}
 else if(act==='floor-hub'){batchResume=false;await batchNavigate(-1);}
 else if(act==='floor-step'){batchResume=false;await batchNavigate(Number(b.dataset.step));}
 else if(act==='batch-prev')await batchNavigate(batchStep-1);
 else if(act==='batch-next')await nextAnswer();
 else if(act==='field-choice'){writeAnswer(editorQuestion(),{[b.dataset.field]:b.dataset.value});render();await nextAnswer();}
 else if(act==='prev'&&qIndex>0)await move(qIndex-1);
 else if(act==='jump'){if(b.dataset.floor){batchResume=true;batchFloors=[b.dataset.floor];batchStep=Number(b.dataset.step)||0;}else if(questions()[Number(b.dataset.index)]?.type==='floors'){batchStep=-1;batchResume=false;}await move(Number(b.dataset.index));}
 else if(act==='next')await nextAnswer();
 else if(act==='skip')await move(qIndex+1);
 else if(act==='save'||act==='save-leave'||act==='publish'){
  if(act==='publish'){
   const miss=missing();if(miss.length){const m=miss[0];batchStep=-1;batchResume=false;if(m.floor){batchFloors=[m.floor];batchStep=m.step;batchResume=true;}await move(m.index);return;}
   if(!model.confirmed){focusConfirmation();return;}
  }
  if(!await save(act==='publish'?'publish':'draft'))return;
  if(act==='publish'){viewId=model.id;model=null;baseline='';mode='complete';render();scrollTop();app.querySelector('h1')?.setAttribute('tabindex','-1');app.querySelector('h1')?.focus({preventScroll:true});}
  else if(act==='save-leave'){toList();toast('답변을 저장했습니다. 나중에 이어서 작성할 수 있어요.');}
  else{render();toast('답변을 저장했습니다.');}
 }
 else if(act==='stair-count'||act==='stair-count-apply'){
  const value=act==='stair-count'?b.dataset.count:app.querySelector('#eplStairCount').value;
  if(!/^\d+$/.test(value)||Number(value)>20){showError('계단 개수는 0~20개로 골라 주세요.');return;}
  const old=model.plan.stairs||[];model.plan.stairs=Array.from({length:Number(value)},(_,i)=>old.find(s=>s.id==='stair_'+(i+1))||{id:'stair_'+(i+1),name:'계단 '+(i+1)});
  syncRouteText();model.confirmed=false;render();mark();
 }
 else if(act==='route-choice'){
  const field=b.dataset.routeField,ref=b.dataset.ref;writeAnswer(editorQuestion(),{[field+'_ref']:ref,[field]:routeText(ref)||''});render();mark();if(ref!=='pending')await nextAnswer();
 }
 else if(act==='stairs-jump'){await move(questions().findIndex(q=>q.type==='stairs'));}
 else if(act==='saved-value'){
  const field=b.dataset.field;if(!savedFields.includes(field))return;model.plan[field]=B.savedContext[field].value;model.confirmed=false;render();mark();
 }
 else if(act==='use-choice'){
  const q=editorQuestion();if(!q.multi)return;
  try{FG.toggleUse(model.plan.floors,q.batch?selectedFloors():[q.floor],b.dataset.value);}catch(error){showError(error.message);return;}
  model.confirmed=false;render();app.querySelectorAll('[data-action="use-choice"]').forEach(el=>{if(el.dataset.value===b.dataset.value)el.focus({preventScroll:true});});mark();
 }
 else if(act==='choice'){
  const q=editorQuestion();writeAnswer(q,{[q.fields[0]]:b.dataset.value});const el=app.querySelector('[data-field]');if(el)el.value=b.dataset.value;if(q.number){const custom=app.querySelector('.epl-free-answer');if(custom)custom.open=false;}updateChoiceState();mark();await nextAnswer();
 }
 else if(act==='copy-routes'){
  const from=b.dataset.floor,r=model.plan.floors[from],targets=Array.from(app.querySelectorAll(`input[data-copy-source="${from}"]:checked`));
  if(!String(r.primary_route).trim()||!String(r.alternate_route).trim()){toast('이 층의 주 경로와 대체 경로를 먼저 입력해 주세요.',true);return;}
  if(routeProblem(r)){toast('현재 층의 대피경로를 먼저 확인해 주세요.',true);return;}
  if(!targets.length){toast('경로를 적용할 층을 선택해 주세요.',true);return;}
  targets.forEach(el=>{const to=model.plan.floors[el.value];to.primary_route=r.primary_route;to.alternate_route=r.alternate_route;['primary_route','alternate_route'].forEach(field=>{if(r[field+'_ref'])to[field+'_ref']=r[field+'_ref'];else delete to[field+'_ref'];});el.checked=false;});model.confirmed=false;mark();toast(targets.length+'개 층에 적용했습니다. 실제 경로를 다시 확인해 주세요.');
 }
});
render();
})();
