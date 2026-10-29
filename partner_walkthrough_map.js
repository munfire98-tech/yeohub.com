/* Tutorial-only adapter: real Paju map, fictional building and practice points. */
(() => {
 'use strict';
 if(!location.pathname.startsWith('/partner_demo_app.php/'))return;
 const pair=p=>[p.getLat(),p.getLng()];
 class LatLng{constructor(lat,lng){this.lat=Number(lat);this.lng=Number(lng);}getLat(){return this.lat;}getLng(){return this.lng;}}
 class Map{
  constructor(el,{center,practice=true}){
   this.el=el;this.center=center;this.listeners={};el.classList.add('pwt-map');
   const route=practice&&typeof STEPS!=='undefined'&&STEPS[step]?.field==='fire_engine_route';
   // A closer view makes the junction and the building-front road easy to follow.
   this.map=L.map(el,{zoomControl:true,scrollWheelZoom:true}).setView(route?[center.lat-.0002,center.lng-.0003]:pair(center),route&&el.clientWidth>=420?18:17);
   L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,referrerPolicy:'strict-origin-when-cross-origin',attribution:'&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors'}).addTo(this.map);
   const lat=center.lat,lng=center.lng;
   L.polygon([[lat-.000155,lng-.00015],[lat+.00008,lng-.00015],[lat+.00008,lng+.00015],[lat-.000155,lng+.00015]],{color:'#2563eb',weight:2,fillColor:'#8eb4f0',fillOpacity:.65,interactive:false}).addTo(this.map);
   this.map.on('click',e=>this.listeners.click?.({latLng:new LatLng(e.latlng.lat,e.latlng.lng)}));
   if(practice){
    // Paju example: Tongil-ro -> junction -> the road south of the fictional building.
    // These fixed offsets illustrate clicking along the visible road, not route guidance.
    const points=route?[['진입 시작점',-.000375,-.001105,'1'],['도로 모퉁이',-.000558,-.000987,'2'],['건물 입구',-.000155,-.000022,'3']]:[['앞 공터',.00018,-.0004,'+']];
    for(const [label,dy,dx,text] of points){
     const button=document.createElement('button');button.type='button';button.className='pwt-map-point';button.dataset.mapPoint=label;button.setAttribute('aria-label',label+' 위치 선택');button.textContent='';button.dataset.pointNumber=text;
     L.DomEvent.disableClickPropagation(button);
     button.onclick=()=>{button.textContent=text;button.classList.add('is-picked');this.listeners.click?.({latLng:new LatLng(lat+dy,lng+dx)});};
     L.marker([lat+dy,lng+dx],{icon:L.divIcon({className:'pwt-point-icon',html:button,iconSize:[28,28],iconAnchor:[14,14]}),keyboard:false}).addTo(this.map);
    }
   }
   this.observer=new ResizeObserver(()=>{if(!el.isConnected){this.observer.disconnect();return;}this.map.invalidateSize({pan:false});});this.observer.observe(el);
   window.addEventListener('pagehide',()=>this.observer.disconnect(),{once:true});
  }
 }
 class Shape{
  constructor(options){this.options=options;this.setMap(options.map);}
  setMap(map){if(this.layer&&this.map)this.map.map.removeLayer(this.layer);this.map=map;if(!map)return;const o=this.options;
   if(o.path)this.layer=L.polyline(o.path.map(pair),{color:o.strokeColor||'#dc2626',weight:o.strokeWeight||4,interactive:false});
   else if(o.content){const label=document.createElement('span');label.className='pwt-map-label';const content=document.createElement('div');content.innerHTML=o.content;label.textContent=content.textContent;this.layer=L.marker(pair(o.position),{icon:L.divIcon({className:'pwt-label-icon',html:label,iconSize:null,iconAnchor:[0,28]}),interactive:false});}
   else this.layer=L.circleMarker(pair(o.center||o.position),{radius:o.radius||7,color:o.strokeColor||'#fff',weight:o.strokeWeight||2,fillColor:o.fillColor||'#15803d',fillOpacity:o.fillOpacity??1,interactive:false});
   this.layer.addTo(map.map);
  }
 }
 window.kakao={maps:{LatLng,Map,Marker:Shape,Circle:Shape,Polyline:Shape,CustomOverlay:Shape,event:{addListener:(map,event,fn)=>map.listeners[event]=fn}}};
 window.partnerTutorialMap=true;
 document.addEventListener('DOMContentLoaded',()=>document.body.classList.add('pwt-draft'));
})();
