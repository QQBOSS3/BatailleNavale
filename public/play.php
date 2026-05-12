<?php
/* Page de jeu principale - affiche la minimap, les grilles ennemies, le log de combat
   et gère le timer de tour. Tout le gameplay côté client est dans play.js */
require __DIR__ . "/../config/db.php";
require __DIR__ . "/../config/constants.php";
session_start();

if (empty($_SESSION['uid'])) {
    header("Location: login.php");
    exit;
}

// Volume utilisateur (pour la musique de fond)
$stmtVol = $pdo->prepare("SELECT Volume FROM `option` WHERE ID_Users = ?");
$stmtVol->execute([$_SESSION['uid']]);
$volRow = $stmtVol->fetch();
$_userVol = $volRow ? (int)$volRow['Volume'] : 50;

$gameId = (int)($_GET['id'] ?? 0);
$myId   = (int)$_SESSION['uid'];

// Récup infos de la partie
$stmt = $pdo->prepare("SELECT * FROM games WHERE id_Game=?");
$stmt->execute([$gameId]);
$game = $stmt->fetch();

if (!$game) exit("Partie introuvable.");

// Redirige si on est pas encore en phase de jeu
if ($game['status'] === 'preparation') {
    header("Location: game.php?id=" . $gameId);
    exit;
}
if ($game['status'] === 'placement') {
    header("Location: place_ships_view.php?id=" . $gameId);
    exit;
}

$taille = (int)($game['taille_grille'] ?? 10);

// Calcul du temps restant dans le tour en cours
$now = time();

if (empty($game['last_turn_timestamp'])) {
    // Premier tour : on initialise le timestamp
    $pdo->prepare("UPDATE games SET last_turn_timestamp = ? WHERE id_Game=?")->execute([$now, $gameId]);
    $lastTurnTime = $now;
} else {
    $lastTurnTime = (int)$game['last_turn_timestamp'];
}

$timeElapsed = $now - $lastTurnTime;
$timeLeft    = ROUND_DURATION - $timeElapsed;
if ($timeLeft < 0) $timeLeft = 0;
if ($timeLeft > ROUND_DURATION) $timeLeft = ROUND_DURATION;

$stmtPseudo = $pdo->prepare("SELECT Pseudo FROM users WHERE ID_Users = ?");
$stmtPseudo->execute([$myId]);
$myPseudo = $stmtPseudo->fetchColumn() ?: 'Moi';

// Mon plateau (pour la minimap)
$stmt = $pdo->prepare("SELECT board_json FROM player_boards WHERE game_id=? AND player_id=? ORDER BY id DESC LIMIT 1");
$stmt->execute([$gameId, $myId]);
$boardData = $stmt->fetchColumn();
$myBoard   = $boardData ? json_decode($boardData, true) : [];

