<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';

/** Gibt die aktive PDO-Verbindung zurück */
function vis_db() {
  global $pdo;
  return $pdo;
}

/** Aktuelle User-ID aus Session */
function vis_current_user_id() {
  return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/** Prüft, ob Benutzer Admin ist */
function vis_is_admin() {
  return !empty($_SESSION['is_admin']);
}

/** Liest oder erstellt den Benutzerstatus (Aktiv / Abwesend) */
function vis_get_user_status($user_id) {
  $pdo = vis_db();

  // Status aus DB lesen oder anlegen
  $stmt = $pdo->prepare("SELECT status, until, override_by_admin FROM user_status WHERE user_id=?");
  $stmt->execute([$user_id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    $pdo->prepare("INSERT INTO user_status (user_id, status) VALUES (?, 'Aktiv')")->execute([$user_id]);
    return ['status' => 'Aktiv', 'until' => null, 'override_by_admin' => 0];
  }

  // Automatische Rückstellung auf Aktiv, falls Abwesenheit abgelaufen ist
  if ((int)$row['override_by_admin'] === 0 && $row['status'] === 'Abwesend') {
    if ($row['until'] !== null && strtotime($row['until']) < time()) {
      $pdo->prepare("UPDATE user_status SET status='Aktiv', until=NULL WHERE user_id=?")->execute([$user_id]);
      $row['status'] = 'Aktiv';
      $row['until']  = null;
    }
  }

  return $row;
}

/**
 * Holt die gesperrten Bereiche aus calendar_settings.
 * Erstellt die Tabelle und den Default-Datensatz automatisch, falls nötig.
 */
function vis_get_restricted_areas() {
  $pdo = vis_db();

  // Tabelle absichern
  try {
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS calendar_settings (
        id INT PRIMARY KEY,
        restricted_areas JSON NULL
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
  } catch (Throwable $e) {
    // Fallback für alte MySQL-Versionen ohne JSON
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS calendar_settings (
        id INT PRIMARY KEY,
        restricted_areas TEXT NULL
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
  }

  // Sicherstellen, dass id=1 existiert
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM calendar_settings WHERE id=1");
  $stmt->execute();
  if ((int)$stmt->fetchColumn() === 0) {
    $pdo->prepare("INSERT INTO calendar_settings (id, restricted_areas) VALUES (1, '[]')")->execute();
  }

  // Gesperrte Bereiche laden
  $stmt = $pdo->prepare("SELECT restricted_areas FROM calendar_settings WHERE id=1");
  $stmt->execute();
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) return [];

  $json = $row['restricted_areas'];
  $arr = json_decode($json, true);
  return is_array($arr) ? $arr : [];
}

/**
 * Zugriffsbeschränkung basierend auf Abwesenheitsstatus.
 */
function enforce_area_access($areaKey, $exitOnBlock = true) {
  if (vis_is_admin()) return true; // Admins dürfen immer rein

  $uid = vis_current_user_id();
  if (!$uid) return true; // Kein Login -> frei

  $status = vis_get_user_status($uid);
  if ($status['status'] !== 'Abwesend') return true;

  $restricted = vis_get_restricted_areas();
  if (!in_array($areaKey, $restricted, true)) return true;

  // Overlay bei gesperrtem Zugriff
  $untilTxt = $status['until'] ? date('d.m.Y H:i', strtotime($status['until'])) : 'unbekannt';
  echo '<div style="
      position:fixed;inset:0;background:rgba(0,0,0,0.85);
      display:flex;align-items:center;justify-content:center;z-index:99999;">
      <div style="
        max-width:520px;width:92%;padding:28px;border-radius:16px;
        background:rgba(20,20,20,0.95);border:2px solid #39ff14;
        color:#fff;text-align:center;box-shadow:0 0 24px rgba(57,255,20,.5)">
        <h3 style="margin:0 0 10px;font-weight:800;color:#a8ffba">Zugriff gesperrt</h3>
        <p>Dein Status ist aktuell <b style="color:#76ff65">Abwesend</b>.</p>
        <p style="opacity:.85">Bis voraussichtlich <b>'.$untilTxt.'</b>.</p>
        <p style="margin-top:10px;">Der Bereich <b>'.htmlspecialchars($areaKey).'</b> ist aktuell gesperrt.</p>
        <a href="/index.php" style="
          display:inline-block;margin-top:12px;padding:10px 16px;border-radius:10px;
          background:linear-gradient(90deg,#39ff14,#76ff65);color:#fff;
          text-decoration:none;font-weight:800;">Zur Startseite</a>
      </div></div>';
  if ($exitOnBlock) exit;
  return false;
}
?>
