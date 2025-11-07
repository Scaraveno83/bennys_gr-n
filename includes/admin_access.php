<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

// Standardmäßig: Kein Zugriff
$adminAccess = false;

// 1️⃣ Normale Admins
if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $adminAccess = true;
}

// 2️⃣ Mitarbeiter mit hohem Rang (Geschäftsführung, Stv., Personalleitung)
elseif (!empty($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("
        SELECT m.rang 
        FROM mitarbeiter m
        JOIN user_accounts u ON u.mitarbeiter_id = m.id
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $rang = $stmt->fetchColumn();

    $erlaubteRollen = [
        'Geschäftsführung',
        'Stv. Geschäftsleitung',
        'Personalleitung'
    ];

    if ($rang && in_array($rang, $erlaubteRollen)) {
        $adminAccess = true;
    }
}

// 3️⃣ Wenn kein Zugriff → Weiterleitung
if (!$adminAccess) {
    header('Location: ../index.php');
    exit;
}
