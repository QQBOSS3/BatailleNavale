<?php
namespace App\Service;

use PDO;

class RewardService
{
    public const XP_WIN  = 50;
    public const XP_LOSE = 25;

    public const GOLD_WIN      = 100;
    public const GOLD_LOSE     = 25;
    public const GOLD_LEVEL_UP = 200;

    public function __construct(private PDO $pdo) {}

    /**
     * Calcule l'XP nécessaire pour passer du niveau $level au niveau suivant.
     */
    public static function xpRequiredForLevel(int $level): int
    {
        if ($level < 1) $level = 1;
        return (int)floor(100 * pow(1.02, $level - 1));
    }

    /**
     * Ajoute de l'XP et du Gold à un joueur et gère les montées de niveau.
     *
     * @return array ['niveau' => int, 'xp' => int, 'gold' => int, 'leveled_up' => bool]
     */
    public function grantXp(int $playerId, int $xpGain, int $goldGain = 0, bool $isWin = false): array
    {
        $stmt = $this->pdo->prepare("SELECT niveau, xp, Gold FROM users WHERE ID_Users = ?");
        $stmt->execute([$playerId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return ['niveau' => 0, 'xp' => 0, 'gold' => 0, 'leveled_up' => false];

        $niveau    = (int)$user['niveau'];
        $xp        = (int)$user['xp'] + $xpGain;
        $gold      = (int)$user['Gold'] + $goldGain;
        $leveledUp = false;

        // Boucle de level-up (au cas où le gain couvre plusieurs niveaux)
        while ($xp >= self::xpRequiredForLevel($niveau)) {
            $xp -= self::xpRequiredForLevel($niveau);
            $niveau++;
            $gold += self::GOLD_LEVEL_UP;
            $leveledUp = true;
        }

        $this->pdo->prepare("UPDATE users SET niveau = ?, xp = ?, Gold = ? WHERE ID_Users = ?")
            ->execute([$niveau, $xp, $gold, $playerId]);

        // Mise à jour du ratio (victoires / défaites / parties jouées)
        $this->pdo->prepare("
            INSERT INTO ratio (ID_Profil, Win, Defeat, Game_Played)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
                Win         = Win + VALUES(Win),
                Defeat      = Defeat + VALUES(Defeat),
                Game_Played = Game_Played + 1
        ")->execute([$playerId, $isWin ? 1 : 0, $isWin ? 0 : 1]);

        return ['niveau' => $niveau, 'xp' => $xp, 'gold' => $gold, 'leveled_up' => $leveledUp];
    }

    /**
     * Construit le récapitulatif de fin de partie pour l'animation XP.
     */
    public function buildRecap(int $playerId, bool $isWinner): array
    {
        $xpGain   = $isWinner ? self::XP_WIN : self::XP_LOSE;
        $goldGain = $isWinner ? self::GOLD_WIN : self::GOLD_LOSE;

        $stmt = $this->pdo->prepare("SELECT niveau, xp, Gold FROM users WHERE ID_Users = ?");
        $stmt->execute([$playerId]);
        $userData = $stmt->fetch();

        $currentLevel = (int)$userData['niveau'];
        $currentXp    = (int)$userData['xp'];
        $xpRequired   = self::xpRequiredForLevel($currentLevel);
        $leveledUp    = ($currentXp < $xpGain);
        $goldLevelBonus = $leveledUp ? self::GOLD_LEVEL_UP : 0;

        if ($leveledUp && $currentLevel > 1) {
            $prevRequired = self::xpRequiredForLevel($currentLevel - 1);
            $xpBefore     = $currentXp + $prevRequired - $xpGain;
            $startPct     = round($xpBefore / $prevRequired * 100, 1);
        } else {
            $xpBefore = $currentXp - $xpGain;
            $startPct = $xpRequired > 0 ? round($xpBefore / $xpRequired * 100, 1) : 0;
        }
        $endPct = $xpRequired > 0 ? round($currentXp / $xpRequired * 100, 1) : 0;

        return [
            'is_winner'        => $isWinner,
            'xp_earned'        => $xpGain,
            'gold_earned'      => $goldGain,
            'gold_level_bonus' => $goldLevelBonus,
            'current_level'    => $currentLevel,
            'current_xp'       => $currentXp,
            'xp_required'      => $xpRequired,
            'leveled_up'       => $leveledUp,
            'start_pct'        => max(0, $startPct),
            'end_pct'          => $endPct,
        ];
    }
}
