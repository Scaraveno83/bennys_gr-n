<?php
error_reporting(E_ALL); ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_access.php';
require_once __DIR__ . '/../includes/forum_helpers.php';

// Räume CRUD
if (isset($_POST['add_room'])) {
  $stmt = $pdo->prepare("INSERT INTO forum_rooms (title, icon, description, sort_order) VALUES (?, ?, ?, ?)");
  $stmt->execute([
    trim($_POST['title'] ?? ''),
    $_POST['icon'] ?? null,
    trim($_POST['description'] ?? ''),
    (int)($_POST['sort_order'] ?? 0)
  ]);
  header('Location: forum_admin.php');
  exit;
}
if (isset($_POST['edit_room'])) {
  $stmt = $pdo->prepare("UPDATE forum_rooms SET title=?, icon=?, description=?, sort_order=? WHERE id=?");
  $stmt->execute([
    trim($_POST['title'] ?? ''),
    $_POST['icon'] ?? null,
    trim($_POST['description'] ?? ''),
    (int)($_POST['sort_order'] ?? 0),
    (int)$_POST['id']
  ]);
  header('Location: forum_admin.php');
  exit;
}
if (isset($_GET['delete'])) {
  $pdo->prepare("DELETE FROM forum_rooms WHERE id=?")->execute([(int)$_GET['delete']]);
  header('Location: forum_admin.php');
  exit;
}

// Schreibrechte speichern
if (isset($_POST['save_perms'])) {
  $room_id = (int)$_POST['room_id'];
  global $RANG_LIST;
  foreach ($RANG_LIST as $r) {
    $flag = isset($_POST['perm'][$r]) ? 1 : 0;
    // upsert
    $sel = $pdo->prepare("SELECT 1 FROM forum_room_permissions WHERE room_id=? AND rang=?");
    $sel->execute([$room_id, $r]);
    if ($sel->fetch()) {
      $upd = $pdo->prepare("UPDATE forum_room_permissions SET can_write=? WHERE room_id=? AND rang=?");
      $upd->execute([$flag, $room_id, $r]);
    } else {
      $ins = $pdo->prepare("INSERT INTO forum_room_permissions (room_id, rang, can_write) VALUES (?, ?, ?)");
      $ins->execute([$room_id, $r, $flag]);
    }
  }
  header('Location: forum_admin.php');
  exit;
}

$rooms = $pdo->query("SELECT * FROM forum_rooms ORDER BY COALESCE(sort_order,0) ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
$icons = ["🛠","📐","🗣","🚬","⚙️","💡","🔥","📦","📋","📅"];

$totalRooms = count($rooms);
$roomsWithIcon = count(array_filter($rooms, static fn($room) => !empty($room['icon'])));
$totalPermissions = (int)$pdo->query("SELECT COUNT(*) FROM forum_room_permissions")->fetchColumn();
$latestRoomTitle = $pdo->query("SELECT title FROM forum_rooms ORDER BY id DESC LIMIT 1")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Forum – Verwaltung</title>
<link rel="stylesheet" href="../header.css">
<link rel="stylesheet" href="../styles.css">
<link rel="stylesheet" href="../forum.css">
<style>
.forum-admin-page {
  gap: 32px;
}

.forum-admin-form-grid {
  display: grid;
  gap: 18px;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.forum-admin-list {
  display: grid;
  gap: 18px;
}

.forum-admin-room {
  display: grid;
  gap: 18px;
  padding: 20px;
  border-radius: 18px;
  border: 1px solid rgba(57, 255, 20, 0.22);
  background: rgba(10, 14, 16, 0.88);
  box-shadow: inset 0 0 0 1px rgba(57, 255, 20, 0.08), 0 22px 36px rgba(0, 0, 0, 0.4);
}

.forum-admin-room__header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  justify-content: space-between;
}

.forum-admin-room__title {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  margin: 0;
  font-size: 1.2rem;
}

.forum-admin-room__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.forum-admin-perms {
  display: grid;
  gap: 12px;
}

.forum-admin-perms .table-wrap {
  margin-top: 0;
}

.button-danger {
  background: rgba(255, 102, 118, 0.12);
  border: 1px solid rgba(255, 102, 118, 0.6);
  color: #ffb6c7;
}

.button-danger:hover,
.button-danger:focus-visible {
  background: rgba(255, 102, 118, 0.22);
  box-shadow: 0 0 22px rgba(255, 102, 118, 0.28);
}

.forum-admin-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  font-size: 0.95rem;
  color: rgba(255, 255, 255, 0.7);
}

