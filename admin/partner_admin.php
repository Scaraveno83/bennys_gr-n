<?php
// /bennys/admin/partner_admin.php
error_reporting(E_ALL); ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/admin_access.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/visibility.php';
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin – Vertragspartner & Preise</title>
<link rel="stylesheet" href="../styles.css">␊
<link rel="stylesheet" href="../header.css">
<link rel="stylesheet" href="../calendar/calendar.css">
</head>
<body>
<?php include __DIR__ . '/../header.php'; ?>
<main class="inventory-page pricing-admin-page">
  <header class="inventory-header">
    <h1 class="inventory-title">🧾 Vertragspartner &amp; Preise – Admin</h1>
    <p class="inventory-description">
      Zentrale Verwaltung für Basispreise, Vertragspartner, Fahrzeuge und deren individuelle Tuning-Daten.
    </p>
    <p class="inventory-info">Nutze die Tabs, um alle Bereiche strukturiert zu pflegen.</p>
  </header>

   <div class="pricing-admin-tabs pricing-center-tabs" role="tablist">␊
    <a href="#tab-base" class="pricing-tab is-active" data-tab="tab-base" role="tab" aria-controls="tab-base" aria-selected="true" id="tab-base-trigger">1) Allgemeine Preise</a>
    <a href="#tab-partners" class="pricing-tab" data-tab="tab-partners" role="tab" aria-controls="tab-partners" aria-selected="false" id="tab-partners-trigger">2) Vertragspartner</a>
    <a href="#tab-cars" class="pricing-tab" data-tab="tab-cars" role="tab" aria-controls="tab-cars" aria-selected="false" id="tab-cars-trigger">3) Fahrzeuge</a>
    <a href="#tab-tuning" class="pricing-tab" data-tab="tab-tuning" role="tab" aria-controls="tab-tuning" aria-selected="false" id="tab-tuning-trigger">4) Tuning je Fahrzeug</a>
    <a href="#tab-pprices" class="pricing-tab" data-tab="tab-pprices" role="tab" aria-controls="tab-pprices" aria-selected="false" id="tab-pprices-trigger">5) Partner-Preise</a>
  </div>␊

  <!-- 1) BASE PRICES -->
  <section class="inventory-section pricing-admin-panel is-active" id="tab-base" role="tabpanel" aria-labelledby="tab-base-trigger">
    <h2>⚙️ Allgemeine Preise (Basis)</h2>
    <form id="baseForm" class="inventory-form pricing-admin-grid">
      <div class="input-control">
        <label for="base_repair">Reparatur</label>
        <input id="base_repair" class="input-field" type="number" name="repair" required>
      </div>
      <div class="input-control">
        <label for="base_wash">Wäsche</label>
        <input id="base_wash" class="input-field" type="number" name="wash" required>
      </div>
      <div class="input-control">
        <label for="base_canister">Benzinkanister</label>
        <input id="base_canister" class="input-field" type="number" name="canister" required>
      </div>
      <div class="input-control">
        <label for="base_dispatch">🚚 Anfahrt (Dispatch)</label>
        <input id="base_dispatch" class="input-field" type="number" name="dispatch_fee" required>
      </div>
      <div class="input-control">
        <label for="base_tow_in">Abschleppen (innerorts)</label>
        <input id="base_tow_in" class="input-field" type="number" name="tow_inside" required>
      </div>
      <div class="input-control">
        <label for="base_tow_out">Abschleppen (außerorts)</label>
        <input id="base_tow_out" class="input-field" type="number" name="tow_outside" required>
      </div>
      <div class="input-control">
        <label for="base_tuning_public">Öffentlicher Tuning-Aufschlag (%)</label>
        <input id="base_tuning_public" class="input-field" type="number" step="0.1" name="tuning_markup_public" required>
      </div>
      <div class="pricing-admin-actions">
        <button class="btn-primary" type="submit">Speichern</button>
      </div>
    </form>
    <div id="baseMsg" class="pricing-admin-message" aria-live="polite"></div>
  </section>

  <!-- 2) PARTNERS -->
  <section class="inventory-section pricing-admin-panel is-active" id="tab-base" role="tabpanel" aria-labelledby="tab-base-trigger">
    <h2>🏢 Vertragspartner</h2>
    <form id="partnerCreate" class="inventory-form pricing-admin-grid">
      <div class="input-control">
        <label for="partner_name">Name</label>
        <input id="partner_name" class="input-field" type="text" name="name" required>
      </div>
      <div class="input-control">
        <label for="partner_tuning">Partner-Tuning-Modifikator (%)</label>
        <input id="partner_tuning" class="input-field" type="number" step="0.1" name="tuning_modifier_percent" value="0">
      </div>
      <div class="input-control input-control--full">
        <label for="partner_remarks">Bemerkungen</label>
        <textarea id="partner_remarks" class="input-field" name="remarks" rows="3"></textarea>
      </div>
      <div class="pricing-admin-actions">
        <button class="btn-primary" type="submit">Anlegen</button>
      </div>
    </form>
    <div id="pList" class="pricing-admin-list"></div>
  </section>

  <!-- 3) CARS -->
  <section class="inventory-section pricing-admin-panel" id="tab-cars" role="tabpanel" hidden aria-labelledby="tab-cars-trigger">
    <h2>🚗 Fahrzeuge verwalten</h2>
    <div class="pricing-admin-grid">
      <div class="input-control">
        <label for="carPartner">Partner</label>
        <select id="carPartner" class="input-field"></select>
      </div>
    </div>
    <form id="carCreate" class="inventory-form pricing-admin-grid">
      <div class="input-control">
        <label for="car_name">Fahrzeugname</label>
        <input id="car_name" class="input-field" type="text" name="car_name" required>
      </div>
      <div class="input-control">
        <label for="car_notes">Notizen</label>
        <input id="car_notes" class="input-field" type="text" name="notes">
      </div>
      <div class="pricing-admin-actions">
        <button class="btn-primary" type="submit">Fahrzeug hinzufügen</button>
      </div>
    </form>
    <div id="carList" class="pricing-admin-list"></div>
  </section>

  <!-- 4) TUNING -->
  <section class="inventory-section pricing-admin-panel" id="tab-tuning" role="tabpanel" hidden aria-labelledby="tab-tuning-trigger">
    <h2>🛠️ Tuning (Key/Value)</h2>
    <div class="pricing-admin-grid">
      <div class="input-control">
        <label for="tunPartner">Partner</label>
        <select id="tunPartner" class="input-field"></select>
      </div>
      <div class="input-control">
        <label for="tunCar">Fahrzeug</label>
        <select id="tunCar" class="input-field"></select>
      </div>
    </div>
    <form id="tunCreate" class="inventory-form pricing-admin-grid">
      <div class="input-control">
        <label for="tuning_part">Teil / Feld (z. B. „Primär“)</label>
        <input id="tuning_part" class="input-field" type="text" name="part" required>
      </div>
      <div class="input-control">
        <label for="tuning_value">Wert (z. B. „Chameleon Light Blue“)</label>
        <input id="tuning_value" class="input-field" type="text" name="value" required>
      </div>
      <div class="pricing-admin-actions">
        <button class="btn-primary" type="submit">Hinzufügen</button>
      </div>
    </form>
    <div id="tunList" class="pricing-admin-list"></div>
  </section>

  <!-- 5) PARTNER PRICES -->
  <section class="inventory-section pricing-admin-panel" id="tab-pprices" role="tabpanel" hidden aria-labelledby="tab-pprices-trigger">
    <h2>💰 Partner-Preise (Overrides)</h2>
    <div class="pricing-admin-grid">
      <div class="input-control">
        <label for="ppPartner">Partner</label>
        <select id="ppPartner" class="input-field"></select>
      </div>
    </div>

    <form id="ppForm" class="inventory-form pricing-admin-grid">
      <fieldset class="pricing-admin-fieldset input-control--full">
        <legend>Werkstatt</legend>
        <div class="pricing-admin-grid">
          <div class="input-control">
            <label for="pp_repair">Reparatur (leer = Basis)</label>
            <input id="pp_repair" class="input-field" type="number" name="repair" placeholder="leer = Basis">
          </div>
          <div class="input-control">
            <label for="pp_wash">Wäsche (leer = Basis)</label>
            <input id="pp_wash" class="input-field" type="number" name="wash" placeholder="leer = Basis">
          </div>
          <div class="input-control">
            <label for="pp_canister">Benzinkanister (leer = Basis)</label>
            <input id="pp_canister" class="input-field" type="number" name="canister" placeholder="leer = Basis">
          </div>
          <div class="input-control">
            <label for="pp_tow_in">Abschleppen (innerorts)</label>
            <input id="pp_tow_in" class="input-field" type="number" name="tow_inside" placeholder="leer = Basis">
          </div>
          <div class="input-control">
            <label for="pp_tow_out">Abschleppen (außerorts)</label>
            <input id="pp_tow_out" class="input-field" type="number" name="tow_outside" placeholder="leer = Basis">
          </div>
        </div>
      </fieldset>

      <fieldset class="pricing-admin-fieldset input-control--full">
        <legend>Außerhalb (Dispatch)</legend>
        <div class="pricing-admin-grid">
          <div class="input-control">
            <label for="pp_repair_out">Reparatur (außerhalb)</label>
            <input id="pp_repair_out" class="input-field" type="number" name="repair_out" placeholder="leer = Basis + Anfahrt">
          </div>
          <div class="input-control">
            <label for="pp_wash_out">Wäsche (außerhalb)</label>
            <input id="pp_wash_out" class="input-field" type="number" name="wash_out" placeholder="leer = Basis + Anfahrt">
          </div>
          <div class="input-control">
            <label for="pp_canister_out">Benzinkanister (außerhalb)</label>
            <input id="pp_canister_out" class="input-field" type="number" name="canister_out" placeholder="leer = Basis + Anfahrt">
          </div>
        </div>
      </fieldset>

      <div class="input-control input-control--full">
        <label for="pp_tuning">Partner-Tuning-Modifikator (%)</label>
        <input id="pp_tuning" class="input-field" type="number" step="0.1" name="tuning_modifier_percent">
      </div>

      <div class="pricing-admin-actions">
        <button class="btn-primary" type="submit">Speichern</button>
      </div>
    </form>
    <div id="ppMsg" class="pricing-admin-message" aria-live="polite"></div>
  </section>
