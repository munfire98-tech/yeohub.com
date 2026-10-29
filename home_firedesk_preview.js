(()=>{'use strict';
const syncScroll=()=>document.documentElement.classList.toggle('home-example-modal-open',!!document.querySelector('#homeSafetyPreviewDialog[open],#homePartnerPreviewDialog[open],#homeMobilePreviewDialog[open],#homePartnerMobilePreviewDialog[open]'));
function connect(dialogId,frameId,openId,closeId){
 const dialog=document.getElementById(dialogId),frame=document.getElementById(frameId),open=document.getElementById(openId),close=document.getElementById(closeId);if(!dialog||!frame||!open||!close)return;
 open.addEventListener('click',()=>{if(!frame.getAttribute('src'))frame.src=frame.dataset.src;if(!dialog.open){dialog.showModal();syncScroll();close.focus();}});
 close.addEventListener('click',()=>dialog.close());dialog.addEventListener('close',()=>{syncScroll();open.focus({preventScroll:true});});
 dialog.addEventListener('click',e=>{if(e.target!==dialog)return;const b=dialog.getBoundingClientRect();if(e.clientX<b.left||e.clientX>b.right||e.clientY<b.top||e.clientY>b.bottom)dialog.close();});
}
connect('homeSafetyPreviewDialog','homeFireDeskFrame','homeSafetyPreviewOpen','homeSafetyPreviewClose');
connect('homePartnerPreviewDialog','homePartnerPreviewFrame','homePartnerPreviewOpen','homePartnerPreviewClose');
connect('homePartnerMobilePreviewDialog','homePartnerMobilePreviewFrame','homePartnerMobilePreviewOpen','homePartnerMobilePreviewClose');
connect('homeMobilePreviewDialog','homeFireDeskMobileFrame','homeMobilePreviewOpen','homeMobilePreviewClose');
})();
