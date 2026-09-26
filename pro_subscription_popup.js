(()=>{
 let dialog,frame,prior,dirty=false;
 function open(){
  if(dialog){dialog.showModal();return;}
  prior=document.activeElement;dialog=document.createElement('dialog');dialog.className='pro-subscription-dialog';
  dialog.innerHTML='<header><div><small>SB.PLAN PRO</small><h2>PRO 이용 안내</h2></div><button type="button" aria-label="PRO 이용 안내 닫기">닫기</button></header><iframe title="PRO 카드 등록 및 구독 관리" src="/subscribe_page.php?embed=1&amp;pro_popup=1"></iframe>';
  frame=dialog.querySelector('iframe');dialog.querySelector('button').onclick=()=>dialog.close();
  dialog.addEventListener('close',()=>{dialog.remove();dialog=null;frame=null;prior?.focus();if(dirty)location.reload();});
  document.body.append(dialog);dialog.showModal();
 }
 window.openProSubscription=open;
 document.addEventListener('click',e=>{
  const link=e.target.closest('a[href]');if(!link||e.button!==0||e.ctrlKey||e.metaKey||e.shiftKey||e.altKey)return;
  const url=new URL(link.href,location.href);if(url.origin!==location.origin||url.pathname!=='/subscribe_page.php')return;
  e.preventDefault();e.stopImmediatePropagation();open();
 },true);
 window.addEventListener('message',e=>{if(e.origin!==location.origin||!frame||e.source!==frame.contentWindow)return;if(e.data?.type==='pro-subscription-updated')dirty=true;});
 if(new URL(location.href).searchParams.get('pro_popup')==='1'){
  const url=new URL(location.href);url.searchParams.delete('pro_popup');history.replaceState(null,'',url);open();
 }
})();
