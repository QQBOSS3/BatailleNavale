<?php
/* Recherche d'un joueur par pseudo - affiche un bouton "Ajouter" si trouvé */
require __DIR__ . "/../vendor/autoload.php";
require __DIR__ . "/../config/db.php";
session_start();

use App\Repository\FriendRepository;

$pseudo = trim($_POST['pseudo'] ?? '');
if ($pseudo === '') {
    exit("Veuillez entrer un pseudo.");
}

// Vérifier si l'utilisateur existe
$stmt = $pdo->prepare("SELECT `ID_Users`, `Pseudo` FROM users WHERE Pseudo = ?");
$stmt->execute([$pseudo]);
$user = $stmt->fetch();

if (!$user) {
    exit("Aucun utilisateur trouvé.");
}

// Vérifier si déjà ami
$friendRepo = new FriendRepository($pdo);
if ($friendRepo->relationExists($_SESSION['uid'], $user['ID_Users'])) {
    exit("Déjà ami ou demande en cours.");
}

// Sinon proposer ajout
echo htmlspecialchars($user['Pseudo']) .
    " <form method='post' action='send_friend_request.php' style='display:inline'>
        <input type='hidden' name='friend_id' value='" . $user['ID_Users'] . "'>
        <button type='submit'>➕ Ajouter</button>
      </form>";
