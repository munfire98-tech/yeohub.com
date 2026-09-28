window.confirmDraftBuildingName=function(proposed,host){
  return new Promise(function(resolve){
    if(!document.getElementById('draft-name-style')){
      var style=document.createElement('style');style.id='draft-name-style';style.textContent=`
.dn-card{box-sizing:border-box;width:100%;max-width:540px;margin:16px 0;padding:24px;background:#fff;border:1px solid #dce5ee;border-radius:18px;box-shadow:0 6px 24px rgba(28,51,80,.05);color:#20334c;font-family:inherit}
.dn-card *{box-sizing:border-box}.dn-eyebrow{display:flex;align-items:center;gap:7px;font-size:12px;font-weight:700;color:#52718e;margin-bottom:12px}.dn-dot{width:7px;height:7px;border-radius:50%;background:#318b83}
.dn-title{margin:0;font-size:19px;line-height:1.45;font-weight:750;letter-spacing:-.5px}.dn-desc{margin:7px 0 20px;font-size:13px;line-height:1.65;color:#738295}
.dn-namebox{display:flex;align-items:center;gap:12px;padding:16px;background:#f5f8fc;border:1px solid #e7edf4;border-radius:12px}.dn-namecontent{flex:1;min-width:0}.dn-label{display:block;font-size:11px;font-weight:650;color:#7b8b9e;margin-bottom:5px}.dn-value{margin:0;font-size:17px;font-weight:700;line-height:1.5;overflow-wrap:anywhere}
.dn-card button{font-family:inherit;cursor:pointer;transition:background .15s,box-shadow .15s}.dn-edit{flex:none;display:inline-flex;align-items:center;gap:5px;border:1px solid #d9e2ed;border-radius:8px;padding:8px 11px;background:#fff;color:#425d7a;font-size:12px;font-weight:650;white-space:nowrap}.dn-edit:hover{background:#eaf0f8}
.dn-form{margin:0}.dn-input{display:block;width:100%;height:48px;border:1px solid #b6c9df;border-radius:10px;padding:0 13px;color:#20334c;background:white;font:inherit;font-size:16px}.dn-field-label{display:block;margin:0 0 8px;color:#526a85;font-size:12px;font-weight:650}.dn-error{margin:7px 0 0;color:#b42318;font-size:12px;line-height:1.5}.dn-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:22px}.dn-cancel{border:0;padding:10px 4px;color:#7a889b;background:transparent;font-size:13px}.dn-primary{min-height:44px;padding:11px 20px;border:0;border-radius:10px;background:#245de8;color:white;font-size:14px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;gap:12px}.dn-primary:hover{background:#194ed0}.dn-card button:focus-visible,.dn-input:focus-visible{outline:3px solid #b1cef9;outline-offset:3px}.dn-card [hidden]{display:none!important}
@media(max-width:480px){.dn-card{padding:18px;border-radius:14px}.dn-title{font-size:18px}.dn-namebox{padding:13px;gap:8px}.dn-primary{flex:1}.dn-actions{gap:22px}.dn-value{font-size:16px}}
`;document.head.appendChild(style);
    }
    var card=document.createElement('section');card.className='dn-card';
    // Static markup only; names are assigned as text/value below.
    card.innerHTML='<div class="dn-eyebrow"><span class="dn-dot" aria-hidden="true"></span>대상명 확인</div><h3 class="dn-title">이 이름으로 등록할까요?</h3><p class="dn-desc">지도와 거래처 목록에 표시할 이름이에요.<br>알아보기 쉬운 건물명으로 바꿀 수 있어요.</p><div class="dn-namebox"><div class="dn-namecontent"><span class="dn-label">등록할 대상명</span><p class="dn-value"></p></div><button type="button" class="dn-edit"><span aria-hidden="true">✎</span> 수정</button></div><form class="dn-form" hidden><label class="dn-field-label">등록할 대상명<input class="dn-input" type="text" maxlength="100" placeholder="예: 화이버텍 공장" autocomplete="off"></label><p class="dn-error" role="alert"></p></form><div class="dn-actions"><button type="button" class="dn-cancel">취소</button><button type="button" class="dn-primary"><span class="dn-submit-label">이 이름으로 사용</span><span aria-hidden="true">→</span></button></div>';
    var value=card.querySelector('.dn-value'),input=card.querySelector('.dn-input'),form=card.querySelector('.dn-form'),namebox=card.querySelector('.dn-namebox'),error=card.querySelector('.dn-error'),primary=card.querySelector('.dn-primary'),label=card.querySelector('.dn-submit-label');
    var original=String(proposed||'').trim();value.textContent=original||'대상명을 입력해 주세요';input.value=original;
    var editing=false,settled=false;
    function finish(v){if(settled)return;settled=true;card.remove();resolve(v);}
    function edit(){editing=true;form.hidden=false;namebox.hidden=true;label.textContent='이 이름으로 사용';input.focus();input.select();}
    function accept(){var v=String(editing?input.value:original).trim();if(!v){error.textContent='지도에 표시할 대상명을 입력해 주세요.';edit();return;}if(v.length>100){error.textContent='대상명은 100자 이내로 입력해 주세요.';edit();return;}finish(v);}
    card.querySelector('.dn-edit').onclick=edit;card.querySelector('.dn-cancel').onclick=function(){finish(null);};primary.onclick=accept;form.onsubmit=function(e){e.preventDefault();accept();};input.oninput=function(){error.textContent='';};
    host.appendChild(card);if(!original)edit();else primary.focus({preventScroll:true});card.scrollIntoView({block:'nearest',behavior:'smooth'});
  });
};
