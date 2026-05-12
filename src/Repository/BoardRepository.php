<?php
namespace App\Repository;

use PDO;

class BoardRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Récupère le plateau d'un joueur (board_json décodé en tableau 2D).
     */
    public function getBoard(int $gameId, int $playerId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT board_json FROM player_boards WHERE game_id = ? AND player_id = ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$gameId, $playerId]);
        return json_decode($stmt->fetchColumn() ?: '[]', true) ?: [];
    }

    /**
     * Récupère tous les plateaux d'une partie, indexés par player_id.
     */
    public function getAllBoards(int $gameId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT player_id, board_json FROM player_boards WHERE game_id = ? ORDER BY id DESC"
        );
        $stmt->execute([$gameId]);
        $boards = [];
        while ($row = $stmt->fetch()) {
            if (!isset($boards[$row['player_id']])) {
                $boards[$row['player_id']] = json_decode($row['board_json'], true) ?: [];
            }
        }
        return $boards;
    }

    /**
     * Sauvegarde (ou met à jour) le plateau d'un joueur et le marque comme validé.
     */
    public function saveBoard(int $gameId, int $playerId, array $board): void
    {
        $boardJson = json_encode($board);
        $this->pdo->prepare("
            INSERT INTO player_boards (game_id, player_id, board_json, validated)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE board_json = VALUES(board_json), validated = 1
        ")->execute([$gameId, $playerId, $boardJson]);
    }

    /**
     * Compte le nombre de plateaux validés pour une partie.
     */
    public function countValidated(int $gameId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT player_id) FROM player_boards WHERE game_id = ? AND validated = 1"
        );
        $stmt->execute([$gameId]);
        return (int)$stmt->fetchColumn();
    }
}
