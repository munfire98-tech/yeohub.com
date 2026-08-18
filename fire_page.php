<?php
/* 자위소방대 편성표 전용 페이지 — clients_mini.php 에서 require 됨.
   $clients, $CSRF, $DATA_DIR 등은 상위 스코프에서 이미 정의되어 있다. */
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
$cl_for_select = $clients;
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>자위소방대 편성표</title>
<style>
  :root{
    --red:#c0392b; --red-d:#a5302492; --navy:#3a5572; --ink:#1f2430;
    --line:#9aa0ab; --mut:#6b7280; --bg:#f1f4f8; --card:#fff; --tintR:#fbeeec;
  }
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Malgun Gothic','Apple SD Gothic Neo',sans-serif;background:var(--bg);color:var(--ink);font-size:14px;line-height:1.5}
  .wrap{max-width:1100px;margin:0 auto;padding:18px 16px 80px}
  .topbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px}
  .topbar h1{font-size:20px;font-weight:800;letter-spacing:1px}
  .topbar .back{margin-left:auto;font-size:13px;color:var(--navy);text-decoration:none;border:1px solid #cdd3dd;border-radius:8px;padding:7px 12px;background:#fff}
  .panel{background:var(--card);border:1px solid #e1e5ec;border-radius:12px;padding:16px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
  .panel h2{font-size:15px;font-weight:700;margin-bottom:10px;display:flex;align-items:center;gap:7px}
  .panel h2 .dot{width:5px;height:16px;background:var(--navy);border-radius:2px;display:inline-block}
  label.fld{display:block;font-size:12px;color:var(--mut);margin:8px 0 3px;font-weight:600}
  input[type=text],textarea,select{width:100%;border:1px solid #cdd3dd;border-radius:8px;padding:8px 10px;font-size:13px;font-family:inherit;background:#fff}
  textarea{resize:vertical}
  .row{display:flex;gap:12px;flex-wrap:wrap}
  .row > div{flex:1;min-width:160px}
  .btn{border:none;border-radius:8px;padding:9px 16px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit}
  .btn-primary{background:var(--red);color:#fff}
  .btn-navy{background:var(--navy);color:#fff}
  .btn-ghost{background:#fff;border:1px solid #cdd3dd;color:var(--ink)}
  .btn-sm{padding:5px 10px;font-size:12px}
  .hint{font-size:12px;color:var(--mut);margin-top:4px}
  .planlist{display:flex;flex-direction:column;gap:8px;max-height:320px;overflow-y:auto}
  .plan-card{display:flex;align-items:center;gap:10px;border:1px solid #dde2ea;border-radius:9px;padding:10px 12px;background:#fafbfc}
  .plan-card.active{border-color:var(--red);background:#fdf3f2}
  .plan-card .pc-main{flex:1;min-width:0}
  .plan-card .pc-name{font-weight:700;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .plan-card .pc-meta{font-size:11px;color:var(--mut);margin-top:2px}
  .plan-card .pc-btns{display:flex;gap:5px;flex-shrink:0}
  .pcbtn{border:1px solid #cdd3dd;background:#fff;border-radius:6px;padding:5px 9px;font-size:12px;cursor:pointer;font-family:inherit}
  .pcbtn.load{border-color:var(--navy);color:var(--navy);font-weight:700}
  .pcbtn.del{border-color:#e0a0a0;color:#c0392b}
  .planlist .empty{font-size:13px;color:var(--mut);padding:14px;text-align:center}
  .actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}

  /* 편성표 미리보기/편집 영역 */
  .org table{width:100%;border-collapse:collapse;font-size:13px}
  .org th,.org td{border:1px solid var(--line);padding:5px 7px;vertical-align:middle}
  .cmd-head th{background:var(--red);color:#fff;text-align:center;font-weight:700}
  .role-cell{background:var(--tintR);font-weight:700;text-align:center;white-space:nowrap}
  .grp-row td{background:var(--navy);color:#fff;font-weight:700}
  .grp-row .gname{display:inline-block;min-width:120px}
  .org input[type=text]{border:1px solid transparent;background:transparent;padding:4px 6px}
  .org input[type=text]:focus{border-color:#cdd3dd;background:#fff}
  td.ctr{text-align:center}
  .mini{width:34px;text-align:center}
  .delbtn{background:#fff;border:1px solid #e0a0a0;color:#c0392b;border-radius:6px;cursor:pointer;font-size:11px;padding:2px 7px}
  .addbtn{background:#eef3f8;border:1px dashed #9fb3c8;color:var(--navy);border-radius:7px;cursor:pointer;font-size:12px;padding:5px 10px;font-weight:600}

  /* 사진 */
  .photos{display:flex;gap:14px;flex-wrap:wrap}
  .photo-slot{flex:1;min-width:240px;border:1px dashed #b8c0cc;border-radius:10px;padding:10px;text-align:center;background:#fafbfc}
  .photo-slot img{max-width:100%;max-height:180px;border-radius:6px;object-fit:contain}
  .photo-slot .ph{color:#9aa3af;padding:30px 0;font-size:13px}
  .toast{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:#1f2430;color:#fff;padding:10px 18px;border-radius:10px;font-size:13px;opacity:0;transition:.25s;z-index:50}
  .toast.show{opacity:1}
  .savebar{position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:1px solid #e1e5ec;padding:11px 16px;display:flex;gap:10px;justify-content:center;box-shadow:0 -2px 8px rgba(0,0,0,.05);z-index:40}
  @media print{
    @page{ margin:12mm 10mm; }
    .no-print{display:none!important}
    body{background:#fff}
    .wrap{max-width:100%;padding:0}
    .panel{border:none;box-shadow:none;padding:0;margin:0 0 10px}
    .print-only{display:block!important}
    .org table{font-size:12px}
    .org th,.org td{padding:4px 6px}
    .org input[type=text]{font-size:12px}
    .photo-slot{break-inside:avoid;border:1px solid #cdd3dd;min-height:230px;display:flex;flex-direction:column;justify-content:center}
    .photo-slot .ph{color:#bbb}
    .page-break{page-break-before:always}
    .print-sign{page-break-inside:avoid}
  }
  .print-only{display:none}
  .print-head{text-align:center;margin-bottom:8px}
  .print-head .ttl{font-size:24px;font-weight:800;letter-spacing:8px}
  .print-head .ttl-sub{font-size:11px;color:#444;letter-spacing:2px}
  .print-head hr{border:none;border-top:2px solid #2b2b2b;margin:6px 0}
  .print-meta{display:flex;justify-content:space-between;font-weight:700;font-size:12px;margin:4px 2px 8px}
  .print-sign{text-align:right;font-size:12px;margin-top:14px;line-height:2}
  .print-notes{font-size:10px;color:#444;line-height:1.6;margin-top:12px}

</style>
</head>
<body>
<div class="wrap">
  <div class="topbar no-print">
    <h1>🧯 자위소방대 편성표</h1>
    <a class="back" href="?">← 대시보드로</a>
  </div>

  <!-- 대상물 선택 -->
  <div class="panel no-print">
    <h2><span class="dot"></span>① 저장된 편성표
      <button class="btn btn-ghost btn-sm" style="margin-left:auto" onclick="newPlan()">＋ 새로 만들기</button>
    </h2>
    <div id="planList" class="planlist"><div class="hint">불러오는 중…</div></div>
    <div class="row" style="margin-top:14px">
      <div>
        <label class="fld">대상물명 (표에 표시)</label>
        <input type="text" id="siteName" placeholder="예: 신김포농협 대곶지점">
      </div>
      <div>
        <label class="fld">근무형태</label>
        <input type="text" id="workType" value="단일근무(주간)">
      </div>
    </div>
    <div class="hint" id="editingHint" style="margin-top:6px"></div>
  </div>

  <!-- 이름 붙여넣기 -->
  <div class="panel no-print">
    <h2><span class="dot"></span>② 인원 붙여넣기 → 자동 배치</h2>
    <label class="fld">이름(과 연락처·직급)을 한 줄에 한 명씩 붙여넣으세요</label>
    <textarea id="bulkInput" rows="6" placeholder="예시)
정영모 010-2636-3385 지점장
이명숙 010-9032-7704 차장
김동환 과장
양연주
…   (이름만 적어도 됩니다. 연락처·직급은 있으면 자동 인식)"></textarea>
    <div class="hint">맨 윗줄=대장, 둘째 줄=부대장, 나머지는 활동조에 순서대로 자동 배치됩니다. 배치 후 아래 표에서 자유롭게 수정하세요.</div>
    <div class="actions">
      <button class="btn btn-primary" onclick="autoAssign()">⚡ 자동 배치</button>
      <button class="btn btn-ghost btn-sm" onclick="clearAll()">표 비우기</button>
    </div>
  </div>

  <!-- 편성표 편집 -->
  <div class="panel">
    <div class="print-only print-head">
      <div class="ttl">자 위 소 방 대 편 성 표</div>
      <div class="ttl-sub">自衛消防隊 編成表</div>
      <hr>
      <div class="print-meta">
        <span id="pmSite">대상물명 : </span>
        <span id="pmWork">근무형태 : </span>
      </div>
    </div>
    <h2 class="no-print"><span class="dot"></span>③ 편성표 (표 안에서 바로 수정)</h2>
    <div class="org" id="orgArea"><!-- JS 렌더 --></div>
    <div class="actions no-print">
      <button class="addbtn" onclick="addGroup()">＋ 활동조 추가</button>
    </div>
  </div>

  <!-- 교육훈련 사진 -->
  <div class="panel page-break">
    <div class="print-only print-head">
      <div class="ttl" style="font-size:20px" id="pmEduTitle">소방교육·훈련 사진</div>
      <hr>
    </div>
    <h2 class="no-print"><span class="dot"></span>④ 소방교육훈련 사진 (편성표 다음 페이지)</h2>
    <div class="row no-print" style="margin-bottom:10px">
      <div><label class="fld">교육·훈련명</label><input type="text" id="eduTitle" placeholder="예: 2026년 상반기 소방 교육·훈련"></div>
      <div><label class="fld">실시일자</label><input type="text" id="eduDate" placeholder="예: 2026-03-15"></div>
    </div>
    <div class="photos">
      <div class="photo-slot" id="slot0">
        <div class="ph" id="ph0">사진 1 (클릭하여 업로드)</div>
        <img id="img0" style="display:none">
        <input type="file" id="file0" accept="image/*" style="display:none" onchange="uploadPhoto(0,this)">
        <div class="no-print" style="margin-top:8px"><input type="text" id="cap0" placeholder="사진 설명 (선택)"></div>
      </div>
      <div class="photo-slot" id="slot1">
        <div class="ph" id="ph1">사진 2 (클릭하여 업로드)</div>
        <img id="img1" style="display:none">
        <input type="file" id="file1" accept="image/*" style="display:none" onchange="uploadPhoto(1,this)">
        <div class="no-print" style="margin-top:8px"><input type="text" id="cap1" placeholder="사진 설명 (선택)"></div>
      </div>
    </div>
  </div>
</div>

<div class="savebar no-print">
  <button class="btn btn-navy" onclick="saveplan()">💾 저장</button>
  <button class="btn btn-primary" onclick="window.print()">🖨️ 인쇄 / PDF 저장</button>
</div>
<div class="toast no-print" id="toast"></div>

<script>
const CSRF = <?=json_encode($CSRF)?>;
let photos = ["",""];
let currentPlanId = null;   // 현재 불러와 수정 중인 편성표 id (null이면 신규)

/* ---------- 임무 기본 문구 (활동조 자동 배정용) ---------- */
const GROUP_TEMPLATES = [
  { name:"비상연락반", tasks:[
      "소방서(119) 화재신고, 관계기관 통보",
      "관계인·근무자 비상연락, 화재경보 방송 전파"] },
  { name:"초기소화반", tasks:[
      "소화기·옥내소화전을 이용한 초기 진화",
      "초기 진화 보조, 연소 확대 방지",
      "발화설비 전원 차단, 가스밸브 차단"] },
  { name:"피난유도반", tasks:[
      "피난로 확보 및 대피 유도",
      "피난약자 보조, 대피 인원 점검",
      "대피 완료 확인, 미대피자 파악·보고"] },
  { name:"응급구조반", tasks:[
      "부상자 응급처치 및 들것 이송",
      "구급차 유도, 인근 병원 연락"] },
  { name:"방호안전반", tasks:[
      "화재구역 출입통제, 중요물품·서류 반출",
      "현금 등 중요자산 반출 보조, 경계근무"] },
];
const CMD_TASK = "자위소방대 총괄 지휘, 화재상황 판단 및 대응 명령, 소방활동 총지휘";
const DEP_TASK = "대장 보좌, 대장 부재 시 임무 대행, 각 활동조 통제";

/* ---------- 현재 편성 상태 ---------- */
let model = {
  cmd:    { name:"", tel:"", task:CMD_TASK },
  deputy: { name:"", tel:"", task:DEP_TASK },
  groups: []   // [{name, members:[{name,tel,task}]}]
};

/* 한 줄에서 이름/전화/직급 추출 */
function parseLine(line){
  line = line.trim();
  if(!line) return null;
  let tel = "";
  const telMatch = line.match(/01[016789][-\s]?\d{3,4}[-\s]?\d{4}/);
  if(telMatch){ tel = telMatch[0].replace(/\s/g,"").replace(/(\d{3})[-]?(\d{3,4})[-]?(\d{4})/,"$1-$2-$3"); line = line.replace(telMatch[0],"").trim(); }
  // 남은 토큰: 첫 토큰=이름, 나머지=직급(참고용, 표엔 이름만)
  const parts = line.split(/[\s\/,·]+/).filter(Boolean);
  const name = parts[0] || "";
  return { name, tel };
}

function autoAssign(){
  const lines = document.getElementById('bulkInput').value.split(/\n/).map(s=>s.trim()).filter(Boolean);
  if(lines.length === 0){ toast("붙여넣은 이름이 없습니다."); return; }
  const people = lines.map(parseLine).filter(p=>p && p.name);
  if(people.length === 0){ toast("이름을 인식하지 못했습니다."); return; }

  model.cmd    = { ...people[0], task:CMD_TASK };
  model.deputy = people[1] ? { ...people[1], task:DEP_TASK } : { name:"", tel:"", task:DEP_TASK };

  const rest = people.slice(2);
  // 활동조 슬롯 채우기: 템플릿 순서대로 정원만큼
  model.groups = GROUP_TEMPLATES.map(g=>({ name:g.name, members:[] }));
  let gi=0, si=0;
  rest.forEach(p=>{
    // 현재 조 정원이 차면 다음 조로
    let guard=0;
    while(si >= GROUP_TEMPLATES[gi].tasks.length){ gi=(gi+1)%GROUP_TEMPLATES.length; si=0; if(++guard>20)break; }
    const task = GROUP_TEMPLATES[gi].tasks[si] || "";
    model.groups[gi].members.push({ name:p.name, tel:p.tel, task });
    si++;
  });
  // 멤버 없는 조는 제거
  model.groups = model.groups.filter(g=>g.members.length>0);
  render();
  toast(people.length + "명 자동 배치 완료 — 표에서 수정하세요.");
}

function clearAll(){
  model = { cmd:{name:"",tel:"",task:CMD_TASK}, deputy:{name:"",tel:"",task:DEP_TASK}, groups:[] };
  render();
}

const CIRC = ["①","②","③","④","⑤","⑥","⑦","⑧","⑨"];
function esc(s){ return (s||"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/"/g,"&quot;"); }

function render(){
  let html = '<table>';
  // 지휘조
  html += '<tr class="cmd-head"><th style="width:16%">직책</th><th style="width:15%">성명</th><th style="width:20%">연락처</th><th>임무</th></tr>';
  html += cmdRow("대 장","cmd");
  html += cmdRow("부 대 장","deputy");
  html += '</table>';

  // 활동조
  html += '<table style="margin-top:14px">';
  model.groups.forEach((g,gi)=>{
    html += `<tr class="grp-row"><td colspan="4">
      ${CIRC[gi]} <input type="text" class="gname" value="${esc(g.name)}" oninput="upd(${gi},-1,'gname',this.value)" style="color:#fff;font-weight:700;border-bottom:1px dashed #ffffff66">
      <span style="opacity:.85;font-weight:400">(${g.members.length}명)</span>
      <button class="delbtn no-print" style="float:right;background:#ffffff22;border-color:#ffffff55;color:#fff" onclick="delGroup(${gi})">조 삭제</button>
    </td></tr>`;
    g.members.forEach((m,mi)=>{
      html += `<tr>
        <td class="ctr" style="width:16%"><input type="text" value="${esc(m.name)}" oninput="upd(${gi},${mi},'name',this.value)" style="text-align:center;font-weight:700"></td>
        <td colspan="0" style="width:20%"><input type="text" value="${esc(m.tel)}" oninput="upd(${gi},${mi},'tel',this.value)" style="text-align:center"></td>
        <td colspan="2"><input type="text" value="${esc(m.task)}" oninput="upd(${gi},${mi},'task',this.value)"></td>
      </tr>`;
    });
    html += `<tr class="no-print"><td colspan="4"><button class="addbtn" onclick="addMember(${gi})">＋ ${esc(g.name)} 인원 추가</button></td></tr>`;
  });
  html += '</table>';
  document.getElementById('orgArea').innerHTML = html;
  syncPrintHead();
}

function syncPrintHead(){
  const site = document.getElementById('siteName').value || "";
  const work = document.getElementById('workType').value || "";
  const total = 2 + model.groups.reduce((a,g)=>a+g.members.length,0);
  document.getElementById('pmSite').textContent = "대상물명 : " + site;
  document.getElementById('pmWork').textContent = "근무형태 : " + work + " · 편성인원 " + total + "명";
  const et = document.getElementById('eduTitle').value || "소방교육·훈련 사진";
  const ed = document.getElementById('eduDate').value || "";
  document.getElementById('pmEduTitle').textContent = et + (ed ? "   ("+ed+")" : "");
}
['siteName','workType','eduTitle','eduDate'].forEach(id=>{
  document.addEventListener('input', e=>{ if(e.target.id===id) syncPrintHead(); });
});

function cmdRow(label,key){
  const m = model[key];
  return `<tr>
    <td class="role-cell">${label}${key==='cmd'?'<br><small style="font-weight:400;color:#555">(소방안전관리자)</small>':''}</td>
    <td class="ctr"><input type="text" value="${esc(m.name)}" oninput="updCmd('${key}','name',this.value)" style="text-align:center;font-weight:700"></td>
    <td class="ctr"><input type="text" value="${esc(m.tel)}" oninput="updCmd('${key}','tel',this.value)" style="text-align:center"></td>
    <td><input type="text" value="${esc(m.task)}" oninput="updCmd('${key}','task',this.value)"></td>
  </tr>`;
}

function updCmd(key,field,val){ model[key][field]=val; }
function upd(gi,mi,field,val){
  if(field==='gname'){ model.groups[gi].name=val; return; }
  model.groups[gi].members[mi][field]=val;
}
function addMember(gi){ model.groups[gi].members.push({name:"",tel:"",task:""}); render(); }
function delGroup(gi){ if(confirm("이 활동조를 삭제할까요?")){ model.groups.splice(gi,1); render(); } }
function addGroup(){ model.groups.push({name:"새 활동조", members:[{name:"",tel:"",task:""}]}); render(); }

/* ---------- 사진 업로드 (기존 photo_upload 액션 재사용) ---------- */
document.getElementById('slot0').addEventListener('click',e=>{ if(e.target.tagName!=='INPUT')document.getElementById('file0').click(); });
document.getElementById('slot1').addEventListener('click',e=>{ if(e.target.tagName!=='INPUT')document.getElementById('file1').click(); });

async function uploadPhoto(idx,input){
  const file = input.files[0]; if(!file) return;
  const fd = new FormData();
  fd.append('csrf',CSRF); fd.append('action','photo_upload'); fd.append('photo_file',file);
  toast("사진 업로드 중…");
  try{
    const res = await fetch(location.pathname, {method:'POST',body:fd}).then(r=>r.json());
    if(res.ok){
      photos[idx]=res.url;
      const img=document.getElementById('img'+idx);
      img.src=res.url; img.style.display='block';
      document.getElementById('ph'+idx).style.display='none';
      toast("사진 "+(idx+1)+" 업로드 완료");
    } else toast("업로드 실패: "+(res.msg||""));
  }catch(e){ toast("네트워크 오류"); }
}

/* ---------- 저장 / 불러오기 ---------- */
function collect(){
  return {
    site_name: document.getElementById('siteName').value,
    work_type: document.getElementById('workType').value,
    cmd:    [model.cmd.name, model.cmd.tel, model.cmd.task],
    deputy: [model.deputy.name, model.deputy.tel, model.deputy.task],
    groups: model.groups,
    photos: photos,
    photo_caps: [document.getElementById('cap0').value, document.getElementById('cap1').value],
    edu_title: document.getElementById('eduTitle').value,
    edu_date:  document.getElementById('eduDate').value
  };
}

async function saveplan(){
  const fd = new FormData();
  fd.append('csrf',CSRF); fd.append('action','fire_save');
  if(currentPlanId) fd.append('plan_id',currentPlanId);   // 있으면 그 항목 갱신
  fd.append('payload',JSON.stringify(collect()));
  try{
    const res = await fetch(location.pathname,{method:'POST',body:fd}).then(r=>r.json());
    if(res.ok){
      currentPlanId = res.id;
      toast(res.updated ? "수정 저장됨 ("+res.saved+")" : "새 편성표로 저장됨");
      setEditingHint();
      loadList();
    } else toast("저장 실패: "+(res.msg||""));
  }catch(e){ toast("네트워크 오류"); }
}

function applyPlan(p){
  document.getElementById('siteName').value=p.site_name||"";
  document.getElementById('workType').value=p.work_type||"단일근무(주간)";
  model.cmd={name:(p.cmd&&p.cmd[0])||"",tel:(p.cmd&&p.cmd[1])||"",task:(p.cmd&&p.cmd[2])||CMD_TASK};
  model.deputy={name:(p.deputy&&p.deputy[0])||"",tel:(p.deputy&&p.deputy[1])||"",task:(p.deputy&&p.deputy[2])||DEP_TASK};
  model.groups=(p.groups||[]).map(g=>({name:g.name||"",members:(g.members||[]).map(m=>({name:m.name||"",tel:m.tel||"",task:m.task||""}))}));
  photos=[(p.photos&&p.photos[0])||"",(p.photos&&p.photos[1])||""];
  document.getElementById('cap0').value=(p.photo_caps&&p.photo_caps[0])||"";
  document.getElementById('cap1').value=(p.photo_caps&&p.photo_caps[1])||"";
  document.getElementById('eduTitle').value=p.edu_title||"";
  document.getElementById('eduDate').value=p.edu_date||"";
  [0,1].forEach(i=>{
    const img=document.getElementById('img'+i), ph=document.getElementById('ph'+i);
    if(photos[i]){ img.src=photos[i]; img.style.display='block'; ph.style.display='none'; }
    else { img.style.display='none'; ph.style.display='block'; }
  });
  render();
}

async function loadPlan(id){
  const fd=new FormData(); fd.append('csrf',CSRF); fd.append('action','fire_load'); fd.append('plan_id',id);
  const res=await fetch(location.pathname,{method:'POST',body:fd}).then(r=>r.json());
  if(res && res.plan){
    currentPlanId=id; applyPlan(res.plan); setEditingHint();
    loadList();
    toast("불러왔습니다. 수정 후 저장하면 이 항목이 갱신됩니다.");
    window.scrollTo({top:0,behavior:'smooth'});
  } else toast("불러올 수 없습니다.");
}

async function deletePlan(id, name){
  if(!confirm("'"+(name||'이 편성표')+"' 를 삭제할까요?")) return;
  const fd=new FormData(); fd.append('csrf',CSRF); fd.append('action','fire_delete'); fd.append('plan_id',id);
  await fetch(location.pathname,{method:'POST',body:fd}).then(r=>r.json());
  if(currentPlanId===id){ currentPlanId=null; setEditingHint(); }
  toast("삭제되었습니다."); loadList();
}

async function dupPlan(id){
  const fd=new FormData(); fd.append('csrf',CSRF); fd.append('action','fire_dup'); fd.append('plan_id',id);
  const res=await fetch(location.pathname,{method:'POST',body:fd}).then(r=>r.json());
  if(res.ok){ toast("복제되었습니다."); loadList(); }
  else toast("복제 실패: "+(res.msg||""));
}

function newPlan(){
  currentPlanId=null;
  clearAll();
  document.getElementById('siteName').value="";
  document.getElementById('workType').value="단일근무(주간)";
  document.getElementById('bulkInput').value="";
  document.getElementById('eduTitle').value="";
  document.getElementById('eduDate').value="";
  ['cap0','cap1'].forEach(id=>document.getElementById(id).value="");
  photos=["",""];
  [0,1].forEach(i=>{ document.getElementById('img'+i).style.display='none'; document.getElementById('ph'+i).style.display='block'; });
  setEditingHint();
  toast("새 편성표 작성 모드입니다.");
}

function setEditingHint(){
  const el=document.getElementById('editingHint');
  if(currentPlanId) el.innerHTML="✏️ <b>기존 편성표 수정 중</b> — 저장하면 같은 항목이 갱신됩니다.";
  else el.innerHTML="🆕 <b>새 편성표</b> — 저장하면 목록에 새로 추가됩니다.";
  document.querySelectorAll('.plan-card').forEach(c=>c.classList.toggle('active', c.dataset.id===currentPlanId));
}

async function loadList(){
  const box=document.getElementById('planList');
  try{
    const fd=new FormData(); fd.append('csrf',CSRF); fd.append('action','fire_list');
    const res=await fetch(location.pathname,{method:'POST',body:fd}).then(r=>r.json());
    const list=(res&&res.list)||[];
    if(list.length===0){ box.innerHTML='<div class="empty">아직 저장된 편성표가 없습니다. 작성 후 💾 저장을 눌러보세요.</div>'; return; }
    box.innerHTML=list.map(p=>`
      <div class="plan-card${p.id===currentPlanId?' active':''}" data-id="${p.id}">
        <div class="pc-main">
          <div class="pc-name">${esc(p.site_name||'(이름 없음)')}</div>
          <div class="pc-meta">편성 ${p.total}명 · 저장 ${esc(p.saved||'')}</div>
        </div>
        <div class="pc-btns">
          <button class="pcbtn load" onclick="loadPlan('${p.id}')">불러오기</button>
          <button class="pcbtn" onclick="dupPlan('${p.id}')" title="복제">복제</button>
          <button class="pcbtn del" onclick="deletePlan('${p.id}','${esc(p.site_name||'').replace(/'/g,'')}')">삭제</button>
        </div>
      </div>`).join('');
  }catch(e){ box.innerHTML='<div class="empty">목록을 불러오지 못했습니다.</div>'; }
}

/* ---------- toast ---------- */
let toastT;
function toast(msg){
  const t=document.getElementById('toast'); t.textContent=msg; t.classList.add('show');
  clearTimeout(toastT); toastT=setTimeout(()=>t.classList.remove('show'),2200);
}

render();
setEditingHint();
loadList();
</script>
<?php require_once __DIR__ . '/admin_quickmemo_widget.php'; ?>
</body>
</html>
