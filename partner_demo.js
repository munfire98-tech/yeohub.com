(() => {
  'use strict';
  const source=document.currentScript;
  if(!source?.dataset.scope)return;
  // The old v1 flag only meant “seen once”, never an explicit opt-out.
  const key='partner-building-demo-v2:'+source.dataset.scope;
  let eligible=false,offered=false,pending=null,timer=null,dialog=null;
  const read=suffix=>{try{return localStorage.getItem(key+suffix)==='1';}catch{return false;}};
  const write=(suffix,value)=>{try{localStorage.setItem(key+suffix,value?'1':'0');}catch{}};
  let muted=read(':muted'),connected=read(':connected');
  const preference=()=>`<label class="pd-auto-choice"><input type="checkbox" data-pd-mute ${muted?'checked':''}><span>이제 익숙해요 · 자동으로 보지 않기</span></label>`;
  function queue(){
    clearTimeout(timer);
    if(!eligible||offered||muted||connected||!pending||window.managerNavigation?.state)return;
    timer=setTimeout(()=>{
      if(!eligible||offered||muted||connected||document.hidden||document.querySelector('dialog[open]'))return;
      // Offer the same guided start on desktop and mobile.
      if(prompt())offered=true;
    },250);
  }
  function offer(shouldGuide,showPopup){
    eligible=shouldGuide;
    pending=shouldGuide?showPopup:null;
    if(!shouldGuide&&dialog?.classList.contains('pd-prompt'))dialog.close();
    queue();
  }
  function createDialog(className,markup,trigger){
    const el=document.createElement('dialog'),returnFocus=trigger||document.activeElement;
    el.className=className;el.setAttribute('aria-labelledby','pd-title');el.innerHTML=markup;
    const previousOverflow=document.documentElement.style.overflow;
    document.body.append(el);document.documentElement.style.overflow='hidden';dialog=el;
    // Center in the visible mobile viewport, including Safari's changing bars.
    const viewport=window.visualViewport;
    let viewportFrame=0;
    const syncViewport=()=>{
      viewportFrame=0;
      for(const [key,value] of Object.entries({width:viewport?.width??window.innerWidth,height:viewport?.height??window.innerHeight,left:viewport?.offsetLeft??0,top:viewport?.offsetTop??0}))el.style.setProperty('--pd-view-'+key,value+'px');
    };
    const queueViewport=()=>{cancelAnimationFrame(viewportFrame);viewportFrame=requestAnimationFrame(syncViewport);};
    syncViewport();window.addEventListener('resize',queueViewport);viewport?.addEventListener('resize',queueViewport);viewport?.addEventListener('scroll',queueViewport);
    el.addEventListener('close',()=>{
      cancelAnimationFrame(viewportFrame);window.removeEventListener('resize',queueViewport);viewport?.removeEventListener('resize',queueViewport);viewport?.removeEventListener('scroll',queueViewport);
      document.documentElement.style.overflow=previousOverflow;el.remove();
      if(dialog===el)dialog=null;
      if(returnFocus?.isConnected)returnFocus.focus({preventScroll:true});
    },{once:true});
    return el;
  }
  function prompt(){
    if(dialog)return false;
    const el=createDialog('pd-prompt',`<button type="button" class="pd-close" data-pd-prompt-close aria-label="예시 안내 닫기">×</button><span class="pd-demo-badge">처음 오셨나요?</span><h2 id="pd-title">사용법을 알려드릴까요?</h2><p>사전등록·요약·방문일정부터 유저 연결까지,<br>실제 화면으로 자동 안내해 드려요.</p><button type="button" class="pd-primary" data-pd-prompt-start>사용법 확인하기 →</button>${preference()}`,document.querySelector('[data-partner-demo-open]'));
    el.querySelector('[data-pd-prompt-close]').onclick=()=>el.close();
    el.querySelector('[data-pd-prompt-start]').onclick=()=>{
      el.addEventListener('close',()=>tour(document.querySelector('[data-partner-tour-open]'),true),{once:true});el.close();
    };
    el.showModal();el.querySelector('[data-pd-prompt-close]').focus({preventScroll:true});return true;
  }
  function startRegistration(){
    if(dialog)dialog.close();
    requestAnimationFrame(()=>document.querySelector('.mm-draft-add[data-address-open]')?.click());
  }
  function open(trigger){
    if(dialog)return;
    offered=true;clearTimeout(timer);
    const el=createDialog('pd-dialog pd-live-dialog',`<header class="pd-head"><div><span class="pd-demo-badge">연결 체험</span><strong id="pd-title">소방계획서.com 빌딩 · 건물관리</strong></div><button type="button" class="pd-close" data-pd-close aria-label="예시 체험 닫기">×</button></header><div class="pd-live-toolbar"><span>실제 업무 화면에서 직접 작성해 보세요.<small>체험 내용만 임시 저장되며, 매일 초기화됩니다.</small></span><button type="button" data-pd-home>건물관리로</button></div><div class="pd-live-content"><div class="pd-loading" role="status">연결된 건물관리 화면을 준비하고 있어요…</div><iframe class="pd-live-frame" title="소방계획서.com 빌딩 예시 건물관리 화면" src="/partner_demo_app.php/FireDesk.php" sandbox="allow-scripts allow-same-origin allow-forms allow-modals allow-downloads allow-popups" allow="clipboard-write"></iframe></div><footer class="pd-live-footer">${preference()}<button type="button" class="pd-register" data-pd-register><span aria-hidden="true">＋</span> 건물 사전등록</button></footer>`,trigger);
    const frame=el.querySelector('iframe');
    frame.addEventListener('load',()=>{el.querySelector('.pd-loading').hidden=true;});
    el.querySelector('[data-pd-close]').onclick=()=>el.close();
    el.querySelector('[data-pd-home]').onclick=()=>{frame.src='/partner_demo_app.php/FireDesk.php';};
    el.querySelector('[data-pd-register]').onclick=startRegistration;
    el.showModal();el.querySelector('[data-pd-close]').focus({preventScroll:true});
  }
  function tour(trigger,autoplay=true){
    if(dialog)return;
    document.dispatchEvent(new CustomEvent('partner-tour-open'));
    offered=true;clearTimeout(timer);
    const steps=['건물 사전등록','예시 건물 검색','등급 · 담당자','저장 완료','요약 확인','방문일정','QR 촬영','유저 가입','카드등록','구독 완료','지도에서 PRO 확인'];
    const el=createDialog('pd-dialog pwt-dialog',`<header class="pd-head"><div><span class="pd-demo-badge">예시 체험</span><strong id="pd-title">사전등록부터 업무 활용·유저 연결까지</strong></div><button type="button" class="pd-close" data-pwt-close aria-label="사용법 안내 닫기">×</button></header><div class="pwt-layout"><aside class="pwt-rail"><span class="pwt-kicker">PARTNER GUIDE</span><h3>한 번 보면,<br>바로 시작할 수 있게.</h3><p>소방계획서.com 빌딩으로<br>등록 과정을 따라가 볼게요.</p><ol class="pwt-steps">${steps.map((name,i)=>`<li data-pwt-step="${i}"><span>${i+1}</span>${name}</li>`).join('')}</ol><div class="pwt-note">가상 건물로 진행하는 체험입니다.<br>실제 거래처·유저·결제는 생성되지 않습니다.</div></aside><section class="pwt-view"><iframe title="건물 사전등록 사용법 체험" sandbox="allow-scripts allow-same-origin allow-forms allow-modals allow-downloads allow-popups" hidden></iframe><div class="pwt-entry"><div class="pwt-entry-map" aria-hidden="true"><span>P</span><span>C</span><span>S</span></div><span class="pwt-entry-cursor" aria-hidden="true">➤</span><h3>첫 건물, 이렇게 등록해요</h3><p>파트너센터의 건물 사전등록 버튼을 눌러요.<br>이제 실제 등록 화면에서 차근차근 보여드릴게요.</p><button type="button" class="mm-draft-add" data-pwt-start><span aria-hidden="true">＋</span><span class="mm-draft-copy"><b>건물 사전등록</b><small>유저 연결 전에 미리 준비하세요</small></span></button><small>자동으로 시작됩니다 · 언제든 일시정지할 수 있어요</small></div><div class="pwt-pause-notice" hidden role="status"><strong>Ⅱ 일시정지됨</strong><span>이어 보기를 누르면 계속됩니다.</span><button type="button" data-pwt-resume>▶ 이어 보기</button></div></section></div><div class="pwt-playback"><label>시연 시간 <output data-pwt-time>0:00</output><input data-pwt-seek type="range" min="0" max="150000" step="100" value="0" aria-label="재생 위치"></label><label class="pwt-speed">속도 <select data-pwt-rate aria-label="재생 속도"><option value="0.5">0.5×</option><option value="0.75">0.75×</option><option value="1" selected>1×</option><option value="1.5">1.5×</option><option value="2">2×</option></select></label><span data-pwt-seeking hidden role="status">선택한 장면을 준비하고 있어요…</span></div><footer class="pwt-footer"><div class="pwt-caption" role="status" aria-live="polite"><strong data-pwt-title></strong><small data-pwt-detail></small></div><div class="pwt-controls"><button type="button" data-pwt-replay>처음부터</button><button type="button" class="pwt-continue" data-pwt-pause>일시정지</button><button type="button" data-pwt-register hidden>내 건물 사전등록 →</button></div></footer>`,trigger);
    const frame=el.querySelector('iframe'),entry=el.querySelector('.pwt-entry'),pause=el.querySelector('[data-pwt-pause]');
    const config=window.partnerTourConfig,slider=el.querySelector('[data-pwt-seek]'),speed=el.querySelector('[data-pwt-rate]'),output=el.querySelector('[data-pwt-time]'),seekingNotice=el.querySelector('[data-pwt-seeking]');
    let started=false,paused=false,finished=false,remaining=config.entryMs,time=0,total=config.totalMs,rate=1,nonce=null,seekTarget=null,dragging=false;
    try{const saved=Number(localStorage.getItem('partner-tour-rate'));if(config.rates.includes(saved))rate=saved;}catch{}
    speed.value=String(rate);slider.max=total;
    const stamp=ms=>Math.floor(ms/60000)+':'+String(Math.floor(ms/1000)%60).padStart(2,'0');
    const paint=()=>{if(!dragging)slider.value=Math.min(time,total);const shown=dragging?Number(slider.value):time;output.textContent=stamp(shown)+' / '+stamp(total);slider.setAttribute('aria-valuetext',stamp(shown));seekingNotice.hidden=seekTarget===null;el.classList.toggle('is-seeking',seekTarget!==null);};
    speed.onchange=()=>{rate=Number(speed.value);try{localStorage.setItem('partner-tour-rate',String(rate));}catch{}command('rate',{rate});};
    slider.oninput=()=>{dragging=true;paint();};
    slider.onchange=()=>{const target=Number(slider.value);dragging=false;restart();if(target<config.entryMs){time=target;remaining=config.entryMs-target;paused=true;showPause();}else{time=config.entryMs;remaining=0;seekTarget=target;launch();}paint();};
    const notice=el.querySelector('.pwt-pause-notice');
    const showPause=()=>{notice.hidden=!paused||finished;el.classList.toggle('is-paused',paused);pause.textContent=paused?'▶ 이어 보기':'Ⅱ 일시정지';};
    const update=(n,title,detail)=>{el.classList.toggle('pwt-visit-scene',n===5);el.querySelectorAll('[data-pwt-step]').forEach((item,i)=>{item.classList.toggle('is-current',i===n);item.classList.toggle('is-done',i<n);item.setAttribute('aria-current',i===n?'step':'false');});el.querySelector('[data-pwt-title]').textContent=title;el.querySelector('[data-pwt-detail]').textContent=detail;};
    const command=(action,data={})=>frame.contentWindow?.postMessage({type:'partner-tour-control',action,...data},location.origin);
    function launch(){if(started)return;started=true;entry.hidden=true;frame.hidden=false;frame.src='/partner_demo_app.php/manager_draft.php?'+(autoplay?'autoplay=1':'pick=1');update(1,'예시 건물을 준비하고 있어요','입력 내용은 별도의 체험 공간에만 저장됩니다.');}
    function restart(){autoplay=true;paused=false;finished=false;started=false;remaining=config.entryMs;time=0;nonce=null;seekTarget=null;paint();showPause();entry.classList.remove('is-clicking');entry.querySelector('.pwt-entry-cursor').removeAttribute('style');frame.src='about:blank';frame.hidden=true;entry.hidden=false;pause.hidden=false;pause.textContent='일시정지';el.querySelector('[data-pwt-register]').hidden=true;update(0,steps[0],'파트너센터에서 이 버튼을 찾으면 돼요.');}
    entry.addEventListener('click',e=>{if(e.isTrusted&&autoplay){e.preventDefault();e.stopImmediatePropagation();}},true);
    const tick=setInterval(()=>{if(!started&&!paused&&!document.hidden){remaining-=100*rate;time=Math.min(config.entryMs,config.entryMs-remaining);paint();if(remaining<=1600){const r=el.querySelector('[data-pwt-start]').getBoundingClientRect(),base=entry.getBoundingClientRect(),c=entry.querySelector('.pwt-entry-cursor');c.style.left=r.left-base.left+r.width*.7+'px';c.style.top=r.top-base.top+r.height*.5+'px';}entry.classList.toggle('is-clicking',remaining<=800);if(remaining<=0)el.querySelector('[data-pwt-start]').click();}},100);
    function receive(e){
      if(e.origin!==location.origin||e.source!==frame.contentWindow||!started)return;
      const data=e.data;if(!data)return;
      if(data.type==='partner-tour-ready'){
        nonce=data.nonce;command('initialize',{nonce,time,rate,seekTarget,paused});return;
      }
      if(data.nonce!==nonce||!nonce)return;
      if(!['partner-tour-state','partner-tour-progress'].includes(data.type))return;
      if(Number.isFinite(data.time)){time=data.time;if(time>total){total=time;slider.max=total;}}
      if(data.seeked){seekTarget=null;paused=true;showPause();}
      paint();
      if(data.type==='partner-tour-progress')return;
      if(!Number.isInteger(data.step)||data.step<0||data.step>=steps.length)return;
      update(data.step,String(data.title||''),String(data.detail||''));
      if(paused&&!data.paused){command('pause');return;}
      paused=!!data.paused;showPause();
      if(data.error){seekTarget=null;paint();}
      if(data.finished){finished=true;seekTarget=null;total=time;slider.max=total;paint();showPause();pause.hidden=true;el.querySelector('[data-pwt-register]').hidden=false;}
      el.querySelector('[data-pwt-detail]').classList.toggle('pwt-error',!!data.error);
    }
    window.addEventListener('message',receive);
    frame.addEventListener('load',()=>{
      let path='';try{path=new URL(frame.contentWindow.location.href).pathname;}catch{}
      if(path==='/partner_demo_app.php/FireDesk.php'){
        finished=true;update(10,'이제 유저의 업무를 함께 확인해 보세요','저장한 기본정보가 반영됐어요. 카드를 눌러 직접 둘러볼 수 있습니다.');pause.hidden=true;el.querySelector('[data-pwt-register]').hidden=false;
      }
    });
    pause.onclick=()=>{if(seekTarget!==null)return;if(!autoplay){restart();return;}paused=!paused;showPause();command(paused?'pause':'resume');};
    el.querySelector('[data-pwt-resume]').onclick=()=>{paused=false;showPause();command('resume');};
    el.querySelector('[data-pwt-start]').onclick=()=>{paused=false;showPause();launch();};
    el.querySelector('[data-pwt-replay]').onclick=restart;
    el.querySelector('[data-pwt-register]').onclick=startRegistration;
    el.querySelector('[data-pwt-close]').onclick=()=>el.close();
    el.addEventListener('close',()=>{clearInterval(tick);window.removeEventListener('message',receive);frame.src='about:blank';},{once:true});
    update(0,steps[0],'파트너센터에서 이 버튼을 찾으면 돼요.');
    paint();el.showModal();el.querySelector('[data-pwt-close]').focus({preventScroll:true});
    if(!autoplay){launch();pause.textContent='자동 안내 보기';}
  }

  document.addEventListener('click',e=>{const tourButton=e.target.closest('[data-partner-tour-open]');if(tourButton){e.preventDefault();tour(tourButton,true);return;}const button=e.target.closest('[data-partner-demo-open]');if(button){e.preventDefault();open(button);}});
  document.addEventListener('change',e=>{
    if(!e.target.matches('[data-pd-mute]'))return;
    muted=e.target.checked;write(':muted',muted);
    document.querySelectorAll('[data-pd-mute]').forEach(input=>input.checked=muted);
    if(muted){offered=true;clearTimeout(timer);}
  });
  document.addEventListener('manager-buildings-updated',e=>{
    const rows=e.detail?.buildings;
    if(!Array.isArray(rows))return;
    const hasLinkedUser=rows.some(row=>!row.demo&&!row.preregistered);
    eligible=!hasLinkedUser;
    if(hasLinkedUser){connected=true;write(':connected',true);pending=null;clearTimeout(timer);if(dialog?.classList.contains('pd-prompt'))dialog.close();}
  });
  document.addEventListener('manager-buildings-unavailable',()=>{eligible=false;pending=null;clearTimeout(timer);});
  document.addEventListener('close',queue,true);
  document.addEventListener('visibilitychange',queue);
  for(const event of ['manager-building-selected','manager-map-focus'])document.addEventListener(event,()=>{offered=true;clearTimeout(timer);});
  window.addEventListener('pageshow',e=>{if(e.persisted){offered=false;queue();}});
  window.addEventListener('storage',e=>{
    if(e.key===key+':muted')muted=e.newValue==='1';
    if(e.key===key+':connected')connected=e.newValue==='1';
    if(muted||connected)clearTimeout(timer);
    document.querySelectorAll('[data-pd-mute]').forEach(input=>input.checked=muted);
  });
  window.partnerDemo={offer,prompt,tour,isMuted:()=>muted};
})();
