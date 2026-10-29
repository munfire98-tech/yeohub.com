(()=>{'use strict';
 // One JSON form field avoids PHP max_input_vars truncation on campuses/high-rises.
 const inputs=[...document.querySelectorAll('[data-bfl-input]')],field=document.querySelector('input[name="bd_floor_manual"]');
 document.addEventListener('bfl:saved',e=>{
  document.querySelectorAll('[data-bfl-row]').forEach(row=>{const f=e.detail?.[row.dataset.bflDong]?.find(f=>f.key===row.dataset.bflRow);if(!f)return;
   row.querySelector('[data-bfl-source]').textContent=f.source==='manual'?'직접 입력':f.source==='registry'?'대장 자료':'자료 없음';
   const area=row.querySelector('.bfl-area');area.textContent=f.area!==''?Number(f.area).toLocaleString('ko-KR',{maximumFractionDigits:6}):'자료 없음';
   if(f.area!==''){const unit=document.createElement('small');unit.textContent='㎡';area.append(unit);}
   row.querySelector('.bfl-use').textContent=f.use||'용도 미입력';row.querySelector('.bfl-structure').textContent=f.structure||'구조 미입력';
  });
 });
 if(!field)return;
 function sync(){const value={};inputs.forEach(el=>{const k=el.dataset.bflDong,f=el.dataset.bflFloor;(value[k]??={})[f]??={};value[k][f][el.dataset.bflField]=el.value;});field.value=JSON.stringify(value);}
 inputs.forEach(el=>el.addEventListener('input',sync));field.form?.addEventListener('submit',sync);
})();
