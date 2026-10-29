(() => {
  'use strict';
  const prefix='/partner_demo_app.php/';
  const routes=new Set(['manager_draft.php','partner_walkthrough.php','FireDesk.php','building_setup.php','building_setup_chat.php','building_facilities.php','building_asset_view.php','work_log.php','work_log_form.php','work_log_print.php','work_log_setup_chat.php','fire_plan.php','fire_plan_new.php','fire_plan_edit.php','fire_plan_chat.php','fire_plan_jawi.php','fire_plan_print.php','fire_plan_book.php','fire_plan_schedule.php','jawi.php','jawi_edit.php','jawi_chat.php','jawi_print.php','train.php','train_edit.php','train_chat.php','train_print.php','evacuation_plan.php','evacuation_plan_chat.php','flame_retardant_api.php','false_alarm_api.php','false_alarm_print.php','print_all.php','manager_help.php','building_education_api.php','fire_manager_board.php']);
  function mapped(input){
    const url=new URL(input,location.href);
    if(url.origin!==location.origin)return null;
    const name=url.pathname.split('/').pop();
    if(!routes.has(name))return null;
    url.pathname=prefix+name;return url.href;
  }
  function notice(){
    let box=document.getElementById('pda-notice');
    if(!box){box=document.createElement('dialog');box.id='pda-notice';box.innerHTML='<h2>실제 연결 후 이용할 수 있어요</h2><p>이곳은 예시 건물의 업무를 작성해 보는 체험 공간입니다.<br>결제·공유·알림 발송·파일 업로드는 실행하지 않습니다.</p><button type="button">확인</button>';box.style.cssText='position:fixed;inset:0;margin:auto;padding:24px;border:1px solid #d8e3f2;border-radius:16px;width:min(400px,calc(100vw - 32px));max-height:80dvh;box-sizing:border-box;color:#293e59;background:white;font:14px/1.7 system-ui;box-shadow:0 20px 80px #19325440';box.querySelector('h2').style.cssText='font-size:17px;margin:0 0 10px';box.querySelector('button').style.cssText='border:0;border-radius:8px;background:#2865c4;color:white;padding:10px 22px;float:right;font:inherit;cursor:pointer';box.querySelector('button').onclick=()=>box.close();document.body.append(box);}
    if(!box.open)box.showModal();
  }
  // Route dynamic requests as well as the server-rendered links into the private app.
  const originalFetch=window.fetch.bind(window);
  window.fetch=(input,init)=>{
    const target=mapped(input instanceof Request?input.url:input);
    if(!target)return Promise.resolve(new Response(JSON.stringify({ok:false,error:'체험에서는 외부 조회·공유·알림 발송을 실행하지 않습니다.'}),{status:403,headers:{'Content-Type':'application/json'}}));
    return originalFetch(input instanceof Request?new Request(target,input):target,init);
  };
  const xhrOpen=XMLHttpRequest.prototype.open;
  XMLHttpRequest.prototype.open=function(method,url,...rest){const target=mapped(url);return xhrOpen.call(this,method,target||prefix+'unavailable.php',...rest);};
  const originalOpen=window.open.bind(window);
  window.open=(url,target,features)=>{
    if(url===''||url==='about:blank')return originalOpen(url,target,features);
    const next=mapped(url);if(!next){notice();return null;}return originalOpen(next,target,features);
  };
  document.addEventListener('click',event=>{
    const link=event.target.closest('a[href]');
    if(!link)return;
    const raw=link.getAttribute('href');if(!raw||raw.startsWith('#')||raw.startsWith('javascript:'))return;
    const next=mapped(raw);if(!next){event.preventDefault();event.stopImmediatePropagation();notice();}else link.href=next;
  },true);
  document.addEventListener('submit',event=>{
    const form=event.target,next=mapped(form.getAttribute('action')||location.href);
    if(!next||[...form.querySelectorAll('input[type=file]')].some(input=>input.files?.length)){event.preventDefault();event.stopImmediatePropagation();notice();}else form.setAttribute('action',next);
  },true);
  document.addEventListener('click',event=>{
    if(event.target.matches('input[type=file]')){event.preventDefault();notice();}
  },true);
  document.addEventListener('DOMContentLoaded',()=>{
    // The surrounding demo shell replaces account navigation, not the actual work UI.
    const style=document.createElement('style');style.textContent=`
      body>nav.nav,body>.site-footer{display:none!important}
      @media screen and (max-width:760px){
        body #buildingInfoDialog .bi-modal-head{padding:10px;gap:7px;font-size:12px}
        body #buildingInfoDialog .bi-modal-actions{flex-shrink:0;gap:5px}
        body #buildingInfoDialog .bi-modal-actions button{font-size:11px;padding:8px 7px;white-space:nowrap}
        body:has(#biForm) .savebar{padding:9px 10px;gap:6px}
        body:has(#biForm) .savebar .btn{min-width:0;flex:1;justify-content:center;font-size:12px;padding:10px 6px;white-space:nowrap}
      }
      @media print{body:after{content:"예시 건물 · 체험용 자료";position:fixed;bottom:4mm;right:5mm;color:#999;font:10px system-ui}}
    `;document.head.append(style);
    window.parent.postMessage({type:'partner-demo-ready'},location.origin);
  });
})();
