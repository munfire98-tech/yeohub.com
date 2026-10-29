(() => {
  'use strict';
  if (window.firePlanShareReady) return;
  window.firePlanShareReady = true;
  let dialog, data, endpoint, selected, trigger, busy = false, controller, scrollState, generation = 0;
  const $ = s => dialog.querySelector(s);
  function makeDialog() {
    dialog = document.createElement('dialog');
    dialog.className = 'fps-dialog'; dialog.setAttribute('aria-labelledby', 'fps-title');
    dialog.innerHTML = `<header class="fps-header"><div><span class="fps-eyebrow">우리 건물의 안전계획</span><h2 id="fps-title">소방계획서 공유</h2></div><button type="button" class="fps-close" aria-label="공유 창 닫기" autofocus>×</button></header>
      <div class="fps-content"><p class="fps-intro">단톡방에 공유하면 구성원이 자기 이름과 역할을 선택해 임무를 확인할 수 있어요.</p>
      <nav class="fps-years" aria-label="공유할 계획서 연도"></nav>
      <p class="fps-message" role="status" aria-live="polite"></p>
      <section class="fps-preview" hidden><div class="fps-qr"></div><div class="fps-plan"><span class="fps-badge"></span><h3></h3><p class="fps-name"></p><p class="fps-updated"></p></div></section>
      <div class="fps-empty" hidden><span aria-hidden="true">▤</span><strong></strong><p></p></div>
      <div class="fps-notes" hidden><strong>같은 QR, 최신 저장 내용</strong><p>내 임무 확인과 전체 계획서를 함께 보여드립니다. 편성표를 변경했다면 계획서에 반영·저장한 뒤 공유해 주세요. 저장한 내용은 같은 링크에도 반영됩니다.</p><p class="fps-access">링크를 가진 사람은 로그인 없이 성명·연락처를 포함한 문서를 읽고 인쇄할 수 있습니다.</p></div>
      <div class="fps-actions" hidden><button type="button" class="fps-primary fps-create">이 연도 공유 시작</button><button type="button" class="fps-primary fps-share">공유 앱으로 보내기</button><button type="button" class="fps-secondary fps-copy">링크 복사</button></div>
      <p class="fps-share-hint" hidden>휴대폰 공유 메뉴에서 카카오톡을 선택하거나, 복사한 링크를 대화방에 붙여 넣으세요.</p>
      <div class="fps-link-row" hidden><input aria-label="공유 링크" readonly><a class="fps-open" target="_blank" rel="noopener noreferrer">미리보기 ↗</a></div>
      <div class="fps-tools" hidden><button type="button" class="fps-download">QR 저장</button><button type="button" class="fps-revoke">공유 중단</button></div>
      <div class="fps-confirm" hidden><p>이 연도의 기존 QR과 링크가 열리지 않게 됩니다. 이미 내려받은 문서는 회수되지 않습니다.</p><button type="button" class="fps-secondary fps-keep">유지하기</button><button type="button" class="fps-danger fps-stop">공유 중단하기</button></div>
      </div>`;
    document.body.append(dialog);
    $('.fps-close').onclick = () => dialog.close();
    dialog.addEventListener('click', e => { if(e.target === dialog) { const r=dialog.getBoundingClientRect(); if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)dialog.close(); }});
    dialog.addEventListener('close', () => {
      generation++; controller?.abort(); controller = null; busy=false;
      if(scrollState){const s=scrollState;document.body.style.cssText=s.css;window.scrollTo(s.x,s.y);scrollState=null;}
      trigger?.focus({preventScroll:true});
      // Do not retain another building's link after closing the dialog.
      data=null;$('.fps-qr').replaceChildren();$('.fps-link-row input').value='';
    });
    $('.fps-create').onclick=()=>mutate('create');
    $('.fps-revoke').onclick=()=>{$('.fps-confirm').hidden=false;$('.fps-keep').focus({preventScroll:true});$('.fps-confirm').scrollIntoView({block:'nearest',behavior:'smooth'});};
    $('.fps-keep').onclick=()=>{$('.fps-confirm').hidden=true;$('.fps-revoke').focus();};
    $('.fps-stop').onclick=()=>mutate('revoke');
    $('.fps-copy').onclick=copy;
    $('.fps-share').onclick=async()=>{
      const r=row();if(!r?.active)return;
      if(!navigator.share){await copy();return;}
      try{await navigator.share({title:`${r.year}년 소방계획서`,text:`${r.name||'우리 건물'} · ${r.year}년 소방계획서\n이름과 역할을 선택해 자위소방대 임무를 확인해 주세요.`,url:r.url});}
      catch(e){if(e.name!=='AbortError')message('공유 메뉴를 열지 못했습니다. 링크를 복사해 카카오톡에 보내주세요.');}
    };
    $('.fps-download').onclick=()=>{
      const r=row();if(!r?.active)return;
      const url=URL.createObjectURL(new Blob([r.qr],{type:'image/svg+xml'}));const a=document.createElement('a');a.href=url;a.download=`소방계획서-${r.year}-QR.svg`;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);
    };
  }
  function row(){return data?.years.find(r=>r.year===selected);}
  function message(text){$('.fps-message').textContent=text;}
  function render(){
    const years=$('.fps-years');years.replaceChildren();
    if(!data)return;
    for(const r of data.years){const b=document.createElement('button');b.type='button';b.className=r.year===selected?'is-selected':'';b.setAttribute('aria-pressed',String(r.year===selected));const strong=document.createElement('strong');strong.textContent=`${r.year}년`;const small=document.createElement('span');small.textContent=r.year===data.current_year?'올해':r.year===data.current_year+1?'차기 계획':r.year===data.current_year-1?'지난해': '보관';b.append(strong,small);b.disabled=busy;b.onclick=()=>{selected=r.year;message('');render();$('.fps-years button[aria-pressed="true"]').focus({preventScroll:true});};years.append(b);}
    const r=row();if(!r)return;
    dialog.classList.toggle('fps-active',r.active);
    if(r.active) $('.fps-notes').before($('.fps-actions'),$('.fps-share-hint'));
    else $('.fps-notes').after($('.fps-actions'),$('.fps-share-hint'));
    $('.fps-confirm').hidden=true;
    $('.fps-preview').hidden=!r.available;
    $('.fps-empty').hidden=r.available;
    $('.fps-empty strong').textContent=`${r.year}년 저장된 계획서가 없어요`;
    $('.fps-empty p').textContent='소방계획서 카드에서 해당 연도의 계획서를 작성·저장한 뒤 공유해 주세요.';
    $('.fps-badge').textContent=r.reviewed?'최종 확인 완료':`작성 중 · ${r.percent}%`;
    $('.fps-badge').classList.toggle('is-draft',!r.reviewed);
    $('.fps-plan h3').textContent=`${r.year}년 소방계획서`;
    $('.fps-name').textContent=r.name||'우리 건물';
    $('.fps-updated').textContent=r.updated_at?`최근 저장 ${r.updated_at}`:'';
    const qr=$('.fps-qr');qr.replaceChildren();
    if(r.active){qr.innerHTML=r.qr;}else{const icon=document.createElement('span');icon.className='fps-qr-placeholder';icon.textContent='QR';qr.append(icon);}
    $('.fps-notes').hidden=!r.available;
    $('.fps-actions').hidden=!r.available;
    $('.fps-create').hidden=r.active;
    $('.fps-create').disabled=busy||!data.can_create;
    $('.fps-create').textContent=data.can_create?'이 연도 공유 시작':'파트너 연결·PRO 이용 필요';
    $('.fps-share').hidden=!r.active;$('.fps-share').disabled=busy;
    $('.fps-copy').hidden=!r.active;$('.fps-copy').disabled=busy;
    $('.fps-share-hint').hidden=!r.active;
    $('.fps-link-row').hidden=!r.active;$('.fps-tools').hidden=!r.active;
    $('.fps-link-row input').value=r.url;$('.fps-open').href=r.url||'#';
    $('.fps-download').disabled=busy;$('.fps-revoke').disabled=busy;
  }
  async function copy(){const r=row();if(!r?.active)return;try{if(navigator.clipboard&&window.isSecureContext)await navigator.clipboard.writeText(r.url);else{const field=$('.fps-link-row input');field.focus();field.select();field.setSelectionRange(0,field.value.length);if(!document.execCommand('copy'))throw Error();}message('링크를 복사했습니다. 카카오톡 대화방에 붙여 넣어주세요.');}catch{const field=$('.fps-link-row input');field.focus();field.select();message('아래 링크를 길게 눌러 복사해 주세요.');}}
  async function request(action){
    controller?.abort();controller=new AbortController();const options={credentials:'same-origin',cache:'no-store',signal:controller.signal,headers:{Accept:'application/json'}};
    if(action){options.method='POST';options.body=new URLSearchParams({action,year:String(selected),csrf:data.csrf});}
    const response=await fetch(endpoint,options);let result;try{result=await response.json();}catch{throw Error('로그인 또는 담당 건물 연결을 확인한 뒤 다시 열어 주세요.');}
    if(!response.ok||!result.ok)throw Error(result.error||'공유 정보를 불러오지 못했습니다.');return result;
  }
  async function mutate(action){
    if(busy)return;const turn=generation;busy=true;render();message(action==='create'?'QR을 준비하고 있습니다.':'공유를 중단하고 있습니다.');
    try{const result=await request(action);if(!dialog.open||turn!==generation)return;data=result;message(action==='create'?'공유 중 · QR을 촬영하거나 링크를 보내주세요.':'공유를 중단했습니다. 다시 시작하면 새로운 QR이 만들어집니다.');}
    catch(e){if(turn===generation&&e.name!=='AbortError')message(e.message);}finally{if(turn===generation){busy=false;if(dialog.open)render();}}
  }
  document.addEventListener('click',async e=>{
    const button=e.target.closest('[data-fps-open]');if(!button)return;e.preventDefault();
    if(!dialog)makeDialog();if(dialog.open)return;
    const turn=++generation;trigger=button;endpoint=button.dataset.fpsEndpoint||'/fire_plan_share_api.php';data=null;selected=Number(button.dataset.fpsYear)||0;
    for(const el of dialog.querySelectorAll('.fps-preview,.fps-empty,.fps-notes,.fps-actions,.fps-share-hint,.fps-link-row,.fps-tools,.fps-confirm'))el.hidden=true;
    $('.fps-years').replaceChildren();message('저장된 연도를 확인하고 있습니다.');
    scrollState={x:window.scrollX,y:window.scrollY,css:document.body.style.cssText};
    document.body.style.position='fixed';document.body.style.top=`-${scrollState.y}px`;document.body.style.left='0';document.body.style.right='0';document.body.style.width='100%';
    dialog.showModal();$('.fps-close').focus({preventScroll:true});$('.fps-content').scrollTop=0;busy=true;
    try{const result=await request();if(!dialog.open||turn!==generation)return;data=result;if(!data.years.some(r=>r.year===selected))selected=data.current_year;message('');}
    catch(err){if(turn===generation&&err.name!=='AbortError')message(err.message);}
    finally{if(turn===generation){busy=false;if(dialog.open)render();}}
  });
})();
