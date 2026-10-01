(()=>{'use strict';
 const triggers=[...document.querySelectorAll('[data-education-open]')];if(!triggers.length||document.getElementById('building-education-dialog'))return;
 const buildingUid=document.currentScript?.dataset.buildingUid||'';
 const media={
 extinguisher:{title:'소화기 사용법',file:'extinguisher-training-v1',category:'초기 화재 대응',note:'불이 커지거나 연기가 많아지면 즉시 대피하세요. 사용하는 소화기의 표시사항을 확인해 주세요.'},
 auto:{title:'옥내소화전 · 자동 기동 방식',file:'hydrant-auto-v3',category:'밸브 개방 · 자동 기동',note:'밸브 개방에 따른 압력 저하로 펌프가 자동 기동하는 설비의 교육 영상입니다. 실제 현장의 안내표지와 사용 방법을 먼저 확인하세요.'},
 manual:{title:'옥내소화전 · 기동 버튼 방식',file:'hydrant-manual-v2',category:'기동 ON · 수동 기동',note:'기동 ON 버튼을 누른 뒤 밸브를 여는 방식의 교육 영상입니다. 색만으로 판단하지 말고 기동·정지 표시를 확인하세요. 사용 중 임의로 OFF를 누르지 마세요.'}
 };
 const dialog=document.createElement('dialog');dialog.id='building-education-dialog';dialog.setAttribute('aria-labelledby','be-title');
 dialog.innerHTML=`<header class="be-head"><div><span class="be-kicker">FIRE SAFETY LIBRARY</span><h2 id="be-title">소방교육 자료</h2><p>현장에서 함께 보고 익히는 소방안전 교육</p></div><button type="button" class="be-close" aria-label="교육자료 닫기">×</button></header><div class="be-body"><section class="be-list" aria-label="교육자료 목록"><section class="be-building" aria-label="이 건물의 옥내소화전 방식"><span class="be-category">소방시설 현황 연동</span><h3 class="be-building-title">소방시설 현황을 확인하고 있습니다.</h3><p class="be-building-status" role="status" aria-live="polite"></p><div class="be-building-scopes"></div><button type="button" class="be-setting-reload" hidden>다시 확인</button></section><div class="be-list-head"><strong>교육 영상</strong><span>2개 주제 · 3개 영상</span></div><button type="button" class="be-card" data-education-play="extinguisher"><span class="be-thumb"><img src="/media/education/extinguisher-training-v1.jpg" alt="소화기 안전핀 교육" loading="lazy"><span class="be-play" aria-hidden="true">▶</span><span class="be-duration">1분 11초</span></span><span class="be-card-info"><span class="be-category">초기 화재 대응</span><strong>소화기 사용법</strong><span class="be-description">안전핀, 노즐, 손잡이, 좌우 분사. 초기 화재 대응을 차례로 배워보세요.</span><span class="be-tags"><span>한국어 음성</span><span>자막 포함</span></span><span class="be-card-action">영상 보기 →</span></span></button>
 <article class="be-hydrant"><div class="be-hydrant-heading"><span class="be-category">설비에 맞는 교육</span><h3>옥내소화전 사용법</h3><p>현장 안내표지를 확인하고, 설치된 방식에 맞는 영상을 선택하세요.</p></div><div class="be-type-grid" role="group" aria-label="옥내소화전 기동 방식 선택"><button type="button" class="be-type be-type-auto" data-education-play="auto"><img src="/media/education/hydrant-auto-v3.jpg" alt="자동 기동형 옥내소화전 교육" loading="lazy"><span class="be-type-copy"><span class="be-type-badge">자동 기동</span><strong>밸브를 열어 사용하는 방식</strong><span>밸브 개방 → 압력 저하 → 펌프 기동</span><b>영상 보기 ▶ <small>1분 41초</small></b></span></button><button type="button" class="be-type be-type-manual" data-education-play="manual"><img src="/media/education/hydrant-manual-v2.jpg" alt="ON 기동 버튼형 옥내소화전 교육" loading="lazy"><span class="be-type-copy"><span class="be-type-badge">기동 버튼</span><strong>ON 버튼을 누르는 방식</strong><span>방수 준비 → 기동 ON → 밸브 개방</span><b>영상 보기 ▶ <small>1분 55초</small></b></span></button></div><p class="be-type-note">방식을 모르겠다면 담당 매니저 또는 소방안전관리자에게 확인해 주세요. 버튼 유무·색상만으로 기동 방식을 단정하지 마세요.</p></article>
 <p class="be-note">시청만으로 교육·훈련 기록이 자동 작성되지는 않습니다.</p></section><section class="be-player" hidden aria-label="교육 영상"><button type="button" class="be-back">← 교육자료 목록</button><video controls playsinline preload="none" aria-label="교육 영상"></video><p class="be-video-error" role="alert" hidden>영상을 불러오지 못했습니다. <button type="button" class="be-retry">다시 불러오기</button></p><div class="be-video-info"><span class="be-category"></span><h3></h3><p></p><small>AI 제작 삽화와 합성 음성을 활용한 교육 영상입니다. 옥내소화전 영상은 일반 평호스형을 예로 설명합니다.</small></div></section></div>`;
 document.body.append(dialog);const q=s=>dialog.querySelector(s),video=q('video'),list=q('.be-list'),player=q('.be-player');let origin=null,lock=null,current='extinguisher',lastPlay=null;
 let facilitySequence=0;
 const typeNames={auto:'자동 기동 방식',manual:'ON·OFF 버튼 방식',both:'자동 기동 / ON·OFF 버튼 방식 함께 설치'};
 function displayFacilities(data){
  const modes=data.modes||[],rows=data.rows||[];
  q('.be-building-title').textContent=data.unconfirmed?'옥내소화전 기동 방식 확인이 필요합니다.':modes.length===1?'해당 건물은 '+typeNames[modes[0]]+'에 해당합니다.':modes.length===2?'해당 건물에는 두 가지 기동 방식이 설치되어 있습니다.':'옥내소화전이 없는 것으로 등록되어 있습니다.';
  q('.be-building-status').textContent=data.unconfirmed?'소방시설 현황에서 옥내소화전 설치 여부와 기동 방식을 확인하고 저장해 주세요.':modes.length?'소방시설 현황에 저장된 방식입니다. 아래에서 해당 영상을 재생하세요.':'현장과 다르다면 소방시설 현황을 수정해 주세요.';
  const box=q('.be-building-scopes');box.replaceChildren();
  rows.forEach(row=>{
   const line=document.createElement('div');line.className='be-scope-line';
   const label=document.createElement('strong');label.textContent=row.label;line.append(label);
   const text=document.createElement('span');text.textContent=typeNames[row.type]||(row.status==='yes'?'기동 방식 미설정':'설치 여부 미확인');line.append(text);
   const choices=row.type==='both'?['auto','manual']:['auto','manual'].includes(row.type)?[row.type]:[];
   choices.forEach(type=>{const button=document.createElement('button');button.type='button';button.textContent=(type==='auto'?'자동 기동':'ON·OFF')+' 영상 보기 ▶';button.onclick=()=>q('[data-education-play="'+type+'"]').onclick();line.append(button);});box.append(line);
  });
  dialog.querySelectorAll('.be-type').forEach(button=>{button.hidden=!modes.includes(button.dataset.educationPlay);});
  q('.be-type-grid').hidden=modes.length===0;q('.be-type-grid').classList.toggle('be-single-type',modes.length===1);
 }
 async function loadFacilities(){
  const sequence=++facilitySequence,controller=new AbortController(),timer=setTimeout(()=>controller.abort(),15000);
  q('.be-building-title').textContent='소방시설 현황을 확인하고 있습니다.';q('.be-building-status').textContent='';q('.be-building-scopes').replaceChildren();q('.be-type-grid').hidden=true;q('.be-setting-reload').hidden=true;
  try{
   if(!buildingUid)throw new Error('건물 정보를 확인할 수 없습니다. 화면을 새로 열어 주세요.');
   const response=await fetch('/building_education_api.php?'+new URLSearchParams({uid:buildingUid}),{credentials:'same-origin',cache:'no-store',signal:controller.signal});
   const data=await response.json();if(!response.ok)throw new Error(data.error||'소방시설 현황을 확인하지 못했습니다.');
   if(sequence===facilitySequence)displayFacilities(data);
  }catch(error){if(sequence===facilitySequence){q('.be-building-title').textContent='소방시설 현황을 확인하지 못했습니다.';q('.be-building-status').textContent=error.name==='AbortError'?'응답이 늦어지고 있습니다. 다시 확인해 주세요.':error.message;q('.be-setting-reload').hidden=false;}}
  finally{clearTimeout(timer);}
 }
 q('.be-setting-reload').onclick=loadFacilities;
 function lockScroll(){if(lock)return;const body=document.body,html=document.documentElement;lock={x:scrollX,y:scrollY,body:body.getAttribute('style'),overflow:html.style.getPropertyValue('overflow'),priority:html.style.getPropertyPriority('overflow')};const gap=Math.max(0,innerWidth-html.clientWidth);const pad=parseFloat(getComputedStyle(body).paddingRight)||0;Object.assign(body.style,{position:'fixed',top:-lock.y+'px',left:-lock.x+'px',width:'100%',boxSizing:'border-box',paddingRight:(pad+gap)+'px'});html.style.setProperty('overflow','hidden');}
 function unlockScroll(){if(!lock)return;const saved=lock;lock=null;const html=document.documentElement;saved.body===null?document.body.removeAttribute('style'):document.body.setAttribute('style',saved.body);saved.overflow?html.style.setProperty('overflow',saved.overflow,saved.priority):html.style.removeProperty('overflow');const behavior=html.style.getPropertyValue('scroll-behavior'),priority=html.style.getPropertyPriority('scroll-behavior');html.style.setProperty('scroll-behavior','auto','important');scrollTo(saved.x,saved.y);behavior?html.style.setProperty('scroll-behavior',behavior,priority):html.style.removeProperty('scroll-behavior');}

 function stop(){video.pause();video.removeAttribute('src');video.load();q('.be-video-error').hidden=true;}
 function showList(){stop();list.hidden=false;player.hidden=true;q('#be-title').textContent='소방교육 자료';}
 function play(){const item=media[current];if(!item)return;q('.be-video-error').hidden=true;video.src='/media/education/'+item.file+'.mp4';video.load();video.play().catch(()=>{});}
 triggers.forEach(button=>button.addEventListener('click',()=>{if(dialog.open)return;origin=button;showList();dialog.showModal();lockScroll();loadFacilities();q('.be-close').focus({preventScroll:true});}));
 dialog.querySelectorAll('[data-education-play]').forEach(button=>button.onclick=()=>{const key=button.dataset.educationPlay,item=media[key];if(!item)return;stop();current=key;lastPlay=button;list.hidden=true;player.hidden=false;q('#be-title').textContent=item.title;video.poster='/media/education/'+item.file+'.jpg';video.setAttribute('aria-label',item.title);player.setAttribute('aria-label',item.title);q('.be-video-info h3').textContent=item.title;q('.be-video-info .be-category').textContent=item.category+' · 한국어 음성 · 자막 포함';q('.be-video-info p').textContent=item.note;q('.be-back').focus({preventScroll:true});q('.be-body').scrollTop=0;dialog.scrollTop=0;play();});
 q('.be-back').onclick=()=>{showList();lastPlay?.focus({preventScroll:true});lastPlay?.scrollIntoView({block:'nearest'});};q('.be-retry').onclick=play;
 video.addEventListener('error',()=>{if(dialog.open&&!player.hidden&&video.getAttribute('src'))q('.be-video-error').hidden=false;});q('.be-close').onclick=()=>dialog.close();
 dialog.addEventListener('click',e=>{if(e.target!==dialog)return;const r=dialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)dialog.close();});
 dialog.addEventListener('close',()=>{facilitySequence++;showList();unlockScroll();if(origin?.isConnected)origin.focus({preventScroll:true});});
})();
