<?php
// Zugriffsschutz für Admin-Seiten
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}
?>
