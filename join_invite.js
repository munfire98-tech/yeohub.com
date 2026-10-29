(()=>{
 const card=document.querySelector('.iq-card'),error=document.getElementById('iq-error');
 const token=location.hash.slice(1),form=document.getElementById('iq-signup');
 async function request(body){
  const controller=new AbortController();
  const timer=setTimeout(()=>controller.abort(),15000);
  try{
   const response=await fetch('/join_invite.php',{method:'POST',credentials:'same-origin',body,signal:controller.signal});
   const data=await response.json();
   if(!response.ok||!data.ok)throw new Error(data.error||'확인하지 못했습니다. 다시 시도해 주세요.');
   return data;
  }catch(e){
   if(e.name==='AbortError')throw new Error('응답이 늦어지고 있습니다. 연결 상태를 확인한 뒤 다시 시도해 주세요.');
   if(e instanceof TypeError||e instanceof SyntaxError)throw new Error('서버와 연결하지 못했습니다. 잠시 후 다시 시도해 주세요.');
   throw e;
  }finally{clearTimeout(timer);}
 }
 if(token){
  // Keep the token out of history/referrers; retain it only in this closure for retry.
  history.replaceState(null,'','/join_invite.php');
  // A new QR must never submit a previously opened building's form.
  if(form)form.hidden=true;
  const previous=card.querySelector('.iq-building');if(previous)previous.hidden=true;
  let busy=false;
  const loading=document.getElementById('iq-loading')||document.createElement('p');
  if(!loading.parentNode)card.append(loading);
  loading.setAttribute('role','status');
  const retry=document.createElement('button');retry.type='button';retry.className='iq-button';retry.textContent='연결 다시 시도';retry.hidden=true;card.append(retry);
  async function openInvite(){
   if(busy)return;busy=true;retry.hidden=true;error.textContent='';loading.textContent='건물과 연결 업체를 확인하고 있습니다…';
   try{
    await request(new URLSearchParams({action:'open',token,csrf:card.dataset.csrf}));
    location.replace('/join_invite.php');
   }catch(e){loading.textContent='';error.textContent=e.message;retry.hidden=false;}
   finally{busy=false;}
  }
  retry.addEventListener('click',openInvite);openInvite();return;
 }
 if(!form)return;
 const userId=form.elements.userid,idButton=document.getElementById('iq-check-id'),idStatus=document.getElementById('iq-id-status');
 const submit=form.querySelector('button[type="submit"]');
 let checkedId='',sequence=0,checking=null,submitting=false;
 const normalizedId=()=>userId.value.trim().toLowerCase();
 function invalidateId(){sequence++;checkedId='';checking=null;idButton.disabled=false;idButton.textContent='중복 확인';idStatus.textContent='아이디 중복 여부를 확인해 주세요.';idStatus.style.color='';}
 userId.addEventListener('input',invalidateId);
 async function checkId(){
  if(!userId.reportValidity())return false;
  if(checking)return checking;
  const candidate=normalizedId(),current=++sequence;
  checkedId='';idButton.disabled=true;idButton.textContent='확인 중';idStatus.textContent='사용 가능한 아이디인지 확인하고 있습니다.';
  checking=(async()=>{
   try{
    const data=await request(new URLSearchParams({action:'check_userid',userid:candidate,csrf:form.elements.csrf.value}));
    if(current!==sequence||candidate!==normalizedId())return false;
    if(data.available&&data.userid===candidate){checkedId=candidate;idStatus.textContent='사용 가능한 아이디입니다.';idStatus.style.color='#287164';return true;}
    idStatus.textContent='이미 사용 중인 아이디입니다. 다른 아이디를 입력해 주세요.';idStatus.style.color='#a8483d';return false;
   }catch(e){if(current===sequence){idStatus.textContent=e.message;idStatus.style.color='#a8483d';}return false;}
   finally{if(current===sequence){checking=null;idButton.disabled=false;idButton.textContent='중복 확인';}}
  })();
  return checking;
 }
 idButton.addEventListener('click',checkId);
 const password=form.elements.password,confirmation=form.elements.password_confirm,hint=document.getElementById('iq-password-match');
 function checkPassword(){
  const filled=confirmation.value!=='',matches=password.value===confirmation.value;
  confirmation.setCustomValidity(filled&&!matches?'비밀번호가 일치하지 않습니다. 다시 확인해 주세요.':'');
  hint.textContent=filled?(matches?'비밀번호가 일치합니다.':'비밀번호가 일치하지 않습니다.'):'';
  hint.style.color=matches?'#287164':'#a8483d';return filled&&matches;
 }
 password.addEventListener('input',checkPassword);confirmation.addEventListener('input',checkPassword);
 form.addEventListener('submit',async e=>{
  if(submitting){e.preventDefault();return;}
  if(!checkPassword()){e.preventDefault();confirmation.reportValidity();confirmation.focus();return;}
  if(checkedId!==normalizedId()){
   e.preventDefault();submitting=true;submit.disabled=true;submit.textContent='아이디 확인 중…';
   const available=await checkId();submitting=false;submit.disabled=false;submit.textContent='가입하고 구독으로 →';
   if(available)form.requestSubmit();else userId.focus();return;
  }
  submitting=true;submit.disabled=true;submit.textContent='가입·연결 중…';
 });
 window.addEventListener('pageshow',()=>{invalidateId();submitting=false;submit.disabled=false;submit.textContent='가입하고 구독으로 →';});
})();
