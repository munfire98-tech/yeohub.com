(()=>{
  document.querySelectorAll('[data-manager-form]').forEach(form=>{
    const input=form.querySelector('[name="manager_code"]');
    input?.addEventListener('input',()=>{input.value=input.value.toUpperCase();});
    input?.addEventListener('paste',event=>{
      const text=event.clipboardData?.getData('text')?.trim().toUpperCase();
      if(text&&/^FM-[0-9A-F]{10}$/.test(text)){event.preventDefault();input.value=text;}
    });
    form.addEventListener('submit',()=>{
      const button=form.querySelector('[type="submit"]');
      if(button){button.disabled=true;button.textContent='요청을 보내는 중…';}
    });
  });
  window.addEventListener('pageshow',()=>document.querySelectorAll('[data-manager-form] button[type="submit"]').forEach(button=>{
    button.disabled=false;button.textContent='연결 요청 →';
  }));
  const copy=document.querySelector('[data-copy-code]');
  copy?.addEventListener('click',async()=>{
    const code=document.getElementById('my-manager-code')?.textContent?.trim()||'';
    const result=document.getElementById('copy-result');
    try{await navigator.clipboard.writeText(code);result.textContent='코드를 복사했습니다.';}
    catch{result.textContent='코드를 길게 누르거나 선택해서 복사해 주세요.';}
  });
})();
