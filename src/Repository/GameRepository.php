<?php
namespace App\Repository;

use PDO;

class GameRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Récupère une partie par son ID.
     */
    public function findById(int $gameId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM games WHERE id_Game = ?");
        $stmt->execute([$gameId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Récupère le statut d'une partie.
     */
    public function getStatus(int $gameId): ?string
    {
        $stmt = $this->pdo->prepare("SELECT status FROM games WHERE id_Game = ?");
        $stmt->execute([$gameId]);
        return $stmt->fetchColumn() ?: null;
    }

    /**
     * Récupère l'ID du créateur d'une partie.
     */
    public function getCreatorId(int $gameId): ?int
    {
        $stmt = $this->pdo->prepare("SELECT id_creator FROM games WHERE id_Game = ?");
        $stmt->execute([$gameId]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (int)$val : null;
    }

    /**
     * Met à jour le statut d'une partie.
     */
    public function updateStatus(int $gameId, string $status): void
    {
        $this->pdo->prepare("UPDATE games SET status = ? WHERE id_Game = ?")
            ->execute([$status, $gameId]);
    }

    /**
     * Marque la partie comme terminée avec un gagnant.
     */
    public function setFinished(int $gameId, ?int $winnerId): void
    {
        $this->pdo->prepare("UPDATE games SET status = 'finished', winner_id = ? WHERE id_Game = ?")
            ->execute([$winnerId, $gameId]);
    }

    /**
     * Compte les joueurs actifs (non partis) dans une partie.
     */
    public function countActivePlayers(int $gameId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM game_players WHERE id_game = ? AND player_status != 'left'");
        $stmt->execute([$gameId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Compte tous les joueurs dans une partie.
     */
    public function countPlayers(int $gameId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM game_players WHERE id_game = ?");
        $stmt->execute([$gameId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Vérifie si un joueur est déjà dans une partie.
     */
    public function isPlayerInGame(int $gameId, int $playerId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM game_players WHERE id_game = ? AND id_player = ?");
        $stmt->execute([$gameId, $playerId]);
        return (bool)$stmt->fetch();
    }

    /**
     * Récupère les survivants (joueurs en vie) avec leur numéro d'équipe.
     */
    public function getSurvivors(int $gameId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id_player, team_number FROM game_players WHERE id_game = ? AND player_status = 'in_game'"
        );
        $stmt->execute([$gameId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les IDs des joueurs en vie.
     */
    public function getAlivePlayerIds(int $gameId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id_player FROM game_players WHERE id_game = ? AND player_status = 'in_game'"
        );
        $stmt->execute([$gameId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Met à jour le statut d'un joueur dans une partie.
     */
    public function setPlayerStatus(int $gameId, int $playerId, string $status): void
    {
        $this->pdo->prepare("UPDATE game_players SET player_status = ? WHERE id_game = ? AND id_player = ?")
            ->execute([$status, $gameId, $playerId]);
    }

    /**
     * Récupère le numéro d'équipe d'un joueur.
     */
    public function getPlayerTeam(int $gameId, int $playerId): ?int
    {
        $stmt = $this->pdo->prepare("SELECT team_number FROM game_players WHERE id_game = ? AND id_player = ?");
        $stmt->execute([$gameId, $playerId]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (int)$val : null;
    }

    /**
     * Ajoute un joueur à une partie.
     */
    public function addPlayer(int $gameId, int $playerId, ?int $teamNumber, string $status = 'in_game'): void
    {
        $this->pdo->prepare(
            "INSERT INTO game_players (id_game, id_player, team_number, player_status) VALUES (?, ?, ?, ?)"
        )->execute([$gameId, $playerId, $teamNumber, $status]);
    }

    /**
     * Récupère la distribution des équipes dans une partie.
     */
    public function getTeamDistribution(int $gameId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT team_number, COUNT(*) as nb FROM game_players WHERE id_game = ? GROUP BY team_number"
        );
        $stmt->execute([$gameId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les joueurs avec leurs infos (pseudo, avatar, skin) pour l'affichage lobby.
     */
    public function getPlayersWithInfo(int $gameId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT gp.*, u.Pseudo, u.Avatar, st.image_prefix AS avatar_prefix
            FROM game_players gp
            JOIN users u ON gp.id_player = u.ID_Users
            LEFT JOIN skin_active sa ON sa.id_user = gp.id_player AND sa.category = 'avatar'
            LEFT JOIN skin_themes st ON st.id = sa.id_theme
            WHERE gp.id_game = ?
        ");
        $stmt->execute([$gameId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
