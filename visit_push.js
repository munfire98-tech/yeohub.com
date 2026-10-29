(()=>{
 'use strict';
 const building=document.currentScript?.dataset.building==='1';
 const types=building?{building_monthly:'매월 1일 · 업무수행기록 작성',building_annual:'12월 1일 · 차기년도 소방계획서'}:{visit:'방문 예정일',report:'보고서 접수 마감일',work:'공사 완료기한',filing:'이행완료보고 제출 마감일',connection:'가입·구독·연결 변경',todo:'마감일이 있는 할 일'};
 let dialog,info,reg,trigger,working=false,permissionStatus;
 const isIOS=()=>/iPhone|iPad|iPod/.test(navigator.userAgent)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1);
 const installed=()=>matchMedia('(display-mode: standalone)').matches||navigator.standalone===true;
 const $=s=>dialog.querySelector(s);
 async function request(body){const response=await fetch('/visit_push_api.php',{credentials:'same-origin',cache:'no-store',...(body?{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({...body,csrf:info.csrf})}:{})});const data=await response.json();if(!response.ok||!data.ok){const e=new Error(data.error||'연결 상태를 확인해 주세요.');e.status=response.status;throw e;}return data;}
 function supported(){return window.isSecureContext&&'serviceWorker' in navigator&&'PushManager' in window&&'Notification' in window;}
 function preferenceDirty(){return !!info&&(Object.keys(types).some(key=>$('[name='+key+']').checked!==info.preferences[key])||['am','pm'].some(part=>$('[name=time_'+part+']').value!==(info.times?.[part]||(part==='am'?'08:15':'17:00'))));}
 function renderDevices(){
  const list=$('.vp-device-list');list.replaceChildren();
  for(const device of info?.devices||[]){
   const card=document.createElement('div');card.className='vp-device-row';
   const copy=document.createElement('div'),title=document.createElement('strong'),detail=document.createElement('small');
   title.textContent=device.label+(device.current?' · 현재 기기':'');
   detail.textContent=(device.status==='active'?'알림 연결됨':'로그아웃 · 일시중지')+(device.updated?' · '+new Intl.DateTimeFormat('ko-KR',{timeZone:'Asia/Seoul',month:'numeric',day:'numeric'}).format(new Date(device.updated*1000))+' 연결 확인':'');
   copy.append(title,detail);const remove=document.createElement('button');remove.type='button';remove.textContent='연결 해제';remove.disabled=working;
   remove.setAttribute('aria-label',title.textContent+' 알림 연결 해제');
   remove.onclick=()=>{if(!confirm(device.label+'의 알림 연결을 해제할까요? 이 기기에서 다시 알림 받기를 눌러야 연결됩니다.'))return;action(async()=>{await request({act:'device_remove',id:device.id});if(device.current)await disconnectBrowser();$('.vp-result').textContent='기기의 알림 연결을 해제했습니다.';});};
   card.append(copy,remove);list.append(card);
  }
  if(info&&!info.devices?.length)list.textContent='연결된 알림 기기가 없습니다.';
 }
 async function disconnectBrowser(){const r=await navigator.serviceWorker?.getRegistration('/');await r?.pushManager.getSubscription().then(s=>s?.unsubscribe());r?.active?.postMessage({type:'VISIT_PUSH_CLEAR'});}
 function state(){
  const blocked=typeof Notification!=='undefined'&&Notification.permission==='denied',iosInstall=isIOS()&&!installed();
  $('.vp-status').textContent=!window.isSecureContext?'HTTPS 연결이 필요합니다.':iosInstall?'홈 화면에 추가한 웹앱에서 알림을 켜 주세요.':!supported()?'웹 푸시 미지원 환경입니다. 최신 Safari 또는 Chrome에서 열어 주세요.':blocked?'이 화면을 연 브라우저에서 알림이 차단되어 있습니다.':info?.enabled&&Notification.permission==='granted'?'이 기기에서 알림을 받고 있습니다.':'이 기기의 알림이 꺼져 있습니다.';
  $('.vp-enable').disabled=working||!info?.ready||!supported()||blocked||iosInstall;
  $('.vp-disable').disabled=working||!info?.enabled;$('.vp-test').disabled=working||!info?.enabled||typeof Notification==='undefined'||Notification.permission!=='granted';$('.vp-save').disabled=working||!info?.enabled||!preferenceDirty();
  for(const key of Object.keys(types)){const input=$('[name='+key+']');input.disabled=working||!info;input.closest('label').classList.toggle('is-selected',input.checked);input.closest('label').querySelector('.vp-type-state').textContent=input.checked?'켜짐':'꺼짐';}
  $('.vp-enabled-devices').textContent=info?'이 계정에 연결된 기기 '+info.device_count+'대 · 수신 종류는 이 기기 · 수신 시간은 계정의 모든 기기에 적용됩니다.':'';
  for(const part of ['am','pm'])$('[name=time_'+part+']').disabled=working||!info;
  for(const part of ['am','pm']){
   const row=info?.timing?.[part],target=$('[data-time-status='+part+']');
   if(!row){target.textContent='';continue;}
   const sent=row.sent_at?new Intl.DateTimeFormat('ko-KR',{timeZone:'Asia/Seoul',hour:'2-digit',minute:'2-digit',hour12:false}).format(new Date(row.sent_at*1000)):'';
   target.textContent=(sent?'오늘 '+sent+' 푸시 서비스 전달 완료. ':row.empty?'오늘은 알림 대상 업무가 없었습니다. ':row.processed?'오늘 발송 시각을 처리했습니다. ':'')+'다음 예정: '+row.next.replace(/^(\d{4})-/,'').replace('-','/')+' (한국시간)';
  }
  renderDevices();
  $('.vp-blocked').hidden=!blocked;$('.vp-setup').hidden=!info||info.ready;
 }
 function recheckPermission(manual=false){
  if(!dialog?.open||working)return;
  state();
  if(manual){
   const permission=typeof Notification==='undefined'?'unsupported':Notification.permission;
   $('.vp-result').textContent=permission==='denied'?'아직 이 브라우저의 사이트 알림이 차단되어 있습니다. 휴대폰 앱 알림과 Chrome의 사이트 알림을 모두 허용한 뒤 다시 확인해 주세요.':permission==='granted'?'브라우저 알림 권한이 허용되었습니다. 알림 받기 또는 테스트 알림을 눌러 주세요.':'알림 받기를 눌러 권한을 허용해 주세요.';
  }
 }
 async function watchPermission(){
  if(permissionStatus||!navigator.permissions?.query)return;
  try{permissionStatus=await navigator.permissions.query({name:'notifications'});permissionStatus.addEventListener('change',()=>recheckPermission());}catch(_){/* Visibility/focus checks cover browsers without the Permissions API. */}
 }
 window.addEventListener('focus',()=>recheckPermission());
 window.addEventListener('pageshow',()=>recheckPermission());
 document.addEventListener('visibilitychange',()=>{if(!document.hidden)recheckPermission();});
 async function refresh(){info=await request();for(const [key] of Object.entries(types))$('[name='+key+']').checked=info.preferences[key]===true;for(const part of ['am','pm'])$('[name=time_'+part+']').value=info.times?.[part]||(part==='am'?'08:15':'17:00');state();}
 async function sw(){reg=await navigator.serviceWorker.register('/visit_push_sw.js',{scope:'/'});await navigator.serviceWorker.ready;return reg;}
 function keyBytes(key){return Uint8Array.from(atob(key.replace(/-/g,'+').replace(/_/g,'/')),c=>c.charCodeAt(0));}
 function bounded(p,ms,message){let timer;return Promise.race([p,new Promise((_,reject)=>{timer=setTimeout(()=>reject(Error(message)),ms);})]).finally(()=>clearTimeout(timer));}
 async function action(run){if(working)return;working=true;$('.vp-result').textContent='처리 중…';state();try{await run();await refresh();}catch(e){$('.vp-result').textContent=e.message;}finally{working=false;state();}}
 function times(){return Object.fromEntries(['am','pm'].map(part=>[part,$('[name=time_'+part+']').value]));}
 function preferences(){return Object.fromEntries(Object.keys(types).map(k=>[k,$('[name='+k+']').checked]));}
 function create(){
  dialog=document.createElement('dialog');dialog.className='vp-dialog';dialog.setAttribute('aria-labelledby','vp-title');
  dialog.innerHTML='<header><div><small>파트너센터 · 업무모드</small><h2 id="vp-title">알림 설정</h2></div><button type="button" class="vp-close" aria-label="알림 설정 닫기">×</button></header><div class="vp-body"><p class="vp-status" role="status"></p><p class="vp-enabled-devices"></p><details class="vp-devices"><summary>연결된 기기 확인 · 관리</summary><div class="vp-device-list"></div><p>기기 정보는 브라우저 기준으로 표시됩니다. 같은 휴대폰의 브라우저와 홈 화면 앱은 별도로 연결될 수 있습니다. 로그아웃 중에는 발송하지 않으며, 같은 계정으로 재로그인하면 기존 설정으로 복구합니다.</p></details><p class="vp-setup" hidden>서버 발송키가 준비되지 않았습니다. 관리자에게 문의해 주세요.</p><div class="vp-actions"><button type="button" class="vp-enable">알림 받기</button><button type="button" class="vp-disable">알림 끄기</button><button type="button" class="vp-test">가상 일정 테스트</button></div><div class="vp-blocked" hidden><p class="vp-blocked-text"></p><button type="button" class="vp-recheck">권한 다시 확인</button></div><fieldset><legend>받을 알림 · 이 기기</legend><div class="vp-types"></div></fieldset><fieldset class="vp-times"><legend>수신 시간 · 계정 공통</legend><label><span>오늘 업무<small>미완료 · 3일 내 마감 포함</small></span><input type="time" name="time_am" aria-label="오늘 업무 수신 시간" min="00:00" max="11:59" value="08:15" required></label><p class="vp-time-status" data-time-status="am"></p><label><span>내일 업무<small>금요일에는 주말 · 다음 주 요약</small></span><input type="time" name="time_pm" aria-label="내일 업무 수신 시간" min="12:00" max="23:59" value="17:00" required></label><p class="vp-time-status" data-time-status="pm"></p><p>한국시간 기준 · 기본 오전 8:15 / 오후 5:00<br>가입·구독·연결 알림은 시간 설정과 관계없이 즉시 발송합니다.<br>오늘 이미 보낸 요약은 시간을 바꿔도 다시 보내지 않습니다.<br>지난 시간으로 변경하면 다음 날부터 적용됩니다.</p><button type="button" class="vp-save">알림 설정 저장</button></fieldset><details class="vp-install"><summary>홈 화면에 추가하는 방법</summary><p class="vp-install-text"></p></details><p class="vp-note">사이트를 닫아도 알림을 받을 수 있습니다. 기기의 네트워크·집중 모드·절전 설정에 따라 수신이 늦어질 수 있습니다. 방문일정과 가입·구독·연결 변경 알림에 적용됩니다.</p><p class="vp-result" role="status" aria-live="polite"></p></div>';
  for(const [key,title] of Object.entries(types)){
   const label=document.createElement('label'),input=document.createElement('input'),text=document.createElement('span'),value=document.createElement('span');
   input.type='checkbox';input.name=key;input.id='vp-type-'+key;input.checked=true;label.htmlFor=input.id;text.className='vp-type-name';text.textContent=title;value.className='vp-type-state';value.setAttribute('aria-hidden','true');
   label.append(input,text,value);$('.vp-types').append(label);
   input.addEventListener('change',()=>{state();$('.vp-result').textContent=preferenceDirty()?(info?.enabled?'변경한 알림 설정을 저장해 주세요.':'알림 받기를 누르면 선택한 종류로 연결됩니다.'):'선택한 수신 종류가 저장 상태와 같습니다.';});
  }
  for(const part of ['am','pm'])$('[name=time_'+part+']').addEventListener('input',()=>{state();$('.vp-result').textContent='변경한 알림 설정을 저장해 주세요.';});
  if(building){dialog.classList.add('vp-building');dialog.querySelector('header small').textContent='FireDesk · 건물관리';$('.vp-test').textContent='업무 알림 테스트';$('.vp-times legend').textContent='수신 시간 · 한국시간';const am=$('[name=time_am]').closest('label');am.querySelector('input').setAttribute('aria-label','건물 업무 알림 수신 시간');am.querySelector('span').innerHTML='업무 알림 시간<small>매월 1일 · 12월에는 차기년도 계획서도 함께 안내</small>';$('[name=time_pm]').closest('label').hidden=true;$('.vp-times>p:not([data-time-status])').innerHTML='기본 오전 8:15 · 완료된 업무는 발송 전에 제외합니다.<br>같은 날 알림은 한 건으로 묶습니다.';$('.vp-note').textContent='사이트를 닫아도 알림을 받을 수 있습니다. 휴대폰의 알림·집중 모드·네트워크 상태에 따라 수신이 늦어질 수 있습니다. 같은 기기에서 다시 로그인하면 연결이 복구됩니다.';}
  document.body.append(dialog);$('.vp-install-text').textContent=isIOS()?'iOS 16.4 이상에서 Safari로 열기 → 공유(□↑ 또는 … 안의 공유) → 홈 화면에 추가. 추가된 아이콘으로 실행한 뒤 알림 받기를 누르세요.':'Android Chrome에서 메뉴(⋮) → 홈 화면에 추가 또는 앱 설치. 추가된 아이콘을 열고 알림 받기를 누르세요.';
  $('.vp-blocked-text').textContent=isIOS()?'설정 → 알림 → 홈 화면 웹앱에서 알림을 허용하세요. 설정에서 돌아오면 상태를 다시 확인합니다.':'① 휴대폰 설정 → 앱 → Chrome(또는 설치한 웹앱) → 알림 허용 ② Chrome에서 이 사이트 열기 → 주소창 왼쪽 사이트 정보 → 권한 → 알림 허용. 홈 화면 앱과 Chrome의 권한이 다를 수 있습니다. 설정에서 돌아오면 상태를 다시 확인합니다.';
  $('.vp-recheck').onclick=()=>recheckPermission(true);
  $('.vp-close').onclick=()=>{if(!working)dialog.close();};dialog.addEventListener('cancel',e=>{if(working)e.preventDefault();});dialog.addEventListener('close',()=>trigger?.focus({preventScroll:true}));
  $('.vp-enable').onclick=()=>{
   if(!['am','pm'].every(part=>$('[name=time_'+part+']').reportValidity()))return;
   // Permission request stays directly in the trusted tap handler (iOS requirement).
   const permission=Notification.requestPermission();action(async()=>{if(await bounded(permission,30000,'알림 권한 창에서 허용 여부를 선택한 뒤 다시 눌러 주세요.')!=='granted')throw Error('알림이 허용되지 않았습니다. 브라우저 설정에서 확인해 주세요.');const r=await bounded(sw(),15000,'웹앱 준비가 지연됩니다. 화면을 다시 열어 주세요.');let subscription=await r.pushManager.getSubscription();if(!subscription)subscription=await bounded(r.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:keyBytes(info.publicKey)}),20000,'기기 푸시 서비스에 연결하지 못했습니다. Safari 또는 Chrome의 홈 화면 웹앱에서 다시 시도해 주세요.');try{await request({act:'subscribe',subscription:subscription.toJSON(),preferences:preferences(),times:times(),standalone:installed()});}catch(e){if(e.status!==409)throw e;await subscription.unsubscribe();subscription=await bounded(r.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:keyBytes(info.publicKey)}),20000,'기기 푸시 서비스에 연결하지 못했습니다. Safari 또는 Chrome의 홈 화면 웹앱에서 다시 시도해 주세요.');await request({act:'subscribe',subscription:subscription.toJSON(),preferences:preferences(),times:times(),standalone:installed()});}$('.vp-result').textContent='알림 받기를 켰습니다. 테스트 알림으로 확인해 주세요.';});
  };
  $('.vp-disable').onclick=()=>action(async()=>{await request({act:'disable'});await disconnectBrowser();$('.vp-result').textContent='이 기기의 알림을 껐습니다.';});
  $('.vp-test').onclick=()=>action(async()=>{await request({act:'test'});$('.vp-result').textContent='가상 방문일정 알림을 발송했습니다. 휴대폰에 도착한 알림을 눌러 일정 상세로 이동하는지 확인해 주세요.';});
  $('.vp-save').onclick=()=>{if(!['am','pm'].every(part=>$('[name=time_'+part+']').reportValidity()))return;action(async()=>{await request({act:'preferences',preferences:preferences(),times:times(),standalone:installed()});$('.vp-result').textContent=building?'알림 설정을 저장했습니다. 매월 1일 선택한 시간에 안내합니다.':'알림 설정을 저장했습니다. 아래 다음 예정 시각을 확인해 주세요.';});};
 }
 window.visitPushOpen=async button=>{
  if(location.pathname.startsWith('/partner_demo_app.php/')){alert('예시 체험에서는 실제 기기 알림을 등록하지 않습니다. 파트너 센터에서 이용해 주세요.');return;}
  if(!dialog)create();watchPermission();trigger=button;dialog.showModal();info=null;state();$('.vp-result').textContent='';try{await refresh();if(info.enabled){await request({act:'device_label',standalone:installed()});await refresh();}if(supported()&&(!isIOS()||installed()))await sw();if(isIOS()&&!installed())$('.vp-install').open=true;}catch(e){$('.vp-status').textContent=e.message;}
 };
})();
