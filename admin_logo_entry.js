(()=>{
 function init(){
  const logo=document.querySelector('.site-footer .footer-logo');if(!logo)return;
  let count=0,last=0;
  function activate(){const now=Date.now();count=now-last<=1500?count+1:1;last=now;if(count===5){count=0;location.assign('/admin_login.php');}}
  logo.setAttribute('role','button');logo.setAttribute('tabindex','0');logo.setAttribute('aria-label','YEOHUB 관리자 로그인: 다섯 번 누르기');
  logo.style.cursor='default';logo.style.touchAction='manipulation';logo.style.userSelect='none';
  logo.addEventListener('click',activate);
  logo.addEventListener('keydown',e=>{if((e.key==='Enter'||e.key===' ')&&!e.repeat){e.preventDefault();activate();}});
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
