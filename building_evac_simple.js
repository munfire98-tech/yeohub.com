(()=>{
 if(!HOSTED)return;
 document.body.classList.add('bes-simple');
 const pane=document.getElementById('paneEdit'),toolBlock=pane.querySelector('[data-tool]').closest('.block');
 const icons={room:'<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M10 20v-5h4v5"/>',door:'<path d="M5 20V4h14v16M9 20V7l7-2v15M12 13h1"/>',stair:'<path d="M3 20h6v-5h5v-5h6V4M3 4h7M3 4v7M3 4l8 8"/>',exit:'<path d="M10 4H4v16h6M10 12h11m-4-4 4 4-4 4"/>'};
 const hints={room:['방 그리기','도면의 빈 곳에서 누른 채 대각선으로 끌어보세요.','사각형 둘레에 벽과 출입문이 함께 만들어집니다.'],door:['문 놓기','벽에서 문을 낼 위치를 누르세요.','문 한 칸을 놓습니다. 넓히려면 옆 칸을 눌러 주세요.'],stair:['계단 그리기','계단실이 들어갈 영역을 사각형으로 끌어보세요.','모든 층의 같은 위치에 계단실이 함께 배치됩니다.'],exit:['출구 놓기','실외로 나가는 출구 위치를 누르세요.','실제 출구와 같은 위치에 배치해 주세요.'],wall:['벽 편집','그리는 방식을 선택해 벽을 그리세요.','잘못 그리면 실행취소로 되돌릴 수 있습니다.'],floor:['바닥 편집','통로로 사용할 영역을 그려 주세요.','벽을 바닥으로 바꾸어 통로를 만들 수 있습니다.'],erase:['영역 지우기','지울 영역을 사각형으로 끌어 주세요.','지운 영역은 통행할 수 없는 공간이 됩니다.'],poly:['연결선 그리기','꼭짓점을 차례대로 누르고 Enter로 마칩니다.','연결선은 벽을 그리는 도구입니다.'],hydrant:['소화전 놓기','실제 소화전 위치를 도면에 표시하세요.','필요한 경우 세부 편집에서 그리는 방식을 바꿀 수 있습니다.']};
 const intro=pane.querySelector('.bes-edit-guide');intro.classList.add('bes-simple-intro');intro.innerHTML='<span class="bes-kicker">도면 만들기</span><strong>방부터 하나씩 놓아보세요</strong><p>층 선택 → 방과 통로 → 계단과 출구</p>';
 const grid=document.createElement('div');grid.className='bes-main-tools';
 for(const key of ['room','door','stair','exit']){const b=toolBlock.querySelector('[data-tool="'+key+'"]');b.innerHTML='<svg viewBox="0 0 24 24" aria-hidden="true">'+icons[key]+'</svg><span>'+hints[key][0]+'</span>';b.onclick=()=>{window.besCancelRouteDrawing?.();selectTool(key);if(key==='door'||key==='exit'){brushSize=1;selectShape('free');}refresh();};grid.append(b);}
 const hint=document.createElement('div');hint.className='bes-tool-help';hint.setAttribute('role','status');hint.innerHTML='<strong></strong><p></p><small></small>';
 const advanced=document.createElement('details');advanced.className='bes-advanced bes-precision';const summary=document.createElement('summary');summary.textContent='세부 편집 · 벽, 통로, 지우기';advanced.append(summary);toolBlock.before(grid,hint,advanced);advanced.append(toolBlock);
 const oldGuide=pane.querySelector('.bes-edit-guide:not(.bes-simple-intro)');if(oldGuide)oldGuide.classList.add('bes-route-panel');
 // Keep the engine controls and their event handlers, but group less frequent settings.
 for(const block of [...pane.querySelectorAll(':scope > .block')]){const title=block.querySelector('.eyebrow')?.textContent.trim();if(!['층','피난 진단'].includes(title))continue;const details=document.createElement('details');details.className='bes-advanced';const heading=document.createElement('summary');heading.textContent=title==='층'?'층 관리 · 추가와 복사':'연결 상태 확인 · 출구와 고립 구역';block.before(details);details.append(heading,block);}
 const sticky=pane.querySelector('.stickybar');intro.after(sticky);sticky.after(grid,hint,advanced);
 let last='';function refresh(){if(last===editTool)return;last=editTool;const h=hints[editTool]||['도면 편집','도구를 선택하세요.',''];hint.querySelector('strong').textContent=h[0];hint.querySelector('p').textContent=h[1];hint.querySelector('small').textContent=h[2];grid.querySelectorAll('button').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.tool===editTool)));}
 document.querySelectorAll('[data-tool]').forEach(b=>b.addEventListener('click',refresh));
 document.addEventListener('keydown',()=>setTimeout(refresh,0));
 selectTool('room');refresh();
 const route=pane.querySelector('.bes-route-panel');if(route){const detail=document.createElement('details');detail.className='bes-advanced';const title=document.createElement('summary');title.textContent='피난 안내 화살표 그리기';route.before(detail);detail.append(title,route);}
})();
