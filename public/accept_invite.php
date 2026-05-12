<?php
/* Accepter une invitation de partie - passe le status du joueur de "invited" à "in_game" */
session_start();
require __DIR__."/../config/db.php";

$inviteId = (int)($_POST['invite_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM game_players WHERE id_GP=? AND id_player=? AND player_status='invited'");
$stmt->execute([$inviteId, $_SESSION['uid']]);
$invite = $stmt->fetch();

if (!$invite) {
    exit("Invitation introuvable ou déjà traitée.");
}

$stmt = $pdo->prepare("UPDATE game_players SET player_status='in_game' WHERE id_GP=?");
$stmt->execute([$inviteId]);

header("Location: game.php?id=" . $invite['id_game']);
exit;
