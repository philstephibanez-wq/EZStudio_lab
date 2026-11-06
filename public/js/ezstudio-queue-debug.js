/* EZStudio LAB queue DEV diagnostic: passive observation and explicit copy. */
'use strict';
(() => {
  const panel = document.getElementById('ezstudio-queue-diagnostic');
  if (!panel) return;
  const output = document.getElementById('ezstudio-queue-diagnostic-output');
  const refresh = document.getElementById('ezstudio-queue-diagnostic-refresh');
  const copy = document.getElementById('ezstudio-queue-diagnostic-copy');
  const status = document.getElementById('ezstudio-queue-diagnostic-status');
  if (!output || !refresh || !copy || !status) return;
  let busy = false;
  const activity = [];
  const started = new WeakMap();
  if (window.jQuery) {
    window.jQuery(document).ajaxSend((_event, xhr, settings) => {
      try {
        const url = new URL(settings.url, location.href);
        if (url.origin !== location.origin || !/^\/analysis\/jobs\/\d+\/cancel$/.test(url.pathname)) return;
        started.set(xhr, {kind:'cancel_request',method:'POST',path:url.pathname,at_utc:new Date().toISOString()});
      } catch (_) { /* observation must never interfere */ }
    });
    window.jQuery(document).ajaxComplete((_event, xhr) => {
      const item = started.get(xhr);
      if (!item) return;
      started.delete(xhr);
      activity.push({...item,http_status:xhr.status,completed_utc:new Date().toISOString()});
      if (activity.length > 25) activity.shift();
    });
  }
  async function collect() {
    if (busy || !panel.open) return;
    busy = true;
    refresh.disabled = true;
    status.textContent = 'Collecte en cours…';
    const at = new Date().toISOString();
    let response;
    try {
      const result = await fetch('/api/dev/queue-diagnostic', {credentials:'same-origin',cache:'no-store',signal:AbortSignal.timeout(10000)});
      const raw = await result.text();
      response = {status:result.status};
      if (raw.length > 100000) response.collection_error = 'BODY_TOO_LARGE';
      else {
        try { response.body = JSON.parse(raw); }
        catch (_) { response.collection_error = 'NON_JSON'; }
      }
    } catch (error) {
      response = {status:null,collection_error:String(error?.name || 'COLLECTION_FAILED')};
    }
    const snapshot = {schema:'ezstudio.browser.queue_dump.v1',captured_utc:at,
      source:'/api/dev/queue-diagnostic',response,
      observed_cancel_requests:activity.slice(),
      note:'Vue d’observation : aucun état global n’est inféré lorsque sa lecture est absente.'};
    output.textContent = JSON.stringify(snapshot,null,2);
    status.textContent = 'Collecte terminée · '+new Date().toLocaleTimeString('fr-FR');
    busy = false;
    refresh.disabled = false;
  }
  refresh.addEventListener('click', () => { void collect(); });
  copy.addEventListener('click', async () => {
    if (!output.textContent.startsWith('{')) return;
    try { await navigator.clipboard.writeText(output.textContent);status.textContent='Diagnostic copié'; }
    catch (_) { output.focus();status.textContent='Sélectionne le texte et utilise Ctrl+C'; }
  });
  panel.addEventListener('toggle', () => { if (panel.open) void collect(); });
})();
