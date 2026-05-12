<?php
/* Polling principal du jeu - renvoie l'état complet (tirs, morts, bateaux coulés, fin de partie)
   Appelé toutes les secondes par play.js pour mettre à jour l'affichage */
require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../config/db.php";
session_start();
header("Content-Type: application/json");

use App\Service\GameLogicService;
use App\Service\RewardService;
use App\Repository\BoardRepository;
use App\Middleware\AuthMiddleware;

AuthMiddleware::requireAuthJson();

$player_id = (int)$_SESSION['uid'];
$game_id   = isset($_GET['game_id']) ? (int)$_GET['game_id'] : 0;

if ($game_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Parametres invalides']);
    exit;
}

try {
    // === REQUETE 1 : Etat de la partie ===
    $stmt = $pdo->prepare("SELECT status, winner_id, last_turn_timestamp FROM games WHERE id_Game = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch();

    if (!$game) {
        echo json_encode(['success' => false, 'error' => 'Partie introuvable']);
        exit;
    }

    $finished = ($game['status'] === 'finished');
    $winnerId = $game['winner_id'] ?? null;

    // === REQUETE 2 : TOUS les tirs en une seule requete ===
    $stmt = $pdo->prepare("
        SELECT id_player, target_id, target_x, target_y, result, state
        FROM shots WHERE id_game = ?
        ORDER BY id_shot ASC
    ");
    $stmt->execute([$game_id]);
    $allShotsRaw = $stmt->fetchAll();

    // On filtre côté PHP plutôt que 3 requêtes séparées (perf)
    $my_shots    = [];
    $shots_on_me = [];
    $all_shots   = $allShotsRaw;
    $hitsByTarget = [];  // index des hits pour calculer les sunk

    foreach ($allShotsRaw as $s) {
        if ((int)$s['id_player'] === $player_id) {
            $my_shots[] = $s;
        }
        if ((int)$s['target_id'] === $player_id) {
            $shots_on_me[] = $s;
        }
        // Index des hits resolus pour le calcul sunk
        if ($s['state'] === 'resolved' && ($s['result'] === 'hit' || $s['result'] === 'sunk')) {
            $hitsByTarget[$s['target_id']][$s['target_x'] . '-' . $s['target_y']] = true;
        }
    }

    // === REQUETE 3 : TOUS les plateaux (mon board + sunk computation) ===
    $boardRepo = new BoardRepository($pdo);
    $allBoards = $boardRepo->getAllBoards($game_id);

    // Mon plateau (extrait depuis allBoards, pas de requete supplementaire)
    $my_board = [];
    $myBoardGrid = $allBoards[$player_id] ?? [];
    for ($y = 0; $y < count($myBoardGrid); $y++) {
        for ($x = 0; $x < count($myBoardGrid[$y]); $x++) {
            if ($myBoardGrid[$y][$x] > 0) {
                $my_board[] = ['x' => $x, 'y' => $y, 'ship' => true];
            }
        }
    }

    // === REQUETE 4 : Joueurs (morts + equipe gagnante + skin bateau) ===
    $stmt = $pdo->prepare("
        SELECT gp.id_player, gp.player_status, gp.team_number,
               st.folder_name AS ship_folder, st.image_prefix AS ship_prefix
        FROM game_players gp
        LEFT JOIN skin_active sa ON sa.id_user = gp.id_player AND sa.category = 'bateau'
        LEFT JOIN skin_themes st ON st.id = sa.id_theme
        WHERE gp.id_game = ?
    ");
    $stmt->execute([$game_id]);
    $gamePlayers = $stmt->fetchAll();

    $dead_players = [];
    $winnerTeam   = null;
    $playerShipSkins = [];
    foreach ($gamePlayers as $gp) {
        if ($gp['player_status'] === 'dead') {
            $dead_players[] = $gp['id_player'];
        }
        if ($finished && $winnerId && (int)$gp['id_player'] === (int)$winnerId) {
            $winnerTeam = $gp['team_number'];
        }
        if ($gp['ship_folder']) {
            $playerShipSkins[$gp['id_player']] = [
                'folder' => $gp['ship_folder'],
                'prefix' => $gp['ship_prefix'],
            ];
        }
    }

    // === Recap fin de partie (XP, Gold, Niveau) ===
    $recap = null;
    if ($finished) {
        $isWinner = false;
        if ($winnerTeam !== null) {
            foreach ($gamePlayers as $gp) {
                if ((int)$gp['id_player'] === $player_id && (int)$gp['team_number'] === (int)$winnerTeam) {
                    $isWinner = true;
                    break;
                }
            }
        } else {
            $isWinner = ((int)$winnerId === $player_id);
        }

        $rewardService = new RewardService($pdo);

        // Attribuer l'XP une seule fois par joueur par partie (flag en session)
        $sessionKey = 'xp_granted_' . $game_id;
        if (empty($_SESSION[$sessionKey])) {
            $rewardService->grantXp(
                $player_id,
                $isWinner ? RewardService::XP_WIN : RewardService::XP_LOSE,
                $isWinner ? RewardService::GOLD_WIN : RewardService::GOLD_LOSE,
                $isWinner
            );
            $_SESSION[$sessionKey] = true;
        }

        $recap = $rewardService->buildRecap($player_id, $isWinner);
    }

    // --- Calcul des bateaux coulés (source de vérité = plateau + index des hits) ---
    $sunkCellsMap = [];
    $sunkCellsMe  = [];
    $sunkShipsMap = []; // navires coulés chez les ennemis
    $sunkShipsMe  = []; // navires coulés chez moi

    foreach ($allBoards as $pid => $brd) {
        $shipGroups = GameLogicService::groupShipCells($brd);
        $hits = $hitsByTarget[$pid] ?? [];

        foreach ($shipGroups as $cells) {
            $allHit = true;
            foreach ($cells as $c) {
                if (!isset($hits[$c['x'] . '-' . $c['y']])) { $allHit = false; break; }
            }
            if ($allHit) {
                $shipInfo = GameLogicService::buildSunkShipInfo($cells);

                foreach ($cells as $c) {
                    if ((int)$pid === $player_id) {
                        $sunkCellsMe[] = [$c['x'], $c['y']];
                    } else {
                        $sunkCellsMap[$pid][] = [$c['x'], $c['y']];
                    }
                }

                if ((int)$pid === $player_id) {
                    $sunkShipsMe[] = $shipInfo;
                } else {
                    $sunkShipsMap[$pid][] = $shipInfo;
                }
            }
        }
    }

    echo json_encode([
        'success'      => true,
        'finished'     => $finished,
        'winner'       => $winnerId,
        'winner_id'    => $winnerId,
        'winner_team'  => $winnerTeam,
        'dead_players' => $dead_players,
        'game_status'  => $game['status'],
        'my_board'     => $my_board,
        'my_shots'     => $my_shots,
        'shots_on_me'  => $shots_on_me,
        'all_shots'    => $all_shots,
        'sunk_cells'   => $sunkCellsMap,
        'sunk_cells_me'=> $sunkCellsMe,
        'sunk_ships'   => $sunkShipsMap,
        'sunk_ships_me'=> $sunkShipsMe,
        'ship_skins'   => $playerShipSkins,
        'last_turn_timestamp' => (int)$game['last_turn_timestamp'],
        'recap'        => $recap
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur SQL : ' . $e->getMessage()]);
}
