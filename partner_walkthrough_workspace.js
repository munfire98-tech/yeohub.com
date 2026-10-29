/* Synthetic rows for the existing walkthrough; never loaded by the real center. */
(()=>{
 if(!location.pathname.startsWith('/partner_demo_app.php/')||!document.body.hasAttribute('data-tour-result-page'))return;
 const map=document.querySelector('[data-tour-mini-map]'),name=document.querySelector('.pwt-saved-card strong').textContent;
 window.partnerWalkthroughBuildings=Array.from({length:40},(_,i)=>({uid:'demo_visit_'+(i+1),demo:true,name:i===0?name:i===1?'한빛 물류센터':['다온','한울','해솔','가람','푸른'][i%5]+' '+['빌딩','공장','상가','센터'][i%4]+' '+(i+1),preregistered:i<16,subscription:{active:i>=28},total_area:i===0?2400:i===1?28640:1250+(i*1379%14500),building_use:['제2종근린생활시설','공장','업무시설','교육연구시설'][i%4],address:i===0?document.querySelector('.pwt-saved-card p').textContent:'경기도 파주시 예시로 '+(i+1),building_tel:'010-0000-0000',lat:i===0?Number(map.dataset.lat):37.76+Math.sin(i*2.399)*(.018+(i%5)*.004),lng:i===0?Number(map.dataset.lng):126.77+Math.cos(i*2.399)*(.025+(i%7)*.011),approval_month:i%8===0?new Date().getMonth()+1:i%8===4?(new Date().getMonth()+6)%12+1:null}));
 // Spread the ten highlighted examples so the labels remain legible on a phone.
 window.partnerWalkthroughBuildings.forEach((row,i)=>{if(i>0&&i%4===0){const n=i/4;row.lat=37.70+n*.016;row.lng=126.69+(n%2)*.13;}});
 document.addEventListener('DOMContentLoaded',()=>{
  document.dispatchEvent(new CustomEvent('manager-buildings-updated',{detail:{buildings:window.partnerWalkthroughBuildings}}));
  document.dispatchEvent(new CustomEvent('manager-building-locations',{detail:{points:window.partnerWalkthroughBuildings}}));
 });
})();
