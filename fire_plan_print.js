(() => {
  'use strict';
  const current = new URL(window.location.href);
  const standalone = current.searchParams.get('print_view') === '1' && window.self === window.top;
  let printing = false;

  function notice(message, href) {
    let box = document.getElementById('fp-print-notice');
    if (!box) {
      box = document.createElement('p');
      box.id = 'fp-print-notice';
      box.className = 'hint';
      box.setAttribute('role', 'status');
      document.body.prepend(box);
    }
    box.replaceChildren(document.createTextNode(message));
    if (href) {
      const link = document.createElement('a');
      link.href = href;
      link.target = '_blank';
      link.rel = 'noopener';
      link.textContent = ' 인쇄창 열기';
      box.append(link);
    }
  }

  async function printReady() {
    if (printing) return;
    printing = true;
    try {
      // A print-only stylesheet can load late in Safari. The shared stylesheet
      // is now loaded with the page, and printing waits for layout and fonts.
      if (document.readyState !== 'complete') {
        await new Promise(resolve => window.addEventListener('load', resolve, {once: true}));
      }
      if ([...document.querySelectorAll('link[rel="stylesheet"]')].some(link => !link.sheet)) {
        throw new Error('stylesheet unavailable');
      }
      if (document.fonts) await document.fonts.ready;
      if(window.eplMapsReady)await window.eplMapsReady();
      await new Promise(resolve => window.requestAnimationFrame(() => window.requestAnimationFrame(resolve)));
      window.focus();
      window.print();
    } catch (_) {
      notice('인쇄 화면을 준비하지 못했습니다. 새로고침 후 인쇄 / PDF 저장을 다시 눌러 주세요.');
    } finally {
      printing = false;
    }
  }

  window.fpPrintDocument = function () {
    if (standalone) return printReady();
    const url = new URL(window.location.href);
    // Preserve year, plan ID, admin context and shared token on the SAME route.
    // No document data is copied into a new, unauthenticated endpoint.
    ['modal', 'embed'].forEach(key => url.searchParams.delete(key));
    url.searchParams.set('print_view', '1');
    url.searchParams.set('auto_print', '1');
    url.hash = '';
    const popup = window.open(url.href, 'fire-plan-print');
    if (popup) popup.focus();
    else notice('브라우저가 새 인쇄창을 막았습니다.', url.href);
  };

  if (standalone && current.searchParams.get('auto_print') === '1') {
    current.searchParams.delete('auto_print');
    window.history.replaceState(window.history.state, '', current.href);
    printReady();
  }
})();
