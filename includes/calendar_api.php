<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/visibility.php';

// Eingabe verarbeiten (POST-JSON oder GET-Query)
$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true);
$action = $input['action'] ?? ($_GET['action'] ?? '');

if (!$action) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Missing action']);
  exit;
}

switch ($action) {

  /* =========================================
   * 📚 Gründe laden
   * ========================================= */
  case 'reasons':
    $showAll = isset($_GET['all']);
    $sql = "SELECT id, label, color, icon, active FROM calendar_reasons";
    if (!$showAll) {
      $sql .= " WHERE active = 1";
    }
    $sql .= " ORDER BY id ASC";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'reasons' => $rows]);
    break;

  /* =========================================
   * ➕ Grund hinzufügen
   * ========================================= */
  case 'add_reason':
    $label = trim($input['label'] ?? '');
    $color = trim($input['color'] ?? '#39ff14');
    $icon  = trim($input['icon'] ?? '');
    if ($label === '') {
      echo json_encode(['ok' => false, 'error' => 'Missing label']);
      exit;
    }

    try {
      $stmt = $pdo->prepare("
        INSERT INTO calendar_reasons (label, color, icon, active)
        VALUES (?, ?, ?, 1)
      ");
      $stmt->execute([$label, $color, $icon]);
      echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
      echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    break;

  /* =========================================
   * 🚫 Grund deaktivieren
   * ========================================= */
  case 'delete_reason':
    $id = (int)($input['id'] ?? 0);
    if ($id > 0) {
      $pdo->prepare("UPDATE calendar_reasons SET active = 0 WHERE id = ?")->execute([$id]);
      echo json_encode(['ok' => true]);
    } else {
      echo json_encode(['ok' => false, 'error' => 'Invalid ID']);
    }
    break;

  /* =========================================
   * ♻️ Grund reaktivieren
   * ========================================= */
  case 'restore_reason':
    $id = (int)($input['id'] ?? 0);
    if ($id > 0) {
      $pdo->prepare("UPDATE calendar_reasons SET active = 1 WHERE id = ?")->execute([$id]);
      echo json_encode(['ok' => true]);
    } else {
      echo json_encode(['ok' => false, 'error' => 'Invalid ID']);
    }
    break;

  /* =========================================
   * ⚙️ Gesperrte Bereiche abrufen
   * ========================================= */
  case 'settings':
    $r = $pdo->query("SELECT restricted_areas FROM calendar_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    $arr = json_decode($r['restricted_areas'] ?? '[]', true);
    echo json_encode(['ok' => true, 'restricted_areas' => $arr]);
    break;

  /* =========================================
   * 💾 Gesperrte Bereiche speichern
   * ========================================= */
  case 'save_settings':
    $areas = $input['restricted_areas'] ?? [];
    if (!is_array($areas)) $areas = [];
    $json = json_encode($areas, JSON_UNESCAPED_UNICODE);
    $pdo->prepare("UPDATE calendar_settings SET restricted_areas = ? WHERE id = 1")->execute([$json]);
    echo json_encode(['ok' => true]);
    break;

  /* =========================================
   * 🛠️ Benutzerstatus setzen (Admin override)
   * ========================================= */
  case 'set_status':
    $user_id = (int)($input['user_id'] ?? 0);
    $status  = $input['status'] ?? 'Aktiv';
    $until   = $input['until'] ?? null;
    if ($user_id <= 0) {
      echo json_encode(['ok' => false, 'error' => 'Invalid user_id']);
      exit;
    }

    $stmt = $pdo->prepare("
      INSERT INTO user_status (user_id, status, until, override_by_admin)
      VALUES (?, ?, ?, 1)
      ON DUPLICATE KEY UPDATE status = VALUES(status), until = VALUES(until), override_by_admin = 1
    ");
    $stmt->execute([$user_id, $status, $until]);
    echo json_encode(['ok' => true]);
    break;

  /* =========================================
   * 👥 Benutzerliste (Admin Dropdown)
   * ========================================= */
  case 'users':
    $stmt = $pdo->query("
      SELECT u.id, 
             COALESCE(m.name, u.username) AS name,
             m.rang
      FROM user_accounts u
      LEFT JOIN mitarbeiter m ON u.mitarbeiter_id = m.id
      ORDER BY name ASC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'users' => $users]);
    break;

  /* =========================================
   * 🔍 Status des eingeloggten Users abrufen
   * ========================================= */
  case 'status':
    $uid = $_SESSION['user_id'] ?? null;
    if (!$uid) {
      echo json_encode(['ok' => false, 'error' => 'Nicht eingeloggt']);
      exit;
    }

    $stmt = $pdo->prepare("SELECT status, until FROM user_status WHERE user_id = ?");
    $stmt->execute([$uid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      $pdo->prepare("INSERT INTO user_status (user_id, status) VALUES (?, 'Aktiv')")->execute([$uid]);
      $row = ['status' => 'Aktiv', 'until' => null];
    }

    echo json_encode(['ok' => true, 'status' => $row['status'], 'until' => $row['until']]);
    break;

  /* =========================================
   * 📅 Eigene Abwesenheiten abrufen
   * ========================================= */
  case 'my_absences':
    $uid = $_SESSION['user_id'] ?? null;
    if (!$uid) {
      echo json_encode(['ok' => false, 'error' => 'Nicht eingeloggt']);
      exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM calendar_absences WHERE user_id = ? ORDER BY start_date DESC");
    $stmt->execute([$uid]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'items' => $data]);
    break;

  /* =========================================
   * 📋 Alle Abwesenheiten (Admin-Übersicht)
   * ========================================= */
  case 'all_absences':
    $stmt = $pdo->query("
      SELECT a.*, 
             COALESCE(m.name, u.username) AS name
      FROM calendar_absences a
      LEFT JOIN user_accounts u ON u.id = a.user_id
      LEFT JOIN mitarbeiter m ON u.mitarbeiter_id = m.id
      ORDER BY a.start_date DESC
    ");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'items' => $items]);
    break;

  /* =========================================
   * ➕ Neue Abwesenheit eintragen
   * ========================================= */
  case 'add_absence':
    $uid = $_SESSION['user_id'] ?? null;
    if (!$uid) {
      echo json_encode(['ok' => false, 'error' => 'Nicht eingeloggt']);
      exit;
    }

    $start   = $input['start_date'] ?? null;
    $end     = $input['end_date'] ?? null;
    $reasons = $input['reasons'] ?? [];
    $note    = trim($input['note'] ?? '');

    if (!$start || !$end || empty($reasons)) {
      echo json_encode(['ok' => false, 'error' => 'Unvollständige Angaben']);
      exit;
    }

    $stmt = $pdo->prepare("
      INSERT INTO calendar_absences (user_id, start_date, end_date, reasons_json, note)
      VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$uid, $start, $end, json_encode($reasons, JSON_UNESCAPED_UNICODE), $note]);

    // Status automatisch auf "Abwesend" setzen
    $pdo->prepare("
      INSERT INTO user_status (user_id, status, until, override_by_admin)
      VALUES (?, 'Abwesend', ?, 0)
      ON DUPLICATE KEY UPDATE status='Abwesend', until=VALUES(until), override_by_admin=0
    ")->execute([$uid, $end]);

    echo json_encode(['ok' => true]);
    break;

  /* =========================================
   * ❌ Unbekannte Aktion
   * ========================================= */
  default:
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action: ' . $action]);
    break;
}
