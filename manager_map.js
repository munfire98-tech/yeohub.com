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
  .mm-marker .mm-building-tag[hidden]{display:none!important}
  .mm-marker .mm-building-tag svg{display:block;flex-shrink:0}
  .mm-marker:hover .mm-building-label{border-color:#91aabe;box-shadow:0 5px 16px #18354b30}
  .leaflet-tooltip.mm-name-tooltip{width:max-content;max-width:min(360px,calc(100vw - 48px));box-sizing:border-box;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;padding:9px 15px;border:1px solid #dce5ef;border-radius:12px;background:#fff;color:#233b55;box-shadow:0 4px 16px #19365126;font:700 13px/1.4 system-ui;letter-spacing:-.02em;pointer-events:none}
  .leaflet-tooltip-top.mm-name-tooltip:before{border-top-color:#dce5ef}
  .mm-popup{min-width:205px;max-width:280px;color:#34475b;font:13px/1.65 system-ui}
  .mm-popup>strong{display:block;color:#203449;font-size:15px;letter-spacing:-.03em;overflow-wrap:anywhere}
  .mm-popup>p{margin:5px 0 12px;color:#7b8997;font-size:12px}
  .mm-popup a.mm-view{display:flex;justify-content:center;align-items:center;border:1px solid #d9e5ed;border-radius:9px;padding:8px 12px;background:#f3f8fb;color:#315d78;text-decoration:none;font-weight:700}
  .mm-popup .mm-help-note{display:flex;align-items:center;gap:9px;margin:12px 0;padding:10px 11px;border:1px solid #f0dfc2;border-radius:10px;background:#fffbf3}
  .mm-help-note[hidden]{display:none!important}
  .mm-help-note>svg{flex-shrink:0;color:#b8792c}
  .mm-help-note strong{display:block;color:#8c5b1e;font-size:12px}
  .mm-help-note small{display:block;color:#9a835f;font-size:11px;line-height:1.6}
  .mm-detail-open{display:flex;align-items:center;justify-content:space-between;width:100%;box-sizing:border-box;margin-top:9px;padding:9px 11px;border:1px solid #e2e9f0;border-radius:8px;background:#f7f9fc;color:#536d88;font:600 12px/1.5 system-ui;cursor:pointer}
  .mm-detail-open::after{content:'›';font-size:18px;line-height:1}.mm-detail-open:hover{background:#eef4fb;border-color:#bfd1e7}
  .mm-popup .mm-help-note{margin:8px 0;padding:7px 9px}.mm-popup .mm-help-note small{display:none}
  dialog.mm-detail-dialog{position:fixed;inset:0;margin:auto;box-sizing:border-box;width:min(480px,calc(100vw - 28px));max-height:calc(100dvh - 40px);padding:0;border:1px solid #dce5ef;border-radius:18px;background:#fff;color:#29415c;box-shadow:0 24px 70px #172d4c40;overflow:hidden}
  .mm-detail-dialog::backdrop{background:#162b4866;backdrop-filter:blur(3px)}
  .mm-detail-dialog[open]{display:flex;flex-direction:column}
  .mm-detail-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;padding:22px 24px 17px;border-bottom:1px solid #edf1f6;flex-shrink:0}
  .mm-detail-head small{color:#8291a5;font:11px/1.5 system-ui}.mm-detail-head h2{margin:6px 0 0;font:700 19px/1.4 system-ui;overflow-wrap:anywhere}.mm-detail-close{border:0;background:#f1f5f9;color:#597087;width:30px;height:30px;border-radius:8px;cursor:pointer;font:20px/1 system-ui;flex-shrink:0}
  .mm-detail-body{padding:18px 24px 24px;overflow:auto;overscroll-behavior:contain;min-height:0;font:13px/1.6 system-ui;scrollbar-width:thin}
  .mm-detail-address{margin:0 0 16px;color:#7a8b9c;font-size:12px;overflow-wrap:anywhere}.mm-detail-facts{margin:0}.mm-detail-facts>div{display:grid;grid-template-columns:100px minmax(0,1fr);gap:12px;padding:11px 0;border-bottom:1px solid #f0f3f7}.mm-detail-facts dt{color:#8290a1}.mm-detail-facts dd{margin:0;color:#2e4661;font-weight:600;overflow-wrap:anywhere}
  .mm-detail-body h3{font-size:12px;margin:22px 0 10px;color:#6a7f95}.mm-detail-person{padding:12px 14px;margin-top:8px;background:#f5f8fc;border:1px solid #e7edf5;border-radius:10px}.mm-detail-person strong{display:block;font-size:13px}.mm-detail-person p{margin:4px 0 0;color:#6a8096;font-size:12px}.mm-detail-body .mm-pro-note{margin-top:18px;padding:12px 14px;border-radius:10px;background:#eff9f5;color:#377561;font-size:12px}.mm-detail-body .mm-pro-note small{display:block;color:#729185;margin-top:3px}
  .mm-detail-close:focus-visible,.mm-detail-open:focus-visible{outline:2px solid #5c8bd1;outline-offset:3px}
  @media(max-width:600px){.mm-marker .mm-building-label{max-width:205px;min-height:34px;padding:6px 8px 6px 10px;gap:7px}.mm-marker .mm-building-name{max-width:130px}}

  .mm-marker .mm-building-label{position:relative;gap:7px;min-height:36px;padding:6px 10px 6px 6px;border-radius:11px;border-color:#dbe3ed;box-shadow:0 2px 8px #213c531a}
  .mm-marker .mm-pro-badge,.mm-marker .mm-connection-badge,.mm-marker .mm-draft-icon{width:24px;height:24px;flex:0 0 24px;display:inline-flex;align-items:center;justify-content:center;border-radius:7px;font:800 13px/1 system-ui;margin:0;color:white}
  .mm-marker .mm-connection-badge{background:#3978cf}.mm-marker .mm-draft-icon{background:#70859d;color:#fff;border:0;box-shadow:inset 0 1px 0 #ffffff25}
  .mm-marker .mm-building-name{max-width:145px;font-size:12px}
  .mm-marker .mm-has-request .mm-building-tag{position:absolute;right:-7px;top:-8px;border:2px solid white;min-width:21px;height:21px;padding:0 4px;background:#e99130;color:white;border-radius:10px;font-size:10px;box-shadow:0 2px 5px #49290018}
  .mm-marker .mm-has-request .mm-building-tag svg{display:none}
  .mm-marker .mm-building-label.mm-selected{outline:3px solid #bdd3f8;border-color:#5485d5;box-shadow:0 4px 16px #235f982b}
  #map.mm-compact .mm-building-label:not(.mm-selected){padding:5px;border-radius:11px;min-height:34px}
  #map.mm-compact .mm-building-label:not(.mm-selected) .mm-building-name,#map.mm-compact .mm-preregistered:not(.mm-selected):not(.mm-has-request) .mm-building-tag{display:none}
  .mm-marker .mm-shared-location{padding:5px;border-radius:11px;min-height:34px}
  .mm-marker .mm-shared-location .mm-building-name{display:none}
  #map .mm-map-filterbar{display:flex;flex-direction:row;align-items:center;gap:9px}
  #map .mm-map-filterbar .mb-filters{min-width:0}
  #map .mm-view-toggle{flex:0 0 36px;width:36px;height:34px;display:flex;align-items:center;justify-content:center;padding:0;border:1px solid #dce4ee;border-radius:8px;color:#63758a;background:#fff;box-shadow:none;transform:none;font:600 15px/1 system-ui;letter-spacing:-.5px;cursor:pointer}
  #map .mm-view-toggle:hover{background:#f5f7fa;border-color:#bdcbdc;color:#344d6a}
  #map .mm-view-toggle:active{background:#eaf0f7}
  #map .mm-view-toggle[aria-pressed=true]{color:#245cba;border-color:#b9d0f2;background:#eaf2ff}
  #map .mm-view-toggle:focus-visible{outline:2px solid #8eafe5;outline-offset:2px}
  #map.mm-names .mm-building-label{width:max-content;max-width:260px;min-height:38px;padding:7px 12px 7px 7px;gap:8px;border-radius:12px;background:#fff;border:1px solid #dce5ef;box-shadow:0 4px 16px #19365126}
  #map.mm-names .mm-building-label .mm-building-name{display:block;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#233b55;font:700 13px/1.4 system-ui;letter-spacing:-.02em}
  #map.mm-names .mm-name-tooltip{display:none}
  #map.mm-compact .mm-building-label .mm-building-name{display:none}
  .mm-selection{background:#fff;padding:20px 22px;border-top:1px solid #e0e7ef;position:relative}
  .mm-selection[hidden]{display:none!important}.mm-selection .mm-popup{max-width:none;min-width:0;padding-right:30px}
  .mm-selection .mm-popup>strong{font-size:17px}.mm-selection .mm-popup>p{margin:6px 0 14px;font-size:12px}
  .mm-selection .mm-view{display:inline-flex!important;min-height:40px;background:#245ed9!important;color:white!important;border:0!important;margin-right:8px}
  .mm-selection-close{position:absolute;right:16px;top:16px;border:0;background:#f2f5f9;border-radius:8px;width:30px;height:30px;color:#61758e;cursor:pointer;font-size:18px}
  .mm-selection .mm-details-toggle{border:1px solid #dce5ee;border-radius:9px;background:white;color:#526b85;padding:9px 13px;min-height:40px;cursor:pointer;font-size:12px}
  .mm-selection .mm-pro-note{display:none}.mm-selection .mm-contact-details[hidden]{display:none}
  .mm-selection-status{font-size:11px;color:#7d8ca0;margin-bottom:6px}.mm-selection .mm-help-note{max-width:400px}
  @media(max-width:600px){.mm-selection{padding:16px}.mm-marker .mm-building-name{max-width:110px}}
  `;
  document.head.append(design);
  const bellSVG='<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>';
  const note=document.createElement('p');note.setAttribute('role','status');note.hidden=true;
  note.style.cssText='margin:6px 0;color:#786b8a;font-size:12px';mapElement.insertAdjacentElement('afterend',note);
  function status(message=''){note.textContent=message;note.hidden=!message;}
  let viewMode='icons';
  try{if(localStorage.getItem('manager-map-view')==='names')viewMode='names';}catch{}
  let overlapLayer=null;
  let layer=null,version=0,controller=null,firstFit=true;
  let helpCounts=new Map();
  function countHelp(data){helpCounts=new Map();for(const r of data?.rows||[]){if(r.status==='pending'&&r.connection_active)helpCounts.set(r.uid,(helpCounts.get(r.uid)||0)+1);}byUid.forEach(paintHelp);}
  function paintHelp(item){const count=helpCounts.get(item.row.uid)||0;
    item.label.classList.toggle('mm-has-request',count>0);
    item.tag.replaceChildren();
    if(count){item.tag.innerHTML=bellSVG;item.tag.append(document.createTextNode(count>99?'99+':String(count)));}
    else item.tag.textContent=item.row.preregistered?'사전등록':'';
    item.tag.hidden=!count;
    const description=(item.row.subscription?.active?'PRO 이용 · ':'')+(count?'작성 도움 요청 '+count+'건':item.row.preregistered?'사전등록 · 유저 연결 전':'매니저 연결 완료 · PRO 미구독');
    item.tag.setAttribute('aria-label',description);item.tag.title=description;
    item.marker.getElement()?.setAttribute('aria-label',item.row.name+' · '+description);
    item.helpNote.hidden=!count;
    item.helpTitle.textContent='작성 도움 요청 '+count+'건';
    item.marker.setZIndexOffset(count?1000:0);
  }
  const byUid=new Map();let focusUid=null,selectedMonth='all',buildingUids=null,loadingBuildings=false,pendingFit=false;
  document.addEventListener('manager-help-updated',e=>countHelp(e.detail));
  countHelp(window.managerHelp?.getState());
  const matches=row=>(buildingUids===null||buildingUids.has(row.uid))&&(selectedMonth==='all'||(selectedMonth==='unknown'?!row.approval_month:String(row.approval_month)===selectedMonth));
  document.addEventListener('manager-building-filter',e=>{buildingUids=new Set(e.detail.uids||[]);if(e.detail.fit)pendingFit=true;applyMonth(pendingFit&&!loadingBuildings);if(!loadingBuildings)pendingFit=false;if(selectedBuilding&&(!buildingUids.has(selectedBuilding)||e.detail.selected===null)){selectedBuilding=null;}});
  // Display offsets only: item.point stays the original location for summaries and storage.
  function sharedOffsets(count){
    if(count<2)return [[0,0]];
    if(viewMode==='names')return Array.from({length:count},(_,i)=>[i%2?18:-18,(i-(count-1)/2)*46]);
    const radius=Math.max(30,Math.ceil(count*46/(2*Math.PI)));
    return Array.from({length:count},(_,i)=>{const angle=2*Math.PI*i/count-Math.PI/2;return [Math.cos(angle)*radius,Math.sin(angle)*radius];});
  }
  let sharedGroups=new Map(),activeSharedKey=null,collapseTimer=null;
  function collapseShared(force=false){
    clearTimeout(collapseTimer);
    const items=sharedGroups.get(activeSharedKey)||[];
    if(!force&&items.some(item=>item.marker.isPopupOpen()))return;
    items.forEach(item=>{item.marker.setLatLng(item.point);item.label.classList.remove('mm-shared-location');});
    overlapLayer?.clearLayers();activeSharedKey=null;
  }
  function expandShared(item){
    const key=item.sharedKey,items=sharedGroups.get(key);
    if(!items||items.length<2)return;
    clearTimeout(collapseTimer);
    if(activeSharedKey===key)return;
    collapseShared(true);activeSharedKey=key;
    const offsets=sharedOffsets(items.length);
    items.forEach((entry,i)=>{
      const p=map.project(entry.point,map.getZoom()),offset=offsets[i];
      const shown=map.unproject(L.point(p.x+offset[0],p.y+offset[1]),map.getZoom());
      entry.marker.setLatLng(shown);entry.label.classList.add('mm-shared-location');
      L.polyline([entry.point,shown],{color:'#8095ad',weight:1.5,opacity:.7,dashArray:'3 4',interactive:false}).addTo(overlapLayer);
    });
  }
  function scheduleSharedCollapse(){clearTimeout(collapseTimer);collapseTimer=setTimeout(()=>collapseShared(),250);}
  function spreadSharedLocations(){
    if(typeof map==='undefined'||!map||!layer)return;
    if(!overlapLayer)overlapLayer=L.layerGroup().addTo(map);
    collapseShared(true);sharedGroups=new Map();
    byUid.forEach(item=>{
      item.marker.setLatLng(item.point);item.sharedKey=null;item.label.classList.remove('mm-shared-location');
      if(!matches(item.row))return;
      const key=item.point.map(v=>Number(v).toFixed(6)).join(',');
      item.sharedKey=key;
      if(!sharedGroups.has(key))sharedGroups.set(key,[]);sharedGroups.get(key).push(item);
    });
    sharedGroups.forEach(items=>items.sort((a,b)=>String(a.row.uid).localeCompare(String(b.row.uid))));
  }
  // Keep the whole expanded area active, including the gaps between markers.
  mapElement.addEventListener('mousemove',event=>{
    const items=sharedGroups.get(activeSharedKey);if(!items)return;
    const frame=mapElement.getBoundingClientRect(),origin=map.latLngToContainerPoint(items[0].point);
    const radius=viewMode==='names'?Math.max(280,items.length*23+50):Math.max(30,Math.ceil(items.length*46/(2*Math.PI)))+48;
    if(Math.hypot(event.clientX-frame.left-origin.x,event.clientY-frame.top-origin.y)<=radius)clearTimeout(collapseTimer);
    else scheduleSharedCollapse();
  });
  mapElement.addEventListener('mouseleave',scheduleSharedCollapse);
  function applyMonth(fit=false){
    if(typeof map==='undefined'||!map||!layer)return;
    map.closePopup();layer.clearLayers();
    byUid.forEach(item=>{if(matches(item.row))layer.addLayer(item.marker);});
    if(typeof group!=='undefined'){if(selectedMonth==='all'&&buildingUids===null){if(!map.hasLayer(group))group.addTo(map);}else if(map.hasLayer(group))map.removeLayer(group);}
    spreadSharedLocations();
    if(fit&&layer.getLayers().length){const bounds=layer.getBounds();if(selectedMonth==='all'&&buildingUids===null&&typeof group!=='undefined'&&group.getLayers().length)bounds.extend(group.getBounds());map.invalidateSize({pan:false});map.fitBounds(bounds,{paddingTopLeft:[44,100],paddingBottomRight:[160,44],maxZoom:16});}
    else if(fit&&selectedMonth==='all'&&buildingUids===null&&typeof group!=='undefined'&&group.getLayers().length)map.fitBounds(group.getBounds().pad(.2),{maxZoom:16});
    status(!loadingBuildings&&!layer.getLayers().length?'선택한 조건에 지도 위치가 확인된 건물이 없습니다.':'');
  }
  document.addEventListener('manager-month-filter',event=>{const month=String(event.detail?.month||'all');if(!['all','unknown',...Array.from({length:12},(_,i)=>String(i+1))].includes(month))return;if(month===selectedMonth)return;selectedMonth=month;applyMonth(true);});
  function focusUser(uid){const item=byUid.get(uid);if(!item)return false;if(!matches(item.row)){status('선택한 사용승인월에 해당하지 않습니다. 월 선택을 바꿔 주세요.');return true;}showSelection(item,true);return true;}
  document.addEventListener("manager-map-focus",event=>{const uid=event.detail?.uid;if(typeof uid!=="string")return;if(!focusUser(uid)){focusUid=uid;load();}});
  let selectedBuilding=null;
  function showSelection(item,center=false,selectionZoom=map.getZoom()){
    selectedBuilding=item.row.uid;byUid.forEach(i=>i.label.classList.toggle('mm-selected',i===item));
    if(center){
      // Center the actual building coordinate, not the popup box or a temporary
      // overlap offset. Finish the camera move before opening its anchored popup.
      map.stop();collapseShared(true);map.invalidateSize({pan:false});
      item.marker.setLatLng(item.point);
      const zoom=selectionZoom,size=map.getSize();
      const center=map.project(item.point,zoom).subtract(L.point(0,size.y/6));
      map.setView(map.unproject(center,zoom),zoom,{animate:false});
    }
    const popup=item.marker.getPopup();
    if(popup)popup.options.maxHeight=Math.max(80,Math.min(360,Math.floor(mapElement.clientHeight*2/3)-90));
    item.marker.openPopup();
    if(popup)popup.update();
  }
  function updateZoom(){mapElement.classList.toggle('mm-compact',viewMode==='icons');mapElement.classList.toggle('mm-names',viewMode==='names');}
  if(typeof map!=='undefined'&&map){map.on('zoomend',()=>{updateZoom();spreadSharedLocations();});updateZoom();}
  function mountViewToggle(){
    const host=document.getElementById('manager-map-filters');
    if(!host||host.querySelector('.mm-view-toggle'))return;
    const button=document.createElement('button');button.className='mm-view-toggle';button.type='button';button.setAttribute('aria-label','건물 이름 표시');
    button.textContent='Aa';
    function paint(){button.setAttribute('aria-pressed',String(viewMode==='names'));button.title=viewMode==='names'?'아이콘 보기로 전환':'이름 보기로 전환';}
    function toggle(event){event.preventDefault();event.stopPropagation();viewMode=viewMode==='icons'?'names':'icons';try{localStorage.setItem('manager-map-view',viewMode);}catch{}updateZoom();spreadSharedLocations();byUid.forEach(item=>item.marker.closeTooltip());paint();}
    button.addEventListener('click',toggle);
    L.DomEvent.disableClickPropagation(button);L.DomEvent.disableScrollPropagation(button);
    host.prepend(button);paint();
  }
  mountViewToggle();
  const cache=new Map(); // Address-only, in-memory cache. No member data persisted in the browser.
  const node=(tag,text,cls)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=text;if(cls)e.className=cls;return e;};
  let detailDialog=null;
  function openBuildingDetails(row,trigger){
    if(detailDialog)detailDialog.remove();
    const dialog=document.createElement('dialog');detailDialog=dialog;dialog.className='mm-detail-dialog';dialog.setAttribute('aria-labelledby','mm-detail-title');
    const head=node('header',undefined,'mm-detail-head'),copy=node('div');const title=node('h2',row.name||'건물 상세정보');title.id='mm-detail-title';copy.append(node('small','건물 상세정보'),title);
    const close=node('button','×','mm-detail-close');close.type='button';close.setAttribute('aria-label','상세정보 닫기');close.onclick=()=>dialog.close();head.append(copy,close);
    const body=node('div',undefined,'mm-detail-body');body.append(node('p',row.address||'주소 미입력','mm-detail-address'));
    const facts=node('dl',undefined,'mm-detail-facts');for(const [label,value] of [['사용승인일',row.approval_date],['대표자',row.representative],['건물 연락처',row.building_tel]]){const line=node('div');line.append(node('dt',label),node('dd',value||'미입력'));facts.append(line);}body.append(facts,node('h3','소방안전관리자'));
    const staff=Array.isArray(row.safety_managers)?row.safety_managers:[];
    if(!staff.length)body.append(node('p','등록된 안전관리자가 없습니다.','mm-detail-address'));
    for(const m of staff){const card=node('div',undefined,'mm-detail-person');card.append(node('strong',[m.name||'이름 미입력',m.type].filter(Boolean).join(' · ')),node('p',m.tel||'연락처 미입력'));body.append(card);}
    if(row.subscription?.active){const sub=row.subscription,pro=node('div',sub.test?'PRO 협업 중 · 테스트':'PRO 협업 중','mm-pro-note');pro.append(node('small','구독 시작 '+(sub.started_at||'확인 필요')));if(sub.expires_at)pro.append(node('small','이용 만료 '+sub.expires_at));body.append(pro);}
    dialog.append(head,body);document.body.append(dialog);
    const previousOverflow=document.body.style.overflow;document.body.style.overflow='hidden';
    dialog.addEventListener('close',()=>{document.body.style.overflow=previousOverflow;dialog.remove();if(detailDialog===dialog)detailDialog=null;if(trigger.isConnected)trigger.focus();},{once:true});
    dialog.showModal();close.focus();
  }
  function viewer(row){if(row.preregistered){const a=node('a','기본정보 수정','mm-view');a.href='/manager_addresses.php?id='+encodeURIComponent(row.address_id);a.dataset.addressOpen='1';return a;}const a=node('a','건물관리 화면','mm-view');a.href='/manager_view.php?uid='+encodeURIComponent(row.uid);a.target='_blank';a.rel='noopener';return a;}
  function clear(){collapseShared(true);sharedGroups.clear();byUid.clear();if(layer)layer.clearLayers();if(overlapLayer)overlapLayer.clearLayers();}
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
    loadingBuildings=true;clear();status();
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
          const tag=node('span','','mm-building-tag');
          if(row.subscription?.active){label.classList.add('mm-is-pro');const subscriptionMark=node('span','S','mm-pro-badge');subscriptionMark.setAttribute('aria-label',row.subscription.test?'테스트 구독 중':'구독 중');label.append(subscriptionMark);}
          if(!row.preregistered&&!row.subscription?.active){const connectionMark=node('span','C','mm-connection-badge');connectionMark.setAttribute('aria-label','매니저 연결 완료 · PRO 미구독');label.append(connectionMark);}
          if(row.preregistered){label.classList.add('mm-preregistered');const draftIcon=node('span','P','mm-draft-icon');draftIcon.setAttribute('aria-label','사전등록');label.append(draftIcon,tag,node('span',row.name,'mm-building-name'));}else label.append(node('span',row.name,'mm-building-name'),tag);
          const marker=L.marker(point,{icon:L.divIcon({className:'mm-marker',html:label,iconSize:null,iconAnchor:[12,14],popupAnchor:[0,-16]})});
          marker.bindTooltip(node('span',row.name||'이름 미입력'),{className:'mm-name-tooltip',direction:'top',offset:[0,-18],opacity:1,interactive:false});
          const popup=node('div',undefined,'mm-popup');popup.append(node('strong',row.name),node('p',row.address||'주소 미입력'),viewer(row));const details=node('button','건물 상세정보','mm-detail-open');details.type='button';details.setAttribute('aria-haspopup','dialog');details.onclick=()=>openBuildingDetails(row,details);
          const helpNote=node('div',undefined,'mm-help-note');helpNote.innerHTML=bellSVG;const helpCopy=node('div'),helpTitle=node('strong');helpCopy.append(helpTitle,node('small','건물관리 화면의 알림을 확인하세요.'));helpNote.append(helpCopy);popup.append(helpNote,details);marker.bindPopup(popup,{maxWidth:Math.min(300,Math.max(180,mapElement.clientWidth-60)),maxHeight:Math.min(360,Math.max(120,mapElement.clientHeight-100)),autoPan:false});
          marker.on('click',()=>{const selectionZoom=map.getZoom();document.dispatchEvent(new CustomEvent('manager-building-selected',{detail:{uid:row.uid}}));const chosen=byUid.get(row.uid);if(chosen)showSelection(chosen,true,selectionZoom);});if(matches(row))layer.addLayer(marker);const item={marker,point,row,label,tag,helpNote,helpTitle,popup};byUid.set(row.uid,item);marker.on('mouseover',()=>expandShared(item));marker.on('mouseout',scheduleSharedCollapse);marker.on('popupclose',scheduleSharedCollapse);paintHelp(item);located++;
        }
      }}
      await Promise.all([worker(),worker(),worker()]);if(current!==version)return;
      document.dispatchEvent(new CustomEvent('manager-building-locations',{detail:{points:[...byUid.values()].map(item=>({uid:item.row.uid,lat:item.point[0],lng:item.point[1]}))}}));
      loadingBuildings=false;applyMonth(pendingFit||firstFit);pendingFit=false;if(layer?.getLayers().length)firstFit=false;
      if(selectedBuilding&&byUid.has(selectedBuilding)&&matches(byUid.get(selectedBuilding).row))showSelection(byUid.get(selectedBuilding));else if(selectedBuilding){selectedBuilding=null;}

      if(focusUid){if(!focusUser(focusUid))status("이 유저의 주소 또는 지도 위치를 확인해 주세요.");focusUid=null;}
    }catch(error){if(current!==version)return;loadingBuildings=false;clear();document.dispatchEvent(new CustomEvent('manager-buildings-unavailable'));status('연결 정보를 불러오지 못했습니다. 로그인 상태를 확인하고 새로고침해 주세요.');}
  }
  if(document.readyState==='complete')load();else window.addEventListener('load',load,{once:true});
  setInterval(()=>{if(!document.hidden)load();},60000);
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)load();});
})();
