// =============================================================================
// /admin/js/user_permissions_save.js
// Definitieve, robuuste versie: detecteert gebruiker en CSRF, rendert fallback
// feature controls wanneer nodig, bindt automatisch aan de Opslaan knop,
// bouwt payload en voert save uit met duidelijke console logging.
// Upload naar /admin/js/ en include in user_permissions.php vlak voor </body>
// =============================================================================
(function(){
  'use strict';

  function _log(k,v){ try { console.log('UPSAVE:', k, v); } catch(e){} }
  function _showToast(msg){ if (typeof showToast === 'function') try { showToast(msg); return; } catch(e){} _log('TOAST', msg); }

  function _qs(name){
    try { return new URLSearchParams(window.location.search).get(name); } catch(e){ return null; }
  }

  function _discoverUserId(){
    try {
      const elData = document.querySelector('[data-userid]');
      if (elData){ const v = elData.getAttribute('data-userid'); if (v) return parseInt(v,10); }
      const elId = document.getElementById('userid');
      if (elId){
        const v = (elId.value !== undefined && elId.value !== '') ? elId.value : elId.textContent;
        if (v) return parseInt(v,10);
      }
      const elHidden = document.querySelector('input[name="userid"], input[name="user_id"], input#userid, input#user_id');
      if (elHidden && elHidden.value) return parseInt(elHidden.value,10);
      const fromUrl = _qs('id') || _qs('userid') || _qs('user');
      if (fromUrl) return parseInt(fromUrl,10);
    } catch(e){}
    return NaN;
  }

  function _discoverCsrf(){
    try {
      const el1 = document.getElementById('csrf_token');
      if (el1 && (el1.value || el1.getAttribute('value'))) return el1.value || el1.getAttribute('value');
      const el2 = document.querySelector('input[name="csrf_token"], input[name="_csrf"], input[name="csrf"]');
      if (el2 && el2.value) return el2.value;
      const meta = document.querySelector('meta[name="csrf-token"], meta[name="csrf"]');
      if (meta) return meta.getAttribute('content');
      const hiddenJson = document.getElementById('page-data-json');
      if (hiddenJson){
        try { const j = JSON.parse(hiddenJson.textContent || hiddenJson.value || '{}'); return j.csrf_token || null; } catch(e){}
      }
    } catch(e){}
    return null;
  }

  function _discoverFeaturesFromDOM(){
    const out = [];
    try {
      const fEls = document.querySelectorAll('.feature-chk[data-feature-key]');
      if (fEls && fEls.length){
        fEls.forEach(function(el){
          const key = el.getAttribute('data-feature-key');
          if (!key) return;
          out.push({ key_name: key, visible: el.checked ? 1 : 0 });
        });
        if (out.length) return out;
      }
    } catch(e){ _log('discoverA_err', String(e)); }

    try {
      const rows = document.querySelectorAll('table.features tr[data-feature-key], tr[data-key]');
      if (rows && rows.length){
        rows.forEach(function(r){
          const key = r.getAttribute('data-feature-key') || r.getAttribute('data-key');
          if (!key) return;
          const cb = r.querySelector('input[type="checkbox"], input[type="radio"]');
          if (cb){ out.push({ key_name: key, visible: cb.checked ? 1 : 0 }); return; }
          const sel = r.querySelector('select');
          if (sel){ out.push({ key_name: key, visible: (sel.value == '1' || sel.value === 'true') ? 1 : 0 }); return; }
          const inp = r.querySelector('input[type="hidden"], input[type="text"], input[type="number"]');
          if (inp){ out.push({ key_name: key, visible: (inp.value == '1' || inp.value === 'true') ? 1 : 0 }); return; }
          out.push({ key_name: key, visible: 0 });
        });
        if (out.length) return out;
      }
    } catch(e){ _log('discoverB_err', String(e)); }

    try {
      const nodes = Array.prototype.slice.call(document.querySelectorAll('input, select, textarea'));
      const parsed = [];
      nodes.forEach(function(el){
        const name = el.getAttribute('name');
        if (!name) return;
        if (name.length > 8 && name.indexOf('feature[') === 0 && name.charAt(name.length-1) === ']'){
          const key = name.substring(8, name.length-1);
          parsed.push({ el: el, key: key });
        }
      });
      if (parsed.length){
        parsed.forEach(function(item){
          const el = item.el;
          const key = item.key;
          if (!key) return;
          let vis = 0;
          const tag = (el.tagName || '').toLowerCase();
          const type = (el.type || '').toLowerCase();
          if (type === 'checkbox' || type === 'radio') vis = el.checked ? 1 : 0;
          else if (tag === 'select') vis = (el.value == '1' || el.value === 'true') ? 1 : 0;
          else vis = (el.value == '1' || el.value === 'true') ? 1 : 0;
          out.push({ key_name: key, visible: vis });
        });
        if (out.length) return out;
      }
    } catch(e){ _log('discoverC_err', String(e)); }

    try {
      const anyEls = document.querySelectorAll('[data-feature-key]');
      if (anyEls && anyEls.length){
        anyEls.forEach(function(el){
          const key = el.getAttribute('data-feature-key');
          if (!key) return;
          let vis = 0;
          const tag = (el.tagName || '').toLowerCase();
          const type = (el.type || '').toLowerCase();
          if (type === 'checkbox' || type === 'radio') vis = el.checked ? 1 : 0;
          else if (tag === 'select') vis = (el.value == '1' || el.value === 'true') ? 1 : 0;
          else vis = (el.value == '1' || el.value === 'true') ? 1 : 0;
          out.push({ key_name: key, visible: vis });
        });
        if (out.length) return out;
      }
    } catch(e){ _log('discoverD_err', String(e)); }

    try {
      const dataEl = document.getElementById('features-json') || document.getElementById('page-data-json');
      if (dataEl){
        try {
          const parsed = JSON.parse(dataEl.textContent || dataEl.value || '{}');
          if (Array.isArray(parsed.features)){
            parsed.features.forEach(function(f){
              if (f && f.key_name) out.push({ key_name: f.key_name, visible: f.visible ? 1 : 0 });
            });
            if (out.length) return out;
          }
        } catch(e){ _log('discoverE_json_err', String(e)); }
      }
    } catch(e){ _log('discoverE_err', String(e)); }

    return out;
  }

  function buildPermissionsPayload(){
    try {
      const userid = _discoverUserId();
      if (!userid || isNaN(userid)){ _log('no_userid', true); return null; }
      const csrf_token = _discoverCsrf();
      if (!csrf_token){ _log('no_csrf', true); return null; }
      const cspEl = document.getElementById('can_show_presentation');
      const can_show_presentation = cspEl ? (cspEl.checked ? 1 : 0) : 0;
      const pidEl = document.getElementById('presentation_id') || document.querySelector('input[name="presentation_id"]');
      const presentation_id = pidEl ? (pidEl.value === '' ? null : pidEl.value) : null;
      const features = _discoverFeaturesFromDOM();
      return { csrf_token: csrf_token, userid: userid, can_show_presentation: can_show_presentation, presentation_id: presentation_id, features: features };
    } catch (e) { _log('buildError', String(e)); return null; }
  }

  async function saveUserPermissions(payload){
    try {
      if (!payload) return { success: false, error: 'no_payload' };
      _log('saving_payload_len', Array.isArray(payload.features) ? payload.features.length : 'no-features');
      const res = await fetch('/admin/api/features_update.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(payload)
      });
      _log('save_status', res.status);
      const text = await res.text().catch(()=>null);
      _log('save_raw', text);
      if (!res.ok){
        _showToast('Opslaan mislukt: server_status_' + res.status);
        return { success: false, error: 'server_status_' + res.status, raw: text };
      }
      let data = null;
      try { data = text ? JSON.parse(text) : null; } catch(e){ _log('invalid_json', text); _showToast('Opslaan mislukt: ongeldige serverreactie'); return { success: false, error: 'invalid_json', raw: text }; }
      if (data && data.success){
        _showToast('Opgeslagen');
        // if features array was empty, force reload so DB reflects
        if ((!payload.features) || (Array.isArray(payload.features) && payload.features.length === 0)){
          setTimeout(function(){ location.reload(true); }, 300);
        }
        return { success: true, data: data };
      } else {
        _showToast('Opslaan mislukt: ' + (data && data.error ? data.error : 'server_error'));
        return { success: false, error: data && data.error ? data.error : 'server_error', raw: data };
      }
    } catch (err){
      _log('save_exception', String(err));
      _showToast('Opslaan mislukt: netwerkfout');
      return { success: false, error: 'network_error', exception: String(err) };
    }
  }

  // Ensure feature controls exist if none present
  (async function _ensureFeatureControls(){
    try {
      const existing = document.querySelectorAll('.feature-chk[data-feature-key]');
      if (existing && existing.length) { _log('existing_controls', existing.length); return; }
      const uid = (function(){
        try {
          const el = document.querySelector('[data-userid]') || document.getElementById('userid') || document.querySelector('input[name="userid"], input[name="user_id"]');
          if (el) return el.getAttribute('data-userid') || el.value || el.textContent || '';
        } catch(e){}
        return _qs('id') || _qs('userid') || '';
      })();
      if (!uid || String(uid).trim() === '') { _log('no_uid_for_fallback', true); return; }
      const res = await fetch('/admin/api/features.php?id=' + encodeURIComponent(uid), { credentials: 'same-origin' });
      if (!res.ok){ _log('features_api_status', res.status); return; }
      const json = await res.json().catch(()=>null);
      if (!json || !Array.isArray(json.features) || json.features.length === 0){ _log('features_api_empty', true