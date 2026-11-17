/* SPDX-License-Identifier: GPL-3.0-or-later */
(() => {
  'use strict';
  function mount(node) {
    if (node.dataset.mounted) return;
    node.dataset.mounted = '1';
    const c = JSON.parse(node.dataset.config);
    if (document.querySelector('[data-editcontrol-active]')) return;
    node.dataset.editcontrolActive = '1';
    let token = '', mine = false, changed = false, busy = false, stopped = false;
    let requestToken = '', pendingRequestKey = '';
    let lastActivity = Date.now(), lastHeartbeat = 0, lastPoll = 0;
    const bar = document.createElement('section');
    const normalClass = 'alert alert-secondary d-flex align-items-center flex-wrap gap-2 m-3 py-2 px-3';
    bar.className = normalClass;
    bar.setAttribute('aria-label', 'Actividad del ticket');
    const msg = document.createElement('p');
    msg.className = 'mb-0 flex-grow-1';
    msg.setAttribute('role', 'status');
    msg.setAttribute('aria-live', 'polite');
    msg.textContent = 'Comprobando quién está usando el ticket…';
    const request = document.createElement('button');
    request.type = 'button'; request.className = 'btn btn-sm btn-outline-primary';
    request.textContent = 'Solicitar control'; request.hidden = true;
    const notice = document.createElement('div');
    notice.className = 'd-flex align-items-center flex-wrap gap-2 w-100';
    notice.setAttribute('role', 'alert'); notice.hidden = true;
    const noticeText = document.createElement('span');
    const handoff = document.createElement('button');
    handoff.type = 'button'; handoff.className = 'btn btn-sm btn-primary';
    handoff.textContent = 'Ceder control'; handoff.hidden = true;
    notice.append(noticeText, handoff);
    bar.append(msg, request, notice);
    (document.querySelector('main') || document.body).prepend(bar);
    const ticketForm = form => /(?:ticket|itilfollowup|tickettask|itilsolution)\.form\.php(?:$|[?\/])/.test(new URL(form.action, location.href).pathname)
      || !!form.querySelector('[name="tickets_id"], [name="itemtype"][value="Ticket"]');
    function fields() {
      document.querySelectorAll('form[method="post" i]').forEach(form => {
        if (bar.contains(form) || !ticketForm(form)) return;
        for (const [name, value] of Object.entries({_editcontrol_token: token, _editcontrol_baseline: c.baseline})) {
          let hidden = form.querySelector(`input[name="${name}"]`);
          if (!hidden) { hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = name; form.append(hidden); }
          if (hidden.value !== value) hidden.value = value;
        }
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(button => {
          const block = c.mode === 'strict' && (!mine || changed);
          if (block && !button.disabled) { button.dataset.editcontrolDisabled = '1'; button.disabled = true; }
          if (!block && button.dataset.editcontrolDisabled) { button.disabled = false; delete button.dataset.editcontrolDisabled; }
        });
      });
    }
    if (window.jQuery) window.jQuery.ajaxPrefilter((options, original, xhr) => {
      if (new URL(options.url, location.href).origin === location.origin) {
        xhr.setRequestHeader('X-Editcontrol-Token', token);
        xhr.setRequestHeader('X-Editcontrol-Baseline', c.baseline);
      }
    });
    document.addEventListener('submit', e => {
      if (c.mode === 'strict' && (!mine || changed) && e.target.method.toLowerCase() === 'post' && ticketForm(e.target)) {
        e.preventDefault(); e.stopImmediatePropagation();
        msg.textContent = 'Este ticket está en uso o se ha actualizado. Conserve su texto antes de actualizar la página.';
      }
    }, true);
    new MutationObserver(fields).observe(document.body, {childList:true, subtree:true});
    for (const event of ['input','pointerdown','keydown']) {
      document.addEventListener(event, () => { lastActivity = Date.now(); }, {passive:true});
    }
    function body(action, leaseToken = requestToken || token) {
      return new URLSearchParams({action, ticket:c.id, nonce:c.nonce, token:leaseToken, baseline:c.baseline,
        request_token:requestToken, request_key:pendingRequestKey, _glpi_csrf_token:c.csrf});
    }
    function headers() {
      // GLPI 11 checks this HEADER (not the POST field) on XMLHttpRequest requests.
      return {'X-Requested-With':'XMLHttpRequest', 'X-Glpi-Csrf-Token':c.csrf};
    }
    async function call(action) {
      const res = await fetch(c.url, {method:'POST', credentials:'same-origin', body:body(action), headers:headers(), signal:AbortSignal.timeout(12000)});
      const type = res.headers.get('content-type') || '';
      if (!type.toLowerCase().includes('application/json')) {
        const error = new Error(res.status === 401 || res.status === 403 || res.redirected
          ? 'La sesión necesita actualizarse. Guarde su texto y actualice la página.'
          : 'No se puede consultar la actividad del ticket. Se reintentará automáticamente.');
        error.status = res.status; throw error;
      }
      let r;
      try { r = await res.json(); }
      catch { throw new Error('No se puede consultar la actividad del ticket. Se reintentará automáticamente.'); }
      if (r.csrf) c.csrf = r.csrf;
      if (!res.ok || !r.ok) {
        const error = new Error(r.message || 'No se puede consultar la actividad del ticket.');
        error.status = res.status; throw error;
      }
      if (r.token) { token = r.token; requestToken = ''; lastActivity = Date.now(); lastHeartbeat = Date.now(); }
      if (r.mine && requestToken) {
        // The owner assigned directly to this tab's pre-registered request credential.
        token = requestToken; requestToken = ''; lastHeartbeat = Date.now(); lastActivity = Date.now();
      }
      if (action === 'heartbeat') lastHeartbeat = Date.now();
      return r;
    }
    function render(r) {
      const lost = mine && !r.mine;
      mine = r.mine; changed = changed || r.changed || lost;
      bar.className = normalClass;
      if (r.owner) {
        msg.textContent = `${r.owner}${mine ? ' (tú)' : r.same_user ? ' (otra pestaña)' : ''} está usando este ticket.`;
      } else {
        msg.textContent = 'Ningún técnico está usando este ticket.';
      }
      if (changed) msg.textContent += ' El ticket se ha actualizado; conserve su texto antes de actualizar la página.';
      const time = value => new Date(value * 1000).toLocaleString();
      bar.title = r.owner ? `Desde ${time(r.started)}. Última actividad: ${time(r.heartbeat)}.` : '';
      request.hidden = !c.canEdit || mine || r.available;
      request.disabled = !!r.requested_by_me;
      request.textContent = r.requested_by_me ? 'Solicitud enviada' : 'Solicitar control';
      pendingRequestKey = mine ? (r.request_key || '') : '';
      notice.hidden = !mine || !r.requester_name;
      const message = r.requester_name ? `${r.requester_name} solicita el control de este ticket.` : '';
      if (noticeText.textContent !== message) noticeText.textContent = message;
      handoff.hidden = notice.hidden || !pendingRequestKey;
      handoff.disabled = false;
      handoff.setAttribute('aria-label', `Ceder control a ${r.requester_name || 'otro técnico'}`);
      if (!notice.hidden) bar.className = 'alert alert-warning d-flex align-items-center flex-wrap gap-2 m-3 py-2 px-3';
      fields();
    }
    function fail(error) {
      // A temporary outage does not discard the token, allowing later heartbeats to recover.
      request.hidden = true;
      handoff.hidden = true;
      msg.textContent = error.name === 'TypeError' || error.name === 'TimeoutError'
        ? 'No se puede consultar la actividad del ticket. Se reintentará automáticamente.' : error.message;
      bar.className = 'alert alert-warning d-flex align-items-center flex-wrap gap-2 m-3 py-2 px-3';
      if (c.mode === 'strict') { mine = false; fields(); }
    }
    request.onclick = async () => {
      if (busy) return;
      busy = true; request.disabled = true;
      if (!requestToken) requestToken = Array.from(crypto.getRandomValues(new Uint8Array(32)), b=>b.toString(16).padStart(2,'0')).join('');
      try { render(await call('request')); }
      catch (error) { fail(error); }
      finally { busy = false; }
    };
    handoff.onclick = async () => {
      if (busy || !mine || !pendingRequestKey) return;
      busy = true; handoff.disabled = true;
      try {
        const r = await call('handoff');
        // This is a deliberate handoff, not a stale-form event. Preserve all draft fields.
        token = ''; mine = false; requestToken = '';
        render(r);
        msg.textContent += ' Has cedido el control.';
      } catch (error) { fail(error); }
      finally { busy = false; }
    };
    async function poll(force = false) {
      if (busy || stopped || !node.isConnected) return;
      const period = document.visibilityState !== 'visible' ? 30000
        : mine || requestToken ? Math.max(5, c.pollInterval || 8) * 1000 : c.interval * 1000;
      if (!force && Date.now() - lastPoll < period) return;
      lastPoll = Date.now();
      busy = true;
      try {
        const active = document.visibilityState === 'visible' && Date.now() - lastActivity < 180000;
        let r;
        const heartbeatDue = Date.now() - lastHeartbeat >= c.interval * 1000;
        try { r = await call(mine && active && !changed && heartbeatDue ? 'heartbeat' : 'status'); }
        catch (error) {
          if (error.status !== 409) throw error;
          r = await call('status');
        }
        // The first eligible visible tab acquires automatically. Readers never acquire.
        if (r.available && c.canEdit && active && !changed && !r.changed) {
          try { r = await call('acquire'); }
          catch (error) {
            if (error.status !== 409) throw error;
            r = await call('status');
          }
        }
        render(r);
      } catch (error) { fail(error); }
      finally { busy = false; }
    }
    // Best-effort navigation/close release; TTL remains the correctness fallback.
    window.addEventListener('pagehide', () => {
      stopped = true;
      if (!token) return;
      fetch(c.url, {method:'POST', credentials:'same-origin', body:body('release'), headers:headers(), keepalive:true}).catch(() => {});
      token = ''; mine = false;
    });
    window.addEventListener('pageshow', event => { if (event.persisted) { stopped = false; poll(true); } });
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') poll(true); });
    fields(); poll();
    setInterval(poll, Math.max(5, Math.min(c.pollInterval || 8, c.interval)) * 1000);
  }
  const scan = () => document.querySelectorAll('.editcontrol-bootstrap').forEach(mount);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan); else scan();
  new MutationObserver(scan).observe(document.documentElement, {childList:true, subtree:true});
})();
