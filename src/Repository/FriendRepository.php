<?php
namespace App\Repository;

use PDO;

class FriendRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Vérifie s'il existe déjà une relation (dans les deux sens) entre deux utilisateurs.
     */
    public function relationExists(int $userId, int $friendId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM friends
             WHERE (Sender_ID = ? AND Receiver_ID = ?)
                OR (Sender_ID = ? AND Receiver_ID = ?)"
        );
        $stmt->execute([$userId, $friendId, $friendId, $userId]);
        return (bool)$stmt->fetch();
    }

    /**
     * Envoie une demande d'ami.
     */
    public function sendRequest(int $senderId, int $receiverId): void
    {
        $this->pdo->prepare(
            "INSERT INTO friends (Sender_ID, Receiver_ID, Status) VALUES (?, ?, 'Pending')"
        )->execute([$senderId, $receiverId]);
    }

    /**
     * Récupère une demande d'ami par son ID.
     */
    public function findRequestById(int $requestId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM friends WHERE ID_Friends = ?");
        $stmt->execute([$requestId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Met à jour le statut d'une demande d'ami.
     */
    public function updateStatus(int $requestId, string $status): void
    {
        $this->pdo->prepare("UPDATE friends SET Status = ? WHERE ID_Friends = ?")
            ->execute([$status, $requestId]);
    }

    /**
     * Récupère la liste des amis acceptés d'un utilisateur.
     */
    public function getAcceptedFriends(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.ID_Users, u.Pseudo,
                   (u.last_activity IS NOT NULL AND u.last_activity > UNIX_TIMESTAMP() - 120) AS Online
            FROM friends f
            JOIN users u
              ON (u.ID_Users = f.Sender_ID AND f.Receiver_ID = ?)
              OR (u.ID_Users = f.Receiver_ID AND f.Sender_ID = ?)
            WHERE f.Status = 'Accepted'
        ");
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
