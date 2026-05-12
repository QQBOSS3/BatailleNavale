<?php
/* Rejoindre une partie existante - gère l'assignation auto des équipes */
require __DIR__."/../config/db.php";
session_start();

if (empty($_SESSION['uid'])) {
    header("Location: login.php");
    exit;
}

$gameId = (int)($_GET['id'] ?? 0);

// Vérifier que la partie existe et est en préparation
$stmt = $pdo->prepare("SELECT * FROM games WHERE id_Game=? AND status='preparation'");
$stmt->execute([$gameId]);
$game = $stmt->fetch();

if (!$game) {
    exit("❌ Partie introuvable ou non disponible.");
}

// Vérifier si le joueur est déjà dans la partie
$stmt = $pdo->prepare("SELECT * FROM game_players WHERE id_game=? AND id_player=?");
$stmt->execute([$gameId, $_SESSION['uid']]);
$alreadyIn = $stmt->fetch();

if ($alreadyIn) {
    header("Location: game.php?id=".$gameId);
    exit;
}

$teamMode = (int)$game['id_team_mode'];
$gameType = (int)$game['id_game_type'];

// Déterminer le nombre max de joueurs par partie
switch ($teamMode) {
    case 1: $maxPlayers = 2; break; // 1 vs 1
    case 2: $maxPlayers = 4; break; // 2 vs 2
    case 3: $maxPlayers = 6; break; // 3 vs 3
    case 4: $maxPlayers = 8; break; // 4 vs 4
    default:
        // Battle Royale : limite haute
        $maxPlayers = 50; // à adapter
        break;
}

// Vérifier combien de joueurs sont déjà dans la partie
$stmt = $pdo->prepare("SELECT COUNT(*) FROM game_players WHERE id_game=?");
$stmt->execute([$gameId]);
$nbPlayers = (int)$stmt->fetchColumn();

if ($nbPlayers >= $maxPlayers) {
    exit("❌ La partie est déjà complète.");
}

// --- Assignation automatique d'équipe ---
$teamNumber = null;

if ($teamMode > 1) {
    // Récupérer la répartition actuelle
    $stmt = $pdo->prepare("SELECT team_number, COUNT(*) as nb
                           FROM game_players
                           WHERE id_game=?
                           GROUP BY team_number");
    $stmt->execute([$gameId]);
    $teams = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [team_number => nb]

    // Déterminer nb max par équipe
    $slotsPerTeam = $teamMode; // ex: 2 joueurs par équipe si 2vs2, 3 si 3vs3

    // Chercher une équipe avec une place dispo
    for ($i = 1; $i <= ($maxPlayers / $slotsPerTeam); $i++) {
        if (($teams[$i] ?? 0) < $slotsPerTeam) {
            $teamNumber = $i;
            break;
        }
    }
} elseif ($teamMode === 1) {
    // 1vs1 → tout le monde dans team 1 ou 2
    $stmt = $pdo->prepare("SELECT team_number, COUNT(*) as nb
                           FROM game_players
                           WHERE id_game=?
                           GROUP BY team_number");
    $stmt->execute([$gameId]);
    $teams = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $teamNumber = ($teams[1] ?? 0) <= ($teams[2] ?? 0) ? 1 : 2;
} else {
    // Battle Royale ou sans équipe → null
    $teamNumber = null;
}

// Ajouter le joueur
$stmt = $pdo->prepare("INSERT INTO game_players (id_game, id_player, player_status, team_number) VALUES (?, ?, 'in_game', ?)");
$stmt->execute([$gameId, $_SESSION['uid'], $teamNumber]);

// Redirection vers la partie
header("Location: game.php?id=".$gameId);
exit;
