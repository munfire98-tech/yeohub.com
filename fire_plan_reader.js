(() => {
  'use strict';
  const catalog=document.querySelector('.fpr-catalog'),doc=document.getElementById('fp-shared-document');
  if(!catalog||!doc)return;
  const body=document.body,bar=document.querySelector('.fpr-readerbar');
  const cards=[...catalog.querySelectorAll('.fpr-card[data-reader-select]')];
  const sections=[...doc.querySelectorAll(':scope > .sec')];
  const keys=cards.map(card=>card.dataset.readerSelect);
  const back=bar.querySelector('[data-reader-back]'),prev=bar.querySelector('[data-reader-prev]'),next=bar.querySelector('[data-reader-next]');
  let selected='catalog',lastCard=null,catalogScroll=0;
  function fromHash(){
    if(location.hash==='#full-document')return 'all';
    if(location.hash==='#plan-references')return 'references';
    const match=/^#section-(\d+)$/.exec(location.hash);
    return match&&keys.includes(match[1])?match[1]:'catalog';
  }
  function show(key,{navigate=false,focus=false}={}){
    if(!['catalog','all',...keys].includes(key))key='catalog';
    if(selected==='catalog'&&key!=='catalog')catalogScroll=window.scrollY;
    selected=key;
    const index=keys.indexOf(key),card=cards[index];
    if(card)lastCard=card;
    const edit=bar.querySelector('[data-reader-edit]');if(edit){edit.hidden=!card?.dataset.readerWriteUrl;if(card?.dataset.readerWriteUrl)edit.href=card.dataset.readerWriteUrl;}
    body.dataset.readerView=key==='catalog'?'catalog':key==='all'?'all':key==='references'?'references':'section';
    catalog.hidden=key!=='catalog';bar.hidden=key==='catalog';
    sections.forEach(section=>{
      const matches=key==='references'?section.classList.contains('fp-appendix'):
        section.id==='section-'+key||section.dataset.planSection===key;
      section.dataset.readerSelected=String(matches);
    });
    if(key!=='catalog'){
      bar.querySelector('[data-reader-heading]').textContent=key==='all'?'전체 문서':card.dataset.readerTitle;
      bar.querySelector('[data-reader-position]').textContent=key==='all'?'전체':key==='references'?'참고 자료':String(index+1).padStart(2,'0')+' / 15';
      prev.disabled=index<=0;next.disabled=index<0||index===keys.length-1;
      prev.setAttribute('aria-label',index>0?'이전 · '+cards[index-1].dataset.readerTitle:'이전 섹션 없음');
      next.setAttribute('aria-label',index>=0&&index<keys.length-1?'다음 · '+cards[index+1].dataset.readerTitle:'다음 섹션 없음');
    }
    if(navigate){
      const url=new URL(location.href);
      url.hash=key==='catalog'?'plan-contents':key==='all'?'full-document':key==='references'?'plan-references':'section-'+key;
      if(url.href!==location.href)history.pushState(null,'',url);
    }
    if(focus){
      window.scrollTo({top:key==='catalog'?catalogScroll:0,behavior:'instant'});
      (key==='catalog'?(lastCard||cards[0]):back).focus({preventScroll:true});
      document.querySelector('[data-reader-announce]').textContent=key==='catalog'?'소방계획서 섹션 목차':bar.querySelector('[data-reader-heading]').textContent+' 내용';
    }
  }
  catalog.querySelectorAll('[data-reader-select]').forEach(link=>link.addEventListener('click',event=>{
    if(event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey)return;
    if(link.dataset.readerNeedsWrite==='true'&&link.dataset.readerWriteUrl)return;
    event.preventDefault();show(link.dataset.readerSelect,{navigate:true,focus:true});
  }));
  back.addEventListener('click',()=>show('catalog',{navigate:true,focus:true}));
  prev.addEventListener('click',()=>show(keys[keys.indexOf(selected)-1],{navigate:true,focus:true}));
  next.addEventListener('click',()=>show(keys[keys.indexOf(selected)+1],{navigate:true,focus:true}));
  window.addEventListener('popstate',()=>show(fromHash(),{focus:true}));
  window.addEventListener('hashchange',()=>show(fromHash()));
  body.classList.add('fp-reader-ready');
  show(fromHash());
})();
