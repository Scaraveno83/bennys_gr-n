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
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 12px 26px;
  border-radius: 12px;
  border: 1px solid var(--button-border, rgba(57,255,20,0.35));
  background: var(--button-bg, rgba(57,255,20,0.1));
  color: var(--button-color, rgba(210,255,215,0.9));
  text-decoration: none;
  font-weight: 700;
  transition: var(--transition, all 0.25s ease);
  box-shadow: none;
}

.logout-box a:hover,
.logout-box a:focus-visible {
  background: var(
    --button-hover-bg,
    linear-gradient(132deg, rgba(42,217,119,0.34), rgba(118,255,101,0.26))
  );
  color: var(--button-hover-color, #041104);
  border-color: var(--button-hover-border, rgba(42,217,119,0.6));
  box-shadow: var(
    --button-hover-shadow,
    0 18px 36px rgba(17,123,69,0.26), inset 0 0 22px rgba(118,255,101,0.24)
  );
  transform: var(--button-hover-transform, translateY(-3px) scale(1.02));
  outline: none;
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
