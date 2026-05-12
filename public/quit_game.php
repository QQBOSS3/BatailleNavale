<?php
/* Abandon en cours de partie (API JSON) - le joueur passe en "dead"
   et reçoit les récompenses de défaite. Peut déclencher la victoire adverse */
require __DIR__ . "/../vendor/autoload.php";
require __DIR__ . "/../config/db.php";
session_start();
header("Content-Type: application/json");

use App\Service\GameLogicService;
use App\Service\RewardService;
use App\Repository\GameRepository;
use App\Middleware\AuthMiddleware;

AuthMiddleware::requireAuthJson();

$raw    = file_get_contents("php://input");
$data   = json_decode($raw, true);
$gameId = (int)($data['game_id'] ?? 0);
$myId   = (int)$_SESSION['uid'];

if ($gameId <= 0) {
    echo json_encode(["success" => false, "error" => "ID invalide"]);
    exit;
}

$gameRepo      = new GameRepository($pdo);
$rewardService = new RewardService($pdo);

try {
    // Vérifier que la partie est en cours
    $status = $gameRepo->getStatus($gameId);

    if ($status !== 'in_progress') {
        echo json_encode(["success" => false, "error" => "Partie non active"]);
        exit;
    }

    // Passer le joueur en "dead"
    $gameRepo->setPlayerStatus($gameId, $myId, 'dead');

    // Vérifier s'il reste des survivants pour déclencher la victoire
    $survivors = $gameRepo->getSurvivors($gameId);

    $victory    = GameLogicService::checkVictory($survivors);
    $finished   = $victory['finished'];
    $winnerId   = $victory['winner_id'];
    $winnerTeam = $victory['winner_team'];

    if ($finished) {
        $gameRepo->setFinished($gameId, $winnerId);
    }

    // Le joueur qui quitte perd toujours → XP/Gold de défaite
    try {
        $rewardService->grantXp($myId, RewardService::XP_LOSE, RewardService::GOLD_LOSE, false);
    } catch (Exception $xpErr) {}

    // Recap pour l'écran de défaite
    $recap = null;
    try {
        $recap = $rewardService->buildRecap($myId, false);
    } catch (Exception $e) {}

    echo json_encode(["success" => true, "recap" => $recap]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>