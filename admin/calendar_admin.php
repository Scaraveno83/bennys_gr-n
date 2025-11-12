<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once __DIR__ . '/../includes/admin_access.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/visibility.php';

$calendarAreas = [
  'orders'     => 'Auftragsverwaltung',
  'inventory'  => 'Lager / Teile',
  'billing'    => 'Rechnungen',
  'rp_docs'    => 'RP-Dokumente',
  'blueprints' => 'Blueprints',
  'arcade'     => 'Arcade',
];
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
.calendar-admin-page {
  padding: 140px 20px 80px;
  display: grid;
  gap: 28px;
}

.calendar-admin-page .inventory-header {
  gap: 18px;
}

.calendar-admin-page .inventory-metrics {
  display: grid;
  gap: 16px;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.calendar-admin-page .inventory-section {
  gap: 24px;
}

.calendar-admin-form--inline {
  display: grid;
  gap: 16px;
}

@media (min-width: 900px) {
  .calendar-admin-form--inline {
    grid-template-columns: minmax(220px, 1.8fr) 140px 120px auto;
    align-items: end;
  }
}

.calendar-admin-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.calendar-admin-label {
  font-size: 0.85rem;
  color: rgba(255, 255, 255, 0.7);
  letter-spacing: 0.2px;
}

.calendar-admin-toggle {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px;
  border-radius: 14px;
  border: 1px solid rgba(57, 255, 20, 0.25);
  background: rgba(57, 255, 20, 0.08);
  color: rgba(255, 255, 255, 0.78);
  box-shadow: inset 0 0 0 1px rgba(57, 255, 20, 0.08);
  width: fit-content;
}

.calendar-admin-toggle input {
  width: 18px;
  height: 18px;
}

.calendar-admin-reasons-table tbody tr td {
  vertical-align: middle;
}

.calendar-admin-reason {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.calendar-admin-id {
  font-size: 0.85rem;
  color: rgba(255, 255, 255, 0.55);
  letter-spacing: 0.3px;
}

.calendar-admin-pill {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 8px 16px;
  border-radius: 999px;
  border: 1px solid rgba(57, 255, 20, 0.24);
  background: rgba(57, 255, 20, 0.08);
  color: rgba(255, 255, 255, 0.88);
  font-weight: 600;
  letter-spacing: 0.2px;
}

.calendar-admin-pill__swatch {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  box-shadow: 0 0 12px rgba(57, 255, 20, 0.28);
  border: 1px solid rgba(255, 255, 255, 0.15);
}

.calendar-admin-pill__icon {
  font-size: 1.2rem;
}

.calendar-admin-pill--compact {
  padding: 4px 10px;
  font-size: 0.85rem;
  font-weight: 500;
}

.calendar-admin-status {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  letter-spacing: 0.2px;
}

.calendar-admin-status::before {
  content: '';
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

.calendar-admin-status--active {
  color: rgba(118, 255, 101, 0.9);
}

.calendar-admin-status--active::before {
  background: rgba(118, 255, 101, 0.9);
  box-shadow: 0 0 12px rgba(118, 255, 101, 0.35);
}

.calendar-admin-status--inactive {
  color: rgba(255, 160, 170, 0.9);
}

.calendar-admin-status--inactive::before {
  background: rgba(255, 160, 170, 0.9);
  box-shadow: 0 0 12px rgba(255, 160, 170, 0.35);
}

.calendar-admin-color {
  display: inline-block;
  width: 32px;
  height: 32px;
  border-radius: 12px;
  border: 1px solid rgba(57, 255, 20, 0.26);
  box-shadow: 0 0 14px rgba(57, 255, 20, 0.22);
}

.calendar-admin-actions-wrapper {
  display: flex;
  justify-content: flex-end;
}

.calendar-admin-actions-heading {
  text-align: right;
}

.calendar-admin-reasons-table .calendar-admin-empty,
.calendar-admin-table .calendar-admin-empty {
  text-align: center;
  color: rgba(255, 255, 255, 0.65);
  padding: 20px 0;
}

.calendar-admin-areas {
  display: grid;
  gap: 12px;
}

@media (min-width: 640px) {
  .calendar-admin-areas {
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  }
}

.calendar-admin-check {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  padding: 14px 16px;
  border-radius: 16px;
  border: 1px solid rgba(57, 255, 20, 0.18);
  background: rgba(12, 16, 18, 0.78);
  box-shadow: inset 0 0 0 1px rgba(57, 255, 20, 0.06);
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.calendar-admin-check:hover,
.calendar-admin-check:focus-within {
  transform: translateY(-1px);
  border-color: rgba(118, 255, 101, 0.4);
  box-shadow: 0 18px 32px rgba(57, 255, 20, 0.18);
}

.calendar-admin-check input {
  margin-top: 4px;
  accent-color: var(--accent, #2ad977);
}

.calendar-admin-check span {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.calendar-admin-check strong {
  font-size: 1rem;
  color: rgba(255, 255, 255, 0.86);
}

.calendar-admin-check small {
  font-size: 0.82rem;
  color: rgba(255, 255, 255, 0.6);
  letter-spacing: 0.3px;
}

.calendar-admin-pill-group {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.calendar-admin-empty {
  color: rgba(255, 255, 255, 0.6);
  font-style: italic;
}

.calendar-admin-page .inventory-note {
  margin: 0;
}

.calendar-admin-pagination {
  display: flex;
  justify-content: center;
  gap: 12px;
  flex-wrap: wrap;
}

.calendar-admin-page__page-btn {
  border-radius: 999px;
  padding: 8px 16px;
  cursor: pointer;
  font-weight: 600;
  letter-spacing: 0.2px;
  transition: var(--transition);
}

.calendar-admin-page .inventory-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  align-items: center;
  justify-content: space-between;
}

.calendar-admin-page .inventory-filters__group {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  align-items: center;
  flex: 1;
}

.calendar-admin-page .inventory-filters__group > * {
  flex: none;
}

.calendar-admin-page .inventory-filters__group .search-field {
  flex: 1;
}

.calendar-admin-page .inventory-filters__group .search-field input[type="search"] {
  width: 100%;
}

.calendar-admin-page .inventory-form input,
.calendar-admin-page .inventory-form select,
.calendar-admin-page .inventory-form textarea {
  width: 100%;
}

.calendar-admin-actions {
  display: flex;
  justify-content: flex-end;
}

.calendar-admin-metric-hint {
  display: block;
}
</style>
</head>
<body>
<?php include __DIR__ . '/../header.php'; ?>

<main class="inventory-page calendar-admin-page">
  <header class="inventory-header">
    <h1 class="inventory-title">🔧 Admin – Kalenderverwaltung</h1>
    <p class="inventory-description">
      Gründe, Sperrbereiche und Abwesenheiten zentral im Blick behalten – passend zu den Lageransichten.
    </p>
    <div class="inventory-metrics calendar-admin-metrics">
      <article class="inventory-metric">
        <span class="inventory-metric__label">Aktive Gründe</span>
        <span class="inventory-metric__value" id="metricReasons">0</span>
        <span class="inventory-metric__hint" id="metricReasonsHint">Kalendergründe verfügbar</span>
      </article>
      <article class="inventory-metric">
        <span class="inventory-metric__label">Gesperrte Bereiche</span>
        <span class="inventory-metric__value" id="metricAreas">0</span>
        <span class="inventory-metric__hint" id="metricAreasHint">Bereiche aktuell blockiert</span>
      </article>
      <article class="inventory-metric">
        <span class="inventory-metric__label">Abwesenheiten</span>
        <span class="inventory-metric__value" id="metricAbsences">0</span>
        <span class="inventory-metric__hint" id="metricAbsencesHint">Einträge insgesamt</span>
      </article>
    </div>
  </header>

  <section class="inventory-section" id="panel-reasons">
    <div class="inventory-section__intro">
      <h2>📚 Gründe verwalten</h2>
      <p>Neue Gründe anlegen, deaktivieren oder wieder aktivieren – ganz wie bei den Lagerkarten.</p>
    </div>

    <label class="calendar-admin-toggle">
      <input type="checkbox" id="showAllReasons"> <span>Auch deaktivierte Gründe anzeigen</span>
    </label>

    <form id="reasonForm" class="inventory-form calendar-admin-form--inline">
      <div class="calendar-admin-field">
        <label for="rLabel" class="calendar-admin-label">Grund</label>
        <input type="text" id="rLabel" placeholder="z. B. Urlaub" required>
      </div>
      <div class="calendar-admin-field">
        <label for="rColor" class="calendar-admin-label">Farbe</label>
        <input type="color" id="rColor" value="#2ad977" title="Farbe">
      </div>
      <div class="calendar-admin-field">
        <label for="rIcon" class="calendar-admin-label">Icon</label>
        <input type="text" id="rIcon" value="🌴" maxlength="4" title="Icon">
      </div>
      <div class="calendar-admin-actions">
        <button class="inventory-submit" type="submit">➕ Hinzufügen</button>
      </div>
    </form>

    <p id="reasonMsg" class="inventory-alert" hidden></p>

    <div class="table-wrap">
      <table class="data-table calendar-admin-reasons-table">
        <thead>
          <tr>
            <th>Grund</th>
            <th>Darstellung</th>
            <th>Status</th>
            <th class="calendar-admin-actions-heading">Aktion</th>
          </tr>
        </thead>
        <tbody id="reasonsBody"></tbody>
      </table>
    </div>

    <p class="calendar-admin-metric-hint">Deaktivieren blendet Gründe in der Auswahl aus, ohne bestehende Einträge zu löschen.</p>
  </section>

  <section class="inventory-section" id="panel-areas">
    <div class="inventory-section__intro">
      <h2>🚫 Gesperrte Bereiche bei „Abwesend“</h2>
      <p>Welche Bereiche sollen gesperrt werden, wenn ein Mitarbeiter als abwesend markiert ist?</p>
    </div>

    <form id="areasForm" class="inventory-form calendar-admin-areas-form">
      <div class="calendar-admin-areas">
        <?php foreach ($calendarAreas as $key => $label): ?>
          <label class="calendar-admin-check">
            <input type="checkbox" name="areas" value="<?= htmlspecialchars($key) ?>">
            <span>
              <strong><?= htmlspecialchars($label) ?></strong>
              <small><?= htmlspecialchars($key) ?></small>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="calendar-admin-actions">
        <button class="inventory-submit inventory-submit--small" type="submit">💾 Speichern</button>
      </div>
    </form>

    <div id="areasNow" class="calendar-admin-pill-group"></div>
  </section>

  <section class="inventory-section" id="panel-override">
    <div class="inventory-section__intro">
      <h2>🛠️ Status vorzeitig ändern</h2>
      <p>Setze den Status einzelner Mitarbeiter direkt – analog zu schnellen Lagerbuchungen.</p>
    </div>

    <form id="overrideForm" class="inventory-form">
      <div class="calendar-admin-field">
        <label for="ovUser" class="calendar-admin-label">Mitarbeiter</label>
        <select id="ovUser" required>
          <option value="">– Mitarbeiter auswählen –</option>
        </select>
      </div>

      <div class="calendar-admin-field">
        <label for="ovStatus" class="calendar-admin-label">Status</label>
        <select id="ovStatus">
          <option value="Aktiv">Aktiv</option>
          <option value="Abwesend">Abwesend</option>
        </select>
      </div>

      <div class="calendar-admin-field">
        <label for="ovUntil" class="calendar-admin-label">Bis (optional)</label>
        <input type="datetime-local" id="ovUntil">
      </div>

      <div class="calendar-admin-actions">
        <button class="inventory-submit" type="submit">✅ Übernehmen</button>
      </div>
    </form>

    <p id="ovMsg" class="inventory-alert" hidden></p>
  </section>

  <section class="inventory-section" id="panel-overview">
    <div class="inventory-section__intro">
      <h2>📊 Übersicht aller Abwesenheiten</h2>
      <p>Filtere und analysiere alle Einträge – visuell passend zu den Lagerübersichten.</p>
    </div>

    <div class="inventory-filters">
      <div class="inventory-filters__group">
        <span class="search-field">
          <input type="search" id="searchAbs" placeholder="🔍 Suchen...">
        </span>
        <select id="filterUser" class="inventory-select">
          <option value="">👥 Alle Mitarbeiter</option>
        </select>
      </div>
    </div>

    <div class="table-wrap">
      <table id="absTable" class="data-table calendar-admin-table">
        <thead>
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

    <div id="pagination" class="calendar-admin-pagination"></div>
  </section>
</main>

<script>
const API = '../includes/calendar_api.php';
const AREA_LABELS = <?= json_encode($calendarAreas, JSON_UNESCAPED_UNICODE) ?>;

async function api(action, data = null) {
  const opt = data
    ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action, ...data }) }
    : { method: 'GET' };
  const url = data ? API : API + '?action=' + encodeURIComponent(action);
  const res = await fetch(url, opt);
  return await res.json();
}

async function loadReasons() {
  const body = document.getElementById('reasonsBody');
  const showAll = document.getElementById('showAllReasons')?.checked;
  const response = await fetch(API + '?action=reasons' + (showAll ? '&all=1' : ''));
  const data = await response.json();

  body.innerHTML = '';

  if (data.ok) {
    let activeCount = 0;

    if (!data.reasons.length) {
      const row = document.createElement('tr');
      const cell = document.createElement('td');
      cell.colSpan = 4;
      cell.className = 'calendar-admin-empty';
      cell.textContent = 'Keine Gründe vorhanden.';
      row.appendChild(cell);
      body.appendChild(row);
    } else {
      data.reasons.forEach(reason => {
        const inactive = Number(reason.active) === 0;
        if (!inactive) {
          activeCount += 1;
        }

        const tr = document.createElement('tr');

        const reasonCell = document.createElement('td');
        const reasonWrap = document.createElement('div');
        reasonWrap.className = 'calendar-admin-reason';

        const pill = document.createElement('span');
        pill.className = 'calendar-admin-pill';

        const swatch = document.createElement('span');
        swatch.className = 'calendar-admin-pill__swatch';
        swatch.style.background = reason.color || '#2ad977';
        pill.appendChild(swatch);

        if (reason.icon) {
          const icon = document.createElement('span');
          icon.className = 'calendar-admin-pill__icon';
          icon.textContent = reason.icon;
          pill.appendChild(icon);
        }

        const label = document.createElement('span');
        label.textContent = reason.label;
        pill.appendChild(label);

        reasonWrap.appendChild(pill);

        const id = document.createElement('span');
        id.className = 'calendar-admin-id';
        id.textContent = '#' + reason.id + (inactive ? ' · Inaktiv' : '');
        reasonWrap.appendChild(id);

        reasonCell.appendChild(reasonWrap);
        tr.appendChild(reasonCell);

        const colorCell = document.createElement('td');
        const colorSwatch = document.createElement('span');
        colorSwatch.className = 'calendar-admin-color';
        colorSwatch.style.background = reason.color || '#2ad977';
        colorCell.appendChild(colorSwatch);
        tr.appendChild(colorCell);

        const statusCell = document.createElement('td');
        const status = document.createElement('span');
        status.className = 'calendar-admin-status ' + (inactive ? 'calendar-admin-status--inactive' : 'calendar-admin-status--active');
        status.textContent = inactive ? 'Inaktiv' : 'Aktiv';
        statusCell.appendChild(status);
        tr.appendChild(statusCell);

        const actionCell = document.createElement('td');
        actionCell.className = 'calendar-admin-actions';
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.dataset.id = reason.id;

        if (inactive) {
          btn.className = 'inventory-submit inventory-submit--small';
          btn.textContent = 'Aktivieren';
          btn.dataset.action = 'restore';
        } else {
          btn.className = 'inventory-submit inventory-submit--small inventory-submit--ghost inventory-submit--danger';
          btn.textContent = 'Deaktivieren';
          btn.dataset.action = 'deactivate';
        }

        actionCell.appendChild(btn);
        tr.appendChild(actionCell);
        body.appendChild(tr);
      });
    }

    if (data.reasons.length && !showAll) {
      activeCount = data.reasons.length;
    }

    document.getElementById('metricReasons').textContent = String(activeCount);
    document.getElementById('metricReasonsHint').textContent = data.reasons.length
      ? 'Gesamt: ' + data.reasons.length + (showAll ? ' (inkl. inaktive)' : ' aktive Gründe')
      : 'Keine Gründe angelegt';

    body.querySelectorAll('button[data-action="deactivate"]').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = parseInt(btn.dataset.id, 10);
        if (!confirm('Diesen Grund deaktivieren?')) return;
        const result = await api('delete_reason', { id });
        if (result.ok) loadReasons();
      });
    });

    body.querySelectorAll('button[data-action="restore"]').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = parseInt(btn.dataset.id, 10);
        const result = await api('restore_reason', { id });
        if (result.ok) loadReasons();
      });
    });
  } else {
    const row = document.createElement('tr');
    const cell = document.createElement('td');
    cell.colSpan = 4;
    cell.className = 'calendar-admin-empty';
    cell.textContent = 'Fehler beim Laden der Gründe.';
    row.appendChild(cell);
    body.appendChild(row);
  }
}

