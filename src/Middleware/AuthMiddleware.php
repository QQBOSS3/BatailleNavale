<?php
namespace App\Middleware;

/**
 * Middleware d'authentification.
 * Vérifie que l'utilisateur est connecté et fournit un bootstrap commun.
 *
 * Utilisation dans les pages HTML (redirection) :
 *   require __DIR__ . '/../vendor/autoload.php';
 *   require __DIR__ . '/../config/db.php';
 *   App\Middleware\AuthMiddleware::requireAuth();
 *
 * Utilisation dans les endpoints JSON (erreur JSON) :
 *   require __DIR__ . '/../vendor/autoload.php';
 *   require __DIR__ . '/../config/db.php';
 *   App\Middleware\AuthMiddleware::requireAuthJson();
 */
class AuthMiddleware
{
    /** Seuil d'inactivité (secondes) au-delà duquel un utilisateur est considéré hors ligne. */
    public const ONLINE_THRESHOLD = 120; // 2 minutes

    /**
     * Vérifie la session et redirige vers login.php si non connecté.
     * À utiliser dans les pages qui génèrent du HTML.
     */
    public static function requireAuth(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['uid'])) {
            header("Location: login.php");
            exit;
        }

        self::touchActivity();
    }

    /**
     * Vérifie la session et renvoie une erreur JSON si non connecté.
     * À utiliser dans les endpoints API.
     */
    public static function requireAuthJson(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['uid'])) {
            header("Content-Type: application/json");
            echo json_encode(["success" => false, "error" => "Non connecté"]);
            exit;
        }

        self::touchActivity();
    }

    /**
     * Retourne l'ID de l'utilisateur connecté.
     */
    public static function getUserId(): int
    {
        return (int)$_SESSION['uid'];
    }

    /**
     * Met à jour last_activity en base (max 1 fois par 30s pour limiter les écritures).
     */
    private static function touchActivity(): void
    {
        global $pdo;
        if (!$pdo || empty($_SESSION['uid'])) return;

        $now = time();
        // Limiter les UPDATE à 1 toutes les 30 secondes
        if (isset($_SESSION['_last_touch']) && ($now - $_SESSION['_last_touch']) < 30) {
            return;
        }

        $pdo->prepare("UPDATE users SET last_activity = ?, Online = 1 WHERE ID_Users = ?")
            ->execute([$now, $_SESSION['uid']]);
        $_SESSION['_last_touch'] = $now;
    }
}
