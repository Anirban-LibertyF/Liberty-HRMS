(function () {
  'use strict';
  const csrf = () => (document.querySelector('meta[name=csrf]') || {}).content || '';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

  window.toast = function (msg, err) {
    const box = $('#toasts'); if (!box) return;
    const t = document.createElement('div');
    t.className = 'toast' + (err ? ' err' : '');
    t.textContent = msg; box.appendChild(t);
    setTimeout(() => t.remove(), 4200);
  };

  window.api = async function (data) {
    const fd = data instanceof FormData ? data : new URLSearchParams(data);
    const r = await fetch('actions.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch', 'X-CSRF-Token': csrf() } });
    try { return await r.json(); } catch (e) { return { ok: false, msg: 'Unexpected server response.' }; }
  };

  // dark / light mode (saved per browser; the inline script in <head> applies it before first paint)
  const modeBtn = $('#modeBtn');
  if (modeBtn) modeBtn.addEventListener('click', () => {
    const dark = document.documentElement.getAttribute('data-mode') !== 'dark';
    document.documentElement.setAttribute('data-mode', dark ? 'dark' : 'light');
    try { localStorage.setItem('lo-mode', dark ? 'dark' : 'light'); } catch (e) {}
  });
  // dismissable alerts / banners
  document.addEventListener('click', (e) => {
    const x = e.target.closest('.alert-x, [data-dismiss]');
    if (x) (x.closest('.alert, .banner') || x.parentElement).remove();
  });

  // mobile sidebar
  const menu = $('#menuBtn'), side = $('#sidebar');
  const closeNav = () => { side.classList.remove('open'); document.body.classList.remove('nav-open'); };
  if (menu) menu.addEventListener('click', () => { const o = side.classList.toggle('open'); document.body.classList.toggle('nav-open', o); });
  const ov = $('#navOverlay'); if (ov) ov.addEventListener('click', closeNav);
  $$('.sidebar a').forEach((a) => a.addEventListener('click', closeNav));

  // admin view switch
  const sw = $('#adminSwitch');
  if (sw) sw.addEventListener('change', () => { location.href = sw.checked ? 'admin.php' : 'index.php'; });

  // notifications dropdown
  const nb = $('#notifBtn'), np = $('#notifPanel');
  if (nb) {
    nb.addEventListener('click', (e) => { e.stopPropagation(); np.hidden = !np.hidden; });
    document.addEventListener('click', (e) => { if (!e.target.closest('#notifWrap')) np.hidden = true; });
    $('#notifReadAll').addEventListener('click', async () => {
      const result = await api({ do: 'notif_read' });
      if (!result.ok) { toast(result.msg || 'Could not mark notifications as read.', true); return; }
      $$('.dd-item.unread').forEach((n) => n.classList.remove('unread'));
      const b = $('.dot-badge', nb); if (b) b.remove();
      const announcementDot = $('.announcement-unread-dot');
      if (announcementDot) announcementDot.remove();
    });
  }

  // Mark announcements as seen only after the dashboard section enters the viewport.
  const announcements = $('#teamAnnouncements');
  if (announcements && announcements.dataset.announcementsUnread === '1') {
    let markingRead = false;
    const markAnnouncementsRead = async () => {
      if (markingRead) return;
      markingRead = true;
      try {
        const result = await api({ do: 'announcement_read' });
        if (!result.ok) {
          markingRead = false;
          toast(result.msg || 'Could not mark announcements as read.', true);
          return;
        }
        $$('.dd-item.announcement-new.unread').forEach((item) => item.classList.remove('unread'));
        const unreadBadge = $('.dot-badge', nb);
        if (result.unread > 0) {
          if (unreadBadge) unreadBadge.textContent = result.unread;
        } else if (unreadBadge) {
          unreadBadge.remove();
        }
        announcements.dataset.announcementsUnread = '0';
        const dot = $('.announcement-unread-dot', announcements);
        if (dot) dot.remove();
        if (observer) observer.disconnect();
      } catch (error) {
        markingRead = false;
        toast('Could not mark announcements as read. Please try again.', true);
      }
    };
    const observer = 'IntersectionObserver' in window
      ? new IntersectionObserver((entries) => {
          if (entries.some((entry) => entry.isIntersecting)) markAnnouncementsRead();
        }, { threshold: 0.25 })
      : null;
    if (observer) observer.observe(announcements);
    else announcements.addEventListener('click', markAnnouncementsRead, { once: true });
  }

  // collapsible header search
  const ts = $('#topSearch'), tsBtn = $('#searchToggle'), tsIn = $('#searchInput');
  if (ts) {
    const setOpen = (o) => { ts.classList.toggle('open', o); tsBtn.setAttribute('aria-expanded', o); if (o) tsIn.focus(); };
    tsBtn.addEventListener('click', () => { if (!ts.classList.contains('open')) setOpen(true); else if (tsIn.value.trim().length >= 2) ts.submit(); else setOpen(false); });
    tsIn.addEventListener('keydown', (e) => { if (e.key === 'Escape') { tsIn.value = ''; setOpen(false); } });
    ts.addEventListener('submit', (e) => { if (tsIn.value.trim().length < 2) { e.preventDefault(); toast('Type at least 2 characters to search.', true); } });
    document.addEventListener('click', (e) => { if (!e.target.closest('#topSearch') && !tsIn.value) setOpen(false); });
  }

  // account menu (log out)
  const ab = $('#accountBtn'), ap = $('#accountPanel');
  if (ab) {
    ab.addEventListener('click', (e) => { e.stopPropagation(); ap.hidden = !ap.hidden; ab.setAttribute('aria-expanded', !ap.hidden); });
    document.addEventListener('click', (e) => { if (!e.target.closest('#accountWrap')) ap.hidden = true; });
  }

  // password visibility
  $$('[data-eye]').forEach((b) => b.addEventListener('click', () => {
    const i = $(b.dataset.eye); i.type = i.type === 'password' ? 'text' : 'password';
  }));

  // login: administrator toggle switches theme + field hints
  const adm = $('#asAdmin');
  if (adm) {
    const id = $('#identifier'), lab = $('#identLabel');
    const apply = () => {
      document.body.classList.toggle('theme-admin', adm.checked);
      document.body.classList.toggle('theme-emp', !adm.checked);
      lab.textContent = adm.checked ? 'Administrator Email' : 'Employee ID / Corporate Email';
      id.placeholder = adm.checked ? 'liberty.admin@liberty.com' : 'LOE-001 or user@enterprise.corp';
    };
    adm.addEventListener('change', apply); apply();
  }

  // SSO / biometric placeholders
  $$('[data-unavailable]').forEach((b) => b.addEventListener('click', () => toast(b.dataset.unavailable, true)));

  // live shift timer
  const timer = $('#shiftTimer');
  if (timer) {
    let base = parseInt(timer.dataset.base, 10);
    const running = timer.dataset.running === '1';
    const loaded = Date.now();
    const paint = () => {
      const s = base + (running ? Math.floor((Date.now() - loaded) / 1000) : 0);
      const h = String(Math.floor(s / 3600)).padStart(2, '0'), m = String(Math.floor(s / 60) % 60).padStart(2, '0'), sec = String(s % 60).padStart(2, '0');
      timer.firstChild.nodeValue = `${h}h ${m}m ${sec}s `;
    };
    paint(); if (running) setInterval(paint, 1000);
  }

  // dialogs
  $$('[data-open]').forEach((b) => b.addEventListener('click', () => { const d = $(b.dataset.open); if (d) d.showModal(); }));
  $$('[data-close]').forEach((b) => b.addEventListener('click', () => b.closest('dialog').close()));

  // profile photo upload
  const photoForm = $('.profile-photo-form');
  const photoInput = $('#profilePhoto');
  const photoEdit = $('.profile-photo-edit');
  if (photoForm && photoInput && photoEdit) {
    photoEdit.addEventListener('click', () => photoInput.click());
    photoInput.addEventListener('change', () => {
      if (photoInput.files && photoInput.files.length) photoForm.requestSubmit();
    });
  }

  // file drop zones: <div class="drop" data-for="inputId">
  $$('.drop').forEach((z) => {
    const inp = $('#' + z.dataset.for); if (!inp) return;
    const name = $('.fname', z);
    z.addEventListener('click', () => inp.click());
    inp.addEventListener('change', () => { name.textContent = inp.files[0] ? inp.files[0].name : ''; });
    ['dragenter', 'dragover'].forEach((ev) => z.addEventListener(ev, (e) => { e.preventDefault(); z.classList.add('over'); }));
    ['dragleave', 'drop'].forEach((ev) => z.addEventListener(ev, (e) => { e.preventDefault(); z.classList.remove('over'); }));
    z.addEventListener('drop', (e) => { if (e.dataTransfer.files.length) { inp.files = e.dataTransfer.files; inp.dispatchEvent(new Event('change')); } });
  });

  // leave form: live duration + reason counter
  const lf = $('#leaveForm');
  if (lf) {
    const from = $('[name=from_date]', lf), to = $('[name=end_date]', lf), half = $('[name=half_day]', lf), out = $('#duration');
    const holidays = JSON.parse(lf.dataset.holidays || '[]');
    const calc = () => {
      if (!from.value || !to.value || to.value < from.value) { out.textContent = 'Duration: --'; return; }
      let n = 0;
      for (let d = new Date(from.value + 'T00:00:00'); d <= new Date(to.value + 'T00:00:00'); d.setDate(d.getDate() + 1)) {
        const iso = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        if (!holidays.includes(iso)) n++;
      }
      if (half.checked) n = n ? 0.5 : 0;
      out.textContent = 'Duration: ' + n + ' Working Day' + (n === 1 ? '' : 's');
    };
    from.addEventListener('change', () => { if (!to.value || to.value < from.value) to.value = from.value; calc(); });
    [to, half].forEach((i) => i.addEventListener('change', () => { if (half.checked) to.value = from.value; calc(); }));
    calc();
  }

  // history filter tabs
  $$('[data-filter]').forEach((b) => b.addEventListener('click', () => {
    $$('[data-filter]').forEach((x) => x.classList.toggle('on', x === b));
    $$('[data-status]').forEach((r) => { r.hidden = b.dataset.filter !== 'all' && r.dataset.status !== b.dataset.filter; });
  }));

  // work log autosave + attachment
  const wl = $('#workLog');
  if (wl) {
    const ta = $('#wlContent'), tag = $('#wlTag'), stat = $('#wlSaved'), date = wl.dataset.date;
    let timerId = null, dirty = false;
    const save = async () => {
      if (!dirty) return; dirty = false; stat.textContent = 'Saving...';
      const r = await api({ do: 'worklog_save', log_date: date, content: ta.value, tag: tag.value });
      stat.textContent = r.ok ? 'Auto-saved just now' : (r.msg || 'Save failed');
    };
    const touch = () => { dirty = true; stat.textContent = 'Unsaved changes...'; clearTimeout(timerId); timerId = setTimeout(save, 1500); };
    ta.addEventListener('input', touch); tag.addEventListener('input', touch);
    const file = $('#wlFile'), chips = $('#wlChips');
    $('#wlAttach').addEventListener('click', () => file.click());
    file.addEventListener('change', async () => {
      if (!file.files[0]) return;
      const fd = new FormData(); fd.append('do', 'worklog_attach'); fd.append('log_date', date); fd.append('content', ta.value); fd.append('tag', tag.value); fd.append('file', file.files[0]);
      const r = await api(fd); file.value = '';
      if (!r.ok) return toast(r.msg, true);
      chips.innerHTML = ''; addChip(r.name); toast('Attachment added.');
    });
    function addChip(name) {
      const c = document.createElement('span'); c.className = 'chip';
      c.innerHTML = '<span></span><button type="button" aria-label="Remove attachment">&times;</button>';
      c.firstChild.textContent = name;
      c.querySelector('button').addEventListener('click', async () => { const r = await api({ do: 'worklog_detach', log_date: date }); if (r.ok) c.remove(); });
      chips.appendChild(c);
    }
    $$('#wlChips .chip button').forEach((b) => b.addEventListener('click', async () => { const r = await api({ do: 'worklog_detach', log_date: date }); if (r.ok) b.parentElement.remove(); }));
    wl.addEventListener('submit', () => { clearTimeout(timerId); });
  }

  // 3D tilt for stat-style cards: values ease toward the pointer every frame (no jumps), desktop pointers only
  const canTilt = matchMedia('(hover: hover) and (pointer: fine)').matches && !matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (canTilt) {
    $$('.stat, .lt-card, .sum-card, .login-card').forEach((el) => {
      el.classList.add('tilt');
      const max = el.classList.contains('login-card') ? 4 : 6;
      const cur = { rx: 0, ry: 0, mx: 50, my: 50 }, tgt = { rx: 0, ry: 0, mx: 50, my: 50 };
      let raf = 0;
      const step = () => {
        let moving = false;
        for (const k in cur) { const d = tgt[k] - cur[k]; if (Math.abs(d) > 0.02) { cur[k] += d * 0.1; moving = true; } else cur[k] = tgt[k]; }
        el.style.setProperty('--rx', cur.rx.toFixed(3) + 'deg'); el.style.setProperty('--ry', cur.ry.toFixed(3) + 'deg');
        el.style.setProperty('--mx', cur.mx.toFixed(1) + '%'); el.style.setProperty('--my', cur.my.toFixed(1) + '%');
        raf = moving ? requestAnimationFrame(step) : 0;
      };
      const kick = () => { if (!raf) raf = requestAnimationFrame(step); };
      el.addEventListener('pointerenter', () => el.classList.add('is-tilting'));
      el.addEventListener('pointermove', (e) => {
        if (innerWidth <= 860) return;
        const r = el.getBoundingClientRect(), px = (e.clientX - r.left) / r.width, py = (e.clientY - r.top) / r.height;
        tgt.ry = (px - 0.5) * 2 * max; tgt.rx = (0.5 - py) * 2 * max; tgt.mx = px * 100; tgt.my = py * 100; kick();
      });
      el.addEventListener('pointerleave', () => { el.classList.remove('is-tilting'); tgt.rx = 0; tgt.ry = 0; kick(); });
    });
  }
  // confirm prompts
  document.addEventListener('submit', (e) => {
    const c = e.target.dataset && e.target.dataset.confirm;
    if (c && !confirm(c)) e.preventDefault();
  });
})();
