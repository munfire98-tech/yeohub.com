(()=>{
'use strict';

const names=['햇살빌딩','푸른상가','늘봄센터','한빛공장','다온타워','새솔프라자','온유센터','나래상가'];
const month=new Date().getMonth()+1;
window.partnerWalkthroughBuildings=Array.from({length:40},(_,i)=>({uid:'demo_visit_'+(i+1),demo:true,featuredDemo:i===0,name:i===0?'소방계획서.com 빌딩':names[i%names.length]+' '+(i+1),address:'경기도 '+['파주시','파주시','파주시','김포시','고양시'][i%5]+' 예시로 '+(i+1),lat:37.65+Math.floor(i/7)*.014+(i%3)*.0021,lng:126.73+(i%7)*.013+(i%2)*.0017,approval_month:i%4===0?month:(i%12)+1,approval_date:'2020-'+String(i%4===0?month:(i%12)+1).padStart(2,'0')+'-15',preregistered:i%3===0,subscription:{active:i%3===2,test:true},total_area:i===0?37460:1200+i*137,area_missing_dongs:0,building_use:['공장','업무시설','교육연구시설','제2종근린생활시설'][i%4],representative:'예시 대표',building_tel:i%13===0?'':'031-000-0000',safety_managers:[]}));
const iso=d=>`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
window.partnerExampleVisits=window.partnerWalkthroughBuildings.slice(0,8).map((b,i)=>{const d=new Date();d.setDate(Math.min(28,Math.max(1,d.getDate()+i-2)));return {id:'example_visit_'+i,revision:1,uid:b.uid,name:b.name,address:b.address,date:iso(d),time:'09:00',status:i<2?'completed':'planned',visited_date:i<2?iso(d):'',memo:'예시 방문일정'};});
const showExampleNotice=()=>{const d=document.getElementById('pe-message');if(!d.open)d.showModal();};
const realFetch=window.fetch.bind(window);
window.fetch=async(input,options={})=>{
 const u=new URL(input instanceof Request?input.url:input,location.href);
 const method=(options.method||(input instanceof Request?input.method:'GET')).toUpperCase();
 const reply=data=>new Response(JSON.stringify(data),{status:200,headers:{'Content-Type':'application/json'}});
 if(u.origin===location.origin&&u.pathname==='/manager_buildings.php'&&method==='GET')return reply({ok:true,buildings:window.partnerWalkthroughBuildings});
 if(u.pathname==='/partner_company.php'&&method==='GET')return reply({ok:true,company:'예시소방안전',mode:'company'});
 if(u.pathname==='/manager_help.php'&&method==='GET')return reply({ok:true,items:[],unread:0});
 if(u.origin!==location.origin&&u.hostname==='tile.openstreetmap.org'&&method==='GET')return realFetch(input,options);
 return reply({ok:false,error:'예시에서는 실제 연결·알림·저장을 실행하지 않습니다.'});
};
window.open=()=>{showExampleNotice();return null;};
document.addEventListener('submit',e=>{if(e.target.closest('.mvc-dialog'))return;e.preventDefault();showExampleNotice();},true);
document.addEventListener('click',e=>{const a=e.target.closest('a[href]');if(a){e.preventDefault();e.stopImmediatePropagation();showExampleNotice();}const b=e.target.closest('[data-partner-demo-open]');if(b){const d=document.getElementById('pe-building'),frame=d.querySelector('iframe');if(!frame.src)frame.src='/firedesk_example.php/FireDesk.php';if(!d.open)d.showModal();}},true);

window.partnerBasemap={add(target){return L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,referrerPolicy:'strict-origin-when-cross-origin',attribution:'&copy; OpenStreetMap contributors'}).addTo(target);}};
try{navigator.sendBeacon=()=>false;}catch(e){}
window.partnerExampleReportPayload=m=>{
 const dates=[4,12,20,26], reports=dates.map((day,i)=>({visit_id:'example_visit_'+i,uid:window.partnerWalkthroughBuildings[i].uid,name:window.partnerWalkthroughBuildings[i].name,date:m+'-'+String(i===0?3:day).padStart(2,'0'),deadline:m+'-'+String(day).padStart(2,'0'),status:i===0?'completed':'planned',received_date:i===0?m+'-03':'',calendar_ready:true,basis_type:'planned',basis:m+'-01',basis_date:m+'-01',revision:1}));
 const r=reports[0],f={visit_id:r.visit_id,uid:r.uid,name:r.name,received_date:r.received_date,work_period:'10',include_received:true,work_business_only:true,filing_basis_type:'planned',report_revision:1,work_due:m+'-16',filing_due:m+'-'+Math.min(30,new Date(Number(m.slice(0,4)),Number(m.slice(5)),0).getDate()),status:'planned',calendar_ready:true,needs_review:false,revision:1};
 return {reports,linked_reports:Object.fromEntries(reports.map(r=>[r.visit_id,r])),followups:{[r.visit_id]:f},followup_events:[{id:'work_'+r.visit_id,visit_id:r.visit_id,uid:r.uid,name:r.name,kind:'work',date:f.work_due,status:'planned',calendar_ready:true},{id:'filing_'+r.visit_id,visit_id:r.visit_id,uid:r.uid,name:r.name,kind:'filing',date:f.filing_due,status:'planned',calendar_ready:true}]};
};
document.addEventListener('DOMContentLoaded',()=>{
 document.querySelectorAll('.mvc-follow-form input,.mvc-follow-form select').forEach(n=>n.disabled=true);
 document.querySelectorAll('[data-visit-inbox-button],.pd-guide-open,.pd-auto-open,[data-partner-tour-open]').forEach(b=>b.addEventListener('click',showExampleNotice));
 document.body.insertAdjacentHTML('beforeend','<dialog id="pe-message"><h2>예시 체험 안내</h2><p>가상 거래처로 지도·요약·업무모드를 체험하고 있습니다. 실제 연결·알림 신청·결제는 실행하지 않습니다.</p><button type="button" onclick="this.closest(\'dialog\').close()">확인</button></dialog><dialog id="pe-building"><header><strong>연결된 건물 화면 · 예시</strong><button type="button" onclick="this.closest(\'dialog\').close()">닫기 ×</button></header><iframe title="가상 건물 관리 화면" sandbox="allow-scripts allow-same-origin allow-modals"></iframe></dialog>');
});

})();
