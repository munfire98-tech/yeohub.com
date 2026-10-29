(() => {
  'use strict';
  const source = document.currentScript;
  const styleUrl = new URL(source?.dataset.styleUrl || '/building_mobile_touch.css?v=20261013-1', location.href);
  if (styleUrl.origin !== location.origin) return;
  const documents = new WeakSet();
  const frames = new WeakSet();

  function watchFrame(frame) {
    if (frames.has(frame)) return;
    frames.add(frame);
    const loaded = () => {
      // Only our own pages are editable. External payment/video frames are left alone.
      try {
        const doc = frame.contentDocument;
        if (!doc || doc.location.origin !== location.origin || !/^https?:$/.test(doc.location.protocol)) return;
        prepare(doc);
      } catch (_) { /* Cross-origin frame. */ }
    };
    frame.addEventListener('load', loaded);
    loaded();
  }

  function scan(node) {
    if (node.nodeType !== 1 && node.nodeType !== 9) return;
    if (node.tagName === 'IFRAME') watchFrame(node);
    node.querySelectorAll('iframe').forEach(watchFrame);
  }

  function prepare(doc) {
    if (!doc.head || documents.has(doc)) return;
    documents.add(doc);
    if (!doc.getElementById('building-mobile-touch-style')) {
      const link = doc.createElement('link');
      link.id = 'building-mobile-touch-style';
      link.rel = 'stylesheet';
      link.href = styleUrl.href;
      doc.head.appendChild(link);
    }
    scan(doc);
    // Popups are added on demand, and their frames may navigate between forms.
    const observer = new MutationObserver(records => {
      for (const record of records) record.addedNodes.forEach(scan);
    });
    const observe = () => {
      // A popup iframe can replace its document before a queued pageshow runs.
      if (doc.documentElement) observer.observe(doc.documentElement, {childList:true, subtree:true});
    };
    observe();
    doc.defaultView?.addEventListener('pagehide', () => observer.disconnect());
    doc.defaultView?.addEventListener('pageshow', event => { if (event.persisted) observe(); });
  }
  prepare(document);
})();
