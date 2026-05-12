<?php
/* Suppression de compte - efface toutes les données du joueur (cascade) */
require __DIR__."/../config/db.php";
require __DIR__."/../vendor/autoload.php";

use App\Service\FlashService;
use App\Repository\UserRepository;

session_start();

if (empty($_SESSION['uid'])) {
    FlashService::add('error', 'Veuillez vous connecter.');
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['uid'];

// --- CORRECTION ICI ---
// On utilise le Repository pour faire le ménage (Tirs, Plateaux...) AVANT de supprimer.
$userRepo = new UserRepository($pdo);

// La fonction deleteUser s'occupe de tout supprimer en cascade
if ($userRepo->deleteUser($userId)) {
    // Si la suppression a marché :
    $_SESSION = [];
    session_destroy();

    // On redémarre une session juste pour le message flash (car session_destroy l'a tuée)
    session_start();
    FlashService::add('info', 'Votre compte et toutes vos données ont été supprimés ❌');
    header("Location: register.php");
    exit;

} else {
    // Si ça a échoué (ex: bug SQL)
    FlashService::add('error', 'Erreur lors de la suppression. Réessayez plus tard.');
    header("Location: index.php"); // Ou vers le profil
    exit;
}