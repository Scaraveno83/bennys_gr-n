<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'].'/includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 120;

// ✅ eingeloggter Benutzer
$currentUserId = $_SESSION['user_id'] ?? 0;

// ✅ Rolle / Mitarbeiter-Rang bestimmen
$canDelete = false;

if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $canDelete = true;
}
else {
    $stmt = $pdo->prepare("
        SELECT m.rang
        FROM mitarbeiter m
        JOIN user_accounts u ON u.mitarbeiter_id = m.id
        WHERE u.id = ?
    ");
    $stmt->execute([$currentUserId]);
    $rang = $stmt->fetchColumn();

    $berechtigte = [
        'Geschäftsführung',
        'Stv. Geschäftsleitung',
        'Personalleitung'
    ];

    if ($rang && in_array($rang, $berechtigte)) {
        $canDelete = true;
    }
}

// ✅ Nachrichten + Avatar laden
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
        CONCAT('/', IFNULL(NULLIF(ma.bild_url, ''), 'pics/default-avatar.png')) AS avatar_url  -- ✅ Avatar hier
    FROM chat_messages c
    LEFT JOIN user_accounts u ON u.id = c.user_id
    LEFT JOIN mitarbeiter ma ON ma.id = u.mitarbeiter_id
    ORDER BY c.id ASC
    LIMIT ?");
$stmt->execute([$limit]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Flag „kann löschen“ hinzufügen
foreach ($messages as &$msg) {
    $msg['can_delete'] = ($canDelete || $msg['user_id'] == $currentUserId) ? 1 : 0;

    // ✅ Avatar-Pfad final normalisieren
    if (empty($msg['avatar_url']) || !file_exists($_SERVER['DOCUMENT_ROOT'].'/'.$msg['avatar_url'])) {
        $msg['avatar_url'] = 'pics/default-avatar.png';
    }
}

echo json_encode([
    'ok' => true,
    'messages' => $messages
]);
exit;
