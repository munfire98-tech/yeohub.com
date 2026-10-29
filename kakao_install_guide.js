(()=>{'use strict';
 const ua=navigator.userAgent,ios=/iPhone|iPad|iPod/i.test(ua)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1),android=/Android/i.test(ua);
 if(!/KAKAOTALK/i.test(ua)||(!ios&&!android)||window.self!==window.top||matchMedia('(display-mode: standalone)').matches||navigator.standalone===true||typeof HTMLDialogElement==='undefined')return;
 const el=(tag,text,cls)=>{const n=document.createElement(tag);if(text)n.textContent=text;if(cls)n.className=cls;return n;};
 // Copy only a public entry point, never OAuth codes, invitation tokens or session parameters.
 const path=/\/(?:partner_center|FireDesk|firedesk)\.php$/i.test(location.pathname)?location.pathname:'/';const url=location.origin+path;
 const banner=el('aside',undefined,'kig-banner');banner.setAttribute('aria-label','카카오톡 홈 화면 추가 안내');
 const copy=el('div');copy.append(el('strong','홈 화면에 추가하고 알림을 받으세요'),el('small','카카오톡에서는 외부 브라우저로 먼저 열어주세요.'));
 const launch=el('button','홈 화면 추가','kig-open');launch.type='button';launch.setAttribute('aria-haspopup','dialog');const dismiss=el('button','×','kig-dismiss');dismiss.type='button';dismiss.setAttribute('aria-label','홈 화면 추가 안내 배너 닫기');dismiss.onclick=()=>banner.hidden=true;banner.append(copy,launch,dismiss);document.body.append(banner);
 const dialog=el('dialog',undefined,'kig-dialog');dialog.setAttribute('aria-labelledby','kig-title');
 dialog.innerHTML='<header><div><small>카카오톡에서 접속하셨네요</small><h2 id="kig-title">홈 화면에 추가하기</h2></div><button type="button" class="kig-close" aria-label="홈 화면 추가 안내 닫기">×</button></header><p class="kig-intro"></p><ol class="kig-steps"></ol><p class="kig-help">카카오톡 메뉴에 외부 브라우저로 여는 항목이 보이지 않으면, 아래에서 주소를 복사한 뒤 브라우저 주소창에 붙여 넣으세요. 메뉴 위치와 이름은 앱 버전에 따라 다를 수 있습니다.</p><div class="kig-notify"><strong>홈 화면 추가 후 알림도 켜 주세요</strong><p>추가된 아이콘으로 접속해 로그인한 뒤, 알림 설정에서 ‘알림 받기’를 누르세요. 홈 화면 추가만으로 알림이 자동으로 켜지지는 않습니다.</p><small>브라우저를 바꾸면 다시 로그인이 필요할 수 있습니다.</small></div><footer><button type="button" class="kig-copy">사이트 주소 복사</button><button type="button" class="kig-done">확인했어요</button></footer><p class="kig-result" role="status"></p><input class="kig-url" aria-label="복사할 사이트 주소" readonly hidden>';
 document.body.append(dialog);const q=s=>dialog.querySelector(s);q('.kig-url').value=url;
 q('.kig-intro').textContent=ios?'Safari에서 열어 홈 화면에 추가할 수 있습니다.':'Chrome에서 열어 홈 화면에 추가하거나 앱을 설치할 수 있습니다.';
 const steps=ios?['카카오톡 화면의 메뉴(⋯)에서 ‘다른 브라우저로 열기’ 등 외부 브라우저로 여는 항목을 찾아보세요.','Safari에서 사이트를 여세요. 다른 브라우저가 열리면 주소를 복사해 Safari에 붙여 넣으세요.','Safari의 공유(□↑) → ‘홈 화면에 추가’ → ‘추가’를 누르세요. ‘웹 앱으로 열기’가 있으면 켜 주세요.']:['카카오톡 화면의 메뉴(⋯)에서 ‘다른 브라우저로 열기’ 등 외부 브라우저로 여는 항목을 찾아보세요.','Chrome에서 사이트를 여세요. 다른 브라우저가 열리면 주소를 복사해 Chrome에 붙여 넣으세요.','Chrome 메뉴(⋮) → ‘홈 화면에 추가’ 또는 ‘앱 설치’를 선택하세요.'];
 q('.kig-steps').append(...steps.map(text=>el('li',text)));let trigger=null;
 function open(from){if(dialog.open)return;trigger=from||document.activeElement;dialog.showModal();q('.kig-close').focus({preventScroll:true});}
 launch.onclick=()=>open(launch);q('.kig-close').onclick=q('.kig-done').onclick=()=>dialog.close();dialog.addEventListener('close',()=>{if(trigger?.isConnected)trigger.focus({preventScroll:true});});
 q('.kig-copy').onclick=async()=>{try{await navigator.clipboard.writeText(url);q('.kig-result').textContent='주소를 복사했습니다. '+(ios?'Safari':'Chrome')+'의 주소창에 붙여 넣으세요.';}catch{q('.kig-url').hidden=false;q('.kig-result').textContent='아래 주소를 길게 눌러 복사해 주세요.';}};
 window.inAppInstallGuide={active:true,open};
})();