document.getElementById('showAllReasons')?.addEventListener('change', loadReasons);

document.getElementById('reasonForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const label = document.getElementById('rLabel').value.trim();
  const color = document.getElementById('rColor').value;
  const icon = document.getElementById('rIcon').value.trim();
  const msg = document.getElementById('reasonMsg');

  msg.hidden = true;

  const result = await api('add_reason', { label, color, icon });
  if (result.ok) {
    msg.textContent = 'Grund erfolgreich hinzugefügt!';
    msg.className = 'inventory-alert inventory-alert--success';
    msg.hidden = false;
    e.target.reset();
    document.getElementById('rColor').value = '#2ad977';
    loadReasons();
  } else {
    msg.textContent = 'Fehler: ' + (result.error || 'Unbekannter Fehler');
    msg.className = 'inventory-alert inventory-alert--error';
    msg.hidden = false;
  }
});

async function loadAreas() {
  const response = await api('settings');
  const now = document.getElementById('areasNow');
  now.innerHTML = '';

  if (response.ok) {
    const list = response.restricted_areas || [];
    document.querySelectorAll('#areasForm input[name="areas"]').forEach(cb => {
      cb.checked = list.includes(cb.value);
    });

    document.getElementById('metricAreas').textContent = String(list.length);
    document.getElementById('metricAreasHint').textContent = list.length ? 'Bereiche aktuell blockiert' : 'Keine Sperren aktiv';

    if (!list.length) {
      const empty = document.createElement('span');
      empty.className = 'calendar-admin-empty';
      empty.textContent = 'Derzeit kein Bereich gesperrt.';
      now.appendChild(empty);
    } else {
      list.forEach(key => {
        const pill = document.createElement('span');
        pill.className = 'calendar-admin-pill calendar-admin-pill--compact';
        const icon = document.createElement('span');
        icon.className = 'calendar-admin-pill__icon';
        icon.textContent = '🚫';
        const label = document.createElement('span');
        label.textContent = AREA_LABELS[key] || key;
        pill.appendChild(icon);
        pill.appendChild(label);
        now.appendChild(pill);
      });
    }
  }
}

