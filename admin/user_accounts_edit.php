<?php
session_start();
require_once '../includes/db.php';

// Zugriff nur für Admins
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
  header("Location: login.php");
  exit;
}

// Mitarbeiterliste laden
$mitarbeiter = $pdo->query("SELECT id, name FROM mitarbeiter ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

/* === CRUD === */

// löschen
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  $pdo->prepare("DELETE FROM user_accounts WHERE id = ?")->execute([$id]);
  header("Location: user_accounts_edit.php");
  exit;
}

// hinzufügen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
  $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
  $stmt = $pdo->prepare("INSERT INTO user_accounts (mitarbeiter_id, username, password_hash, role) VALUES (?, ?, ?, ?)");
  $stmt->execute([
    (int)$_POST['mitarbeiter_id'],
    trim($_POST['username']),
    $hash,
    $_POST['role']
  ]);
  header("Location: user_accounts_edit.php");
  exit;
}

// bearbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
  $sql = "UPDATE user_accounts SET username=?, role=?, active=?";
  $params = [trim($_POST['username']), $_POST['role'], (int)$_POST['active']];
  if (!empty($_POST['password'])) {
    $sql .= ", password_hash=?";
    $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
  }
  $sql .= " WHERE id=?";
  $params[] = (int)$_POST['edit_id'];
  $pdo->prepare($sql)->execute($params);
  header("Location: user_accounts_edit.php");
  exit;
}

// alle Accounts laden
$accounts = $pdo->query("
  SELECT ua.*, m.name AS mitarbeiter_name
  FROM user_accounts ua
  LEFT JOIN mitarbeiter m ON ua.mitarbeiter_id = m.id
  ORDER BY ua.id ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Benutzerkonten verwalten | Admin</title>

<!-- Fonts & globale Styles -->
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../header.css">
<link rel="stylesheet" href="../styles.css">
<script src="../script.js" defer></script>

<style>
main {
  max-width: 1200px;
  margin: 120px auto 80px;
  padding: 0 40px;
  text-align: center;
}

/* Tabellen-Styling */
.admin-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 25px;
}
.admin-table th, .admin-table td {
  border: 1px solid rgba(57,255,20,0.35);
  padding: 10px;
  vertical-align: middle;
}
.admin-table th {
  background: rgba(57,255,20,0.18);
  color: #76ff65;
  text-align: center;
}
.admin-table td {
  background: rgba(20,20,20,0.85);
  color: #eee;
}
.admin-table input, .admin-table select {
  width: 100%;
  background: rgba(30,30,30,0.9);
  border: 1px solid rgba(57,255,20,0.4);
  border-radius: 8px;
  color: #fff;
  padding: 6px 8px;
}
.admin-table input[type="password"] {
  width: 140px;
}
.actions {
  display: flex;
  gap: 6px;
  justify-content: center;
  align-items: center;
}

.card {
  background: rgba(20,20,20,0.85);
  border: 1px solid rgba(57,255,20,0.4);
  border-radius: 15px;
  padding: 25px;
  box-shadow: 0 0 20px rgba(57,255,20,0.25);
  backdrop-filter: blur(10px);
  margin-bottom: 40px;
}

.card h3 {
  color: #76ff65;
  margin-bottom: 15px;
}

.back-wrap {
  margin-top: 40px;
  text-align: center;
}
</style>
</head>
<body id="top">

<?php include '../header.php'; ?>

<main>
  <section class="cards-section">
    <h2 class="section-title">👥 Benutzerkonten verwalten</h2>

    <!-- Neuer Benutzer -->
    <div class="card">
      <h3>➕ Benutzer anlegen</h3>
      <form method="post">
        <input type="hidden" name="add" value="1">

        <label>Mitarbeiter:</label>
        <select name="mitarbeiter_id" required>
          <option value="">– auswählen –</option>
          <?php foreach ($mitarbeiter as $m): ?>
            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
          <?php endforeach; ?>
        </select>

        <label>Benutzername:</label>
        <input type="text" name="username" required>

        <label>Passwort:</label>
        <input type="password" name="password" required>

        <label>Rolle:</label>
        <select name="role">
          <option value="user">Mitarbeiter</option>
          <option value="admin">Admin</option>
        </select>

        <button type="submit" class="btn btn-primary" style="margin-top:10px;">+ Benutzer anlegen</button>
      </form>
    </div>

    <!-- Bestehende Accounts -->
    <div class="card">
      <h3>📋 Bestehende Benutzer</h3>
      <?php if ($accounts): ?>
        <table class="admin-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Mitarbeiter</th>
              <th>Benutzername</th>
              <th>Rolle</th>
              <th>Status</th>
              <th>Aktionen</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($accounts as $a): ?>
              <tr>
                <form method="post">
                  <td><?= $a['id'] ?></td>
                  <td><?= htmlspecialchars($a['mitarbeiter_name'] ?? '-') ?></td>
                  <td><input type="text" name="username" value="<?= htmlspecialchars($a['username']) ?>"></td>
                  <td>
                    <select name="role">
                      <option value="user" <?= $a['role']==='user'?'selected':'' ?>>Mitarbeiter</option>
                      <option value="admin" <?= $a['role']==='admin'?'selected':'' ?>>Admin</option>
                    </select>
                  </td>
                  <td>
                    <select name="active">
                      <option value="1" <?= $a['active']?'selected':'' ?>>Aktiv</option>
                      <option value="0" <?= !$a['active']?'selected':'' ?>>Gesperrt</option>
                    </select>
                  </td>
                  <td class="actions">
                    <input type="hidden" name="edit_id" value="<?= $a['id'] ?>">
                    <input type="password" name="password" placeholder="Passwort (optional)">
                    <button type="submit" class="btn btn-primary" title="Speichern">💾</button>
                    <a href="?delete=<?= $a['id'] ?>" class="btn btn-ghost" onclick="return confirm('Account wirklich löschen?')">🗑️</a>
                  </td>
                </form>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>Keine Benutzer vorhanden.</p>
      <?php endif; ?>
    </div>

    <div class="back-wrap">
      <a href="dashboard.php" class="btn btn-ghost">← Zurück zum Dashboard</a>
    </div>
  </section>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

</body>
</html>
