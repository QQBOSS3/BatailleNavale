<?php
namespace App\Repository;

use App\Entity\Avatar;
use PDO;

class AvatarRepository {
    public function __construct(private PDO $pdo) {}

    public function findAll(): array {
        $stmt = $this->pdo->query("SELECT * FROM avatar");
        $avatars = [];
        while ($row = $stmt->fetch()) {
            $avatars[] = new Avatar(
                (int)$row['ID_Avatar'],
                $row['Name'],
                $row['mime_type'],
                $row['Avatar']
            );
        }
        return $avatars;
    }

    public function findById(int $id): ?Avatar {
        $stmt = $this->pdo->prepare("SELECT * FROM avatar WHERE ID_Avatar = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? new Avatar(
            (int)$row['ID_Avatar'],
            $row['Name'],
            $row['mime_type'],
            $row['Avatar']
        ) : null;
    }
}
