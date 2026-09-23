(function(){
'use strict';
const script=document.currentScript;
const asmEl=document.getElementById('asmMap'),routeEl=document.getElementById('routeMap');
const latInput=document.getElementById('asmLat'),lngInput=document.getElementById('asmLng'),nameInput=document.getElementById('assemblyName'),routeInput=document.getElementById('fireEngineRoute');
function valid(lat,lng){return String(lat??'').trim()!==''&&String(lng??'').trim()!==''&&Number.isFinite(Number(lat))&&Number.isFinite(Number(lng))&&Math.abs(Number(lat))<=90&&Math.abs(Number(lng))<=180;}
function dirty(){window.buildingInfoDirty=true;}
function fail(){[asmEl,routeEl].forEach(el=>{el.textContent='지도를 불러오지 못했습니다. 새로고침하거나 지도 키 설정을 확인해 주세요.';});}
if(!window.kakao?.maps){fail();return;}
kakao.maps.load(function(){
 const center=new kakao.maps.LatLng(Number(script.dataset.centerLat),Number(script.dataset.centerLng));
 asmEl.replaceChildren();routeEl.replaceChildren();
 const hasPoint=valid(latInput.value,lngInput.value);
 const asmCenter=hasPoint?new kakao.maps.LatLng(Number(latInput.value),Number(lngInput.value)):center;
 const asmMap=new kakao.maps.Map(asmEl,{center:asmCenter,level:3});
 const routeMap=new kakao.maps.Map(routeEl,{center,level:3});
 let marker=hasPoint?new kakao.maps.Marker({map:asmMap,position:asmCenter}):null;
 const prompt=document.getElementById('assemblyPickPrompt');if(prompt)prompt.hidden=hasPoint;
 nameInput.readOnly=!hasPoint;
 kakao.maps.event.addListener(asmMap,'click',function(e){
   if(marker)marker.setPosition(e.latLng);else marker=new kakao.maps.Marker({map:asmMap,position:e.latLng});
   latInput.value=String(e.latLng.getLat());lngInput.value=String(e.latLng.getLng());
   if(prompt)prompt.hidden=true;
   nameInput.readOnly=false;nameInput.required=true;nameInput.focus();dirty();
   document.getElementById('asmSaved').textContent='위치를 지정했습니다. 집결지 이름을 입력한 뒤 저장해 주세요.';
   document.getElementById('assemblyPrintLocation').textContent='집결지 위치 · '+latInput.value+' / '+lngInput.value;
 });
 let route=[];try{const parsed=JSON.parse(routeInput.value||'[]');if(Array.isArray(parsed))route=parsed.filter(p=>p&&valid(p.lat,p.lng));}catch(e){}
 let line=null,dots=[],editing=false,changed=false;
 const edit=document.getElementById('routeEditBtn'),hint=document.getElementById('routeHint');
 function draw(){
   if(line)line.setMap(null);dots.forEach(d=>d.setMap(null));dots=[];
   const path=route.map(p=>new kakao.maps.LatLng(Number(p.lat),Number(p.lng)));
   if(path.length){line=new kakao.maps.Polyline({map:routeMap,path,strokeWeight:6,strokeColor:'#d85f45',strokeOpacity:.95});path.forEach((p,i)=>dots.push(new kakao.maps.Circle({map:routeMap,center:p,radius:i===0?5:3,strokeWeight:2,strokeColor:'#fff',fillColor:i===0?'#19866d':'#d85f45',fillOpacity:1})));}
   if(changed)routeInput.value=route.length?JSON.stringify(route):'';
   hint.textContent=editing?'도로에서 건물 방향으로 눌러 주세요 · '+route.length+'개 지점':changed?'변경한 진입로 '+route.length+'개 지점 · 아래 저장 버튼을 눌러 주세요.':route.length>=2?'저장된 진입로 · '+route.length+'개 지점':'소방차 진입로를 아직 지정하지 않았습니다.';
   document.getElementById('routePrintLocation').textContent=route.length>=2?'진입 경로 · '+route.length+'개 지점 (초록점: 시작)':'소방차 진입로 미지정';
 }
 edit.onclick=()=>{editing=!editing;edit.textContent=editing?'그리기 마침':'진입로 그리기';draw();};
 document.getElementById('routeUndoBtn').onclick=()=>{if(!route.length)return;route.pop();changed=true;dirty();draw();};
 document.getElementById('routeResetBtn').onclick=()=>{route=[];changed=true;dirty();draw();};
 kakao.maps.event.addListener(routeMap,'click',e=>{if(!editing)return;route.push({lat:e.latLng.getLat(),lng:e.latLng.getLng()});changed=true;dirty();draw();});
 draw();if(route.length){const bounds=new kakao.maps.LatLngBounds();route.forEach(p=>bounds.extend(new kakao.maps.LatLng(Number(p.lat),Number(p.lng))));routeMap.setBounds(bounds);}
 document.getElementById('biForm').addEventListener('submit',e=>{
   nameInput.setCustomValidity('');
   if((latInput.value||lngInput.value||nameInput.value.trim())&&(!valid(latInput.value,lngInput.value)||!nameInput.value.trim())){e.preventDefault();nameInput.setCustomValidity(valid(latInput.value,lngInput.value)?'집결지 이름을 입력해 주세요.':'지도에서 집결지 위치를 먼저 찍어 주세요.');nameInput.reportValidity();document.getElementById('asmSaved').textContent=nameInput.validationMessage;return;}
   if(route.length===1){e.preventDefault();hint.textContent='진입로는 두 지점 이상 찍은 뒤 저장해 주세요.';routeEl.scrollIntoView({block:'center'});}
 });
 nameInput.addEventListener('input',()=>nameInput.setCustomValidity(''));
 function relayout(){const a=asmMap.getCenter(),r=routeMap.getCenter();asmMap.relayout();routeMap.relayout();asmMap.setCenter(a);routeMap.setCenter(r);}
 window.addEventListener('beforeprint',relayout);window.addEventListener('afterprint',relayout);
 if(window.ResizeObserver){new ResizeObserver(relayout).observe(asmEl);new ResizeObserver(relayout).observe(routeEl);}
});
})();
