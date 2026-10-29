(() => {
  'use strict';
  const app = document.querySelector('.fp-team-app');
  if (!app) return;
  const buttons = [...app.querySelectorAll('[data-team-view]')];
  const cards = [...app.querySelectorAll('.fp-team-person')];
  const search = app.querySelector('#fp-team-search');
  const result = app.querySelector('.fp-team-search-result');
  const normalize = text => String(text).normalize('NFKC').toLocaleLowerCase('ko-KR').replace(/\s+/g, '');
  function show(view) {
    if (!['team', 'document'].includes(view)) return;
    document.body.dataset.sharedView = view;
    buttons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.teamView === view)));
  }
  buttons.forEach(button => button.addEventListener('click', () => show(button.dataset.teamView)));
  app.querySelectorAll('[data-team-document]').forEach(link => link.addEventListener('click', () => show('document')));
  app.querySelector('.fp-team-nav').hidden = false;
  document.querySelectorAll('[data-team-back]').forEach(button => {
    button.hidden = false;
    button.addEventListener('click', () => {
      show('team');
      app.scrollIntoView({block:'start'});
      buttons.find(b => b.dataset.teamView === 'team')?.focus({preventScroll:true});
    });
  });
  if (search) {
    app.querySelector('.fp-team-search').hidden = false;
    search.addEventListener('input', () => {
      const term = normalize(search.value);
      let matches = 0;
      cards.forEach(card => {
        const visible = normalize(card.dataset.teamSearch).includes(term);
        card.hidden = !visible;
        if (visible) matches++;
      });
      result.hidden = !term;
      result.textContent = `검색된 역할 ${matches}개`;
      app.querySelector('.fp-team-no-match').hidden = matches !== 0;
    });
    search.addEventListener('search', () => search.dispatchEvent(new Event('input')));
  }
  // Name selection is for reading only. No identity claim, receipt, storage or network request.
  show(/^#section-/.test(location.hash) ? 'document' : 'team');
  document.body.classList.add('fp-team-ready');
  window.addEventListener('hashchange', () => { if (/^#section-/.test(location.hash)) show('document'); });
})();
