<?php
// --- DEBUG MODUS ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Zugriffskontrolle
require_once '../includes/admin_access.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin-Dashboard | Benny's Werkstatt</title>

<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../header.css">
<link rel="stylesheet" href="../styles.css">

<style>
:root {
  --surface: #101315;
  --surface-raised: #15191d;
  --surface-strong: #1d2328;
  --border: rgba(57, 255, 20, 0.25);
  --border-soft: rgba(57, 255, 20, 0.15);
  --text: #f5f5f5;
  --text-muted: #a0a7ad;
  --accent: #39ff14;
  --accent-soft: rgba(57, 255, 20, 0.12);
  --radius-lg: 18px;
  --radius-md: 14px;
  --transition: all 0.25s ease;
}

body {
  background: radial-gradient(circle at top, rgba(57,255,20,0.08), transparent 55%),
              radial-gradient(circle at bottom, rgba(57,255,20,0.05), transparent 55%),
              #050607;
  color: var(--text);
  font-family: 'Roboto', sans-serif;
  min-height: 100vh;
}

main {
  width: min(1180px, calc(100% - 32px));
  margin: 120px auto 72px;
  display: grid;
  gap: 40px;
}

.dashboard-header {
  background: linear-gradient(135deg, rgba(57, 255, 20, 0.08), rgba(57, 255, 20, 0.02));
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 32px clamp(24px, 4vw, 40px);
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.45);
  display: grid;
  gap: 28px;
}

.dashboard-header h1 {
  margin: 0;
  font-size: clamp(2rem, 3vw, 2.6rem);
  letter-spacing: 0.6px;
}

.dashboard-header p {
  margin: 0;
  max-width: 640px;
  color: var(--text-muted);
}

.quick-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.quick-actions a {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 12px 18px;
  background: var(--surface-raised);
  border: 1px solid var(--border-soft);
  border-radius: 999px;
  color: var(--text);
  text-decoration: none;
  font-weight: 500;
  transition: var(--transition);
  box-shadow: inset 0 0 0 0 rgba(57, 255, 20, 0.35);
}

.quick-actions a span {
  font-size: 1.1rem;
}

.quick-actions a:hover,
.quick-actions a:focus-visible {
  border-color: var(--border);
  box-shadow: inset 0 0 12px rgba(57, 255, 20, 0.35);
  transform: translateY(-1px);
}

.section {
  display: grid;
  gap: 24px;
}

.section header {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 12px;
  align-items: baseline;
  padding: 0 4px;
}

.section h2 {
  margin: 0;
  font-size: 1.6rem;
  letter-spacing: 0.4px;
  display: inline-flex;
  align-items: center;
  gap: 10px;
}

.section header span {
  color: var(--text-muted);
  font-size: 0.95rem;
}

.card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
  gap: 18px;
}

.card {
  position: relative;
  background: var(--surface-raised);
  border: 1px solid var(--border-soft);
  border-radius: var(--radius-md);
  padding: 22px;
  display: grid;
  gap: 14px;
  min-height: 170px;
  transition: var(--transition);
  box-shadow: 0 10px 26px rgba(0, 0, 0, 0.28);
}

.card::before {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: inherit;
  background: linear-gradient(135deg, var(--accent-soft), transparent 65%);
  opacity: 0;
  transition: var(--transition);
  pointer-events: none;
}

.card:hover,
.card:focus-within {
  border-color: var(--border);
  transform: translateY(-4px);
}

.card:hover::before,
.card:focus-within::before {
  opacity: 1;
}

.card .icon {
  font-size: 1.4rem;
  width: 36px;
  height: 36px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: rgba(57, 255, 20, 0.16);
  color: var(--accent);
}

.card h3 {
  margin: 0;
  font-size: 1.1rem;
}

.card p {
  margin: 0;
  color: var(--text-muted);
  font-size: 0.95rem;
  line-height: 1.5;
}

.card a {
  justify-self: start;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 14px;
  border-radius: 999px;
  border: 1px solid transparent;
  color: var(--accent);
  font-weight: 500;
  transition: var(--transition);
}

.card a:hover,
.card a:focus-visible {
  border-color: var(--accent);
  background: var(--accent-soft);
}

.logout {
  justify-self: center;
  padding-top: 12px;
}

.logout a {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 26px;
  border-radius: 999px;
  border: 1px solid var(--border);
  color: var(--text);
  font-weight: 600;
  transition: var(--transition);
}

.logout a:hover,
.logout a:focus-visible {
  background: var(--accent-soft);
  color: var(--accent);
  transform: translateY(-2px);
}

@media (max-width: 720px) {
  main {
    margin-top: 104px;
    gap: 32px;
  }

  .dashboard-header {
    padding: 26px 22px;
  }

  .card {
    padding: 20px;
  }
}
</style>
</head>

<body>
<?php include '../header.php'; ?>

