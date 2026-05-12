<?php
namespace App\Repository;

use PDO;

class SkinRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Récupère tous les thèmes triés par catégorie puis par prix.
     */
    public function getAllThemes(): array
    {
        return $this->pdo->query("SELECT * FROM skin_themes ORDER BY category, price")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère un thème par son ID.
     */
    public function findThemeById(int $themeId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM skin_themes WHERE id = ?");
        $stmt->execute([$themeId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Récupère la liste des IDs de thèmes achetés par un joueur.
     */
    public function getOwnedThemeIds(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT id_theme FROM skin_purchases WHERE id_user = ?");
        $stmt->execute([$userId]);
        return array_column($stmt->fetchAll(), 'id_theme');
    }

    /**
     * Enregistre l'achat d'un thème.
     */
    public function purchase(int $userId, int $themeId, int $price): void
    {
        $this->pdo->prepare("UPDATE users SET Gold = Gold - ? WHERE ID_Users = ?")
            ->execute([$price, $userId]);
        $this->pdo->prepare("INSERT INTO skin_purchases (id_user, id_theme) VALUES (?, ?)")
            ->execute([$userId, $themeId]);
    }

    /**
     * Équipe un thème pour une catégorie donnée.
     */
    public function equip(int $userId, string $category, int $themeId): void
    {
        $this->pdo->prepare("
            INSERT INTO skin_active (id_user, category, id_theme) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE id_theme = VALUES(id_theme)
        ")->execute([$userId, $category, $themeId]);
    }

    /**
     * Retire l'équipement d'une catégorie (retour au défaut).
     */
    public function unequip(int $userId, string $category): void
    {
        $this->pdo->prepare("DELETE FROM skin_active WHERE id_user = ? AND category = ?")
            ->execute([$userId, $category]);
    }

    /**
     * Récupère les thèmes actifs d'un joueur, indexés par catégorie.
     *
     * @return array ['avatar' => ['id_theme' => int, 'image_prefix' => string], ...]
     */
    public function getActiveThemes(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT sa.category, sa.id_theme, st.image_prefix
            FROM skin_active sa
            JOIN skin_themes st ON st.id = sa.id_theme
            WHERE sa.id_user = ?
        ");
        $stmt->execute([$userId]);
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[$row['category']] = [
                'id_theme'     => (int)$row['id_theme'],
                'image_prefix' => $row['image_prefix'],
            ];
        }
        return $result;
    }
}
