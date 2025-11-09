<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_access.php';

// Rang lists
$rang_order = [
  "Geschäftsführung" => 1, "Stv. Geschäftsleitung" => 2, "Personalleitung" => 3,
  "Ausbilder/in" => 4, "Tuner/in" => 5, "Meister/in" => 6, "Mechaniker/in" => 7,
  "Geselle/Gesellin" => 8, "Azubi 3.Jahr" => 9, "Azubi 2.Jahr" => 10,
  "Azubi 1.Jahr" => 11, "Praktikant/in" => 12
];
$rangliste = array_keys($rang_order);

// ✅ FIX: Mitarbeiter löschen mit Login + Avatar
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];

  // Avatar löschen (alle möglichen Dateiendungen)
  foreach (['png','jpg','jpeg','webp'] as $ext) {
      $file = __DIR__ . "/../pics/profile/$id.$ext";
      if (file_exists($file)) unlink($file);
  }

  // Login vorher löschen (wichtig!)
  $pdo->prepare("DELETE FROM user_accounts WHERE mitarbeiter_id=?")->execute([$id]);

  // Mitarbeiter löschen
  $pdo->prepare("DELETE FROM mitarbeiter WHERE id=?")->execute([$id]);

  header("Location: edit_mitarbeiter.php");
  exit;
}

// Add
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add'])) {

  $stmt = $pdo->prepare("INSERT INTO mitarbeiter(name,rang,beschreibung,bild_url) VALUES(?,?,?,?)");
  $stmt->execute([$_POST['name'], $_POST['rang'], $_POST['beschreibung'], ""]);
  $new_id = $pdo->lastInsertId();

  $login = strtolower(preg_replace('/\s+/', '.', $_POST['name']));
  $prefixes = ["Benny", "Cars"];
$prefix = $prefixes[array_rand($prefixes)];
$numbers = rand(1000, 9999);
$pass = $prefix . $numbers . "!";


  $stmt2 = $pdo->prepare("
      INSERT INTO user_accounts (username, password_hash, mitarbeiter_id, role, active)
      VALUES (?, ?, ?, 'user', 1)
  ");
  $stmt2->execute([$login, password_hash($pass, PASSWORD_DEFAULT), $new_id]);

  $_SESSION['login_info'] = "Login-Name: <b>$login</b><br>Passwort: <b>$pass</b>";

  header("Location: edit_mitarbeiter.php");
  exit;
}

// Edit
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_id'])) {
  $id = (int)$_POST['edit_id'];
  $stmt = $pdo->prepare("UPDATE mitarbeiter SET name=?, rang=?, beschreibung=? WHERE id=?");
  $stmt->execute([$_POST['name'], $_POST['rang'], $_POST['beschreibung'], $id]);

  if (isset($_FILES['avatar']) && $_FILES['avatar']['error']==UPLOAD_ERR_OK) {
    require __DIR__.'/upload_mitarbeiter_avatar.php';
  }

  header("Location: edit_mitarbeiter.php");
  exit;
}

// Load list
$mitarbeiter = $pdo->query("SELECT * FROM mitarbeiter ORDER BY name ASC")
    ->fetchAll(PDO::FETCH_ASSOC);

$totalMitarbeiter = count($mitarbeiter);
$aktiveLogins = (int)$pdo->query("SELECT COUNT(*) FROM user_accounts WHERE active = 1")
    ->fetchColumn();
$anzahlRollen = count(array_unique(array_map(static function ($row) {
    return $row['rang'] ?? '';
}, $mitarbeiter)));

function avatar_exists_for(array $m): bool {
    $baseDir = __DIR__ . '/../pics/profile/';
    $id = (int)($m['id'] ?? 0);

    foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
        if (is_file($baseDir . $id . '.' . $ext)) {
            return true;
        }
    }

    if (!empty($m['bild_url'])) {
        $url = $m['bild_url'];
        if (preg_match('~^https?://~i', $url)) {
            return true;
        }

        $relative = '/' . ltrim($url, '/');
        $fs = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__ . '/..', '/') . $relative;

        return is_file($fs);
    }

    return false;
}

