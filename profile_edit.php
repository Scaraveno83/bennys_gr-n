<?php
session_start();
require_once __DIR__ . '/includes/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: admin/login.php');
    exit;
}

$stmt = $pdo->prepare("
  SELECT m.*, u.username
  FROM user_accounts u
  JOIN mitarbeiter m ON m.id = u.mitarbeiter_id
  WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$me) { http_response_code(404); exit('Profil nicht gefunden'); }
if (isset($_POST['beschreibung'])) {

    $stmt = $pdo->prepare("
        UPDATE mitarbeiter
        SET beschreibung = ?, skills = ?, status = ?, phone = ?, email = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $_POST['beschreibung'],
        $_POST['skills'],
        $_POST['status'],
        $_POST['phone'],
        $_POST['email'],
        $me['id']
    ]);

    header("Location: profile.php?id=" . $me['id']);
    exit;
}

$bild = (!empty($me['bild_url']) && file_exists($me['bild_url']))
    ? $me['bild_url']
    : 'pics/default-avatar.png';
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Profil bearbeiten</title>
<link rel="stylesheet" href="header.css">
<link rel="stylesheet" href="styles.css">
<link rel="stylesheet" href="profile.css">
</head>
<body>
<?php include 'header.php'; ?>

<div class="profile-card" style="text-align:left;">
  <img src="<?= htmlspecialchars($bild) ?>" class="profile-avatar" alt="Profilbild" style="display:block;margin:0 auto;">
  <br>

  <form action="upload_avatar.php" method="post" enctype="multipart/form-data">
    <label>Profilbild ändern:</label>
    <input type="file" name="avatar" accept="image/*" class="form-dark">
    <button type="submit" class="button-primary button-main">Bild hochladen</button>
  </form>

  <form action="profile_edit.php" method="post" class="form-dark">
    <label>Beschreibung:</label>
    <textarea name="beschreibung" rows="4"><?= htmlspecialchars($me['beschreibung']) ?></textarea>

    <label>Skills:</label>
    <input type="text" name="skills" value="<?= htmlspecialchars($me['skills']) ?>">

    <label>Status:</label>
    <input type="text" name="status" value="<?= htmlspecialchars($me['status']) ?>">

    <label>Telefon:</label>
    <input type="text" name="phone" value="<?= htmlspecialchars($me['phone']) ?>">

    <label>E-Mail:</label>
    <input type="email" name="email" value="<?= htmlspecialchars($me['email']) ?>">

    <button type="submit" class="button-main">💾 Speichern</button>
    <a href="profile.php" class="button-sec" style="margin-left:10px;">Zurück</a>
  </form>
</div>

</body>
</html>
