<?php
/* Sauvegarde des options utilisateur (volume, langue, daltonien, thème) */
require __DIR__."/../config/db.php";
session_start();

if (empty($_SESSION['uid'])) {
    http_response_code(403);
    exit("Non autorisé");
}

$userId = $_SESSION['uid'];

$volume = isset($_POST['volume']) ? (int)$_POST['volume'] : 50;
$lang = $_POST['languages'] ?? 'fr';
$colorblind = isset($_POST['colorblind']) ? 1 : 0;
$theme = $_POST['theme'] ?? 'normal';

$stmt = $pdo->prepare("SELECT ID_Option FROM `option` WHERE ID_Users=?");
$stmt->execute([$userId]);
$existing = $stmt->fetch();

if ($existing) {
    $stmt = $pdo->prepare("UPDATE `option` 
                           SET Volume=?, Languages=?, Colorblind=?, Theme=? 
                           WHERE ID_Users=?");
    $stmt->execute([$volume, $lang, $colorblind, $theme, $userId]);
    echo "Mise à jour OK ✅";
} else {
    $stmt = $pdo->prepare("INSERT INTO `option` (Volume, Languages, Colorblind, Theme, ID_Users) 
                           VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$volume, $lang, $colorblind, $theme, $userId]);
    echo "Insertion OK ✅";
}
