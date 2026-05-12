<?php
/* Polling du statut de la partie (lobby.js) - renvoie l'état + la liste des joueurs
   Gère aussi la détection des kicks et les redirections selon la phase */
require __DIR__ . "/../config/db.php";
session_start();

header('Content-Type: application/json');

$gameId = (int)($_GET['id'] ?? 0);
if ($gameId <= 0) {
    echo json_encode(["ok" => false, "error" => "ID de partie invalide"]);
    exit;
}

// Vérifie que la partie existe
$stmt = $pdo->prepare("SELECT id_Game, status, current_round FROM games WHERE id_Game=? LIMIT 1");
$stmt->execute([$gameId]);
$game = $stmt->fetch();

if (!$game) {
    echo json_encode(["ok" => false, "error" => "Partie introuvable"]);
    exit;
}

$status = $game['status'];
$userId = $_SESSION['uid'] ?? null;
if (!$userId) {
    echo json_encode(["ok" => false, "error" => "Non connecté"]);
    exit;
}

// Vérifie la présence du joueur dans la partie
$stmt = $pdo->prepare("SELECT player_status, team_number FROM game_players WHERE id_game = ? AND id_player = ? LIMIT 1");
$stmt->execute([$gameId, $userId]);
$player = $stmt->fetch();

// --- Correction ici ---
if (!$player) {
    // Si la partie est encore en préparation → on laisse le joueur rejoindre normalement
    if (in_array($status, ['waiting', 'preparation', 'placement'])) {
        echo json_encode([
            "ok" => true,
            "status" => $status
        ]);
    } else {
        // Partie déjà en cours ou terminée → redirection
        echo json_encode([
            "ok" => true,
            "status" => "not_in_game",
            "redirect" => "index.php"
        ]);
    }
    exit;
}

// Si le joueur a quitté ou été expulsé
if ($player['player_status'] === 'left') {
    echo json_encode([
        "ok" => true,
        "status" => "kicked",
        "redirect" => "index.php"
    ]);
    exit;
}

$myTeam = $player['team_number'] ?? null;

// Joueurs dans le lobby (pour rafraichir sans recharger la page)
$stmt = $pdo->prepare("
    SELECT gp.id_player, u.Pseudo, u.Avatar, gp.player_status, st.image_prefix AS avatar_prefix
    FROM game_players gp
    JOIN users u ON gp.id_player = u.ID_Users
    LEFT JOIN skin_active sa ON sa.id_user = gp.id_player AND sa.category = 'avatar'
    LEFT JOIN skin_themes st ON st.id = sa.id_theme
    WHERE gp.id_game = ?
");
$stmt->execute([$gameId]);
$lobbyPlayers = $stmt->fetchAll();

$creatorStmt = $pdo->prepare("SELECT id_creator FROM games WHERE id_Game = ?");
$creatorStmt->execute([$gameId]);
$creatorId = (int)$creatorStmt->fetchColumn();

// Gestion des différents états de partie
switch ($status) {

    case 'waiting':
    case 'preparation':
        echo json_encode([
            "ok" => true,
            "status" => "preparation",
            "redirect" => "place_ships_view.php?id=" . $gameId,
            "players" => $lobbyPlayers,
            "creator_id" => $creatorId
        ]);
        break;

    case 'placement':
        echo json_encode([
            "ok" => true,
            "status" => "placement",
            "redirect" => "place_ships_view.php?id=" . $gameId
        ]);
        break;

    case 'in_progress':
        echo json_encode([
            "ok" => true,
            "status" => "in_progress",
            "redirect" => "play.php?id=" . $gameId
        ]);
        break;

    case 'finished':
        // Recherche du gagnant
        $stmt = $pdo->prepare("
            SELECT DISTINCT gp.team_number
            FROM game_players gp
            WHERE gp.id_game = ? AND gp.player_status != 'left'
        ");
        $stmt->execute([$gameId]);
        $winner = $stmt->fetchColumn();

        $victory = ($myTeam && $myTeam == $winner);

        echo json_encode([
            "ok" => true,
            "status" => "finished",
            "winner" => $winner,
            "victory" => $victory,
            "redirect" => "index.php"
        ]);
        break;

    default:
        echo json_encode([
            "ok" => true,
            "status" => $status ?? "unknown"
        ]);
        break;
}
