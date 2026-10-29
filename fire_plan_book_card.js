(() => {
  'use strict';
  function installBookSwipe(card, turn) {
    const preview = card.querySelector('.planhome-document');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let gesture = null;
    let suppressClickUntil = 0;
    function reset() {
      const previous = gesture;
      gesture = null;
      card.classList.remove('is-book-swiping');
      preview?.style.removeProperty('transform');
      if (previous && card.hasPointerCapture?.(previous.id)) card.releasePointerCapture(previous.id);
    }
    card.addEventListener('pointerdown', event => {
      // A second finger belongs to pinch zoom, never to year navigation.
      if (!event.isPrimary) { reset(); return; }
      suppressClickUntil = 0;
      if (event.button !== 0) return;
      const control = event.target.closest('button, input, select, textarea, summary, a, [contenteditable="true"]');
      if (control && !control.matches('[data-book-cover-open]')) return;
      reset();
      gesture = {id:event.pointerId, x:event.clientX, y:event.clientY, horizontal:false};
    });
    card.addEventListener('pointermove', event => {
      if (!gesture || event.pointerId !== gesture.id) return;
      const dx = event.clientX - gesture.x;
      const dy = event.clientY - gesture.y;
      if (!gesture.horizontal) {
        if (Math.max(Math.abs(dx), Math.abs(dy)) < 10) return;
        if (Math.abs(dx) <= Math.abs(dy) * 1.3) { reset(); return; }
        gesture.horizontal = true;
        card.classList.add('is-book-swiping');
        card.setPointerCapture?.(event.pointerId);
      }
      if (event.cancelable) event.preventDefault();
      if (preview && !reducedMotion.matches) preview.style.transform = `translateX(${Math.max(-32, Math.min(32, dx * .25))}px)`;
    });
    card.addEventListener('pointerup', event => {
      if (!gesture || event.pointerId !== gesture.id) return;
      const dx = event.clientX - gesture.x;
      const dy = event.clientY - gesture.y;
      const horizontal = gesture.horizontal;
      const threshold = Math.max(40, Math.min(72, card.clientWidth * .16));
      reset();
      if (!horizontal) return;
      suppressClickUntil = Date.now() + 800;
      if (Math.abs(dx) >= threshold && Math.abs(dx) > Math.abs(dy) * 1.3) turn(dx < 0 ? 1 : -1);
    });
    card.addEventListener('pointercancel', reset);
    card.addEventListener('lostpointercapture', event => {
      // Touch starts with implicit capture on the cover link. Transferring that
      // capture to the card must not cancel the gesture through the bubbled event.
      if (event.target === card && event.pointerId === gesture?.id) reset();
    });
    card.addEventListener('dragstart', event => {
      if (event.target.closest('[data-book-cover-open]')) event.preventDefault();
    });
    // The dashboard opens links during document capture. Block the swipe's synthetic
    // click earlier, at window capture, while keeping ordinary taps and keyboard clicks.
    window.addEventListener('click', event => {
      if (event.detail === 0 || Date.now() > suppressClickUntil || !card.contains(event.target)) return;
      suppressClickUntil = 0;
      event.preventDefault();event.stopImmediatePropagation();
    }, true);
  }

  document.querySelectorAll('[data-book-card]').forEach(card => {
    let years;
    try { years = JSON.parse(card.dataset.bookYears); } catch { return; }
    if (!Array.isArray(years) || years.length !== 3) return;
    const prev = card.querySelector('[data-book-prev]');
    const next = card.querySelector('[data-book-next]');
    const year = card.querySelector('[data-book-year]');
    const label = card.querySelector('[data-book-label]');
    const state = card.querySelector('[data-book-state]');
    const open = card.querySelector('[data-book-open]');
    const cover = card.querySelector('[data-book-cover-open]');
    const announce = card.querySelector('[data-book-announce]');
    const edit = card.querySelector('[data-book-edit]');
    const progress = card.querySelector('[data-book-progress]');
    const help = card.querySelector('[data-book-help]');
    const requestedYear = Number(new URL(location.href).searchParams.get('plan_year'));
    const initialIndex = years.findIndex(entry => entry.year === requestedYear);
    let index = initialIndex < 0 ? 1 : initialIndex;
    let slideAnimation = null;
    function updateHelp() {
      if (!help) return;
      const entry = years[index];
      const helpState = window.managerHelp?.getState?.();
      const pending = (helpState?.rows || []).filter(row => entry.id &&
        row.connection_active && row.status === 'pending' &&
        String(row.field || '').startsWith(`__fp_${entry.id}_`));
      help.hidden = pending.length === 0;
      help.textContent = pending.length ? `도움 ${pending.length}` : '';
      help.setAttribute('aria-label', `${entry.year}년 소방계획서 작성 문의 ${pending.length}건`);
      help.href = entry.editHref || entry.href;
      const match = /^__fp_([0-9]+)_([0-9]+)_([A-Za-z0-9_]+)$/.exec(pending[0]?.field || '');
      if (helpState?.mode === 'manager' && match) {
        const target = new URL(entry.editHref, location.href);
        target.pathname = '/fire_plan_chat.php';
        target.searchParams.set('id', match[1]);
        target.searchParams.set('help_code', match[2]);
        target.searchParams.set('help_key', match[3]);
        help.href = target.href;
      }
    }
    function show(target, notify = true) {
      const previousIndex = index;
      index = Math.max(0, Math.min(years.length - 1, target));
      const entry = years[index];
      year.textContent = `${entry.year}년`;
      label.textContent = entry.label;
      card.querySelectorAll('[data-book-cover-year]').forEach(el => el.textContent = entry.year);
      card.querySelectorAll('[data-book-title-year]').forEach(el => el.textContent = `${entry.year}년`);
      const coverName = card.querySelector('[data-book-name]');
      if (coverName) coverName.textContent = entry.name || '우리 건물';
      const progressLabel = card.querySelector('[data-book-progress-label]');
      if (progressLabel) progressLabel.textContent = entry.reviewed ? '최종 확인 완료' : '항목 채움 현황';
      state.textContent = entry.state;
      state.classList.toggle('is-reviewed', entry.reviewed ?? entry.state === '확인 완료');
      open.href = entry.href;
      if (cover) {
        cover.href = entry.href;
        cover.setAttribute('aria-label', `${entry.year}년 소방계획서 ${entry.hasPlan ? '열기' : '시작하기'}`);
        const hint = cover.querySelector('.planhome-cover-hint');
        if (hint) hint.textContent = entry.hasPlan ? '눌러서 자료 반영 · 내용 작성 ↗' : '눌러서 계획서 시작하기 ↗';
      }
      open.setAttribute('aria-label', `${entry.year}년 우리 건물 소방계획서 보기 · ${entry.state}`);
      if (edit && progress) {
        if(edit.tagName==='A') edit.href = entry.href;
        edit.textContent = entry.previewOnly ? '예시 소방계획서 미리보기 →' : entry.reviewed ? '계획서 확인하기 →' : entry.awaitingImport ? '저장자료로 계획서 시작 →' : '계획서 완성하기 →';
        edit.setAttribute('aria-label', `${entry.year}년 소방계획서 ${entry.actionLabel}`);
        open.hidden = entry.previewOnly || !entry.hasPlan;
        card.querySelector('[data-book-percent]').textContent = entry.known ? (entry.awaitingImport ? '준비 중' : `${entry.percent}%`) : '—';
        card.querySelector('[data-book-fill]').style.width = `${entry.known ? entry.percent : 0}%`;
        if (entry.known) progress.setAttribute('aria-valuenow', entry.percent);
        else progress.removeAttribute('aria-valuenow');
        progress.setAttribute('aria-valuetext', entry.known ? `${entry.filled} / ${entry.total}개 질문, ${entry.percent}%` : '계획서 상태 확인 필요');
        card.querySelector('[data-book-count]').textContent = entry.known ? (entry.awaitingImport ? '저장된 업무자료를 반영해 주세요' : `${entry.filled} / ${entry.total}개 질문`) : '상태 확인 필요';
        card.querySelector('[data-book-remaining]').textContent = entry.known ? (entry.awaitingImport ? '기본정보·시설 현황 불러오기' : `남은 질문 ${entry.remaining}개`) : '잠시 후 다시 확인';
        card.querySelector('[data-book-source-count]').textContent = entry.known ? entry.sourceCount : '—';
        const sourceList = card.querySelector('[data-book-source-list]');
        sourceList.replaceChildren();
        for (const title of entry.sources || []) {
          const item = document.createElement('li'); item.textContent = title; sourceList.append(item);
        }
        card.querySelector('[data-book-source-note]').textContent = !entry.known ? '자료 상태를 확인하지 못했습니다.' :
          entry.sourceCount ? '기본정보·소방시설은 저장 시 자동 반영됩니다.' : '기본정보·소방시설을 저장하면 자동으로 반영됩니다.';
        updateHelp();
      }
      prev.disabled = index === 0;
      next.disabled = index === years.length - 1;
      prev.setAttribute('aria-label', index > 0 ? `${years[index - 1].year}년 계획서로 넘기기` : '이전 연도 없음');
      next.setAttribute('aria-label', index < years.length - 1 ? `${years[index + 1].year}년 계획서로 넘기기` : '다음 연도 없음');
      card.dataset.selectedYear = String(entry.year);
      if (card.closest('[data-compact-dashboard]') && notify) {
        const url = new URL(location.href); url.searchParams.set('plan_year', entry.year);
        history.replaceState(history.state, '', url);
      }
      document.dispatchEvent(new CustomEvent('fire-plan-year-change', {detail:{year:entry.year}}));
      if (notify) announce.textContent = `${entry.year}년 ${entry.label}, ${entry.state}. 업무 카드도 ${entry.year}년으로 변경되었습니다.`;
      if (notify && previousIndex !== index && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        slideAnimation?.cancel();
        slideAnimation = card.querySelector('.planhome-document')?.animate([
          {transform:`translateX(${index > previousIndex ? 22 : -22}px)`, opacity:.65},
          {transform:'translateX(0)', opacity:1}
        ], {duration:180, easing:'ease-out'});
      }
    }
    installBookSwipe(card, direction => show(index + direction));
    prev.addEventListener('click', () => show(index - 1));
    next.addEventListener('click', () => show(index + 1));
    card.querySelector('.building-plan-book__years').addEventListener('keydown', event => {
      if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
      event.preventDefault();
      show(index + (event.key === 'ArrowLeft' ? -1 : 1));
    });
    document.addEventListener('manager-help-updated', updateHelp);
    window.managerHelp?.ready?.then(updateHelp).catch(() => {});
    show(index, false);
  });
})();
