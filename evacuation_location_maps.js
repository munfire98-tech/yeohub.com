(()=>{'use strict';
const states=new Map();let loaded=false,scheduled=false;
const elements=()=>[...document.querySelectorAll('[data-epl-location-map]')];
const visible=el=>el.getBoundingClientRect().width>0&&el.getBoundingClientRect().height>0;
function fail(el,message){let status=el.querySelector('.epl-map-status');if(!status){status=document.createElement('p');status.className='epl-map-status';el.append(status);}status.textContent=message;}
function draw(el){
 if(states.has(el)||!visible(el))return;
 if(!loaded)return;
 const K=window.kakao.maps;let data;try{data=JSON.parse(el.dataset.location);}catch(_){fail(el,'저장된 지도 위치를 확인해 주세요.');return;}
 const assembly=el.dataset.eplLocationMap==='assembly',points=assembly?[data.assembly]:data.route;
 if(!Array.isArray(points)||!points.length)return;
 el.replaceChildren();const path=points.map(p=>new K.LatLng(Number(p.lat),Number(p.lng)));
 const map=new K.Map(el,{center:path[0],level:3,draggable:false,scrollwheel:false,disableDoubleClick:true});
 map.setZoomable(false);
 const state={map,ready:false,bounds:null,center:path[0],settle:null};
 state.promise=new Promise(resolve=>{state.settle=resolve;});states.set(el,state);
 K.event.addListener(map,'tilesloaded',()=>{state.ready=true;state.settle();});
 if(assembly)new K.Marker({map,position:path[0]});
 else{
  new K.Polyline({map,path,strokeWeight:6,strokeColor:'#d85f45',strokeOpacity:.95});
  path.forEach((p,i)=>new K.Circle({map,center:p,radius:i===0?5:3,strokeWeight:2,strokeColor:'#fff',fillColor:i===0?'#19866d':'#d85f45',fillOpacity:1}));
  state.bounds=new K.LatLngBounds();path.forEach(p=>state.bounds.extend(p));map.setBounds(state.bounds);
 }
 if(resize)resize.observe(el);
}
function relayout(el,state){if(!visible(el))return;state.map.relayout();if(state.bounds)state.map.setBounds(state.bounds);else{state.map.setCenter(state.center);state.map.setLevel(3);}}
const resize=window.ResizeObserver?new ResizeObserver(entries=>{entries.forEach(({target})=>{const state=states.get(target);if(state)relayout(target,state);else draw(target);});}):null;
function scan(){
 scheduled=false;for(const [el] of states)if(!el.isConnected){resize?.unobserve(el);states.delete(el);}
 elements().forEach(el=>{if(!states.has(el)){resize?.observe(el);draw(el);}});
}
function schedule(){if(!scheduled){scheduled=true;requestAnimationFrame(scan);}}
new MutationObserver(schedule).observe(document.body,{childList:true,subtree:true});
window.addEventListener('beforeprint',()=>{scan();states.forEach((state,el)=>relayout(el,state));});
window.addEventListener('afterprint',()=>states.forEach((state,el)=>relayout(el,state)));
window.eplMapsReady=async()=>{
 scan();const targets=elements().filter(visible);if(!targets.length)return;
 if(!loaded)throw Error('지도 준비 중입니다. 잠시 후 다시 인쇄해 주세요.');
 states.forEach((state,el)=>relayout(el,state));
 const pending=targets.map(el=>states.get(el)?.promise||Promise.reject(Error('지도 위치를 확인해 주세요.')));
 let timer;try{await Promise.race([Promise.all(pending),new Promise((_,reject)=>{timer=setTimeout(()=>reject(Error('지도를 불러오지 못했습니다. 연결을 확인하고 다시 인쇄해 주세요.')),12000);})]);}finally{clearTimeout(timer);}
 await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));
};
window.eplPrintLocations=async()=>{try{if(document.fonts)await document.fonts.ready;await window.eplMapsReady();window.print();}catch(e){alert(e.message);}};
if(window.kakao?.maps?.load)window.kakao.maps.load(()=>{loaded=true;scan();});
else elements().forEach(el=>fail(el,'카카오 지도를 불러오지 못했습니다. 연결 또는 지도 설정을 확인한 뒤 새로고침해 주세요.'));
})();
