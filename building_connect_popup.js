(()=>{
 const dialog=document.getElementById('building-connect-guide'),card=document.getElementById('manager-connect');
 if(!dialog||!card||typeof dialog.showModal!=='function')return;
 const anchor=document.createComment('manager connection card');card.before(anchor);
 const body=dialog.querySelector('[data-connect-body]'),close=dialog.querySelector('[data-connect-close]');
 const details=card.querySelector('.mc-mini__details');if(details)details.open=true;
 const local=card.querySelector('.mc-local-choice'),codeForm=card.querySelector('.mc-code-form');
 const codeLabel=card.querySelector('label[for="manager-code-input"]');if(codeLabel)codeLabel.textContent='업체에서 안내받은 파트너 코드를 입력해 주세요.';
 const pending=!!card.querySelector('.mc-status--pending');
 if(pending){dialog.querySelector('[data-connect-description]').textContent='연결 요청을 보냈습니다. 파트너가 수락하면 안내가 자동으로 닫힙니다.';}
 if(!pending&&local&&codeForm){
  const chooser=document.createElement('div');chooser.className='bc-methods';chooser.setAttribute('role','group');chooser.setAttribute('aria-label','이용 중인 업체 유무');
  chooser.innerHTML='<button type="button" id="bc-company-tab" aria-controls="bc-company-panel"><strong>업체가 있어요</strong><span>안내받은 코드로 연결</span></button><button type="button" id="bc-local-tab" aria-controls="bc-local-panel"><strong>업체가 없어요</strong><span>로컬 파트너로 연결</span></button>';
  body.before(chooser);
  const back=document.createElement('button');back.type='button';back.className='bc-back';back.textContent='← 다시 선택';back.hidden=true;body.before(back);
  const title=dialog.querySelector('#building-connect-guide-title'),description=dialog.querySelector('[data-connect-description]'),note=dialog.querySelector('.bc-note');
  function panel(id,label,node){const p=document.createElement('div');p.id=id;p.setAttribute('role','region');p.setAttribute('aria-labelledby',label);node.before(p);p.append(node);return p;}
  const companyPanel=panel('bc-company-panel','bc-company-tab',codeForm),localPanel=panel('bc-local-panel','bc-local-tab',local);
  local.open=true;local.classList.add('bc-local-panel-content');
  const localBox=local.querySelector('.mc-local');
  const promo=localBox.querySelector('p+p');if(promo){const info=document.createElement('details'),summary=document.createElement('summary');info.className='bc-promo';summary.textContent='PRO 프로모션 안내';promo.before(info);info.append(summary,promo);}
  localBox.querySelector('.mc-local__head strong').textContent='로컬 파트너 연결';
  localBox.querySelector('p').textContent='정보 공유에 동의하면 로컬 파트너와 바로 연결됩니다. 연결 후 기본정보에서 건물등록을 시작하세요.';
  const buttons=[...chooser.querySelectorAll('button')];let selected=-1;
  function select(index,focus=false){selected=index;const initial=index<0;chooser.hidden=!initial;body.hidden=initial;back.hidden=initial;if(note)note.hidden=initial;companyPanel.hidden=index!==0;localPanel.hidden=index!==1;
   if(note)note.textContent=index===1?'동의하면 로컬 파트너와 바로 연결됩니다.':'연결 요청 후 업체가 수락하면 연결이 완료됩니다.';
   title.textContent=initial?'이용 중인 업체가 있으신가요?':index===0?'업체를 연결해 주세요':'로컬 파트너로 연결합니다';
   description.textContent=initial?'해당하는 항목을 선택해 주세요.':index===0?'업체에서 안내받은 파트너 코드를 입력해 주세요.':'업체가 없어도 로컬 파트너와 연결해 시작할 수 있습니다.';
   if(focus)buttons[index<0?0:index].focus({preventScroll:true});
  }
  buttons.forEach((button,i)=>button.addEventListener('click',()=>{select(i);back.focus({preventScroll:true});}));
  back.addEventListener('click',()=>{const previous=selected;select(-1);buttons[Math.max(0,previous)].focus({preventScroll:true});});
  const old=codeForm.querySelector('[name="manager_code"]').value,localCode=local.querySelector('[name="manager_code"]').value;
  select(card.querySelector('.mc-notice--error')||old?(old===localCode?1:0):-1);

 }
 let timer=null,controller=null;
 function stop(){clearTimeout(timer);controller?.abort();}
 async function check(){
  if(!dialog.open||document.visibilityState!=='visible')return;
  controller=new AbortController();const timeout=setTimeout(()=>controller.abort(),8000);
  try{const r=await fetch('/building_connect_status.php',{credentials:'same-origin',cache:'no-store',signal:controller.signal});if(r.ok){const d=await r.json();if(d.ok&&d.accepted){dialog.close();location.reload();return;}}}catch{}finally{clearTimeout(timeout);}
  if(dialog.open)timer=setTimeout(check,15000);
 }
 function open(){if(dialog.open)return;body.append(card);dialog.showModal();close.focus({preventScroll:true});check();}
 close.addEventListener('click',()=>dialog.close());
 dialog.addEventListener('close',()=>{stop();anchor.after(card);});
 document.addEventListener('visibilitychange',()=>{stop();if(dialog.open&&document.visibilityState==='visible')check();});
 window.addEventListener('pagehide',stop);
 open();
})();
