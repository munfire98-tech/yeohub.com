window.buildingAddressConfirm=function(a,j,retried){
  return new Promise(function(resolve){
    var dialog=document.createElement('dialog');
    dialog.style.cssText='box-sizing:border-box;width:min(520px,calc(100vw - 32px));max-height:85vh;overflow:auto;margin:auto;padding:24px;border:1px solid #dce3ed;border-radius:18px;background:#fff;color:#17253e;box-shadow:0 20px 70px #0004;';
    var title=document.createElement('h3');title.textContent='선택하신 건물이 맞나요?';title.style.margin='0 0 16px';
    var info=document.createElement('p');info.style.cssText='white-space:pre-wrap;line-height:1.7;font-size:14px;overflow-wrap:anywhere';
    info.textContent='선택한 주소\n'+(a.road||a.jibun||'')+'\n\n조회된 건물\n'+((j.patch||{}).name||'명칭 없음')+'\n'+(j.matched_address||(j.patch||{}).address||'주소 없음')+'\n'+(j.matched_jibun||'');
    var hint=document.createElement('p');hint.style.cssText='font-size:13px;color:#52637a;line-height:1.6';hint.textContent=retried?'선택한 도로명주소로 다시 찾은 결과입니다. 다른 건물이면 주소를 다시 검색해 주세요.':'다른 건물이면 처음 선택한 도로명주소로 다시 찾아드립니다.';
    var actions=document.createElement('div');actions.style.cssText='display:flex;flex-wrap:wrap;gap:8px;margin-top:20px';
    var finished=false;
    function finish(value){if(finished)return;finished=true;dialog.close();dialog.remove();resolve(value);}
    function button(label,value,primary){var b=document.createElement('button');b.type='button';b.textContent=label;b.style.cssText='flex:1 1 180px;padding:12px;border-radius:10px;border:1px solid #dce3ed;cursor:pointer;font-weight:700;background:'+(primary?'#215ceb;color:white':'#f4f6fa;color:#243752');b.onclick=function(){finish(value);};actions.appendChild(b);return b;}
    var no=button(retried?'다른 건물이에요 · 주소 다시 검색':'다른 건물이에요 · 다시 찾기','retry',false);
    button('이 건물이 맞아요','accept',true);
    var close=document.createElement('button');close.type='button';close.textContent='닫기';close.style.cssText='float:right;border:0;background:none;color:#52637a;cursor:pointer';close.onclick=function(){finish('cancel');};
    dialog.append(close,title,info,hint,actions);document.body.appendChild(dialog);
    dialog.addEventListener('cancel',function(e){e.preventDefault();finish('cancel');});
    dialog.showModal();no.focus();
  });
};
