// --- LIVE TIMER (circular, advanced) ---
// Fetches timer info from API and updates the circular timer in the Live Timer card
function updateLiveTimerUI(data) {
  // Elements
  const display = document.getElementById('live-timer-display');
  const level = document.getElementById('live-timer-level');
  const blinds = document.getElementById('live-timer-blinds');
  const progressCircle = document.getElementById('live-timer-progress');
  const tile = document.getElementById('live-timer-tile');
  // Timer logic
  let seconds = (data && typeof data.seconds_remaining === 'number') ? data.seconds_remaining : 0;
  let totalDuration = (data && typeof data.duration_seconds === 'number') ? data.duration_seconds : 1;
  let isPaused = data && data.is_paused;
  // Masquer si valeur aberrante (> 2h = timer pas encore démarré) ou nulle
  const timerValid = (seconds > 0 && seconds <= 7200);
  if (tile) tile.style.display = timerValid ? 'flex' : 'none';
  if (!timerValid) return;
  // Display
  if (isPaused) {
    display.textContent = 'PAUSE';
    display.style.color = '#ff0000';
  } else {
    let m = Math.floor(seconds / 60).toString().padStart(2, '0');
    let s = (seconds % 60).toString().padStart(2, '0');
    display.textContent = `${m}:${s}`;
    // Color: red if <=2min, cyan otherwise
    display.style.color = (seconds <= 120 && seconds > 0) ? '#ff0000' : '#00d2ff';
  }
  // Level name
  if (level) level.textContent = data && data.level_name ? data.level_name : 'Niveau';
  // Blinds
  if (blinds) blinds.textContent = data && data.blinds_text ? data.blinds_text : '-- / --';
  // Progress circle
  if (progressCircle && totalDuration > 0) {
    const circumference = 2 * Math.PI * 50; // r=50
    let elapsed = totalDuration - seconds;
    let progress = Math.max(0, Math.min(1, elapsed / totalDuration));
    const offset = circumference * (1 - progress);
    progressCircle.style.strokeDashoffset = offset;
    // Color
    if (seconds <= 120 && seconds > 0) {
      progressCircle.style.stroke = '#ff0000';
      progressCircle.style.filter = 'drop-shadow(0 0 6px #ff0000)';
    } else {
      progressCircle.style.stroke = '#00d2ff';
      progressCircle.style.filter = 'drop-shadow(0 0 6px #00d2ff)';
    }
  }
}

// Polls the timer API and updates the Live Timer card every 5s
async function pollLiveTimer() {
  try {
    // Use the current activity id if available
    const act = window.currentActivity;
    if (!act || !act.id) return;
    const url = apiUrl('next-activity.php?id=' + encodeURIComponent(act.id));
    const res = await fetchWithRetry(url, {}, 1, 400);
    if (res && (res.seconds_remaining !== undefined || res.duration_seconds !== undefined)) {
      updateLiveTimerUI(res);
    }
  } catch (e) {
    // fallback: show --:--
    updateLiveTimerUI(null);
  }
}

// Start polling when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  pollLiveTimer();
  setInterval(pollLiveTimer, 5000);
});
console.log('app.js chargé');
// Test JS execution: change #js-test-div if present
window.addEventListener('DOMContentLoaded', function(){
  var testDiv = document.getElementById('js-test-div');
  if(testDiv) testDiv.textContent = '[JS OK]';
});
// Minimal JS client to interact with the PHP API

// Fetch with retries and exponential backoff
async function fetchWithRetry(url, opts={}, retries=3, backoff=600){
  let lastErr = null;
  for(let i=0;i<=retries;i++){
    try{
      const res = await fetch(url, opts);
      if(!res.ok) throw new Error('http '+res.status+' '+res.statusText);
      return await res.json();
    }catch(e){
      lastErr = e;
      if(i === retries) break;
      await new Promise(r=>setTimeout(r, backoff*(i+1)));
    }
  }
  throw lastErr;
}

