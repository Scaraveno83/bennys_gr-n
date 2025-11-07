<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once __DIR__ . '/../includes/admin_access.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/visibility.php';
?>

<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin – Kalender</title>
<link rel="stylesheet" href="../styles.css">
<link rel="stylesheet" href="../header.css">
<link rel="stylesheet" href="../calendar/calendar.css">
<style>
.page-wrap{max-width:1100px;margin:120px auto 60px;padding:0 20px;}
.grid{display:grid; gap:18px; grid-template-columns:1fr 1fr;}
@media (max-width:900px){ .grid{grid-template-columns:1fr;} }
.small{font-size:.9rem; opacity:.8;}
label.chk{display:flex; gap:10px; align-items:center; margin:6px 0;}
.area-pill{display:inline-block;margin:6px 6px 0 0; padding:6px 10px;border-radius:999px;background:#121212;border:1px solid rgba(57,255,20,.35);}
table{width:100%;border-collapse:collapse;margin-top:10px;}
th,td{padding:8px;border-bottom:1px solid rgba(57,255,20,0.25);}
th{color:#a8ffba;text-align:left;}
tr:hover{background:rgba(57,255,20,0.05);}
.msg {margin-top:8px; font-weight:bold;}
.msg.ok{color:#8fff8f;}
.msg.err{color:#76ff65;}
</style>
</head>
<body>
<?php include __DIR__ . '/../header.php'; ?>

<main class="page-wrap">
  <section class="cal-hero">
    <h1>🔧 Admin – Kalenderverwaltung</h1>
    <p>Hier verwaltest du Gründe, Sperrbereiche und kannst Status vorzeitig ändern.</p>
  </section>

  <section class="grid">

    <!-- 📚 GRÜNDE -->
    <div class="cal-card" id="panel-reasons">
      <h2>📚 Gründe verwalten</h2>
      <label class="chk small" style="margin-top:6px;">
       <input type="checkbox" id="showAllReasons"> Auch deaktivierte Gründe anzeigen
      </label>
      <form id="reasonForm" style="display:grid; gap:10px; grid-template-columns:1fr 120px 100px auto;">
        <input type="text" id="rLabel" placeholder="Grund (z. B. Urlaub)" required>
        <input type="color" id="rColor" value="#39ff14" title="Farbe">
        <input type="text" id="rIcon" value="🌴" maxlength="4" title="Icon">
        <button class="btn-primary" type="submit">Hinzufügen</button>
      </form>
      <div id="reasonMsg" class="msg"></div>
      <div id="reasonsList" class="list" style="margin-top:10px;"></div>
      <div class="small">Deaktivieren entfernt Grund aus Auswahl, löscht aber keine Historie.</div>
    </div>

    <!-- 🚫 GESPERRTE BEREICHE -->
    <div class="cal-card" id="panel-areas">
      <h2>🚫 Gesperrte Bereiche bei „Abwesend“</h2>
      <div class="small">Diese Bereiche werden für Mitarbeiter mit Status „Abwesend“ blockiert.</div>
      <form id="areasForm" style="margin-top:10px;">
        <?php
        $areas = [
          'orders'      => 'Auftragsverwaltung',
          'inventory'   => 'Lager / Teile',
          'billing'     => 'Rechnungen',
          'rp_docs'     => 'RP-Dokumente',
          'blueprints'  => 'Blueprints',
          'arcade'      => 'Arcade',
        ];
        foreach ($areas as $key => $label) {
          echo "<label class='chk'><input type='checkbox' name='areas' value='{$key}'> {$label} ({$key})</label>";
        }
        ?>
        <div style="margin-top:10px;">
          <button class="btn-primary" type="submit">Speichern</button>
        </div>
      </form>
      <div id="areasNow" style="margin-top:12px;"></div>
    </div>

    <!-- 🛠️ STATUS ÄNDERN -->
    <div class="cal-card" id="panel-override">
      <h2>🛠️ Status vorzeitig ändern</h2>
      <form id="overrideForm" style="display:grid;gap:10px;">
        <label>Mitarbeiter:</label>
        <select id="ovUser" required>
          <option value="">– Mitarbeiter auswählen –</option>
        </select>

        <label>Status:</label>
        <select id="ovStatus">
          <option value="Aktiv">Aktiv</option>
          <option value="Abwesend">Abwesend</option>
        </select>

        <label>Bis (optional, bei „Abwesend“)</label>
        <input type="datetime-local" id="ovUntil">
        <button class="btn-primary" type="submit">Übernehmen</button>
      </form>
      <div id="ovMsg" class="msg"></div>
    </div>

    <!-- 📊 ÜBERSICHT -->
<div class="cal-card" id="panel-overview">
  <h2>📊 Übersicht aller Abwesenheiten</h2>
  <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px;">
    <input type="text" id="searchAbs" placeholder="🔍 Suchen..." style="flex:1;padding:6px 10px;border-radius:8px;border:1px solid rgba(57,255,20,.4);background:#121212;color:#fff;">
    <select id="filterUser" style="padding:6px 10px;border-radius:8px;background:#121212;color:#fff;border:1px solid rgba(57,255,20,.4);">
      <option value="">👥 Alle Mitarbeiter</option>
    </select>
  </div>
  <div style="overflow-x:auto;max-height:400px;overflow-y:auto;border-radius:8px;">
    <table id="absTable" style="width:100%;border-collapse:collapse;">
      <thead style="position:sticky;top:0;background:#1a1a1a;">
        <tr>
          <th>Mitarbeiter</th>
          <th>Von</th>
          <th>Bis</th>
          <th>Gründe</th>
          <th>Notiz</th>
          <th>Erstellt</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
  <div id="pagination" style="display:flex;justify-content:center;align-items:center;margin-top:10px;gap:6px;"></div>
</div>

  </section>
</main>

<script>
const API = '../includes/calendar_api.php';

async function api(action, data=null){
  const opt = data ? {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action, ...data})}
                   : {method:'GET'};
  const url = data ? API : API + '?action=' + encodeURIComponent(action);
  const res = await fetch(url, opt);
  return await res.json();
}

// === Gründe laden ===
async function loadReasons(){
  const box = document.getElementById('reasonsList');
  const showAll = document.getElementById('showAllReasons')?.checked;
  const r = await fetch(API + '?action=reasons' + (showAll ? '&all=1' : ''));
  const data = await r.json();
  box.innerHTML = '';
  if (data.ok){
    data.reasons.forEach(x=>{
      const inactive = x.active == 0;
      const row = document.createElement('div');
      row.className = 'list-item';
      row.innerHTML = `
        <div>
          <div><span class="area-pill" style="opacity:${inactive?0.4:1}; border-color:${x.color};">${x.icon||''} ${x.label}</span></div>
          <div class="small">#${x.id} ${inactive ? '(inaktiv)' : ''}</div>
        </div>
        <div>
          ${
            inactive
              ? `<button data-id="${x.id}" class="btn-restore" style="background:#1a1a1a;border:1px solid #55ff55;color:#9bff9b;border-radius:10px;padding:8px 12px;cursor:pointer;">Aktivieren</button>`
              : `<button data-id="${x.id}" class="btn-del" style="background:#151515;border:1px solid rgba(57,255,20,.45);color:#a8ffba;border-radius:10px;padding:8px 12px;cursor:pointer;">Deaktivieren</button>`
          }
        </div>`;
      box.appendChild(row);
    });

    // deaktivieren
    box.querySelectorAll('.btn-del').forEach(b=>{
      b.addEventListener('click', async ()=>{
        const id = parseInt(b.dataset.id,10);
        if (!confirm('Diesen Grund deaktivieren?')) return;
        const r = await api('delete_reason', {id});
        if (r.ok) loadReasons();
      });
    });

    // reaktivieren
    box.querySelectorAll('.btn-restore').forEach(b=>{
      b.addEventListener('click', async ()=>{
        const id = parseInt(b.dataset.id,10);
        const r = await api('restore_reason', {id});
        if (r.ok) loadReasons();
      });
    });
  }
}

document.getElementById('showAllReasons')?.addEventListener('change', loadReasons);

// === Grund hinzufügen ===
document.getElementById('reasonForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const label = document.getElementById('rLabel').value.trim();
  const color = document.getElementById('rColor').value;
  const icon  = document.getElementById('rIcon').value.trim();
  const msg = document.getElementById('reasonMsg');

  const r = await api('add_reason', {label,color,icon});
  if (r.ok){
    msg.textContent = '✅ Grund erfolgreich hinzugefügt!';
    msg.className = 'msg ok';
    e.target.reset();
    loadReasons();
  } else {
    msg.textContent = '❌ Fehler: ' + (r.error || 'Unbekannter Fehler');
    msg.className = 'msg err';
  }
});

// === Bereiche laden ===
async function loadAreas(){
  const r = await api('settings');
  const now = document.getElementById('areasNow');
  now.innerHTML = '';
  if (r.ok){
    const list = r.restricted_areas || [];
    document.querySelectorAll('#areasForm input[name="areas"]').forEach(cb=>{
      cb.checked = list.includes(cb.value);
    });
    now.innerHTML = list.length
      ? 'Aktuell gesperrt: ' + list.map(a=>`<span class="area-pill">${a}</span>`).join(' ')
      : '<span class="small">Derzeit kein Bereich gesperrt.</span>';
  }
}

// === Mitarbeiterliste laden ===
async function loadUsers(){
  const res = await fetch(API + '?action=users');
  const data = await res.json();
  const sel = document.getElementById('ovUser');
  sel.innerHTML = '<option value="">– Mitarbeiter auswählen –</option>';
  if (data.ok && data.users.length){
    data.users.forEach(u=>{
      const opt = document.createElement('option');
      opt.value = u.id;
      opt.textContent = u.name + (u.rang ? ' (' + u.rang + ')' : '');
      sel.appendChild(opt);
    });
  }
}

// === Übersicht laden ===
let absencesData = [];
let currentPage = 1;
const perPage = 10;

async function loadOverview(){
  const res = await fetch(API + '?action=all_absences');
  const data = await res.json();
  if (data.ok){
    absencesData = data.items;
    fillUserFilter(absencesData);
    renderOverview();
  }
}

// Filteroptionen befüllen
function fillUserFilter(items){
  const select = document.getElementById('filterUser');
  const uniqueUsers = [...new Set(items.map(i => i.name))];
  select.innerHTML = '<option value="">👥 Alle Mitarbeiter</option>';
  uniqueUsers.forEach(u=>{
    const opt = document.createElement('option');
    opt.value = u;
    opt.textContent = u;
    select.appendChild(opt);
  });
}

function renderOverview(){
  const body = document.querySelector('#absTable tbody');
  const search = document.getElementById('searchAbs').value.toLowerCase();
  const userFilter = document.getElementById('filterUser').value;

  let filtered = absencesData.filter(x=>{
    const reasons = Array.isArray(x.reasons_json) ? x.reasons_json :
                    (x.reasons_json ? JSON.parse(x.reasons_json) : []);
    const text = `${x.name} ${reasons.join(', ')} ${x.note || ''}`.toLowerCase();
    return (!search || text.includes(search)) &&
           (!userFilter || x.name === userFilter);
  });

  // Pagination
  const totalPages = Math.ceil(filtered.length / perPage);
  currentPage = Math.min(currentPage, totalPages || 1);
  const start = (currentPage - 1) * perPage;
  const pageItems = filtered.slice(start, start + perPage);

  body.innerHTML = '';
  if (pageItems.length === 0){
    body.innerHTML = '<tr><td colspan="6" class="small">Keine Einträge gefunden.</td></tr>';
  } else {
    pageItems.forEach(x=>{
      const reasons = Array.isArray(x.reasons_json) ? x.reasons_json :
                      (x.reasons_json ? JSON.parse(x.reasons_json) : []);
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${x.name}</td>
        <td>${new Date(x.start_date).toLocaleString('de-DE')}</td>
        <td>${new Date(x.end_date).toLocaleString('de-DE')}</td>
        <td>${reasons.join(', ') || '-'}</td>
        <td>${x.note || '-'}</td>
        <td>${new Date(x.created_at).toLocaleString('de-DE')}</td>`;
      body.appendChild(tr);
    });
  }

  renderPagination(totalPages);
}

// Pagination UI
function renderPagination(totalPages){
  const container = document.getElementById('pagination');
  container.innerHTML = '';
  if (totalPages <= 1) return;
  for(let i=1;i<=totalPages;i++){
    const btn = document.createElement('button');
    btn.textContent = i;
    btn.style.cssText = `
      background:${i===currentPage?'#39ff14':'#1a1a1a'};
      color:#fff;border:none;padding:5px 10px;border-radius:6px;cursor:pointer;
    `;
    btn.onclick = ()=>{ currentPage=i; renderOverview(); };
    container.appendChild(btn);
  }
}

// Event-Listener
document.getElementById('searchAbs').addEventListener('input', ()=>{ currentPage=1; renderOverview(); });
document.getElementById('filterUser').addEventListener('change', ()=>{ currentPage=1; renderOverview(); });


// === Formularevents ===
document.getElementById('areasForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const areas = Array.from(document.querySelectorAll('#areasForm input[name="areas"]:checked')).map(x=>x.value);
  const r = await api('save_settings', {restricted_areas: areas});
  if (r.ok) loadAreas();
});

document.getElementById('overrideForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const user_id = parseInt(document.getElementById('ovUser').value,10);
  const status  = document.getElementById('ovStatus').value;
  const until   = document.getElementById('ovUntil').value || null;
  const msg = document.getElementById('ovMsg');
  const r = await api('set_status', {user_id, status, until});
  if (r.ok){ msg.textContent = 'Status aktualisiert.'; msg.className='msg ok'; }
  else { msg.textContent = 'Fehler: ' + (r.error||''); msg.className='msg err'; }
});

// === Initiale Ladevorgänge ===
loadReasons();
loadAreas();
loadUsers();
loadOverview();
</script>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>


<script src="../script.js"></script>
</body>
</html>
