<?php
/* Liste les joueurs actifs d'une partie en HTML (avec bouton kick si hôte) */
require __DIR__."/../config/db.php";
session_start();

$gameId = (int)($_GET['id'] ?? 0);

// Vérifier la partie
$stmt = $pdo->prepare("SELECT * FROM games WHERE id_Game=?");
$stmt->execute([$gameId]);
$game = $stmt->fetch();
if (!$game) exit;

$isHost = ($game['id_creator'] == $_SESSION['uid']);

// Joueurs actifs (pas "left")
$stmt = $pdo->prepare("
    SELECT u.ID_Users, u.Pseudo, gp.player_status, gp.team_number
    FROM game_players gp
    JOIN users u ON u.ID_Users = gp.id_player
    WHERE gp.id_game=? AND gp.player_status != 'left'
");
$stmt->execute([$gameId]);
$players = $stmt->fetchAll();

foreach ($players as $p) {
    echo "<li>";
    echo htmlspecialchars($p['Pseudo']);
    if ($p['ID_Users'] == $game['id_creator']) echo " 👑";
    if ($p['team_number']) echo " (Équipe ".htmlspecialchars($p['team_number']).")";

    if ($isHost && $p['ID_Users'] != $_SESSION['uid']) {
        echo " <form method='post' action='kick_player.php' style='display:inline'>
                <input type='hidden' name='game_id' value='$gameId'>
                <input type='hidden' name='player_id' value='".$p['ID_Users']."'>
                <button type='submit' class='lobby-btn'>❌ Expulser</button>
              </form>";
    }
    echo "</li>";
}
