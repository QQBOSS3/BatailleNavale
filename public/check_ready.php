<?php
/* Check si tous les joueurs ont placé leurs bateaux
   Si oui => lance la partie (transition placement → in_progress) */
require __DIR__."/../config/db.php";
session_start();

header('Content-Type: application/json');

$gameId = (int)($_GET['id'] ?? 0);

if ($gameId <= 0) {
    echo json_encode(["ready" => false, "error" => "No ID"]);
    exit;
}

// 1. D'abord, on regarde le statut OFFICIEL de la partie
$stmt = $pdo->prepare("SELECT status FROM games WHERE id_Game=?");
$stmt->execute([$gameId]);
$status = $stmt->fetchColumn();

// SI la partie est déjà lancée ('in_progress'), on renvoie TRUE direct !
if ($status === 'in_progress') {
    echo json_encode([
        "ready" => true,
        "status" => $status,
        "message" => "Partie déjà lancée en BDD"
    ]);
    exit;
}

// 2. Sinon, on compte manuellement (au cas où le UPDATE n'a pas encore eu lieu)
// Nombre de joueurs dans la partie
$stmt = $pdo->prepare("SELECT COUNT(*) FROM game_players WHERE id_game=? AND player_status!='left'");
$stmt->execute([$gameId]);
$total = (int)$stmt->fetchColumn();

// ✅ Fix 1 : guard manquant
if ($total < 2) $total = 2;

// Nombre de plateaux validés
$stmt = $pdo->prepare("SELECT COUNT(*) FROM player_boards WHERE game_id=? AND validated=1");
$stmt->execute([$gameId]);
$readyCount = (int)$stmt->fetchColumn();

// On considère prêt si tout le monde a validé (et qu'il y a au moins 2 joueurs)
$isReady = ($total > 1 && $readyCount >= $total);

if ($isReady) {
    // Si le statut est encore 'placement', déclencher la transition ici.
    // AND status='placement' est un guard atomique : no-op si place_ships.php
    // ou un appel concurrent l'a déjà fait.
    $now = time();
    $pdo->prepare("
        UPDATE games
        SET status='in_progress', current_round=1, last_turn_timestamp=?
        WHERE id_Game=? AND status='placement'
    ")->execute([$now, $gameId]);

    // Mettre les joueurs en vie (idempotent)
    $pdo->prepare(
        "UPDATE game_players SET player_status='in_game'
         WHERE id_game=? AND player_status != 'in_game'"
    )->execute([$gameId]);

    echo json_encode(["ready" => true, "status" => "in_progress"]);
} else {
    echo json_encode([
        "ready"      => false,
        "total"      => $total,
        "readyCount" => $readyCount,
        "status"     => $status
    ]);
}