</main>

<script>
const API = '../includes/partner_api.php';

function setPanelVisibility(panel, visible) {
  if (!panel) return;
  if (visible) {
    panel.classList.add('is-active');
    panel.removeAttribute('hidden');
    panel.setAttribute('aria-hidden', 'false');
  } else {
    panel.classList.remove('is-active');
    panel.setAttribute('hidden', '');
    panel.setAttribute('aria-hidden', 'true');
  }
}

function safeGet(source, key, fallback) {
  if (source && Object.prototype.hasOwnProperty.call(source, key) && source[key] !== null && source[key] !== undefined) {
    return source[key];
  }
  return fallback;
}

// Tabs
const adminTabs = Array.from(document.querySelectorAll('.pricing-admin-tabs .pricing-tab'));
const adminPanels = Array.from(document.querySelectorAll('.pricing-admin-panel'));

function activateAdminTab(targetId, { updateHash = true } = {}) {
  if (!adminTabs.length || !adminPanels.length) return;
  let targetPanel = adminPanels.find((panel) => panel.id === targetId);
  if (!targetPanel) {
    targetPanel = adminPanels[0];
    targetId = targetPanel ? targetPanel.id : null;
  }
  if (!targetPanel) return;

  adminTabs.forEach((tab) => {
    const isActive = tab.dataset.tab === targetId;
    tab.classList.toggle('is-active', isActive);
    tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
    tab.setAttribute('tabindex', isActive ? '0' : '-1');
  });

  adminPanels.forEach((panel) => {
    setPanelVisibility(panel, panel === targetPanel);
    if (panel === targetPanel) {
      panel.removeAttribute('tabindex');
    } else {
      panel.setAttribute('tabindex', '-1');
    }
  });

  if (updateHash && targetId && typeof history.replaceState === 'function') {
    const newHash = `#${targetId}`;
    if (window.location.hash !== newHash) {
      history.replaceState(null, '', newHash);
    }
  }
}

