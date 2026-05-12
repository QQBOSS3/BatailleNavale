<?php
/* Réponse à une invitation de partie (accept/reject) - ajoute le joueur si accepté */
require __DIR__."/../config/db.php";
session_start();

$inviteId = (int)($_POST['invite_id'] ?? 0);
$action   = $_POST['action'] ?? '';

if (empty($_SESSION['uid']) || !$inviteId) {
    exit("Requête invalide.");
}

// Vérifier si l'invitation existe et appartient bien à ce joueur
$stmt = $pdo->prepare("SELECT * FROM game_invites WHERE ID=? AND receiver_id=? AND status='Pending'");
$stmt->execute([$inviteId, $_SESSION['uid']]);
$invite = $stmt->fetch();

if (!$invite) {
    exit("Invitation invalide ou déjà utilisée.");
}

// Vérifier que la partie existe encore et est en préparation
$stmt = $pdo->prepare("SELECT * FROM games WHERE id_Game=? AND status='preparation'");
$stmt->execute([$invite['id_game']]);
$game = $stmt->fetch();

if (!$game) {
    // La partie n'existe plus → supprimer l'invitation
    $pdo->prepare("DELETE FROM game_invites WHERE ID=?")->execute([$inviteId]);
    exit("Cette partie n'est plus disponible.");
}

if ($action === 'accept') {
    // Marquer l'invitation comme acceptée
    $pdo->prepare("UPDATE game_invites SET status='Accepted' WHERE ID=?")->execute([$inviteId]);

    // Supprimer une éventuelle ligne existante pour éviter doublons
    $pdo->prepare("DELETE FROM game_players WHERE id_game=? AND id_player=?")
        ->execute([$invite['id_game'], $_SESSION['uid']]);

    // Insérer le joueur dans la partie
    $pdo->prepare("INSERT INTO game_players (id_game, id_player, player_status) VALUES (?, ?, 'in_game')")
        ->execute([$invite['id_game'], $_SESSION['uid']]);

    header("Location: game.php?id=".$invite['id_game']);
    exit;
}


    elseif ($action === 'reject') {
    // Marquer l'invitation comme rejetée
    $pdo->prepare("UPDATE game_invites SET status='Rejected' WHERE ID=?")->execute([$inviteId]);

    header("Location: index.php");
    exit;

} else {
    exit("Action invalide.");
}
