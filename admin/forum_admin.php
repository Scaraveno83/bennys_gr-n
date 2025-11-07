<?php
error_reporting(E_ALL); ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_access.php';
require_once __DIR__ . '/../includes/forum_helpers.php';

// Räume CRUD
if (isset($_POST['add_room'])) {
  $stmt = $pdo->prepare("INSERT INTO forum_rooms (title, icon, description, sort_order) VALUES (?, ?, ?, ?)");
  $stmt->execute([trim($_POST['title'] ?? ''), $_POST['icon'] ?? null, trim($_POST['description'] ?? ''), (int)($_POST['sort_order'] ?? 0)]);
  header('Location: forum_admin.php'); exit;
}
if (isset($_POST['edit_room'])) {
  $stmt = $pdo->prepare("UPDATE forum_rooms SET title=?, icon=?, description=?, sort_order=? WHERE id=?");
  $stmt->execute([trim($_POST['title'] ?? ''), $_POST['icon'] ?? null, trim($_POST['description'] ?? ''), (int)($_POST['sort_order'] ?? 0), (int)$_POST['id']]);
  header('Location: forum_admin.php'); exit;
}
if (isset($_GET['delete'])) {
  $pdo->prepare("DELETE FROM forum_rooms WHERE id=?")->execute([(int)$_GET['delete']]);
  header('Location: forum_admin.php'); exit;
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
  header('Location: forum_admin.php'); exit;
}

$rooms = $pdo->query("SELECT * FROM forum_rooms ORDER BY COALESCE(sort_order,0) ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
$icons = ["🛠","📐","🗣","🚬","⚙️","💡","🔥","📦","📋","📅"];
?>
<!DOCTYPE html><html lang="de"><head>
<meta charset="UTF-8"><title>Forum – Verwaltung</title>
<link rel="stylesheet" href="/header.css"><link rel="stylesheet" href="/styles.css"><link rel="stylesheet" href="/forum.css">
<style>
main{max-width:1000px;margin:140px auto;padding:0 20px;color:#e9e9e9;}
.admin-box{background:#161616;border:1px solid rgba(57,255,20,.45);border-radius:14px;padding:18px;margin:16px 0;box-shadow:0 0 18px rgba(57,255,20,.18);}
.section-title{margin:0 0 10px 0;color:#76ff65;}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
input,textarea,select{width:100%;padding:10px;border-radius:10px;border:1px solid rgba(57,255,20,.35);background:#111;color:#fff;margin-bottom:10px;}
.table{width:100%;border-collapse:collapse}
.table th,.table td{border:1px solid rgba(57,255,20,.35);padding:8px;text-align:left}
.table th{background:rgba(57,255,20,.12)}
.btn{padding:8px 12px;border-radius:10px;border:1px solid rgba(57,255,20,.45);background:linear-gradient(180deg,#1c1f23,#15181b);color:#fff;text-decoration:none;cursor:pointer}
.btn:hover{box-shadow:0 0 14px rgba(57,255,20,.35);}
.btn-danger{background:#39ff14;border:1px solid rgba(57,255,20,.7);}
</style>
</head><body>
<?php include __DIR__ . '/../header.php'; ?>
<main>

  <div class="admin-box">
    <h3 class="section-title">➕ Kategorie anlegen</h3>
    <form method="post" class="grid">
      <input type="hidden" name="add_room" value="1">
      <div>
        <label>Name</label>
        <input name="title" required>
      </div>
      <div>
        <label>Icon</label>
        <select name="icon"><option value="">—</option><?php foreach($icons as $ic): ?><option><?= $ic ?></option><?php endforeach; ?></select>
      </div>
      <div style="grid-column:1/3">
        <label>Beschreibung</label>
        <textarea name="description" rows="2"></textarea>
      </div>
      <div>
        <label>Sortierung (0=oben)</label>
        <input type="number" name="sort_order" value="0">
      </div>
      <div style="display:flex;align-items:flex-end;gap:8px">
        <button class="btn">Speichern</button>
      </div>
    </form>
  </div>

  <div class="admin-box">
    <h3 class="section-title">📂 Kategorien verwalten</h3>
    <?php if(!$rooms): ?><p>Keine Kategorien.</p><?php endif; ?>
    <?php foreach($rooms as $r): ?>
      <div class="admin-box" style="margin:10px 0">
        <form method="post" class="grid">
          <input type="hidden" name="edit_room" value="1">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <div>
            <label>Name</label>
            <input name="title" value="<?= h($r['title'] ?? '') ?>" required>
          </div>
          <div>
            <label>Icon</label>
            <select name="icon"><option value="">—</option><?php foreach($icons as $ic): ?><option <?= ($ic === ($r['icon'] ?? ''))?'selected':'' ?>><?= $ic ?></option><?php endforeach; ?></select>
          </div>
          <div style="grid-column:1/3">
            <label>Beschreibung</label>
            <textarea name="description" rows="2"><?= h($r['description'] ?? '') ?></textarea>
          </div>
          <div>
            <label>Sortierung</label>
            <input type="number" name="sort_order" value="<?= (int)($r['sort_order'] ?? 0) ?>">
          </div>
          <div style="display:flex;align-items:flex-end;gap:8px">
            <button class="btn">💾</button>
            <a class="btn btn-danger" href="?delete=<?= (int)$r['id'] ?>" onclick="return confirm('Kategorie wirklich löschen?')">🗑</a>
          </div>
        </form>

        <div style="margin-top:8px">
          <form method="post">
            <input type="hidden" name="save_perms" value="1">
            <input type="hidden" name="room_id" value="<?= (int)$r['id'] ?>">
            <table class="table">
              <thead><tr><th>Rang</th><th>Darf schreiben</th></tr></thead>
              <tbody>
              <?php global $RANG_LIST; foreach ($RANG_LIST as $rg):
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
            <button class="btn" style="margin-top:8px">Rechte speichern</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

</main>
<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="/script.js"></script>
</body></html>
