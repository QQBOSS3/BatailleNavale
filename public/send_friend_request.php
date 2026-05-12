<?php
/* Envoi d'une demande d'ami - vérifie que la relation n'existe pas déjà */
require __DIR__."/../vendor/autoload.php";
require __DIR__."/../config/db.php";
session_start();

use App\Service\FlashService;
use App\Repository\FriendRepository;

$friendId = (int)($_POST['friend_id'] ?? 0);
if ($friendId <= 0 || $friendId == $_SESSION['uid']) {
    FlashService::add('error', 'Demande invalide.');
    header("Location: index.php");
    exit;
}

// Vérifier si l'utilisateur existe
$stmt = $pdo->prepare("SELECT ID_Users FROM users WHERE ID_Users=?");
$stmt->execute([$friendId]);
$friend = $stmt->fetch();

if (!$friend) {
    FlashService::add('error', 'Utilisateur introuvable.');
    header("Location: index.php");
    exit;
}

$friendRepo = new FriendRepository($pdo);

// Vérifier si déjà une relation
if ($friendRepo->relationExists($_SESSION['uid'], $friendId)) {
    FlashService::add('info', 'Relation déjà existante.');
    header("Location: index.php");
    exit;
}

// Ajouter la demande
$friendRepo->sendRequest($_SESSION['uid'], $friendId);

// ✅ Ajout du message flash
FlashService::add('success', 'Demande d\'ami envoyée ✅');
header("Location: index.php");
exit;
