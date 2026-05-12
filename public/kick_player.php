<?php
/* Expulser un joueur du lobby - seul l'hôte peut le faire */
require __DIR__."/../vendor/autoload.php";
require __DIR__."/../config/db.php";
session_start();

use App\Repository\GameRepository;
use App\Middleware\AuthMiddleware;

AuthMiddleware::requireAuth();

$gameId = (int)($_POST['game_id'] ?? 0);
$userId = (int)($_POST['player_id'] ?? 0);

if (!$gameId || !$userId) {
    exit("Requête invalide.");
}

$gameRepo = new GameRepository($pdo);

// Vérifier que je suis bien l'host
$creatorId = $gameRepo->getCreatorId($gameId);

if ($creatorId !== null && $creatorId === (int)$_SESSION['uid']) {
    $gameRepo->setPlayerStatus($gameId, $userId, 'left');
}

header("Location: game.php?id=".$gameId);
exit;
