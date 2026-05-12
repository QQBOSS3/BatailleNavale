<?php
/**
 * Système de progression XP / Niveaux
 *
 * Formule : XP requis pour passer du niveau n au n+1 = floor(100 * 1.02^(n-1))
 * Victoire = 50 XP, Défaite = 25 XP
 */

const XP_WIN  = 50;
const XP_LOSE = 25;

const GOLD_WIN      = 100;
const GOLD_LOSE     = 25;
const GOLD_LEVEL_UP = 200;

/**
 * Calcule l'XP nécessaire pour passer du niveau $level au niveau suivant.
 */
function xpRequiredForLevel(int $level): int {
    if ($level < 1) $level = 1;
    return (int)floor(100 * pow(1.02, $level - 1));
}

/**
 * Ajoute de l'XP à un joueur et gère les montées de niveau.
 *
 * @param PDO $pdo      Connexion à la base
 * @param int $playerId ID du joueur
 * @param int $xpGain   Quantité d'XP à ajouter
 * @param int $goldGain Quantité de Gold à ajouter
 * @return array ['niveau' => int, 'xp' => int, 'gold' => int, 'leveled_up' => bool]
 */
function grantXp(PDO $pdo, int $playerId, int $xpGain, int $goldGain = 0, bool $isWin = false): array {
    $stmt = $pdo->prepare("SELECT niveau, xp, Gold FROM users WHERE ID_Users = ?");
    $stmt->execute([$playerId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) return ['niveau' => 0, 'xp' => 0, 'gold' => 0, 'leveled_up' => false];

    $niveau    = (int)$user['niveau'];
    $xp        = (int)$user['xp'] + $xpGain;
    $gold      = (int)$user['Gold'] + $goldGain;
    $leveledUp = false;

    // Boucle de level-up (au cas où le gain couvre plusieurs niveaux)
    while ($xp >= xpRequiredForLevel($niveau)) {
        $xp -= xpRequiredForLevel($niveau);
        $niveau++;
        $gold += GOLD_LEVEL_UP;
        $leveledUp = true;
    }

    $pdo->prepare("UPDATE users SET niveau = ?, xp = ?, Gold = ? WHERE ID_Users = ?")
        ->execute([$niveau, $xp, $gold, $playerId]);

    // Mise à jour du ratio (victoires / défaites / parties jouées)
    $pdo->prepare("
        INSERT INTO ratio (ID_Profil, Win, Defeat, Game_Played)
        VALUES (?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            Win         = Win + VALUES(Win),
            Defeat      = Defeat + VALUES(Defeat),
            Game_Played = Game_Played + 1
    ")->execute([$playerId, $isWin ? 1 : 0, $isWin ? 0 : 1]);

    return ['niveau' => $niveau, 'xp' => $xp, 'gold' => $gold, 'leveled_up' => $leveledUp];
}