$ohneAvatar = array_reduce($mitarbeiter, static function ($carry, $row) {
    return $carry + (avatar_exists_for($row) ? 0 : 1);
}, 0);

function avatar_web_for($m) {
    if (!empty($m['bild_url'])) {
        $url = $m['bild_url'];
        if (preg_match('~^https?://~i', $url)) return $url;
        $web = '/' . ltrim($url, '/');
        $fs = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__ . '/..', '/') . $web;
        if (file_exists($fs)) return $web;
    }
    $id = (int)$m['id'];
    foreach (['png','jpg','jpeg','webp'] as $ext) {
        $fs = __DIR__ . "/../pics/profile/{$id}.{$ext}";
        if (file_exists($fs)) return "/pics/profile/{$id}.{$ext}";
    }
    return "/pics/default-avatar.png";
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>👥 Mitarbeiter verwalten | Admin</title>
<link rel="stylesheet" href="../styles.css">
<link rel="stylesheet" href="../header.css">
<style>
.inventory-page.admin-inventory-page {
  gap: 32px;
}

.team-admin-grid {
  display: grid;
  gap: 24px;
}

.team-admin-card {
  position: relative;
  padding: 24px;
  border-radius: 18px;
  border: 1px solid rgba(57, 255, 20, 0.18);
  background: rgba(10, 14, 16, 0.82);
  box-shadow: inset 0 0 0 1px rgba(57, 255, 20, 0.08);
  display: grid;
  gap: 18px;
}

.team-admin-card__header {
  display: flex;
  align-items: center;
  gap: 16px;
}

.team-admin-card__info {
  display: grid;
  gap: 4px;
}

.team-admin-card__name {
  font-size: 1.25rem;
  font-weight: 600;
  margin: 0;
}

.team-admin-card__rang {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  border-radius: 999px;
  border: 1px solid rgba(57, 255, 20, 0.28);
  background: rgba(57, 255, 20, 0.08);
  color: rgba(200, 255, 210, 0.9);
  font-weight: 600;
  width: fit-content;
}

.team-admin-card__avatar {
  width: 72px;
  height: 72px;
  object-fit: cover;
  border-radius: 16px;
  border: 2px solid rgba(57, 255, 20, 0.35);
  box-shadow: 0 0 18px rgba(57, 255, 20, 0.18);
}

.team-admin-form .form-grid {
  gap: 20px;
}

