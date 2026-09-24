(()=>{
  const mapElement=document.getElementById('map');if(!mapElement)return;
  const note=document.createElement('p');note.setAttribute('role','status');note.hidden=true;
  note.style.cssText='margin:6px 0;color:#786b8a;font-size:12px';mapElement.insertAdjacentElement('afterend',note);
  function status(message=''){note.textContent=message;note.hidden=!message;}
  let layer=null,version=0,controller=null,firstFit=true;
  let helpCounts=new Map();
  function countHelp(data){helpCounts=new Map();for(const r of data?.rows||[]){if(r.status==='pending'&&r.connection_active)helpCounts.set(r.uid,(helpCounts.get(r.uid)||0)+1);}byUid.forEach(paintHelp);}
  function paintHelp(item){const count=helpCounts.get(item.row.uid)||0;
    item.label.style.background=count?'#fff4df':'';item.label.style.borderColor=count?'#d97706':'';item.label.style.boxShadow=count?'0 0 0 3px rgba(245,158,11,.25)':'';
    item.tag.textContent=count?'! 요청 '+count+'건':'담당';item.tag.style.background=count?'#b45309':'';item.tag.style.color=count?'#fff':'';
    item.helpNote.textContent=count?'작성 도움 요청 '+count+'건 · 건물관리 화면에서 확인하세요.':'';item.helpNote.hidden=!count;
    item.marker.setZIndexOffset(count?1000:0);
  }
  const byUid=new Map();let focusUid=null,selectedMonth='all';
  document.addEventListener('manager-help-updated',e=>countHelp(e.detail));
  countHelp(window.managerHelp?.getState());
  const matches=row=>selectedMonth==='all'||(selectedMonth==='unknown'?!row.approval_month:String(row.approval_month)===selectedMonth);
  function applyMonth(fit=false){
    if(typeof map==='undefined'||!map||!layer)return;
    map.closePopup();layer.clearLayers();
    byUid.forEach(item=>{if(matches(item.row))layer.addLayer(item.marker);});
    if(typeof group!=='undefined'){if(selectedMonth==='all'){if(!map.hasLayer(group))group.addTo(map);}else if(map.hasLayer(group))map.removeLayer(group);}
    if(fit&&layer.getLayers().length){const bounds=layer.getBounds();if(selectedMonth==='all'&&typeof group!=='undefined'&&group.getLayers().length)bounds.extend(group.getBounds());map.fitBounds(bounds.pad(.2),{maxZoom:16});}
    else if(fit&&selectedMonth==='all'&&typeof group!=='undefined'&&group.getLayers().length)map.fitBounds(group.getBounds().pad(.2),{maxZoom:16});
    status(selectedMonth!=='all'&&!layer.getLayers().length?'선택한 사용승인월에 지도 위치가 확인된 담당 건물이 없습니다.':'');
  }
  document.addEventListener('manager-month-filter',event=>{const month=String(event.detail?.month||'all');if(!['all','unknown',...Array.from({length:12},(_,i)=>String(i+1))].includes(month))return;selectedMonth=month;applyMonth(true);});
  function focusUser(uid){const item=byUid.get(uid);if(!item)return false;if(!matches(item.row)){status('선택한 사용승인월에 해당하지 않습니다. 월 선택을 바꿔 주세요.');return true;}map.setView(item.point,Math.max(15,map.getZoom()));item.marker.openPopup();return true;}
  document.addEventListener("manager-map-focus",event=>{const uid=event.detail?.uid;if(typeof uid!=="string")return;if(!focusUser(uid)){focusUid=uid;load();}});
  const cache=new Map(); // Address-only, in-memory cache. No member data persisted in the browser.
  const node=(tag,text,cls)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=text;if(cls)e.className=cls;return e;};
  function viewer(row){const a=node('a','건물관리 화면','mm-view');a.href='/manager_view.php?uid='+encodeURIComponent(row.uid);a.target='_blank';a.rel='noopener';return a;}
  function clear(){byUid.clear();if(layer)layer.clearLayers();}
  async function locate(row){
    if(row.lat!==null&&row.lng!==null)return [row.lat,row.lng];
    if(!row.address)return null;
    if(cache.has(row.address))return cache.get(row.address);
    if(!window.kakao?.maps)return null;
    const result=await new Promise(resolve=>{
      const timeout=setTimeout(()=>resolve(null),7000);
      kakao.maps.load(()=>{
        if(!kakao.maps.services){clearTimeout(timeout);resolve(null);return;}
        try{new kakao.maps.services.Geocoder().addressSearch(row.address,(rows,status)=>{
          clearTimeout(timeout);
          const p=rows?.[0],lat=Number(p?.y),lng=Number(p?.x);
          resolve(status===kakao.maps.services.Status.OK&&p&&Number.isFinite(lat)&&Number.isFinite(lng)&&Math.abs(lat)<=90&&Math.abs(lng)<=180?[lat,lng]:null);
        });}catch{clearTimeout(timeout);resolve(null);}
      });
    });
    if(result)cache.set(row.address,result);return result;
  }
  async function load(){
    const current=++version;controller?.abort();controller=new AbortController();
    clear();status();
    try{
      const response=await fetch('/manager_buildings.php',{credentials:'same-origin',cache:'no-store',signal:controller.signal});
      const data=await response.json();if(current!==version)return;
      if(!response.ok||!data.ok||!Array.isArray(data.buildings))throw new Error();
      document.dispatchEvent(new CustomEvent('manager-buildings-updated',{detail:data}));
      if(typeof L==='undefined'||typeof map==='undefined'||!map){status('지도를 불러오지 못했습니다. 우측 담당 유저 목록에서 확인해 주세요.');}
      else if(!layer)layer=L.featureGroup().addTo(map);
      let located=0;
      // Limited parallel workers keep address lookups bounded.
      const queue=data.buildings.slice();
      async function worker(){while(queue.length&&current===version){
        const row=queue.shift();
        const point=await locate(row);if(current!==version)return;
        if(point&&layer){
          const label=node('div',undefined,'mm-building-label');
          const tag=node('span','담당','mm-building-tag');label.append(tag,node('span',row.name,'mm-building-name'));
          const marker=L.marker(point,{title:row.name,icon:L.divIcon({className:'mm-marker',html:label,iconSize:null,iconAnchor:[12,14],popupAnchor:[0,-16]})});
          const popup=node('div',undefined,'mm-popup');popup.append(node('strong',row.name),node('p',row.address||'주소 미입력'),viewer(row));const details=node('div',undefined,'mm-contact-details');
          details.append(node('p','사용승인일: '+(row.approval_date||'미입력')),node('p','대표자: '+(row.representative||'미입력')));
          if(row.building_tel)details.append(node('p','건물 연락처: '+row.building_tel));
          const staff=Array.isArray(row.safety_managers)?row.safety_managers:[];
          details.append(node('strong','안전관리자'));
          if(!staff.length)details.append(node('p','미입력'));
          staff.forEach(m=>details.append(node('p',[m.name,m.type,m.tel].filter(Boolean).join(' · '))));
          const helpNote=node('p');helpNote.style.cssText='color:#9a5208;font-weight:700';popup.append(helpNote,details);marker.bindPopup(popup);if(matches(row))layer.addLayer(marker);const item={marker,point,row,label,tag,helpNote};byUid.set(row.uid,item);paintHelp(item);located++;
        }
      }}
      await Promise.all([worker(),worker(),worker()]);if(current!==version)return;
      applyMonth(false);
      if(firstFit&&located&&selectedMonth==='all'){const bounds=layer.getBounds();if(typeof group!=='undefined'&&group.getLayers().length)bounds.extend(group.getBounds());map.fitBounds(bounds.pad(.2),{maxZoom:16});firstFit=false;}
      if(focusUid){if(!focusUser(focusUid))status("이 유저의 주소 또는 지도 위치를 확인해 주세요.");focusUid=null;}
    }catch(error){if(current!==version)return;clear();document.dispatchEvent(new CustomEvent('manager-buildings-unavailable'));status('연결 정보를 불러오지 못했습니다. 로그인 상태를 확인하고 새로고침해 주세요.');}
  }
  if(document.readyState==='complete')load();else window.addEventListener('load',load,{once:true});
  setInterval(()=>{if(!document.hidden)load();},60000);
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)load();});
})();
