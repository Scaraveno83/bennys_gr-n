<?php
// /bennys/calendar/calendar.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';

?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Benny’s – Kalender</title>
<link rel="stylesheet" href="../styles.css">
<link rel="stylesheet" href="../header.css">
<link rel="stylesheet" href="calendar.css">
<style>
  .reason-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 6px;
}
.reason-list label {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  background: #121212;
  border: 1px solid rgba(57,255,20,.35);
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.2s;
}
.reason-list label:hover {
  background: rgba(57,255,20,.1);
}
.reason-list input[type="checkbox"] {
  accent-color: #39ff14;
}

/* Container-Breite */
.page-wrap{max-width:1100px;margin:120px auto 60px;padding:0 20px;}
</style>
</head>
<?php
$basePath = "../";  // Muss VOR include stehen
include __DIR__ . '/../header.php';
?>
<body>



<main class="page-wrap">
  <section class="cal-hero">
    <h1>🗓️ Abwesenheiten</h1>
    <p>Trage deine Abwesenheiten ein. Während „Abwesend“ sind bestimmte Bereiche gesperrt.</p>
    <div id="statusBadge" class="status-badge">Lade Status…</div>
  </section>

  <section class="cal-grid">
    <div class="cal-card">
      <h2>➕ Abwesenheit eintragen</h2>
      <form id="absenceForm">
        <div class="field">
          <label>Von</label>
          <input type="datetime-local" name="start_date" required>
        </div>
        <div class="field">
          <label>Bis</label>
          <input type="datetime-local" name="end_date" required>
        </div>
        <div class="field">
          <label>Gründe:</label>
          <div id="reasonCheckboxes" class="reason-list"></div>
          <small>Mehrere Gründe einfach anklicken</small>
        </div>
        <div class="field">
          <label>Notiz (optional)</label>
          <input type="text" name="note" placeholder="Kurzinfo…">
        </div>
        <button class="btn-primary" type="submit">Speichern</button>
      </form>
      <div id="formMsg" class="msg"></div>
    </div>

    <div class="cal-card">
      <h2>📋 Meine Abwesenheiten</h2>
      <div id="myList" class="list"></div>
    </div>
  </section>
</main>

<script>
const API = '../includes/calendar_api.php';

async function api(action, data=null) {
  const opt = data ? {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action, ...data})}
                   : {method:'GET'};
  const url = data ? API : API + '?action=' + encodeURIComponent(action);
  const res = await fetch(url, opt);
  return await res.json();
}

function badge(status, until) {
  if (status === 'Abwesend') {
    const t = until ? new Date(until).toLocaleString('de-DE') : 'unbekannt';
    return `<span class="badge badge-away">Abwesend</span> bis ${t}`;
  }
  return `<span class="badge badge-ok">Aktiv</span>`;
}

async function loadStatus() {
  const r = await api('status');
  const el = document.getElementById('statusBadge');
  if (r.ok) el.innerHTML = badge(r.status, r.until);
  else el.textContent = 'Fehler beim Laden des Status';
}

async function loadReasons() {
  const box = document.getElementById('reasonCheckboxes');
  box.innerHTML = '<div style="opacity:.7;">Lade Gründe…</div>';
  const r = await api('reasons');
  box.innerHTML = '';
  if (r.ok && r.reasons.length) {
    r.reasons.forEach(x=>{
      const label = document.createElement('label');
      label.innerHTML = `
        <input type="checkbox" name="reasons" value="${x.id}">
        <span style="color:${x.color};">${x.icon || ''} ${x.label}</span>
      `;
      box.appendChild(label);
    });
  } else {
    box.innerHTML = '<div style="color:#a8ffba;">⚠️ Keine Gründe gefunden.</div>';
  }
}

async function loadMyAbsences() {
  const r = await api('my_absences');
  const box = document.getElementById('myList');
  box.innerHTML = '';
  if (r.ok && r.items.length) {
    r.items.forEach(x=>{
      const li = document.createElement('div');
      li.className = 'list-item';
      const reasons = Array.isArray(x.reasons_json) ? x.reasons_json : (x.reasons_json ? JSON.parse(x.reasons_json) : []);
      li.innerHTML = `
        <div>
          <div class="li-dates">Von <b>${new Date(x.start_date).toLocaleString('de-DE')}</b> bis <b>${new Date(x.end_date).toLocaleString('de-DE')}</b></div>
          <div class="li-reasons">Gründe: ${reasons.join(', ') || '-'}</div>
          ${x.note ? `<div class="li-note">Notiz: ${x.note}</div>`:''}
        </div>
        <div class="li-meta">eingetragen am ${new Date(x.created_at).toLocaleString('de-DE')}</div>
      `;
      box.appendChild(li);
    });
  } else {
    box.innerHTML = '<div class="empty">Keine Einträge vorhanden.</div>';
  }
}

document.getElementById('absenceForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  const reasons = Array.from(document.querySelectorAll('#reasonCheckboxes input:checked')).map(c=>parseInt(c.value,10));
  const payload = {
    start_date: fd.get('start_date'),
    end_date:   fd.get('end_date'),
    reasons:    reasons,
    note:       fd.get('note') || null
  };
  const r = await api('add_absence', payload);
  const msg = document.getElementById('formMsg');
  if (r.ok) {
    msg.textContent = 'Gespeichert. Status nun: Abwesend.';
    msg.className = 'msg ok';
    e.target.reset();
    loadStatus();
    loadMyAbsences();
  } else {
    msg.textContent = 'Fehler: ' + (r.error || 'unbekannt');
    msg.className = 'msg err';
  }
});

loadStatus();
loadReasons();
loadMyAbsences();
</script>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="../script.js"></script>
</body>
</html>