<main>
  <section class="dashboard-header">
    <div>
      <h1>Admin-Dashboard</h1>
      <p>Hallo <strong><?= htmlspecialchars($_SESSION['admin_username'] ?? $_SESSION['mitarbeiter_name'] ?? 'Admin'); ?></strong>, hier behältst du Inhalte, Teams und Lager im Blick.</p>
    </div>
    <nav class="quick-actions" aria-label="Schnellzugriffe">
      <a href="news_manage.php"><span>📰</span>News erstellen</a>
      <a href="manage_messages.php"><span>💬</span>Nachrichten prüfen</a>
      <a href="calendar_admin.php"><span>🗓️</span>Kalender öffnen</a>
      <a href="hauptlager_edit.php"><span>🏭</span>Lager anpassen</a>
    </nav>
  </section>

  <section class="section">
    <header>
      <h2>News &amp; Kommunikation</h2>
      <span>Beiträge &amp; Austausch</span>
    </header>
    <div class="card-grid">
      <article class="card">
        <span class="icon">🗞️</span>
        <h3>News &amp; Ankündigungen</h3>
        <p>Öffentliche und interne Meldungen verfassen oder anpassen.</p>
        <a href="news_manage.php">Verwalten</a>
      </article>
      <article class="card">
        <span class="icon">💬</span>
        <h3>Nachrichtenverwaltung</h3>
        <p>Private Nachrichten einsehen, beantworten oder entfernen.</p>
        <a href="manage_messages.php">Verwalten</a>
      </article>
      <article class="card">
        <span class="icon">📝</span>
        <h3>Feedback</h3>
        <p>Rückmeldungen sammeln und Entscheidungen nachhalten.</p>
        <a href="manage_feedback.php">Verwalten</a>
      </article>
      <article class="card">
        <span class="icon">🧑‍💻</span>
        <h3>Forum</h3>
        <p>Threads moderieren und Themen nach Priorität ordnen.</p>
        <a href="forum_admin.php">Verwalten</a>
      </article>
      <article class="card">
        <span class="icon">🤝</span>
        <h3>Preise &amp; Partner</h3>
        <p>Preislisten und Vertragspartner gepflegt und aktuell halten.</p>
        <a href="partner_admin.php">Verwalten</a>
      </article>
    </div>
  </section>

  <section class="section">
    <header>
      <h2>Mitarbeiter &amp; Organisation</h2>
      <span>Team &amp; Planung</span>
    </header>
    <div class="card-grid">
      <article class="card">
        <span class="icon">🧑‍🔧</span>
        <h3>Mitarbeiter</h3>
        <p>Profile, Ränge und Beschreibungen deiner Crew verwalten.</p>
        <a href="edit_mitarbeiter.php">Verwalten</a>
      </article>
      <article class="card">
        <span class="icon">🗓️</span>
        <h3>Kalender</h3>
        <p>Termine koordinieren, Schichten planen und Deadlines sichern.</p>
        <a href="calendar_admin.php">Verwalten</a>
      </article>
      <?php if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
      <article class="card">
        <span class="icon">🔐</span>
        <h3>Useraccounts</h3>
        <p>Zugänge verwalten, Berechtigungen vergeben und sichern.</p>
        <a href="user_accounts_edit.php">Verwalten</a>
      </article>
      <?php endif; ?>
      <article class="card">
        <span class="icon">✅</span>
        <h3>Wochenaufgaben</h3>
        <p>Aufgabenplanung aktualisieren und Fortschritt tracken.</p>
        <a href="wochenaufgaben_edit.php">Verwalten</a>
      </article>
      <article class="card">
        <span class="icon">🚗</span>
        <h3>Fahrzeuge</h3>
        <p>Fuhrpark organisieren, neue Fahrzeuge anlegen oder ändern.</p>
        <a href="fahrzeuge_edit.php">Verwalten</a>
      </article>
    </div>
  </section>

  <section class="section">
    <header>
      <h2>Lagerverwaltung</h2>
      <span>Bestände im Überblick</span>
    </header>
    <div class="card-grid">
      <article class="card">
        <span class="icon">🏭</span>
        <h3>Hauptlager</h3>
        <p>Bestände zentral pflegen und schnell aktualisieren.</p>
        <a href="hauptlager_edit.php">Verwalten</a>
      </article>
      <article class="card">
        <span class="icon">🛠️</span>
        <h3>Azubilager</h3>
        <p>Material für Auszubildende organisieren und freigeben.</p>
        <a href="azubilager_edit.php">Verwalten</a>
      </article>
      <article class="card">
        <span class="icon">🗂️</span>
        <h3>Bürolager</h3>
        <p>Büromaterial, Geräte und Verbrauchsgüter im Blick behalten.</p>
        <a href="buero_lager_edit.php">Verwalten</a>
      </article>
      <article class="card">
        <span class="icon">🧊</span>
        <h3>Kühlschrank</h3>
        <p>Vorräte checken und direkt nachfüllen, wenn etwas fehlt.</p>
        <a href="kuehlschrank_edit.php">Verwalten</a>
      </article>
      <article class="card">
        <span class="icon">📊</span>
        <h3>Lager-Übersicht</h3>
        <p>Alle Lager schnell vergleichen und Bestände prüfen.</p>
        <a href="lageruebersicht.php">Ansehen</a>
      </article>
    </div>
  </section>

  <section class="section">
    <header>
      <h2>Webseiteninhalte</h2>
      <span>Texte &amp; Medien</span>
    </header>
    <div class="card-grid">
      <article class="card">
        <span class="icon">🏢</span>
        <h3>Über uns</h3>
        <p>Unternehmensprofil aktualisieren und Highlights betonen.</p>
        <a href="edit_about.php">Bearbeiten</a>
      </article>
      <article class="card">
        <span class="icon">🧾</span>
        <h3>Services</h3>
        <p>Leistungen und Angebote auf aktuellem Stand halten.</p>
        <a href="edit_services.php">Bearbeiten</a>
      </article>
      <article class="card">
        <span class="icon">👥</span>
        <h3>Team</h3>
        <p>Teamseite pflegen und die Crew vorstellen.</p>
        <a href="edit_team.php">Bearbeiten</a>
      </article>
      <article class="card">
        <span class="icon">🖼️</span>
        <h3>Galerie</h3>
        <p>Neue Bilder hinzufügen oder Alben umsortieren.</p>
        <a href="edit_gallery.php">Bearbeiten</a>
      </article>
    </div>
  </section>

  <div class="logout">
    <a href="logout.php">🚪 Abmelden</a>
  </div>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Adminbereich</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="../script.js"></script>
</body>
</html>