(()=>{
'use strict';
if(window.managerHelpReview)return;
const css=document.createElement('link');css.rel='stylesheet';css.href='/manager_help_review.css?v=20261007-1';document.head.append(css);
const el=(tag,cls,text)=>{const n=document.createElement(tag);if(cls)n.className=cls;if(text!==undefined)n.textContent=text;return n;};
const dialog=el('dialog','mhr-dialog');dialog.setAttribute('aria-labelledby','mhr-title');
dialog.innerHTML='<div class="mhr-head"><div><span class="mhr-eyebrow">담당 유저 · 작성 문의</span><h2 id="mhr-title">요청한 질문에 답변하기</h2><p class="mhr-building"></p></div><button type="button" class="mhr-close" aria-label="작성 문의 닫기" autofocus>닫기 <span aria-hidden="true">×</span></button></div><div class="mhr-body"><aside class="mhr-side"><div class="mhr-list-head"><strong>남은 요청 <span class="mhr-count">0</span></strong><button type="button" class="mhr-refresh" aria-label="요청 목록 새로고침">↻</button></div><div class="mhr-list" aria-label="작성 문의 목록"></div><p class="mhr-note">저장한 답변은 유저의 기록에<br>바로 반영됩니다.</p></aside><section class="mhr-editor"><div class="mhr-editor-head"><span class="mhr-category"></span><span class="mhr-guide">저장하면 다음 요청으로 이동해요</span></div><div class="mhr-content"></div></section></div><p class="mhr-status" role="status" aria-live="polite"></p>';
document.body.append(dialog);
const list=dialog.querySelector('.mhr-list'),content=dialog.querySelector('.mhr-content'),status=dialog.querySelector('.mhr-status');
const closeButton=dialog.querySelector('.mhr-close'),refreshButton=dialog.querySelector('.mhr-refresh');
let uid='',csrf='',rows=[],current=null,frame=null,token='',loading=false,refreshing=false,refreshAgain=false,revision=0,trigger=null,savedBody=null,emptyShown=false;
function category(r){const f=r.field||'';return f==='__facilities'?'소방시설 현황':f.startsWith('__jw_')?'자위소방대 교육·훈련':f.startsWith('__tr_')?'소방훈련·교육':f.startsWith('__fp_')?'소방계획서':'기본정보';}
function question(r){return (r.text||'작성 문의').replace(/^(자위소방대 교육·훈련|소방훈련·교육|소방시설 현황)\s*[:：]\s*/,'');}
async function request(url,data){const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),20000);
 try{const options={credentials:'same-origin',cache:'no-store',signal:controller.signal};if(data){options.method='POST';options.body=new URLSearchParams({...data,csrf});}
 const response=await fetch(url,options);let result;try{result=await response.json();}catch(e){throw new Error('응답을 확인하지 못했습니다. 다시 시도해 주세요.');}
 if(!response.ok||!result.ok)throw new Error(result.error||'요청을 처리하지 못했습니다.');return result;
 }catch(e){if(e.name==='AbortError')throw new Error('응답이 늦습니다. 잠시 후 다시 시도해 주세요.');throw e;}finally{clearTimeout(timer);}}
