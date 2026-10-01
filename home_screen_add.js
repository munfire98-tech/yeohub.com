(()=>{
 let installPrompt=null;
 const standalone=matchMedia('(display-mode: standalone)');
 const installed=()=>standalone.matches||navigator.standalone===true;
 let button;
 const refresh=()=>{if(button)button.hidden=installed();};
 window.addEventListener('beforeinstallprompt',event=>{event.preventDefault();installPrompt=event;refresh();});
 window.addEventListener('appinstalled',()=>{installPrompt=null;if(button)button.hidden=true;});
 if(standalone.addEventListener)standalone.addEventListener('change',refresh);
 function setup(){
  button=document.getElementById('home-screen-add');if(!button)return;refresh();
  const dialog=document.createElement('dialog');dialog.className='home-add-dialog';dialog.setAttribute('aria-labelledby','home-add-title');
  dialog.innerHTML='<header><div><small>소방계획서.com</small><h2 id="home-add-title">홈 화면에 추가하기</h2></div><button type="button" class="home-add-close" aria-label="안내 닫기">×</button></header><p class="home-add-intro">아이콘을 눌러 더 빠르게 접속하세요.</p><ol class="home-add-steps"></ol><p class="home-add-note"></p><div class="home-add-actions"><button type="button" class="home-add-copy">주소 복사</button><button type="button" class="home-add-done">확인했어요</button></div><p class="home-add-status" role="status"></p>';
  document.body.append(dialog);
  let previousOverflow='';
  function guide(){
   const ios=/iPhone|iPad|iPod/.test(navigator.userAgent)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1);
   const android=/Android/.test(navigator.userAgent);
   const steps=ios?['Safari에서 이 사이트를 열어주세요.','공유 버튼(□↑)을 누르세요. 보이지 않으면 … 메뉴에서 공유를 선택하세요.','홈 화면에 추가 → 추가를 누르세요.']:android?['Chrome에서 이 사이트를 열어주세요.','우측 상단 메뉴(⋮)를 누르세요.','홈 화면에 추가 또는 앱 설치를 선택하세요.']:['휴대폰의 Safari 또는 Chrome에서 이 사이트를 열어주세요.','브라우저 메뉴에서 홈 화면에 추가를 선택하세요.'];
   const list=dialog.querySelector('ol');list.replaceChildren();steps.forEach(text=>{const li=document.createElement('li');li.textContent=text;list.append(li);});
   dialog.querySelector('.home-add-note').textContent=ios?'항목이 보이지 않으면 공유 메뉴를 아래로 내려 확인하세요. 카카오톡 등 앱 안에서 열었다면 주소를 복사해 Safari에서 열어주세요.':'메뉴 이름은 기기와 브라우저마다 다를 수 있어요. 카카오톡 등 앱 안에서 열었다면 주소를 복사해 Chrome에서 열어주세요.';
   dialog.querySelector('.home-add-status').textContent='';previousOverflow=document.body.style.overflow;dialog.showModal();document.body.style.overflow='hidden';dialog.querySelector('.home-add-close').focus({preventScroll:true});
  }
  dialog.querySelector('.home-add-close').onclick=()=>dialog.close();dialog.querySelector('.home-add-done').onclick=()=>dialog.close();
  dialog.addEventListener('close',()=>{document.body.style.overflow=previousOverflow;button.focus({preventScroll:true});});
  dialog.querySelector('.home-add-copy').onclick=async()=>{const status=dialog.querySelector('.home-add-status');const url=location.origin+'/';try{await navigator.clipboard.writeText(url);status.textContent='사이트 주소를 복사했어요.';}catch{status.textContent='이 주소를 길게 눌러 복사해 주세요: '+url;}};
  button.addEventListener('click',async()=>{
   if(installed())return;
   if(!installPrompt){guide();return;}
   const pending=installPrompt;installPrompt=null;button.disabled=true;
   try{await pending.prompt();const result=await pending.userChoice;if(result.outcome==='accepted')button.hidden=true;}catch{guide();}finally{button.disabled=false;}
  });
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',setup,{once:true});else setup();
})();
