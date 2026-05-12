<?php
/* Endpoint de tir - reçoit coordonnées + cible, enregistre le tir en "pending"
   La résolution (hit/miss) se fait dans resolve_turn.php */
require __DIR__ . "/../vendor/autoload.php";
require __DIR__ . "/../config/db.php";
session_start();
header("Content-Type: application/json");

use App\Repository\GameRepository;
use App\Middleware\AuthMiddleware;

AuthMiddleware::requireAuthJson();

$gameId   = (int)($_POST['game_id'] ?? 0);
$x        = (int)($_POST['x'] ?? -1);
$y        = (int)($_POST['y'] ?? -1);
$myId     = $_SESSION['uid'];

$gameRepo = new GameRepository($pdo);

// 1. Vérif état de la partie
$gameStatus = $gameRepo->getStatus($gameId);

if ($gameStatus !== 'in_progress') {
    echo json_encode(["success" => false, "error" => "Partie non active."]);
    exit;
}

$targetId = (int)($_POST['target_id'] ?? 0);

// Si pas de target_id envoyé (1v1 legacy), on auto-détecte
if (!$targetId) {
    $stmt = $pdo->prepare("
        SELECT id_player FROM game_players 
        WHERE id_game=? AND id_player != ? AND player_status='in_game' 
        LIMIT 1
    ");
    $stmt->execute([$gameId, $myId]);
    $targetId = (int)$stmt->fetchColumn();
}

if (!$targetId) {
    echo json_encode(["success" => false, "error" => "Cible invalide."]);
    exit;
}

// 2. Validation : la cible est bien un ennemi
$stmt = $pdo->prepare("
    SELECT gp_me.team_number, gp_target.team_number as target_team, gp_target.player_status
    FROM game_players gp_me
    JOIN game_players gp_target ON gp_target.id_game = gp_me.id_game
    WHERE gp_me.id_game=? AND gp_me.id_player=? AND gp_target.id_player=?
");
$stmt->execute([$gameId, $myId, $targetId]);
$check = $stmt->fetch();

if (!$check || $check['player_status'] === 'dead') {
    echo json_encode(["success" => false, "error" => "Cible morte ou invalide."]);
    exit;
}

// Interdire de tirer sur soi-même
if ($targetId === (int)$myId) {
    echo json_encode(["success" => false, "error" => "Vous ne pouvez pas vous tirer dessus."]);
    exit;
}

// En mode équipe, interdire de tirer sur un coéquipier
if ($check['team_number'] !== null && $check['team_number'] === $check['target_team']) {
    echo json_encode(["success" => false, "error" => "Impossible de tirer sur un coéquipier !"]);
    exit;
}

// 3. ✅ CORRECTION : vérifier uniquement MES tirs en double, pas ceux des autres joueurs
$stmt = $pdo->prepare("SELECT id_shot FROM shots WHERE id_game=? AND id_player=? AND target_id=? AND target_x=? AND target_y=?");
$stmt->execute([$gameId, $myId, $targetId, $x, $y]);
if ($stmt->fetch()) {
    echo json_encode(["success" => false, "error" => "Vous avez déjà tiré sur cette case."]);
    exit;
}

// 4. Enregistrement du tir en pending
$stmt = $pdo->prepare("
    INSERT INTO shots (id_game, id_player, target_id, target_x, target_y, result, state, created_at)
    VALUES (?, ?, ?, ?, ?, NULL, 'pending', NOW())
");
$stmt->execute([$gameId, $myId, $targetId, $x, $y]);

echo json_encode([
    "success" => true,
    "message" => "Ordre confirmé. En attente de résolution..."
]);
?>