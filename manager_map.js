(()=>{
  const mapElement=document.getElementById('map');if(!mapElement)return;
  const design=document.createElement('style');
  design.textContent=`
  .mm-marker .mm-building-label{display:inline-flex;align-items:center;gap:9px;width:max-content;max-width:250px;min-height:36px;padding:7px 9px 7px 12px;box-sizing:border-box;background:#fff;border:1px solid #dbe4ec;border-radius:12px;box-shadow:0 3px 10px #18354b20;color:#25394c;font:600 12px/1.4 system-ui;white-space:nowrap;transition:border-color .18s,box-shadow .18s}
  .mm-marker .mm-building-label.mm-is-pro{border-color:#dbe4ec;background:#fff;box-shadow:0 3px 10px #18354b20}
  .mm-marker .mm-pro-badge{display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;box-sizing:border-box;border-radius:6px;background:linear-gradient(145deg,#f25559,#df343c);color:#fff;font:800 13px/1 system-ui;flex:0 0 20px;margin-right:-3px;box-shadow:inset 0 1px 0 #ffffff30,0 1px 3px #c72b3226}
  .mm-pro-note{margin:10px 0;padding:10px 12px;background:#f0fdfa;border:1px solid #b5e5db;border-radius:10px;color:#116b60;font-size:12px}
  .mm-pro-note small{display:block;margin-top:3px;color:#527a72}
  .mm-marker .mm-building-name{min-width:0;max-width:170px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;letter-spacing:-.02em}
  .mm-marker .mm-building-tag{display:inline-flex;align-items:center;justify-content:center;gap:4px;flex-shrink:0;border-radius:7px;padding:3px 6px;background:#edf6fa;color:#44839b;font:650 10px/1.4 system-ui}
  .mm-marker .mm-building-label.mm-has-request{border-color:#ebc590;box-shadow:0 3px 12px #80521c22}
  .mm-marker .mm-has-request .mm-building-tag{background:#fff0d9;color:#b46616;font-size:11px;min-width:31px}
  .mm-marker .mm-preregistered{border-color:#b6c6d3;background:#f8fafc}.mm-marker .mm-preregistered .mm-building-tag{background:#e5ecf2;color:#536d83;font-size:10px}.mm-marker .mm-preregistered .mm-building-name{color:#526879}
  .mm-marker .mm-building-tag svg{display:block;flex-shrink:0}
  .mm-marker:hover .mm-building-label{border-color:#91aabe;box-shadow:0 5px 16px #18354b30}
  .mm-popup{min-width:205px;max-width:280px;color:#34475b;font:13px/1.65 system-ui}
  .mm-popup>strong{display:block;color:#203449;font-size:15px;letter-spacing:-.03em;overflow-wrap:anywhere}
  .mm-popup>p{margin:5px 0 12px;color:#7b8997;font-size:12px}
  .mm-popup a.mm-view{display:flex;justify-content:center;align-items:center;border:1px solid #d9e5ed;border-radius:9px;padding:8px 12px;background:#f3f8fb;color:#315d78;text-decoration:none;font-weight:700}
  .mm-popup .mm-help-note{display:flex;align-items:center;gap:9px;margin:12px 0;padding:10px 11px;border:1px solid #f0dfc2;border-radius:10px;background:#fffbf3}
  .mm-help-note[hidden]{display:none!important}
  .mm-help-note>svg{flex-shrink:0;color:#b8792c}
  .mm-help-note strong{display:block;color:#8c5b1e;font-size:12px}
  .mm-help-note small{display:block;color:#9a835f;font-size:11px;line-height:1.6}
  .mm-popup .mm-contact-details{margin-top:13px;padding-top:10px;border-top:1px solid #ecf0f4;font-size:12px}
  .mm-popup .mm-contact-details p{margin:4px 0}
  @media(max-width:600px){.mm-marker .mm-building-label{max-width:205px;min-height:34px;padding:6px 8px 6px 10px;gap:7px}.mm-marker .mm-building-name{max-width:130px}}
  `;
  document.head.append(design);
  const bellSVG='<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>';
  const note=document.createElement('p');note.setAttribute('role','status');note.hidden=true;
  note.style.cssText='margin:6px 0;color:#786b8a;font-size:12px';mapElement.insertAdjacentElement('afterend',note);
  function status(message=''){note.textContent=message;note.hidden=!message;}
  let layer=null,version=0,controller=null,firstFit=true;
  let helpCounts=new Map();
  function countHelp(data){helpCounts=new Map();for(const r of data?.rows||[]){if(r.status==='pending'&&r.connection_active)helpCounts.set(r.uid,(helpCounts.get(r.uid)||0)+1);}byUid.forEach(paintHelp);}
  function paintHelp(item){const count=helpCounts.get(item.row.uid)||0;
    item.label.classList.toggle('mm-has-request',count>0);
    item.tag.replaceChildren();
    if(count){item.tag.innerHTML=bellSVG;item.tag.append(document.createTextNode(count>99?'99+':String(count)));}
    else item.tag.textContent=item.row.preregistered?'사전등록':'담당';
    item.tag.hidden=!count&&item.row.subscription?.active===true;
    const description=(item.row.subscription?.active?'PRO 협업 중 · ':'')+(count?'작성 도움 요청 '+count+'건':item.row.preregistered?'사전등록 · 유저 연결 전':'담당 건물');
    item.tag.setAttribute('aria-label',description);item.tag.title=description;
    item.label.title=item.row.name+' · '+description;
    item.helpNote.hidden=!count;
    item.helpTitle.textContent='작성 도움 요청 '+count+'건';
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
  function viewer(row){if(row.preregistered){const a=node('a','기본정보 수정','mm-view');a.href='/manager_addresses.php?id='+encodeURIComponent(row.address_id);a.dataset.addressOpen='1';return a;}const a=node('a','건물관리 화면','mm-view');a.href='/manager_view.php?uid='+encodeURIComponent(row.uid);a.target='_blank';a.rel='noopener';return a;}
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
          const tag=node('span','담당','mm-building-tag');
          if(row.subscription?.active){label.classList.add('mm-is-pro');const subscriptionMark=node('span','S','mm-pro-badge');subscriptionMark.setAttribute('aria-label',row.subscription.test?'테스트 구독 중':'구독 중');label.append(subscriptionMark);}
          if(row.preregistered){label.classList.add('mm-preregistered');label.append(tag,node('span',row.name,'mm-building-name'));}else label.append(node('span',row.name,'mm-building-name'),tag);
          const marker=L.marker(point,{title:row.name,icon:L.divIcon({className:'mm-marker',html:label,iconSize:null,iconAnchor:[12,14],popupAnchor:[0,-16]})});
          const popup=node('div',undefined,'mm-popup');popup.append(node('strong',row.name),node('p',row.address||'주소 미입력'),viewer(row));const details=node('div',undefined,'mm-contact-details');
          if(row.subscription?.active){const sub=row.subscription,pro=node('div',sub.test?'PRO 협업 중 · 테스트':'PRO 협업 중','mm-pro-note');pro.append(node('small','구독 시작 '+(sub.started_at||'확인 필요')));if(sub.expires_at)pro.append(node('small','이용 만료 '+sub.expires_at));popup.append(pro);}
          details.append(node('p','사용승인일: '+(row.approval_date||'미입력')),node('p','대표자: '+(row.representative||'미입력')));
          if(row.building_tel)details.append(node('p','건물 연락처: '+row.building_tel));
          const staff=Array.isArray(row.safety_managers)?row.safety_managers:[];
          details.append(node('strong','안전관리자'));
          if(!staff.length)details.append(node('p','미입력'));
          staff.forEach(m=>details.append(node('p',[m.name,m.type,m.tel].filter(Boolean).join(' · '))));
          const helpNote=node('div',undefined,'mm-help-note');helpNote.innerHTML=bellSVG;const helpCopy=node('div'),helpTitle=node('strong');helpCopy.append(helpTitle,node('small','건물관리 화면의 알림을 확인하세요.'));helpNote.append(helpCopy);popup.append(helpNote,details);marker.bindPopup(popup);if(matches(row))layer.addLayer(marker);const item={marker,point,row,label,tag,helpNote,helpTitle};byUid.set(row.uid,item);paintHelp(item);located++;
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
