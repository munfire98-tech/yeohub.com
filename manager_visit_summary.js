(function(){
 'use strict';
 function point(r){const a=r.lat,b=r.lng;if(a===null||b===null||a===''||b===''||a===undefined||b===undefined)return null;const lat=Number(a),lng=Number(b);return Number.isFinite(lat)&&Number.isFinite(lng)&&Math.abs(lat)<=90&&Math.abs(lng)<=180&&(lat!==0||lng!==0)?[lat,lng]:null;}
 function distance(a,b){const rad=Math.PI/180,dlat=(b[0]-a[0])*rad,dlng=(b[1]-a[1])*rad;const h=Math.sin(dlat/2)**2+Math.cos(a[0]*rad)*Math.cos(b[0]*rad)*Math.sin(dlng/2)**2;return 6371*2*Math.asin(Math.sqrt(Math.min(1,Math.max(0,h))));}
 function route(rows){
   const valid=rows.map(r=>({...r,point:point(r)})).filter(r=>r.point),n=valid.length;
   if(n<2)return {rows:valid,km:0,excluded:rows.length-n};
   const d=valid.map(a=>valid.map(b=>distance(a.point,b.point)));let best=[],length=Infinity;
   const total=p=>p.slice(1).reduce((v,id,i)=>v+d[p[i]][id],0);
   // Several starting points plus bounded 2-opt: a recommendation, not a proven optimum.
   for(let s=0;s<Math.min(n,16);s++){
     const order=[Math.floor(s*n/Math.min(n,16))],left=new Set(valid.map((_,i)=>i));left.delete(order[0]);
     while(left.size){const last=order[order.length-1];let next=-1,min=Infinity;for(const i of left)if(d[last][i]<min){min=d[last][i];next=i;}order.push(next);left.delete(next);}
     if(total(order)<length){best=order;length=total(order);}
   }
   for(let pass=0;pass<3;pass++){let changed=false;for(let i=0;i<n-1;i++)for(let j=i+1;j<n;j++){
     const before=(i?d[best[i-1]][best[i]]:0)+(j<n-1?d[best[j]][best[j+1]]:0);
     const after=(i?d[best[i-1]][best[j]]:0)+(j<n-1?d[best[i]][best[j+1]]:0);
     if(after+1e-9<before){best.splice(i,j-i+1,...best.slice(i,j+1).reverse());changed=true;}
   }if(!changed)break;}
   return {rows:best.map(i=>valid[i]),km:total(best),excluded:rows.length-n};
 }
 function areaRanking(rows,ascending=false){return rows.filter(r=>typeof r.total_area==='number'&&Number.isFinite(r.total_area)&&r.total_area>0).slice().sort((a,b)=>(ascending?a.total_area-b.total_area:b.total_area-a.total_area)||String(a.name||'').localeCompare(String(b.name||''),'ko'));}
 function overview(rows){
   const known=rows.filter(r=>typeof r.total_area==='number'&&Number.isFinite(r.total_area)&&r.total_area>0);
   return {missingDongs:rows.reduce((n,r)=>n+(Number(r.area_missing_dongs)||0),0),partial:rows.filter(r=>r.total_area>0&&r.area_missing_dongs>0).length,count:rows.length,area:known.reduce((s,r)=>s+r.total_area,0),known:known.length,missing:rows.length-known.length,largest:known.reduce((a,r)=>!a||r.total_area>a.total_area?r:a,null),pre:rows.filter(r=>r.preregistered).length,pro:rows.filter(r=>!r.preregistered&&r.subscription?.active).length,linked:rows.filter(r=>!r.preregistered&&!r.subscription?.active).length};
 }
 function region(address){
   const parts=String(address||'').trim().split(/\s+/),aliases={'서울':'서울특별시','부산':'부산광역시','대구':'대구광역시','인천':'인천광역시','광주':'광주광역시','대전':'대전광역시','울산':'울산광역시','세종':'세종특별자치시','경기':'경기도','강원':'강원특별자치도','강원도':'강원특별자치도','충북':'충청북도','충남':'충청남도','전북':'전북특별자치도','전라북도':'전북특별자치도','전남':'전라남도','경북':'경상북도','경남':'경상남도','제주':'제주특별자치도','제주도':'제주특별자치도'};
   const province=aliases[parts[0]]||parts[0];
   if(province==='세종특별자치시')return province;
   if(!Object.values(aliases).includes(province))return null;
   return parts[1]&&/^[가-힣]+[시군구]$/.test(parts[1])?province+' '+parts[1]:null;
 }
 function useBuildings(rows,label){return rows.filter(r=>(String(r.building_use||'').trim()||'용도 미입력')===label).slice().sort((a,b)=>String(a.name||'').localeCompare(String(b.name||''),'ko'));}
 function insights(rows){
   const regions=new Map(),uses=new Map(),missing=[];let unclassified=0;
   for(const r of rows){const place=region(r.address);if(place)regions.set(place,(regions.get(place)||0)+1);else unclassified++;
     const use=String(r.building_use||'').trim()||'용도 미입력';uses.set(use,(uses.get(use)||0)+1);
     const gaps=[];if(!String(r.address||'').trim())gaps.push('주소');
     if(!(typeof r.total_area==='number'&&Number.isFinite(r.total_area)&&r.total_area>0))gaps.push('연면적');else if(r.area_missing_dongs>0)gaps.push('일부 동 연면적');
     if(!String(r.building_tel||'').trim()&&!(r.safety_managers||[]).some(m=>String(m.tel||'').trim()))gaps.push('연락처');
     if(gaps.length)missing.push({row:r,gaps});
   }
   const sorted=m=>[...m].sort((a,b)=>b[1]-a[1]||a[0].localeCompare(b[0],'ko'));
   return {regions:sorted(regions),uses:sorted(uses),unclassified,missing};
 }
 if(typeof module!=='undefined'&&module.exports){module.exports={point,distance,route,overview,areaRanking,region,insights,useBuildings};return;}
 let buildings=[],uids=null,located=new Map(),loading=true,dialog=null;
 const node=(tag,text,cls)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=text;if(cls)e.className=cls;return e;};
 function show(){
   if(dialog){dialog.close();return;}
   const chosen=buildings.filter(r=>!uids||uids.has(r.uid)).map(r=>({...r,...(located.get(r.uid)||{})}));
   const result=route(chosen);
   dialog=document.createElement('dialog');dialog.className='mv-dialog';dialog.setAttribute('aria-labelledby','mv-title');
   const head=node('div',undefined,'mv-head'),title=node('h2','내 담당 현황');title.id='mv-title';
   const close=node('button','×','mv-close');close.type='button';close.setAttribute('aria-label','요약 닫기');close.onclick=()=>dialog.close();head.append(title,close);dialog.append(head);
   const stats=overview(buildings),format=n=>n.toLocaleString('ko-KR',{maximumFractionDigits:1});
   dialog.append(node('p','내 전체 거래처 정보를 한눈에 확인하세요.','mv-caption'));
   const grid=node('div',undefined,'mv-stats');
   function tile(label,value,detail){const box=node('section',undefined,'mv-stat');box.append(node('span',label),node('strong',value),node('small',detail));grid.append(box);return box;}
   tile('전체 거래처',stats.count.toLocaleString()+'곳','사전등록과 연결된 거래처 포함');
   tile('등록 연면적 합계',stats.known?format(stats.area)+' ㎡':'정보 없음',stats.known+'곳 합산 · '+stats.missing+'곳 미입력');
   tile('거래처 평균 연면적',stats.known?format(stats.area/stats.known)+' ㎡':'정보 없음','면적이 입력된 거래처 기준');
   const largestCard=tile('가장 큰 거래처',stats.largest?format(stats.largest.total_area)+' ㎡':'정보 없음',stats.largest?.name||'연면적 입력 후 확인할 수 있어요');
   const rankButton=node('button','연면적 순서 보기','mv-rank-toggle');rankButton.type='button';rankButton.setAttribute('aria-expanded','false');rankButton.setAttribute('aria-controls','mv-area-ranking');largestCard.append(rankButton);
   const rankPanel=node('section',undefined,'mv-ranking');rankPanel.id='mv-area-ranking';rankPanel.hidden=true;
   const rankHeader=node('div',undefined,'mv-rank-header');rankHeader.append(node('h3','거래처 연면적 순서'));
   const sortLabel=node('label','정렬 '),sort=document.createElement('select');sort.setAttribute('aria-label','연면적 정렬 순서');
   [['desc','큰 면적순'],['asc','작은 면적순']].forEach(([value,text])=>{const option=node('option',text);option.value=value;sort.append(option);});sortLabel.append(sort);rankHeader.append(sortLabel);rankPanel.append(rankHeader);
   const rankList=node('ol',undefined,'mv-rank-list');rankPanel.append(node('p','전체 거래처 · 동별 합산 연면적 기준 · 면적 미입력 '+stats.missing+'곳 제외','mv-caption'),rankList);
   function renderRanking(){
     rankList.replaceChildren();const ranked=areaRanking(buildings,sort.value==='asc');
     if(!ranked.length){rankList.append(node('li','연면적이 입력된 거래처가 없습니다.','mv-rank-empty'));return;}
     ranked.forEach((r,i)=>{
       const li=node('li'),number=node('span',String(i+1),'mv-rank-number'),body=node('div',undefined,'mv-rank-body'),line=node('div',undefined,'mv-rank-line');
       line.append(node('strong',r.name||'이름 미입력'),node('b',format(r.total_area)+' ㎡'));body.append(line);
       if(r.area_missing_dongs>0)body.append(node('small','일부 합산 · '+r.area_missing_dongs+'개 동 면적 미입력','mv-rank-partial'));
       const share=stats.area>0?r.total_area/stats.area*100:0,track=node('div',undefined,'mv-rank-track'),fill=node('span');fill.style.width=Math.min(100,share)+'%';track.setAttribute('aria-hidden','true');track.append(fill);body.append(track,node('small','전체 등록 연면적의 '+share.toLocaleString('ko-KR',{maximumFractionDigits:1})+'%'));li.append(number,body);rankList.append(li);
     });
   }
   sort.onchange=renderRanking;
   rankButton.onclick=()=>{rankPanel.hidden=!rankPanel.hidden;rankButton.setAttribute('aria-expanded',String(!rankPanel.hidden));rankButton.textContent=rankPanel.hidden?'연면적 순서 보기':'연면적 순서 닫기';if(!rankPanel.hidden){renderRanking();requestAnimationFrame(()=>{dialog.scrollTo({top:dialog.scrollTop+rankPanel.getBoundingClientRect().top-dialog.getBoundingClientRect().top-24,behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});});}};
   dialog.append(grid,rankPanel);
   const distribution=node('div',undefined,'mv-distribution');['사전등록 '+stats.pre+'곳','매니저 연결 '+stats.linked+'곳','PRO 이용 '+stats.pro+'곳'].forEach(t=>distribution.append(node('span',t)));dialog.append(distribution);
   dialog.append(node('p','동별 현황이 있으면 표시된 모든 동의 연면적을 합산합니다. 기준동은 기본정보의 최신 면적을 한 번만 포함하며, 동별 현황이 없으면 기본정보 연면적을 사용합니다. 중복 등록된 거래처는 각각 포함됩니다. 위 현황은 전체 거래처 기준입니다.','mv-note'));
   if(stats.missingDongs)dialog.append(node('p','연면적이 없는 '+stats.missingDongs+'개 동은 합계에서 제외했습니다. '+stats.partial+'개 거래처는 일부 동의 면적만 반영되어 합계·평균·최대 면적이 실제보다 작을 수 있습니다.','mv-warning'));
   const profile=insights(buildings),profileGrid=node('div',undefined,'mv-profile-grid');
   function profileSection(title){const section=node('section',undefined,'mv-profile-section');section.append(node('h3',title));profileGrid.append(section);return section;}
   const useButtons=[],usePanel=node('section',undefined,'mv-use-panel');usePanel.hidden=true;usePanel.id='mv-use-buildings';let selectedUse=null;
   function openUse(label){
     selectedUse=selectedUse===label?null:label;usePanel.hidden=selectedUse===null;
     useButtons.forEach(({button,label:l})=>button.setAttribute('aria-expanded',String(l===selectedUse)));
     if(selectedUse===null)return;
     usePanel.replaceChildren();const members=useBuildings(buildings,label),header=node('div',undefined,'mv-use-header');
     header.append(node('h3',label+' · '+members.length+'곳'));const closeList=node('button','접기');closeList.type='button';closeList.onclick=()=>{openUse(label);useButtons.find(v=>v.label===label)?.button.focus();};header.append(closeList);usePanel.append(header);
     const list=node('ul',undefined,'mv-use-list');members.forEach(r=>{const item=node('li'),text=node('div');text.append(node('strong',r.name||'이름 미입력'),node('small',r.address||'주소 미입력'));
       const info=[];if(typeof r.total_area==='number'&&r.total_area>0)info.push(format(r.total_area)+' ㎡'+(r.area_missing_dongs>0?' · 일부 합산':''));info.push(r.preregistered?'사전등록':r.subscription?.active?'PRO 이용':'매니저 연결');text.append(node('small',info.join(' · ')));
       const link=node('a','정보 확인');link.href=r.preregistered?'/manager_addresses.php?id='+encodeURIComponent(r.address_id):'/manager_view.php?uid='+encodeURIComponent(r.uid);link.target='_blank';link.rel='noopener';link.setAttribute('aria-label',(r.name||'거래처')+' 정보 확인 (새 창)');item.append(text,link);list.append(item);});usePanel.append(list);
     requestAnimationFrame(()=>{if(!dialog||usePanel.hidden)return;dialog.scrollTo({top:dialog.scrollTop+usePanel.getBoundingClientRect().top-dialog.getBoundingClientRect().top-24,behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});});
   }
   function bars(section,items,interactive=false){const list=node('ul',undefined,'mv-profile-bars');items.forEach(([label,count])=>{const item=node('li'),line=node(interactive?'button':'div');
     if(interactive){line.type='button';line.className='mv-use-button';line.setAttribute('aria-expanded','false');line.setAttribute('aria-controls','mv-use-buildings');line.setAttribute('aria-label',label+' 거래처 '+count+'곳 보기');line.onclick=()=>openUse(label);useButtons.push({button:line,label});}
     line.append(node('span',label),node('b',count+'곳'+(interactive?' ›':'')));const track=node('div',undefined,'mv-rank-track'),fill=node('span');fill.style.width=(buildings.length?count/buildings.length*100:0)+'%';track.setAttribute('aria-hidden','true');track.append(fill);item.append(line,track);list.append(item);});section.append(list);}
   const regions=profileSection('관리 지역 TOP 3');bars(regions,profile.regions.slice(0,3));
   regions.append(node('p',profile.regions.length?'주소의 시·군·구 기준 · '+profile.regions.length+'개 지역':'분류 가능한 주소가 없습니다.','mv-caption'));
   if(profile.unclassified)regions.append(node('p','주소 미입력·분류 불가 '+profile.unclassified+'곳 제외','mv-caption'));
   const uses=profileSection('건물 용도 구성');bars(uses,profile.uses.slice(0,4),true);
   if(profile.uses.length>4){const more=node('details'),toggle=node('summary','전체 용도 보기');more.append(toggle);bars(more,profile.uses.slice(4),true);uses.append(more);}
   uses.append(node('p','용도를 누르면 거래처 목록이 펼쳐집니다. 기본정보 주용도 기준으로 거래처당 1회 집계합니다.','mv-caption'));dialog.append(profileGrid,usePanel);
   const incomplete=node('details',undefined,'mv-incomplete'),incompleteToggle=node('summary','정보 보완이 필요한 거래처 '+profile.missing.length+'곳');incomplete.append(incompleteToggle,node('p','주소 · 연면적(동별 누락 포함) · 연락처를 확인합니다. 건물 또는 안전관리자 연락처 중 하나가 있으면 연락처 입력으로 봅니다.','mv-caption'));
   const incompleteList=node('ul',undefined,'mv-incomplete-list');
   profile.missing.forEach(({row:r,gaps})=>{const li=node('li'),text=node('div');text.append(node('strong',r.name||'이름 미입력'),node('small',gaps.join(' · ')+' 보완 필요'));const link=node('a','확인');link.href=r.preregistered?'/manager_addresses.php?id='+encodeURIComponent(r.address_id):'/manager_view.php?uid='+encodeURIComponent(r.uid);link.target='_blank';link.rel='noopener';link.setAttribute('aria-label',(r.name||'거래처')+' 정보 확인 (새 창)');li.append(text,link);incompleteList.append(li);});
   if(!profile.missing.length)incomplete.append(node('p',buildings.length?'확인 대상 항목이 모두 입력되어 있습니다.':'등록된 거래처가 없습니다.','mv-caption'));
   incomplete.append(incompleteList);dialog.append(incomplete);
   dialog.append(node('h3','월 1회 방문 기준 예상 이동거리','mv-section-title'));
   dialog.append(node('p','현재 지도 필터·검색에 포함된 건물을 각각 한 번 방문하는 기준입니다.','mv-caption'));
   const metric=node('div',undefined,'mv-metric');metric.append(node('strong',result.rows.length>=2?'약 '+result.km.toLocaleString('ko-KR',{maximumFractionDigits:1})+' km':'계산 대상 부족'),node('span','직선거리 기준 · '+result.rows.length+'개 건물'));dialog.append(metric);
   dialog.append(node('p','실제 도로 주행거리와 다릅니다. 출발·복귀 및 날짜별 이동은 제외됩니다. 방문 순서는 추천 순서이며 최단 경로를 보장하지 않습니다.','mv-note'));
   if(loading)dialog.append(node('p','지도 위치 확인 중입니다. 완료 후 요약을 다시 열면 갱신됩니다.','mv-warning'));
   if(result.excluded)dialog.append(node('p','위치가 없는 '+result.excluded+'개 건물은 계산에서 제외했습니다.','mv-warning'));
   if(result.rows.length<2)dialog.append(node('p','위치가 확인된 건물이 2개 이상일 때 거리를 계산합니다.','mv-note'));
   if(result.rows.length>=2){
     const ns='http://www.w3.org/2000/svg',svg=document.createElementNS(ns,'svg');svg.setAttribute('viewBox','0 0 600 200');svg.setAttribute('role','img');svg.setAttribute('aria-label','추천 방문 순서의 직선 연결도');
     const pts=result.rows.map(r=>[r.point[1]*Math.cos(result.rows[0].point[0]*Math.PI/180),-r.point[0]]),xs=pts.map(p=>p[0]),ys=pts.map(p=>p[1]),minX=Math.min(...xs),minY=Math.min(...ys),width=Math.max(...xs)-minX,height=Math.max(...ys)-minY,scale=Math.min(550/(width||1e-9),150/(height||1e-9));
     const project=p=>[300+(p[0]-minX-width/2)*scale,100+(p[1]-minY-height/2)*scale];
     const line=document.createElementNS(ns,'polyline');line.setAttribute('points',pts.map(p=>project(p).join(',')).join(' '));line.setAttribute('fill','none');line.setAttribute('stroke','#4e78ca');line.setAttribute('stroke-width','2');svg.append(line);
     pts.forEach((p,i)=>{const [x,y]=project(p),c=document.createElementNS(ns,'circle');c.setAttribute('cx',x);c.setAttribute('cy',y);c.setAttribute('r',i===0||i===pts.length-1?'6':'3');c.setAttribute('fill',i===0?'#269b79':i===pts.length-1?'#da7751':'#4e78ca');svg.append(c);});
     dialog.append(svg,node('p','직선 연결도 · 초록: 첫 방문 / 주황: 마지막 방문','mv-caption'));
   }
   const list=node('ol',undefined,'mv-order');result.rows.forEach(r=>{const item=node('li');item.append(node('strong',r.name||'이름 미입력'),node('small',r.address||'주소 미입력'));list.append(item);});dialog.append(list);
   const trigger=document.activeElement;dialog.addEventListener('close',()=>{dialog.remove();dialog=null;trigger?.focus();});document.body.append(dialog);dialog.showModal();close.focus();
 }
 function mount(){const host=document.querySelector('.mb-workspace');if(!host||host.querySelector('.mv-open'))return;const b=node('button','요약','mv-open');b.type='button';b.onclick=show;host.prepend(b);}
 document.addEventListener('manager-buildings-updated',e=>{buildings=e.detail.buildings||[];located.clear();loading=true;mount();});
 document.addEventListener('manager-building-filter',e=>{uids=new Set(e.detail.uids||[]);mount();});
 document.addEventListener('manager-building-locations',e=>{located=new Map(e.detail.points.map(p=>[p.uid,{lat:p.lat,lng:p.lng}]));loading=false;});
 document.addEventListener('manager-buildings-unavailable',()=>{buildings=[];located.clear();loading=false;if(dialog)dialog.close();});
 mount();
})();
