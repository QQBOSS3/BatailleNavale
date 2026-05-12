<?php
/* Résolution de fin de tour - appelé par le timer côté client
   Étapes : résoudre les tirs pending → détecter bateaux coulés → vérifier morts → check victoire */
require __DIR__ . "/../vendor/autoload.php";
require __DIR__ . "/../config/db.php";
require __DIR__ . "/../config/constants.php";
session_start();
header("Content-Type: application/json");

use App\Service\GameLogicService;
use App\Service\RewardService;
use App\Repository\GameRepository;
use App\Repository\BoardRepository;
use App\Middleware\AuthMiddleware;

AuthMiddleware::requireAuthJson();

$raw    = file_get_contents("php://input");
$data   = json_decode($raw, true);
$gameId = (int)($data['game_id'] ?? 0);

if ($gameId <= 0) exit(json_encode(["error" => "ID invalide"]));

$gameRepo      = new GameRepository($pdo);
$boardRepo     = new BoardRepository($pdo);
$rewardService = new RewardService($pdo);

try {
    // ---------------------------------------------------------
    // 0. Lire l'état de la partie
    // ---------------------------------------------------------
    $row = $gameRepo->findById($gameId);

    if (!$row) exit(json_encode(["error" => "Partie introuvable"]));

    if ($row['status'] === 'finished') {
        exit(json_encode(["finished" => true, "message" => "Partie déjà terminée"]));
    }

    $lastTurn  = (int)$row['last_turn_timestamp'];
    $creatorId = (int)$row['id_creator'];
    $now       = time();
    $elapsed   = $now - $lastTurn;

    if ($elapsed < ROUND_DURATION) {
        exit(json_encode([
            "finished"    => false,
            "message"     => "Trop tôt...",
            "wait"        => ROUND_DURATION - $elapsed,
            "round_start" => $lastTurn
        ]));
    }

    // ---------------------------------------------------------
    // 1. RÉSOLUTION DES TIRS (pending → resolved)
    // ---------------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT id_shot, target_id, target_x, target_y
        FROM shots
        WHERE id_game = ? AND state = 'pending'
    ");
    $stmt->execute([$gameId]);
    $pendingShots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Charger tous les plateaux en une seule requête
    $boardCache = $boardRepo->getAllBoards($gameId);
    foreach ($pendingShots as $shot) {
        $pid = $shot['target_id'];
        $board  = $boardCache[$pid];
        $val    = (int)($board[$shot['target_y']][$shot['target_x']] ?? 0);
        $result = $val > 0 ? 'hit' : 'miss';

        $pdo->prepare("UPDATE shots SET result=?, state='resolved' WHERE id_shot=?")
            ->execute([$result, $shot['id_shot']]);
    }

    // ---------------------------------------------------------
    // 3. BATEAUX COULÉS : hit → sunk si tout le bateau est touché
    // ---------------------------------------------------------
    $activePlayers = $gameRepo->getAlivePlayerIds($gameId);

    foreach ($activePlayers as $pid) {
        $board = $boardCache[$pid] ?? [];

        $s = $pdo->prepare("SELECT target_x, target_y FROM shots WHERE id_game=? AND target_id=? AND result IN ('hit','sunk')");
        $s->execute([$gameId, $pid]);
        $hitSet = [];
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $h) {
            $hitSet[$h['target_x'] . '-' . $h['target_y']] = true;
        }

        $shipGroups = GameLogicService::groupShipCells($board);

        foreach ($shipGroups as $cells) {
            $allHit = true;
            foreach ($cells as $c) {
                if (!isset($hitSet[$c['x'] . '-' . $c['y']])) { $allHit = false; break; }
            }
            if ($allHit) {
                foreach ($cells as $c) {
                    $pdo->prepare("
                        UPDATE shots SET result='sunk'
                        WHERE id_game=? AND target_id=? AND target_x=? AND target_y=? AND result='hit'
                    ")->execute([$gameId, $pid, $c['x'], $c['y']]);
                }
            }
        }
    }

    // ---------------------------------------------------------
    // 4. JOUEURS MORTS
    // ---------------------------------------------------------
    foreach ($activePlayers as $pid) {
        $board = $boardCache[$pid] ?? [];

        $totalLife = 0;
        foreach ($board as $rowB) {
            foreach ($rowB as $cell) {
                if ($cell > 0) $totalLife++;
            }
        }

        $s = $pdo->prepare("SELECT COUNT(*) FROM shots WHERE id_game=? AND target_id=? AND result IN ('hit','sunk')");
        $s->execute([$gameId, $pid]);
        $hitsTaken = (int)$s->fetchColumn();

        if ($totalLife > 0 && $hitsTaken >= $totalLife) {
            $gameRepo->setPlayerStatus($gameId, $pid, 'dead');
        }
    }

    // ---------------------------------------------------------
    // 5. VÉRIFICATION VICTOIRE
    // ---------------------------------------------------------
    $survivors = $gameRepo->getSurvivors($gameId);

    $victory    = GameLogicService::checkVictory($survivors);
    $finished   = $victory['finished'];
    $winnerId   = $victory['winner_id'];
    $winnerTeam = $victory['winner_team'];
    $isBR       = $victory['is_br'];

    if ($finished) {
        $gameRepo->setFinished($gameId, $winnerId);
        // L'XP/Gold est attribué dans get_shots.php (polling) avec un garde anti-doublon en session
    } else {
        // Seul le créateur avance le round.
        // Le guard AND last_turn_timestamp = $lastTurn évite les doublons
        // si le créateur appelle deux fois avant que son timer ne redémarre.
        if ($creatorId === (int)$_SESSION['uid']) {
            $pdo->prepare("
                UPDATE games
                SET last_turn_timestamp = ?, current_round = current_round + 1
                WHERE id_Game = ?
                  AND last_turn_timestamp = ?
                  AND status = 'in_progress'
            ")->execute([$now, $gameId, $lastTurn]);
        }
    }

    echo json_encode([
        "finished"    => $finished,
        "winner"      => $winnerId,
        "winner_team" => $winnerTeam,
        "round_start" => $now,
        "message"     => "Tour résolu"
    ]);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
