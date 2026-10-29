(()=>{'use strict';
 const standalone=matchMedia('(display-mode: standalone)'),installed=()=>standalone.matches||navigator.standalone===true;
 if(window.inAppInstallGuide?.active)return;
 if(installed()||typeof HTMLDialogElement==='undefined'||window.self!==window.top)return;
 const storageKey='firedesk-install-guide-v1';const remembered=()=>{try{return localStorage.getItem(storageKey)==='hidden';}catch{return false;}};
 const ios=/iPhone|iPad|iPod/.test(navigator.userAgent)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1),android=/Android/.test(navigator.userAgent);
 let platform=android?'android':'ios',installPrompt=null;
 window.addEventListener('beforeinstallprompt',e=>{e.preventDefault();installPrompt=e;render();});
 const dialog=document.createElement('dialog');dialog.className='fd-install';dialog.setAttribute('aria-labelledby','fd-install-title');
 dialog.innerHTML='<header><div><span class="fd-install-kicker">홈 화면 추가 · 알림 안내</span><h2 id="fd-install-title">휴대폰에서도 업무를 이어가세요.</h2></div><button type="button" class="fd-install-close" aria-label="홈 화면 추가 안내 닫기">×</button></header><p class="fd-install-intro">홈 화면에 추가하면 <strong>업무 진행상황과 해야 할 일</strong>을 더 간편하게 확인할 수 있습니다.</p><div class="fd-install-tabs" role="tablist" aria-label="휴대폰 종류"><button type="button" id="fd-install-ios" role="tab" aria-controls="fd-install-steps">아이폰</button><button type="button" id="fd-install-android" role="tab" aria-controls="fd-install-steps">안드로이드</button></div><ol id="fd-install-steps" role="tabpanel" class="fd-install-steps"></ol><p class="fd-install-browser"></p><div class="fd-install-notifications"><strong>해야 할 일 알림은 별도로 설정해 주세요.</strong><p>홈 화면 추가만으로 알림이 켜지지는 않습니다. 지원되는 업무에서 ‘알림 받기’를 누르고 알림을 허용해야 합니다.</p><small>건물관리자는 매월 1일 업무수행기록 작성 안내와 12월 1일 차기년도 소방계획서 작성 안내를 받을 수 있습니다. 완료된 업무는 제외합니다.</small></div><button type="button" class="fd-install-notify" style="border:0;background:none;color:#3267bd;font:600 12px system-ui;padding:12px 0;cursor:pointer">업무 알림 설정 →</button><label class="fd-install-dismiss"><input type="checkbox">이 기기에서 다시 보지 않기</label><footer><button type="button" class="fd-install-copy">사이트 주소 복사</button><button type="button" class="fd-install-native" hidden>홈 화면에 추가</button><button type="button" class="fd-install-done">확인했어요</button></footer><p class="fd-install-result" role="status"></p>';
 document.body.append(dialog);
 const q=s=>dialog.querySelector(s),tabs=[q('#fd-install-ios'),q('#fd-install-android')];
 function render(){tabs.forEach((tab,i)=>{const active=(i===0)===(platform==='ios');tab.setAttribute('aria-selected',String(active));tab.tabIndex=active?0:-1;});q('#fd-install-steps').setAttribute('aria-labelledby',platform==='ios'?'fd-install-ios':'fd-install-android');
  const steps=platform==='ios'?['Safari에서 소방계획서.com을 여세요.','공유 버튼(□↑)을 누르세요. 보이지 않으면 … 메뉴에서 공유를 선택하세요.','‘홈 화면에 추가’ → ‘추가’를 누르세요. ‘웹 앱으로 열기’가 보이면 켜 주세요.','추가한 아이콘을 눌러 로그인하고 업무를 확인하세요.']:['Chrome에서 소방계획서.com을 여세요.','오른쪽 위 메뉴(⋮)를 누르세요.','‘홈 화면에 추가’ 또는 ‘앱 설치’를 선택하세요.','추가한 아이콘을 눌러 로그인하고 업무를 확인하세요.'];
  q('ol').replaceChildren(...steps.map(text=>{const li=document.createElement('li');li.textContent=text;return li;}));
  q('.fd-install-browser').textContent=platform==='ios'?'카카오톡 등 앱 안에서 열었다면 주소를 복사해 Safari에서 여세요. 아이폰 웹 푸시는 iOS 16.4 이상에서 홈 화면 웹앱으로 실행해야 합니다.':'카카오톡 등 앱 안에서 열었다면 주소를 복사해 Chrome에서 여세요. 메뉴 이름은 기기와 브라우저에 따라 다를 수 있습니다.';
  q('.fd-install-native').hidden=platform!=='android'||!installPrompt;
 }
 tabs.forEach((tab,i)=>{tab.onclick=()=>{platform=i===0?'ios':'android';render();};tab.onkeydown=e=>{if(!['ArrowLeft','ArrowRight','Home','End'].includes(e.key))return;e.preventDefault();platform=e.key==='Home'?'ios':e.key==='End'?'android':platform==='ios'?'android':'ios';render();tabs[platform==='ios'?0:1].focus();};});
 let trigger=null,shown=false;
 function open(from){if(installed()||dialog.open)return;trigger=from||document.activeElement;render();dialog.showModal();shown=true;q('.fd-install-close').focus({preventScroll:true});}
 function finish(){if(q('.fd-install-dismiss input').checked){try{localStorage.setItem(storageKey,'hidden');}catch{}}dialog.close();}
 q('.fd-install-close').onclick=q('.fd-install-done').onclick=finish;dialog.addEventListener('cancel',()=>{if(q('.fd-install-dismiss input').checked){try{localStorage.setItem(storageKey,'hidden');}catch{}}});
 dialog.addEventListener('close',()=>{if(trigger instanceof HTMLElement&&trigger.isConnected)trigger.focus({preventScroll:true});});
 q('.fd-install-notify').onclick=()=>{dialog.close();if(window.visitPushOpen)window.visitPushOpen(reopen);else window.openProSubscription?.('notifications');};
 q('.fd-install-copy').onclick=async()=>{const url=location.origin+'/FireDesk.php';try{await navigator.clipboard.writeText(url);q('.fd-install-result').textContent='주소를 복사했습니다. 휴대폰 브라우저에서 열어 주세요.';}catch{q('.fd-install-result').textContent='이 주소를 복사해 주세요: '+url;}};
 q('.fd-install-native').onclick=async()=>{const prompt=installPrompt;if(!prompt)return;installPrompt=null;try{await prompt.prompt();await prompt.userChoice;}finally{render();}};
 const reopen=document.createElement('button');reopen.type='button';reopen.className='fd-install-reopen';reopen.textContent='휴대폰 홈 화면 추가 안내';reopen.onclick=()=>open(reopen);
 const target=document.querySelector('main')||document.querySelector('.doc');if(target)target.after(reopen);else document.body.append(reopen);
 const auto=()=>{if(shown||remembered()||installed()||document.visibilityState!=='visible'||document.querySelector('dialog[open]'))return;open();};
 // A prior connection/PRO dialog can block the first attempt; retry when it closes.
 let scheduled=false;
 function scheduleAuto(){if(shown||scheduled)return;scheduled=true;requestAnimationFrame(()=>{scheduled=false;auto();});}
 document.addEventListener('close',scheduleAuto,true);
 document.addEventListener('cancel',scheduleAuto,true);
 const observer=new MutationObserver(records=>{
  if(shown){observer.disconnect();return;}
  if(records.some(r=>r.type==='attributes'||[...r.addedNodes,...r.removedNodes].some(n=>n.nodeType===1&&(n.matches?.('dialog')||n.querySelector?.('dialog')))))scheduleAuto();
 });
 observer.observe(document.body,{subtree:true,childList:true,attributes:true,attributeFilter:['open']});
 window.addEventListener('pageshow',scheduleAuto);

 document.addEventListener('visibilitychange',auto);window.addEventListener('appinstalled',()=>{installPrompt=null;reopen.hidden=true;if(dialog.open)dialog.close();});
 if(standalone.addEventListener)standalone.addEventListener('change',()=>{reopen.hidden=installed();if(installed()&&dialog.open)dialog.close();});
 auto();
})();
