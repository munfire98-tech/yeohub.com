/* Batch editing changes only the fields explicitly chosen, retaining floor records. */
window.EvacuationFloorGroups={
 useParts(value){return [...new Set(String(value??'').split(/\s*\/\s*/).map(v=>v.trim()).filter(Boolean))];},
 toggleUse(floors,selected,value){
  const rows=[...new Set(selected)].map(k=>floors[k]).filter(Boolean);
  if(!rows.length||!value)return;
  const parts=rows.map(row=>this.useParts(row.use)),remove=parts.every(items=>items.includes(value));
  const next=parts.map(items=>(remove?items.filter(v=>v!==value):items.includes(value)?items:[...items,value]).join(' / '));
  if(next.some(v=>Array.from(v).length>120))throw Error('공간 설명은 120자까지 저장할 수 있어요. 직접 입력에서 기존 설명을 줄인 뒤 선택해 주세요.');
  rows.forEach((row,i)=>{row.use=next[i];});
 },
 range(keys,from,to){const a=keys.indexOf(from),b=keys.indexOf(to);return a<0||b<0?[]:keys.slice(Math.min(a,b),Math.max(a,b)+1);},
 common(floors,selected,fields){const result={};for(const field of fields){const values=selected.map(k=>String(floors[k]?.[field]??''));result[field]=values.length&&values.every(v=>v===values[0])?values[0]:'';}return result;},
 apply(floors,selected,patch){for(const key of new Set(selected)){const row=floors[key];if(!row)continue;for(const [field,value] of Object.entries(patch)){if(value===undefined)delete row[field];else row[field]=value;}}},
 complete(row,fields,routeProblem){return fields.every(field=>String(row?.[field]??'').trim()!=='')&&!routeProblem(row||{});}
};
