(()=>{'use strict';
const search=document.getElementById('manager-search');if(!search)return;
const filter=document.getElementById('manager-filter'),sort=document.getElementById('manager-sort'),body=document.getElementById('manager-table'),managers=[...body.querySelectorAll('[data-manager-row]')];
function applyManagers(){const q=search.value.trim().toLocaleLowerCase();let n=0;managers.sort((a,b)=>sort.value==='order'?+a.dataset.order-+b.dataset.order:+b.dataset[sort.value]-+a.dataset[sort.value]);for(const row of managers){const d=row.dataset,f=filter.value;row.hidden=!d.search.toLocaleLowerCase().includes(q)||!(f==='all'||(f==='pending'&&+d.pending>0)||(f==='pro'&&+d.pro>0)||(f==='balance'&&+d.available>0)||(f==='deficit'&&+d.deficit>0));body.append(row);if(!row.hidden)n++;}document.getElementById('manager-count').textContent=n+'명 / 전체 '+managers.length+'명';document.getElementById('manager-empty').hidden=n>0;}
search.addEventListener('input',applyManagers);filter.addEventListener('change',applyManagers);sort.addEventListener('change',applyManagers);applyManagers();
const ps=document.getElementById('payout-search'),pf=document.getElementById('payout-filter'),payouts=[...document.querySelectorAll('[data-payout-row]')];let exact=null;
function applyPayouts(){let n=0;const q=ps.value.trim().toLocaleLowerCase();for(const row of payouts){row.hidden=!(pf.value==='all'||pf.value===row.dataset.status)||!row.dataset.search.toLocaleLowerCase().includes(q)||(exact!==null&&row.dataset.manager!==exact);if(!row.hidden)n++;}document.getElementById('payout-result').textContent=(exact?exact+' · ':'')+n+'건의 신청';document.getElementById('payout-empty').hidden=n>0;}
ps.addEventListener('input',()=>{exact=null;applyPayouts();});pf.addEventListener('change',applyPayouts);document.getElementById('payout-reset').addEventListener('click',()=>{exact=null;ps.value='';pf.value='pending';applyPayouts();});
document.querySelectorAll('[data-manager-payouts]').forEach(btn=>btn.addEventListener('click',()=>{exact=btn.dataset.managerPayouts;ps.value='';pf.value='all';applyPayouts();document.getElementById('payout-section').scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'start'});}));
const requestedManager=new URLSearchParams(location.search).get('manager');
if(requestedManager&&[...document.querySelectorAll('[data-manager-payouts]')].some(btn=>btn.dataset.managerPayouts===requestedManager)){exact=requestedManager;pf.value='all';}
applyPayouts();
})();
