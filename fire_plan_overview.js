(()=>{'use strict';
let submitting=false;
document.querySelectorAll('form[method="post"]').forEach(form=>form.addEventListener('submit',event=>{
 if(event.defaultPrevented)return;
 if(submitting){event.preventDefault();return;}
 submitting=true;window.recordModalPending=true;
 const button=event.submitter;
 if(button){button.disabled=true;button.textContent='저장하고 있어요…';}
}));
window.addEventListener('pageshow',event=>{if(event.persisted)location.reload();});
if(new URLSearchParams(location.search).has('saved')||new URLSearchParams(location.search).has('reviewed')){
 try{if('BroadcastChannel'in window){const ch=new BroadcastChannel('manager-plan-changes');ch.postMessage('plan-saved');ch.close();}}catch(e){}
}
})();