adminTabs.forEach((tab) => {
  tab.addEventListener('click', (event) => {
    event.preventDefault();
    activateAdminTab(tab.dataset.tab);
    tab.focus();
  });
});

window.addEventListener('hashchange', () => {
  const hashId = window.location.hash ? window.location.hash.substring(1) : '';
  if (!hashId) return;
  activateAdminTab(hashId, { updateHash: false });
});

const initialHash = window.location.hash ? window.location.hash.substring(1) : '';
if (initialHash) {
  activateAdminTab(initialHash, { updateHash: false });
} else if (adminTabs.length) {
  const defaultTab = adminTabs.find((tab) => tab.classList.contains('is-active')) || adminTabs[0];
  activateAdminTab(defaultTab.dataset.tab, { updateHash: false });
}

// 1) Base prices
async function loadBase(){
  const r = await fetch(API+'?action=get_base_prices'); const j = await r.json();
  const f = document.getElementById('baseForm');
  const baseValues = j && j.base ? j.base : {};
  ['repair','wash','canister','dispatch_fee','tow_inside','tow_outside','tuning_markup_public'].forEach(k=>{
    if (f.elements[k]) {
      f.elements[k].value = safeGet(baseValues, k, '');
    }
  });
}
document.getElementById('baseForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target); const payload = {};
  fd.forEach((v,k)=>payload[k]=v);
  const res = await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'save_base_prices',...payload})});
  const j = await res.json(); const msg = document.getElementById('baseMsg');
  if (j.ok){
    msg.textContent='Gespeichert.';
    msg.className='pricing-admin-message pricing-admin-message--ok';
  } else {
    msg.textContent=j.error||'Fehler';
    msg.className='pricing-admin-message pricing-admin-message--error';
  }
});

