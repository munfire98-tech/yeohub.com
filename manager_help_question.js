(()=>{
'use strict';
const params=new URLSearchParams(location.search),token=params.get('help_review');
if(!token||window.parent===window)return;
let dirty=false,pending=0,version=0,discard=false;
const notify=type=>window.parent.postMessage({type,token},location.origin);
const originalFetch=window.fetch;
// Track only saves from this question, never background help-list polling.
window.fetch=async function(input,options){
 const url=new URL(typeof input==='string'?input:input.url,location.href);
 const isSave=url.origin===location.origin&&url.pathname===location.pathname&&(options?.method||input?.method||'GET').toUpperCase()==='POST';
 if(!isSave)return originalFetch.apply(this,arguments);
 pending++;const saveVersion=version;
 try{const response=await originalFetch.apply(this,arguments);let result=null;try{result=await response.clone().json();}catch(e){}
  if(response.ok&&result?.ok){if(saveVersion===version)dirty=false;setTimeout(()=>notify('manager-help-saved'),0);}else notify('manager-help-error');
  return response;
 }catch(e){notify('manager-help-error');throw e;}finally{pending--;}
};
window.managerHelpQuestion={state(){const original=window.fpModalState?.()||{};return {busy:pending>0||!!window.recordModalPending||!!window.chatPending||!!original.busy,dirty:dirty||!!window.recordModalDirty||!!window.chatUnsaved||!!original.dirty};},discard(){discard=true;}};
document.addEventListener('input',e=>{if(e.target.matches('input,textarea,select')&&!e.target.matches('#facility-search,#selected-only')){dirty=true;version++;}},true);
document.addEventListener('change',e=>{if(e.target.matches('input,textarea,select')&&!e.target.matches('#facility-search,#selected-only')){dirty=true;version++;}},true);
// Suppress the inner page's unload dialog only after the outer dialog confirmed leaving.
window.addEventListener('beforeunload',e=>{if(discard)e.stopImmediatePropagation();},true);
document.addEventListener('click',e=>{const a=e.target.closest('a[href]');if(a&&!a.getAttribute('href').startsWith('#')){e.preventDefault();e.stopImmediatePropagation();}},true);
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!document.querySelector('dialog[open]')){e.preventDefault();e.stopPropagation();notify('manager-help-close');}});
document.addEventListener('click',e=>{if(e.target.closest('#receiver-apply')){dirty=true;version++;}},true);
document.addEventListener('submit',e=>{if(e.target.id==='facility-form'){dirty=false;pending++;}},true);
})();