async function loadUsers() {
  const res = await fetch(API + '?action=users');
  const data = await res.json();
  const select = document.getElementById('ovUser');
  select.innerHTML = '<option value="">– Mitarbeiter auswählen –</option>';
  if (data.ok && data.users.length) {
    data.users.forEach(user => {
      const opt = document.createElement('option');
      opt.value = user.id;
      opt.textContent = user.name + (user.rang ? ' (' + user.rang + ')' : '');
      select.appendChild(opt);
    });
  }
}

let absencesData = [];
let currentPage = 1;
const perPage = 10;

async function loadOverview() {
  const res = await fetch(API + '?action=all_absences');
  const data = await res.json();
  if (data.ok) {
    absencesData = data.items;
    document.getElementById('metricAbsences').textContent = String(absencesData.length);
    document.getElementById('metricAbsencesHint').textContent = absencesData.length ? 'Einträge insgesamt' : 'Keine Abwesenheiten erfasst';
    fillUserFilter(absencesData);
    renderOverview();
  }
}

function fillUserFilter(items) {
  const select = document.getElementById('filterUser');
  const uniqueUsers = [...new Set(items.map(item => item.name))].sort((a, b) => a.localeCompare(b, 'de'));
  select.innerHTML = '<option value="">👥 Alle Mitarbeiter</option>';
  uniqueUsers.forEach(name => {
    const opt = document.createElement('option');
    opt.value = name;
    opt.textContent = name;
    select.appendChild(opt);
  });
}

