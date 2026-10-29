/* Drives real demo controls; all writes stay behind partner_demo_app.php. */
(() => {
 'use strict';
 if(!location.pathname.startsWith('/partner_demo_app.php/'))return;
 const params=new URLSearchParams(location.search),auto=params.get('autoplay')==='1';
 let complete=false,paused=false,stopped=false,synthetic=false,phase=0,phaseDetails={};
 const parent=window.parent;
 const nonce=crypto.randomUUID();let clockMs=0,rate=1,seekTarget=null,initialized=false,resolveReady;
 const ready=new Promise(resolve=>{resolveReady=resolve;});
 const send=(data)=>parent.postMessage({type:'partner-tour-state',nonce,time:clockMs,...data},location.origin);
 const progress=extra=>parent.postMessage({type:'partner-tour-progress',nonce,time:clockMs,...extra},location.origin);
 const setSeeking=()=>document.documentElement.classList.toggle('pwt-seeking',seekTarget!==null);
 const validRate=n=>config.rates.includes(Number(n))?Number(n):1;
 const config=window.partnerTourConfig,labels=config.labels;
 const timing=key=>config.timings[key];
 function state(n,extra){if(n!==phase)phaseDetails={};phase=n;if(extra)phaseDetails=extra;send({step:n===11?4:n===12?5:n>=6?n:n>=4?n-2:n,title:labels[n][0],detail:labels[n][1],...phaseDetails,paused,auto});}
 const cursor=document.createElement('div');cursor.className='pwt-cursor';cursor.hidden=true;cursor.setAttribute('aria-hidden','true');cursor.innerHTML='<svg viewBox="0 0 30 36"><path d="M3 2v27l7-6 6 11 6-3-6-11 10-1Z" fill="#2865c5" stroke="white" stroke-width="2"/></svg>';document.body.append(cursor);
 // Shorten presentation delays only; readiness timeouts keep their full budget.
 async function wait(ms,paced=true){
  let left=paced?Math.round(ms*.8):ms;
  while(left>0){
   if(stopped)throw Error('stopped');
   if(paced&&seekTarget!==null){
    const skip=Math.min(left,Math.max(0,seekTarget-clockMs));clockMs+=skip;left-=skip;
    if(clockMs>=seekTarget){seekTarget=null;paused=true;setSeeking();progress({seeked:true,paused:true});state(phase);while(paused&&!stopped)await new Promise(r=>setTimeout(r,40));if(stopped)throw Error('stopped');}
    else {progress();return;}
   }
   if(paused||document.hidden){await new Promise(r=>setTimeout(r,40));continue;}
   if(!left)break;
   await new Promise(r=>setTimeout(r,40));
   if(paused||document.hidden)continue;
   const advance=Math.min(left,paced?40*rate:40);left-=advance;
   if(paced){clockMs+=advance;progress();}
  }
 }
 async function until(fn){let elapsed=0;while(elapsed<20000){await wait(100,false);const value=fn();if(value)return value;elapsed+=100;}throw Error('화면 준비가 늦어 자동 안내를 멈췄어요. 처음부터 다시 보거나 직접 이어서 작성할 수 있어요.');}
 function byText(text,root=document){if(!root)return null;return [...root.querySelectorAll('button,a')].find(el=>el.textContent.trim().startsWith(text)&&!el.disabled&&el.getClientRects().length);}
 async function point(el){(el.closest('dialog')||document.body).append(cursor);el.scrollIntoView({block:'nearest',behavior:'instant'});await wait(timing('cursorApproach'));const r=el.getBoundingClientRect();cursor.hidden=false;cursor.style.left=r.left+r.width*.5+'px';cursor.style.top=r.top+r.height*.5+'px';if(!el.hasAttribute('data-map-point'))el.classList.add('pwt-target');await wait(timing('cursorHover'));}
 async function click(el){const closing=el.matches('.mv-close,.mvc-close,.mvc-day-close');if(closing)el.classList.add('pwt-close-focus');await point(el);await wait(closing?900:timing('clickHold'));synthetic=true;cursor.classList.add('is-clicking');if(closing)await wait(240);try{el.click();}finally{synthetic=false;}await wait(timing('clickRelease'));cursor.classList.remove('is-clicking');el.classList.remove('pwt-target','pwt-close-focus');}
 async function type(el,value){await point(el);el.focus({preventScroll:true});el.value='';for(const letter of value){await wait(timing('typeLetter'));el.value+=letter;}synthetic=true;el.dispatchEvent(new Event('input',{bubbles:true}));synthetic=false;el.classList.remove('pwt-target');await wait(timing('typeDone'));}
 const field=()=>typeof STEPS!=='undefined'?STEPS[step]?.field:null;
 async function at(name){return until(()=>field()===name&&document.getElementById('ansBox'));}
 async function draft(){
  // Loaded after the real form. Only the illustrative driver uses these helpers.
  state(1);
  const input=await until(()=>document.querySelector('#ansBox input'));
  await type(input,'소방계획서.com 빌딩');
  await click(await until(()=>document.querySelector('[data-tour-result]')));
  await click(await until(()=>document.querySelector('.dn-primary')));
  await until(()=>document.querySelector('[data-tour-registry-continue]:not(:disabled)'));state(1,{title:'건축물대장에서 찾았습니다',detail:'주소·용도·층수·면적·구조·사용승인일을 자동으로 채우는 과정이에요. 체험용 예시 자료입니다.'});await wait(timing('registryIntro'));cursor.hidden=true;document.querySelector('[data-tour-floor-table]').scrollIntoView({block:'nearest',behavior:'smooth'});await wait(timing('floorTable'));await click(document.querySelector('[data-tour-registry-continue]'));
  await at('grade');state(4,{title:'건물 규모로 등급을 먼저 확인해요',detail:'지상 5층 · 연면적 2,400㎡. 이 예시는 설비를 추가 확인해 등급을 안내합니다.'});await wait(timing('equipmentOrScan'));await click(byText('🤔 잘 모르겠어요',document.getElementById('ansBox')));
  await until(()=>byText('있습니다',document.getElementById('ansBox')));state(4,{title:'설치된 소방설비도 확인해요',detail:'체험 건물에는 옥내소화전이 있는 것으로 설정했어요.'});await wait(timing('readDetail'));await click(byText('있습니다',document.getElementById('ansBox')));
  await until(()=>byText('계산된 2급 선택'));state(4,{title:'자동 계산 결과 · 2급',detail:'규모와 설비 답변으로 2급을 안내합니다. 실제 등록할 때는 선임신고 필증의 등급을 확인해 주세요.'});await wait(timing('gradeResult'));await click(byText('계산된 2급 선택'));
  const manager=await at('__mgr');state(4,{title:'소방안전관리자를 입력해요',detail:'홍길동 · 010-0000-0000을 저장하면 대표자 입력에도 활용할 수 있어요.'});await type(manager.querySelectorAll('input')[0],'홍길동');await type(manager.querySelectorAll('input')[1],'010-0000-0000');await click(byText('다음',manager));
  await at('rep');state(5);await wait(timing('representative'));await click(byText('관리자 홍길동 선택'));
  const saved=await until(()=>document.querySelector('.doneRow a'));await wait(timing('savedOrReturn'));await click(saved);
 }
 // Keep manual practice useful and allow pausing an auto run before interacting.
 window.addEventListener('message',e=>{
  if(e.origin!==location.origin||e.source!==parent||e.data?.type!=='partner-tour-control')return;
  const data=e.data;
  if(data.action==='initialize'&&data.nonce===nonce&&!initialized){
   initialized=true;clockMs=Number.isFinite(data.time)?data.time:0;rate=validRate(data.rate);
   seekTarget=Number.isFinite(data.seekTarget)?data.seekTarget:null;paused=seekTarget!==null?false:!!data.paused;setSeeking();resolveReady();state(phase);return;
  }
  if(!initialized)return;
  if(data.action==='pause')paused=true;
  if(data.action==='resume')paused=false;
  if(data.action==='rate')rate=validRate(data.rate);
  state(phase);
 });
 parent.postMessage({type:'partner-tour-ready',nonce},location.origin);
 // Manual standalone practice has no surrounding playback controller.
 if(!auto){initialized=true;resolveReady();}
 // Navigation hides the old document; it must not pause the next scene.

 // Watch-only during playback: trusted input cannot change the scripted form.
 ['pointerdown','pointerup','click','dblclick','keydown','beforeinput','submit'].forEach(name=>document.addEventListener(name,e=>{if(e.isTrusted&&auto&&!complete){e.preventDefault();e.stopImmediatePropagation();}},true));
 window.addEventListener('pagehide',()=>{stopped=true;});
 function fail(e){if(stopped)return;paused=true;cursor.hidden=true;document.querySelectorAll('.pwt-target').forEach(el=>el.classList.remove('pwt-target'));state(phase,{error:true,detail:e.message});}
 if(document.querySelector('[data-tour-result-page]')){
  const list=document.querySelector('[data-tour-list]'),mapCard=document.querySelector('[data-tour-map-card]'),qr=document.querySelector('[data-tour-qr]'),phone=document.querySelector('[data-tour-phone]');
  let sceneLayoutFrame=0,buildingView='overview';
  function queueConnectionLayout(){
   cancelAnimationFrame(sceneLayoutFrame);
   sceneLayoutFrame=requestAnimationFrame(fitConnectionScene);
  }
  function fitConnectionScene(){
   const active=!qr.hidden?qr:!phone.hidden?phone:null;
   if(!active)return;
   active.style.zoom='1';
   if(active===phone&&phone.dataset.scene==='home'){
    // One fixed viewport owns iframe sizing. Never scale the real building UI.
    const box=phone.querySelector('.pwt-live-building');
    const frame=box.querySelector('iframe');
    const height=Math.floor(box.getBoundingClientRect().height);
    if(height>0)frame.style.height=height+'px';
    if(buildingView==='work')alignBuildingWork();
   }else{
    const bounds=active.getBoundingClientRect();
    const scale=Math.min(1,(window.innerHeight-16)/bounds.height,(window.innerWidth-16)/bounds.width);
    active.style.zoom=String(Math.max(.1,scale));
   }
   window.scrollTo(0,0);
  }
  new MutationObserver(queueConnectionLayout).observe(phone,{attributes:true,attributeFilter:['hidden','data-scene'],subtree:true});
  new MutationObserver(queueConnectionLayout).observe(qr,{attributes:true,attributeFilter:['hidden']});
  window.addEventListener('resize',queueConnectionLayout);
  document.fonts.ready.then(queueConnectionLayout);
  const mapRoot=document.querySelector('[data-tour-mini-map]'),popup=mapRoot.querySelector('.pwt-map-pop');
  popup.remove();popup.classList.remove('pwt-map-pop');popup.classList.add('pwt-map-popup-content');
  const center=new kakao.maps.LatLng(Number(mapRoot.dataset.lat),Number(mapRoot.dataset.lng));let demoMap,building,subscribed=false;
  document.querySelector('[data-tour-map-open]').onclick=()=>{
   list.hidden=true;mapCard.hidden=false;state(6,{title:'저장한 건물이 지도에 표시돼요',detail:'사전등록한 건물이 지도에 생겼어요. 요약과 방문일정도 살펴볼게요.'});
   if(!demoMap){
    demoMap=new kakao.maps.Map(mapRoot,{center,practice:false});
    const button=document.createElement('button');button.type='button';button.className='pwt-building-marker';button.dataset.tourBuildingMarker='1';button.setAttribute('aria-label','소방계획서.com 빌딩 선택');button.innerHTML='<b>P</b><span>소방계획서.com 빌딩</span>';L.DomEvent.disableClickPropagation(button);
    building=L.marker([center.lat,center.lng],{icon:L.divIcon({className:'pwt-building-icon',html:button,iconSize:null,iconAnchor:[18,18]}),keyboard:false}).addTo(demoMap.map);
    building.bindPopup(popup,{maxWidth:230,minWidth:180,autoPan:false,offset:[0,-18]});
    button.onclick=()=>{const map=demoMap.map,zoom=map.getZoom(),position=map.project(building.getLatLng(),zoom).subtract(L.point(0,map.getSize().y*.22));map.setView(map.unproject(position,zoom),zoom,{animate:false});button.classList.add('is-selected');building.openPopup();if(!subscribed)state(6,{title:'건물에서 유저 연결 QR을 열어요',detail:'파트너 코드나 건물을 유저가 다시 찾을 필요가 없어요.'});};
   }
   requestAnimationFrame(()=>demoMap.map.invalidateSize({pan:false}));
   if(!auto)document.querySelector('[data-tour-building-marker]').click();
  };
  popup.querySelector('[data-tour-qr-open]').onclick=()=>{mapCard.hidden=true;qr.hidden=false;state(6,{title:'파트너가 QR을 보여줘요',detail:'초대 링크를 카카오톡이나 문자 메시지로 전달할 수도 있어요.'});if(!auto)wait(timing('manualScan')).then(startScan).catch(fail);};
  function scene(name,n,title,detail){qr.hidden=true;phone.hidden=false;phone.dataset.scene=name;phone.querySelectorAll('[data-phone-step]').forEach(el=>el.hidden=el.dataset.phoneStep!==name);phone.querySelector('[data-phone-title]').textContent=title;phone.querySelector('[data-phone-description]').textContent=detail;state(n,{title,detail});window.scrollTo({top:0,behavior:'instant'});}
  document.querySelector('[data-tour-copy]').onclick=()=>{document.querySelector('[data-tour-copy]').textContent='✓ 복사 완료';const hint=document.querySelector('[data-tour-copy-note]');hint.textContent='카카오톡·문자에 붙여넣어 보내세요';hint.classList.add('is-copy-highlight');requestAnimationFrame(fitConnectionScene);};
  const buildingFrame=document.querySelector('[data-phone-building]');
  let buildingReady=false;
  buildingFrame.addEventListener('load',()=>{
   try{
    const doc=buildingFrame.contentDocument;
    buildingReady=!!doc.querySelector('.building-board');
    if(buildingReady){
     // Keep the actual responsive cards untouched; the tour controls scrolling.
     const style=doc.createElement('style');
     style.textContent='html,body{overflow-anchor:none;scroll-behavior:auto!important;scrollbar-width:none}::-webkit-scrollbar{display:none}';
     doc.head.append(style);
     buildingFrame.parentElement.classList.add('is-ready');
     resetBuildingScroll();queueConnectionLayout();
    }
   }catch(e){buildingReady=false;}
  });
  function resetBuildingScroll(){
   buildingView='overview';
   try{buildingFrame.contentWindow.scrollTo(0,0);}catch(e){}
  }
  function alignBuildingWork(){
   try{
    const win=buildingFrame.contentWindow,target=buildingFrame.contentDocument.querySelector('.compact-deck__head');
    if(target)win.scrollTo(0,Math.max(0,win.scrollY+target.getBoundingClientRect().top-14));
   }catch(e){}
  }
  async function revealBuildingWork(){
   const win=buildingFrame.contentWindow,doc=buildingFrame.contentDocument;
   const target=doc.querySelector('.compact-deck__head');
   if(!target)return;
   const from=win.scrollY,to=Math.max(0,from+target.getBoundingClientRect().top-14);
   // Scroll the real page as a person would, without asking viewers to scroll the player.
   for(let i=1;i<=12;i++){
    await wait(45);
    const t=i/12,ease=t*t*(3-2*t);
    win.scrollTo(0,Math.round(from+(to-from)*ease));
   }
   buildingView='work';
  }
  function prepareBuilding(){
   fitConnectionScene();
   if(!buildingFrame.getAttribute('src'))buildingFrame.src=buildingFrame.dataset.src;
  }
  async function showUserBuilding(){
   cursor.hidden=true;
   phone.querySelector('.pwt-device-label').textContent='유저 폰 · 내 건물';
   scene('home',9,'내 건물 연결 완료','구독한 건물의 업무를 바로 시작할 수 있어요.');
   // Load only after the device has a real visible viewport, including on iOS.
   prepareBuilding();await until(()=>buildingReady);
   resetBuildingScroll();
   await wait(timing('userBuilding')*.46);
   state(9,{title:'기본정보에서 안전 위치를 먼저 설정해요',detail:'건물 정보는 연결됐어요. 소방차 진입로와 집결지를 확인하고 업무를 시작해요.'});
   await revealBuildingWork();await wait(timing('userBuilding')*.46);
   state(10,{title:'이제 파트너의 휴대폰으로 돌아갑니다',detail:'유저의 구독 완료가 파트너 지도에도 반영돼요.'});phone.classList.add('is-switching-device');await wait(timing('deviceSwitch'));finishPhone();
  }
  function startScan(){cursor.hidden=true;scene('scan',6,'유저 폰으로 파트너의 QR을 찍어요','파트너가 보여주는 QR을 유저의 휴대폰 카메라로 촬영해요.');}
  document.querySelector('[data-phone-scan]').onclick=()=>scene('signup',7,'유저가 아이디를 만들어요','가입하면 사전등록 건물과 파트너가 자동 연결돼요.');
  document.querySelector('[data-phone-signup]').onclick=async e=>{
   const button=e.currentTarget;button.disabled=true;
   try{const response=await fetch(location.href,{method:'POST',body:new URLSearchParams({act:'connect',csrf:document.body.dataset.csrf})});const data=await response.json();if(!response.ok||!data.ok)throw Error(data.error||'연결 예시를 준비하지 못했습니다. 다시 눌러 주세요.');scene('card',8,'건물 연결 완료 · 카드를 등록해요','가입·카드등록 화면을 간단히 재현한 체험입니다.');}
   catch(error){fail(error);button.disabled=false;}
  };
  document.querySelector('[data-phone-card-save]').onclick=()=>scene('subscribe',9,'등록한 카드로 구독을 시작해요','카드등록 뒤 구독 결제를 완료하는 과정입니다.');
  document.querySelector('[data-phone-subscribe]').onclick=()=>{scene('success',9,'구독 완료','내 건물관리 화면으로 연결됩니다.');if(!auto)wait(timing('transition')).then(showUserBuilding).catch(fail);};
  function finishPhone(){
   subscribed=true;phone.hidden=true;mapCard.hidden=false;mapCard.classList.add('is-partner-phone');document.querySelector('[data-tour-finished]').hidden=false;
   const marker=document.querySelector('[data-tour-building-marker]');marker.classList.add('is-pro');marker.querySelector('b').textContent='S';marker.setAttribute('aria-label','소방계획서.com 빌딩 PRO 이용');
   document.querySelector('[data-tour-map-status]').textContent='S · PRO 이용 1';
   popup.querySelector('.pwt-tag').textContent='S · PRO 이용 중 · 체험';popup.querySelector('[data-tour-qr-open]').hidden=true;popup.querySelector('[data-tour-open-building]').hidden=false;
   building.closePopup();demoMap.map.invalidateSize({pan:false});demoMap.map.setView([center.lat,center.lng],demoMap.map.getZoom(),{animate:false});
   if(!auto)complete=true;window.scrollTo({top:0,behavior:'instant'});cursor.hidden=true;state(10,{title:'지도에서도 S · PRO 이용으로 바뀌었어요',detail:'파트너는 유저의 업무 상황을 확인하고 작성을 도와줄 수 있어요.'});
  };
  async function showPartnerBuilding(){
   mapCard.hidden=true;document.querySelector('[data-tour-finished]').hidden=true;
   phone.classList.remove('is-switching-device');phone.querySelector('.pwt-device-label').textContent='파트너 폰 · 유저 화면 확인';
   scene('home',10,'파트너도 유저의 건물 화면을 확인해요','같은 건물관리 화면에서 작성 현황을 확인하고 필요한 업무를 도와줄 수 있어요.');
   fitConnectionScene();resetBuildingScroll();
   cursor.hidden=true;await wait(1800);await revealBuildingWork();await wait(1400);
   complete=true;state(10,{finished:true,title:'유저의 업무를 함께 도와주세요',detail:'파트너 지도에서 S 건물을 누르면 유저의 건물관리 화면을 확인할 수 있어요.'});
  }
  popup.querySelector('[data-tour-open-building]').onclick=()=>showPartnerBuilding().catch(fail);
  document.querySelector('[data-tour-building]').onclick=()=>showPartnerBuilding().catch(fail);
  async function workspaceFeatures(){
   state(11,{title:'사전등록하면 이런 기능을 사용할 수 있어요',detail:'요약과 방문일정으로 거래처를 관리해요. 가상 거래처 40곳으로 보여드릴게요.'});
   const examples=window.partnerWalkthroughBuildings;document.querySelector('[data-tour-map-status]').textContent='가상 거래처 40곳';
   const sceneMarkers=[{marker:building,kind:'P',row:examples[0]}];
   examples.slice(1).forEach(r=>{const kind=r.preregistered?'P':r.subscription.active?'S':'C';const pin=document.createElement('div');pin.innerHTML='<span class="pwt-example-pin '+kind+'">'+kind+'</span>';const label=document.createElement('span');label.className='pwt-pin-name';label.textContent=r.name;pin.append(label);const marker=L.marker([r.lat,r.lng],{interactive:false,icon:L.divIcon({className:'pwt-extra-marker',html:pin,iconSize:[28,28]})}).addTo(demoMap.map);sceneMarkers.push({marker,kind,row:r});});
   demoMap.map.fitBounds(examples.map(r=>[r.lat,r.lng]),{padding:[30,40]});
   const nameToggle=document.createElement('button');nameToggle.type='button';nameToggle.className='pwt-map-aa';nameToggle.textContent='Aa';nameToggle.setAttribute('aria-label','건물 이름 표시');nameToggle.setAttribute('aria-pressed','false');mapRoot.append(nameToggle);L.DomEvent.disableClickPropagation(nameToggle);
   nameToggle.onclick=()=>{const on=nameToggle.getAttribute('aria-pressed')!=='true';nameToggle.setAttribute('aria-pressed',String(on));mapRoot.classList.toggle('pwt-show-names',on);};
   const filters=document.createElement('div');filters.className='pwt-map-filters';filters.setAttribute('aria-label','건물 상태 필터');mapRoot.append(filters);L.DomEvent.disableClickPropagation(filters);
   for(const [kind,title] of [['ALL','전체'],['P','사전등록'],['C','파트너 연결'],['S','PRO 이용']]){
    const button=document.createElement('button');button.type='button';button.textContent=title;button.dataset.kind=kind;button.setAttribute('aria-pressed',String(kind==='ALL'));filters.append(button);
    button.onclick=()=>{const visible=sceneMarkers.filter(item=>kind==='ALL'||item.kind===kind);sceneMarkers.forEach(item=>{if(visible.includes(item))item.marker.addTo(demoMap.map);else demoMap.map.removeLayer(item.marker);});filters.querySelectorAll('button').forEach(el=>el.setAttribute('aria-pressed',String(el===button)));demoMap.map.invalidateSize({pan:false});demoMap.map.fitBounds(visible.map(item=>item.marker.getLatLng()),{padding:[40,65],animate:false});};
   }
   state(11,{title:'Aa로 지도 표시를 바꿔요',detail:'마커와 건물 이름 표시를 전환해 거래처를 쉽게 찾을 수 있어요.'});
   await click(nameToggle);cursor.hidden=true;await wait(timing('readDetail'));await click(nameToggle);
   for(const [kind,title] of [['C','파트너 연결 건물만 모아봐요'],['S','PRO 구독 건물만 모아봐요'],['P','사전등록 건물만 확인해요'],['ALL','전체 거래처로 돌아와요']]){
    state(11,{title,detail:'지도 위 상태 카드를 누르면 해당 건물만 표시되고, 위치에 맞춰 지도가 이동해요.'});await click(filters.querySelector('[data-kind="'+kind+'"]'));cursor.hidden=true;await wait(timing('readDetail'));
   }
   state(11,{title:'사전등록하면 요약을 볼 수 있어요',detail:'전체 거래처·면적·관리지역·방문거리를 한곳에서 확인해요.'});
   await wait(timing('mapOverview'));await click(await until(()=>document.querySelector('.mv-open')));cursor.hidden=true;
   function focusSummary(selector){
    const panel=document.querySelector('.mv-dialog'),target=panel.querySelector(selector);
    panel.classList.add('pwt-scene-panel');
    panel.querySelectorAll('.pwt-scene-visible,.pwt-scene-item').forEach(el=>el.classList.remove('pwt-scene-visible','pwt-scene-item'));
    let root=target;while(root.parentElement!==panel)root=root.parentElement;
    root.classList.add('pwt-scene-visible');target.classList.add('pwt-scene-item');
    if(selector==='.mv-stats')panel.querySelector('.mv-distribution')?.classList.add('pwt-scene-visible');
    if(selector==='.mv-profile-grid')target.querySelectorAll('.mv-profile-section').forEach(el=>el.classList.add('pwt-scene-item'));
    if(selector==='.mv-metric'){
     panel.querySelector('.mv-section-title')?.classList.add('pwt-scene-visible');
     panel.querySelector('.mv-section-title')?.nextElementSibling?.classList.add('pwt-scene-visible');
     target.nextElementSibling?.classList.add('pwt-scene-visible');
     panel.querySelector('svg')?.classList.add('pwt-scene-visible');
    }
    panel.scrollTop=0;return target;
   }
   async function showSummary(selector,title,detail){
    state(11,{title,detail});cursor.hidden=true;
    await until(()=>document.querySelector(selector));const target=focusSummary(selector);
    target.classList.add('pwt-reading');await wait(timing('summaryDetail'));target.classList.remove('pwt-reading');
   }
   await showSummary('.mv-stats','전체 거래처와 등록 연면적을 한눈에','등록된 거래처 수, 동별 합산 연면적, 평균 면적과 가장 큰 거래처를 확인해요.');
   const distanceText=document.querySelector('.mv-metric strong').textContent;
   await showSummary('.mv-metric','월 1회 방문 · '+distanceText,'예시 거래처를 한 번씩 방문하는 직선거리 기준 추정치예요. 실제 도로 주행거리와는 달라요.');
   await showSummary('.mv-profile-grid','관리지역과 건물 용도를 함께 확인해요','지역별 거래처 수와 용도 구성을 볼 수 있어요. 용도를 누르면 해당 거래처 목록이 열립니다.');
   const factoryButton=[...document.querySelectorAll('.mv-use-button')].find(el=>el.textContent.includes('공장'));
   state(11,{title:'공장을 누르면 등록된 공장이 나와요',detail:'선택한 용도의 거래처 이름·주소·면적을 한 번에 확인해요.'});
   await click(factoryButton);focusSummary('#mv-use-buildings');cursor.hidden=true;
   await wait(100,false);document.querySelector('.mv-dialog').scrollTop=0;
   const factoryRows=[...document.querySelectorAll('.mv-use-list>li')];
   for(let start=0;start<factoryRows.length;start+=4){factoryRows.forEach((el,i)=>{el.hidden=i<start||i>=start+4;});state(11,{title:'공장 거래처 목록 · '+(start+1)+'–'+Math.min(start+4,factoryRows.length)+' / '+factoryRows.length+'곳',detail:'용도별 거래처를 모아서 확인할 수 있어요.'});await wait(timing('summaryDetail'));}

   state(11,{title:'큰 건물부터 연면적 순서로',detail:'가장 큰 거래처 카드의 연면적 순서 보기를 누르면 전체 순위를 볼 수 있어요.'});
   focusSummary('.mv-stats');await click(document.querySelector('.mv-rank-toggle'));focusSummary('.mv-ranking');cursor.hidden=true;await wait(timing('areaRanking'));
   await showSummary('.mv-ranking','전체 거래처 연면적 순서','동별 합산 연면적 기준으로 큰 면적순·작은 면적순 정렬이 가능해요.');
   state(11,{title:'확인했으면 오른쪽 위에서 닫아요',detail:'× 버튼을 누르면 등록된 거래처 지도로 돌아갑니다.'});
   await click(document.querySelector('.mv-close'));await wait(timing('dialogGap'));
   state(12);await click(document.querySelector('.mvc-open'));await click(await until(()=>document.querySelector('.mvc-guide-start')));await wait(timing('calendarReady'));
   const viewButton=await until(()=>document.querySelector('.mvc-view-toggle'));
   if(viewButton.getAttribute('aria-pressed')==='true')await click(viewButton);
   state(12,{title:'사용승인월과 6개월 주기를 표시해요',detail:'보라색은 사용승인월, 청록색은 6개월 주기입니다. 예시에는 각각 5곳이 있어요.'});
   cursor.hidden=true;await wait(timing('readDetail'));
   await click(await until(()=>document.querySelector('.mvc-approval-summary:not(:disabled)')));await wait(650,false);
   state(12,{title:'이달에 해당하는 건물만 모아봐요',detail:'모아 보기를 누르면 사용승인월·6개월 주기 건물에 맞춰 지도가 이동합니다.'});
   cursor.hidden=true;await wait(timing('summaryDetail'));
   state(12,{title:'지도는 확대해서 볼 수 있어요',detail:'실제 지도처럼 + / − 버튼으로 확대·축소할 수 있어요.'});
   await click(await until(()=>document.querySelector('.mvc-plan-map .leaflet-control-zoom-in')));await wait(650,false);cursor.hidden=true;await wait(timing('transition'));
   await click(document.querySelector('.mvc-plan-map .leaflet-control-zoom-out'));await wait(650,false);await wait(timing('calendarReady'));
   state(12,{title:'방문일정에서도 Aa로 전환해요',detail:'건물 이름으로 찾거나, 마커로 간결하게 볼 수 있어요.'});
   await click(document.querySelector('.mvc-view-toggle'));cursor.hidden=true;await wait(timing('readDetail'));await click(document.querySelector('.mvc-view-toggle'));
   await click(document.querySelector('.mvc-approval-summary'));await click(document.querySelector('.mvc-fit'));await wait(timing('calendarReady'));
   const planner=document.querySelector('.mvc-planner');
   if(document.querySelector('.mvc-view-toggle').getAttribute('aria-pressed')==='true')await click(document.querySelector('.mvc-view-toggle'));
   state(12,{title:'건물이 구분되도록 가까이 확대해요',detail:'사전등록 건물 위치를 확대해서 방문할 거래처를 골라요.'});
   planner.dispatchEvent(new CustomEvent('pwt-visit-camera'));await wait(650,false);cursor.hidden=true;await wait(timing('readDetail'));
   const desktop=window.innerWidth>=651;
   if(desktop){
    state(12,{title:'지도를 살짝 옮겨 위치를 확인해요',detail:'마우스로 지도를 잡아 이동한 뒤 거래처를 선택합니다.'});
    const mapEl=document.querySelector('.mvc-plan-map'),r=mapEl.getBoundingClientRect();planner.append(cursor);cursor.hidden=false;cursor.style.left=(r.left+r.width*.6)+'px';cursor.style.top=(r.top+r.height*.6)+'px';await wait(700);cursor.classList.add('is-clicking');
    for(let n=0;n<8;n++){planner.dispatchEvent(new CustomEvent('pwt-visit-camera',{detail:{pan:[-3,0]}}));cursor.style.left=(r.left+r.width*.6+(n+1)*3)+'px';await wait(100);}
    cursor.classList.remove('is-clicking');await wait(500);
   }
   state(12,{title:'방문할 사전등록 거래처를 선택해요',detail:'확대한 지도에서 건물을 선택하면 달력에 등록할 수 있어요.'});
   for(let i=0;i<3;i++){await click(await until(()=>document.querySelectorAll('[data-tour-visit-target]')[i]));}
   const dateCell=()=>[...document.querySelectorAll('.mvc-cell')].find(el=>el.querySelector('.mvc-date-line b')?.textContent==='15');
   if(desktop){
    state(12,{title:'선택한 거래처를 달력으로 끌어 놓아요',detail:'끌기 버튼을 잡고 15일에 놓으면 방문일정이 등록됩니다.'});
    const handle=document.querySelector('.mvc-drag-handle'),cell=dateCell();await point(handle);
    const dataTransfer=new DataTransfer();handle.dispatchEvent(new DragEvent('dragstart',{bubbles:true,cancelable:true,dataTransfer}));
    const badge=document.createElement('div');badge.className='pwt-carried-visits';badge.textContent='선택한 거래처 3곳';cursor.append(badge);cursor.classList.add('is-clicking');
    const from=handle.getBoundingClientRect(),to=cell.getBoundingClientRect();
    for(let n=1;n<=16;n++){const t=n/16;cursor.style.left=(from.left+from.width/2+(to.left+to.width/2-from.left-from.width/2)*t)+'px';cursor.style.top=(from.top+from.height/2+(to.top+to.height/2-from.top-from.height/2)*t)+'px';await wait(100);}
    cell.dispatchEvent(new DragEvent('dragover',{bubbles:true,cancelable:true,dataTransfer}));await wait(600);cell.dispatchEvent(new DragEvent('drop',{bubbles:true,cancelable:true,dataTransfer}));handle.dispatchEvent(new DragEvent('dragend',{bubbles:true,dataTransfer}));badge.remove();cursor.classList.remove('is-clicking');cursor.hidden=true;
    await until(()=>document.querySelector('.mvc-toast')?.textContent.includes('등록 완료'));await wait(timing('visitSaved'));
   }else{
    const mobileNext=document.querySelector('.mvc-mobile-footer button');if(mobileNext?.getClientRects().length)await click(mobileNext);
    state(12,{title:'날짜를 눌러 방문일정을 등록해요',detail:'원하는 날짜를 선택하고 방문 등록을 누르면 됩니다.'});await click(await until(dateCell));await click(await until(()=>document.querySelector('.mvc-batch-save:not(:disabled)')));
    await until(()=>document.querySelector('.mvc-record'));await wait(timing('visitSaved'));await click(document.querySelector('.mvc-day-close'));await wait(timing('transition'));
   }
   await click(document.querySelector('.mvc-close'));await wait(timing('calendarClose'));document.body.append(cursor);
   state(6,{title:'이제 유저와 연결해 볼게요',detail:'방금 사전등록한 건물에서 유저 연결 QR을 열어요.'});
   demoMap.map.setView([center.lat,center.lng],17,{animate:false});await wait(timing('backToBuilding'));
  }
  state(5,{title:'사전등록 목록에 저장됐어요',detail:'이제 지도로 이동해 등록한 건물을 선택합니다.'});
  if(auto)(async()=>{
   await ready;
   await wait(timing('savedOrReturn'));await click(document.querySelector('[data-tour-map-open]'));await wait(timing('mapReady'));await workspaceFeatures();await click(await until(()=>document.querySelector('[data-tour-building-marker]')));await wait(timing('transition'));await click(popup.querySelector('[data-tour-qr-open]'));
   await wait(timing('qrRead'));await click(document.querySelector('[data-tour-copy]'));cursor.hidden=true;state(6,{title:'복사한 링크를 보내면 돼요',detail:'카카오톡이나 문자 메시지에 붙여넣어 전달하세요.'});await wait(timing('copyHint'));startScan();await wait(timing('equipmentOrScan'));await click(document.querySelector('[data-phone-scan]'));
   await type(document.querySelector('[data-phone-id]'),'sobang_demo');document.querySelector('[data-phone-valid]').hidden=false;await type(document.querySelector('[data-phone-password]'),'DemoOnly!');await type(document.querySelector('[data-phone-confirm]'),'DemoOnly!');await click(document.querySelector('[data-phone-signup]'));
   await until(()=>!document.querySelector('[data-phone-step="card"]').hidden);await type(document.querySelector('[data-phone-card]'),'•••• •••• •••• 1234');await type(document.querySelector('[data-phone-expiry]'),'12 / 30');await click(document.querySelector('[data-phone-card-save]'));
   await wait(timing('subscriptionRead'));await click(document.querySelector('[data-phone-subscribe]'));await wait(timing('transition'));await showUserBuilding();await wait(timing('savedOrReturn'));await click(document.querySelector('[data-tour-building-marker]'));await wait(timing('transition'));await click(popup.querySelector('[data-tour-open-building]'));
  })().catch(fail);
 }else if(typeof STEPS!=='undefined'&&document.getElementById('chat')){
  window.down=function(){pauseChatFollow();window.scrollTo({top:0,behavior:'instant'});};
  state(1);
  if(auto)ready.then(draft).catch(fail);
  else{
   const indices={__search:1,__assembly:2,fire_engine_route:3,fire_engine_route_note:3,grade:4,__mgr:4,rep:5,tel:5};let last='';
   new MutationObserver(()=>{const now=field()||'done';if(now!==last){last=now;state(indices[now]??5,{detail:'직접 눌러 보세요. 입력 내용은 체험 공간에만 저장됩니다.'});}}).observe(document.getElementById('chat'),{childList:true});
   if(params.get('pick')==='1')until(()=>document.querySelector('#ansBox input')).then(async input=>{input.value='소방계획서.com 빌딩';input.dispatchEvent(new Event('input',{bubbles:true}));const result=await until(()=>document.querySelector('[data-tour-result]'));result.click();}).catch(fail);
  }
 }
})();
