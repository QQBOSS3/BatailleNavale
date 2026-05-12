<?php
/* Renvoie la liste d'amis en HTML (pour le sidebar d'invitation) */
require __DIR__."/../vendor/autoload.php";
require __DIR__."/../config/db.php";
session_start();

use App\Repository\FriendRepository;
use App\Middleware\AuthMiddleware;

AuthMiddleware::requireAuth();

$friendRepo = new FriendRepository($pdo);

$inviteMode = isset($_GET['invite_mode']) && $_GET['invite_mode'] == '1';

$friends = $friendRepo->getAcceptedFriends($_SESSION['uid']);

if ($friends) {
    echo '<div class="friends-list-container">';
    foreach ($friends as $f) {
        $status = $f['Online'] ? '🟢' : '🔴';
        $pseudo = htmlspecialchars($f['Pseudo']);
        $id     = (int)$f['ID_Users'];

        // CORRECTION ICI : Ajout de color:white et font-weight pour la lisibilité
        echo '<div class="friend-row" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; padding:8px; border-bottom:1px solid #444; color: white;">';
        
        echo "<span style='font-size:1.1rem; font-weight:500;'>{$status} {$pseudo}</span>";

        if ($inviteMode) {
            echo "<button onclick=\"inviteFriend({$id})\" style='background:#00bcd4; border:none; color:white; padding:6px 12px; border-radius:4px; cursor:pointer; font-weight:bold;'>Inviter</button>";
        }

        echo '</div>';
    }
    echo '</div>';
} else {
    echo "<p style='color:#ccc; font-style:italic; text-align:center;'>Aucun ami trouvé.</p>";
}