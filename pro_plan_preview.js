(()=>{'use strict';
const button=document.querySelector('[data-pro-plan-preview]');if(!button)return;
button.addEventListener('click',()=>{
 // The PRO dialog owns a second frame so card/payment state survives previewing.
 if(window.parent!==window){try{if(typeof window.parent.openProPlanPreview==='function'){window.parent.openProPlanPreview();return;}}catch{}}
 const prior=document.activeElement,dialog=document.createElement('dialog');dialog.className='pps-dialog';dialog.setAttribute('aria-label','소방계획서 완성 예시');
 dialog.innerHTML='<header><span>소방계획서 완성 예시</span><button type="button">안내로 돌아가기</button></header><iframe title="가상 건물 소방계획서 출력 예시" src="/fire_plan_sample.html" sandbox="allow-scripts allow-same-origin allow-modals" referrerpolicy="same-origin"></iframe>';
 dialog.querySelector('button').onclick=()=>dialog.close();dialog.addEventListener('close',()=>{dialog.remove();prior?.focus();},{once:true});document.body.append(dialog);dialog.showModal();dialog.querySelector('button').focus();
});
})();
