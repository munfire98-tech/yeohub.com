(()=>{
 const form=document.getElementById('facility-form'),panels=[...form.querySelectorAll('.scope-panel')],tabs=[...form.querySelectorAll('[data-scope-tab]')],q=document.getElementById('facility-search'),only=document.getElementById('selected-only');
 let active='base',dirty=!!document.querySelector('.notice.error');
 const dialog=document.getElementById('receiver-dialog'),locationInput=document.getElementById('receiver-input');let receiverRow=null,receiverTrigger=null,oldOverflow='';
 function receiverChanged(){dirty=true;document.getElementById('save-status').textContent='변경한 내용을 저장해 주세요.';}
 function openReceiver(row,trigger){receiverRow=row;receiverTrigger=trigger;locationInput.value=row.querySelector('.receiver-value').value;document.getElementById('receiver-scope').textContent=row.closest('.scope-panel').querySelector('.scope-heading [data-dong-label]').textContent;document.getElementById('receiver-error').hidden=true;syncReceiver();oldOverflow=document.documentElement.style.overflow;document.documentElement.style.overflow='hidden';dialog.showModal();}
 function syncReceiver(){dialog.querySelectorAll('[data-receiver-preset]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.receiverPreset===locationInput.value.trim())));}
 if(dialog){
 dialog.querySelectorAll('[data-receiver-preset]').forEach(b=>b.addEventListener('click',()=>{locationInput.value=b.dataset.receiverPreset;syncReceiver();}));
 locationInput.addEventListener('input',syncReceiver);
 ['receiver-close','receiver-later'].forEach(id=>document.getElementById(id).addEventListener('click',()=>dialog.close()));
 dialog.addEventListener('close',()=>{document.documentElement.style.overflow=oldOverflow;receiverTrigger?.focus({preventScroll:true});});
 document.getElementById('receiver-apply').addEventListener('click',()=>{const v=locationInput.value.trim();if(!v){const err=document.getElementById('receiver-error');err.textContent='위치를 선택하거나 직접 입력해 주세요.';err.hidden=false;return;}receiverRow.querySelector('.receiver-value').value=v;receiverChanged();refresh();dialog.close();});
 form.querySelectorAll('.receiver-open').forEach(b=>b.addEventListener('click',()=>openReceiver(b.closest('.item'),b)));
 }
 function refresh(){
  const included=new Set(['base',...[...form.querySelectorAll('.scope-include:checked')].map(e=>e.value)]);
  if(!included.has(active))active='base';let total=0,visible=0;
  tabs.forEach(b=>{b.hidden=!included.has(b.dataset.scopeTab);b.setAttribute('aria-pressed',String(b.dataset.scopeTab===active));});
  panels.forEach(p=>{const on=included.has(p.dataset.scope);p.dataset.included=on?'1':'0';p.hidden=p.dataset.scope!==active;
   const rows=[...p.querySelectorAll('.item')];
   const selected=rows.filter(r=>r.querySelector('input').checked).length;if(on)total+=selected;
   p.querySelector('.print-scope-state').textContent='선택한 시설 '+selected+'종';
   rows.forEach(r=>{const receiver=r.querySelector('.receiver-open');if(receiver){receiver.hidden=!r.querySelector('input[type=checkbox]').checked;receiver.textContent=r.querySelector('.receiver-value').value?'수신기 · '+r.querySelector('.receiver-value').value:'수신기 위치 입력 +';}const type=r.querySelector('.hydrant-choice select');if(type)type.disabled=!r.querySelector('input').checked;r.hidden=!r.dataset.name.toLowerCase().includes(q.value.trim().toLowerCase())||(only.checked&&!r.querySelector('input').checked);if(!p.hidden&&!r.hidden)visible++;});
   p.querySelectorAll('.facility-group').forEach(g=>{const rs=[...g.querySelectorAll('.item')];g.hidden=!rs.some(r=>!r.hidden);g.querySelector('.group-count').textContent=rs.filter(r=>r.querySelector('input').checked).length+' / '+rs.length;});
  });
  document.getElementById('selected-total').textContent=total;document.getElementById('facility-empty').hidden=visible>0;
 }
 tabs.forEach(b=>b.addEventListener('click',()=>{active=b.dataset.scopeTab;refresh();}));
 q.addEventListener('input',refresh);only.addEventListener('change',refresh);
 form.addEventListener('change',e=>{dirty=true;if(e.target.closest('.item'))e.target.closest('.scope-panel').dataset.unrecorded='0';if(e.target.matches('.scope-include')&&e.target.checked)active=e.target.value;document.getElementById('save-status').textContent='변경한 내용을 저장해 주세요.';refresh();if(e.target.matches('.receiver-item input[type=checkbox]')&&e.target.checked)openReceiver(e.target.closest('.item'),e.target);});
 form.addEventListener('submit',e=>{
  if(e.submitter?.value==='reset'&&!confirm('모든 동의 소방시설 현황을 초기화할까요?\n포함에서 제외한 동의 기록과 소방시설 작성 문의·완료 표시도 삭제됩니다.\n동 목록과 포함 선택은 유지됩니다.')){e.preventDefault();return;}
  dirty=false;
 });
 window.buildingInfoPrint=()=>{refresh();window.focus();window.print();};
 window.addEventListener('beforeprint',refresh);
 window.buildingInfoRequestClose=()=>{if(!dirty||confirm('저장하지 않은 변경사항이 있습니다. 닫을까요?'))window.parent.postMessage({type:'building-info-close'},location.origin);};
 window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});refresh();
})();