// 2) Partners
async function renderPartners(){
  const box = document.getElementById('pList');
  const r = await fetch(API+'?action=list_partners'); const j = await r.json();
  box.innerHTML = '';
  if (!j.items || !j.items.length) {
    const empty = document.createElement('p');
    empty.className = 'pricing-admin-empty';
    empty.textContent = 'Keine Vertragspartner vorhanden.';
    box.appendChild(empty);
    return;
  }
  const table = document.createElement('table');
  table.className='data-table pricing-admin-table';
  table.innerHTML = `<thead><tr><th>Logo</th><th>Name</th><th>Tuning %</th><th>Bemerkung</th><th>Aktion</th></tr></thead>`;
  const tb = document.createElement('tbody');
  (j.items||[]).forEach(p=>{
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${p.logo_url?`<img src="${p.logo_url}" class="pricing-admin-logo" alt="Logo ${p.name||''}">`:'—'}
          <form class="logoForm pricing-admin-logo-form" data-id="${p.id}" enctype="multipart/form-data">
            <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp" required>
            <button class="btn-primary pricing-admin-inline-btn" type="submit">Upload</button>
          </form>
      </td>
      <td><input type="text" value="${p.name||''}" class="in-name pricing-admin-inline-input"></td>
      <td><input type="number" step="0.1" value="${p.tuning_modifier_percent||0}" class="in-mod pricing-admin-inline-input"></td>
      <td><input type="text" value="${p.remarks||''}" class="in-rem pricing-admin-inline-input"></td>
      <td>
        <button class="btn-primary btn-save pricing-admin-inline-btn">Speichern</button>
        <button class="btn-ghost btn-del pricing-admin-inline-btn">Löschen</button>
      </td>`;
    tb.appendChild(tr);

    tr.querySelector('.logoForm').addEventListener('submit', async (e)=>{
      e.preventDefault();
      const id = e.currentTarget.dataset.id;
      const fd = new FormData(e.currentTarget); fd.append('partner_id', id);
      const res = await fetch(API+'?action=upload_logo',{method:'POST',body:fd});
      const jj = await res.json(); if (jj.ok) renderPartners();
    });

    tr.querySelector('.btn-save').addEventListener('click', async ()=>{
      const name = tr.querySelector('.in-name').value.trim();
      const mod  = parseFloat(tr.querySelector('.in-mod').value||0);
      const rem  = tr.querySelector('.in-rem').value;
      await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'update_partner',id:p.id,name, tuning_modifier_percent:mod,remarks:rem})});
      renderPartners();
    });

    tr.querySelector('.btn-del').addEventListener('click', async ()=>{
      if (!confirm('Partner wirklich löschen?')) return;
      await fetch(API+'?action=delete_partner&id='+p.id);
      renderPartners();
      loadPartnerSelects();
    });
  });
  table.appendChild(tb); box.appendChild(table);
}
document.getElementById('partnerCreate').addEventListener('submit', async (e)=>{
  e.preventDefault(); const fd = new FormData(e.target); const payload={}; fd.forEach((v,k)=>payload[k]=v);
  const r = await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'create_partner',...payload})});
  const j = await r.json(); if (j.ok){ e.target.reset(); renderPartners(); loadPartnerSelects(); }
});

// 3) Cars
async function loadCarList(){
  const pid = document.getElementById('carPartner').value;
  if (!pid){ document.getElementById('carList').innerHTML=''; return; }
  const r = await fetch(API+'?action=list_cars&partner_id='+encodeURIComponent(pid)); const j= await r.json();
  const box = document.getElementById('carList'); box.innerHTML='';
  const ul = document.createElement('ul');
  ul.className = 'pricing-admin-listing';
  (j.items||[]).forEach(c=>{
    const li = document.createElement('li');
    li.className = 'pricing-admin-listing__item';
    li.innerHTML = `<span class="pricing-admin-car-name">${c.car_name}</span>${c.notes?`<span class="pricing-admin-note"> – ${c.notes}</span>`:''}
                    <button class="btn-ghost pricing-admin-inline-btn" data-id="${c.id}">Löschen</button>`;
    ul.appendChild(li);
    li.querySelector('button').addEventListener('click', async ()=>{
      if (!confirm('Fahrzeug löschen?')) return;
      await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'delete_car',id:c.id})});
      loadCarList(); loadTunCars();
    });
  });
  box.appendChild(ul);
}
document.getElementById('carCreate').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const pid = document.getElementById('carPartner').value;
  if(!pid) return;
  const fd = new FormData(e.target); const payload={}; fd.forEach((v,k)=>payload[k]=v);
  payload['partner_id']=pid;
  await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'create_car',...payload})});
  e.target.reset(); loadCarList(); loadTunCars();
});

// 4) Tuning
async function loadTunCars(){
  const pid = document.getElementById('tunPartner').value;
  const sel = document.getElementById('tunCar'); sel.innerHTML='';
  if (!pid) return;
  const r = await fetch(API+'?action=list_cars&partner_id='+encodeURIComponent(pid)); const j = await r.json();
  (j.items||[]).forEach(c=>{ const o = document.createElement('option'); o.value=c.id; o.textContent=c.car_name; sel.appendChild(o); });
  renderTunList();
}
async function renderTunList(){
  const cid = document.getElementById('tunCar').value;
  const box = document.getElementById('tunList'); box.innerHTML='';
  if (!cid) return;
  const r = await fetch(API+'?action=list_tuning&car_id='+encodeURIComponent(cid)); const j = await r.json();
  const ul = document.createElement('ul');
  ul.className = 'pricing-admin-listing';
  (j.items||[]).forEach(t=>{
    const li = document.createElement('li');
    li.className = 'pricing-admin-listing__item';
    li.innerHTML = `<span class="pricing-admin-car-name">${t.part}</span>: <span class="pricing-admin-note">${t.value}</span> <button class="btn-ghost pricing-admin-inline-btn" data-id="${t.id}">Löschen</button>`;
    ul.appendChild(li);
    li.querySelector('button').addEventListener('click', async ()=>{
      await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'delete_tuning',id:t.id})});
      renderTunList();
    });
  });
  box.appendChild(ul);
}
document.getElementById('tunPartner').addEventListener('change', loadTunCars);
document.getElementById('tunCar').addEventListener('change', renderTunList);
document.getElementById('tunCreate').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const cid = document.getElementById('tunCar').value; if(!cid) return;
  const fd = new FormData(e.target); const payload={}; fd.forEach((v,k)=>payload[k]=v);
  payload['car_id']=cid;
  await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'add_tuning',...payload})});
  e.target.reset(); renderTunList();
});

// 5) Partner-Preise
async function loadPartnerPriceForm(){
  const pid = document.getElementById('ppPartner').value;
  if (!pid) return;
  const r = await fetch(API+'?action=get_partner_prices&partner_id='+encodeURIComponent(pid)); const j = await r.json();
  const f = document.getElementById('ppForm');
  const overrides = j && j.override ? j.override : {};
  ['repair','repair_out','wash','wash_out','canister','canister_out','tow_inside','tow_outside'].forEach(s=>{
    if (f.elements[s]) {
      f.elements[s].value = safeGet(overrides, s, '');
    }
  });
  const partnerData = j && j.partner ? j.partner : {};
  f.elements['tuning_modifier_percent'].value = safeGet(partnerData, 'tuning_modifier_percent', 0);
}
document.getElementById('ppForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const pid = document.getElementById('ppPartner').value; if(!pid) return;
  const fd = new FormData(e.target); const payload={}; fd.forEach((v,k)=>payload[k]=v);
  payload['partner_id']=pid;
 const r = await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'save_partner_prices',...payload})});
  const j = await r.json(); const m = document.getElementById('ppMsg');
  if (j.ok){
    m.textContent='Gespeichert.';
    m.className='pricing-admin-message pricing-admin-message--ok';
  } else {
    m.textContent=j.error||'Fehler';
    m.className='pricing-admin-message pricing-admin-message--error';
  }
});

// Shared: Partner-Selects laden
async function loadPartnerSelects(){
  const r = await fetch(API+'?action=list_partners'); const j = await r.json();
  const sels = [document.getElementById('carPartner'), document.getElementById('tunPartner'), document.getElementById('ppPartner')];
  sels.forEach(s=>{ s.innerHTML=''; const o0=document.createElement('option'); o0.value=''; o0.textContent='– wählen –'; s.appendChild(o0); });
  (j.items||[]).forEach(p=>{
    sels.forEach(s=>{ const o=document.createElement('option'); o.value=p.id; o.textContent=p.name; s.appendChild(o); })
  });
}

// Init
loadBase();
renderPartners();
loadPartnerSelects();
document.getElementById('carPartner').addEventListener('change', loadCarList);
document.getElementById('ppPartner').addEventListener('change', loadPartnerPriceForm);
</script>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="../script.js"></script>
