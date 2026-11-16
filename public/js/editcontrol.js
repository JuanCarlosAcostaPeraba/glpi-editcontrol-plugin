/* SPDX-License-Identifier: GPL-3.0-or-later */
(() => {
  'use strict';
  function mount(node) {
    if (node.dataset.mounted) return;
    node.dataset.mounted = '1';
    const c = JSON.parse(node.dataset.config);
    // Only one controller per ticket page, even when forms are rendered again.
    if (document.querySelector('[data-editcontrol-active]')) return;
    node.dataset.editcontrolActive = '1';
    let token = '', mine = false, changed = false, lastAvailable = true, busy = false;
    let lastActivity = Date.now();
    const bar = document.createElement('section');
    bar.className = 'alert alert-info m-3';
    bar.setAttribute('aria-label', 'Control de edición');
    const msg = document.createElement('p');
    msg.setAttribute('role', 'status'); msg.setAttribute('aria-live', 'polite');
    bar.append(msg);
    const buttons = {};
    const ticketForm = form => /(?:ticket|itilfollowup|tickettask|itilsolution)\.form\.php(?:$|[?\/])/.test(new URL(form.action, location.href).pathname)
      || !!form.querySelector('[name="tickets_id"], [name="itemtype"][value="Ticket"]');
    function button(label, action) {
      const b = document.createElement('button'); b.type = 'button';
      b.className = 'btn btn-sm btn-outline-primary me-2'; b.textContent = label;
      b.onclick = async () => {
        if (busy) return;
        busy = true;
        try { await call(action); } catch (e) { fail(e); } finally { busy = false; }
      };
      buttons[action] = b; bar.append(b);
    }
    button('Editar', 'acquire'); button('Terminar edición', 'release'); button('Solicitar control', 'request');
    if (c.admin) button('Tomar control', 'takeover');
    const reload = document.createElement('button'); reload.type = 'button';
    reload.className = 'btn btn-sm btn-outline-secondary'; reload.textContent = 'Recargar ticket';
    reload.onclick = () => location.reload(); bar.append(reload);
    (document.querySelector('main') || document.body).prepend(bar);
    // A badge and a server guard remain effective if a custom form ignores disabled controls.
    function fields() {
      document.querySelectorAll('form[method="post" i]').forEach(form => {
        if (bar.contains(form) || !ticketForm(form)) return;
        for (const [name, value] of Object.entries({_editcontrol_token: token, _editcontrol_baseline: c.baseline})) {
          let hidden = form.querySelector(`input[name="${name}"]`);
          if (!hidden) { hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = name; form.append(hidden); }
          if (hidden.value !== value) hidden.value = value;
        }
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(b => {
          const block = c.mode === 'strict' && (!mine || changed);
          if (block && !b.disabled) { b.dataset.editcontrolDisabled = '1'; b.disabled = true; }
          if (!block && b.dataset.editcontrolDisabled) { b.disabled = false; delete b.dataset.editcontrolDisabled; }
        });
      });
    }
    // Cover jQuery AJAX timeline actions as well as ordinary form submissions.
    if (window.jQuery) window.jQuery.ajaxPrefilter((options, original, xhr) => {
      const url = new URL(options.url, location.href);
      if (url.origin === location.origin) {
        xhr.setRequestHeader('X-Editcontrol-Token', token);
        xhr.setRequestHeader('X-Editcontrol-Baseline', c.baseline);
      }
    });
    document.addEventListener('submit', e => {
      if (c.mode === 'strict' && (!mine || changed) && e.target.method.toLowerCase() === 'post' && ticketForm(e.target)) {
        e.preventDefault(); e.stopImmediatePropagation();
        msg.textContent = 'Guardado detenido. Adquiera el control o conserve el texto y recargue.';
      }
    }, true);
    const observer = new MutationObserver(fields);
    observer.observe(document.body, {childList:true, subtree:true});
    for (const evt of ['input','pointerdown','keydown']) document.addEventListener(evt, () => { lastActivity = Date.now(); }, {passive:true});
    function fail(e) {
      mine = false; changed = true;
      msg.textContent = `${e.message || 'Sin conexión.'} Conserve el texto antes de recargar.`;
      bar.className = 'alert alert-danger m-3'; fields();
    }
    async function call(action) {
      const body = new URLSearchParams({action, ticket:c.id, nonce:c.nonce, token, baseline:c.baseline, _glpi_csrf_token:c.csrf});
      const res = await fetch(c.url, {method:'POST', credentials:'same-origin', body, headers:{'X-Requested-With':'XMLHttpRequest'}, signal:AbortSignal.timeout(12000)});
      const r = await res.json();
      if (r.csrf) c.csrf = r.csrf;
      if (!res.ok || !r.ok) throw new Error(r.message || 'Sesión caducada o servicio no disponible.');
      if (r.token) { token = r.token; lastActivity = Date.now(); }
      const lost = mine && !r.mine;
      mine = r.mine; changed = changed || r.changed || lost;
      if (action === 'release') token = '';
      const availableNotice = r.available && !lastAvailable ? 'El ticket ha quedado disponible. ' : '';
      lastAvailable = r.available;
      const time = value => new Date(value * 1000).toLocaleTimeString();
      msg.textContent = availableNotice + (lost ? 'Ha perdido el control. Conserve su texto y recargue antes de editar.' : changed ? 'El ticket cambió o el control caducó. Conserve su texto y recargue antes de editar.' :
        mine ? `Tiene el control desde ${time(r.started)}. Última actividad: ${time(r.heartbeat)}.${r.requester ? ' Otro técnico solicita el control.' : ''}` :
        r.available ? 'Modo consulta. Puede adquirir el control para editar.' : `Editando: ${r.owner}. Desde ${time(r.started)}. Última actividad: ${time(r.heartbeat)}.`);
      if (c.mode === 'warning') msg.textContent += ' Modo aviso: no se impide el guardado.';
      bar.className = `alert ${changed ? 'alert-danger' : mine ? 'alert-success' : 'alert-info'} m-3`;
      buttons.acquire.hidden = mine || !r.available || changed;
      buttons.release.hidden = !mine;
      buttons.request.hidden = mine || r.available;
      if (buttons.takeover) buttons.takeover.hidden = mine || r.available || changed;
      fields(); return r;
    }
    async function poll() {
      if (busy) return;
      busy = true;
      try {
        // Stop renewing an abandoned tab, even if the browser keeps its timers running.
        const active = document.visibilityState === 'visible' && Date.now() - lastActivity < 180000;
        await call(mine && active && !changed ? 'heartbeat' : 'status');
      } catch (e) { fail(e); } finally { busy = false; }
    }
    fields(); poll();
    setInterval(poll, c.interval * 1000);
    // Correctness uses TTL, never unload/beacon delivery. No token is stored on disk.
  }
  const scan = () => document.querySelectorAll('.editcontrol-bootstrap').forEach(mount);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan); else scan();
  new MutationObserver(scan).observe(document.documentElement, {childList:true, subtree:true});
})();