// Debug logger (no console output by default)
const debugLog = (...args)=>{ if(window.DEBUG_MODE) console.warn(...args); };

// API base helper: can be set by server via window.API_BASE or stored in localStorage
function getApiBase(){
  if(window.API_BASE && window.API_BASE.length) return window.API_BASE.replace(/\/$/,'');
  const saved = localStorage.getItem('apiBase') || '';
  if(saved) return saved.replace(/\/$/,'');
  // Si on n'est pas sur viendez.com, utilise l'IP locale
  if(location.hostname === 'viendez.com' || location.hostname === 'www.viendez.com')
    return 'https://viendez.com/api';
  // Sinon, IP locale
  return 'http://192.168.1.77/api';
}
function apiUrl(path){
  const base = getApiBase();
  if(!base) throw new Error('no-api-base');
  return base.replace(/\/$/,'') + '/' + path.replace(/^\//,'');
}

window.currentActivity = window.currentActivity || null;
window.timerInterval = window.timerInterval || null;
window.lastFetchError = window.lastFetchError || null;
window.activitiesList = window.activitiesList || [];
window.currentIndex = typeof window.currentIndex !== 'undefined' ? window.currentIndex : -1;

// Safely set the text inside a pill without removing icon SVGs: always ensure a <span> exists and update it.
function setPillText(id, value){
  const el = document.getElementById(id);
  if(!el) return;
  let sp = el.querySelector('span');
  if(!sp){
    sp = document.createElement('span');
    el.appendChild(sp);
  }
  sp.textContent = value;
}

// Helper to show inscrits-pill: '— inscrits' if absent, else 'X inscrit(s)'
function setInscritsPill(count) {
  if (count === null || count === undefined || count === '' || isNaN(Number(count)) || Number(count) === 0) {
    setPillText('inscrits-pill', '— inscrits');
  } else {
    setPillText('inscrits-pill', Number(count) + ' inscrit' + (Number(count) > 1 ? 's' : ''));
  }
}

// Instrumentation: intercept textContent/innerHTML writes to diagnose who replaces pill contents
(function instrumentPills(){
  try{
    const watch = new Set(['inscrits-pill','buyin-pill','rake-pill','activity-date']);
    const nodeDesc = Object.getOwnPropertyDescriptor(Node.prototype, 'textContent');
    if(nodeDesc && nodeDesc.set){
      const origSet = nodeDesc.set.bind(Node.prototype);
      Object.defineProperty(Node.prototype, 'textContent', {
        get: nodeDesc.get,
        set: function(v){
          try{
            if(this && this.id && watch.has(this.id)){
              console.warn('[PILL DEBUG] textContent set on', this.id, 'value=', v);
              console.trace();
              // If activity-date is being set to a raw timestamp, ignore to preserve formatted display
              if(this.id === 'activity-date' && typeof v === 'string' && /^(\d{4}-\d{2}-\d{2})([ T]\d{2}:\d{2}:\d{2})?$/.test(v)){
                console.warn('[PILL DEBUG] Blocking raw timestamp write to activity-date:', v);
                return; // do not perform the original set
              }
            }
          }catch(e){}
          return nodeDesc.set.call(this, v);
        },
        configurable: true,
        enumerable: nodeDesc.enumerable
      });
    }
    const htmlDesc = Object.getOwnPropertyDescriptor(Element.prototype, 'innerHTML');
    if(htmlDesc && htmlDesc.set){
      Object.defineProperty(Element.prototype, 'innerHTML', {
        get: htmlDesc.get,
        set: function(v){
          try{
            if(this && this.id && watch.has(this.id)){
              console.warn('[PILL DEBUG] innerHTML set on', this.id, 'value=', v);
              console.trace();
              if(this.id === 'activity-date' && typeof v === 'string' && /^(\d{4}-\d{2}-\d{2})([ T]\d{2}:\d{2}:\d{2})?$/.test(v)){
                console.warn('[PILL DEBUG] Blocking raw timestamp innerHTML on activity-date');
                return;
              }
            }
          }catch(e){}
          return htmlDesc.set.call(this, v);
        },
        configurable: true,
        enumerable: htmlDesc.enumerable
      });
    }
  }catch(e){ console.error('instrumentPills failed', e); }
})();

function formatEur(v){ return v + ' €'; }

// Format display date: prefer server-provided `display`, otherwise format `dateStr` to French readable string
function formatDisplayDate(display, dateStr){
  if(display && String(display).trim()) return display;
  if(!dateStr) return '';
  let d = dateStr;
  if(typeof d === 'string' && d.indexOf(' ') !== -1 && d.indexOf('T') === -1) d = d.replace(' ', 'T');
  const dt = new Date(d);
  if(isNaN(dt.getTime())) return dateStr;
  try{
    let s = new Intl.DateTimeFormat('fr-FR', { weekday:'long', day:'numeric', month:'long' }).format(dt);
    // Capitalize first letter of each word to match visual style (Vendredi 27 Mars)
    s = s.replace(/\b\w/g, c => c.toUpperCase());
    return s;
  }catch(e){
    return dt.toLocaleString('fr-FR');
  }
}

function startCountdown(dt){
  if(timerInterval) clearInterval(timerInterval);
  // accept string date from API (e.g. "2026-03-23 20:00:00") or Date
  if(typeof dt === 'string'){
    // normalize space-separated datetime to ISO-like format so `new Date()` parses reliably
    if(dt.indexOf(' ') !== -1 && dt.indexOf('T') === -1) dt = dt.replace(' ', 'T');
    dt = new Date(dt);
  }
  function u(){
    if(!(dt instanceof Date) || isNaN(dt.getTime())){
      debugLog('startCountdown: invalid date', dt);
      return;
    }
    const now = new Date();
    const s = Math.floor((dt - now)/1000);
    // Ne pas toucher à #live-timer-display (géré par le timer live de blindes)
    const el = document.getElementById('countdown');
    const lbl = document.querySelector('.count-label');
    if(s>0){
      const h = String(Math.floor(s/3600)).padStart(2,'0');
      const m = String(Math.floor((s%3600)/60)).padStart(2,'0');
      if(el) el.textContent = h+':'+m;
      if(lbl) lbl.textContent = 'Démarre dans';
    } else {
      if(el) el.textContent = '';
      if(lbl) lbl.textContent = '';
    }
  }
  u();
  timerInterval = setInterval(u,1000);
}

function applyActivityToUI(act){
  if(!act) return;
  currentActivity = act;
  const titleEl = document.getElementById('activity-title'); if(titleEl) titleEl.textContent = act.title || 'Prochaine partie';
  const nameEl = document.getElementById('activity-name'); if(nameEl) nameEl.textContent = act.title || '—';
  const dateEl = document.getElementById('activity-date'); if(dateEl) dateEl.textContent = formatDisplayDate(act.display_date, act.date);
  setPillText('buyin-pill', (act.buyin!==null && act.buyin!==undefined)? act.buyin+' €' : '—');
  setPillText('rake-pill', (act.rake!==null && act.rake!==undefined)? act.rake+' €' : '—');
  setInscritsPill(act.participants_count !== undefined ? act.participants_count : act.count);
  if(act.date) startCountdown(new Date(act.date));
}

async function loadActivitiesList(){
  try{
    const j = await fetchWithRetry(apiUrl('activities-list.php'), {}, 2, 500);
    if(j && j.success && Array.isArray(j.activities)){
      activitiesList = j.activities;
      // try to position currentIndex to the currentActivity if available
      if(currentActivity && currentActivity.id){
        currentIndex = activitiesList.findIndex(a=>String(a.id)===String(currentActivity.id));
      }
      if(currentIndex === -1) currentIndex = 0;
      // apply the first activity if none set yet
      if(activitiesList[currentIndex]) applyActivityToUI(activitiesList[currentIndex]);
    }
  }catch(e){ debugLog('loadActivitiesList', e); }
}

function updateSectionLabelFromActivity(act){
  try{
    const el = document.getElementById('section-label-text');
    if(!el) return;
    if(!act || !act.date){ el.textContent = 'Aucune activité'; return; }
    // normalize date string
    let d = act.date;
    if(typeof d === 'string' && d.indexOf(' ') !== -1 && d.indexOf('T')===-1) d = d.replace(' ', 'T');
    const dt = new Date(d);
    const now = new Date();
    if(isNaN(dt.getTime())){ el.textContent = 'Prochaine partie'; return; }
    if(dt.getTime() <= now.getTime()){
      el.textContent = 'En cours';
    } else {
      el.textContent = 'Prochaine partie';
    }
  }catch(e){ debugLog('updateSectionLabel', e); }
}

async function fetchNext(){
  try{
    document.getElementById('activity-title').textContent = 'Chargement...';
    let j;
    try {
      j = await fetchWithRetry(apiUrl('next-activity.php'), {}, 2, 700);
    } catch (e) {
      // If next-activity.php fails, fallback to activities-list.php
      j = null;
    }
    let act = null;
    if(j && j.success !== false){
      act = {
        id: j.id || j['id-activite'] || j.activity_id || j['id'] || null,
        date: j.date || j.date_depart || j.activity_date || null,
        display_date: j.display_date || null,
        title: j['titre-activite'] || j.title || j.activity_title || j['titre-activite'] || null,
        location: j.ville || j.lieu || j.location || null,
        buyin: (j.buyin!==undefined)? j.buyin : (j['buyin']!==undefined? j['buyin']: null),
        rake: (j.rake!==undefined)? j.rake : (j['rake']!==undefined? j['rake']: null),
        participants_count: (j.participants_count!==undefined)? j.participants_count : (j.count!==undefined? j.count: null)
      };
    } else {
      // Fallback: use first activity from activities-list.php
      try {
        const list = await fetchWithRetry(apiUrl('activities-list.php'), {}, 2, 700);
        if(list && list.success && Array.isArray(list.activities) && list.activities.length > 0) {
          const a = list.activities[0];
          act = {
            id: a.id,
            date: a.date,
            location: a.location || a.ville || a.lieu || null,
            display_date: a.display_date,
            title: a.title,
            buyin: a.buyin,
            rake: a.rake,
            participants_count: a.participants_count
          };
        }
      } catch (err) {
        debugLog('activities-list fallback failed', err);
      }
    }
    if(act) {
      currentActivity = act;
      lastFetchError = null;
      try{ localStorage.setItem('lastActivity', JSON.stringify(act)); }catch(e){}
      document.getElementById('activity-title').textContent = act.title || 'Prochaine partie';
      document.getElementById('activity-name').textContent = act.title || '—';
      document.getElementById('activity-date').textContent = formatDisplayDate(act.display_date, act.date);
      setPillText('buyin-pill', (act.buyin!==null && act.buyin!==undefined)? act.buyin+' €' : '—');
      setPillText('rake-pill', (act.rake!==null && act.rake!==undefined)? act.rake+' €' : '—');
      setInscritsPill(act.participants_count);
      if(act.date) startCountdown(new Date(act.date));
      fetchPodium(act.id);
    } else {
      const at = document.getElementById('activity-title'); if(at) at.textContent = 'Erreur réseau';
    }
  }catch(e){
    debugLog('fetchNext', e);
  }
}

async function fetchPodium(activityId){
  if(!activityId) return;
  try{
    // include token if present (GET param is accepted by server)
    const token = localStorage.getItem('apiToken') || '';
    const url = apiUrl('participants-list.php?activity_id='+activityId) + (token? '&token='+encodeURIComponent(token): '');
    const j = await fetchWithRetry(url, {}, 2, 700);
    if(j.success && j.participants){
      const paid = j.participants.filter(p=>p.gain && p.gain>0).sort((a,b)=>(a.classement||999)-(b.classement||999));
      const el=document.getElementById('podium-list');
      if(!paid.length){ el.innerHTML='<div class="small">Aucun joueur payé</div>'; return; }
      el.innerHTML = paid.map(p=>`<div class="podium-item"><div style="font-weight:700">${p.pseudo||'(inconnu)'}</div><div style="color:var(--green);font-weight:700">${formatEur(p.gain)}</div></div>`).join('');
    }
  }catch(e){ debugLog('podium',e); const el=document.getElementById('podium-list'); if(el) el.innerHTML='<div class="small">Erreur réseau</div>'; }
}

// Register
// Register (sécurisé)
const regBtn = document.getElementById('reg-action');
if(regBtn && !regBtn.dataset.regActionBound){
  regBtn.dataset.regActionBound = '1';
  regBtn.addEventListener('click', async (e)=>{
    const modal = document.getElementById('inscription-modal');
    if(modal){
      e.preventDefault();
      e.stopPropagation();
      const activityId = currentActivity?.id || window.SERVER_ACTIVITY?.id || new URLSearchParams(window.location.search).get('uid');
      const uidInput = modal.querySelector('input[name="uid"]');
      if(uidInput && activityId) uidInput.value = activityId;
      modal.style.display = 'block';
      modal.setAttribute('aria-hidden', 'false');
      if(typeof modal.scrollIntoView === 'function'){
        modal.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
      return;
    }

    if(!currentActivity || !currentActivity.id) return alert('Aucune activité');
    const an = document.getElementById('opt-anonyme')?.checked;
    const op = document.getElementById('opt-option')?.checked;
    const lr = document.getElementById('opt-latereg')?.checked;
    try{
      const headers = {'Content-Type':'application/json'};
      const res = await fetchWithRetry(apiUrl('register-activity.php'), {method:'POST', headers: headers, credentials: 'same-origin', body: JSON.stringify({action:'register', activity_id: currentActivity.id, anonyme: an, is_option: op, latereg: lr, pseudo: 'WebGuest'})}, 2, 700);
      if(res.success){ alert('Inscription: '+(res.registered? 'OK':'?')); fetchNext(); } else alert('Erreur inscription');
    }catch(e){ alert('Erreur réseau: '+(e.message||e)); }
  });
}

// arrow navigation handlers
document.addEventListener('click', async (e)=>{
  if(!e.target) return;
  const btn = e.target.closest ? e.target.closest('button') : null;
  if(!btn || !btn.id) return;
  const id = btn.id;
  
  if(id !== 'next-act' && id !== 'prev-act') return;
  // always attempt to reload list to ensure up-to-date navigation
  try{ await loadActivitiesList(); }catch(e){ debugLog('nav load list', e); }
  // ensure currentIndex points to currentActivity if possible
  if(currentActivity && currentActivity.id){
    const found = activitiesList.findIndex(a=>String(a.id)===String(currentActivity.id));
    if(found !== -1) currentIndex = found;
    else if(currentIndex === -1) currentIndex = 0;
  } else {
    if(currentIndex === -1) currentIndex = 0;
  }

  if(id === 'next-act'){
    debugLog('nav click', id, {currentIndex, activitiesCount: activitiesList.length});
    // brief visual feedback
    try{ btn.classList.add('flash'); setTimeout(()=>btn.classList.remove('flash'), 220); }catch(e){}
    if(activitiesList.length && currentIndex < activitiesList.length-1){
      currentIndex++;
      applyActivityToUI(activitiesList[currentIndex]);
      return;
    }
    // no next in list: try to refresh server-side "next" and reload list
    try{ await fetchNext(); await loadActivitiesList();
      const found = activitiesList.findIndex(a=>String(a.id)===String(currentActivity?.id));
      currentIndex = (found!==-1)? found : activitiesList.length-1;
      applyActivityToUI(activitiesList[currentIndex]);
    }catch(e){ debugLog('next-act fallback', e); }
  }

  if(id === 'prev-act'){
    debugLog('nav click', id, {currentIndex, activitiesCount: activitiesList.length});
    try{ btn.classList.add('flash'); setTimeout(()=>btn.classList.remove('flash'), 220); }catch(e){}
    if(activitiesList.length && currentIndex > 0){
      currentIndex--;
      applyActivityToUI(activitiesList[currentIndex]);
      return;
    }
    // no previous available: reload list to ensure latest state
    try{ await loadActivitiesList();
      if(activitiesList.length && currentIndex > 0){ currentIndex--; applyActivityToUI(activitiesList[currentIndex]); }
    }catch(e){ debugLog('prev-act fallback', e); }
  }
});

// Init
fetchNext(); setInterval(fetchNext, 30000);
// load activity list for navigation
setTimeout(()=>{ try{ loadActivitiesList(); }catch(e){ debugLog('load list', e); } }, 600);


// Sécurise l'eventListener sur variantA
const variantABtn = document.getElementById('variantA');
if(variantABtn){
  variantABtn.addEventListener('click', function(){
    // ... ton code ici si besoin ...
  });
}

// If server injected an activity, use it immediately to populate the UI
document.addEventListener('DOMContentLoaded', ()=>{
  try{
    const srv = window.SERVER_ACTIVITY || (()=>{ try{ return JSON.parse(localStorage.getItem('lastActivity')||'null'); }catch(e){ return null; } })();
    if(srv){
      const act = {
        id: srv.id || srv['id-activite'] || null,
        date: srv.date || srv.date_depart || null,
        display_date: srv.display_date || null,
        title: srv.title || srv['titre-activite'] || null,
        buyin: srv.buyin || srv['buyin'] || null,
        rake: srv.rake || srv['rake'] || null,
        participants_count: srv.participants_count || srv.count || null
      };
      currentActivity = act;
      const at = document.getElementById('activity-title'); if(at) at.textContent = act.title || 'Prochaine partie';
      const an = document.getElementById('activity-name'); if(an) an.textContent = act.title || '—';
      const ad = document.getElementById('activity-date'); if(ad) ad.textContent = formatDisplayDate(act.display_date, act.date);
      setPillText('buyin-pill', (act.buyin? act.buyin+' €':'—'));
      setPillText('rake-pill', (act.rake? act.rake+' €':'—'));
      setInscritsPill(act.participants_count);
      if(act.date) startCountdown(act.date);
      // update the section label text according to activity date (Prochaine partie / En cours)
      try{ updateSectionLabelFromActivity(act); }catch(e){}
    }
  }catch(e){ debugLog('init from server activity', e); }
});

// Offline badge handling
function setOfflineBadge(status){
  // status: 'online' | 'offline' | 'api-down'
  const el = document.getElementById('offline-badge');
  if(!el) return;
  el.classList.remove('visible','warn','error');
  if(status === 'offline'){
    el.classList.add('visible','error'); el.textContent = 'Hors-ligne';
  }else if(status === 'api-down'){
    el.classList.add('visible','warn'); el.textContent = 'API indisponible';
  }else{
    el.classList.remove('visible'); el.textContent = '';
  }
}
window.addEventListener('online', ()=>{ setOfflineBadge('online'); try{ checkApiConnectivity(); }catch(e){} });
window.addEventListener('offline', ()=>setOfflineBadge('offline'));
document.addEventListener('DOMContentLoaded', ()=>{
  setOfflineBadge(navigator.onLine ? 'online' : 'offline');
  if(navigator.onLine) setTimeout(()=>{ try{ checkApiConnectivity(); }catch(e){} }, 300);
});

// Periodic API connectivity check (pings `next-activity.php`)
async function checkApiConnectivity(){
  try{
    const url = apiUrl('next-activity.php');
    // try a single quick attempt
    await fetchWithRetry(url, {}, 0, 400);
    setOfflineBadge('online');
  }catch(e){
    // If no api base configured, don't mark as API-down
    if(e && String(e.message).indexOf('no-api-base')!==-1) return;
    debugLog('API ping failed', e);
    setOfflineBadge('api-down');
  }
}

// start periodic ping every 30s
setInterval(()=>{ if(navigator.onLine) try{ checkApiConnectivity(); }catch(e){} }, 30000);

// Token prompt helpers
function showTokenPrompt(message){
  const p = document.getElementById('token-prompt');
  if(!p) return;
  p.style.display = 'block';
  const inp = document.getElementById('api-token-input'); if(inp) inp.value = localStorage.getItem('apiToken') || '';
  if(message){
    let mEl = p.querySelector('.token-message');
    if(!mEl){ mEl = document.createElement('div'); mEl.className = 'token-message small'; mEl.style.marginTop='6px'; p.appendChild(mEl); }
    mEl.textContent = message;
  }
}
function hideTokenPrompt(){ const p=document.getElementById('token-prompt'); if(p) p.style.display='none'; }
document.addEventListener('click', (e)=>{
  if(e.target && e.target.id==='save-api-token'){
    const v = document.getElementById('api-token-input').value.trim();
    if(v){ localStorage.setItem('apiToken', v); hideTokenPrompt(); fetchNext(); }
  }
  if(e.target && e.target.id==='clear-api-token'){
    localStorage.removeItem('apiToken'); document.getElementById('api-token-input').value='';
  }
});

// Debug panel updater
function getApiBaseSafe(){ try{ return getApiBase(); }catch(e){ return '(none)'; } }
function updateDebug(){
  const el = document.getElementById('debug-info'); if(!el) return;
  // Do not show on-screen debug info unless explicitly enabled (window.DEBUG_MODE === true)
  if(!window.DEBUG_MODE){ el.style.display = 'none'; return; }
  el.style.display = '';
  const info = {
    apiBase: getApiBaseSafe(),
    online: navigator.onLine,
    serverActivity: window.SERVER_ACTIVITY || null,
    lastActivityLocal: (()=>{ try{ return JSON.parse(localStorage.getItem('lastActivity')||'null'); }catch(e){ return localStorage.getItem('lastActivity'); } })(),
    currentActivity: currentActivity || null,
    lastFetchError: lastFetchError || null
  };
  el.textContent = JSON.stringify(info, null, 2);
}

// update debug info periodically and after key events
setInterval(updateDebug, 1500);
document.addEventListener('DOMContentLoaded', updateDebug);

// Monitor pill elements for accidental replacement/removal of the inner <span>
(function monitorPills(){
  const pillIds = ['buyin-pill','rake-pill','inscrits-pill'];
  function ensureSpan(el, fallback){
    if(!el) return;
    const sp = el.querySelector('span');
    if(!sp){
      console.debug('Pill missing span, restoring for', el.id);
      const span = document.createElement('span');
      span.textContent = fallback || '—';
      el.appendChild(span);
    }
  }
  const obs = new MutationObserver((mutations)=>{
    for(const m of mutations){
      if(m.type === 'childList'){
        pillIds.forEach(id=>{
          const el = document.getElementById(id);
          if(el && !el.querySelector('span')){
            const fallback = (currentActivity && currentActivity[id.replace('-pill','')])? String(currentActivity[id.replace('-pill','')]) : null;
            ensureSpan(el, fallback ? (fallback + (id==='inscrits-pill'? ' inscrits':' €')) : '—');
          }
        });
      }
    }
  });
  pillIds.forEach(id=>{
    const el = document.getElementById(id);
    if(el) obs.observe(el, {childList:true, subtree:false, characterData:true});
  });
  // also attempt to restore on DOMContentLoaded if necessary
  document.addEventListener('DOMContentLoaded', ()=>{ pillIds.forEach(id=>ensureSpan(document.getElementById(id),'—')); });
})();