.forum-admin-meta span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 999px;
  border: 1px solid rgba(57, 255, 20, 0.22);
  background: rgba(57, 255, 20, 0.08);
}
</style>
</head>
<body>
<?php include __DIR__ . '/../header.php'; ?>

  <main class="inventory-page forum-admin-page">
  <header class="inventory-header">
    <h1 class="inventory-title">🛠 Forum – Verwaltung</h1>
    <p class="inventory-description">
      Strukturiere die Forenbereiche der Werkstatt, aktualisiere Beschreibungen und steuere Schreibrechte für jede Kategorie.
    </p>
    <p class="inventory-info">
      Letzte Kategorie: <?= $latestRoomTitle ? h($latestRoomTitle) : 'Noch keine Kategorien angelegt' ?>
    </p>

    <div class="inventory-metrics">
      <article class="inventory-metric">
        <span class="inventory-metric__label">Kategorien gesamt</span>
        <span class="inventory-metric__value"><?= number_format($totalRooms, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">inkl. ausgeblendeter Räume</span>
      </article>
      <article class="inventory-metric">
        <span class="inventory-metric__label">Mit Icon</span>
        <span class="inventory-metric__value"><?= number_format($roomsWithIcon, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">optisch hervorgehobene Bereiche</span>
      </article>
      <article class="inventory-metric">
        <span class="inventory-metric__label">Rechte-Einträge</span>
        <span class="inventory-metric__value"><?= number_format($totalPermissions, 0, ',', '.') ?></span>
        <span class="inventory-metric__hint">Zuweisungen an Rollen</span>
      </article>
    </div>
  </header>

  <section class="inventory-section">
    <h2>➕ Kategorie anlegen</h2>
    <p class="inventory-section__intro">
      Lege einen neuen Forenbereich samt Icon, Beschreibung und Sortier-Reihenfolge fest.
    </p>
    <form method="post" class="inventory-form">
      <input type="hidden" name="add_room" value="1">
       <div class="forum-admin-form-grid">
        <div class="input-control">
          <label for="new_title">Name</label>
          <input id="new_title" class="input-field" name="title" required>
        </div>
        <div class="input-control">
          <label for="new_icon">Icon</label>
          <select id="new_icon" name="icon">
            <option value="">—</option>
            <?php foreach ($icons as $ic): ?>
              <option><?= $ic ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="input-control input-control--full">
          <label for="new_description">Beschreibung</label>
          <textarea id="new_description" name="description" rows="2"></textarea>
        </div>
        <div class="input-control">
          <label for="new_sort">Sortierung (0 = oben)</label>
          <input id="new_sort" class="input-field" type="number" name="sort_order" value="0">
        </div>
      </div>
    <div class="form-actions">
        <button class="button-main" type="submit">Kategorie speichern</button>
      </div>
    </form>
  </section>

  <section class="inventory-section">
    <h2>📂 Kategorien verwalten</h2>
    <p class="inventory-section__intro">
      Bearbeite Titel, Icons und Beschreibungen bestehender Räume und passe die Schreibrechte der einzelnen Rollen an.
    </p>

    <?php if (!$rooms): ?>
      <p class="empty-state">Noch keine Kategorien vorhanden. Lege oben einen neuen Raum an.</p>
    <?php else: ?>
      <div class="forum-admin-meta">
        <span>Sortierung: kleinere Zahlen werden zuerst angezeigt</span>
        <span>Schreibrechte wirken sofort nach dem Speichern</span>
      </div>
    <div class="forum-admin-list">
        <?php foreach ($rooms as $r): ?>
          <article class="forum-admin-room">
            <header class="forum-admin-room__header">
              <h3 class="forum-admin-room__title">
                <span><?= h($r['icon'] ?? '📁') ?></span>
                <span><?= h($r['title'] ?? '') ?></span>
              </h3>
              <div class="forum-admin-room__actions">
                <a class="button-secondary button-danger" href="?delete=<?= (int)$r['id'] ?>" onclick="return confirm('Kategorie wirklich löschen?');">🗑 Entfernen</a>
              </div>
            </header>

            <form method="post" class="inventory-form">
              <input type="hidden" name="edit_room" value="1">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <div class="forum-admin-form-grid">
                <div class="input-control">
                  <label for="title_<?= (int)$r['id'] ?>">Name</label>
                  <input id="title_<?= (int)$r['id'] ?>" class="input-field" name="title" value="<?= h($r['title'] ?? '') ?>" required>
                </div>
                <div class="input-control">
                  <label for="icon_<?= (int)$r['id'] ?>">Icon</label>
                  <select id="icon_<?= (int)$r['id'] ?>" name="icon">
                    <option value="">—</option>
                    <?php foreach ($icons as $ic): ?>
                      <option <?= ($ic === ($r['icon'] ?? '')) ? 'selected' : '' ?>><?= $ic ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="input-control input-control--full">
                  <label for="desc_<?= (int)$r['id'] ?>">Beschreibung</label>
                  <textarea id="desc_<?= (int)$r['id'] ?>" name="description" rows="2"><?= h($r['description'] ?? '') ?></textarea>
                </div>
                <div class="input-control">
                  <label for="sort_<?= (int)$r['id'] ?>">Sortierung</label>
                  <input id="sort_<?= (int)$r['id'] ?>" class="input-field" type="number" name="sort_order" value="<?= (int)($r['sort_order'] ?? 0) ?>">
                </div>
              </div>
              <div class="form-actions">
                <button class="button-main" type="submit">💾 Änderungen speichern</button>
              </div>
            </form>

            <div class="forum-admin-perms">
              <h4>✍️ Schreibrechte</h4>
              <form method="post">
                <input type="hidden" name="save_perms" value="1">
                <input type="hidden" name="room_id" value="<?= (int)$r['id'] ?>">
                <div class="table-wrap">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>Rang</th>
                        <th>Darf schreiben</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                      global $RANG_LIST;
                      foreach ($RANG_LIST as $rg):
                        $q = $pdo->prepare("SELECT can_write FROM forum_room_permissions WHERE room_id=? AND rang=?");
                        $q->execute([(int)$r['id'], $rg]);
                        $perm = $q->fetch(PDO::FETCH_ASSOC);
                        $checked = ((int)($perm['can_write'] ?? 0) === 1) ? 'checked' : '';
                      ?>
                        <tr>
                          <td><?= h($rg) ?></td>
                          <td><input type="checkbox" name="perm[<?= h($rg) ?>]" <?= $checked ?>></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <div class="form-actions">
                  <button class="button-secondary" type="submit">Rechte speichern</button>
                </div>
              </form>
            </div>
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
