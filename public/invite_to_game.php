<?php
/* Inviter un ami à rejoindre la partie - seul l'hôte peut envoyer */
require __DIR__."/../config/db.php";
session_start();

$gameId = (int)($_POST['game_id'] ?? 0);
$friendId = (int)($_POST['friend_id'] ?? 0);

if (!$gameId || !$friendId) {
    exit("❌ Requête invalide.");
}

// Vérifier que je suis bien l'host
$stmt = $pdo->prepare("SELECT * FROM games WHERE id_Game=? AND id_creator=?");
$stmt->execute([$gameId, $_SESSION['uid']]);
$game = $stmt->fetch();

if (!$game) {
    exit("❌ Vous n'êtes pas autorisé à inviter.");
}

// Vérifier si une invit existe déjà (optionnel mais propre)
$stmt = $pdo->prepare("SELECT * FROM game_invites WHERE id_game=? AND receiver_id=? AND status='Pending'");
$stmt->execute([$gameId, $friendId]);
if ($stmt->fetch()) {
    exit("⚠️ Invitation déjà envoyée !");
}

// Insérer une invitation en attente
$stmt = $pdo->prepare("INSERT INTO game_invites (id_game, sender_id, receiver_id, status) VALUES (?, ?, ?, 'Pending')");
$stmt->execute([$gameId, $_SESSION['uid'], $friendId]);

// CORRECTION ICI : Pas de header location ! Juste un message texte.
echo "✅ Invitation envoyée !";
exit;