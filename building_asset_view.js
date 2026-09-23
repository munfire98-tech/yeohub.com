(function(){
'use strict';
const host=document.getElementById('assetMap');
if(!window.kakao?.maps){host.querySelector('.map-fallback').textContent='지도를 불러오지 못했습니다. 잠시 후 다시 열어 주세요.';return;}
kakao.maps.load(function(){
 if(!savedAsset.points.length)return;
 const points=savedAsset.points.map(p=>new kakao.maps.LatLng(p.lat,p.lng));host.replaceChildren();
 const map=new kakao.maps.Map(host,{center:points[0],level:3});
 if(savedAsset.route){
   new kakao.maps.Polyline({map,path:points,strokeWeight:6,strokeColor:'#d85f45',strokeOpacity:.95});
   points.forEach((p,i)=>new kakao.maps.Circle({map,center:p,radius:i===0?6:3,strokeWeight:2,strokeColor:'#fff',fillColor:i===0?'#19866d':'#d85f45',fillOpacity:1}));
 }else{new kakao.maps.Marker({map,position:points[0]});}
 function fit(){map.relayout();if(savedAsset.route){const bounds=new kakao.maps.LatLngBounds();points.forEach(p=>bounds.extend(p));map.setBounds(bounds,35,35,35,35);}else map.setCenter(points[0]);}
 fit();if(window.ResizeObserver)new ResizeObserver(fit).observe(host);
 window.addEventListener('beforeprint',fit);window.addEventListener('afterprint',fit);
});
})();
