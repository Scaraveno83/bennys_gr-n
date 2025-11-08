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
</head>

<body>
<?php include '../header.php'; ?>

<main class="dashboard-main">
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