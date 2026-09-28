/* Retry only transient lookup failures. Saving is handled by the caller, once. */
window.buildingRegistryLookup=async function(url,options,onAttempt){
 const started=Date.now(),limit=85000,outer=options.signal;
 const canceled=()=>outer&&outer.aborted;
 const cancelError=()=>Object.assign(new Error('조회가 중단되었습니다.'),{name:'AbortError'});
 let lastError=new Error('조회 서버의 응답이 늦어지고 있습니다.');
 for(let attempt=1;attempt<=3;attempt++){
  if(canceled())throw cancelError();
  const remaining=limit-(Date.now()-started);if(remaining<1000)break;
  onAttempt?.(attempt,3);
  const controller=new AbortController();const forward=()=>controller.abort();outer?.addEventListener('abort',forward,{once:true});
  const timer=setTimeout(()=>controller.abort(),Math.min(28000,remaining));
  let retry=false;
  try{
   const response=await fetch(url,{...options,signal:controller.signal,cache:'no-store'});
   let data;try{data=await response.json();}catch(e){if([401,403,404].includes(response.status)){throw Object.assign(new Error('조회 요청 또는 로그인 상태를 확인해 주세요.'),{permanent:true});}throw e;}
   if(canceled())throw cancelError();
   if(response.ok&&data&&data.ok)return data;
   // An explicit server decision overrides the HTTP retry classification.
   retry=typeof data?.retryable==='boolean'?data.retryable:[408,429,500,502,503,504].includes(response.status);
   lastError=new Error(data?.error||'조회 서버가 응답하지 않습니다.');
   if(!retry)return data||{ok:false,error:lastError.message};
  }catch(e){
   if(canceled())throw cancelError();
   if(e.permanent)throw e;
   retry=true;lastError=new Error(e.name==='AbortError'?'조회 서버의 응답 시간이 초과되었습니다.':'조회 중 연결이 끊겼습니다.');
  }finally{clearTimeout(timer);outer?.removeEventListener('abort',forward);}
  if(!retry||attempt===3)break;
  const delay=Math.min(attempt*1500,limit-(Date.now()-started));if(delay<=0)break;
  await new Promise((resolve,reject)=>{const abort=()=>{clearTimeout(t);outer?.removeEventListener('abort',abort);reject(cancelError());};const t=setTimeout(()=>{outer?.removeEventListener('abort',abort);resolve();},delay);outer?.addEventListener('abort',abort,{once:true});if(canceled())abort();});
 }
 return {ok:false,retryable:false,error:'자동으로 최대 3차례 조회했지만 정보를 가져오지 못했습니다. '+lastError.message};
};
