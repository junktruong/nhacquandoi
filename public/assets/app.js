(() => {
  // ===== Offline Audio Manager =====
  const AUDIO_CACHE_NAME = 'audio-v2';
  const MANIFEST_KEY = 'offline_manifest_v2';
  const LAST_CHECK_KEY = 'offline_last_check_v2';

  const offlineBtn = document.getElementById('offlineBtn');
  const offlineBadge = document.getElementById('offlineBadge');

  const isStandalone =
    window.matchMedia('(display-mode: standalone)').matches ||
    window.navigator.standalone === true;

  function setBadge(on) {
    if (!offlineBadge) return;
    offlineBadge.style.display = on ? '' : 'none';
  }

  function showOfflineBtn() {
    if (!offlineBtn) return;
    // chỉ hiện trong app đã cài (đúng ý anh)
    if (isStandalone) offlineBtn.style.display = '';
  }

  async function fetchManifest() {
    const r = await fetch('/api/offline_manifest.php', { cache: 'no-store' });
    if (!r.ok) throw new Error('manifest http ' + r.status);
    return await r.json();
  }

  function getLocalManifest() {
    try { return JSON.parse(localStorage.getItem(MANIFEST_KEY) || 'null'); }
    catch { return null; }
  }
  function setLocalManifest(m) {
    localStorage.setItem(MANIFEST_KEY, JSON.stringify(m));
  }

  function createModal() {
    const wrap = document.createElement('div');
    wrap.className = 'offline-modal';
    wrap.innerHTML = `
      <div class="offline-card">
        <div class="offline-row">
          <div class="offline-title">Nhạc offline</div>
          <button id="offlineCloseBtn" title="Đóng">✕</button>
        </div>
        <div class="offline-muted" id="offlineMsg">Sẵn sàng.</div>
        <div class="offline-progress"><div id="offlineBar"></div></div>
        <div class="offline-row">
          <div class="offline-muted" id="offlineStat">0/0</div>
          <div class="offline-muted" id="offlineNet">${navigator.onLine ? 'Online' : 'Offline'}</div>
        </div>
        <div class="offline-actions">
          <button id="offlineCheckBtn">Kiểm tra cập nhật</button>
          <button id="offlineDownloadBtn" class="primary">Tải / Cập nhật</button>
        </div>
      </div>
    `;
    document.body.appendChild(wrap);

    const close = () => wrap.remove();
    wrap.querySelector('#offlineCloseBtn').addEventListener('click', close);
    wrap.addEventListener('click', (e) => { if (e.target === wrap) close(); });

    return {
      wrap,
      msg: wrap.querySelector('#offlineMsg'),
      bar: wrap.querySelector('#offlineBar'),
      stat: wrap.querySelector('#offlineStat'),
      net: wrap.querySelector('#offlineNet'),
      btnCheck: wrap.querySelector('#offlineCheckBtn'),
      btnDownload: wrap.querySelector('#offlineDownloadBtn'),
      setProgress(done, total) {
        this.stat.textContent = `${done}/${total}`;
        this.bar.style.width = total ? `${Math.floor(done * 100 / total)}%` : '0%';
      },
      setMsg(t) { this.msg.textContent = t; },
      setNet() { this.net.textContent = navigator.onLine ? 'Online' : 'Offline'; }
    };
  }

  async function syncAudio({ dryRun = false } = {}) {
    if (!navigator.onLine) throw new Error('offline');

    const server = await fetchManifest();
    if (!server?.ok) throw new Error('bad manifest');

    const cache = await caches.open(AUDIO_CACHE_NAME);

    const local = getLocalManifest();
    const localMap = new Map((local?.songs || []).map(s => [s.path, s]));
    const serverSet = new Set((server.songs || []).map(s => s.path));

    let need = [];
    for (const s of (server.songs || [])) {
      const old = localMap.get(s.path);
      // nếu chưa có hoặc khác mtime/size => cần tải
      if (!old || old.mtime !== s.mtime || old.size !== s.size) need.push(s);
    }

    if (dryRun) {
      return { server, needCount: need.length, total: (server.songs || []).length };
    }

    // tải những bài cần update
    let done = 0;
    for (const s of need) {
      const r = await fetch(s.path);
      if (r.ok) await cache.put(s.path, r.clone());
      done++;
    }

    // xóa cache những bài server đã xóa (cho sạch)
    const keys = await cache.keys();
    for (const req of keys) {
      const u = new URL(req.url);
      if (u.pathname.startsWith('/uploads/') && !serverSet.has(u.pathname)) {
        await cache.delete(req);
      }
    }

    setLocalManifest({ hash: server.hash, songs: server.songs, savedAt: Date.now() });
    return { server, updated: need.length, total: (server.songs || []).length };
  }

  async function checkUpdates({ force = false } = {}) {
    if (!navigator.onLine) return;

    const last = Number(localStorage.getItem(LAST_CHECK_KEY) || '0');
    const now = Date.now();
    // tránh check spam: 10 phút/lần
    if (!force && (now - last) < 10 * 60 * 1000) return;

    localStorage.setItem(LAST_CHECK_KEY, String(now));

    try {
      const result = await syncAudio({ dryRun: true });
      // nếu chưa tải offline lần nào => badge off
      const local = getLocalManifest();
      if (!local?.hash) { setBadge(false); return; }

      // khác hash hoặc có bài cần update => bật badge
      setBadge(local.hash !== result.server.hash || result.needCount > 0);
    } catch {
      // im lặng
    }
  }

  showOfflineBtn();

  offlineBtn?.addEventListener('click', async () => {
    const ui = createModal();
    ui.setNet();

    const refresh = async () => {
      ui.setNet();
      if (!navigator.onLine) {
        ui.setMsg('Đang offline. Kết nối mạng để tải/cập nhật.');
        ui.setProgress(0, 0);
        return;
      }
      ui.setMsg('Đang kiểm tra dữ liệu trên server…');
      try {
        const r = await syncAudio({ dryRun: true });
        ui.setMsg(`Server: ${r.total} bài. Cần tải/cập nhật: ${r.needCount} bài.`);
        ui.setProgress(0, r.needCount);
      } catch (e) {
        ui.setMsg('Không lấy được danh sách từ server.');
      }
    };

    ui.btnCheck.addEventListener('click', refresh);

    ui.btnDownload.addEventListener('click', async () => {
      ui.setNet();
      if (!navigator.onLine) { ui.setMsg('Đang offline.'); return; }

      ui.setMsg('Đang tải/cập nhật nhạc offline…');
      try {
        // chạy thật
        const server = await fetchManifest();
        if (!server?.ok) throw new Error('bad manifest');

        const cache = await caches.open(AUDIO_CACHE_NAME);
        const local = getLocalManifest();
        const localMap = new Map((local?.songs || []).map(s => [s.path, s]));

        const need = [];
        for (const s of (server.songs || [])) {
          const old = localMap.get(s.path);
          if (!old || old.mtime !== s.mtime || old.size !== s.size) need.push(s);
        }

        ui.setProgress(0, need.length);

        let done = 0, fail = 0;
        for (const s of need) {
          try {
            const r = await fetch(s.path);
            if (r.ok) await cache.put(s.path, r.clone());
            else fail++;
          } catch { fail++; }
          done++;
          ui.setProgress(done, need.length);
        }

        setLocalManifest({ hash: server.hash, songs: server.songs, savedAt: Date.now() });
        setBadge(false);

        ui.setMsg(`Xong. Đã cập nhật ${need.length - fail}/${need.length} bài (lỗi ${fail}).`);
      } catch (e) {
        ui.setMsg('Lỗi khi tải/cập nhật.');
      }
    });

    window.addEventListener('online', () => { ui.setNet(); refresh(); }, { once: false });
    window.addEventListener('offline', () => ui.setNet(), { once: false });

    refresh();
  });

  // tự check khi app mở + khi có mạng trở lại
  window.addEventListener('load', () => checkUpdates({ force: false }));
  window.addEventListener('online', () => checkUpdates({ force: true }));

  // ===== PWA install (ổn định) =====
  const installBtn = document.getElementById('installBtn');
  let deferredPrompt = null;

  function setInstallBtn(mode) {
    if (!installBtn) return;
    if (mode === 'hide') { installBtn.style.display = 'none'; return; }
    installBtn.style.display = '';
    installBtn.disabled = (mode !== 'ready');
    installBtn.textContent = (mode === 'ready') ? 'Cài app' : 'Tải app';
  }

  // Mặc định disable để tránh bấm “hên xui”
  setInstallBtn('disabled');

  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js').catch(console.error);
    });
  }

  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();          // dùng nút custom, không cho Edge tự hiện banner
    deferredPrompt = e;
    setInstallBtn('ready');
  });

  installBtn?.addEventListener('click', async () => {
    if (!deferredPrompt) {
      alert('Edge chưa hiện prompt cài lúc này. Anh có thể cài bằng: ⋯ → Apps → Install this site as an app.');
      return;
    }
    deferredPrompt.prompt();
    await deferredPrompt.userChoice;
    deferredPrompt = null;
    setInstallBtn('disabled');
  });

  window.addEventListener('appinstalled', () => {
    localStorage.setItem('pwa_installed', '1');
    setInstallBtn('hide');
  });

  // ===== Offline audio precache (sau khi cài) =====
  const OFFLINE_AUDIO_KEY = 'offline_audio_cached_v2';

  function toast(msg) {
    let el = document.getElementById('offlineToast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'offlineToast';
      el.style.cssText = `
        position:fixed; left:12px; bottom:12px; z-index:9999;
        background:rgba(0,0,0,.75); color:#fff; padding:10px 12px;
        border-radius:12px; font-weight:700; font-size:13px;
        border:1px solid rgba(255,255,255,.15);
      `;
      document.body.appendChild(el);
    }
    el.textContent = msg;
    el.style.display = 'block';
    clearTimeout(el.__t);
    el.__t = setTimeout(() => (el.style.display = 'none'), 2200);
  }

  async function precacheAllAudio() {
    if (!('caches' in window)) return;
    if (localStorage.getItem(OFFLINE_AUDIO_KEY) === '1') return;

    toast('Đang chuẩn bị tải nhạc offline…');

    const res = await fetch('/api/offline_manifest.php', { cache: 'no-store' });
    const data = await res.json();
    if (!data || !data.ok) return;

    const list = data.songs || [];
    const total = list.length;
    if (!total) {
      localStorage.setItem(OFFLINE_AUDIO_KEY, '1');
      return;
    }

    // MUST khớp với sw.js
    const cache = await caches.open('audio-v2');
    let done = 0, cached = 0, failed = 0;

    for (const s of list) {
      const url = s.path;
      try {
        const hit = await cache.match(url);
        if (!hit) {
          const r = await fetch(url);
          if (r.ok) {
            await cache.put(url, r.clone());
            cached++;
          } else failed++;
        }
      } catch { failed++; }

      done++;
      if (done % 5 === 0 || done === total) toast(`Tải offline: ${done}/${total}`);
    }

    localStorage.setItem(OFFLINE_AUDIO_KEY, '1');
    toast(`Offline xong: +${cached} bài (lỗi ${failed})`);
  }

  const isStandalone =
    window.matchMedia('(display-mode: standalone)').matches ||
    window.navigator.standalone === true;

  // chạy nếu đã cài hoặc đang mở dạng app
  if (isStandalone || localStorage.getItem('pwa_installed') === '1') {
    window.addEventListener('load', () => setTimeout(precacheAllAudio, 1200));
  }

  // ===== Player (phần anh đang có) =====
  const audio = document.getElementById('audio');
  const list = document.getElementById('songList');
  if (!audio || !list) return;

  const btnPlay = document.getElementById('btnPlay');
  const logo = document.getElementById('logoSpin');
  const btnPrev = document.getElementById('btnPrev');
  const btnNext = document.getElementById('btnNext');
  const nowTitle = document.getElementById('nowTitle');
  const seek = document.getElementById('seek');
  const vol = document.getElementById('vol');
  const tCur = document.getElementById('tCur');
  const tDur = document.getElementById('tDur');
  const search = document.getElementById('songSearch');

  const items = Array.from(list.querySelectorAll('.song-item'));
  let currentIndex = -1;
  let seeking = false;

  function setSpin(on) {
    if (!logo) return;
    logo.classList.toggle('is-spinning', !!on);
  }
  audio.addEventListener('play', () => setSpin(true));
  audio.addEventListener('pause', () => setSpin(false));
  audio.addEventListener('ended', () => setSpin(false));

  function fmt(sec) {
    if (!isFinite(sec) || sec < 0) return "00:00";
    sec = Math.floor(sec);
    const m = Math.floor(sec / 60);
    const s = sec % 60;
    return String(m).padStart(2, '0') + ":" + String(s).padStart(2, '0');
  }

  function setActive(idx) {
    items.forEach(el => el.classList.remove('is-playing'));
    if (idx >= 0 && items[idx]) items[idx].classList.add('is-playing');
  }

  function loadAndPlay(idx) {
    const el = items[idx];
    if (!el) return;
    currentIndex = idx;
    nowTitle.textContent = el.dataset.title || 'Không tên';
    audio.src = el.dataset.src;

    audio.play().then(() => setSpin(true)).catch(() => setSpin(false));
    setActive(idx);
    btnPlay.textContent = "⏸";
  }

  function togglePlay() {
    if (currentIndex === -1) {
      const visible = items.findIndex(el => el.style.display !== 'none');
      if (visible !== -1) loadAndPlay(visible);
      return;
    }
    if (audio.paused) {
      audio.play().then(() => setSpin(true)).catch(() => setSpin(false));
      btnPlay.textContent = "⏸";
    } else {
      audio.pause();
      setSpin(false);
      btnPlay.textContent = "▶";
    }
  }

  function next() {
    if (items.length === 0) return;
    for (let step = 1; step <= items.length; step++) {
      const idx = (currentIndex + step) % items.length;
      if (items[idx].style.display !== 'none') { loadAndPlay(idx); return; }
    }
  }

  function prev() {
    if (items.length === 0) return;
    for (let step = 1; step <= items.length; step++) {
      let idx = currentIndex - step;
      if (idx < 0) idx = items.length + idx;
      if (items[idx].style.display !== 'none') { loadAndPlay(idx); return; }
    }
  }

  items.forEach((el) => el.addEventListener('click', () => loadAndPlay(Number(el.dataset.index))));
  btnPlay?.addEventListener('click', togglePlay);
  btnNext?.addEventListener('click', next);
  btnPrev?.addEventListener('click', prev);

  audio.addEventListener('ended', next);
  audio.addEventListener('loadedmetadata', () => { tDur.textContent = fmt(audio.duration); });
  audio.addEventListener('timeupdate', () => {
    if (seeking) return;
    const ratio = (audio.currentTime / (audio.duration || 1));
    seek.value = String(Math.floor(ratio * 1000));
    tCur.textContent = fmt(audio.currentTime);
  });

  seek.addEventListener('input', () => seeking = true);
  seek.addEventListener('change', () => {
    const ratio = Number(seek.value) / 1000;
    audio.currentTime = ratio * (audio.duration || 0);
    seeking = false;
  });

  vol.addEventListener('input', () => audio.volume = Number(vol.value) / 100);
  audio.volume = Number(vol.value) / 100;

  search?.addEventListener('input', () => {
    const q = (search.value || '').trim().toLowerCase();
    items.forEach(el => {
      const t = (el.dataset.title || '').toLowerCase();
      el.style.display = (q === '' || t.includes(q)) ? '' : 'none';
    });
  });

  window.addEventListener('keydown', (e) => {
    if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA')) return;
    if (e.code === 'Space') { e.preventDefault(); togglePlay(); }
    if (e.code === 'ArrowRight') { audio.currentTime += 5; }
    if (e.code === 'ArrowLeft') { audio.currentTime -= 5; }
  });
})();
