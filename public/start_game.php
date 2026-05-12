<?php
/* Lancement de la partie par l'hôte - passe le statut de "preparation" à "placement"
   Vérifie que le nombre de joueurs requis est atteint selon le mode (1v1, 2v2, BR...) */
require __DIR__."/../vendor/autoload.php";
require __DIR__."/../config/db.php";
session_start();

use App\Repository\GameRepository;
use App\Middleware\AuthMiddleware;

AuthMiddleware::requireAuth();

$gameRepo = new GameRepository($pdo);

// Récupérer l'ID de la partie depuis POST (préférable) ou GET
$gameId = (int)($_POST['game_id'] ?? $_GET['id'] ?? 0);
if ($gameId <= 0) {
    exit("Requête invalide (ID manquant).");
}

// Vérifier si la partie existe
$game = $gameRepo->findById($gameId);
if (!$game) {
    exit("Partie introuvable.");
}

// Seul l'host peut lancer
if ((int)$game['id_creator'] !== (int)$_SESSION['uid']) {
    exit("Seul l'hôte peut démarrer la partie.");
}

// Doit être en préparation
if ($game['status'] !== 'preparation') {
    // Si elle est déjà lancée, on renvoie "ok" pour que le JS redirige
    if ($game['status'] === 'placement' || $game['status'] === 'in_progress') {
        echo "ok";
        exit;
    }
    exit("La partie n'est pas disponible pour démarrer.");
}

// Compter joueurs actifs (pas 'left')
$activePlayers = $gameRepo->countActivePlayers($gameId);

// Déterminer le minimum requis selon le mode
$teamMode = (int)$game['id_team_mode'];
$minPlayers = 2; // par défaut

switch ($teamMode) {
    case 1: $minPlayers = 2; break; // 1v1
    case 2: $minPlayers = 4; break; // 2v2
    case 3: $minPlayers = 6; break; // 3v3
    case 4: $minPlayers = 8; break; // 4v4
    default:
        // Battle Royale : exige au moins 2 joueurs
        $minPlayers = 2;
        break;
}

if ($activePlayers < $minPlayers) {
    exit("Pas assez de joueurs pour démarrer (".$activePlayers." / ".$minPlayers.").");
}

// Basculer en phase de placement
$gameRepo->updateStatus($gameId, 'placement');

// CORRECTION ICI :
// On ne redirige pas en PHP. On renvoie juste "ok" au Javascript.
echo "ok";
exit;