<?php
header('Cache-Control: no-store');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>비화재보 기록 인쇄</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f1f5f9;color:#172b40;font:14px/1.6 system-ui,sans-serif}.toolbar{max-width:1000px;margin:20px auto;padding:0 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.toolbar p{margin:0;font-size:13px;color:#60748a}.toolbar button{border:0;border-radius:9px;padding:11px 18px;background:#285fb3;color:white;font:600 14px system-ui;cursor:pointer}.toolbar button:disabled{opacity:.5;cursor:wait}.sheet{max-width:1000px;margin:0 auto 40px;background:#fff;padding:36px;box-shadow:0 3px 20px #21354a10}h1{text-align:center;font-size:26px;margin:0 0 24px}.meta{border-top:2px solid #243f5b;border-bottom:1px solid #d4dde7;padding:12px 0;margin-bottom:20px;display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap}.meta strong{overflow-wrap:anywhere}.meta span{color:#64788b;font-size:12px}table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{border:1px solid #cbd5e1;padding:9px 8px;text-align:left;vertical-align:top;overflow-wrap:anywhere;white-space:pre-wrap}th{background:#edf2f7;font-size:12px;color:#344b63}td{font-size:12px}thead{display:table-header-group}tr{break-inside:avoid}td small{display:block;color:#637587;font-size:10px;margin-top:5px}.foot{font-size:11px;color:#6c7f92;margin-top:18px}.error{padding:18px;background:#fff4ee;color:#8d4624}.print-content[hidden]{display:none!important}@media(max-width:600px){.sheet{padding:16px}.table-wrap{overflow-x:auto}table{min-width:680px}}@page{size:A4 landscape;margin:12mm}@media print{body{background:white}.toolbar{display:none}.sheet{margin:0;padding:0;max-width:none;box-shadow:none}.table-wrap{overflow:visible}table{min-width:0}h1{font-size:21px;margin-bottom:14px}th,td{font-size:10px;padding:7px}th{print-color-adjust:exact;-webkit-print-color-adjust:exact}.meta{margin-bottom:12px}.foot{font-size:9px}}
</style></head><body>
<div class="toolbar"><p>저장된 기록 전체를 인쇄합니다. 인쇄 화면에서 PDF로 저장할 수 있습니다.</p><button id="print" disabled>인쇄 / PDF 저장</button></div>
<main class="sheet"><h1>비화재보 기록</h1><p id="status" role="status">저장된 기록을 불러오는 중입니다.</p><div class="print-content" hidden><div class="meta"><strong id="building"></strong><span id="summary"></span></div><div class="table-wrap"><table><colgroup><col style="width:5%"><col style="width:14%"><col style="width:15%"><col style="width:19%"><col style="width:29%"><col style="width:18%"></colgroup><thead><tr><th>번호</th><th>발생일시</th><th>발생 위치</th><th>추정 원인</th><th>조치 내용</th><th>처리 상태 / 작성자</th></tr></thead><tbody id="records"></tbody></table></div><p class="foot">소방계획서.com · 입력된 현장 기록을 기준으로 출력한 자료입니다.</p></div></main>
<script>
(async()=>{
 const status=document.getElementById('status'),button=document.getElementById('print');button.onclick=()=>window.print();
 try{
  const uid=new URLSearchParams(location.search).get('uid')||'';if(!/^[A-Za-z0-9_-]{1,64}$/.test(uid))throw Error('건물 정보를 확인해 주세요. 기록 팝업에서 다시 열어주세요.');
  const response=await fetch('/false_alarm_api.php?uid='+encodeURIComponent(uid),{credentials:'same-origin',cache:'no-store'});const data=await response.json();if(!response.ok||!data.ok)throw Error(data.error||'기록을 불러오지 못했습니다.');if(!data.rows.length)throw Error('인쇄할 기록이 없습니다.');
  document.getElementById('building').textContent=data.name;
  document.getElementById('summary').textContent='총 '+data.count+'건 · 출력 기준 '+new Date().toLocaleString('ko-KR',{timeZone:'Asia/Seoul'});
  const body=document.getElementById('records');data.rows.forEach((row,i)=>{const tr=document.createElement('tr');for(const value of [String(i+1),row.occurred_at.replace('T',' '),row.location,row.cause||'미확인',row.action,row.status]){const td=document.createElement('td');td.textContent=value;tr.append(td);}const note=document.createElement('small');note.textContent='작성: '+row.created_name+'\n최근 수정: '+row.updated_name+'\n'+new Date(row.updated_at).toLocaleString('ko-KR',{timeZone:'Asia/Seoul'});tr.lastChild.append(note);body.append(tr);});
  status.hidden=true;document.querySelector('.print-content').hidden=false;button.disabled=false;
 }catch(e){status.className='error';status.textContent=e.message||'기록을 불러오지 못했습니다.';}
})();
</script></body></html>
