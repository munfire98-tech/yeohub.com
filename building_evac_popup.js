(()=>{
 const links=document.querySelectorAll('[data-evac-editor]');if(!links.length)return;
 const dialog=document.createElement('dialog');dialog.className='bes-dialog';dialog.setAttribute('aria-labelledby','bes-title');
 dialog.innerHTML='<header><div><small>EVACUATION STUDIO</small><h2 id="bes-title">우리 건물 피난 시뮬레이션</h2><p>건물 설정 → 도면 편집 → 시뮬레이션</p></div><button type="button" aria-label="피난 시뮬레이션 닫기">닫기 ×</button></header><p class="bes-loading" role="status">시뮬레이션을 열고 있습니다…</p><iframe title="건물 설정 및 피난 시뮬레이션"></iframe>';
 document.body.append(dialog);const frame=dialog.querySelector('iframe'),close=dialog.querySelector('button'),status=dialog.querySelector('.bes-loading');let trigger=null,closing=false,overflow='';
 links.forEach(link=>link.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();if(dialog.open)return;trigger=link;overflow=document.documentElement.style.overflow;document.documentElement.style.overflow='hidden';status.hidden=false;status.textContent='시뮬레이션을 열고 있습니다…';frame.src=link.href;dialog.showModal();close.focus();}));
 frame.addEventListener('load',()=>{status.hidden=true;});
 async function finish(){
  if(closing)return;closing=true;close.disabled=true;close.textContent='저장 확인 중…';
  try{const flush=frame.contentWindow?.buildingEvacFlush;if(flush&&!(await flush())&&!confirm('저장에 실패했습니다. 저장하지 않은 내용을 버리고 닫을까요?'))return;dialog.close();}
  catch{status.textContent='저장 상태를 확인하지 못했습니다. 잠시 후 다시 닫아 주세요.';status.hidden=false;}
  finally{closing=false;close.disabled=false;close.textContent='닫기 ×';}
 }
 window.addEventListener('message',e=>{if(e.origin===location.origin&&e.source===frame.contentWindow&&e.data?.type==='building-evac-close')finish();});
 close.onclick=finish;dialog.addEventListener('cancel',e=>{e.preventDefault();finish();});
 dialog.addEventListener('close',()=>{frame.src='about:blank';document.documentElement.style.overflow=overflow;trigger?.focus({preventScroll:true});});
})();
