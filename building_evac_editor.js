(()=>{
 if(!HOSTED)return;
 const controls=document.createElement('div');controls.className='bes-editor-actions';controls.innerHTML='<button type="button" data-bes-settings>① 건물 설정</button><button type="button" data-bes-edit>② 도면 편집</button><button type="button" data-bes-run>③ 시뮬레이션</button><button type="button" data-bes-save>저장</button><button type="button" data-bes-close>닫기</button>';
 document.querySelector('.topbar').append(controls);
 const libraryButton=document.createElement('button');libraryButton.type='button';libraryButton.textContent='내 도면';controls.prepend(libraryButton);
 libraryButton.onclick=async()=>{if(!(await window.buildingEvacFlush())){alert('저장하지 못했습니다. 저장 상태를 확인한 뒤 다시 시도해 주세요.');return;}const u=new URL(location.href);u.searchParams.delete('doc');location.href=u;};

 const settings=document.createElement('dialog');settings.className='bes-settings';settings.innerHTML='<form><header><small>BUILDING SETUP</small><h2>기본정보를 불러올까요?</h2><p>시뮬레이션을 만들 동과 층수를 확인해 주세요.</p></header><label class="bes-dong-label">시뮬레이션 대상 동<select name="dong" required></select></label><p class="bes-basic-summary" aria-live="polite"></p><div class="bes-settings-fields"><label>가로 길이 (m)<input name="width" type="number" min="12" max="200" step="1" required></label><label>세로 길이 (m)<input name="height" type="number" min="12" max="200" step="1" required></label><label>지상층<input name="above" type="number" min="1" max="8" step="1" required></label><label>지하층<input name="below" type="number" min="0" max="7" step="1" required></label></div><p class="bes-settings-note">지상·지하 합계 최대 8층 · 기본 평면은 편집을 위한 시작 도면입니다.</p><p class="bes-settings-error" role="alert"></p><footer><button type="button" data-cancel>닫기</button><button type="button" data-continue hidden>기존 도면 열기</button><button type="submit">이 정보로 도면 만들기</button></footer></form>';
 document.body.append(settings);const form=settings.querySelector('form'),field=n=>form.elements.namedItem(n),error=settings.querySelector('.bes-settings-error');
 const options=HOST_BUILDINGS,summary=settings.querySelector('.bes-basic-summary');
 let setupStep=1;
 const steps=document.createElement('p');steps.className='bes-steps';steps.setAttribute('aria-live','polite');form.querySelector('header').after(steps);
 const sizeHint=document.createElement('p');sizeHint.className='bes-size-hint';sizeHint.textContent='가로·세로는 실제 길이를 확인해 입력하세요. 현재 숫자는 시작용 예시이며 기본정보에서 계산한 값이 아닙니다.';form.querySelector('.bes-settings-fields').after(sizeHint);
 const prev=document.createElement('button');prev.type='button';prev.textContent='이전';form.querySelector('footer').insertBefore(prev,form.querySelector('[type="submit"]'));prev.onclick=()=>showStep(1);
 function showStep(step){
  setupStep=step;steps.textContent=step===1?'1 / 2 · 건물과 층수 확인':'2 / 2 · 도면 크기 설정';
  settings.querySelector('.bes-dong-label').hidden=step!==1;summary.hidden=step!==1;
  for(const key of ['above','below'])field(key).closest('label').hidden=step!==1;
  for(const key of ['width','height'])field(key).closest('label').hidden=step!==2;
  sizeHint.hidden=step!==2;prev.hidden=step===1;sizePreview.hidden=step!==2;if(step===2)drawSize();
  form.querySelector('[type="submit"]').textContent=step===1?'이 층수로 다음':'이 크기로 내부 그리기';
  settings.querySelector('h2').textContent=step===1?'어떤 건물을 만들어 볼까요?':'건물의 가로·세로를 알려 주세요';
  form.querySelector('header p').textContent=step===1?'기본정보에서 가져온 동과 층수를 확인해 주세요.':'실제 건물 크기를 바탕으로 편집할 시작 도면을 만듭니다.';
  error.textContent='';
 }
 form.noValidate=true;
 const sizePreview=document.createElement('section');sizePreview.className='bes-size-preview';
 sizePreview.innerHTML='<div class="bes-size-caption"><strong>빈 바닥 크기</strong><output aria-live="polite"></output></div><svg viewBox="0 0 400 270" role="img" aria-label="외벽과 사람 크기 비교"><defs><pattern id="bes-metre" patternUnits="userSpaceOnUse"><path fill="none" stroke="#d7e3ed" stroke-width=".6"/></pattern></defs><rect class="bes-floor" fill="url(#bes-metre)" stroke="#405a74" stroke-width="3"/><g class="bes-person" fill="#2764da" stroke="#2764da" stroke-linecap="round"><circle cx="0" cy="-0.61" r=".13" stroke="none"/><path d="M0 -.4V.12M-.26 -.1L0 -.35 .26 -.1M0 .12L-.18 .74M0 .12L.18 .74" fill="none" stroke-width=".1"/></g><text class="bes-person-label" font-size="11" fill="#425a74">사람 약 1.7m</text><g class="bes-width-grip" role="slider" tabindex="0" aria-label="가로 길이" aria-valuemin="12" aria-valuemax="200"><rect width="24" height="38" rx="9" fill="#2866dd"/><path d="M9 12v14M15 12v14" stroke="white" stroke-width="2"/></g><g class="bes-height-grip" role="slider" tabindex="0" aria-label="세로 길이" aria-valuemin="12" aria-valuemax="200"><rect width="38" height="24" rx="9" fill="#2866dd"/><path d="M12 9h14M12 15h14" stroke="white" stroke-width="2"/></g></svg><p>파란 손잡이를 끌거나 위 숫자를 입력하세요. 격자 한 칸은 1m입니다.</p><small>사람은 키 약 1.7m의 크기 비교용 그림입니다. 실제 재실자나 피난 시작 위치가 아닙니다.</small>';
 sizeHint.before(sizePreview);
 let sizeScale=1,dragSize=null;
 const sizeSvg=sizePreview.querySelector('svg');
 function drawSize(){
  const w=+field('width').value,h=+field('height').value;if(!w||!h)return;
  if(!dragSize)sizeScale=Math.min(320/w,190/h);
  const x=24,y=20,sw=w*sizeScale,sh=h*sizeScale;
  const rect=sizePreview.querySelector('.bes-floor');for(const [k,v]of Object.entries({x,y,width:sw,height:sh}))rect.setAttribute(k,v);
  const pattern=sizePreview.querySelector('pattern');pattern.setAttribute('width',sizeScale);pattern.setAttribute('height',sizeScale);pattern.querySelector('path').setAttribute('d',`M ${sizeScale} 0 H 0 V ${sizeScale}`);
  sizePreview.querySelector('.bes-person').setAttribute('transform',`translate(${x+sw*.3},${y+sh*.5}) scale(${sizeScale*1.7/1.48})`);
  const label=sizePreview.querySelector('.bes-person-label');label.setAttribute('x',x+sw*.3+12);label.setAttribute('y',y+sh*.5+4);
  for(const [key,cls,tx,ty]of [['width','.bes-width-grip',x+sw-12,y+sh/2-19],['height','.bes-height-grip',x+sw/2-19,y+sh-12]]){const g=sizePreview.querySelector(cls);g.setAttribute('transform',`translate(${tx},${ty})`);g.setAttribute('aria-valuenow',field(key).value);g.setAttribute('aria-valuetext',field(key).value+'미터');}
  sizePreview.querySelector('output').textContent=`${w}m × ${h}m · ${(w*h).toLocaleString()}㎡`;
 }
 for(const key of ['width','height']){
  field(key).addEventListener('input',drawSize);
  const g=sizePreview.querySelector(key==='width'?'.bes-width-grip':'.bes-height-grip');
  g.addEventListener('pointerdown',e=>{e.preventDefault();const box=sizeSvg.getBoundingClientRect();dragSize={key,id:e.pointerId,start:key==='width'?e.clientX:e.clientY,value:+field(key).value,ratio:400/box.width,scale:sizeScale};g.setPointerCapture(e.pointerId);});
  g.addEventListener('pointermove',e=>{if(!dragSize||e.pointerId!==dragSize.id)return;const d=dragSize,now=d.key==='width'?e.clientX:e.clientY;field(d.key).value=Math.max(12,Math.min(200,Math.round(d.value+(now-d.start)*d.ratio/d.scale)));drawSize();});
  const end=()=>{dragSize=null;drawSize();};g.addEventListener('pointerup',end);g.addEventListener('pointercancel',end);
  g.addEventListener('keydown',e=>{if(!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(e.key))return;e.preventDefault();field(key).value=Math.max(12,Math.min(200,+field(key).value+(['ArrowRight','ArrowUp'].includes(e.key)?1:-1)));drawSize();});
 }


 const savedScenario=JSON.parse(HOST_SCN||'{}');
 options.forEach(row=>{const o=document.createElement('option');o.value=row.id;o.textContent=row.name+(row.primary?' · 기준동':'');field('dong').append(o);});
 if(options.length>1){const empty=document.createElement('option');empty.value='';empty.textContent='어떤 동을 만들까요?';field('dong').prepend(empty);}
 function applyBasic(){
  const row=options.find(r=>r.id===field('dong').value);
  field('above').value=row?.above??'';field('below').value=row?.below??'';
  summary.textContent=row?row.name+' · 지상 '+(row.above??'미입력')+'층 / 지하 '+(row.below??'미입력')+'층 — 이 층수를 사용할까요?':'동을 선택하면 해당 동의 기본정보를 불러옵니다.';
  error.textContent=row&&(row.above??0)+(row.below??0)>MAX_FLOORS?'기본정보의 전체 층수가 지원 범위(8층)를 넘습니다. 층수를 확인하고 모형에 표현할 범위를 직접 정해 주세요.':'';
 }
 field('dong').onchange=applyBasic;
 function configure(){
  field('width').value=GW;field('height').value=GH;
  const previous=options.find(r=>r.name===hostSourceDong||r.name===savedScenario.source_dong);
  field('dong').value=previous?.id||(options.length===1?options[0].id:'');applyBasic();
  settings.querySelector('[data-continue]').hidden=!(EMBED_MAP||hostRevision>0||dirtyFlag);
  showStep(1);settings.showModal();(options.length>1?field('dong'):field('above')).focus();
 }
 async function leave(){
  if(window.parent!==window){window.parent.postMessage({type:'building-evac-close'},location.origin);return;}
  if(!(await window.buildingEvacFlush())&&!confirm('저장에 실패했습니다. 저장하지 않고 빌딩 매니저로 돌아갈까요?'))return;
  location.href='/building_manager.php'+(new URL(location.href).searchParams.has('uid')?'?uid='+encodeURIComponent(new URL(location.href).searchParams.get('uid')):'');
 }
 controls.querySelector('[data-bes-settings]').onclick=configure;
 controls.querySelector('[data-bes-close]').onclick=leave;
 settings.querySelector('[data-cancel]').onclick=leave;
 settings.addEventListener('cancel',e=>{e.preventDefault();leave();});
 settings.querySelector('[data-continue]').onclick=()=>settings.close();
 form.onsubmit=e=>{
  e.preventDefault();
  const required=setupStep===1?['dong','above','below']:['width','height'];
  for(const key of required)if(!field(key).reportValidity())return;
  if(setupStep===1){if(+field('above').value + +field('below').value>MAX_FLOORS){error.textContent='현재 전체 8층까지 지원합니다. 실제 층수를 임의로 줄이지 말고 표현할 범위를 먼저 확인해 주세요.';return;}showStep(2);field('width').focus();return;}

  const w=+field('width').value,h=+field('height').value,above=+field('above').value,below=+field('below').value;
  if(above+below>MAX_FLOORS){error.textContent='지상층과 지하층의 합계를 8층 이하로 입력해 주세요.';return;}
  if((EMBED_MAP||hostRevision>0||dirtyFlag)&&!confirm('기존 도면을 새 기본 평면으로 바꿀까요? 변경 직후에는 실행취소할 수 있습니다.'))return;
  hostSourceDong=options.find(r=>r.id===field('dong').value)?.name||'';
  snapshot();window.besRoutes=[];window.besRoomLabels=[];BASEMENTS=below;GW=w;GH=h;FLOORS=above+below;grids=Array.from({length:FLOORS},()=>Array.from({length:h},(_,y)=>Array.from({length:w},(_,x)=>x===0||y===0||x===w-1||y===h-1?WALL:FLOOR)));stairCfg={};customMapText=serializeMap();viewF=below;reset();showPane(true);autoSave();settings.close();updateGuide();window.bedOpen?.();
 };
 controls.querySelector('[data-bes-edit]').onclick=()=>{showPane(true);updateGuide();};
 controls.querySelector('[data-bes-run]').onclick=()=>{showPane(false);document.getElementById('btnStart').focus();};
 controls.querySelector('[data-bes-save]').onclick=async e=>{const button=e.currentTarget;button.disabled=true;dirtyFlag=true;try{await window.buildingEvacFlush();}finally{button.disabled=false;}};
 // The hosted window does not expose the administrator's shared library.
 document.getElementById('btnModels').hidden=true;
 document.getElementById('selGrade').addEventListener('change',autoSave);

 const guide=document.createElement('section');guide.className='bes-edit-guide';guide.innerHTML='<strong>층을 고르고, 실제 건물에 맞게 그려 주세요</strong><ol><li>왼쪽 도면 위에서 편집할 층을 선택하세요.</li><li>벽·문·비상구·계단 도구를 선택해 배치하세요.</li><li>준비되면 위의 ③ 시뮬레이션을 누르세요.</li></ol><p aria-live="polite"></p>';
 document.getElementById('paneEdit').prepend(guide);
 function updateGuide(){if(document.body.classList.contains('bes-simple'))return;const label={wall:'벽',floor:'바닥',room:'구획',door:'문',exit:'비상구',stair:'계단실',erase:'지우기',poly:'연결선',hydrant:'옥내소화전'}[editTool]||'선택한 도구';guide.querySelector('p').textContent='현재 도구: '+label+' · 수정 내용은 자동으로 저장됩니다.';}
 document.querySelectorAll('[data-tool]').forEach(b=>b.addEventListener('click',updateGuide));
 for(const block of [...document.querySelectorAll('#paneEdit>.block')]){
  const title=block.querySelector('.eyebrow')?.textContent.trim();
  if(!['밑그림 도면','계단 설정','도면 텍스트','시작 평면'].includes(title))continue;
  const details=document.createElement('details'),summary=document.createElement('summary');details.className='bes-advanced';summary.textContent=title+' · 세부 설정';block.before(details);details.append(summary,block);
 }
 const runHint=document.createElement('p');runHint.className='bes-run-hint';runHint.textContent='재실 인원을 확인하고 도면에서 화재 시작 위치를 누른 다음, 시작 버튼을 누르세요. 이 모형은 대피 과정을 살펴보는 참고용입니다.';document.getElementById('btnStart').before(runHint);
 document.getElementById('btnStart').textContent='시뮬레이션 시작';
 updateGuide();
 if(EMBED_MAP){
  const welcome=document.createElement('dialog');welcome.className='bes-settings bes-welcome';welcome.setAttribute('aria-label','내 도면 열기');
  welcome.innerHTML='<small>MY BUILDING</small><h2>저장된 내 도면이 있습니다</h2><p class="bes-welcome-name"></p><p class="bes-settings-note">이전에 만든 도면을 이어서 편집하거나 시뮬레이션을 실행할 수 있습니다. 새로 만들면 현재 도면이 교체됩니다.</p><div class="bes-welcome-actions"><button type="button" data-resume>내 도면 이어서 열기</button><button type="button" data-run>바로 시뮬레이션 보기</button><button type="button" data-rebuild>새 도면 만들기</button><button type="button" data-leave>닫기</button></div>';
  welcome.querySelector('.bes-welcome-name').textContent=EMBED_NAME+(hostSourceDong?' · '+hostSourceDong:'');document.body.append(welcome);
  welcome.querySelector('[data-resume]').onclick=()=>{welcome.close();showPane(true);updateGuide();};
  welcome.querySelector('[data-run]').onclick=()=>{welcome.close();showPane(false);};
  welcome.querySelector('[data-rebuild]').onclick=()=>{welcome.close();configure();};
  welcome.querySelector('[data-leave]').onclick=leave;welcome.addEventListener('cancel',e=>{e.preventDefault();leave();});welcome.showModal();welcome.querySelector('[data-resume]').focus();
 }else configure();
})();
