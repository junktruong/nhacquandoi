(() => {
  const root = document.querySelector('[data-cats-admin]');
  if (!root) return;

  const csrf = root.getAttribute('data-csrf') || '';
  const api = root.getAttribute('data-api') || 'categories_api.php';
  const baseUrl = document.body?.getAttribute('data-baseurl') || '';

  function qs(sel, el=document){ return el.querySelector(sel); }
  function qsa(sel, el=document){ return Array.from(el.querySelectorAll(sel)); }

  async function post(action, params = {}) {
    const body = new URLSearchParams({ action, csrf_token: csrf, ...params });
    const res = await fetch(api, { method: 'POST', headers: { 'Accept': 'application/json' }, body });
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); } catch { data = { ok:false, error: text || 'Bad JSON' }; }
    if (!res.ok) data.ok = false;
    return data;
  }

  function escapeHtml(str){
    return String(str)
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'","&#039;");
  }

  function makeItem({id, name}) {
    const li = document.createElement('li');
    li.className = 'cat-item';
    li.dataset.id = String(id);
    li.dataset.songs = '0';
    li.dataset.kids = '0';
    li.innerHTML = `
      <div class="cat-row">
        <div class="handle" draggable="true" title="Kéo để đổi thứ tự">⠿</div>
        <input class="cat-name" value="${escapeHtml(name)}" disabled />
        <div class="cat-badges"></div>
        <div class="cat-actions">
          <button class="cat-btn cat-btn-edit" type="button">Sửa</button>
          <button class="cat-btn cat-btn-save cat-btn--gold" type="button" style="display:none;">Lưu</button>
          <a class="cat-btn cat-btn-music" href="${baseUrl}/admin/songs.php?cat=${id}">Nhạc</a>
          <button class="cat-btn cat-btn-add" type="button">+ Con</button>
          <button class="cat-btn cat-btn-del cat-btn--danger" type="button">Xóa</button>
        </div>
      </div>
      <ul class="cat-children" data-parent="${id}"></ul>
    `;
    return li;
  }

  function updateBadgesAndMusic(li){
    const kids = Number(li.dataset.kids || '0');
    const songs = Number(li.dataset.songs || '0');

    const badges = qs('.cat-badges', li);
    if (badges) {
      badges.innerHTML = '';
      if (kids > 0) {
        const b = document.createElement('span');
        b.className = 'cat-badge cat-badge--kids';
        b.textContent = `${kids} mục con`;
        badges.appendChild(b);
      }
      if (songs > 0) {
        const b = document.createElement('span');
        b.className = 'cat-badge cat-badge--songs';
        b.textContent = `${songs} bài`;
        badges.appendChild(b);
      }
    }

    const a = qs('.cat-btn-music', li);
    if (!a) return;

    if (kids === 0) {
      a.style.display = '';
      a.textContent = 'Nhạc';
      a.classList.remove('cat-btn-music--hidden');
    } else {
      if (songs > 0) {
        a.style.display = '';
        a.textContent = 'Nhạc (ẩn)';
        a.classList.add('cat-btn-music--hidden');
      } else {
        a.style.display = 'none';
      }
    }
  }

  function getListParentId(ul){
    const p = ul.getAttribute('data-parent');
    return p ? Number(p) : 0;
  }

  async function computeAndSendOrder(ul){
    const parent_id = getListParentId(ul);
    const ids = Array.from(ul.children)
      .filter(x => x.classList.contains('cat-item'))
      .map(x => Number(x.dataset.id));
    return post('reorder', { parent_id: String(parent_id), ids: JSON.stringify(ids) });
  }

  function getDragAfterElement(container, y){
    const els = [...container.querySelectorAll('.cat-item:not(.dragging)')];
    return els.reduce((closest, child) => {
      const box = child.getBoundingClientRect();
      const offset = y - box.top - box.height / 2;
      if (offset < 0 && offset > closest.offset) return { offset, element: child };
      return closest;
    }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
  }

  function initSortable(ul){
    ul.addEventListener('dragstart', (e) => {
      const handle = e.target.closest('.handle');
      if (!handle) return; // chỉ cho kéo từ handle
      const li = handle.closest('.cat-item');
      if (!li) return;
      li.classList.add('dragging');
      try { e.dataTransfer.setDragImage(li, 18, 18); } catch {}
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', li.dataset.id || '');
    });

    ul.addEventListener('dragend', (e) => {
      const li = e.target.closest('.cat-item');
      if (li) li.classList.remove('dragging');
    });

    ul.addEventListener('dragover', (e) => {
      e.preventDefault();
      const dragging = ul.querySelector('.cat-item.dragging');
      if (!dragging) return;
      const after = getDragAfterElement(ul, e.clientY);
      if (after == null) ul.appendChild(dragging);
      else ul.insertBefore(dragging, after);
    });

    ul.addEventListener('drop', async (e) => {
      e.preventDefault();
      const data = await computeAndSendOrder(ul);
      if (!data.ok) alert(data.error || 'Lỗi sắp xếp');
    });
  }

  function enhanceTree(scope){
    qsa('.cat-children', scope).forEach(ul => initSortable(ul));
    qsa('.cat-item', scope).forEach(li => updateBadgesAndMusic(li));
  }
  enhanceTree(root);

  root.addEventListener('click', async (e) => {
    const btn = e.target.closest('button');
    if (!btn) return;

    if (btn.matches('[data-add-root]')) {
      const name = prompt('Tên danh mục mới (cấp cao nhất):');
      if (!name) return;
      const data = await post('add', { parent_id: '0', name });
      if (!data.ok) return alert(data.error || 'Lỗi thêm danh mục');
      const rootUl = qs('.cat-children[data-parent="0"]', root);
      rootUl.appendChild(makeItem({id: data.id, name: data.name}));
      enhanceTree(rootUl);
      return;
    }

    const li = btn.closest('.cat-item');
    if (!li) return;

    const id = Number(li.dataset.id || 0);
    if (!id) return;

    const nameInput = qs('.cat-name', li);
    const btnEdit = qs('.cat-btn-edit', li);
    const btnSave = qs('.cat-btn-save', li);

    if (btn.classList.contains('cat-btn-edit')) {
      nameInput.disabled = false;
      nameInput.focus();
      nameInput.select();
      btnEdit.style.display = 'none';
      btnSave.style.display = '';
      return;
    }

    if (btn.classList.contains('cat-btn-save')) {
      const newName = (nameInput.value || '').trim();
      if (!newName) return alert('Tên không được trống');
      li.classList.add('is-saving');
      const data = await post('rename', { id: String(id), name: newName });
      li.classList.remove('is-saving');
      if (!data.ok) {
        li.classList.add('is-error');
        return alert(data.error || 'Lỗi lưu');
      }
      nameInput.value = data.name;
      nameInput.disabled = true;
      btnEdit.style.display = '';
      btnSave.style.display = 'none';
      li.classList.remove('is-error');
      return;
    }

    if (btn.classList.contains('cat-btn-add')) {
      const kids = Number(li.dataset.kids || '0');
      const songs = Number(li.dataset.songs || '0');

      if (kids === 0 && songs > 0) {
        const ok = confirm(`Danh mục này đang có ${songs} bài hát. Nếu thêm mục con, danh mục sẽ trở thành mục cha và nhạc sẽ bị ẩn khi duyệt.\n\nBạn chắc chắn muốn thêm mục con?`);
        if (!ok) return;
      }

      const name = prompt('Tên mục con:');
      if (!name) return;
      const data = await post('add', { parent_id: String(id), name });
      if (!data.ok) return alert(data.error || 'Lỗi thêm mục con');

      const childUl = qs(`.cat-children[data-parent="${id}"]`, li);
      childUl.appendChild(makeItem({id: data.id, name: data.name}));

      li.dataset.kids = String(Number(li.dataset.kids || '0') + 1);
      updateBadgesAndMusic(li);
      enhanceTree(childUl);
      return;
    }

    if (btn.classList.contains('cat-btn-del')) {
      if (!confirm('Xóa mục này? (xóa luôn mục con & bài hát)')) return;
      const data = await post('delete', { id: String(id) });
      if (!data.ok) return alert(data.error || 'Lỗi xóa');

      const parentLi = li.parentElement?.closest('.cat-item');
      if (parentLi) {
        parentLi.dataset.kids = String(Math.max(0, Number(parentLi.dataset.kids || '0') - 1));
        updateBadgesAndMusic(parentLi);
      }

      li.remove();
      return;
    }
  });

  root.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    const input = e.target.closest('.cat-name');
    if (!input || input.disabled) return;
    e.preventDefault();
    const li = input.closest('.cat-item');
    qs('.cat-btn-save', li)?.click();
  });

  root.addEventListener('blur', (e) => {
    const input = e.target.closest('.cat-name');
    if (!input || input.disabled) return;
    const li = input.closest('.cat-item');
    setTimeout(() => {
      if (document.activeElement !== input) qs('.cat-btn-save', li)?.click();
    }, 120);
  }, true);
})();
