<?php
namespace App\Repository;

use App\Entity\User;
use PDO;

// Requêtes liées aux utilisateurs (login, inscription, suppression)
class UserRepository {
    public function __construct(private PDO $pdo) {}

    // Cette fonction est utilisée par register.php pour vérifier les doublons
    public function findByEmail(string $email): ?User {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE Email = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function create(string $email, string $pseudo, string $password, string $birthDay): User {
        // ✅ CHANGEMENT ICI : Passage à BCRYPT
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $this->pdo->prepare(
            "INSERT INTO users (Email, Pseudo, Password, BirthDay) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$email, $pseudo, $hash, $birthDay]);

        $id = (int)$this->pdo->lastInsertId();

        // On retourne l'objet User tout neuf
        return new User($id, $email, $pseudo, $hash, null, 1, 0, 0, false);
    }

    private function hydrate(array $row): User {
        // Attention : Vérifie bien que les clés du tableau (ex: 'Avatar_ID', 'niveau') 
        // correspondent exactement à tes colonnes dans la BDD MySQL.
        return new User(
            (int)$row['ID_Users'],
            $row['Email'],
            $row['Pseudo'],
            $row['Password'],
            $row['Avatar_ID'] ? (int)$row['Avatar_ID'] : null,
            (int)$row['niveau'],
            (int)($row['xp'] ?? 0),
            (int)$row['Gold'],
            (bool)$row['Online']
        );
    }

    public function deleteUser(int $userId): bool {
        try {
            $this->pdo->beginTransaction();

            // 1. Supprimer ses tirs (shots)
            // On supprime les tirs qu'il a faits ET les tirs reçus (si nécessaire, selon ta structure)
            $stmt = $this->pdo->prepare("DELETE FROM shots WHERE id_player = ?");
            $stmt->execute([$userId]);

            // 2. Supprimer ses plateaux (player_boards)
            $stmt = $this->pdo->prepare("DELETE FROM player_boards WHERE player_id = ?");
            $stmt->execute([$userId]);

            // 3. Le retirer des parties (game_players)
            $stmt = $this->pdo->prepare("DELETE FROM game_players WHERE id_player = ?");
            $stmt->execute([$userId]);
            
            // 4. (Optionnel) Si l'utilisateur a CRÉÉ des parties (table games), 
            // on peut soit les supprimer, soit mettre un autre admin. 
            // Pour l'instant, essayons sans toucher à la table games pour éviter de casser les parties des autres.

            // 5. ENFIN : Supprimer l'utilisateur lui-même
            $stmt = $this->pdo->prepare("DELETE FROM users WHERE ID_Users = ?");
            $stmt->execute([$userId]);

            $this->pdo->commit();
            return true;

        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            // Tu peux loguer l'erreur ici si tu as un système de log
            // error_log($e->getMessage()); 
            return false;
        }
    }
}