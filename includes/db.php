<?php
// includes/db.php
$host = 'localhost';       // DB-Host (meist localhost)
$db   = 'db_452177_2';// Datenbankname
$user = 'USER452177_3';            // dein DB-User
$pass = '15118329112006';                // dein Passwort
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Verbindungsfehler: ' . $e->getMessage());
}
?>
