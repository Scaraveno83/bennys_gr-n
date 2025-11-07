<?php
session_start();

// ✅ Alle Session-Daten löschen
$_SESSION = [];

// ✅ Session-Cookie entfernen
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// ✅ Session endgültig zerstören
session_destroy();
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Abmeldung | Benny’s Werkstatt</title>

<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../styles.css">

<style>
body {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100vh;
  margin: 0;
  background: radial-gradient(circle at center, #1a1a1a 0%, #0b0b0b 100%);
  color: #fff;
  font-family: 'Roboto', sans-serif;
  overflow: hidden;
}

.logout-box {
  text-align: center;
  background: rgba(20,20,20,0.9);
  border: 1px solid rgba(57,255,20,0.4);
  border-radius: 15px;
  box-shadow: 0 0 25px rgba(57,255,20,0.3);
  padding: 50px 40px;
  width: 380px;
  animation: fadeIn 1s ease-out;
}

.logout-box h2 {
  font-size: 1.8rem;
  color: #39ff14;
  text-shadow: 0 0 15px rgba(57,255,20,0.8);
  margin-bottom: 10px;
}

.logout-box p {
  font-size: 1rem;
  color: #ddd;
  margin-bottom: 30px;
}

.logout-box a {
  display: inline-block;
  padding: 10px 25px;
  color: #fff;
  background: linear-gradient(90deg, #39ff14, #76ff65);
  border-radius: 10px;
  text-decoration: none;
  font-weight: bold;
  transition: 0.3s;
  box-shadow: 0 0 12px rgba(57,255,20,0.4);
}

.logout-box a:hover {
  transform: scale(1.08);
  box-shadow: 0 0 25px rgba(57,255,20,0.7);
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>

<script>
// 🔁 Automatische Weiterleitung nach 3 Sekunden
setTimeout(() => {
  window.location.href = "../index.php";
}, 3000);
</script>
</head>
<body>

<div class="logout-box">
  <h2>👋 Erfolgreich abgemeldet</h2>
  <p>Du wirst gleich zur Startseite weitergeleitet...</p>
  <a href="../index.php">Jetzt zur Startseite</a>
</div>

</body>
</html>
