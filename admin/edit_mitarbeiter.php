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
$mitarbeiter = $pdo->query("SELECT * FROM mitarbeiter")->fetchAll(PDO::FETCH_ASSOC);

function avatar_web_for($m) {
    if (!empty($m['bild_url'])) {
        $url = $m['bild_url'];
        if (preg_match('~^https?://~i', $url)) return $url;
        $web = '/' . ltrim($url, '/');
        $fs = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $web;
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
<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Mitarbeiter verwalten</title>
<link rel="stylesheet" href="../header.css"><link rel="stylesheet" href="../styles.css">
<style>
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
.card{background:#1b1b1b;border:1px solid rgba(57,255,20,0.35);border-radius:14px;padding:20px;margin-bottom:30px;}
.card h3{text-align:left;margin-bottom:10px;color:#76ff65;}
.input,select,textarea{width:100%;padding:10px;border-radius:10px;border:1px solid rgba(57,255,20,.35);background:#111;color:#fff;margin-bottom:10px;}
.avatar-preview{width:120px;height:120px;object-fit:cover;border-radius:14px;border:2px solid rgba(57,255,20,.5);margin-bottom:10px;}
.btn-main{background:#39ff14;border:none;border-radius:10px;padding:10px 16px;color:#fff;cursor:pointer;}
</style>
</head><body>
<?php include '../header.php'; ?>
<main style="padding:120px 50px;max-width:1200px;margin:auto;">

<?php if(isset($_SESSION['login_info'])): ?>
<div class="card" style="background:#262626;color:#fff;padding:15px;margin-bottom:20px;">
  <h3>Login-Daten:</h3>
  <p><?= $_SESSION['login_info'] ?></p>
</div>
<?php unset($_SESSION['login_info']); endif; ?>

<div class="card">
<h3>Mitarbeiter hinzufügen</h3>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="add" value="1">
<div class="form-grid">
<div>
<label>Name</label>
<input class="input" name="name" required>
<label>Rang</label>
<select class="input" name="rang" required>
<option value="">--</option>
<?php foreach($rangliste as $r): ?><option value="<?=htmlspecialchars($r)?>"><?=$r?></option><?php endforeach; ?>
</select>
<label>Beschreibung</label>
<textarea class="input" name="beschreibung" rows="3"></textarea>
</div>
<div style="text-align:center;">
<img src="/pics/default-avatar.png" class="avatar-preview">
<label style="display:block;margin-bottom:6px;">Avatar hochladen:</label>
<input type="file" name="avatar" accept="image/*">
</div>
</div>
<button class="btn-main">Speichern</button>
</form>
</div>

<div class="card">
<h3>Bestehende Mitarbeiter</h3>

<?php foreach($mitarbeiter as $m): ?>
<div class="card" style="position:relative;">

<!-- Löschen Button oben rechts -->
<a href="?delete=<?= $m['id'] ?>" 
   onclick="return confirm('Diesen Mitarbeiter wirklich löschen?');"
   style="position:absolute; top:15px; right:15px; background:#39ff14; border:none; border-radius:8px; padding:6px 12px; color:#fff; font-weight:bold; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
  🗑 Löschen
</a>

<form method="post" enctype="multipart/form-data" class="form-grid" style="margin-bottom:20px;">
<div>
<input type="hidden" name="edit_id" value="<?=$m['id']?>">

<label>Name</label>
<input class="input" name="name" value="<?=htmlspecialchars($m['name'])?>" required>

<label>Rang</label>
<select class="input" name="rang">
<?php foreach($rangliste as $r): ?><option <?=$r==$m['rang']?'selected':''?>><?=$r?></option><?php endforeach; ?>
</select>

<label>Beschreibung</label>
<textarea class="input" name="beschreibung" rows="3"><?=htmlspecialchars($m['beschreibung'])?></textarea>
</div>

<div style="text-align:center;">
<img src="<?= htmlspecialchars(avatar_web_for($m)) ?>" class="avatar-preview">
<label style="display:block;margin-bottom:6px;">Neuen Avatar hochladen:</label>
<input type="file" name="avatar" accept="image/*">
</div>

<button class="btn-main" style="grid-column:1/3;">💾 Speichern</button>
</form>

</div>
<?php endforeach; ?>

</div>


</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="../script.js"></script>
</body>
</html>
