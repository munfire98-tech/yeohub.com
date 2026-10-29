(()=>{'use strict';
const script=document.currentScript,dialog=document.getElementById('dong-name-dialog');if(!dialog)return;
const input=dialog.querySelector('input'),feedback=dialog.querySelector('[role=status]'),save=dialog.querySelector('[data-dong-save]');let revision=script.dataset.revision,current=null,busy=false;
document.querySelectorAll('[data-dong-key]').forEach(button=>button.addEventListener('click',()=>{current=button;input.value=button.dataset.dongAlias||'';input.placeholder=button.dataset.dongDefault||'예: 본관';feedback.textContent='';dialog.showModal();input.focus();}));
dialog.querySelector('[data-dong-cancel]').onclick=()=>{if(!busy)dialog.close();};dialog.addEventListener('cancel',e=>{if(busy)e.preventDefault();});dialog.addEventListener('close',()=>{input.value='';current?.focus();});
save.onclick=async()=>{if(busy||!current)return;busy=true;save.disabled=true;feedback.textContent='이름을 저장하고 있습니다…';
 try{
  const body=new FormData();body.set('action','rename_dong');body.set('csrf',script.dataset.csrf);body.set('dong_key',current.dataset.dongKey);body.set('dong_name',input.value);body.set('dong_revision',revision);
  const response=await fetch(location.href,{method:'POST',body,credentials:'same-origin',cache:'no-store'}),data=await response.json();if(!response.ok||!data.ok)throw Error(data.error||'이름을 저장하지 못했습니다.');
  revision=data.revision;
  document.querySelectorAll('[data-dong-label]').forEach(el=>{const label=data.labels[el.dataset.dongLabel];if(label)el.textContent=label;});
  document.querySelectorAll('[data-scope-tab]').forEach(el=>{const label=data.labels[el.dataset.scopeTab];if(label)el.textContent=label;});
  document.querySelectorAll('[data-dong-key]').forEach(el=>{el.dataset.dongAlias=data.aliases[el.dataset.dongKey]||'';});
  dialog.close();
 }catch(e){feedback.textContent=e.message||'이름을 저장하지 못했습니다.';}finally{busy=false;save.disabled=false;}
};
})();
