<?php
/* Validation du placement des bateaux (API JSON)
   Reçoit la grille 2D, vérifie les règles FR/BE, sauvegarde en BDD
   Si tout le monde a placé => la partie passe en "in_progress" */
require __DIR__ . "/../vendor/autoload.php";
require __DIR__ . "/../config/db.php";
session_start();
header("Content-Type: application/json");

use App\Service\GameLogicService;
use App\Repository\GameRepository;
use App\Repository\BoardRepository;
use App\Middleware\AuthMiddleware;

AuthMiddleware::requireAuthJson();

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!$data || empty($data['game_id']) || empty($data['ships'])) {
    echo json_encode(["success" => false, "error" => "Paramètres invalides."]);
    exit;
}

$gameId = (int)$data['game_id'];
$board = $data['ships']; // Grille 2D
$playerId = $_SESSION['uid'];

$gameRepo  = new GameRepository($pdo);
$boardRepo = new BoardRepository($pdo);

// Vérifie la partie
$game = $gameRepo->findById($gameId);

if (!$game || $game['status'] !== 'placement') {
    echo json_encode(["success" => false, "error" => "Partie introuvable ou phase terminée."]);
    exit;
}

// 1. Détection des règles
$version = (int)($game['id_version'] ?? 1);
$isFrench = ($version !== 2); // 2 = BE, sinon FR

// 2. VALIDATION SERVEUR DES RÈGLES
if ($isFrench) {
    $placementError = GameLogicService::validatePlacementFrench($board);
    if ($placementError !== null) {
        echo json_encode(['success' => false, 'error' => $placementError]);
        exit;
    }
}

// Sauvegarde en BDD
$boardRepo->saveBoard($gameId, $playerId, $board);

// --- VÉRIFICATION DU DÉMARRAGE ---
$readyCount   = $boardRepo->countValidated($gameId);
$totalPlayers = $gameRepo->countPlayers($gameId);
if ($totalPlayers < 2) $totalPlayers = 2;

if ($readyCount >= $totalPlayers) {
    // START avec Timestamp UNIX (La correction cruciale pour le pending)
    $now = time(); 

    $pdo->prepare("
        UPDATE games
        SET 
            status='in_progress', 
            current_round=1,
            last_turn_timestamp=? 
        WHERE id_Game=?
    ")->execute([$now, $gameId]);
    
    // Tout le monde en vie
    $pdo->prepare("UPDATE game_players SET player_status='in_game' WHERE id_game=?")->execute([$gameId]);
    
    echo json_encode(["success" => true, "message" => "Placement validé — la partie démarre !", "game_started" => true]);
} else {
    echo json_encode(["success" => true, "message" => "Placement validé — en attente ($readyCount/$totalPlayers)...", "game_started" => false]);
}
?>