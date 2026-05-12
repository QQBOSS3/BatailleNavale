<?php
namespace App\Service;

// Messages flash (success/error/info) - stockés en session, affichés une seule fois
class FlashService {
    public static function add(string $type, string $message): void {
        $_SESSION['flash'][$type][] = $message;
    }

    // Récupère et supprime tous les messages (lecture unique)
    public static function getAll(): array {
        if (!isset($_SESSION['flash'])) {
            return [];
        }
        $flashes = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flashes;
    }
}