.team-admin-form .form-grid.two-column {
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.team-admin-form .form-grid textarea {
  min-height: 140px;
}

.team-admin-form .avatar-upload {
  display: grid;
  gap: 12px;
  justify-items: center;
}

.team-admin-form .avatar-upload img {
  width: 140px;
  height: 140px;
  object-fit: cover;
  border-radius: 18px;
  border: 2px solid rgba(57, 255, 20, 0.35);
  box-shadow: 0 0 22px rgba(57, 255, 20, 0.16);
}

.team-admin-form .avatar-upload label {
  font-weight: 600;
  color: rgba(200, 255, 210, 0.85);
}

.team-admin-form input[type="file"] {
  width: 100%;
  color: rgba(200, 255, 210, 0.8);
}

.team-admin-card__actions {
  position: absolute;
  top: 20px;
  right: 20px;
  display: flex;
  gap: 10px;
}

.team-admin-card__delete {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 999px;
  background: linear-gradient(120deg, rgba(226, 73, 128, 0.88), rgba(166, 40, 100, 0.85));
  color: #fff;
  font-weight: 600;
  text-decoration: none;
  border: none;
  box-shadow: 0 18px 32px rgba(226, 73, 128, 0.28);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.team-admin-card__delete:hover,
.team-admin-card__delete:focus-visible {
  transform: translateY(-1px);
  box-shadow: 0 22px 38px rgba(226, 73, 128, 0.38);
}

.team-admin-form .form-actions {
  justify-content: flex-start;
  margin-top: 8px;
}

.team-admin-form .inventory-submit {
  justify-content: center;
}

.team-login-hint {
  border-radius: 16px;
  border: 1px solid rgba(118, 255, 101, 0.32);
  background: rgba(36, 58, 40, 0.55);
  padding: 18px 20px;
  color: rgba(220, 255, 230, 0.95);
  display: grid;
  gap: 6px;
}

.team-login-hint strong {
  color: #fff;
}

@media (max-width: 720px) {
  .team-admin-card__header {
    flex-direction: column;
    align-items: flex-start;
  }

  .team-admin-card__avatar {
    width: 84px;
    height: 84px;
  }

  .team-admin-card__actions {
    position: static;
    justify-content: flex-end;
  }
}
</style>
</head>
<body>
<?php include '../header.php'; ?>

<main class="inventory-page admin-inventory-page">
  <header class="inventory-header">
    <h1 class="inventory-title">👥 Mitarbeiterverwaltung</h1>
    <p class="inventory-description">
      Lege neue Teammitglieder an, pflege Rollenbeschreibungen und aktualisiere Avatare. Änderungen werden sofort auf der öffentlichen Teamseite sichtbar.
    </p>
    <p class="inventory-info">
      Aktuelles Team: <?= number_format($totalMitarbeiter, 0, ',', '.') ?> Personen
    </p>

    <div class="inventory-metrics">
      <article class="inventory-metric">
        <span class="inventory-metric__label">Mitarbeitende</span>
        <span class="inventory-metric__value"><?= number_format($totalMitarbeiter, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">gesamt erfasst</span>
      </article>

      <article class="inventory-metric">
        <span class="inventory-metric__label">Aktive Logins</span>
        <span class="inventory-metric__value"><?= number_format($aktiveLogins, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">freigeschaltet in der Datenbank</span>
      </article>

      <article class="inventory-metric">
        <span class="inventory-metric__label">Rollenvielfalt</span>
        <span class="inventory-metric__value"><?= number_format($anzahlRollen, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">distincte Ränge</span>
      </article>

      <article class="inventory-metric <?= $ohneAvatar ? 'inventory-metric--alert' : '' ?>">
        <span class="inventory-metric__label">Ohne Avatar</span>
        <span class="inventory-metric__value"><?= number_format($ohneAvatar, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">nutzen Platzhalterbild</span>
      </article>
    </div>
  </header>

  <?php if(isset($_SESSION['login_info'])): ?>
    <section class="inventory-section">
      <h2>Neuer Login angelegt</h2>
      <div class="team-login-hint">
        <p>Bitte übergebe die Zugangsdaten vertraulich an das Teammitglied:</p>
        <p><?= $_SESSION['login_info'] ?></p>
      </div>
    </section>
    <?php unset($_SESSION['login_info']); endif; ?>

  <section class="inventory-section">
    <h2>Neuen Mitarbeiter hinzufügen</h2>
    <p class="inventory-section__intro">
      Erstelle einen Eintrag inklusive Rang und Kurzbeschreibung. Das Avatarbild kann optional direkt hochgeladen werden.
    </p>

    <form method="post" enctype="multipart/form-data" class="inventory-form team-admin-form">
      <input type="hidden" name="add" value="1">

      <div class="form-grid two-column">
        <div class="input-control">
          <label for="mitarbeiter-name">Name</label>
          <input id="mitarbeiter-name" class="input-field" name="name" required>
        </div>

        <div class="input-control">
          <label for="mitarbeiter-rang">Rang</label>
          <select id="mitarbeiter-rang" class="input-field" name="rang" required>
            <option value="">– Rang auswählen –</option>
            <?php foreach($rangliste as $r): ?>
              <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="input-control" style="grid-column: 1 / -1;">
          <label for="mitarbeiter-beschreibung">Beschreibung</label>
          <textarea id="mitarbeiter-beschreibung" class="input-field" name="beschreibung" rows="3" placeholder="Kurzbeschreibung, Aufgabenbereiche oder Besonderheiten"></textarea>
        </div>

        <div class="avatar-upload" style="grid-column: 1 / -1;">
          <img src="/pics/default-avatar.png" alt="Avatar Platzhalter">
          <label for="mitarbeiter-avatar">Avatar hochladen</label>
          <input id="mitarbeiter-avatar" type="file" name="avatar" accept="image/*">
        </div>
      </div>

      <div class="form-actions">
        <button class="inventory-submit" type="submit">➕ Eintrag speichern</button>
      </div>
    </form>
  </section>

  <section class="inventory-section">
    <h2>Bestehende Teammitglieder</h2>
    <p class="inventory-section__intro">
      Aktualisiere Stammdaten oder ersetze Avatare. Mit einem Klick auf „Entfernen“ wird der Datensatz inklusive Login gelöscht.
    </p>

    <?php if (empty($mitarbeiter)): ?>
      <p class="inventory-section__intro">Noch keine Einträge vorhanden.</p>
    <?php else: ?>
      <div class="team-admin-grid">
        <?php foreach($mitarbeiter as $m):
          $avatar = avatar_web_for($m);
        ?>
          <article class="team-admin-card">
            <div class="team-admin-card__header">
              <img src="<?= htmlspecialchars($avatar) ?>" class="team-admin-card__avatar" alt="Avatar von <?= htmlspecialchars($m['name']) ?>">
              <div class="team-admin-card__info">
                <h3 class="team-admin-card__name"><?= htmlspecialchars($m['name']) ?></h3>
                <span class="team-admin-card__rang"><?= htmlspecialchars($m['rang']) ?></span>
              </div>
            </div>

            <div class="team-admin-card__actions">
              <a href="?delete=<?= $m['id'] ?>"
                 class="team-admin-card__delete"
                 onclick="return confirm('Diesen Mitarbeiter wirklich löschen?');">
                🗑 Entfernen
              </a>
            </div>

            <form method="post" enctype="multipart/form-data" class="inventory-form team-admin-form">
              <input type="hidden" name="edit_id" value="<?= $m['id'] ?>">

              <div class="form-grid two-column">
                <div class="input-control">
                  <label for="name-<?= $m['id'] ?>">Name</label>
                  <input id="name-<?= $m['id'] ?>" class="input-field" name="name" value="<?= htmlspecialchars($m['name']) ?>" required>
                </div>

                <div class="input-control">
                  <label for="rang-<?= $m['id'] ?>">Rang</label>
                  <select id="rang-<?= $m['id'] ?>" class="input-field" name="rang">
                    <?php foreach($rangliste as $r): ?>
                      <option value="<?= htmlspecialchars($r) ?>" <?= $r == $m['rang'] ? 'selected' : '' ?>><?= htmlspecialchars($r) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="input-control" style="grid-column: 1 / -1;">
                  <label for="beschreibung-<?= $m['id'] ?>">Beschreibung</label>
                  <textarea id="beschreibung-<?= $m['id'] ?>" class="input-field" name="beschreibung" rows="3"><?= htmlspecialchars($m['beschreibung']) ?></textarea>
                </div>

                <div class="avatar-upload" style="grid-column: 1 / -1;">
                  <img src="<?= htmlspecialchars($avatar) ?>" alt="Aktueller Avatar">
                  <label for="avatar-<?= $m['id'] ?>">Neuen Avatar hochladen</label>
                  <input id="avatar-<?= $m['id'] ?>" type="file" name="avatar" accept="image/*">
                </div>
              </div>

              <div class="form-actions">
                <button class="inventory-submit" type="submit">💾 Änderungen speichern</button>
              </div>
            </form>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="../script.js"></script>
</body>
</html>
