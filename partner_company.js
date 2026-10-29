(()=>{
 'use strict';
 if(location.pathname.startsWith('/partner_demo_app.php/'))return;
 const mount=document.querySelector('[data-pci-settings]');let info,dialog,working=false,offered=false,timer;
 const el=(tag,text,cls)=>{const n=document.createElement(tag);if(text)n.textContent=text;if(cls)n.className=cls;return n;};
 async function request(body){const r=await fetch('/partner_company.php',{credentials:'same-origin',cache:'no-store',...(body?{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({...body,csrf:info.csrf})}:{})});const data=await r.json();if(!r.ok||!data.ok)throw Error(data.error||'업체정보를 불러오지 못했습니다.');return data;}
 function paint(){if(!mount)return;mount.replaceChildren();mount.append(el('h2','소속 업체 정보'));const p=info.profile||{};mount.append(el('p',p.mode==='personal'?'개인 활동':p.company||'등록된 업체정보가 없습니다.','hint'));if(p.phone)mount.append(el('p','대표전화 '+p.phone,'hint'));const edit=el('button',p.saved_at?'업체정보 수정':'업체정보 등록','btn');edit.type='button';edit.onclick=()=>open(edit);mount.append(edit);mount.append(el('p','저장한 업체명은 연결된 유저의 소방계획서에서 ‘최신자료 반영하기’를 누를 때 반영됩니다.','hint'));}
 function markDeferred(){try{sessionStorage.setItem('pci-offered:'+info.scope,'1');}catch{}}
 async function later(){if(working)return;working=true;markDeferred();try{info=await request({action:'defer'});}catch{}finally{working=false;dialog.close();}}
 function open(trigger){
  if(dialog?.open)return;
  if(dialog)dialog.remove();dialog=el('dialog',null,'pci-dialog');dialog.setAttribute('aria-labelledby','pci-title');
  dialog.innerHTML='<header><div><small>파트너 정보</small><h2 id="pci-title">소속 업체를 알려주세요</h2></div><button type="button" class="pci-close" aria-label="나중에 입력하고 닫기">×</button></header><form><p class="pci-intro">연결된 유저의 소방계획서에 점검 담당 업체명을 반영할 수 있어요.</p><div class="pci-modes"><label><input type="radio" name="mode" value="company" checked>소속 업체 있음</label><label><input type="radio" name="mode" value="personal">개인 활동</label></div><label class="pci-field">소속 업체명<input name="company" maxlength="80" autocomplete="organization" required placeholder="예: ○○소방"></label><label class="pci-field">업체 대표전화 <small>선택</small><input name="phone" type="tel" maxlength="30" autocomplete="organization tel" placeholder="예: 031-000-0000"></label><p class="pci-note">파트너 이름과 별도로 저장합니다. 기존 계획서는 바뀌지 않으며 ‘최신자료 반영하기’ 때 적용됩니다. 개인 활동을 선택하면 업체명은 자동 입력하지 않습니다.</p><p class="pci-error" role="status"></p><footer><button type="button" class="pci-later">나중에 입력</button><button type="submit" class="pci-save">저장</button></footer></form>';
  const form=dialog.querySelector('form'),p=info.profile||{};form.elements.company.value=p.company||'';form.elements.phone.value=p.phone||'';form.elements.mode.value=p.mode==='personal'?'personal':'company';
  const mode=()=>{const personal=form.elements.mode.value==='personal';form.elements.company.disabled=form.elements.phone.disabled=personal;form.elements.company.required=!personal;dialog.querySelectorAll('.pci-field').forEach(n=>n.hidden=personal);};form.addEventListener('change',mode);mode();
  dialog.querySelector('.pci-close').onclick=dialog.querySelector('.pci-later').onclick=later;dialog.addEventListener('cancel',e=>{e.preventDefault();later();});
  form.onsubmit=async e=>{e.preventDefault();if(working||!form.reportValidity())return;working=true;dialog.querySelector('.pci-save').disabled=true;const error=dialog.querySelector('.pci-error');error.textContent='저장 중…';try{info=await request({action:'save',mode:form.elements.mode.value,company:form.elements.company.value,phone:form.elements.phone.value});paint();dialog.close();}catch(e){error.textContent=e.message;}finally{working=false;dialog.querySelector('.pci-save').disabled=false;}};
  dialog.addEventListener('close',()=>{if(trigger?.isConnected)trigger.focus({preventScroll:true});});document.body.append(dialog);dialog.showModal();dialog.querySelector('.pci-close').focus({preventScroll:true});
 }
 function offer(){
  if(mount||offered||!info?.needs_prompt)return;
  try{if(sessionStorage.getItem('pci-offered:'+info.scope))return;}catch{}
  if(document.hidden||document.querySelector('dialog[open]')||/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement?.tagName||''))return;
  offered=true;markDeferred();open(null);
 }
 request().then(data=>{info=data;paint();timer=setTimeout(offer,1500);if(!mount){new MutationObserver(()=>{clearTimeout(timer);timer=setTimeout(offer,400);}).observe(document.body,{subtree:true,attributes:true,attributeFilter:['open']});window.addEventListener('focus',offer);}}).catch(()=>{if(mount){const retry=el('button','업체정보 다시 불러오기','btn');retry.type='button';retry.onclick=()=>location.reload();mount.append(el('p','업체정보를 불러오지 못했습니다.','hint'),retry);}});
})();