function renderOverview() {
  const body = document.querySelector('#absTable tbody');
  const search = document.getElementById('searchAbs').value.toLowerCase();
  const userFilter = document.getElementById('filterUser').value;

  let filtered = absencesData.filter(item => {
    const reasons = Array.isArray(item.reasons_json) ? item.reasons_json : (item.reasons_json ? JSON.parse(item.reasons_json) : []);
    const text = `${item.name} ${reasons.join(', ')} ${item.note || ''}`.toLowerCase();
    return (!search || text.includes(search)) && (!userFilter || item.name === userFilter);
  });

  const totalPages = Math.ceil(filtered.length / perPage) || 1;
  currentPage = Math.min(currentPage, totalPages);
  const start = (currentPage - 1) * perPage;
  const pageItems = filtered.slice(start, start + perPage);

  body.innerHTML = '';

  if (!pageItems.length) {
    const row = document.createElement('tr');
    const cell = document.createElement('td');
    cell.colSpan = 6;
    cell.className = 'calendar-admin-empty';
    cell.textContent = 'Keine Einträge gefunden.';
    row.appendChild(cell);
    body.appendChild(row);
  } else {
    pageItems.forEach(item => {
      const reasons = Array.isArray(item.reasons_json) ? item.reasons_json : (item.reasons_json ? JSON.parse(item.reasons_json) : []);
      const tr = document.createElement('tr');

      const nameCell = document.createElement('td');
      nameCell.textContent = item.name;
      tr.appendChild(nameCell);

      const fromCell = document.createElement('td');
      fromCell.textContent = new Date(item.start_date).toLocaleString('de-DE');
      tr.appendChild(fromCell);

      const toCell = document.createElement('td');
      toCell.textContent = new Date(item.end_date).toLocaleString('de-DE');
      tr.appendChild(toCell);

      const reasonsCell = document.createElement('td');
      if (reasons.length) {
        const group = document.createElement('div');
        group.className = 'calendar-admin-pill-group';
        reasons.forEach(reason => {
          const pill = document.createElement('span');
          pill.className = 'calendar-admin-pill calendar-admin-pill--compact';
          pill.textContent = reason;
          group.appendChild(pill);
        });
        reasonsCell.appendChild(group);
      } else {
        const empty = document.createElement('span');
        empty.className = 'calendar-admin-empty';
        empty.textContent = '–';
        reasonsCell.appendChild(empty);
      }
      tr.appendChild(reasonsCell);

      const noteCell = document.createElement('td');
      noteCell.textContent = item.note || '–';
      tr.appendChild(noteCell);

      const createdCell = document.createElement('td');
      createdCell.textContent = new Date(item.created_at).toLocaleString('de-DE');
      tr.appendChild(createdCell);

      body.appendChild(tr);
    });
  }

  renderPagination(totalPages);
}

