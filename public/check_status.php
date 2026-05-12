<?php
/* Vérif rapide : est-ce que le joueur est toujours dans la partie ?
   Utilisé pour détecter un kick côté client */
require __DIR__."/../config/db.php";
session_start();

$gameId = (int)($_GET['id'] ?? 0);
$userId = (int)($_SESSION['uid'] ?? 0);

if (!$userId || !$gameId) {
    echo json_encode(["inGame" => false]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT player_status 
    FROM game_players 
    WHERE id_game=? AND id_player=? 
    LIMIT 1
");
$stmt->execute([$gameId, $userId]);
$row = $stmt->fetch();

if (!$row || $row['player_status'] === 'left') {
    echo json_encode(["inGame" => false]);
} else {
    echo json_encode(["inGame" => true]);
}
