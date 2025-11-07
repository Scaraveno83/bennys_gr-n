<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once $_SERVER['DOCUMENT_ROOT'].'/includes/db.php';

// ✅ User-ID prüfen
if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok'=>false,'error'=>'not_authenticated']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// ✅ Berechtigung ermitteln (kann löschen?)
$canDeleteGlobal = false;

// Admin → darf immer
if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $canDeleteGlobal = true;
} else {
    // Rang prüfen
    $stmtR = $pdo->prepare("
        SELECT m.rang
        FROM mitarbeiter m
        JOIN user_accounts u ON u.mitarbeiter_id = m.id
        WHERE u.id = ?
    ");
    $stmtR->execute([$userId]);
    $rang = $stmtR->fetchColumn();

    $berechtigte = [
        'Geschäftsführung',
        'Stv. Geschäftsleitung',
        'Personalleitung'
    ];

    if ($rang && in_array($rang, $berechtigte)) {
        $canDeleteGlobal = true;
    }
}

// ✅ User als aktiv markieren
$pdo->prepare("
    INSERT INTO chat_online (user_id, last_active)
    VALUES (?, NOW())
    ON DUPLICATE KEY UPDATE last_active = NOW()
")->execute([$userId]);

// ✅ Benutzer als offline werten, wenn 45 Sekunden keine Aktivität
$pdo->exec("DELETE FROM chat_online WHERE last_active < (NOW() - INTERVAL 45 SECOND)");


// ✅ Neue Nachrichten seit letzter ID holen (mit Avatar)
$since_id = isset($_GET['since_id']) ? (int)$_GET['since_id'] : 0;

$stmt = $pdo->prepare("
    SELECT
        c.id,
        c.user_id,
        c.body,
        c.type,
        c.file_path,
        c.duration_seconds,
        DATE_FORMAT(c.created_at, '%Y-%m-%d %H:%i:%s') AS created_at,
        COALESCE(ma.name, u.username, 'Unbekannt') AS display_name,
        CONCAT('/', IFNULL(NULLIF(ma.bild_url, ''), 'pics/default-avatar.png')) AS avatar_url   -- ✅ Avatar dazu
    FROM chat_messages c
    LEFT JOIN user_accounts u ON u.id = c.user_id
    LEFT JOIN mitarbeiter ma ON ma.id = u.mitarbeiter_id
    WHERE c.id > ?
    ORDER BY c.id ASC
");
$stmt->execute([$since_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ can_delete setzen + Avatar prüfen
foreach ($messages as &$msg) {
    $msg['can_delete'] = ($canDeleteGlobal || $msg['user_id'] == $userId) ? 1 : 0;

    if (empty($msg['avatar_url']) || !file_exists($_SERVER['DOCUMENT_ROOT'].'/'.$msg['avatar_url'])) {
        $msg['avatar_url'] = 'pics/default-avatar.png';
    }
}

// ✅ Online-Liste holen
$online = $pdo->query("
    SELECT
        o.user_id,
        COALESCE(ma.name, u.username, 'Unbekannt') AS display_name,
        CONCAT('/', IFNULL(NULLIF(ma.bild_url, ''), 'pics/default-avatar.png')) AS avatar_url   -- ✅ Online-Liste auch mit Bild
    FROM chat_online o
    LEFT JOIN user_accounts u ON u.id = o.user_id
    LEFT JOIN mitarbeiter ma ON ma.id = u.mitarbeiter_id
    ORDER BY display_name ASC
")->fetchAll(PDO::FETCH_ASSOC);


// ✅ Antwort
echo json_encode([
    'ok' => true,
    'messages' => $messages,
    'online' => $online
]);
exit;
