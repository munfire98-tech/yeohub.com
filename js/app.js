document.addEventListener('DOMContentLoaded', () => {

  /* ================= DOM ================= */
  const modal = document.getElementById('modal');
  const box = modal.querySelector('.box');

  const m_id = document.getElementById('m_id');
  const m_name = document.getElementById('m_name');
  const m_order = document.getElementById('m_order');
  const m_section = document.getElementById('m_section');
  const m_visited = document.getElementById('m_visited');
  const m_visit_date = document.getElementById('m_visit_date');
  const m_it = document.getElementById('m_it');
  const m_if = document.getElementById('m_if');
  const m_memo = document.getElementById('m_memo');
  const m_is_important = document.getElementById('m_is_important');
  const m_safety_manager_name = document.getElementById('m_safety_manager_name');
  const m_safety_manager_phone = document.getElementById('m_safety_manager_phone');

  /* ================= modal ================= */
  window.openClientModal = () => modal.classList.remove('is-hidden');
  window.closeModal = () => modal.classList.add('is-hidden');

  box.addEventListener('click', e => e.stopPropagation());
  modal.addEventListener('click', () => closeModal());

  /* ================= section toggle ================= */
  window.toggle = id => {
    document.getElementById(id).classList.toggle('is-hidden');
  };

  /* ================= client click ================= */
  document.querySelectorAll('.client').forEach(el => {
    el.addEventListener('click', () => {
      const c = JSON.parse(el.dataset.client);
      m_id.value = c.id;
      m_name.innerText = c.name;
      m_order.value = c.order || 0;
      m_section.value = c.section;
      m_visited.checked = !!c.visited;
      m_visit_date.value = c.visit_date || '';
      m_it.value = c.inspection_month_total || '';
      m_if.value = c.inspection_month_function || '';
      m_memo.value = c.memo || '';
      m_is_important.checked = !!c.is_important;
      m_safety_manager_name.value = c.safety_manager_name || '';
      m_safety_manager_phone.value = c.safety_manager_phone || '';
      openClientModal();
    });
  });

  /* ================= save ================= */
  window.save = (e) => {
    if (e) e.stopPropagation();

    const fd = new FormData();
    fd.append('action', 'save');
    fd.append('id', m_id.value);
    fd.append('section', m_section.value);
    fd.append('order', m_order.value);
    fd.append('visited', m_visited.checked);
    fd.append('visit_date', m_visit_date.value);
    fd.append('inspection_month_total', m_it.value);
    fd.append('inspection_month_function', m_if.value);
    fd.append('memo', m_memo.value);
    fd.append('safety_manager_name', m_safety_manager_name.value);
    fd.append('safety_manager_phone', m_safety_manager_phone.value);
    fd.append('is_important', m_is_important.checked);

    fetch('', { method: 'POST', body: fd });
    closeModal();
  };

  /* ================= delete ================= */
  window.deleteClient = () => {
    if (!confirm('정말 삭제할까요?')) return;

    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', m_id.value);

    fetch('', { method: 'POST', body: fd });

    document
      .querySelector(`.client[data-client*='"id":"${m_id.value}"']`)
      ?.remove();

    closeModal();
  };

  /* ================= move ================= */
  window.move = (e, btn, dir) => {
    e.stopPropagation();

    const el = btn.closest('.client');
    const parent = el.parentElement;
    const target =
      dir === -1 ? el.previousElementSibling : el.nextElementSibling;

    if (!target) return;

    parent.insertBefore(
      dir === -1 ? el : target,
      dir === -1 ? target : el
    );

    const fd = new FormData();
    fd.append('action', 'move');
    fd.append('id', JSON.parse(el.dataset.client).id);
    fd.append('section', el.dataset.section);
    fd.append('dir', dir);

    fetch('', { method: 'POST', body: fd });
  };

});

// ===== 지도 모달 테스트용 =====
window.openMapModal = function () {
  const modal = document.getElementById('mapModal');
  if (!modal) {
    alert('mapModal 없음');
    return;
  }
  modal.classList.remove('is-hidden');
};

window.closeMapModal = function () {
  const modal = document.getElementById('mapModal');
  modal.classList.add('is-hidden');
};


