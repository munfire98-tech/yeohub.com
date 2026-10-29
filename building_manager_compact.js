(() => {
  'use strict';
  const root = document.querySelector('[data-compact-dashboard]');
  if (!root) return;
  const tabs = root.querySelector('.compact-mobile-tabs');
  const buttons = Array.from(root.querySelectorAll('[data-compact-view]'));
  const panels = {work:'compactWorkPanel', tools:'compactToolsPanel', plan:'compactPlanPanel', building:'compactBuildingPanel'};
  const deckViews = ['work', 'tools', 'building'];
  const deckTitles = {work:'소방안전관리 업무', tools:'소방안전관리 업무', building:'건물현황'};
  const deckSections = {work:'업무 1 · 작성 업무', tools:'업무 2 · 현장 기록·교육·인쇄', building:'건물 정보'};
  const deckDestinations = {work:'업무로 돌아가기', tools:'업무로 돌아가기', building:'건물현황 보기'};
  let lastWorkView = 'work';
  const deck = root.querySelector('#compactSectionDeck');
  const deckPages = deck ? Array.from(deck.querySelectorAll('[data-deck-view]')) : [];
  function turnDeck(view, announce = false) {
    if (!deck || !deckViews.includes(view)) return;
    for (const key of deckViews) {
      const panel = document.getElementById(panels[key]);
      if (!panel) continue;
      // Every panel sizes the shared card; only the active view is accessible.
      panel.dataset.deckActive = String(key === view);
      panel.toggleAttribute('inert', key !== view);
      panel.setAttribute('aria-hidden', String(key !== view));
    }
    const index = deckViews.indexOf(view);
    const title = deckTitles[view];
    if (view !== 'building') lastWorkView = view;
    const next = view === 'building' ? lastWorkView : 'building';
    deck.querySelector('.compact-work-pages').hidden = view === 'building';
    deck.querySelector('[data-deck-title]').textContent = title;
    deck.querySelector('[data-deck-section]').textContent = deckSections[view];
    deck.querySelector('[data-deck-destination]').textContent = deckDestinations[next];
    const mobileDestination = deck.querySelector('[data-deck-mobile-destination]');
    if (mobileDestination) mobileDestination.textContent = next === 'building' ? '건물현황' : '업무';
    deckPages.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.deckView === view)));
    deck.querySelectorAll('[data-deck-prev], [data-deck-next]').forEach(button => {
      const target = button.hasAttribute('data-deck-prev') ? deckViews[(index + deckViews.length - 1) % deckViews.length] : next;
      button.setAttribute('aria-label', deckSections[target] + ' · ' + deckTitles[target] + ' 보기');
      button.dataset.deckTarget = target;
    });
    if (announce) deck.querySelector('[data-deck-announce]').textContent = deckSections[view] + ', ' + title + ', ' + (index + 1) + ' / ' + deckViews.length;
  }
  function show(view) {
    // Connection controls remain visible above every section.
    if (view === 'manager') view = 'work';
    if (!Object.prototype.hasOwnProperty.call(panels, view)) return;
    const mobileView = view === 'tools' ? 'work' : view;
    if (view !== 'plan') { const url=new URL(location.href);url.searchParams.set('work_page',view);history.replaceState(history.state,'',url); }
    root.dataset.mobileView = mobileView;
    if (view !== 'plan') turnDeck(view, true);
    buttons.forEach(button => button.setAttribute('aria-pressed',String(button.dataset.compactView === mobileView)));
  }
  window.showBuildingCompactPanel = show;
  buttons.forEach(button => button.addEventListener('click',()=>show(button.dataset.compactView)));
  if (tabs) tabs.hidden = false;
  if (deck) {
    const switchTo = view => show(view);
    deck.querySelectorAll('[data-deck-prev], [data-deck-next]').forEach(button => {
      button.addEventListener('click', () => switchTo(button.dataset.deckTarget));
    });
    deckPages.forEach(button => button.addEventListener('click', () => switchTo(button.dataset.deckView)));
    deck.querySelector('.compact-deck__arrows').hidden = false;
    deck.querySelector('.compact-deck__pages').hidden = false;
    turnDeck('work');
  }
  const initialPage = new URL(location.href).searchParams.get('work_page');
  // Start with actionable work; the mobile plan preview remains alongside it.
  show(deckViews.includes(initialPage) ? initialPage : 'work');
  root.classList.add('compact-ready');
  root.addEventListener('click',event=>{
    const link=event.target.closest('a[href]');
    if(link && link.hash==='#manager-connect') show('manager');
  });
  const help=root.querySelector('.planhome-assistance');
  if(help){
    document.addEventListener('click',event=>{if(help.open&&!help.contains(event.target))help.open=false;});
    help.addEventListener('keydown',event=>{if(event.key==='Escape'){help.open=false;help.querySelector('summary')?.focus();}});
  }
  const managerDetails = document.querySelector('#manager-connect .mc-mini__details');
  if (managerDetails) {
    document.addEventListener('click', event => {
      if (managerDetails.closest('.planhome-header-manager') && managerDetails.open && !managerDetails.contains(event.target)) managerDetails.open = false;
    });
    managerDetails.addEventListener('keydown', event => {
      if (event.key === 'Escape' && managerDetails.closest('.planhome-header-manager')) {
        managerDetails.open = false;
        managerDetails.querySelector('summary')?.focus({preventScroll:true});
      }
    });
  }

  const book = root.querySelector('[data-book-card]');
  let workYears = [];
  try { workYears = JSON.parse(book?.dataset.bookYears || '[]'); } catch {}
  function syncWorkYear(year) {
    const entry = workYears.find(entry => entry.year === Number(year));
    const work = entry?.work;
    const panel = document.getElementById('compactWorkPanel');
    if (!work || !panel) return;
    panel.dataset.workYear = work.year;
    panel.dataset.workMode = work.mode;
    panel.querySelector('[data-work-year-title]').textContent = work.title;
    panel.querySelector('.compact-progress-count').textContent = `${work.done} / ${work.total} ${work.unit}`;
    const track = panel.querySelector('.compact-progress__track');
    track.setAttribute('aria-label', work.title);
    track.setAttribute('aria-valuemax', work.total);track.setAttribute('aria-valuenow', work.done);
    track.setAttribute('aria-valuetext', `${work.done} / ${work.total} ${work.unit}`);
    track.firstElementChild.style.width = `${Math.round(work.done * 100 / work.total)}%`;
    panel.querySelector('.compact-progress__note').textContent = work.note;
    panel.querySelector('[data-work-year-label]').textContent = `${work.year}년 ${work.mode==='future'?'계획':'기록'}`;
    for (const card of panel.querySelectorAll('[data-annual-task]')) {
      const task = work.tasks[card.dataset.annualTask];
      if (!('annualNow' in card.dataset)) card.dataset.annualNow=String(card.classList.contains('is-now'));
      card.dataset.annualRecords=JSON.stringify(task.recordIds||[]);
      card.href = task.href;card.dataset.helpOriginalHref = task.href;
      card.dataset.annualCurrent = String(work.mode==='current');
      card.classList.toggle('is-done', task.done);card.classList.toggle('is-planned', task.planned);
      card.classList.toggle('is-now',work.mode==='current'&&!task.done&&card.dataset.annualNow==='true');
      card.classList.remove('mh-help-glow');
      card.querySelectorAll('[data-help-bell]').forEach(el=>el.remove());
      card.querySelector('.building-task__description').textContent = task.description;
      const badge=card.querySelector('small');badge.textContent=task.badge;
      if(work.mode==='current'&&task.done){
        const check=panel.querySelector('.task-done')?.cloneNode(true);
        if(check){check.setAttribute('aria-label',task.badge);check.title=task.badge;badge.replaceChildren(check);}
      }
      const access=document.querySelector('script[src^="/building_service_access.js?v=20261015-partner-1"]');
      if(access&&(access.dataset.connected!=='1'||access.dataset.paid!=='1')){
        badge.textContent=access.dataset.connected==='1'?'PRO':'연결 필요';
      }
      card.setAttribute('aria-label', `${work.year}년 ${card.children[1].textContent}, ${task.description}, ${task.badge}`);
    }
    const mobileWork = tabs?.querySelector('[data-compact-view="work"]');
    if (mobileWork) mobileWork.textContent = `업무 · ${work.year}`;
    document.dispatchEvent(new Event('building-work-year-updated'));
  }
  document.addEventListener('fire-plan-year-change', event=>syncWorkYear(event.detail.year));
  syncWorkYear(book?.dataset.selectedYear || workYears[1]?.year);

  if(location.hash==='#manager-connect')show('manager');
})();
