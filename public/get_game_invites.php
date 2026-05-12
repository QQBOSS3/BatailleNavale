<?php
/* Affiche les invitations de partie en attente pour le joueur connecté (HTML) */
require __DIR__."/../config/db.php";
session_start();

if (empty($_SESSION['uid'])) {
    exit("Non autorisé");
}
$stmt = $pdo->prepare("SELECT gi.ID, gi.id_game, u.Pseudo AS sender
                       FROM game_invites gi
                       JOIN users u ON u.ID_Users = gi.sender_id
                       WHERE gi.receiver_id=? AND gi.status='Pending'");
$stmt->execute([$_SESSION['uid']]);
$invites = $stmt->fetchAll();

if ($invites) {
    foreach ($invites as $inv) {
        echo "<p>🎮 Invitation à rejoindre la partie #".htmlspecialchars($inv['id_game'])." envoyée par ".htmlspecialchars($inv['sender'])."
            <form method='post' action='respond_game_invite.php' style='display:inline'>
              <input type='hidden' name='invite_id' value='".$inv['ID']."'>
              <button type='submit' name='action' value='accept'>✅ Accepter</button>
              <button type='submit' name='action' value='reject'>❌ Refuser</button>
            </form>
        </p>";
    }
} else {
    echo "<p>Aucune invitation en attente.</p>";
}
