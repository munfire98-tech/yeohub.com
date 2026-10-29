(()=>{
 if(!HOSTED)return;
 window.besRoutes=JSON.parse(HOST_SCN||'{}').routes||[];
 let drawing=false,points=[],floor=0;
 window.besCancelRouteDrawing=()=>{drawing=false;points=[];};
 const validity=new WeakMap();
 function cachedValid(r){const hit=validity.get(r);if(hit&&hit.map===customMapText&&hit.floors===FLOORS)return hit.ok;const ok=valid(r);validity.set(r,{map:customMapText,floors:FLOORS,ok});return ok;}
 const panel=document.createElement('section');panel.className='bes-edit-guide';panel.innerHTML='<strong>피난 안내 경로</strong><p>2D 도면의 출발점부터 복도·출구 순서로 눌러 화살표를 그립니다. 층별로 저장하며, 시뮬레이션 인원은 기존 자동 경로 계산을 따릅니다.</p><input aria-label="경로 이름" maxlength="60" placeholder="예: 1층 주 출입구 경로"><div class="bes-route-buttons"><button type="button" data-new>경로 그리기</button><button type="button" data-back>한 점 취소</button><button type="button" data-done>경로 저장</button><button type="button" data-cancel>그리기 취소</button></div><p role="status"></p><div data-list></div>';
 document.getElementById('paneEdit').append(panel);
 const status=panel.querySelector('[role="status"]');
 function validSegment(a,b,f){const count=Math.max(Math.abs(a[0]-b[0]),Math.abs(a[1]-b[1]))*2+1;for(let i=0;i<=count;i++){const x=Math.round(a[0]+(b[0]-a[0])*i/count),y=Math.round(a[1]+(b[1]-a[1])*i/count);if(!inBounds(x,y)||!grids[f]||grids[f][y][x]===WALL)return false;}return true;}
 function valid(r){return r.floor<FLOORS&&r.points.every((p,i)=>validSegment(i?r.points[i-1]:p,p,r.floor));}
 function list(){const el=panel.querySelector('[data-list]');el.replaceChildren();window.besRoutes.forEach((r,i)=>{const row=document.createElement('div'),b=document.createElement('button'),del=document.createElement('button');row.className='bes-route-row';b.textContent=r.name+(cachedValid(r)?'':' · 도면 변경 확인 필요');b.onclick=()=>{showPane(true);viewF=r.floor<FLOORS?r.floor:0;};del.textContent='삭제';del.onclick=()=>{if(!confirm('이 안내 경로를 삭제할까요?'))return;window.besRoutes.splice(i,1);autoSave();list();};row.append(b,del);el.append(row);});}
 panel.querySelector('[data-new]').onclick=()=>{if(window.besRoutes.length>=30){status.textContent='경로는 최대 30개까지 저장할 수 있습니다.';return;}drawing=true;points=[];floor=viewF;status.textContent='현재 층에서 출발 위치를 누르세요. 끝나면 경로 저장을 누르세요.';};
 panel.querySelector('[data-back]').onclick=()=>{points.pop();};
 panel.querySelector('[data-cancel]').onclick=()=>{drawing=false;points=[];status.textContent='그리기를 취소했습니다.';};
 panel.querySelector('[data-done]').onclick=()=>{if(!drawing||points.length<2){status.textContent='출발점과 도착점을 포함해 두 곳 이상 눌러 주세요.';return;}window.besRoutes.push({name:panel.querySelector('input').value.trim()||'안내 경로 '+(window.besRoutes.length+1),floor,points:points.map(p=>[...p])});drawing=false;points=[];autoSave();list();status.textContent='안내 경로를 추가했습니다. 상단 저장 상태를 확인해 주세요.';};
 cv.addEventListener('pointerdown',e=>{if(!drawing||!editMode)return;e.preventDefault();e.stopImmediatePropagation();if(viewF!==floor){status.textContent='그리기를 시작한 층으로 돌아가거나 취소 후 다시 시작해 주세요.';return;}const p=cellFromEvent(e),last=points.at(-1)||p;if(!validSegment(last,p,floor)){status.textContent='벽을 통과할 수 없습니다. 문이나 복도를 따라 점을 추가하세요.';return;}if(points.length>=400)return;points.push(p);status.textContent=points.length+'개 지점 · 끝났으면 경로 저장을 누르세요.';},true);
 for(const event of ['pointermove','pointerup'])cv.addEventListener(event,e=>{if(drawing&&editMode)e.stopImmediatePropagation();},true);
 window.besDrawRoutes=()=>{ctx.save();ctx.setTransform(1,0,0,1,0,0);ctx.translate(PLAN_PAD,PLAN_PAD);for(const r of [...window.besRoutes,...(drawing?[{floor,points}]:[])]){if(r.floor!==viewF||r.points.length<1)continue;ctx.strokeStyle=(r.points===points||cachedValid(r))?'#00a884':'#e66b36';ctx.fillStyle=ctx.strokeStyle;ctx.lineWidth=3;for(let i=1;i<r.points.length;i++){const a=r.points[i-1].map(n=>(n+.5)*CELL),b=r.points[i].map(n=>(n+.5)*CELL),angle=Math.atan2(b[1]-a[1],b[0]-a[0]);ctx.beginPath();ctx.moveTo(...a);ctx.lineTo(...b);ctx.stroke();ctx.beginPath();ctx.moveTo(...b);ctx.lineTo(b[0]-9*Math.cos(angle-.5),b[1]-9*Math.sin(angle-.5));ctx.lineTo(b[0]-9*Math.cos(angle+.5),b[1]-9*Math.sin(angle+.5));ctx.closePath();ctx.fill();}const a=r.points[0];ctx.beginPath();ctx.arc((a[0]+.5)*CELL,(a[1]+.5)*CELL,4,0,Math.PI*2);ctx.fill();}ctx.restore();};
 let lastMap=customMapText;setInterval(()=>{if(lastMap!==customMapText){lastMap=customMapText;list();}},1000);
 list();
})();
