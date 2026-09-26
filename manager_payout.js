(()=>{
 const root=document.getElementById('manager-payout');if(!root)return;
 const account=root.querySelector('[data-account-form]'),request=root.querySelector('[data-payout-form]'),status=root.querySelector('[data-payout-status]'),saved=root.querySelector('[data-account-saved]'),history=root.querySelector('[data-payout-history]');let token='',busy=false,rate=1500;
 function newToken(){const a=new Uint8Array(20);crypto.getRandomValues(a);return Array.from(a,x=>x.toString(16).padStart(2,'0')).join('');}
 token=newToken();const money=n=>Number(n).toLocaleString('ko-KR');
 function render(d){rate=Number(d.rate)||1500;root.querySelector('[data-payout-amount]').textContent=money(Number(request.elements.coins.value)*rate)+'원';const b=d.balance;const headline=document.querySelector('.manager-header-wallet strong');if(headline){headline.replaceChildren(document.createTextNode(money(b.available)));const unit=document.createElement('small');unit.textContent='개';headline.append(unit);}const wallet=document.querySelector('#manager-wallet-dialog .ms-wallet>strong');if(wallet){wallet.replaceChildren(document.createTextNode(money(b.available)));const unit=document.createElement('small');unit.textContent='개';wallet.append(unit);}root.querySelector('[data-payout-balance]').textContent=`출금 가능 ${money(b.available)}개 · 신청 중 ${money(b.pending)}개 · 지급 완료 ${money(b.paid)}개`;
 root.querySelector('[data-payout-deficit]').textContent=b.deficit?'환불로 코인 정산 확인이 필요합니다. 관리자에게 문의해 주세요.':'';
 saved.textContent=d.account?`${d.account.bank} · ${d.account.number} · ${d.account.holder}`:'등록된 계좌가 없습니다.';
 request.elements.coins.max=b.available;request.querySelector('button').disabled=!d.account||b.available<1;
 const plans=root.querySelector('[data-reward-plans]');if(plans){plans.replaceChildren();for(const plan of d.reward_plans||[]){const item=document.createElement('p');item.className='payout-record';item.textContent=`${plan.user} · ${plan.issued}/12개 지급 · ${plan.stopped?'지급 중단':plan.next_at?'다음 지급 '+plan.next_at:'지급 완료'}`;plans.append(item);}if(!plans.childElementCount)plans.textContent='실결제 완료 후 월별 리워드가 시작됩니다.';}
 history.replaceChildren();for(const r of d.requests){const item=document.createElement('p');item.className='payout-record';item.textContent=`${r.at.slice(0,10)} · ${money(r.coins)}개 / ${money(r.amount)}원 · ${{pending:'신청 대기',paid:'지급 완료',rejected:'반려'}[r.status]}${r.note?' — '+r.note:''}`;history.append(item);}if(!d.requests.length)history.textContent='출금 신청 내역이 없습니다.';
 }
 async function call(form){if(busy)return;busy=true;root.querySelectorAll('button').forEach(b=>b.disabled=true);status.textContent='확인 중…';
 try{const opts={credentials:'same-origin',cache:'no-store'};if(form){opts.method='POST';opts.body=new FormData(form);opts.body.set('csrf',typeof CSRF==='string'?CSRF:'');if(form===request)opts.body.set('token',token);}
 const response=await fetch('/manager_payout.php',opts),d=await response.json();if(!response.ok||!d.ok)throw Error(d.error||'처리하지 못했습니다. 다시 시도해 주세요.');root.querySelectorAll('button').forEach(b=>b.disabled=false);render(d);
 if(form===account)account.reset();if(form===request)token=newToken();status.textContent=form?'저장되었습니다. 출금 신청은 관리자가 확인 후 직접 송금합니다.':'';
 }catch(e){status.textContent=e.message;root.querySelectorAll('button').forEach(b=>b.disabled=false);}finally{busy=false;}}
 account.addEventListener('submit',e=>{e.preventDefault();call(account);});request.addEventListener('submit',e=>{e.preventDefault();if(confirm(`${money(request.elements.coins.value)}코인, ${money(Number(request.elements.coins.value)*rate)}원 출금을 신청할까요?`))call(request);});
 request.elements.coins.addEventListener('input',()=>root.querySelector('[data-payout-amount]').textContent=money(Number(request.elements.coins.value)*rate)+'원');document.querySelector('[data-wallet-open]')?.addEventListener('click',()=>call());
})();
