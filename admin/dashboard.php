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

<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../header.css">
<link rel="stylesheet" href="../styles.css">

<style>
main {
  padding: 120px 40px;
  max-width: 1300px;
  margin: 0 auto;
  text-align: center;
  color: #fff;
}

/* ===== Abschnittstitel ===== */
.section-group {
  margin-bottom: 60px;
}
.section-group h2 {
  color: #76ff65;
  text-shadow: 0 0 12px rgba(57,255,20,0.8);
  font-size: 1.7rem;
  border-bottom: 2px solid rgba(57,255,20,0.4);
  display: inline-block;
  padding-bottom: 5px;
  margin-bottom: 25px;
}

/* ===== Grid ===== */
.card-grid {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 25px;
}

/* ===== Karten ===== */
.card {
  background: rgba(25, 25, 25, 0.9);
  border: 1px solid rgba(57,255,20,0.45);
  border-radius: 14px;
  padding: 28px 22px;
  width: 290px;
  min-height: 200px;
  box-shadow: 0 0 18px rgba(57,255,20,0.25);
  transition: all 0.3s ease;
  position: relative;
}
.card:hover {
  transform: translateY(-5px) scale(1.03);
  box-shadow: 0 0 25px rgba(57,255,20,0.5)
}
.card h3 {
  color: #76ff65;
  text-shadow: 0 0 8px rgba(57,255,20,0.5);
  margin-bottom: 10px;
  font-size: 1.2rem;
}
.card p {
  color: #ccc;
  font-size: 0.9rem;
  margin-bottom: 18px;
  min-height: 45px;
}
.card a {
  display: inline-block;
  background: linear-gradient(90deg, #39ff14, #76ff65);
  color: #fff;
  padding: 9px 18px;
  border-radius: 8px;
  text-decoration: none;
  font-weight: bold;
  transition: 0.3s;
}
.card a:hover {
  box-shadow: 0 0 15px rgba(57,255,20,0.6);
  transform: scale(1.05);
}

/* ===== Logout ===== */
.logout-container {
  text-align: center;
  margin: 60px 0 30px;
}
.logout-container a {
  border: 2px solid #39ff14;
  padding: 10px 25px;
  border-radius: 8px;
  color: #39ff14;
  font-weight: bold;
  text-decoration: none;
  transition: 0.3s;
}
.logout-container a:hover {
  background: #39ff14;
  color: white;
  box-shadow: 0 0 15px #39ff14;
}
</style>
</head>

<body>
<?php include '../header.php'; ?>

<main>
  <h1 class="section-title" style="margin-bottom:10px;">🔧 Admin-Dashboard</h1>
  <p>Willkommen, <strong><?= htmlspecialchars($_SESSION['admin_username'] ?? $_SESSION['mitarbeiter_name'] ?? 'Admin'); ?></strong>!<br>
  Wähle einen Bereich, um Inhalte deiner Webseite zu verwalten.</p>

  <!-- === News & Kommunikation === -->
  <div class="section-group">
    <h2>📰 News & Kommunikation</h2>
    <div class="card-grid">
      <div class="card">
        <h3>News & Ankündigungen</h3>
        <p>Erstelle oder bearbeite öffentliche und interne News.</p>
        <a href="news_manage.php">Verwalten</a>
      </div>

      <div class="card">
        <h3>Nachrichtenverwaltung</h3>
        <p>Alle privaten Nachrichten einsehen und löschen.</p>
        <a href="manage_messages.php">Verwalten</a>
      </div>

      <div class="card">
        <h3>Feedback</h3>
        <p>Verwalte eingereichte Rückmeldungen und Vorschläge.</p>
        <a href="manage_feedback.php">Verwalten</a>
      </div>

      <div class="card">
        <h3>Forum</h3>
        <p>Verwalte Forum.</p>
        <a href="forum_admin.php">Verwalten</a>
      </div>

      <div class="card">
        <h3>Preise & Vertragspartner</h3>
        <p>Verwalte Preislisten & Vertragspartner + Tunings.</p>
        <a href="partner_admin.php">Verwalten</a>
      </div>
    </div>
  </div>

  <!-- === Mitarbeiter & Organisation === -->
  <div class="section-group">
    <h2>👥 Mitarbeiter & Organisation</h2>
    <div class="card-grid">
      <div class="card">
        <h3>Mitarbeiter</h3>
        <p>Verwalte Namen, Ränge, Beschreibungen und Bilder.</p>
        <a href="edit_mitarbeiter.php">Verwalten</a>
      </div>

      <div class="card">
        <h3>Kalender</h3>
        <p>Kalender & Verwaltung .</p>
        <a href="calendar_admin.php">Verwalten</a>
      </div>

      <?php if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
      <div class="card">
        <h3>Useraccounts</h3>
        <p>Konten, Passwörter und Berechtigungen bearbeiten.</p>
        <a href="user_accounts_edit.php">Verwalten</a>
      </div>
      <?php endif; ?>

      <div class="card">
        <h3>Wochenaufgaben</h3>
        <p>Aufgabenplanung und Fortschritte verwalten.</p>
        <a href="wochenaufgaben_edit.php">Verwalten</a>
      </div>

      <div class="card"><h3>Fahrzeuge</h3><p>Fahrzeugflotten Verwaltung</p><a href="fahrzeuge_edit.php">Verwalten</a></div>
    </div>
  </div>

  <!-- === Lagerverwaltung === -->
  <div class="section-group">
    <h2>🏭 Lagerverwaltung</h2>
    <div class="card-grid">
      <div class="card"><h3>Hauptlager</h3><p>Alle Hauptlagerbestände bearbeiten.</p><a href="hauptlager_edit.php">Verwalten</a></div>
      <div class="card"><h3>Azubilager</h3><p>Werkzeug- und Materialverwaltung für Azubis.</p><a href="azubilager_edit.php">Verwalten</a></div>
      <div class="card"><h3>Bürolager</h3><p>Artikel und Geräte des Büros verwalten.</p><a href="buero_lager_edit.php">Verwalten</a></div>
      <div class="card"><h3>Kühlschrank</h3><p>Inhalte des Kühlschranks pflegen.</p><a href="kuehlschrank_edit.php">Verwalten</a></div>
      <div class="card"><h3>Lager-Übersicht</h3><p>Alle Lager zentral im Überblick.</p><a href="lageruebersicht.php">Ansehen</a></div>
    </div>
  </div>

  <!-- === Website-Inhalte === -->
  <div class="section-group">
    <h2>🌐 Webseiteninhalte</h2>
    <div class="card-grid">
      <div class="card"><h3>Über uns</h3><p>Unternehmensbeschreibung bearbeiten.</p><a href="edit_about.php">Bearbeiten</a></div>
      <div class="card"><h3>Services</h3><p>Leistungen und Preise aktualisieren.</p><a href="edit_services.php">Bearbeiten</a></div>
      <div class="card"><h3>Team</h3><p>Darstellung des Teams auf der Webseite.</p><a href="edit_team.php">Bearbeiten</a></div>
      <div class="card"><h3>Galerie</h3><p>Bilder hinzufügen oder löschen.</p><a href="edit_gallery.php">Bearbeiten</a></div>
    </div>
  </div>

  <!-- === Logout === -->
  <div class="logout-container">
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
