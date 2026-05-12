<?php
/* Déconnexion - passe le joueur hors ligne puis détruit la session */
require __DIR__ . "/../config/db.php";
require __DIR__ . "/../vendor/autoload.php";

use App\Service\FlashService;

session_start();

// ✅ Mettre l'utilisateur hors ligne avant de détruire la session
if (isset($_SESSION['uid'])) {
    $stmt = $pdo->prepare("UPDATE users SET Online = 0 WHERE ID_Users = ?");
    $stmt->execute([$_SESSION['uid']]);
}

// Maintenant on détruit la session
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Nouveau cycle pour le flash
session_start();
FlashService::add('info', 'Déconnexion réussie ✅');

header("Location: login.php");
exit;
