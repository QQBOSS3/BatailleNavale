<?php
/* Changement d'avatar - vérifie que l'avatar existe puis met à jour */
require __DIR__."/../config/db.php";
session_start();

if (empty($_SESSION['uid'])) {
    http_response_code(403);
    exit("Non autorisé");
}

$avatarId = (int)($_POST['avatar_id'] ?? 0);


$stmt = $pdo->prepare("SELECT ID_Avatar FROM avatar WHERE ID_Avatar=?");
$stmt->execute([$avatarId]);
if (!$stmt->fetch()) {
    http_response_code(400);
    exit("Avatar invalide");
}

$stmt = $pdo->prepare("UPDATE users SET Avatar=? WHERE ID_Users=?");
$stmt->execute([$avatarId, $_SESSION['uid']]);

echo "Avatar mis à jour ✅";
