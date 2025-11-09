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
</head>
<?php
$basePath = "../";  // Muss VOR include stehen
include __DIR__ . '/../header.php';
?>
<body>



<main class="calendar-app">
  <section class="calendar-columns">
    <div class="calendar-column calendar-column--narrow">
      <article class="calendar-card calendar-card--status">
        <header class="card-head">
          <h1 class="card-title">🗓️ Abwesenheiten</h1>
          <p class="card-subtitle">Behalte deinen Status im Blick und plane kompakt.</p>
        </header>
        <div class="status-widget">
          <span class="status-label">Aktueller Status</span>
          <div id="statusBadge" class="status-badge">Lade Status…</div>
          <p class="status-hint">Während „Abwesend“ können sensible Bereiche gesperrt sein.</p>
        </div>
      </article>

      <article class="calendar-card calendar-card--form">
        <header class="card-head">
          <h2 class="card-title">➕ Neue Abwesenheit</h2>
          <p class="card-subtitle">Start- und Endzeit eingeben, Gründe wählen, fertig.</p>
        </header>
        <form id="absenceForm" class="calendar-form">
          <div class="form-grid">
            <label class="form-field" for="absence-start">
              <span class="form-label">Von</span>
              <input id="absence-start" type="datetime-local" name="start_date" required>
            </label>
            <label class="form-field" for="absence-end">
              <span class="form-label">Bis</span>
              <input id="absence-end" type="datetime-local" name="end_date" required>
            </label>
          </div>
          <div class="form-field">
            <span class="form-label">Gründe</span>
            <div id="reasonCheckboxes" class="reason-grid"></div>
            <small>Mehrere Gründe lassen sich kombinieren.</small>
          </div>
          <label class="form-field" for="absence-note">
            <span class="form-label">Notiz (optional)</span>
            <input id="absence-note" type="text" name="note" placeholder="Kurzinfo…">
          </label>
          <div class="form-actions">
            <button class="btn-primary" type="submit">Speichern</button>
            <div id="formMsg" class="msg" aria-live="polite"></div>
          </div>
        </form>
      </article>
    </div>

    <div class="calendar-column calendar-column--wide">
      <article class="calendar-card calendar-card--list">
        <header class="card-head">
          <h2 class="card-title">📋 Meine Abwesenheiten</h2>
          <p class="card-subtitle">Sortiert nach Startdatum, aktuellste zuerst.</p>
        </header>
        <div id="myList" class="calendar-timeline"></div>
      </article>
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
  box.innerHTML = '<div class="loader">Lade Gründe…</div>';
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
    box.innerHTML = '<div class="empty">⚠️ Keine Gründe gefunden.</div>';
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