function release(t){if(t)request('/manager_help_review.php',{action:'close',token:t}).catch(()=>{});}
function editorState(){try{return frame?.contentWindow?.managerHelpQuestion?.state()||{busy:false,dirty:false};}catch(e){return {busy:false,dirty:false};}}
function discardEditor(){try{frame?.contentWindow?.managerHelpQuestion?.discard();}catch(e){}}
function canLeave(continueLeaving){const s=editorState();if(s.busy){status.textContent='답변을 저장하고 있습니다. 잠시만 기다려 주세요.';return false;}
 if(s.dirty){
  status.replaceChildren(el('span',null,'저장하지 않은 답변이 있습니다.'));
  const keep=el('button','mhr-keep','계속 작성'),leave=el('button','mhr-discard','저장하지 않고 이동');keep.type=leave.type='button';
  keep.onclick=()=>{status.replaceChildren();frame?.focus();};leave.onclick=()=>{discardEditor();status.replaceChildren();continueLeaving();};status.append(keep,leave);keep.focus();return false;
 }
 discardEditor();return true;
}
function empty(title,description,retry){content.replaceChildren();const box=el('div','mhr-empty');box.append(el('span','mhr-empty-icon',retry?'!':'✓'),el('h3',null,title),el('p',null,description));if(retry){const b=el('button','mhr-retry','다시 열기');b.type='button';b.onclick=retry;box.append(b);}content.append(box);}
function renderList(){list.replaceChildren();dialog.querySelector('.mhr-count').textContent=rows.length;
 for(const row of rows){const b=el('button','mhr-item');b.type='button';b.setAttribute('aria-current',String(row.id===current?.id));b.disabled=loading;b.append(el('span','mhr-item-kind',category(row)),el('strong',null,question(row)),el('span','mhr-item-arrow','→'));b.onclick=()=>select(row);list.append(b);}
 if(!rows.length)list.append(el('p','mhr-list-empty','모두 확인했어요.'));
}
async function select(row,automatic=false){if(loading||(!automatic&&row.id===current?.id&&frame)||(!automatic&&!canLeave(()=>select(row,true))))return;
 const version=++revision;loading=true;emptyShown=false;current=row;status.textContent='질문을 불러오고 있습니다.';renderList();dialog.querySelector('.mhr-category').textContent=category(row);
 const oldToken=token;token='';frame=null;empty('질문을 준비하고 있어요','잠시만 기다려 주세요.');release(oldToken);
 try{const result=await request('/manager_help_review.php',{action:'open',uid,id:row.id});
  if(!dialog.open||version!==revision){release(result.token);return;}
  token=result.token;frame=el('iframe','mhr-frame');frame.title=category(row)+' · '+question(row);frame.setAttribute('referrerpolicy','same-origin');
  // Forms and scripts work inside the editor; links cannot navigate the manager screen.
  frame.setAttribute('sandbox','allow-scripts allow-same-origin allow-forms allow-modals');
  const openedFrame=frame;
  const loadTimeout=setTimeout(()=>{if(version!==revision||frame!==openedFrame||!loading)return;loading=false;frame=null;renderList();empty('질문을 불러오는 데 시간이 걸리고 있어요','다시 열기를 눌러 이어서 확인해 주세요.',()=>select(row,true));status.textContent='질문 화면의 연결을 확인해 주세요.';},25000);
  frame.onload=()=>{clearTimeout(loadTimeout);if(version!==revision||frame!==openedFrame||!dialog.open)return;loading=false;renderList();
   let ready=false;try{ready=!!frame.contentWindow.managerHelpQuestion;}catch(e){}
   if(!ready){frame=null;status.textContent='연결 또는 이용 권한을 확인하고 다시 열어 주세요.';empty('질문 화면을 열지 못했습니다',status.textContent,()=>select(row,true));return;}
   status.textContent='';refresh();};
  frame.src=result.url;content.replaceChildren(frame);
 }catch(e){if(version!==revision||!dialog.open)return;loading=false;renderList();status.textContent=e.message;empty('질문을 열지 못했습니다',e.message,()=>select(row,true));}
}
async function refresh(){if(!dialog.open)return;if(refreshing){refreshAgain=true;return;}
 const target=uid;refreshing=true;refreshButton.disabled=true;
 try{const result=await request('/manager_help.php?uid='+encodeURIComponent(target));if(!dialog.open||uid!==target)return;
  csrf=result.csrf;rows=result.rows.filter(r=>r.uid===uid&&r.status==='pending'&&r.connection_active);renderList();
  const building=result.rows.find(r=>r.uid===uid);if(building)dialog.querySelector('.mhr-building').textContent=building.building_name||building.name;
  if(loading)return;
  const s=editorState();
  if(current&&!rows.some(r=>r.id===current.id)&&(s.busy||s.dirty)){status.textContent='이 요청의 처리 상태가 바뀌었습니다. 작성 중인 내용을 확인해 주세요.';return;}
  if((!current&&(rows.length||!emptyShown))||(current&&!rows.some(r=>r.id===current.id))){
   const wasCurrent=!!current;release(token);token='';frame=null;current=null;
   if(rows.length){select(rows[0],true);if(wasCurrent)status.textContent='답변을 저장했습니다. 다음 요청을 여는 중입니다.';}
   else{emptyShown=true;dialog.querySelector('.mhr-category').textContent='작성 완료';empty('요청한 항목을 모두 확인했어요','저장된 답변은 유저의 기록에도 반영됩니다.');status.textContent='남은 작성 문의이 없습니다.';closeButton.focus();}
   window.managerHelp?.refresh();
  }
 }catch(e){if(dialog.open&&uid===target){status.textContent=e.message;if(!frame)empty('요청 목록을 불러오지 못했습니다',e.message,refresh);}}
 finally{refreshing=false;refreshButton.disabled=false;if(refreshAgain){refreshAgain=false;refresh();}}
}
function lock(){const body=document.body;savedBody={x:scrollX,y:scrollY,style:body.getAttribute('style')};body.style.position='fixed';body.style.top=-savedBody.y+'px';body.style.left=-savedBody.x+'px';body.style.width='100%';body.style.overflow='hidden';}
function unlock(){if(!savedBody)return;const saved=savedBody;savedBody=null;if(saved.style===null)document.body.removeAttribute('style');else document.body.setAttribute('style',saved.style);window.scrollTo({left:saved.x,top:saved.y,behavior:'instant'});trigger?.focus({preventScroll:true});}
function close(force=false){if(!force&&!canLeave(()=>close(true)))return;++revision;release(token);token='';current=null;frame=null;loading=false;content.replaceChildren();dialog.close();unlock();window.managerHelp?.refresh();}
closeButton.onclick=()=>close();refreshButton.onclick=refresh;
dialog.addEventListener('cancel',e=>{e.preventDefault();close();});
let outside=false;dialog.addEventListener('pointerdown',e=>{outside=e.target===dialog;});dialog.addEventListener('click',e=>{if(outside&&e.target===dialog)close();outside=false;});
window.addEventListener('message',e=>{if(e.origin!==location.origin||e.source!==frame?.contentWindow||e.data?.token!==token)return;
 if(e.data?.type==='manager-help-saved'){status.textContent='답변이 저장되었습니다.';refresh();}
 if(e.data?.type==='manager-help-close')close();
 if(e.data?.type==='manager-help-error')status.textContent='저장하지 못했습니다. 답변을 확인하고 다시 저장해 주세요.';
});
window.addEventListener('beforeunload',e=>{if(dialog.open){const s=editorState();if(s.busy||s.dirty){e.preventDefault();e.returnValue='';}}});
document.addEventListener('manager-help-updated',()=>{if(dialog.open&&!loading)refresh();});
window.managerHelpReview={open(target,button){if(dialog.open){if(uid===target){closeButton.focus();return;}if(!canLeave(()=>{close(true);window.managerHelpReview.open(target,button);}))return;close(true);}
 uid=target;csrf='';emptyShown=false;rows=[];current=null;frame=null;token='';loading=false;trigger=button||document.activeElement;revision++;
 dialog.querySelector('.mhr-building').textContent='건물의 요청을 확인하고 있습니다.';dialog.querySelector('.mhr-category').textContent='작성 문의';status.textContent='';renderList();empty('요청 목록을 불러오고 있어요','요청한 질문만 모아서 보여드릴게요.');lock();dialog.showModal();closeButton.focus();refresh();
}};
})();
