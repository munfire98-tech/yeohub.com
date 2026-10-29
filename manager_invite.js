(()=>{
 'use strict';
 let opener=null,timer=null,token='',csrf='',id='',creating=false,polling=false,sequence=0,oldOverflow='',phase='closed',frame=null;
 let url='',expires=0;
 const requests=new Set(),dialog=document.createElement('dialog');
 dialog.className='iq-dialog';dialog.setAttribute('aria-labelledby','iq-dialog-title');
 dialog.innerHTML='<header><h2 id="iq-dialog-title">유저 연결</h2><button type="button" data-close aria-label="초대 QR 닫기">닫기 ×</button></header><strong data-name></strong><div data-qr></div><p data-status role="status"></p><div class="iq-actions"><button type="button" data-copy hidden>초대 링크 복사</button><button type="button" data-refresh hidden>새 QR 만들기</button><button type="button" data-revoke hidden>초대 취소</button><button type="button" data-retry hidden>연결 다시 확인</button></div><p data-guide>유저의 휴대폰 카메라로 QR을 촬영해 주세요.<br>가입이 완료되면 소방시설 현황이 자동으로 열립니다.</p><section class="iq-facilities" data-facilities hidden><div class="iq-connected-note"><strong data-connected-name></strong><p>가입·건물 연결 완료 · 유저가 카드 등록·구독하는 동안 설치 시설을 체크하고 저장해 주세요.</p></div><p data-loading role="status">소방시설 현황을 여는 중입니다…</p><div class="iq-frame-host" data-frame-host></div></section>';
 document.body.append(dialog);
 const status=dialog.querySelector('[data-status]'),qr=dialog.querySelector('[data-qr]'),closeButton=dialog.querySelector('[data-close]');
 const current=n=>n===sequence&&dialog.open;
 function stop(){clearTimeout(timer);timer=null;for(const request of requests)request.abort();requests.clear();polling=false;}
 async function json(endpoint,options={}){
  const controller=new AbortController();requests.add(controller);const timeout=setTimeout(()=>controller.abort(),12000);
  try{const response=await fetch(endpoint,{credentials:'same-origin',cache:'no-store',...options,signal:controller.signal});const d=await response.json();if(!response.ok||!d.ok)throw new Error(d.error||'초대를 처리하지 못했습니다.');return d;}
  finally{clearTimeout(timeout);requests.delete(controller);}
 }
 const api=(action,values={})=>json('/manager_invite_api.php',{method:'POST',body:new URLSearchParams({action,csrf,...values})});
 function actions(show){dialog.querySelectorAll('.iq-actions button').forEach(b=>b.hidden=!show);dialog.querySelector('[data-retry]').hidden=true;}
 function schedule(){clearTimeout(timer);if(dialog.open&&phase==='qr')timer=setTimeout(poll,5000);}
 function tick(){const left=Math.max(0,Math.ceil(expires-Date.now()/1000));status.textContent='남은 시간 '+Math.floor(left/60)+'분 '+left%60+'초 · 1회 사용';if(!left)qr.replaceChildren();}
 function showFacilities(d){
  if(phase==='facilities')return;
  if(!/^[A-Za-z0-9_-]{1,64}$/.test(d.uid||'')||typeof d.connection_key!=='string'||!d.connection_key)throw new Error('연결된 유저 정보를 확인하지 못했습니다.');
  phase='facilities';stop();qr.replaceChildren();actions(false);dialog.classList.add('iq-dialog--facilities');
  dialog.querySelector('#iq-dialog-title').textContent='소방시설 현황';closeButton.setAttribute('aria-label','소방시설 현황 닫기');
  for(const selector of ['[data-name]','[data-qr]','[data-status]','.iq-actions','[data-guide]'])dialog.querySelector(selector).hidden=true;
  dialog.querySelector('[data-facilities]').hidden=false;
  dialog.querySelector('[data-connected-name]').textContent=(d.building||'연결된 건물')+' · '+d.uid;
  const loading=dialog.querySelector('[data-loading]');loading.hidden=false;
  frame=document.createElement('iframe');frame.name='iq-facilities-'+sequence;frame.title='연결된 유저의 소방시설 현황';const activeFrame=frame;
  frame.addEventListener('load',()=>{try{if(activeFrame.contentWindow.location.href==='about:blank')return;}catch{}if(frame===activeFrame)loading.hidden=true;});
  dialog.querySelector('[data-frame-host]').replaceChildren(frame);
  // Start the existing isolated manager editor via CSRF-protected same-origin POST.
  const form=document.createElement('form');form.method='post';form.action='/manager_view.php';form.target=frame.name;form.hidden=true;
  for(const [name,value] of Object.entries({action:'edit',uid:d.uid,csrf,destination:'facilities',connection_key:d.connection_key})){const input=document.createElement('input');input.type='hidden';input.name=name;input.value=value;form.append(input);}
  document.body.append(form);form.submit();form.remove();closeButton.focus({preventScroll:true});
  document.dispatchEvent(new CustomEvent('manager-invite-connected'));
 }
 function finishStatus(d){
  if(d.connected){showFacilities(d);return true;}
  if(d.used){qr.replaceChildren();actions(false);if(d.pending){status.textContent='가입 정보를 마무리하고 있습니다. 잠시만 기다려 주세요.';return false;}phase='done';status.textContent='가입은 완료되었지만 현재 연결을 확인할 수 없습니다. 건물 목록에서 연결 상태를 확인해 주세요.';dialog.querySelector('[data-retry]').hidden=false;return true;}
  if(d.expired){phase='done';qr.replaceChildren();actions(false);status.textContent='초대가 만료되거나 취소되었습니다.';dialog.querySelector('[data-refresh]').hidden=false;return true;}
  return false;
 }
 async function create(){
  if(creating||phase==='facilities')return;
  creating=true;const generation=++sequence,requestedId=id;stop();phase='qr';token='';url='';actions(false);qr.replaceChildren();status.textContent='초대 QR을 만들고 있습니다…';
  try{
   const auth=await json('/manager_invite_api.php');if(!current(generation))return;csrf=auth.csrf;
   const d=await api('create',{id:requestedId});if(!current(generation))return;
   token=d.token;url=d.url;expires=d.expires;dialog.querySelector('[data-name]').textContent=d.building;
   const svg=new DOMParser().parseFromString(d.svg,'image/svg+xml').documentElement;if(svg.nodeName!=='svg')throw new Error('QR을 표시하지 못했습니다.');
   qr.replaceChildren(document.importNode(svg,true));actions(true);tick();schedule();
  }catch(e){if(current(generation)){phase='done';status.textContent=e.name==='AbortError'?'응답이 늦습니다. 새 QR 만들기를 눌러 다시 시도해 주세요.':e.message;dialog.querySelector('[data-refresh]').hidden=false;}}
  finally{if(current(generation))creating=false;}
 }
 async function poll(){
  if(!dialog.open||phase!=='qr'||creating||polling||!token)return;
  if(document.hidden){schedule();return;}
  const generation=sequence,requestedToken=token;polling=true;tick();
  try{const d=await api('status',{token:requestedToken});if(!current(generation)||phase!=='qr')return;finishStatus(d);}
  catch(e){if(current(generation)&&phase==='qr')status.textContent='연결 상태를 확인하지 못했습니다. 다시 확인 중…';}
  finally{if(current(generation)){polling=false;schedule();}}
 }
 function close(){
  if(phase==='facilities'&&frame){try{if(typeof frame.contentWindow.buildingInfoRequestClose==='function'){frame.contentWindow.buildingInfoRequestClose();return;}}catch{}}
  dialog.close();
 }
 document.addEventListener('click',e=>{
  const b=e.target.closest('[data-invite-draft]');if(!b||dialog.open)return;
  opener=b;id=b.dataset.inviteDraft;phase='qr';dialog.classList.remove('iq-dialog--facilities');dialog.querySelector('#iq-dialog-title').textContent='유저 연결';closeButton.setAttribute('aria-label','초대 QR 닫기');
  for(const selector of ['[data-name]','[data-qr]','[data-status]','.iq-actions','[data-guide]'])dialog.querySelector(selector).hidden=false;
  dialog.querySelector('[data-name]').textContent='';dialog.querySelector('[data-facilities]').hidden=true;oldOverflow=document.body.style.overflow;document.body.style.overflow='hidden';dialog.showModal();closeButton.focus({preventScroll:true});create();
 });
 closeButton.addEventListener('click',close);dialog.addEventListener('cancel',e=>{e.preventDefault();close();});
 dialog.addEventListener('close',()=>{sequence++;creating=false;phase='closed';stop();document.body.style.overflow=oldOverflow;frame?.remove();frame=null;dialog.querySelector('[data-frame-host]').replaceChildren();if(opener?.isConnected)opener.focus({preventScroll:true});});
 window.addEventListener('message',e=>{if(dialog.open&&phase==='facilities'&&e.origin===location.origin&&e.source===frame?.contentWindow&&e.data?.type==='building-info-close')dialog.close();});
 document.addEventListener('visibilitychange',()=>{if(!document.hidden&&dialog.open&&phase==='qr'){clearTimeout(timer);poll();}});
 dialog.querySelector('[data-refresh]').onclick=create;
 dialog.querySelector('[data-retry]').onclick=()=>{phase='qr';dialog.querySelector('[data-retry]').hidden=true;poll();};
 dialog.querySelector('[data-copy]').onclick=async()=>{const generation=sequence;try{await navigator.clipboard.writeText(url);if(current(generation)&&phase==='qr')status.textContent='초대 링크를 복사했습니다.';}catch{if(current(generation)&&phase==='qr')status.textContent='링크를 복사할 수 없습니다. QR을 촬영해 주세요.';}};
 dialog.querySelector('[data-revoke]').onclick=async()=>{const generation=++sequence;stop();actions(false);try{const d=await api('revoke',{token});if(!current(generation))return;if(!finishStatus(d))schedule();}catch(e){if(current(generation)){status.textContent='취소 결과를 확인하지 못했습니다. 연결 상태를 다시 확인해 주세요.';dialog.querySelector('[data-retry]').hidden=false;}}};
})();
