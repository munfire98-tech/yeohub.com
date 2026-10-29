(function(){
 'use strict';
 const dialog=document.getElementById('flame-retardant-guide');
 const dataNode=document.getElementById('frg-data');
 const rules=window.FlameRetardantGuideRules;
 if(!dialog||!dataNode||!rules)return;
 let data;try{data=JSON.parse(dataNode.textContent);}catch(_){return;}
 const q=s=>dialog.querySelector(s), qa=s=>Array.from(dialog.querySelectorAll(s));
 let dongs=Array.isArray(data.dongs)&&data.dongs.length?data.dongs:[{name:'내 건물',use:'',floors:0}];
 const states=dongs.map(()=>({uses:[],multi:'',height:'',apartment:'',step:0}));
 let dongIndex=0,tab='check',opener=null,lock=null;
 let year=data.year,planId='',revision='',buildingRevision='',csrf='',checks=[],loading=false,saving=false,loaded=false,loadGeneration=0,saveError='';
 const state=()=>states[dongIndex];
 const answers=()=>{const s=state();return {uses:s.uses,multi:s.multi,height:s.height,apartment:s.apartment};};
 const extra=[{id:'none',title:'위 용도에 해당하지 않아요',detail:'층수·영업 형태를 이어서 확인'},{id:'unknown',title:'정확히 모르겠어요',detail:'알고 있는 조건으로 확인'}];
 const brief={neighborhood:'공연장·종교집회장 등 포함',assembly:'옥내에 있는 시설',sports:'수영장 제외',medical:'병원 등 의료시설',dorm:'학교 전체가 아닌 합숙소',welfare:'법령상 노유자시설',lodging:'숙박이 가능한 시설',broadcast:'방송통신시설 중 해당 용도'};
 const choices=rules.uses.concat(extra);
 choices.forEach(use=>{
  const button=document.createElement('button');button.type='button';button.dataset.frgUse=use.id;button.setAttribute('aria-pressed','false');button.setAttribute('aria-label',use.title+' · '+use.detail);
  const title=document.createElement('b'),detail=document.createElement('small');title.textContent=use.title;detail.textContent=brief[use.id]||use.detail;
  button.append(title,detail);q('[data-frg-uses]').append(button);
 });
 const selectionNote=document.createElement('p');selectionNote.className='frg-selection-note';selectionNote.hidden=true;selectionNote.setAttribute('aria-live','polite');q('[data-frg-uses]').after(selectionNote);
 function populateDongs(){
  q('[data-frg-dong]').replaceChildren();dongs.forEach((dong,index)=>{const option=document.createElement('option');option.value=String(index);option.textContent=dong.name||('동 '+(index+1));q('[data-frg-dong]').append(option);});
  q('[data-frg-dong-label]').hidden=dongs.length<2;q('[data-frg-dong]').value=String(dongIndex);
 }
 populateDongs();
 q('[data-frg-building-name]').textContent=data.name||'내 건물';
 function renderSave(){
  q('[data-frg-plan-year]').textContent=year+'년 소방계획서 · 6번 방화구획·마감재·방염물품 유지관리';
  q('[data-frg-save-title]').textContent=year+'년 계획서에 이 확인 내용을 넣을까요?';
  const saved=checks.find(c=>c.dong?.id===dongs[dongIndex].id);
  const same=!!saved&&JSON.stringify(saved.answers)===JSON.stringify(answers());
  if(same)q('[data-frg-save-title]').textContent=year+'년 계획서에 반영되어 있어요.';
  const button=q('[data-frg-save]');button.hidden=tab!=='check'||state().step!==2||!data.canSave||same;q('[data-frg-next]').hidden=!button.hidden;button.disabled=!data.canSave||loading||saving||!loaded||same;
  button.textContent=saving?'저장 중…':same?'✓ 6번에 반영됨':'6번에 저장';
  const status=q('[data-frg-save-status]');status.dataset.error=saveError?'true':'false';
  status.textContent=!data.canSave?'계획서 저장은 PRO 이용 중에 가능합니다.':saveError||(loading?'저장된 내용을 확인하고 있어요.':same?'저장 완료 · '+saved.checked_at:'저장하면 문서 보기와 인쇄 / PDF에도 표시됩니다.');
 }
 async function api(method,body){
  const target=new URL(data.api,location.origin);target.searchParams.set('year',String(year));
  const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),15000);
  try{const response=await fetch(target.href,{method,credentials:'same-origin',headers:{Accept:'application/json',...(body?{'Content-Type':'application/json'}:{})},...(body?{body:JSON.stringify(body)}:{}),signal:controller.signal});
   let value;try{value=await response.json();}catch(_){throw new Error('로그인 상태를 확인하고 방염 확인을 다시 열어 주세요.');}
   if(!response.ok||!value.ok)throw new Error(value.error||'저장한 내용을 확인하지 못했습니다.');return value;
  }catch(error){if(error.name==='AbortError')throw new Error('응답이 늦어 확인하지 못했습니다. 닫았다가 다시 열어 저장 여부를 확인해 주세요.');throw error;}
  finally{clearTimeout(timer);}
 }
 async function loadPlan(){
  if(!data.canSave){render();return;}
  const generation=++loadGeneration;loading=true;loaded=false;saveError='';render();
  try{const value=await api('GET');if(generation!==loadGeneration||!dialog.open)return;
   planId=value.plan_id;revision=value.revision;buildingRevision=value.building_revision;csrf=value.csrf;checks=value.checks;
   dongs=value.dongs;dongIndex=0;states.splice(0,states.length,...dongs.map(dong=>{const saved=checks.find(c=>c.dong?.id===dong.id);return saved?{...saved.answers,uses:saved.answers.uses.slice(),step:2}:{uses:[],multi:'',height:'',apartment:'',step:0};}));populateDongs();loaded=true;
  }catch(error){if(generation===loadGeneration)saveError=error.message||'저장된 내용을 불러오지 못했습니다.';}
  finally{if(generation===loadGeneration){loading=false;render();}}
 }
 async function savePlan(){
  if(!loaded||loading||saving||!data.canSave)return;
  saving=true;saveError='';render();
  try{const value=await api('POST',{csrf,plan_id:planId,revision,building_revision:buildingRevision,dong:dongs[dongIndex].id,answers:answers()});
   planId=value.plan_id;revision=value.revision;checks=value.checks;
   const saved=checks.find(c=>c.dong?.id===dongs[dongIndex].id);if(saved)Object.assign(state(),saved.answers,{uses:saved.answers.uses.slice()});
   try{if('BroadcastChannel' in window){const channel=new BroadcastChannel('manager-plan-changes');channel.postMessage('plan-saved');channel.close();}}catch(_){}
  }catch(error){saveError=error.message||'저장하지 못했습니다. 다시 시도해 주세요.';}
  finally{saving=false;render();if(!saveError)q('[data-frg-next]').focus({preventScroll:true});}
 }
 function renderContext(){
  const dong=dongs[dongIndex];
  q('[data-frg-saved]').textContent=[dongs.length>1?(dong.name||'선택한 동'):'',dong.use||'용도 미등록',dong.floors>0?'지상 '+dong.floors+'층':'층수 미등록'].filter(Boolean).join(' · ');
  q('[data-frg-floor-hint]').textContent=(dong.floors>0?'저장된 정보는 지상 '+dong.floors+'층입니다. 실제 층수를 확인해 주세요. ':'')+'영업장이 있는 층이 아니라 건물의 층수입니다.';
  const declared=Math.max(dongs.length,Number(data.declaredCount)||0);
  const note=q('[data-frg-dong-note]');note.hidden=declared<2;
  note.textContent='총 '+declared+'개 동 중 선택한 동만 확인합니다.'+(declared>dongs.length?' 정보가 없는 동은 기본정보를 보완한 뒤 별도로 확인해 주세요.':' 동마다 실제 용도가 다르면 각각 확인해 주세요.');
 }
 function canContinue(){
  const s=state();if(tab==='items'||s.step===2)return true;
  if(s.step===0)return s.uses.length>0;
  return s.multi==='yes'||(!!s.multi&&!!s.height&&(s.height==='low'||!!s.apartment));
 }
 function renderResult(){
  const result=rules.assess(state()),box=q('[data-frg-result]');box.dataset.result=result.state;
  const messages={applies:['방염 적용 대상에 해당해요','선택한 용도 또는 조건에 해당하는 시설·범위에서 방염 대상물품을 확인해 주세요.'],outside:['선택한 조건에서는 대상이 아니에요','시행령 제30조의 조건을 선택한 답변과 비교한 결과입니다. 다른 입점 시설이나 실제 용도가 있다면 각각 다시 확인해 주세요.'],uncertain:['추가 확인이 필요해요','아직 확인되지 않은 조건이 있어 대상 여부를 정할 수 없습니다. 아래 항목을 연결 업체 또는 관할 소방서에 확인해 주세요.']};
  q('[data-frg-result-title]').textContent=messages[result.state][0];q('[data-frg-result-copy]').textContent=messages[result.state][1];
  const list=q('[data-frg-reasons]');list.replaceChildren();
  const items=result.state==='outside'?['선택한 용도 없음 · 다중이용업소 해당 없음',state().height==='low'?'건물 층수 10층 이하':'11층 이상 기준의 아파트등 제외 조건']:result.reasons.concat(result.missing);
  items.forEach(text=>{const li=document.createElement('li');li.textContent=text;list.append(li);});
 }
 function render(moveFocus=false){
  const s=state();renderContext();renderSave();
  qa('[data-frg-use], [data-frg-question] button, [data-frg-dong], [data-frg-tab], [data-frg-back]').forEach(el=>el.disabled=loading||saving);q('[data-frg-close]').disabled=saving;
  qa('[data-frg-panel]').forEach(el=>el.hidden=el.dataset.frgPanel!==tab);
  qa('[data-frg-tab]').forEach(el=>el.setAttribute('aria-pressed',String(el.dataset.frgTab===tab)));
  qa('[data-frg-step]').forEach(el=>el.hidden=Number(el.dataset.frgStep)!==s.step);
  qa('[data-frg-dot]').forEach(el=>{if(Number(el.dataset.frgDot)===s.step)el.setAttribute('aria-current','step');else el.removeAttribute('aria-current');});
  qa('[data-frg-use]').forEach(el=>el.setAttribute('aria-pressed',String(s.uses.includes(el.dataset.frgUse))));
  const selected=rules.uses.filter(use=>s.uses.includes(use.id));selectionNote.textContent=selected.map(use=>use.detail).join(' / ');selectionNote.hidden=!selected.length;
  qa('[data-frg-question]').forEach(group=>group.querySelectorAll('button').forEach(el=>el.setAttribute('aria-pressed',String(s[group.dataset.frgQuestion]===el.dataset.value))));
  q('[data-frg-apartment]').hidden=!s.height||s.height==='low';
  if(s.step===2)renderResult();
  const back=q('[data-frg-back]'),next=q('[data-frg-next]');back.hidden=tab==='check'&&s.step===0;back.textContent=tab==='items'?'대상 확인으로':'이전';
  next.disabled=!canContinue()||loading||saving;next.textContent=tab==='items'?'확인했어요':s.step===2?'대상물품 보기':s.step===1?'결과 확인':selected.length?'결과 확인':'다음';
  q('[data-frg-action-hint]').textContent=tab==='items'?'':s.step===0?(s.uses.length?'선택한 용도로 확인합니다':'용도를 선택해 주세요'):s.step===1?(!canContinue()?'각 항목을 선택해 주세요':'선택한 조건으로 확인합니다'):'내용을 바꿀 땐 이전을 누르세요';
  if(moveFocus){q('.frg-scroll').scrollTop=0;const heading=tab==='items'?q('.frg-items-intro h3'):q('[data-frg-step="'+s.step+'"] h3');heading.focus({preventScroll:true});}
 }
 function open(button){
  if(dialog.open)return;opener=button;year=Number(document.querySelector('[data-book-card]')?.dataset.selectedYear)||Number(data.year);tab='check';saveError='';
  // Native dialog isolates keyboard focus; lock the page too for mobile Safari.
  const body=document.body,html=document.documentElement;
  lock={x:window.scrollX,y:window.scrollY,body:{},htmlOverflow:html.style.overflow};
  ['position','top','left','width','overflow'].forEach(key=>lock.body[key]=body.style[key]);
  body.style.position='fixed';body.style.top=(-lock.y)+'px';body.style.left=(-lock.x)+'px';body.style.width='100%';body.style.overflow='hidden';html.style.overflow='hidden';
  render();dialog.showModal();q('[data-frg-close]').focus({preventScroll:true});loadPlan();
 }
 function unlock(){
  loadGeneration++;loading=false;
  if(lock){const saved=lock;lock=null;Object.keys(saved.body).forEach(key=>document.body.style[key]=saved.body[key]);document.documentElement.style.overflow=saved.htmlOverflow;
   const behavior=document.documentElement.style.scrollBehavior;document.documentElement.style.scrollBehavior='auto';window.scrollTo(saved.x,saved.y);document.documentElement.style.scrollBehavior=behavior;}
  if(opener&&opener.isConnected)opener.focus({preventScroll:true});
 }
 document.querySelectorAll('[data-frg-open]').forEach(button=>button.addEventListener('click',()=>open(button)));
 dialog.addEventListener('close',unlock);
 dialog.addEventListener('cancel',event=>{if(saving)event.preventDefault();});
 dialog.addEventListener('click',event=>{
  const button=event.target.closest('button');if(!button)return;
  if(button.hasAttribute('data-frg-close')){if(!saving)dialog.close();return;}
  if(button.hasAttribute('data-frg-save')){savePlan();return;}
  if(loading||saving)return;
  if(button.hasAttribute('data-frg-tab')||button.hasAttribute('data-frg-items')){tab=button.dataset.frgTab||'items';render(true);return;}
  const s=state();
  if(button.hasAttribute('data-frg-use')){
   saveError='';const id=button.dataset.frgUse;if(extra.some(use=>use.id===id))s.uses=s.uses.includes(id)?[]:[id];
   else {s.uses=s.uses.filter(value=>value!=='none'&&value!=='unknown');s.uses=s.uses.includes(id)?s.uses.filter(value=>value!==id):s.uses.concat(id);}
   // A changed answer must not reuse answers from an earlier route through the questions.
   s.multi='';s.height='';s.apartment='';render();return;
  }
  const question=button.closest('[data-frg-question]');if(question){saveError='';s[question.dataset.frgQuestion]=button.dataset.value;if(question.dataset.frgQuestion==='height')s.apartment='';render();return;}
  if(button.hasAttribute('data-frg-back')){if(tab==='items')tab='check';else if(s.step===2&&rules.uses.some(use=>s.uses.includes(use.id)))s.step=0;else s.step=Math.max(0,s.step-1);render(true);return;}
  if(button.hasAttribute('data-frg-next')&&canContinue()){
   if(tab==='items'){dialog.close();return;}
   if(s.step===2)tab='items';else if(s.step===0)s.step=rules.uses.some(use=>s.uses.includes(use.id))?2:1;else s.step=2;render(true);
  }
 });
 q('[data-frg-dong]').addEventListener('change',event=>{dongIndex=Number(event.target.value);saveError='';render(true);});
 render();
})();
