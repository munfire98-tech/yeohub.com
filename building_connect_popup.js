(()=>{
 const dialog=document.getElementById('building-connect-guide'),card=document.getElementById('manager-connect');
 if(!dialog||!card||typeof dialog.showModal!=='function')return;
 const anchor=document.createComment('manager connection card');card.before(anchor);
 const body=dialog.querySelector('[data-connect-body]'),close=dialog.querySelector('[data-connect-close]');
 const details=card.querySelector('.mc-mini__details');if(details)details.open=true;
 const local=card.querySelector('.mc-local-choice');if(local){local.open=true;local.querySelector('summary').textContent='담당 매니저가 없으신가요? 로컬 매니저를 선택해 주세요.';}
 const codeLabel=card.querySelector('label[for="manager-code-input"]');if(codeLabel)codeLabel.textContent='담당 매니저가 있다면 매니저 코드를 입력해 주세요.';
 const pending=!!card.querySelector('.mc-status--pending');
 if(pending){dialog.querySelector('[data-connect-description]').textContent='연결 요청을 보냈습니다. 매니저가 수락하면 안내가 자동으로 닫힙니다.';}
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
