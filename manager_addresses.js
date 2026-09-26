(()=>{
 const form=document.getElementById('ma-link-form');if(!form)return;
 const note=document.getElementById('ma-selection-note'),submit=document.getElementById('ma-connect');
 form.addEventListener('change',()=>{const selected=form.querySelector('[name="address_id"]:checked');if(!selected)return;submit.disabled=false;submit.textContent=selected.value?'사전정보로 교체하고 연결':'사전 등록 없이 연결';note.textContent=selected.value?'유저의 기존 기본정보를 선택한 사전등록 내용으로 교체합니다.':'사전 정보를 가져오지 않고 유저와 연결합니다.';});
 document.getElementById('ma-filter').addEventListener('input',e=>{let count=0;form.querySelectorAll('[data-choice-search]').forEach(el=>{el.hidden=!el.dataset.choiceSearch.toLowerCase().includes(e.target.value.trim().toLowerCase());if(!el.hidden)count++;});const empty=document.getElementById('ma-match-empty');empty.hidden=count>0;empty.textContent='일치하는 사전 등록 거래처가 없습니다.';});
 form.addEventListener('submit',()=>{submit.disabled=true;submit.textContent='연결 중…';});
})();
(()=>{
 const root=document.querySelector('.ma-page');if(!root||!document.getElementById('ma-data'))return;
 const status=document.getElementById('ma-status'),results=document.getElementById('ma-results'),saved=document.getElementById('ma-saved');
 let rows=JSON.parse(document.getElementById('ma-data').textContent),busy=false;
 const node=(tag,text,cls)=>{const el=document.createElement(tag);if(text!==undefined)el.textContent=text;if(cls)el.className=cls;return el;};
 const button=(text,fn,cls='')=>{const b=node('button',text,cls);b.type='button';b.addEventListener('click',fn);return b;};
 function changed(){if(window.parent!==window)window.parent.postMessage({type:'manager-addresses-changed'},location.origin);}
 async function api(act,fields={}){if(busy)return null;busy=true;root.querySelectorAll('button').forEach(b=>b.disabled=true);status.textContent=act==='lookup'?'건축물대장을 조회하고 있습니다…':'처리 중…';
  try{const response=await fetch('/manager_addresses.php',{method:'POST',credentials:'same-origin',cache:'no-store',body:new URLSearchParams({act,csrf:root.dataset.csrf,...fields})});const data=await response.json();if(!response.ok||!data.ok)throw Error(data.error||'처리하지 못했습니다.');status.textContent='';return data;}
  catch(e){status.textContent=e.message;return null;}finally{busy=false;root.querySelectorAll('button').forEach(b=>b.disabled=false);}
 }
 function render(){saved.replaceChildren();document.getElementById('ma-count').textContent=rows.length+'개';if(!rows.length)saved.append(node('p','아직 사전 등록한 거래처가 없습니다.','ma-note'));
  const query=(document.getElementById('ma-saved-search').value||'').trim().toLowerCase();const visible=rows.filter(r=>(r.name+' '+r.address).toLowerCase().includes(query));if(rows.length&&!visible.length)saved.append(node('p','검색 결과가 없습니다.','ma-note'));for(const r of visible){const card=node('article',undefined,'ma-address'),copy=node('div');copy.append(node('strong',r.name||r.address||'작성 중인 거래처'));if(r.name)copy.append(node('p',r.address));copy.append(node('small',r.linked?'연결 완료':r.address?'연결 대기':'주소 작성 필요'));const actions=node('div',undefined,'ma-actions');if(!r.linked)actions.append(button('기본정보 확인',()=>{location.href='/manager_draft_view.php?id='+encodeURIComponent(r.id);},'ma-secondary'));else copy.append(node('p','기본정보 수정은 담당 유저 화면에서 진행해 주세요.'));
   if(!r.linked)actions.append(button('삭제',async()=>{if(!confirm('사전 등록 주소를 삭제할까요?'))return;const d=await api('delete',{id:r.id});if(d){rows=rows.filter(x=>x.id!==r.id);render();changed();}},'ma-quiet'));
   card.append(copy,actions);saved.append(card);
  }
 }
 document.getElementById('ma-new').addEventListener('click',async()=>{const d=await api('new');if(d){location.href='/manager_draft.php?id='+encodeURIComponent(d.id)+'&modal=1';}});
 document.getElementById('ma-saved-search').addEventListener('input',render);
 render();
 if(root.dataset.create==='1'){history.replaceState(null,'','/manager_addresses.php');document.getElementById('ma-new').click();}
 if(root.dataset.selected&&rows.some(r=>r.id===root.dataset.selected&&!r.linked))location.replace('/manager_draft_view.php?id='+encodeURIComponent(root.dataset.selected));
})();
