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
<link rel="stylesheet" href="../styles.css">
<link rel="stylesheet" href="../header.css">
<link rel="stylesheet" href="../calendar/calendar.css">
<style>
.page-wrap{max-width:1100px;margin:120px auto 60px;padding:0 20px;}
.tabs {display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px;}
.tab {padding:8px 12px;border:1px solid rgba(57,255,20,.35);border-radius:10px;cursor:pointer;}
.tab.active {background:#151515; box-shadow:0 0 8px rgba(57,255,20,.35);}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:900px){.grid2{grid-template-columns:1fr}}
label{display:block;margin:6px 0 3px;font-weight:600}
input[type="text"],input[type="number"],textarea,select{
  width:100%;padding:10px;border-radius:10px;border:1px solid rgba(57,255,20,.35);
  background:rgba(20,20,20,.9);color:#fff
}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:8px;border-bottom:1px solid rgba(57,255,20,.25)}
.small{opacity:.85}
.logo-prev{max-width:140px;max-height:90px;display:block;margin-top:6px;border-radius:8px}
.msg{margin-top:6px;font-weight:bold}
.msg.ok{color:#8fff8f}.msg.err{color:#76ff65}
.fieldset{border:1px dashed rgba(57,255,20,.35);border-radius:10px;padding:10px}
.fieldset legend{padding:0 6px;opacity:.9}
</style>
</head>
<body>
<?php include __DIR__ . '/../header.php'; ?>
<main class="page-wrap">
  <h1>🧾 Vertragspartner & Preise – Admin</h1>
  <div class="tabs">
    <div class="tab active" data-tab="base">1) Allgemeine Preise</div>
    <div class="tab" data-tab="partners">2) Vertragspartner</div>
    <div class="tab" data-tab="cars">3) Fahrzeuge</div>
    <div class="tab" data-tab="tuning">4) Tuning je Fahrzeug</div>
    <div class="tab" data-tab="pprices">5) Partner-Preise (Overrides)</div>
  </div>

  <!-- 1) BASE PRICES -->
  <section class="cal-card" id="tab-base">
    <h2>⚙️ Allgemeine Preise (Basis)</h2>
    <form id="baseForm" class="grid2">
      <div><label>Reparatur</label><input type="number" name="repair" required></div>
      <div><label>Wäsche</label><input type="number" name="wash" required></div>
      <div><label>Benzinkanister</label><input type="number" name="canister" required></div>
      <div><label>🚚 Anfahrt (Dispatch)</label><input type="number" name="dispatch_fee" required></div>
      <div><label>Abschleppen (innerorts)</label><input type="number" name="tow_inside" required></div>
      <div><label>Abschleppen (außerorts)</label><input type="number" name="tow_outside" required></div>
      <div><label>Öffentlicher Tuning-Aufschlag (%)</label><input type="number" step="0.1" name="tuning_markup_public" required></div>
      <div><button class="btn-primary" type="submit">Speichern</button></div>
    </form>
    <div id="baseMsg" class="msg"></div>
  </section>

  <!-- 2) PARTNERS -->
  <section class="cal-card" id="tab-partners" style="display:none">
    <h2>🏢 Vertragspartner</h2>
    <form id="partnerCreate" class="grid2">
      <div><label>Name</label><input type="text" name="name" required></div>
      <div><label>Partner-Tuning-Modifikator (%)</label><input type="number" step="0.1" name="tuning_modifier_percent" value="0"></div>
      <div style="grid-column:1/-1"><label>Bemerkungen</label><textarea name="remarks" rows="3"></textarea></div>
      <div><button class="btn-primary" type="submit">Anlegen</button></div>
    </form>
    <div id="pList" style="margin-top:12px"></div>
  </section>

  <!-- 3) CARS -->
  <section class="cal-card" id="tab-cars" style="display:none">
    <h2>🚗 Fahrzeuge verwalten</h2>
    <div class="grid2">
      <div>
        <label>Partner</label>
        <select id="carPartner"></select>
      </div>
      <div></div>
    </div>
    <form id="carCreate" class="grid2" style="margin-top:8px">
      <div><label>Fahrzeugname</label><input type="text" name="car_name" required></div>
      <div><label>Notizen</label><input type="text" name="notes"></div>
      <div><button class="btn-primary" type="submit">Fahrzeug hinzufügen</button></div>
    </form>
    <div id="carList" style="margin-top:12px"></div>
  </section>

  <!-- 4) TUNING -->
  <section class="cal-card" id="tab-tuning" style="display:none">
    <h2>🛠️ Tuning (Key/Value)</h2>
    <div class="grid2">
      <div><label>Partner</label><select id="tunPartner"></select></div>
      <div><label>Fahrzeug</label><select id="tunCar"></select></div>
    </div>
    <form id="tunCreate" class="grid2" style="margin-top:8px">
      <div><label>Teil / Feld (z. B. „Primär“)</label><input type="text" name="part" required></div>
      <div><label>Wert (z. B. „Chameleon Light Blue“)</label><input type="text" name="value" required></div>
      <div><button class="btn-primary" type="submit">Hinzufügen</button></div>
    </form>
    <div id="tunList" style="margin-top:12px"></div>
  </section>

  <!-- 5) PARTNER PRICES -->
  <section class="cal-card" id="tab-pprices" style="display:none">
    <h2>💰 Partner-Preise (Overrides)</h2>
    <div class="grid2">
      <div><label>Partner</label><select id="ppPartner"></select></div>
    </div>

    <form id="ppForm" class="grid2" style="margin-top:8px">
      <fieldset class="fieldset" style="grid-column:1/-1">
        <legend>Werkstatt</legend>
        <div class="grid2">
          <div><label>Reparatur (leer = Basis)</label><input type="number" name="repair" placeholder="leer = Basis"></div>
          <div><label>Wäsche (leer = Basis)</label><input type="number" name="wash" placeholder="leer = Basis"></div>
          <div><label>Benzinkanister (leer = Basis)</label><input type="number" name="canister" placeholder="leer = Basis"></div>
          <div><label>Abschleppen (innerorts)</label><input type="number" name="tow_inside" placeholder="leer = Basis"></div>
          <div><label>Abschleppen (außerorts)</label><input type="number" name="tow_outside" placeholder="leer = Basis"></div>
        </div>
      </fieldset>

      <fieldset class="fieldset" style="grid-column:1/-1">
        <legend>Außerhalb (Dispatch)</legend>
        <div class="grid2">
          <div><label>Reparatur (außerhalb)</label><input type="number" name="repair_out" placeholder="leer = Basis + Anfahrt"></div>
          <div><label>Wäsche (außerhalb)</label><input type="number" name="wash_out" placeholder="leer = Basis + Anfahrt"></div>
          <div><label>Benzinkanister (außerhalb)</label><input type="number" name="canister_out" placeholder="leer = Basis + Anfahrt"></div>
        </div>
      </fieldset>

      <div style="grid-column:1/-1">
        <label>Partner-Tuning-Modifikator (%)</label>
        <input type="number" step="0.1" name="tuning_modifier_percent">
      </div>

      <div><button class="btn-primary" type="submit">Speichern</button></div>
    </form>
    <div id="ppMsg" class="msg"></div>
  </section>
