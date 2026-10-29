(()=>{
 'use strict';
 const root=document.querySelector('.auto-page');if(!root)return;
 const $=s=>document.querySelector(s),status=$('#auto-status'),review=$('#import-review'),list=$('#import-rows');
 let job=JSON.parse($('#import-data').textContent),busy=false;const edits=new Map();
 const groups=[['choose','주소 선택','여러 주소가 검색됐습니다. 실제 거래처 주소 하나를 골라주세요.'],['address','주소 수정','주소를 찾지 못했거나 입력 내용이 바뀌었습니다. 도로명·건물번호를 확인하세요.'],['delay','조회 지연','일시적인 지연은 최대 3회 자동 재시도합니다. 계속 남으면 잠시 후 다시 조회하세요.'],['building','건물정보 확인','주소는 확인됐지만 건축물대장을 확인하지 못했습니다. 기본정보를 확인하거나 주소만 먼저 등록할 수 있습니다.'],['pending','조회 대기','순서대로 자동 조회합니다.'],['ready','등록 가능','주소와 건축물대장이 조회됐습니다. 여러 동인 건물도 함께 등록할 수 있습니다.'],['done','등록 완료','사전등록 거래처에서 이어서 관리할 수 있습니다.']];
 const kind=r=>r.address_id?'done':r.status==='edited'?'address':r.needs_enrichment?'pending':r.kind||(r.status==='ready'?'ready':r.status==='choose'?'choose':r.status==='error'?'delay':r.status==='not_found'?'address':'pending');
 const retryable=r=>kind(r)==='delay'&&r.retryable!==false;
 const canRegister=r=>!r.address_id&&kind(r)==='ready';
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 function adopt(next,finished=null){if(finished)edits.delete(finished);job=next;for(const r of job.rows){const edit=edits.get(r.id);if(edit&&!r.address_id){Object.assign(r,edit);r.status='edited';r.kind='address';r.candidates=[];}}}
 function message(text,error=false){status.hidden=!text;status.textContent=text;status.classList.toggle('error',error);}
 function updateCounts(){if(!job)return;const counts={};for(const r of job.rows)counts[kind(r)]=(counts[kind(r)]||0)+1;const need=['choose','address','delay','building'].reduce((n,k)=>n+(counts[k]||0),0);
  $('#import-counts').innerHTML=`<span>전체 <b>${job.rows.length}</b></span><span class="ready">등록 가능 <b>${counts.ready||0}</b></span><span class="needs">확인 필요 <b>${need}</b></span>${counts.pending?`<span>조회 대기 <b>${counts.pending}</b></span>`:''}<span>등록됨 <b>${counts.done||0}</b></span>`;
  $('#register-all').disabled=busy||!counts.ready;$('#register-all').textContent=counts.ready?`확인된 ${counts.ready}개 한 번에 등록`:'등록 가능한 항목이 없습니다';
  $('#lookup-all').disabled=busy||!job.rows.some(r=>kind(r)==='pending'||kind(r)==='delay'||r.status==='edited');
 }
 function lock(on){busy=on;root.setAttribute('aria-busy',String(on));root.querySelectorAll('button,input,textarea').forEach(e=>e.disabled=on);if(!on)updateCounts();}
 async function api(action,values={},form=null){
  const body=form||new FormData();body.set('action',action);body.set('csrf',root.dataset.csrf);if(job)body.set('id',job.id);for(const [k,v] of Object.entries(values))body.set(k,v);
  let response;try{response=await fetch('/manager_client_import.php',{method:'POST',body,credentials:'same-origin'});}catch{const e=new Error('연결이 지연되어 다시 조회합니다.');e.retryable=true;throw e;}
  let data;try{data=await response.json();}catch{const e=new Error('응답을 받지 못했습니다. 다시 조회합니다.');e.retryable=true;throw e;}
  if(!response.ok||!data.ok)throw new Error(data.error||'처리하지 못했습니다.');return data.job;
 }
 function rowHTML(r){const k=kind(r),done=k==='done',ready=k==='ready',partial=k==='building';
  return `<article class="import-row ${done?'registered':ready?'verified':'needs-review'}" data-row="${esc(r.id)}"><div class="row-head"><b>${esc(r.name||'거래처명 자동 확인')}</b><span class="badge">${esc(groups.find(g=>g[0]===k)?.[1]||'확인 필요')}</span></div>${done?`<p>${esc(r.resolved_address||r.address)}</p><a href="/manager_draft.php?id=${esc(r.address_id)}">기본정보 확인 →</a>${r.needs_enrichment?'<button type="button" class="secondary" data-fill>층별 면적·용도 채우기</button>':''}`:`<div class="row-fields"><input data-name aria-label="거래처명" maxlength="150" placeholder="거래처명 · 비워두면 건물명 사용" value="${esc(r.name)}"><input data-address aria-label="주소" maxlength="500" value="${esc(r.address)}"><button type="button" data-lookup class="secondary">다시 조회</button></div>${ready||partial?`<p class="official">✓ ${esc(r.resolved_address)}</p><small>${esc(r.note)}</small>`:`<p class="row-reason">${esc(r.status==='edited'?'수정한 내용을 다시 조회해 주세요.':k==='choose'?'입력한 주소로 여러 곳이 검색됐습니다. 아래에서 실제 주소를 선택하세요.':r.error||r.result||'자동으로 주소를 확인합니다.')}</p>`}${k==='choose'?`<div class="candidate-list">${r.candidates.map(c=>`<button type="button" data-candidate="${esc(c.key)}"><b>${esc(c.name||c.address)}</b><small>${esc(c.address)}</small><span>이 주소 선택 →</span></button>`).join('')}</div>`:''}${partial?'<button type="button" class="secondary" data-register-one>주소만 먼저 등록</button>':''}`}</article>`;
 }
 function render(){if(!job)return;review.hidden=false;if($('#import-input'))$('#import-input').hidden=true;
  const open=new Map([...list.querySelectorAll('details[data-group]')].map(el=>[el.dataset.group,el.open]));
  list.innerHTML=groups.map(([k,title,desc])=>{const rows=job.rows.filter(r=>kind(r)===k);if(!rows.length)return '';const expanded=open.has(k)?open.get(k):false;return `<details class="review-group group-${k}" data-group="${k}" ${expanded?'open':''}><summary><span>${title}</span><b>${rows.length}개</b></summary><p class="group-description">${desc}</p>${rows.map(rowHTML).join('')}</details>`;}).join('');updateCounts();
 }
 async function lookupOne(row,el=null,candidate=null){
  const values={row:row.id};let action='lookup';if(candidate){values.candidate=candidate;action='choose';}else{values.name=el?.querySelector('[data-name]')?.value??row.name;values.address=el?.querySelector('[data-address]')?.value??row.address;if(row.status!=='edited'&&(row.needs_enrichment||kind(row)==='delay'&&row.status==='ready'))action='enrich';}
  adopt(await api(action,values),row.id);render();
 }
 async function withRetries(id,el=null,candidate=null,progress=''){
  for(let attempt=1;attempt<=3;attempt++){
   const row=job.rows.find(r=>r.id===id);message(`${progress}${attempt>1?'조회 지연 자동 재시도 '+attempt+'/3':'주소와 건축물대장을 확인하고 있습니다.'}`);
   try{await lookupOne(row,attempt===1?el:null,attempt===1?candidate:null);}catch(e){const current=job.rows.find(r=>r.id===id);current.error=e.message;current.status='error';current.kind='delay';current.retryable=e.retryable===true;render();if(!current.retryable)break;}
   lock(true);const current=job.rows.find(r=>r.id===id);if(!retryable(current)||attempt===3)break;
   await new Promise(resolve=>setTimeout(resolve,attempt*1000));
  }
 }
 async function lookupAll(){if(!job||busy)return;const ids=job.rows.filter(r=>kind(r)==='pending'||kind(r)==='delay'||r.status==='edited').map(r=>r.id);if(!ids.length)return;
  lock(true);try{for(let i=0;i<ids.length;i++)await withRetries(ids[i],null,null,`${i+1} / ${ids.length} · `);const remaining=job.rows.filter(r=>kind(r)==='delay').length;message(remaining?`자동 재조회 후 ${remaining}개가 남았습니다. ‘조회 지연’에서 확인하세요.`:'조회가 끝났습니다. 확인할 항목을 이유별로 모았습니다.',remaining>0);}finally{lock(false);}
 }
 async function register(selected){if(busy||!selected.length)return;lock(true);message('거래처를 등록하고 있습니다.');try{adopt(await api('register',{selected:JSON.stringify(selected),revision:job.revision}));render();const failed=job.rows.filter(r=>selected.includes(r.id)&&!r.address_id);message(failed.length?'일부 항목을 등록하지 못했습니다. 항목별 결과를 확인하세요.':'등록했습니다. 확인이 필요한 항목은 목록에 남겨두었습니다.',failed.length>0);parent.postMessage({type:'manager-addresses-changed'},location.origin);}catch(e){message(e.message,true);}finally{lock(false);}}
 $('#import-file')?.addEventListener('change',e=>{$('#import-filename').textContent=e.target.files[0]?.name||'최대 600개 · 5MB';});
 $('#import-form')?.addEventListener('submit',async e=>{e.preventDefault();if(busy)return;const body=new FormData(e.currentTarget);body.set('token',root.dataset.token);lock(true);message('목록을 읽고 있습니다.');try{job=await api('prepare',{},body);history.replaceState(null,'','?id='+job.id);render();lock(false);await lookupAll();}catch(err){message(err.message,true);}finally{lock(false);}});
 list.addEventListener('input',e=>{const el=e.target.closest('[data-row]');if(!el||busy)return;const r=job.rows.find(r=>r.id===el.dataset.row);r.name=el.querySelector('[data-name]').value;r.address=el.querySelector('[data-address]').value;r.status='edited';r.kind='address';edits.set(r.id,{name:r.name,address:r.address});el.classList.remove('verified');el.querySelector('.badge').textContent='다시 조회 필요';el.querySelector('.candidate-list')?.remove();el.querySelector('[data-register-one]')?.remove();el.querySelector('.official')?.remove();let reason=el.querySelector('.row-reason');if(!reason){reason=document.createElement('p');reason.className='row-reason';el.append(reason);}reason.textContent='수정한 내용을 다시 조회해 주세요.';updateCounts();});
 list.addEventListener('focusout',e=>{if(e.target.matches('[data-name],[data-address]'))setTimeout(()=>{if(!busy&&!list.contains(document.activeElement))render();},0);});
 list.addEventListener('click',async e=>{const button=e.target.closest('[data-lookup],[data-candidate],[data-register-one],[data-fill]');if(!button||busy)return;const el=button.closest('[data-row]'),r=job.rows.find(r=>r.id===el.dataset.row);if(button.hasAttribute('data-fill')){lock(true);message('기존 입력은 유지하면서 층별 정보를 채우고 있습니다.');try{adopt(await api('fill',{row:r.id}));render();message('층별 면적·용도를 채웠습니다.');parent.postMessage({type:'manager-addresses-changed'},location.origin);}catch(e){message(e.message,true);}finally{lock(false);}return;}if(button.hasAttribute('data-register-one')){await register([r.id]);return;}lock(true);try{await withRetries(r.id,el,button.dataset.candidate||null);message('조회 결과를 이유별로 정리했습니다.');}finally{lock(false);}});
 $('#lookup-all').addEventListener('click',()=>lookupAll());$('#register-all').addEventListener('click',()=>register(job.rows.filter(canRegister).map(r=>r.id)));
 if(job){render();if(job.rows.some(r=>kind(r)==='pending'))lookupAll();}
})();
