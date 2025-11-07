<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

session_start();
require_once '../includes/db.php';

// ✅ Bereits eingeloggt? Immer zur Startseite leiten
if (!empty($_SESSION['user_role']) || !empty($_SESSION['admin_logged_in'])) {
  header('Location: ../index.php');
  exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = trim($_POST['password'] ?? '');

  // Nutzer aus DB abrufen
  $stmt = $pdo->prepare("SELECT * FROM user_accounts WHERE username = ? AND active = 1 LIMIT 1");
  $stmt->execute([$username]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  // Passwort prüfen
  if ($user && password_verify($password, $user['password_hash'])) {
    session_regenerate_id(true);

    // 🔐 Session-Daten
    $_SESSION['user_id']   = (int)$user['id'];
    $_SESSION['user_role'] = $user['role'] ?? 'user';
    $_SESSION['admin_username'] = $username;

    // Mitarbeitername laden (falls verknüpft)
    if (!empty($user['mitarbeiter_id'])) {
      $stm = $pdo->prepare("SELECT name FROM mitarbeiter WHERE id = ?");
      $stm->execute([$user['mitarbeiter_id']]);
      $m = $stm->fetch(PDO::FETCH_ASSOC);
      $_SESSION['mitarbeiter_name'] = $m['name'] ?? $username;
    } else {
      $_SESSION['mitarbeiter_name'] = $username;
    }

    // Admin-Flag setzen
    $_SESSION['admin_logged_in'] = ($user['role'] === 'admin');

    // 🚀 Immer zur Startseite leiten – egal ob Admin oder User
    header('Location: ../index.php');
    exit;

  } else {
    $error = '❌ Benutzername oder Passwort falsch!';
  }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | Benny’s Werkstatt</title>

<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../styles.css">
<link rel="stylesheet" href="../header.css">

<style>
body { display: flex; align-items: center; justify-content: center; height: 100vh; background: #0b0b0b; color: #fff; margin: 0; }
.login-container { background: rgba(20,20,20,0.9); border: 1px solid rgba(57,255,20,0.5); border-radius: 15px; box-shadow: 0 0 25px rgba(57,255,20,0.4); padding: 40px; width: 360px; text-align: center; }
.login-container h2 { font-size: 1.8rem; margin-bottom: 20px; color: #39ff14; text-shadow: 0 0 15px #39ff14; }
.login-container input { width: 100%; margin: 10px 0; padding: 12px; border: none; border-radius: 8px; background: #111; color: #fff; }
.login-container button { width: 100%; background: linear-gradient(90deg, #39ff14, #76ff65); color: #fff; border: none; padding: 12px; border-radius: 8px; cursor: pointer; margin-top: 15px; font-weight: bold; }
.error { color: #76ff65; margin-bottom: 15px; font-weight: 600; }
.login-footer { margin-top: 15px; font-size: 0.9rem; color: #aaa; }
</style>
</head>
<body>

<div class="login-container">
  <h2>🔒 Login</h2>

  <?php if ($error): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
  <?php endif; ?>

  <form method="POST" autocomplete="off">
    <input type="text" name="username" placeholder="Benutzername" required>
    <input type="password" name="password" placeholder="Passwort" required>
    <button type="submit">Einloggen</button>
  </form>

  <div class="login-footer">
    <p>&copy; <?= date('Y') ?> Benny’s Werkstatt</p>
    <a href="../index.php">← Zur Startseite</a>
  </div>
</div>

</body>
</html>
