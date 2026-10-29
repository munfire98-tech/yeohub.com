/* OpenFreeMap Positron under the existing Leaflet markers and interactions. */
(()=>{
 'use strict';
 let loading;
 function script(src){return new Promise((resolve,reject)=>{const s=document.createElement('script');s.src=src;s.onload=resolve;s.onerror=reject;document.head.append(s);});}
 function libraries(){
  if(!loading){
   const css=document.createElement('link');css.rel='stylesheet';css.href='/map_vendor/maplibre-gl.css';document.head.append(css);
   loading=script('/map_vendor/maplibre-gl.js').then(()=>script('/map_vendor/leaflet-maplibre-gl.js'));
  }
  return loading;
 }
 window.partnerBasemap={add(map){
  const mobile=matchMedia('(max-width:760px)').matches;
  map.attributionControl?.setPrefix(false);
  const credit=mobile?'<a href="https://openfreemap.org/" target="_blank" rel="noopener noreferrer">OpenFreeMap</a> · &copy; <a href="https://openmaptiles.org/" target="_blank" rel="noopener noreferrer">OpenMapTiles</a> · <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OSM</a>':'&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a>';

  const fallback=L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,referrerPolicy:'strict-origin-when-cross-origin',attribution:credit}).addTo(map);
  if(!mobile)return fallback;
  map.setMinZoom(Math.max(1,map.getMinZoom()));
  map.whenReady(async()=>{
   let layer,timer,loaded=false;
   const fail=()=>{if(loaded)return;clearTimeout(timer);if(layer&&map.hasLayer(layer)){try{map.removeLayer(layer);}catch(_){try{layer.getContainer()?.remove();}catch{}}}map.getContainer().dataset.basemap='standard';};
   try{
    await libraries();
    layer=L.maplibreGL({style:'/partner_basemap_style.json?v=20261021-1',interactive:false,localIdeographFontFamily:'sans-serif',attributionControl:{customAttribution:credit}});
    layer.addTo(map);
    layer.getContainer().style.opacity='0';
    timer=setTimeout(fail,15000);
    const gl=layer.getMaplibreMap();
    gl.once('load',()=>{if(!map.hasLayer(layer))return;loaded=true;clearTimeout(timer);layer.getContainer().style.opacity='1';if(map.hasLayer(fallback))map.removeLayer(fallback);map.getContainer().dataset.basemap='clean';});
   }catch(_){fail();}
  });
  return fallback;
 }};
})();
