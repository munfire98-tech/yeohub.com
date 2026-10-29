(()=>{'use strict';
const boot=document.getElementById('fpsc-boot'),form=document.getElementById('fpsc-form');if(!boot||!form)return;
const app=JSON.parse(boot.textContent),events=app.events,labels={fire:'소방훈련',education:'소방교육',jawi:'자위소방대 교육'},kinds=Object.keys(labels),stage=document.getElementById('fpsc-stage'),preview=document.getElementById('fpsc-preview'),err=document.getElementById('fpsc-client-error');
let kind=kinds.includes(app.kind)?app.kind:'fire',dirty=false,submitting=false;
const el=(tag,cls,txt)=>{const e=document.createElement(tag);if(cls)e.className=cls;if(txt!==undefined)e.textContent=txt;return e;};
const button=(txt,cls,fn)=>{const b=el('button',cls,txt);b.type='button';b.addEventListener('click',fn);return b;};
const resolve=e=>e.same_as?events.find(x=>x.id===e.same_as&&x.kind==='fire'):e;
const ready=e=>{const r=resolve(e);return !!(r?.date&&e.who.trim()&&e.content.trim());};
function dateLabel(e){const r=resolve(e);if(!r?.date)return '예정 월을 선택해 주세요';const p=r.date.split('-');return `${Number(p[1])}월${p[2]?' '+Number(p[2])+'일':''} 예정`;}
function sync(){document.getElementById('fpsc-events').value=JSON.stringify(events);preview.replaceChildren();
 for(const k of kinds){for(const e of events.filter(x=>x.kind===k)){const row=el('div','fpsc-preview-row'+(ready(e)?'':' is-empty'));row.append(el('strong','',labels[k]),el('span','',dateLabel(e)));if(e.same_as)row.append(el('small','','소방훈련과 같은 일정'));preview.append(row);}}
 const count=kinds.filter(k=>events.filter(e=>e.kind===k).every(ready)).length;
 document.getElementById('fpsc-progress').textContent=`${count} / 3개 일정 준비`;
 document.querySelectorAll('[data-kind]').forEach(b=>{b.setAttribute('aria-current',b.dataset.kind===kind?'step':'false');b.firstElementChild.textContent=events.filter(e=>e.kind===b.dataset.kind).every(ready)?'✓':String(kinds.indexOf(b.dataset.kind)+1);});
 document.getElementById('fpsc-next').hidden=kind==='jawi';
}
function changed(){dirty=true;err.hidden=true;sync();}
function field(label,type,value,onInput){const wrap=el('label'),title=el('span','fpsc-field-label',label),input=el(type==='textarea'?'textarea':'input');if(type!=='textarea')input.type=type;else input.rows=2;input.value=value;input.maxLength=1500;input.addEventListener('input',()=>{onInput(input.value);changed();});wrap.append(title,input);return wrap;}
function render(){stage.replaceChildren();const rows=events.filter(e=>e.kind===kind);
 rows.forEach((e,index)=>{const card=el('div','fpsc-event'),head=el('div','fpsc-event-head');head.append(el('h2','',`${labels[kind]}${rows.length>1?' · '+(index+1)+'회차':''}`));
 if(rows.length>1)head.append(button('삭제','fpsc-delete',()=>{if(events.some(x=>x.same_as===e.id)){err.textContent='이 훈련과 같은 날로 연결한 교육의 날짜를 먼저 변경해 주세요.';err.hidden=false;return;}events.splice(events.indexOf(e),1);changed();render();}));card.append(head);
 const modes=el('div','fpsc-modes');[['month','월만 선택'],['date','날짜 선택'],...(kind!=='fire'?[['same','훈련과 같은 날']]:[])].forEach(([mode,title])=>{const b=button(title,'',()=>{if(mode==='same'){e.same_as=events.find(x=>x.kind==='fire').id;}else{e.same_as='';e.precision=mode;e.date=mode==='month'?e.date.slice(0,7):e.date.length===10?e.date:'';}changed();render();});b.setAttribute('aria-pressed',String(e.same_as?mode==='same':mode===e.precision));modes.append(b);});card.append(modes);
 if(e.same_as){const select=el('select');select.setAttribute('aria-label','함께 진행할 소방훈련');events.filter(x=>x.kind==='fire').forEach((x,i)=>{const option=el('option','',`소방훈련 ${i+1}회차 · ${dateLabel(x)}`);option.value=x.id;select.append(option);});select.value=e.same_as;select.addEventListener('change',()=>{e.same_as=select.value;changed();});card.append(select);}
 else if(e.precision==='month'){const grid=el('div','fpsc-months');grid.setAttribute('role','group');grid.setAttribute('aria-label',labels[kind]+' 예정 월');for(let n=1;n<=12;n++){const val=app.year+'-'+String(n).padStart(2,'0'),b=button(n+'월','',()=>{e.date=val;changed();grid.querySelectorAll('button').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));});b.setAttribute('aria-pressed',String(e.date===val));grid.append(b);}card.append(grid);}
 else{const wrap=field('예정일','date',e.date,v=>e.date=v),input=wrap.querySelector('input');input.min=app.year+'-01-01';input.max=app.year+'-12-31';card.append(wrap);}
 const refs=app.references[kind]||[];
 if(refs.length){const area=el('div','fpsc-ref'),select=el('select');select.setAttribute('aria-label','지난해 실시기록 선택');refs.forEach((r,i)=>{const o=el('option','',`${app.year-1}년 실시기록 · ${r.date.slice(5).replace('-','월 ')}일`);o.value=i;area.append(select);select.append(o);});area.append(button('같은 월로 가져오기','',()=>{const r=refs[Number(select.value)];e.same_as='';e.precision='month';e.date=app.year+'-'+r.date.slice(5,7);e.place=r.place;e.content=r.content||e.content;changed();render();}));card.append(area,el('p','fpsc-ref-hint','선택한 기록의 월·장소·내용을 가져와요. 이번 계획에 맞게 확인해 주세요.'));}
 const details=el('details'),summary=el('summary','','대상 · 장소 · 교육 내용');summary.append(el('small','','확인·수정'));details.append(summary,field('대상','text',e.who,v=>e.who=v),field('장소 (미정이면 비워 두세요)','text',e.place,v=>e.place=v),field('내용','textarea',e.content,v=>e.content=v));card.append(details);stage.append(card);
 });
 if(events.length<36)stage.append(button('+ 일정 한 번 더 추가','fpsc-add',()=>{const source=rows[rows.length-1],bytes=new Uint8Array(8);crypto.getRandomValues(bytes);events.push({...source,id:Array.from(bytes,n=>n.toString(16).padStart(2,'0')).join(''),same_as:'',date:'',precision:'month'});changed();render();stage.lastElementChild.previousElementSibling?.scrollIntoView({block:'nearest',behavior:'smooth'});}));sync();
}
document.querySelectorAll('[data-kind]').forEach(b=>b.addEventListener('click',()=>{kind=b.dataset.kind;render();}));
document.getElementById('fpsc-next').addEventListener('click',()=>{kind=kinds[Math.min(2,kinds.indexOf(kind)+1)];render();document.querySelector('.fpsc-editor').scrollIntoView({block:'nearest',behavior:'smooth'});});
form.addEventListener('submit',ev=>{if(submitting){ev.preventDefault();return;}const missing=events.find(e=>!ready(e));if(missing){ev.preventDefault();kind=missing.kind;render();err.textContent=labels[kind]+'의 예정일과 내용을 확인해 주세요.';err.hidden=false;document.querySelector('.fpsc-editor').scrollIntoView({block:'start',behavior:'smooth'});return;}sync();submitting=true;dirty=false;document.getElementById('fpsc-save').disabled=true;document.getElementById('fpsc-save').textContent='저장 중…';});
window.addEventListener('beforeunload',e=>{if(dirty&&!submitting){e.preventDefault();e.returnValue='';}});
window.fpModalState=()=>({dirty,busy:submitting});
window.fpModalDiscard=()=>{dirty=false;};
render();
})();
