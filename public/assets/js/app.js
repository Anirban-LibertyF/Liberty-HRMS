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
    $('#notifClear').addEventListener('click', async () => {
      const result = await api({ do: 'notif_clear' });
      if (!result.ok) { toast(result.msg || 'Could not clear notifications.', true); return; }
      $$('#notifPanel .dd-item').forEach((n) => n.remove());
      const b = $('.dot-badge', nb); if (b) b.remove();
      const empty = document.createElement('p'); empty.className = 'muted pad'; empty.textContent = "You're all caught up."; np.appendChild(empty);
    });
    $$('#notifPanel [data-invite] [data-choice]').forEach((btn) => btn.addEventListener('click', async (e) => {
      e.stopPropagation();
      const box = btn.parentElement, choice = btn.dataset.choice;
      $$('button', box).forEach((x) => { x.disabled = true; });
      const r = await api({ do: 'project_respond', id: box.dataset.invite, choice });
      if (!r.ok) { $$('button', box).forEach((x) => { x.disabled = false; }); return toast(r.msg, true); }
      box.innerHTML = choice === 'accept' ? '<span class="badge b-green">Accepted</span> <a href="project.php?id=' + box.dataset.invite + '">Open project</a>' : '<span class="badge b-red">Rejected</span>';
      box.closest('.dd-item').classList.remove('unread'); toast(r.msg);
    }));
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


  // project form: progress slider <-> number, secret show/hide, existing-file removal, multi-file drop zone
  $$('[data-progress]').forEach((box) => {
    const r = $('input[type=range]', box), n = $('input[type=number]', box);
    const clamp = (v) => Math.max(0, Math.min(100, parseInt(v, 10) || 0));
    r.addEventListener('input', () => { n.value = r.value; });
    n.addEventListener('input', () => { r.value = clamp(n.value); });
    n.addEventListener('change', () => { n.value = clamp(n.value); r.value = n.value; });
  });
  $$('[data-mask]').forEach((b) => b.addEventListener('click', () => $(b.dataset.mask).classList.toggle('masked')));
  $$('[data-remove-file]').forEach((b) => b.addEventListener('click', () => {
    const row = b.closest('.file-row'), inp = $('input[name="remove_files[]"]', row);
    inp.disabled = !inp.disabled; row.classList.toggle('removed', !inp.disabled);
  }));
  $$('.drop[data-multi]').forEach((z) => {
    const inp = $('#' + z.dataset.multi), list = $('#' + z.dataset.list);
    const eye = '<svg class="ic" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
    const bin = '<svg class="ic" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M6 6l1 15h10l1-15"/></svg>';
    let files = [];
    const paint = () => {
      const dt = new DataTransfer(); files.forEach((f) => dt.items.add(f)); inp.files = dt.files;
      list.innerHTML = '';
      files.forEach((f, i) => {
        const row = document.createElement('div'); row.className = 'file-row is-new';
        const fn = document.createElement('div'); fn.className = 'fn'; fn.textContent = f.name;
        const sz = document.createElement('small'); sz.textContent = (f.size / 1024 < 1024 ? Math.round(f.size / 1024) + ' KB' : (f.size / 1048576).toFixed(1) + ' MB') + ' · new'; fn.appendChild(sz);
        const view = document.createElement('a'); view.className = 'icon-btn'; view.innerHTML = eye; view.href = URL.createObjectURL(f); view.target = '_blank'; view.rel = 'noopener'; view.title = 'View'; view.setAttribute('aria-label', 'View');
        const rm = document.createElement('button'); rm.type = 'button'; rm.className = 'icon-btn'; rm.innerHTML = bin; rm.title = 'Remove'; rm.setAttribute('aria-label', 'Remove');
        rm.addEventListener('click', () => { files.splice(i, 1); paint(); });
        row.append(fn, view, rm); list.appendChild(row);
      });
    };
    z.addEventListener('click', () => inp.click());
    inp.addEventListener('change', () => { files = files.concat(Array.from(inp.files)); paint(); });
    ['dragenter', 'dragover'].forEach((ev) => z.addEventListener(ev, (e) => { e.preventDefault(); z.classList.add('over'); }));
    ['dragleave', 'drop'].forEach((ev) => z.addEventListener(ev, (e) => { e.preventDefault(); z.classList.remove('over'); }));
    z.addEventListener('drop', (e) => { files = files.concat(Array.from(e.dataTransfer.files)); paint(); });
  });


  // expense sheet: serial numbers, live amounts and total
  const expBody = $('#expRows');
  if (expBody) {
    const fmt = (n) => n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const calc = () => {
      let total = 0;
      $$('tr', expBody).forEach((tr, i) => {
        const amt = Math.round((parseFloat($('[name="qty[]"]', tr).value) || 0) * (parseFloat($('[name="price[]"]', tr).value) || 0) * 100) / 100;
        total += amt; $('.sl', tr).textContent = i + 1; $('.amt', tr).textContent = fmt(amt);
      });
      $('#expTotal').textContent = fmt(total);
    };
    expBody.addEventListener('input', calc);
    expBody.addEventListener('click', (e) => {
      const b = e.target.closest('[data-exp-del]'); if (!b) return;
      const tr = b.closest('tr');
      if ($$('tr', expBody).length > 1) tr.remove(); else $$('input', tr).forEach((i) => { i.value = ''; });
      calc();
    });
    $('#expAdd').addEventListener('click', () => {
      const tr = expBody.lastElementChild.cloneNode(true);
      $$('input', tr).forEach((i) => { i.value = ''; }); expBody.appendChild(tr); calc(); $('input', tr).focus();
    });
    calc();
  }


  // notebook rich-text editor
  const rte = $('#rte');
  if (rte) {
    const bar = $('#rteBar'), hidden = $('#rteInput'); let saved = null;
    const save = () => { const sel = getSelection(); if (sel.rangeCount && rte.contains(sel.anchorNode)) saved = sel.getRangeAt(0).cloneRange(); };
    document.addEventListener('selectionchange', save);
    const restore = () => { rte.focus(); if (saved) { const sel = getSelection(); sel.removeAllRanges(); sel.addRange(saved); } };
    const run = (cmd, val) => {
      restore();
      document.execCommand('styleWithCSS', false, cmd !== 'fontSize');
      document.execCommand(cmd, false, val);
      save();
    };
    // "deselect" a colour: paint a marker colour (the browser splits any coloured parent spans for us), then strip the marker
    const MARK = 'rgb(1, 2, 3)';
    const clearColour = (kind) => {
      const cmd = kind === 'bg' ? 'hiliteColor' : 'foreColor', prop = kind === 'bg' ? 'backgroundColor' : 'color', css = kind === 'bg' ? 'background-color' : 'color';
      run(cmd, '#010203');
      $$('[style]', rte).forEach((el) => {
        if (el.style[prop] !== MARK) return;
        el.style.removeProperty(css);
        if (!el.getAttribute('style')) {
          el.removeAttribute('style');
          if (el.tagName === 'SPAN' && !el.attributes.length) { while (el.firstChild) el.parentNode.insertBefore(el.firstChild, el); el.remove(); }
        }
      });
    };
    const setSize = (px) => {
      run('fontSize', '7');
      $$('font[size="7"]', rte).forEach((f) => { const sp = document.createElement('span'); sp.style.fontSize = px + 'px'; while (f.firstChild) sp.appendChild(f.firstChild); f.replaceWith(sp); });
    };
    bar.addEventListener('mousedown', (e) => { if (e.target.closest('button')) e.preventDefault(); });
    bar.addEventListener('click', (e) => {
      const b = e.target.closest('button'); if (!b) return;
      if (b.dataset.clear) clearColour(b.dataset.clear); else if (b.dataset.hl) run('hiliteColor', b.dataset.hl); else if (b.dataset.cmd) run(b.dataset.cmd);
    });
    bar.addEventListener('change', (e) => {
      const t = e.target, cmd = t.dataset.cmd; if (!cmd) return;
      if (t.tagName === 'SELECT') { if (t.value) { cmd === 'fontSize' ? setSize(t.value) : run(cmd, t.value); } t.value = ''; }
      else run(cmd, t.value);
    });
    rte.addEventListener('paste', (e) => { e.preventDefault(); document.execCommand('insertText', false, (e.clipboardData || window.clipboardData).getData('text/plain')); });
    rte.closest('form').addEventListener('submit', () => { hidden.value = rte.textContent.trim() === '' ? '' : rte.innerHTML; });
  }


  // copy-to-clipboard buttons
  $$('[data-copy]').forEach((b) => b.addEventListener('click', async () => {
    const inp = $(b.dataset.copy); const text = inp.value.trim();
    if (!text) return toast('Nothing to copy yet.', true);
    try { await navigator.clipboard.writeText(text); }
    catch (e) { inp.select(); document.execCommand('copy'); inp.setSelectionRange(0, 0); }
    toast('Link copied.');
  }));


  // note audience: show employee picker only for "specific employees"
  $$('input[name=audience]').forEach((r) => r.addEventListener('change', () => { const l = $('#audList'); if (l) l.hidden = $('input[name=audience]:checked').value === 'all'; }));


  // employee pickers: live search filter
  $$('input[data-filter-list]').forEach((inp) => inp.addEventListener('input', () => {
    const list = $(inp.dataset.filterList), term = inp.value.trim().toLowerCase(); let shown = 0;
    $$('.member-opt', list).forEach((o) => { const hit = !term || o.textContent.toLowerCase().includes(term); o.hidden = !hit; if (hit) shown++; });
    const none = $('#memEmpty'); if (none && list.id === 'memberPick') none.hidden = shown > 0;
  }));
  // project members: single (one employee) vs group (many), count, select shown / clear
  const mp = $('#memberPick');
  if (mp) {
    const boxes = () => $$('input[type=checkbox]', mp), mode = () => ($('input[name=project_type]:checked') || {}).value;
    const sync = () => { $('#memCount').textContent = boxes().filter((b) => b.checked).length + ' selected'; };
    const applyMode = () => {
      const single = mode() === 'single';
      $('#memLabel').textContent = single ? 'Assign to employee' : 'Group members';
      $('#memAll').hidden = single;
      if (single) { let seen = false; boxes().forEach((b) => { if (b.checked && seen) b.checked = false; else if (b.checked) seen = true; }); }
      sync();
    };
    mp.addEventListener('change', (e) => {
      if (mode() === 'single' && e.target.checked) boxes().forEach((b) => { if (b !== e.target) b.checked = false; });
      sync();
    });
    $$('input[name=project_type]').forEach((r) => r.addEventListener('change', applyMode));
    $('#memAll').addEventListener('click', () => { $$('.member-opt:not([hidden]) input', mp).forEach((b) => { b.checked = true; }); sync(); });
    $('#memNone').addEventListener('click', () => { boxes().forEach((b) => { b.checked = false; }); sync(); });
    applyMode();
  }

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
