/* Guidance under Fire Facilities Act Enforcement Decree Articles 30–31.
 * Checked 2026-10-15. Building use must be confirmed; never infer exemption from a name. */
(function(root){
 'use strict';
 const uses=[
  {id:'neighborhood',title:'의원·체력단련장 등',detail:'근린생활시설 중 의원·치과의원·한의원·조산원·산후조리원·체력단련장·공연장·종교집회장',basis:'제30조제1호'},
  {id:'assembly',title:'문화·집회·종교시설',detail:'건축물의 옥내에 있는 문화 및 집회시설·종교시설',basis:'제30조제2호가목·나목'},
  {id:'sports',title:'옥내 운동시설',detail:'건축물의 옥내 운동시설 · 수영장은 제외',basis:'제30조제2호다목'},
  {id:'medical',title:'의료시설',detail:'의료시설에 해당하는 병원 등',basis:'제30조제3호'},
  {id:'dorm',title:'교육연구시설의 합숙소',detail:'학교·학원 전체가 아닌, 교육연구시설 중 합숙소',basis:'제30조제4호'},
  {id:'welfare',title:'노유자시설',detail:'소방시설법상 노유자시설에 해당하는 시설',basis:'제30조제5호'},
  {id:'lodging',title:'숙박·숙박 가능한 수련시설',detail:'숙박시설 또는 숙박이 가능한 수련시설',basis:'제30조제6호·제7호'},
  {id:'broadcast',title:'방송국·촬영소',detail:'방송통신시설 중 방송국 및 촬영소',basis:'제30조제8호'}
 ];
 function assess(answer={}){
  const selected=Array.isArray(answer.uses)?answer.uses:[];
  const known=uses.filter(use=>selected.includes(use.id));
  // Mutually exclusive answers are checked again here, even though the UI prevents them.
  if((selected.includes('none')||selected.includes('unknown'))&&selected.length>1)return {state:'uncertain',reasons:[],missing:['사용 용도를 다시 확인해 주세요.']};
  const reasons=known.map(use=>use.title+' · '+use.basis);
  if(answer.multi==='yes')reasons.push('다중이용업소 · 제30조제9호');
  if(answer.height==='high'&&answer.apartment==='no')reasons.push('11층 이상이며 아파트등에 해당하지 않음 · 제30조제10호');
  if(reasons.length)return {state:'applies',reasons,missing:[]};
  const missing=[];
  if(selected.length!==1||selected[0]!=='none')missing.push('실제 사용 용도');
  if(answer.multi!=='no')missing.push('다중이용업소 해당 여부');
  if(answer.height!=='low'&&answer.apartment!=='yes')missing.push(answer.height==='high'?'아파트등 해당 여부':'건물 층수·아파트등 해당 여부');
  return {state:missing.length?'uncertain':'outside',reasons:[],missing};
 }
 const api={uses,assess};
 if(typeof module==='object'&&module.exports)module.exports=api;else root.FlameRetardantGuideRules=api;
})(typeof window==='object'?window:globalThis);
