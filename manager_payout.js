(()=>{
 const root=document.getElementById('manager-payout');if(!root)return;
 const account=root.querySelector('[data-account-form]'),request=root.querySelector('[data-payout-form]'),status=root.querySelector('[data-payout-status]'),saved=root.querySelector('[data-account-saved]'),history=root.querySelector('[data-payout-history]');
 const amount=request.elements.mileage;if(!amount)return;
 let token='',busy=false,snapshot=null;
 const money=n=>Number(n||0).toLocaleString('ko-KR');
 function newToken(){const a=new Uint8Array(20);crypto.getRandomValues(a);return Array.from(a,x=>x.toString(16).padStart(2,'0')).join('');}
 token=newToken();
 function inputAmount(){root.querySelector('[data-payout-amount]').textContent=money(amount.value)+'원';}
 function buttons(){root.querySelectorAll('button').forEach(b=>b.disabled=busy);request.querySelector('button[type=submit]').disabled=busy||!snapshot?.account||snapshot.balance.available<1;}
 function render(d){
  snapshot=d;const b=d.balance;inputAmount();
  for(const wallet of document.querySelectorAll('.manager-header-wallet strong,#manager-wallet-dialog .ms-wallet>strong')){
   wallet.replaceChildren(document.createTextNode(money(b.available)));const unit=document.createElement('small');unit.textContent='마일리지';wallet.append(unit);
  }
  root.querySelector('[data-payout-balance]').textContent=`출금 가능 ${money(b.available)}마일리지 · 신청 중 ${money(b.pending)}마일리지 · 지급 완료 ${money(b.paid)}마일리지`;
  root.querySelector('[data-payout-deficit]').textContent=b.deficit?`환불로 ${money(b.deficit)}마일리지 정산 확인이 필요합니다. 관리자에게 문의해 주세요.`:'';
  saved.textContent=d.account?`${d.account.bank} · ${d.account.number} · ${d.account.holder}`:'등록된 계좌가 없습니다.';
  amount.max=b.available;
  const progress=document.querySelector('[data-starter-progress]');
  if(progress&&d.starter){
   progress.querySelector('strong').textContent=`${d.starter.reserved} / ${d.starter.limit}명`;
   progress.querySelector('[data-starter-note]').textContent=`혜택 배정 ${d.starter.reserved}명 · 첫 적립 이력 ${d.starter.rewarded}명 · 남은 자리 ${d.starter.remaining}명`;
   progress.querySelectorAll('[data-starter-dot]').forEach((dot,i)=>dot.classList.toggle('is-used',i<d.starter.reserved));
  }
  const plans=root.querySelector('[data-reward-plans]');plans.replaceChildren();
  for(const plan of d.reward_plans||[]){
   const item=document.createElement('p');item.className='payout-record';
   const title=document.createElement('strong');title.textContent=`${plan.user} · 누적 ${money(plan.mileage)}마일리지`;
   const detail=document.createElement('span');let next='이번 달 업무수행기록 완료 대기';
   if(plan.stopped)next='구독 · 연결 상태 확인 필요';
   else if(plan.current_issued)next='이번 달 적립 완료';
   else if(plan.reward_policy==='first-five-v1'&&!plan.first_issued)next=plan.facilities_ready?'첫 기록 저장 시 10,000마일리지':'시설 현황 저장 후 유저의 월 기록이 필요합니다';
   else if(plan.reward_policy==='first-five-v1')next='첫 구독 기간 · 월 기록 시 1,000마일리지';
   else next='월 기록 시 1,500마일리지';
   detail.textContent=next;item.append(title,detail);plans.append(item);
  }
  if(!plans.childElementCount)plans.textContent='아직 도움 기록이 없습니다. 구독한 사용자가 업무수행기록을 완료하면 사용자별 적립 현황이 표시됩니다.';
  history.replaceChildren();
  for(const r of d.requests){const item=document.createElement('p');item.className='payout-record';item.textContent=`${r.at.slice(0,10)} · ${money(r.mileage)}마일리지 → ${money(r.amount)}원 · ${{pending:'신청 대기',paid:'지급 완료',rejected:'반려'}[r.status]||'상태 확인'}${r.note?' — '+r.note:''}`;history.append(item);}
  if(!d.requests.length)history.textContent='출금 신청 내역이 없습니다.';
 }
 async function call(form){
  if(busy)return;busy=true;buttons();status.textContent='확인 중…';
  try{
   const opts={credentials:'same-origin',cache:'no-store'};
   if(form){opts.method='POST';opts.body=new FormData(form);opts.body.set('csrf',typeof CSRF==='string'?CSRF:'');if(form===request){opts.body.set('token',token);opts.body.set('unit','mileage-v1');}}
   const response=await fetch('/manager_payout.php',opts),d=await response.json();
   if(!response.ok||!d.ok)throw Error(d.error||'처리하지 못했습니다. 다시 시도해 주세요.');
   if(d.unit!=='mileage-v1')throw Error('마일리지 단위를 확인할 수 없습니다. 새로고침해 주세요.');
   render(d);if(form===account)account.reset();if(form===request)token=newToken();
   status.textContent=form?'저장되었습니다. 출금 신청은 관리자가 확인 후 직접 송금합니다.':'';
  }catch(e){status.textContent=e.message;}finally{busy=false;buttons();}
 }
 account.addEventListener('submit',e=>{e.preventDefault();call(account);});
 request.addEventListener('submit',e=>{e.preventDefault();if(!request.reportValidity())return;if(confirm(`${money(amount.value)}마일리지를 ${money(amount.value)}원으로 출금 신청할까요?`))call(request);});
 amount.addEventListener('input',inputAmount);
 root.querySelector('[data-payout-all]')?.addEventListener('click',()=>{if(snapshot){amount.value=String(snapshot.balance.available);inputAmount();}});
 document.querySelectorAll('[data-wallet-open]').forEach(button=>button.addEventListener('click',()=>call()));
})();