// Liste des adversaires (pour afficher leurs grilles)
$stmt = $pdo->prepare("
    SELECT gp.id_player, u.Pseudo, gp.team_number, gp.player_status
    FROM game_players gp
    JOIN users u ON u.ID_Users = gp.id_player
    WHERE gp.id_game = ? AND gp.id_player != ?
");
$stmt->execute([$gameId, $myId]);
$opponents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT team_number FROM game_players WHERE id_game=? AND id_player=?");
$stmt->execute([$gameId, $myId]);
$myTeamNumber = $stmt->fetchColumn();

// On flag les alliés pour pas qu'on puisse leur tirer dessus
foreach ($opponents as &$opp) {
    $opp['is_ally'] = ($myTeamNumber !== null && (int)$opp['team_number'] === (int)$myTeamNumber);
}
unset($opp);

if (empty($myBoard)) {
    header("Location: place_ships_view.php?id=" . $gameId);
    exit;
}

// Themes actifs (fond + bateau)
$stmtSkins = $pdo->prepare("
    SELECT sa.category, st.image_prefix, st.folder_name FROM skin_active sa
    JOIN skin_themes st ON st.id = sa.id_theme
    WHERE sa.id_user = ? AND sa.category IN ('fond','bateau')
");
$stmtSkins->execute([$myId]);
$activeFondPrefix = null;
$activeShipFolder = null;
$activeShipPrefix = null;
while ($sk = $stmtSkins->fetch()) {
    if ($sk['category'] === 'fond') $activeFondPrefix = $sk['image_prefix'];
    if ($sk['category'] === 'bateau') { $activeShipFolder = $sk['folder_name']; $activeShipPrefix = $sk['image_prefix']; }
}
$gameBg = $activeFondPrefix ? "assets/img/game/Game1{$activeFondPrefix}.png" : "assets/img/lobby-bg.png";

// Taille des cellules minimap - s'adapte à la taille de grille
$miniCellNormal = max(12, min(24, (int)(240 / $taille)));
$miniCellZoomed = max(20, min(38, (int)(380 / $taille)));
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bataille Navale — Partie #<?= (int)$gameId ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=2">
    <style>
        * { box-sizing: border-box; }

        body {
            background: url('<?= $gameBg ?>') no-repeat center center fixed;
            background-size: cover;
            color: #e5e9f0;
            font-family: "PixelFont", monospace;
            text-align: center;
            overflow-x: hidden;
            margin: 0;
            user-select: none;
        }
        body::after {
            content: ""; position: fixed; inset: 0; pointer-events: none; z-index: 0;
            background: radial-gradient(ellipse at center, transparent 25%, rgba(4,10,20,0.6) 100%);
        }

        h1 {
            margin-top: 16px; position: relative; z-index: 1;
            font-size: 1.2rem; color: var(--brass-light); letter-spacing: 0.2em;
            text-transform: uppercase;
            text-shadow: 0 0 16px rgba(234,192,64,0.35);
        }

        /* --- LOG DE COMBAT --- */
        #combat-log {
            position: fixed; right: 16px; bottom: 16px;
            width: clamp(300px, 25vw, 420px); max-height: 50vh;
            background: linear-gradient(170deg, rgba(10,22,40,0.94), rgba(7,15,28,0.97));
            border: 1px solid var(--wood-light); border-radius: 4px;
            box-shadow: 4px 4px 0 rgba(0,0,0,0.3);
            z-index: 500; display: flex; flex-direction: column;
            font-size: clamp(0.78rem, 0.9vw, 0.92rem); overflow: hidden;
        }
        #combat-log-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 8px 14px;
            background: rgba(200,147,62,0.08);
            border-bottom: 1px solid rgba(200,147,62,0.15);
            color: var(--brass-light); font-size: clamp(0.8rem, 1vw, 0.95rem); letter-spacing: 0.1em;
            text-transform: uppercase;
        }
        #combat-log-header button {
            background: none; border: none; color: var(--brass); cursor: pointer;
            font-size: 1.1rem; padding: 0 4px; line-height: 1;
        }
        #combat-log-header button:hover { color: var(--brass-light); }
        #combat-log-body {
            flex: 1; overflow-y: auto; padding: 4px 14px;
            scrollbar-width: thin; scrollbar-color: var(--wood-light) transparent;
        }
        #combat-log-body::-webkit-scrollbar { width: 4px; }
        #combat-log-body::-webkit-scrollbar-thumb { background: var(--wood-light); border-radius: 2px; }
        .log-entry {
            padding: 6px 0; border-bottom: 1px solid rgba(200,147,62,0.08);
            line-height: 1.5; color: #b0b8c8;
            animation: logFadeIn 0.3s ease-out;
        }
        .log-entry:last-child { border-bottom: none; }
        .log-entry .log-time {
            color: #5a6a7a; margin-right: 8px;
            font-size: 0.8em; font-variant-numeric: tabular-nums;
        }
        .log-entry .log-hit { color: #e05030; font-weight: bold; }
        .log-entry .log-miss { color: #5a6a8a; }
        .log-entry .log-sunk { color: #ff4060; font-weight: bold; text-transform: uppercase; }
        .log-entry .log-dead { color: #d32f2f; font-weight: bold; text-transform: uppercase; }
        .log-entry .log-join { color: #4caf50; }
        @keyframes logFadeIn {
            from { opacity: 0; transform: translateX(10px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        #combat-log.minimized #combat-log-body { display: none; }
        #combat-log.minimized { max-height: none; }

        /* --- MINIMAP NAVAL --- */
        #minimap {
            position: fixed; top: 16px; left: 16px; z-index: 1000;
            background: linear-gradient(170deg, rgba(10,22,40,0.94) 0%, rgba(7,15,28,0.96) 100%);
            border: 2px solid var(--wood-light); border-radius: 4px;
            padding: 12px;
            box-shadow: 0 0 20px rgba(0,0,0,0.6), inset 0 0 15px rgba(0,0,0,0.3);
            transition: box-shadow 0.3s, border-color 0.3s, transform 0.3s, opacity 0.3s;
        }
        #minimap::before {
            content: ""; position: absolute; top: 0; left: 0; right: 0; height: 3px;
            background: repeating-linear-gradient(90deg, #3d2008 0px, #c08030 6px, #f0b840 9px, #c08030 12px, #3d2008 18px);
        }
        #minimap.hidden-map #minimap-grid,
        #minimap.hidden-map #minimap-hp,
        #minimap.hidden-map #minimap-title,
        #minimap.hidden-map .minimap-btns #btn-zoom {
            display: none;
        }
        #minimap.hidden-map {
            padding: 10px;
            cursor: pointer;
        }
        #minimap.hidden-map #minimap-header {
            margin-bottom: 0;
        }

        #minimap.under-attack {
            animation: minimapPulse 0.5s ease-in-out 3;
            border-color: #e05030;
            box-shadow: 0 0 25px rgba(224,80,48,0.5), inset 0 0 15px rgba(224,80,48,0.15);
        }

        @keyframes minimapPulse {
            0%   { transform: scale(1);    box-shadow: 0 0 10px rgba(224,80,48,0.3); }
            50%  { transform: scale(1.04); box-shadow: 0 0 30px rgba(224,80,48,0.7); }
            100% { transform: scale(1);    box-shadow: 0 0 10px rgba(224,80,48,0.3); }
        }

        #minimap-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 8px;
        }
        #minimap-title {
            font-size: 0.65rem; letter-spacing: 0.14em;
            text-transform: uppercase; color: var(--brass);
        }
        .minimap-btns {
            display: flex; gap: 4px;
        }
        .minimap-btn {
            background: rgba(13,33,55,0.7); border: 1px solid var(--wood-light);
            color: var(--rope); padding: 3px 7px; border-radius: 2px;
            cursor: pointer; font-size: 0.65rem; line-height: 1;
            transition: all 0.15s; font-family: "PixelFont", monospace;
        }
        .minimap-btn:hover {
            border-color: var(--brass-light); color: var(--brass-light);
        }
        #minimap.hidden-map #btn-toggle {
            font-size: 1.4rem; padding: 8px 12px;
            border-color: var(--brass-light); color: var(--brass-light);
        }
        .minimap-btn.active {
            border-color: var(--accent); color: var(--accent);
        }

        #minimap-grid {
            display: grid;
            grid-template-rows: repeat(<?= $taille ?>, <?= $miniCellNormal ?>px);
            grid-template-columns: repeat(<?= $taille ?>, <?= $miniCellNormal ?>px);
            gap: 1px;
            transition: all 0.3s ease;
        }
        #minimap-grid.zoomed {
            grid-template-rows: repeat(<?= $taille ?>, <?= $miniCellZoomed ?>px);
            grid-template-columns: repeat(<?= $taille ?>, <?= $miniCellZoomed ?>px);
        }

        .mini-cell {
            width: 100%; height: 100%;
            background: rgba(13,33,55,0.5);
            border: 1px solid rgba(200,147,62,0.08);
            border-radius: 0; transition: background 0.3s;
        }
        .mini-cell.ship {
            background: rgba(200,147,62,0.4);
            border-color: rgba(200,147,62,0.25);
        }
        .mini-cell.hit {
            background: rgba(224,80,48,0.9) !important;
            border-color: rgba(224,80,48,0.6) !important;
            box-shadow: 0 0 3px rgba(224,80,48,0.6);
        }
        .mini-cell.sunk {
            background: rgba(120,30,60,0.95) !important;
            border-color: rgba(180,40,80,0.7) !important;
            box-shadow: 0 0 4px rgba(180,40,80,0.6);
        }
        .mini-cell.miss {
            background: rgba(90,90,110,0.35) !important;
        }

        #minimap-hp {
            margin-top: 8px; font-size: 0.65rem;
            color: var(--rope); text-align: center;
        }
        #minimap-hp span { color: #4caf50; font-weight: bold; }

        /* --- SYSTÈME DE STRESS --- */

        /* Niveau 1 : Alerte (50%-25% HP) — léger clignotement de bordure */
        #minimap.stress-1 {
            border-color: #ff9800;
            animation: stress1Pulse 2s ease-in-out infinite;
        }
        @keyframes stress1Pulse {
            0%, 100% { box-shadow: 0 0 15px rgba(0,0,0,0.6), inset 0 0 15px rgba(0,0,0,0.3); }
            50%      { box-shadow: 0 0 20px rgba(255,152,0,0.3), inset 0 0 15px rgba(255,152,0,0.05); }
        }

        /* Niveau 2 : Danger (25%-10% HP) — pulsation rouge + vignette */
        #minimap.stress-2 {
            border-color: #f44336;
            animation: stress2Pulse 1.2s ease-in-out infinite;
        }
        @keyframes stress2Pulse {
            0%, 100% { box-shadow: 0 0 15px rgba(244,67,54,0.2), inset 0 0 10px rgba(244,67,54,0.05); border-color: #f44336; }
            50%      { box-shadow: 0 0 30px rgba(244,67,54,0.5), inset 0 0 20px rgba(244,67,54,0.1); border-color: #ff6659; }
        }

        /* Vignette danger sur l'écran */
        #stress-vignette {
            position: fixed; inset: 0; z-index: 999;
            pointer-events: none; opacity: 0;
            transition: opacity 0.8s ease;
        }
        #stress-vignette.active {
            opacity: 1;
            background: radial-gradient(ellipse at center, transparent 40%, rgba(244,67,54,0.08) 100%);
            animation: vignetteBreath 1.2s ease-in-out infinite;
        }
        @keyframes vignetteBreath {
            0%, 100% { background: radial-gradient(ellipse at center, transparent 40%, rgba(244,67,54,0.06) 100%); }
            50%      { background: radial-gradient(ellipse at center, transparent 30%, rgba(244,67,54,0.14) 100%); }
        }

        /* Niveau 3 : Critique (<10% HP) — battement de coeur, écran rouge */
        #minimap.stress-3 {
            border-color: #d32f2f;
            animation: stress3Heartbeat 0.8s ease-in-out infinite;
        }
        @keyframes stress3Heartbeat {
            0%   { transform: scale(1);     box-shadow: 0 0 20px rgba(211,47,47,0.3); }
            15%  { transform: scale(1.03);  box-shadow: 0 0 35px rgba(211,47,47,0.6); }
            30%  { transform: scale(1);     box-shadow: 0 0 20px rgba(211,47,47,0.3); }
            45%  { transform: scale(1.02);  box-shadow: 0 0 30px rgba(211,47,47,0.5); }
            60%  { transform: scale(1);     box-shadow: 0 0 20px rgba(211,47,47,0.3); }
            100% { transform: scale(1);     box-shadow: 0 0 20px rgba(211,47,47,0.3); }
        }
        #stress-vignette.critical {
            opacity: 1;
            animation: vignetteCritical 0.8s ease-in-out infinite;
        }
        @keyframes vignetteCritical {
            0%, 60%, 100% { background: radial-gradient(ellipse at center, transparent 30%, rgba(211,47,47,0.1) 100%); }
            15%           { background: radial-gradient(ellipse at center, transparent 20%, rgba(211,47,47,0.22) 100%); }
            45%           { background: radial-gradient(ellipse at center, transparent 25%, rgba(211,47,47,0.18) 100%); }
        }

        /* HP bar dans la minimap */
        #minimap-hp-bar {
            margin-top: 6px; height: 4px;
            background: rgba(13,33,55,0.8);
            border: 1px solid var(--wood-light);
            border-radius: 2px; overflow: hidden;
        }
        #minimap-hp-fill {
            height: 100%; width: 100%;
            background: #4caf50;
            transition: width 0.6s ease, background-color 0.6s ease;
            border-radius: 1px;
        }

        /* --- BARRE D'INFOS COCKPIT --- */
        .info-bar {
            display: inline-block; position: relative; z-index: 1;
            background: linear-gradient(90deg, rgba(10,22,40,0.9), rgba(7,15,28,0.95), rgba(10,22,40,0.9));
            padding: 10px 30px; border-radius: 2px;
            border: 1px solid var(--wood-light); margin-top: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.5);
        }

        #timer {
            font-size: 1.4rem; font-weight: bold; margin-right: 16px;
            color: var(--brass-light); text-shadow: 0 0 12px rgba(234,192,64,0.4);
        }
        #status { font-size: 0.9rem; color: var(--rope); }

        #btn-quit {
            background: transparent; color: var(--rope);
            border: 1px solid var(--wood-light); padding: 6px 18px;
            border-radius: 2px; cursor: pointer;
            font-family: "PixelFont", monospace; font-size: 0.7rem;
            letter-spacing: 0.08em; transition: all 0.2s;
            position: relative; z-index: 1;
        }
        #btn-quit:hover {
            background: rgba(224,80,48,0.15); color: #e05030;
            border-color: #e05030;
        }

        /* --- GRILLES ENNEMIES --- */
        .grids {
            display: flex; justify-content: center;
            gap: 30px; flex-wrap: wrap;
            margin-top: 30px; padding-bottom: 40px;
            position: relative; z-index: 1;
            perspective: 900px;
        }

        .grid-container {
            background: linear-gradient(170deg, rgba(10,22,40,0.85) 0%, rgba(7,15,28,0.9) 100%);
            padding: 14px; border-radius: 4px;
            border: 1px solid var(--wood-light);
            transition: all 0.4s;
            box-shadow:
                4px 8px 0 rgba(0,0,0,0.3),
                0 20px 40px rgba(0,0,0,0.4);
            transform: rotateX(18deg) rotateY(0deg);
            transform-style: flat;
        }
        .grid-container:hover {
            transform: rotateX(8deg) rotateY(0deg) scale(1.02);
            box-shadow:
                2px 4px 0 rgba(0,0,0,0.2),
                0 14px 30px rgba(0,0,0,0.35),
                0 0 25px rgba(234,192,64,0.08);
        }

        .grid-container.targeted {
            border-color: var(--brass-light);
            box-shadow:
                0 0 25px rgba(234,192,64,0.2),
                4px 8px 0 rgba(0,0,0,0.3),
                0 20px 40px rgba(0,0,0,0.4);
        }

        .grid-title {
            font-weight: 700; margin-bottom: 10px;
            text-transform: uppercase; letter-spacing: 0.14em;
            color: var(--brass); font-size: 0.8rem;
        }

        .grid {
            display: grid;
            grid-template-rows: repeat(<?= $taille ?>, clamp(28px, 4.5vw, 52px));
            grid-template-columns: repeat(<?= $taille ?>, clamp(28px, 4.5vw, 52px));
            gap: 2px;
            position: relative;
        }

        .cell {
            position: relative; overflow: visible;
            width: 100%; height: 100%;
            aspect-ratio: 1;
            background: rgba(13,33,55,0.6);
            border: 1px solid rgba(200,147,62,0.1);
            cursor: crosshair;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.15s, border-color 0.15s;
        }
        .cell:hover {
            background: rgba(200,147,62,0.18); border-color: rgba(200,147,62,0.3);
        }

        /* Reflet eau sur cellules vides */
        .cell::before {
            content: "";
            position: absolute; inset: 0;
            background: linear-gradient(145deg, rgba(94,234,212,0.03) 0%, transparent 50%);
            pointer-events: none;
        }

        /* === CANNONBALL === */
        .cannonball {
            position: absolute;
            width: 12px; height: 12px;
            background: radial-gradient(circle at 35% 35%, #555, #1a1a1a);
            border-radius: 50%;
            z-index: 200;
            pointer-events: none;
            top: 50%; left: 50%;
            box-shadow: 1px 2px 4px rgba(0,0,0,0.8);
            animation: cannonballFall 0.45s cubic-bezier(0.12, 0, 0.39, 0) forwards;
        }
        .cannonball::after {
            content: "";
            position: absolute;
            width: 4px; height: 60px;
            background: linear-gradient(to bottom, rgba(80,80,80,0.5), transparent);
            bottom: 100%; left: 50%; transform: translateX(-50%);
            border-radius: 2px;
            animation: trailFade 0.35s ease-out forwards;
        }
        @keyframes cannonballFall {
            0%   { transform: translate(-50%, -400px) scale(0.5); opacity: 0.6; }
            60%  { opacity: 1; }
            85%  { transform: translate(-50%, -50%) scale(1.1); }
            100% { transform: translate(-50%, -50%) scale(0); opacity: 0; }
        }
        @keyframes trailFade {
            0%   { opacity: 0.6; height: 60px; }
            100% { opacity: 0; height: 10px; }
        }
        /* Impact splash */
        .cannon-impact {
            position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 0; height: 0;
            border-radius: 50%;
            pointer-events: none; z-index: 190;
            animation: cannonImpact 0.4s ease-out forwards;
        }
        .cannon-impact.water {
            border: 2px solid rgba(94,180,212,0.6);
            background: rgba(13,55,80,0.4);
        }
        .cannon-impact.fire {
            border: 2px solid rgba(255,140,40,0.8);
            background: rgba(255,100,30,0.3);
        }
        @keyframes cannonImpact {
            0%   { width: 4px; height: 4px; opacity: 1; }
            100% { width: 50px; height: 50px; opacity: 0; }
        }

        .cell.aiming {
            background: rgba(234,192,64,0.35);
            border: 1px dashed var(--brass-light);
            animation: aimPulse 0.8s ease-in-out infinite;
        }
        @keyframes aimPulse {
            0%, 100% { box-shadow: inset 0 0 4px rgba(234,192,64,0.2); }
            50%      { box-shadow: inset 0 0 10px rgba(234,192,64,0.5); }
        }

        .cell.hit {
            background: rgba(224,80,48,0.85) !important;
            border-color: rgba(224,80,48,0.5) !important;
            box-shadow: inset 0 0 8px rgba(224,80,48,0.4), 0 0 6px rgba(224,80,48,0.3);
        }
        .cell.sunk {
            background: linear-gradient(145deg, rgba(120,30,60,0.9), rgba(80,15,40,0.95)) !important;
            border-color: rgba(180,40,80,0.7) !important;
            box-shadow: inset 0 0 10px rgba(180,40,80,0.5), 0 0 8px rgba(180,40,80,0.4);
        }
        .cell.miss {
            background: rgba(13,33,55,0.3) !important;
            border-color: rgba(90,90,110,0.3) !important;
        }
        /* Marque X sur miss */
        .cell.miss::after {
            content: "\00d7"; font-size: 18px; color: rgba(90,90,110,0.5);
        }
        /* Flamme sur hit */
        .cell.hit::after {
            content: "\1F525"; font-size: 14px;
            animation: flicker 0.6s ease-in-out infinite alternate;
        }
        /* Sunk : pas d'icone (image bateau affichee a la place) */
        .cell.sunk::after {
            content: none;
        }
        @keyframes flicker {
            0%   { opacity: 0.7; transform: scale(1); }
            100% { opacity: 1;   transform: scale(1.15); }
        }

        /* --- RIPPLE AMELIORE --- */
        .ripple {
            position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            border-radius: 50%; pointer-events: none;
            z-index: 100; opacity: 0;
        }
        .ripple.water {
            border: 2px solid rgba(90,90,110,0.6);
            background: rgba(13,33,55,0.3);
            animation: rippleWave 0.8s ease-out;
        }
        .ripple.fire {
            border: 2px solid #e05030;
            background: rgba(224,80,48,0.25);
            animation: rippleWave 0.8s ease-out;
        }

        @keyframes rippleWave {
            0%   { width: 0;     height: 0;     opacity: 1; border-width: 4px; }
            100% { width: 100px; height: 100px; opacity: 0; border-width: 0; }
        }

        /* --- IMAGE BATEAU COULE --- */
        .sunk-ship-img {
            position: absolute; z-index: 8;
            pointer-events: none;
            image-rendering: auto;
            filter: saturate(0.3) brightness(0.5) sepia(0.6) hue-rotate(-10deg);
            opacity: 0.85;
            animation: sunkShipReveal 0.8s ease-out forwards;
            transform: translateZ(1px);
            backface-visibility: hidden;
        }
        @keyframes sunkShipReveal {
            0%   { opacity: 0; filter: saturate(0) brightness(2) sepia(0); }
            50%  { opacity: 0.9; filter: saturate(0.1) brightness(0.8) sepia(0.3); }
            100% { opacity: 0.85; filter: saturate(0.3) brightness(0.5) sepia(0.6) hue-rotate(-10deg); }
        }

        /* --- EXPLOSION HIT --- */
        .explosion-particle {
            position: absolute; border-radius: 50%;
            pointer-events: none; z-index: 120;
        }
        @keyframes explodeParticle {
            0%   { opacity: 1; transform: translate(0,0) scale(1); }
            100% { opacity: 0; transform: translate(var(--tx), var(--ty)) scale(0.2); }
        }
        .cell-flash {
            animation: cellFlashAnim 0.3s ease-out;
        }
        @keyframes cellFlashAnim {
            0%   { filter: brightness(3); }
            100% { filter: brightness(1); }
        }

        /* --- EXPLOSION SUNK (gros boom) --- */
        .sunk-boom {
            position: absolute; inset: 0;
            pointer-events: none; z-index: 130;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,180,50,0.9), rgba(224,80,48,0.6), transparent 70%);
            animation: sunkBoomAnim 0.6s ease-out forwards;
        }
        @keyframes sunkBoomAnim {
            0%   { transform: scale(0.3); opacity: 1; }
            50%  { transform: scale(2.5); opacity: 0.7; }
            100% { transform: scale(3.5); opacity: 0; }
        }
        .sunk-shockwave {
            position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            border-radius: 50%; pointer-events: none; z-index: 125;
            border: 3px solid rgba(255,200,100,0.8);
            animation: shockwaveAnim 0.7s ease-out forwards;
        }
        @keyframes shockwaveAnim {
            0%   { width: 0; height: 0; opacity: 1; }
            100% { width: 200px; height: 200px; opacity: 0; }
        }
        /* Shake de la grille quand un bateau est coulé */
        .grid-shake {
            animation: gridShakeAnim 0.4s ease-out;
        }
        @keyframes gridShakeAnim {
            0%, 100% { transform: translate(0,0); }
            20%  { transform: translate(-4px, 2px); }
            40%  { transform: translate(4px, -2px); }
            60%  { transform: translate(-3px, -1px); }
            80%  { transform: translate(2px, 3px); }
        }

        /* --- ECRAN DE FIN NAVAL --- */
        #end-screen {
            position: fixed; inset: 0;
            background: radial-gradient(ellipse at center, rgba(7,15,28,0.96), rgba(4,10,20,0.99));
            color: #e5e9f0;
            display: flex; flex-direction: column;
            justify-content: center; align-items: center;
            opacity: 0; pointer-events: none;
            transition: opacity 1s; z-index: 9999;
            overflow-y: auto;
        }

        #end-msg {
            font-size: 2.6rem; font-weight: bold;
            margin-bottom: 28px; letter-spacing: 0.25em;
            text-transform: uppercase;
            transform: scale(0); opacity: 0;
        }
        @keyframes bannerReveal {
            0%   { transform: scale(0) rotate(-5deg); opacity: 0; }
            60%  { transform: scale(1.1) rotate(1deg); opacity: 1; }
            100% { transform: scale(1) rotate(0deg); opacity: 1; }
        }

        /* Panneau recap */
        .recap-panel {
            background: linear-gradient(170deg, rgba(10,22,40,0.92), rgba(7,15,28,0.96));
            border: 2px solid var(--wood-light); border-radius: 6px;
            width: 400px; max-width: 92vw;
            overflow: hidden;
            box-shadow: 0 0 50px rgba(0,0,0,0.6), 0 0 0 1px rgba(200,147,62,0.08);
            opacity: 0; transform: translateY(30px);
        }
        .recap-brass {
            height: 4px;
            background: repeating-linear-gradient(90deg, #3d2008 0px, #c08030 6px, #f0b840 9px, #c08030 12px, #3d2008 18px);
        }
        .recap-content { padding: 24px 28px 20px; }

        /* Lignes de stats */
        .recap-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 13px 0; border-bottom: 1px solid rgba(200,147,62,0.1);
            opacity: 0; transform: translateX(-20px);
        }
        .recap-row:last-of-type { border-bottom: none; }
        .recap-row .label {
            color: var(--rope); font-size: 0.82rem;
            text-transform: uppercase; letter-spacing: 0.08em;
        }
        .recap-row .value {
            font-size: 1.05rem; font-weight: bold;
        }
        .recap-row .value.xp   { color: var(--accent); }
        .recap-row .value.gold { color: #ffd700; }

        /* Barre d'XP */
        .xp-bar-section {
            margin-top: 18px; padding-top: 14px;
            border-top: 1px solid rgba(200,147,62,0.12);
            opacity: 0;
        }
        .xp-bar-header {
            display: flex; justify-content: space-between;
            font-size: 0.7rem; color: var(--rope); margin-bottom: 6px;
            letter-spacing: 0.06em;
        }
        .xp-bar-track {
            height: 20px; background: rgba(13,33,55,0.8);
            border: 1px solid var(--wood-light); border-radius: 3px;
            overflow: hidden; position: relative;
        }
        .xp-bar-fill {
            height: 100%; width: 0%;
            background: linear-gradient(90deg, var(--accent), var(--accent), var(--accent));
            border-radius: 2px;
            transition: width 1.2s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 0 12px rgba(94,234,212,0.35);
            position: relative;
        }
        .xp-bar-fill::after {
            content: ""; position: absolute; inset: 0;
            background: linear-gradient(180deg, rgba(255,255,255,0.15) 0%, transparent 60%);
        }
        .xp-bar-fill.full {
            background: linear-gradient(90deg, #fbbf24, #f59e0b, #ffd700) !important;
            box-shadow: 0 0 20px rgba(251,191,36,0.5) !important;
        }

        /* Level up */
        .level-up-banner {
            text-align: center; margin-top: 16px;
            opacity: 0; transform: scale(0);
        }
        .level-up-text {
            display: inline-block; font-size: 1.15rem;
            color: #ffd700; letter-spacing: 0.15em;
            text-shadow: 0 0 20px rgba(255,215,0,0.5);
            padding: 6px 18px;
            border: 1px solid rgba(255,215,0,0.25);
            border-radius: 4px;
            background: rgba(255,215,0,0.06);
        }
        @keyframes levelUpPop {
            0%   { transform: scale(0) rotate(-8deg); opacity: 0; }
            50%  { transform: scale(1.15) rotate(2deg); opacity: 1; }
            100% { transform: scale(1) rotate(0deg); opacity: 1; }
        }

        /* Bouton retour */
        .btn-return {
            margin-top: 26px; padding: 14px 36px;
            font-size: 0.9rem;
            background: var(--wood); color: var(--brass-light);
            border: 2px solid var(--brass-light); cursor: pointer;
            border-radius: 2px;
            font-family: "PixelFont", monospace;
            letter-spacing: 0.12em; text-transform: uppercase;
            box-shadow: 4px 4px 0 rgba(0,0,0,0.5);
            transition: all 0.15s;
            opacity: 0; transform: translateY(20px);
        }
        .btn-return:hover {
            background: #3a2010; border-color: #ffd860; color: #ffd860;
            transform: translate(-2px,-2px); box-shadow: 6px 6px 0 rgba(0,0,0,0.5);
        }

    </style>
</head>
<body>
    <!-- Vignette de stress -->
    <div id="stress-vignette"></div>

    <!-- Son heartbeat (généré en JS via AudioContext) -->

    <h1>⚓ OPERATION NAVALE #<?= (int)$gameId ?></h1>

    <!-- MINIMAP MA FLOTTE -->
    <div id="minimap">
        <div id="minimap-header">
            <span id="minimap-title">🛡 Ma Flotte</span>
            <div class="minimap-btns">
                <button class="minimap-btn" id="btn-zoom" title="Zoom">🔍</button>
                <button class="minimap-btn" id="btn-toggle" title="Masquer">👁</button>
            </div>
        </div>
        <div id="minimap-grid">
            <?php
            $totalLife = 0;
            for ($y = 0; $y < $taille; $y++):
                for ($x = 0; $x < $taille; $x++):
                    $isShip = isset($myBoard[$y][$x]) && $myBoard[$y][$x] > 0;
                    if ($isShip) $totalLife++;
                    $c = $isShip ? 'ship' : '';
            ?>
                <div class="mini-cell <?= $c ?>" id="mini-<?= $x ?>-<?= $y ?>"></div>
            <?php endfor; endfor; ?>
        </div>
        <div id="minimap-hp-bar"><div id="minimap-hp-fill"></div></div>
        <div id="minimap-hp">Vie : <span id="hp-count"><?= $totalLife ?></span> / <?= $totalLife ?></div>
    </div>

    <div class="info-bar">
        <span id="timer"><?= $timeLeft ?>s</span>
        <span id="status">En attente...</span>
    </div>
    <!-- ✅ AJOUT : bouton quitter -->
<div style="margin-top: 10px;">
    <button id="btn-quit">🚪 Quitter la partie</button>
</div>

    <div class="grids">
        <?php foreach ($opponents as $opp):
            $isDead = $opp['player_status'] === 'dead';
            $isAlly = $opp['is_ally'];
        ?>
            <div class="grid-container" id="container-<?= $opp['id_player'] ?>"
                 style="<?= $isDead ? 'opacity:0.4;' : '' ?>
                        <?= $isAlly ? 'border-color:rgba(0,200,80,0.3);' : '' ?>">
                <div class="grid-title" style="<?= $isAlly ? 'color:#4caf50;' : '' ?>">
                    <?= htmlspecialchars($opp['Pseudo']) ?>
                    <?= $isDead ? ' 💀' : '' ?>
                    <?php if ($opp['team_number']): ?>
                        <span style="font-size:0.75rem; color:<?= $isAlly ? '#4caf50' : '#ef5350' ?>; margin-left:6px;">
                            <?= $isAlly ? '🤝 Allié' : '⚔ Ennemi' ?> <?= $opp['team_number'] ?>
                        </span>
                    <?php else: ?>
                        <span style="font-size:0.75rem; color:#ffeb3b; margin-left:6px;">BR</span>
                    <?php endif; ?>
                </div>
                <div class="grid" id="grid-<?= $opp['id_player'] ?>"></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Log de combat -->
    <div id="combat-log">
        <div id="combat-log-header">
            <span>Journal de combat</span>
            <button id="btn-toggle-log" title="Réduire">−</button>
        </div>
        <div id="combat-log-body"></div>
    </div>

    <!-- Modal naval réutilisable -->
    <div class="nv-overlay" id="naval-modal">
        <div class="nv-box">
            <div class="nv-brass"></div>
            <div class="nv-body">
                <div class="nv-title" id="nm-title"></div>
                <div class="nv-text" id="nm-text"></div>
                <div class="nv-buttons" id="nm-buttons"></div>
            </div>
        </div>
    </div>

    <div id="end-screen">
        <div id="end-msg"></div>
        <div class="recap-panel" id="recap-panel">
            <div class="recap-brass"></div>
            <div class="recap-content">
                <div class="recap-row" id="row-result">
                    <span class="label">Résultat</span>
                    <span class="value" id="recap-result">—</span>
                </div>
                <div class="recap-row" id="row-xp">
                    <span class="label"> Expérience</span>
                    <span class="value xp" id="recap-xp">+0 XP</span>
                </div>
                <div class="recap-row" id="row-gold">
                    <span class="label"> Gold</span>
                    <span class="value gold" id="recap-gold">+0</span>
                </div>
                <div class="recap-row" id="row-level-bonus" style="display:none;">
                    <span class="label"> Bonus niveau</span>
                    <span class="value gold" id="recap-level-bonus">+200</span>
                </div>
                <div class="xp-bar-section" id="xp-bar-section">
                    <div class="xp-bar-header">
                        <span id="xp-level-label">Niveau 1</span>
                        <span id="xp-progress-label">0 / 100 XP</span>
                    </div>
                    <div class="xp-bar-track">
                        <div class="xp-bar-fill" id="xp-bar-fill"></div>
                    </div>
                </div>
                <div class="level-up-banner" id="level-up-banner">
                    <span class="level-up-text">⬆ NIVEAU SUPÉRIEUR !</span>
                </div>
            </div>
        </div>
        <button class="btn-return" id="btn-return" onclick="window.location.href='index.php'">⚓ Retour au QG</button>
    </div>

    <script>
        const GAME_CONFIG = {
            gameId:          <?= (int)$gameId ?>,
            myId:            <?= (int)$myId ?>,
            gridSize:        <?= (int)$taille ?>,
            opponents:       <?= json_encode($opponents) ?>,
            totalLife:       <?= $totalLife ?>,
            myPseudo:        <?= json_encode($myPseudo) ?>,
            timeLeft:        <?= $timeLeft ?>,
            activeShipFolder:<?= json_encode($activeShipFolder) ?>,
            activeShipPrefix:<?= json_encode($activeShipPrefix) ?>,
            userVolume:      <?= $_userVol ?>,
            turnDuration:    <?= ROUND_DURATION ?>
        };
    </script>
    <script src="assets/js/play.js"></script>

    <!-- Musique de fond -->
    <audio id="bg-music" src="assets/sound/play_sound.mp3" loop></audio>
    <script>
    (function(){
        const a=document.getElementById('bg-music');
        const dbVol=GAME_CONFIG.userVolume;
        a.volume=dbVol/100;
        if(dbVol===0) a.muted=true;
        if(localStorage.getItem('bn_music_muted')==='1') a.muted=true;
        function tryPlay(){a.play().catch(()=>{});}
        tryPlay();
        document.addEventListener('click',function once(){tryPlay();document.removeEventListener('click',once);},{once:true});
    })();
    </script>
</body>
</html>
