<?php
/* Quitter une partie en phase de préparation
   Si c'est l'hôte qui part => toute la partie est supprimée
   Si c'est un joueur => juste son entrée est retirée */
require __DIR__ . "/../config/db.php";
session_start();

if (empty($_SESSION['uid'])) {
    header("Location: login.php");
    exit;
}

$playerId = (int)$_SESSION['uid'];

// 1. On cherche la partie active du joueur (en status 'preparation')
// On le cherche AUTOMATIQUEMENT, pas besoin d'envoyer l'ID en POST
$stmt = $pdo->prepare("
    SELECT gp.id_game, g.id_creator 
    FROM game_players gp
    JOIN games g ON gp.id_game = g.id_Game
    WHERE gp.id_player = ? AND g.status = 'preparation'
    LIMIT 1
");
$stmt->execute([$playerId]);
$info = $stmt->fetch();

if ($info) {
    $gameId = $info['id_game'];
    $creatorId = $info['id_creator'];

    if ($playerId == $creatorId) {
        // CAS 1 : C'est le CHEF (Host) qui quitte
        // => On supprime TOUT LE MONDE et LA PARTIE
        
        // Supprime les joueurs
        $pdo->prepare("DELETE FROM game_players WHERE id_game = ?")->execute([$gameId]);
        
        // Supprime la partie
        $pdo->prepare("DELETE FROM games WHERE id_Game = ?")->execute([$gameId]);
        
    } else {
        // CAS 2 : C'est un joueur normal
        // => On le SUPPRIME de la table (DELETE) pour ne pas laisser de fantôme
        $stmt = $pdo->prepare("DELETE FROM game_players WHERE id_game = ? AND id_player = ?");
        $stmt->execute([$gameId, $playerId]);
    }
}

// 2. Redirection immédiate vers l'accueil
header("Location: index.php");
exit;