<?php
namespace App\Service;

class GameLogicService
{
    /**
     * Groupe les cellules d'un plateau par navire via flood-fill orthogonal.
     * Chaque composante connexe = un navire, quel que soit le format d'ID.
     *
     * @param array $board Grille 2D (board_json décodé)
     * @return array Liste de groupes, chaque groupe = tableau de ['x' => int, 'y' => int]
     */
    public static function groupShipCells(array $board): array
    {
        $allShipCells = [];
        $rows = count($board);
        for ($y = 0; $y < $rows; $y++) {
            $cols = count($board[$y]);
            for ($x = 0; $x < $cols; $x++) {
                if ((int)$board[$y][$x] > 0) {
                    $allShipCells[] = ['x' => $x, 'y' => $y];
                }
            }
        }
        if (empty($allShipCells)) return [];

        $cellMap = [];
        foreach ($allShipCells as $c) $cellMap[$c['x'] . '-' . $c['y']] = true;
        $visited = [];
        $groups  = [];
        foreach ($allShipCells as $c) {
            $key = $c['x'] . '-' . $c['y'];
            if (isset($visited[$key])) continue;
            $group = [];
            $stack = [$c];
            while (!empty($stack)) {
                $cur = array_pop($stack);
                $ck  = $cur['x'] . '-' . $cur['y'];
                if (isset($visited[$ck])) continue;
                $visited[$ck] = true;
                $group[] = $cur;
                foreach ([[-1,0],[1,0],[0,-1],[0,1]] as [$dx,$dy]) {
                    $nk = ($cur['x']+$dx) . '-' . ($cur['y']+$dy);
                    if (isset($cellMap[$nk]) && !isset($visited[$nk])) {
                        $stack[] = ['x' => $cur['x']+$dx, 'y' => $cur['y']+$dy];
                    }
                }
            }
            $groups[] = $group;
        }
        return $groups;
    }

    /**
     * Valide le placement des bateaux selon les règles françaises.
     * Vérifie les contacts en diagonale et les bateaux collés.
     *
     * @param array $board Grille 2D
     * @return string|null Message d'erreur ou null si valide
     */
    public static function validatePlacementFrench(array $board): ?string
    {
        $gridSize = count($board);
        for ($y = 0; $y < $gridSize; $y++) {
            for ($x = 0; $x < $gridSize; $x++) {
                if (isset($board[$y][$x]) && (int)$board[$y][$x] > 0) {
                    $myId = (int)$board[$y][$x];
                    // Diagonales interdites en FR
                    $diagonals = [[-1,-1], [1,-1], [-1,1], [1,1]];
                    foreach ($diagonals as $d) {
                        $nx = $x + $d[0]; $ny = $y + $d[1];
                        if (isset($board[$ny][$nx]) && (int)$board[$ny][$nx] > 0 && (int)$board[$ny][$nx] !== $myId) {
                            return "Règles FR : Contact en diagonale interdit.";
                        }
                    }
                    // Contacts latéraux (blocs) interdits — deux bateaux différents côte à côte
                    $hasTop    = (isset($board[$y-1][$x]) && (int)$board[$y-1][$x] > 0 && (int)$board[$y-1][$x] !== $myId);
                    $hasBottom = (isset($board[$y+1][$x]) && (int)$board[$y+1][$x] > 0 && (int)$board[$y+1][$x] !== $myId);
                    $hasLeft   = (isset($board[$y][$x-1]) && (int)$board[$y][$x-1] > 0 && (int)$board[$y][$x-1] !== $myId);
                    $hasRight  = (isset($board[$y][$x+1]) && (int)$board[$y][$x+1] > 0 && (int)$board[$y][$x+1] !== $myId);

                    if (($hasTop || $hasBottom) && ($hasLeft || $hasRight)) {
                        return "Règles FR : Bateaux collés interdits.";
                    }
                }
            }
        }
        return null;
    }

    /**
     * Détermine si la partie est terminée à partir de la liste des survivants.
     * Gère le mode Battle Royal (team_number NULL) et le mode Équipe.
     *
     * @param array $survivors Tableau de ['id_player' => int, 'team_number' => ?int]
     * @return array ['finished' => bool, 'winner_id' => ?int, 'winner_team' => ?int, 'is_br' => bool]
     */
    public static function checkVictory(array $survivors): array
    {
        $isBR = true;
        foreach ($survivors as $s) {
            if ($s['team_number'] !== null) { $isBR = false; break; }
        }

        $finished   = false;
        $winnerId   = null;
        $winnerTeam = null;

        if ($isBR) {
            if (count($survivors) <= 1) {
                $finished = true;
                $winnerId = $survivors[0]['id_player'] ?? null;
            }
        } else {
            $aliveTeams = array_unique(array_column($survivors, 'team_number'));
            if (count($aliveTeams) <= 1) {
                $finished = true;
                if (!empty($survivors)) {
                    $winnerId   = $survivors[0]['id_player'];
                    $winnerTeam = $survivors[0]['team_number'];
                }
            }
        }

        return [
            'finished'    => $finished,
            'winner_id'   => $winnerId,
            'winner_team' => $winnerTeam,
            'is_br'       => $isBR,
        ];
    }

    /**
     * Détermine si un joueur est mort (tous ses bateaux coulés).
     *
     * @param array $board    Grille 2D du joueur
     * @param array $hitSet   Set de positions touchées ['x-y' => true]
     * @return bool true si le joueur est mort
     */
    public static function isPlayerDead(array $board, array $hitSet): bool
    {
        $totalLife = 0;
        foreach ($board as $row) {
            foreach ($row as $cell) {
                if ($cell > 0) $totalLife++;
            }
        }
        if ($totalLife === 0) return false;

        $hitCount = 0;
        foreach ($board as $y => $row) {
            foreach ($row as $x => $cell) {
                if ($cell > 0 && isset($hitSet[$x . '-' . $y])) {
                    $hitCount++;
                }
            }
        }

        return $hitCount >= $totalLife;
    }

    /**
     * Construit les infos d'un navire coulé (orientation, taille, cellules triées).
     *
     * @param array $cells Cellules du navire [['x' => int, 'y' => int], ...]
     * @return array ['cells' => [[x,y], ...], 'size' => int, 'ori' => 'H'|'V', 'start' => [x,y]]
     */
    public static function buildSunkShipInfo(array $cells): array
    {
        $sortedCells = $cells;
        usort($sortedCells, function($a, $b) {
            return ($a['x'] === $b['x']) ? ($a['y'] - $b['y']) : ($a['x'] - $b['x']);
        });
        $size = count($sortedCells);
        $ori = 'H';
        if ($size > 1 && $sortedCells[0]['x'] === $sortedCells[1]['x']) $ori = 'V';

        return [
            'cells' => array_map(fn($c) => [$c['x'], $c['y']], $sortedCells),
            'size'  => $size,
            'ori'   => $ori,
            'start' => [$sortedCells[0]['x'], $sortedCells[0]['y']],
        ];
    }
}