function renderPagination(totalPages) {
  const container = document.getElementById('pagination');
  container.innerHTML = '';
  if (totalPages <= 1) return;

  for (let i = 1; i <= totalPages; i += 1) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = i;
    btn.className = 'calendar-admin-page__page-btn' + (i === currentPage ? ' is-active' : '');
    btn.addEventListener('click', () => {
      currentPage = i;
      renderOverview();
    });
    container.appendChild(btn);
  }
}

document.getElementById('searchAbs').addEventListener('input', () => {
  currentPage = 1;
  renderOverview();
});

document.getElementById('filterUser').addEventListener('change', () => {
  currentPage = 1;
  renderOverview();
});

document.getElementById('areasForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const areas = Array.from(document.querySelectorAll('#areasForm input[name="areas"]:checked')).map(cb => cb.value);
  const result = await api('save_settings', { restricted_areas: areas });
  if (result.ok) {
    loadAreas();
  }
});

document.getElementById('overrideForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const user_id = parseInt(document.getElementById('ovUser').value, 10);
  const status = document.getElementById('ovStatus').value;
  const until = document.getElementById('ovUntil').value || null;
  const msg = document.getElementById('ovMsg');

  msg.hidden = true;

  const result = await api('set_status', { user_id, status, until });
  if (result.ok) {
    msg.textContent = 'Status aktualisiert.';
    msg.className = 'inventory-alert inventory-alert--success';
    msg.hidden = false;
    loadOverview();
  } else {
    msg.textContent = 'Fehler: ' + (result.error || '');
    msg.className = 'inventory-alert inventory-alert--error';
    msg.hidden = false;
  }
});

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