</main>

<script>
const API = '../includes/partner_api.php';

// Tabs
document.querySelectorAll('.tab').forEach(t=>{
  t.addEventListener('click', ()=>{
    document.querySelectorAll('.tab').forEach(x=>x.classList.remove('active'));
    t.classList.add('active');
    const id = 'tab-'+t.dataset.tab;
    document.querySelectorAll('main .cal-card').forEach(s=>s.style.display='none');
    document.getElementById(id).style.display='block';
  });
});

// 1) Base prices
async function loadBase(){
  const r = await fetch(API+'?action=get_base_prices'); const j = await r.json();
  const f = document.getElementById('baseForm');
  ['repair','wash','canister','dispatch_fee','tow_inside','tow_outside','tuning_markup_public'].forEach(k=>{
    if (f.elements[k]) f.elements[k].value = j.base?.[k] ?? '';
  });
}
document.getElementById('baseForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target); const payload = {};
  fd.forEach((v,k)=>payload[k]=v);
  const res = await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'save_base_prices',...payload})});
  const j = await res.json(); const msg = document.getElementById('baseMsg');
  if (j.ok){ msg.textContent='Gespeichert.'; msg.className='msg ok'; } else { msg.textContent=j.error||'Fehler'; msg.className='msg err'; }
});

// 2) Partners
async function renderPartners(){
  const box = document.getElementById('pList');
  const r = await fetch(API+'?action=list_partners'); const j = await r.json();
  box.innerHTML = '';
  const table = document.createElement('table'); table.className='table';
  table.innerHTML = `<thead><tr><th>Logo</th><th>Name</th><th>Tuning %</th><th>Bemerkung</th><th>Aktion</th></tr></thead>`;
  const tb = document.createElement('tbody');
  (j.items||[]).forEach(p=>{
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${p.logo_url?`<img src="${p.logo_url}" class="logo-prev">`:'—'}
          <form class="logoForm" data-id="${p.id}" enctype="multipart/form-data" style="margin-top:6px">
            <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp" required>
            <button class="btn-primary" type="submit" style="margin-top:6px">Upload</button>
          </form>
      </td>
      <td><input type="text" value="${p.name||''}" class="in-name"></td>
      <td><input type="number" step="0.1" value="${p.tuning_modifier_percent||0}" class="in-mod"></td>
      <td><input type="text" value="${p.remarks||''}" class="in-rem"></td>
      <td>
        <button class="btn-primary btn-save">Speichern</button>
        <button class="btn-ghost btn-del">Löschen</button>
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
  (j.items||[]).forEach(c=>{
    const li = document.createElement('li');
    li.innerHTML = `${c.car_name} ${c.notes?'<span class="small">– '+c.notes+'</span>':''}
                    <button class="btn-ghost" data-id="${c.id}" style="margin-left:10px">Löschen</button>`;
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
  (j.items||[]).forEach(t=>{
    const li = document.createElement('li');
    li.innerHTML = `<b>${t.part}</b>: ${t.value} <button class="btn-ghost" data-id="${t.id}" style="margin-left:10px">Löschen</button>`;
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
  ['repair','repair_out','wash','wash_out','canister','canister_out','tow_inside','tow_outside'].forEach(s=>{
    if (f.elements[s]) f.elements[s].value = j.override?.[s] ?? '';
  });
  f.elements['tuning_modifier_percent'].value = j.partner?.tuning_modifier_percent ?? 0;
}
document.getElementById('ppForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const pid = document.getElementById('ppPartner').value; if(!pid) return;
  const fd = new FormData(e.target); const payload={}; fd.forEach((v,k)=>payload[k]=v);
  payload['partner_id']=pid;
  const r = await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'save_partner_prices',...payload})});
  const j = await r.json(); const m = document.getElementById('ppMsg');
  if (j.ok){ m.textContent='Gespeichert.'; m.className='msg ok'; } else { m.textContent=j.error||'Fehler'; m.className='msg err'; }
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
</body>
</html>
