<?php
/* Liste des parties publiques - affichage radar/sonar avec animation des blips */
require __DIR__ . "/../config/db.php";
require __DIR__ . "/../config/constants.php";
session_start();

// Volume utilisateur
$_userVol = 50;
if (!empty($_SESSION['uid'])) {
    $stmtVol = $pdo->prepare("SELECT Volume FROM `option` WHERE ID_Users = ?");
    $stmtVol->execute([$_SESSION['uid']]);
    $volRow = $stmtVol->fetch();
    if ($volRow) $_userVol = (int)$volRow['Volume'];
}

// --- RENDU DES BLIPS SONAR ---
function renderBlips($games, $pdo) {
    if (!$games) {
        echo '<div class="no-signal">Aucun signal detecte<br><span>Le radar ne capte aucune partie active</span></div>';
        return;
    }

    foreach ($games as $g) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM game_players WHERE id_game=?");
        $stmt->execute([$g['id_Game']]);
        $currentPlayers = (int)$stmt->fetchColumn();

        $typeID   = (int)$g['id_game_type'];
        $teamSize = (int)($g['team_size'] ?? 0);
        if ($teamSize === 0) $teamSize = (int)$g['id_team_mode'];

        $modeLabel = "Inconnu"; $formatLabel = "? vs ?"; $maxPlayers = 0;
        if ($typeID === TYPE_BATTLEROYALE) {
            $modeLabel = "Battle Royale"; $formatLabel = "Survivant"; $maxPlayers = 50;
        } elseif ($typeID === TYPE_SOLO) {
            $modeLabel = "Solo"; $formatLabel = "1 vs 1"; $maxPlayers = 2;
        } elseif ($typeID === TYPE_TEAM) {
            $modeLabel = "Team"; $formatLabel = $teamSize." vs ".$teamSize; $maxPlayers = $teamSize * 2;
        }

        $rulesVal = $g['rules'] ?? $g['game_rules'] ?? 1;
        $rulesLabel = ($rulesVal == 2 || $rulesVal === 'be') ? "BE" : "FR";
        $playerColor = ($currentPlayers >= $maxPlayers) ? '#ef4444' : '#5eead4';

        echo '<div class="game-blip" data-id="'.(int)$g['id_Game'].'">';
        echo   '<div class="blip-dot"></div>';
        echo   '<div class="blip-tag">#'.(int)$g['id_Game'].'</div>';
        echo   '<div class="blip-card">';
        echo     '<div class="bc-header">';
        echo       '<span class="bc-id">#'.(int)$g['id_Game'].'</span>';
        echo       '<span class="bc-host">Cpt. '.htmlspecialchars($g['host']).'</span>';
        echo     '</div>';
        echo     '<div class="bc-row"><span>Mode</span><span class="bc-val">'.$modeLabel.'</span></div>';
        echo     '<div class="bc-row"><span>Format</span><span class="bc-val">'.$formatLabel.'</span></div>';
        echo     '<div class="bc-row"><span>Regles</span><span class="bc-val">'.$rulesLabel.'</span></div>';
        echo     '<div class="bc-row"><span>Joueurs</span><span class="bc-val" style="color:'.$playerColor.'">'.$currentPlayers.'/'.$maxPlayers.'</span></div>';
        echo     '<a href="join_game.php?id='.(int)$g['id_Game'].'" class="blip-join">Rejoindre</a>';
        echo   '</div>';
        echo '</div>';
    }
}

// --- FILTRES ---
$filterMode = isset($_GET['game_mode']) ? (int)$_GET['game_mode'] : 0;
$filterType = isset($_GET['game_type']) ? (int)$_GET['game_type'] : 0;
$filterTeam = isset($_GET['team_mode']) ? (int)$_GET['team_mode'] : 0;

$where  = ["g.status = 'preparation'"];
$params = [];

if ($filterMode === 1 || $filterMode === 0) {
    $where[] = "g.id_game_mode = ?"; $params[] = PUBLIC_MODE_ID;
}
if ($filterType > 0) { $where[] = "g.id_game_type = ?"; $params[] = $filterType; }
if ($filterTeam > 0) { $where[] = "g.id_team_mode = ?"; $params[] = $filterTeam; }

try {
    $pdo->query("UPDATE games g LEFT JOIN game_players gp_all ON g.id_Game = gp_all.id_game LEFT JOIN game_players gp_host ON g.id_Game = gp_host.id_game AND gp_host.id_player = g.id_creator SET g.status = 'finished' WHERE g.status = 'preparation' AND (gp_all.id_game IS NULL OR gp_host.id_player IS NULL)");
} catch (Exception $e) {}

$sqlBase = "
  SELECT g.*, u.Pseudo AS host, m.name as mode_name, t.name as type_name, tm.team_size
  FROM games g
  JOIN users u ON u.ID_Users = g.id_creator
  LEFT JOIN mode m ON g.id_game_mode = m.id_Mode
  LEFT JOIN type t ON g.id_game_type = t.id_Type
  LEFT JOIN team_mode tm ON g.id_team_mode = tm.id_Team
  WHERE " . implode(" AND ", $where) . "
  ORDER BY g.id_Game DESC
";

try {
    $stmt = $pdo->prepare($sqlBase);
    $stmt->execute($params);
    $games = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Erreur SQL : " . $e->getMessage());
}

// Fond theme actif
$myId = $_SESSION['uid'] ?? 0;
$activeFondPrefix = null;
if ($myId) {
    $stmtFond = $pdo->prepare("
        SELECT st.image_prefix FROM skin_active sa
        JOIN skin_themes st ON st.id = sa.id_theme
        WHERE sa.id_user = ? AND sa.category = 'fond'
    ");
    $stmtFond->execute([$myId]);
    $activeFondPrefix = $stmtFond->fetchColumn() ?: null;
}

// --- AJAX ---
if (isset($_GET['ajax'])) {
    renderBlips($games, $pdo);
    exit;
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Radar de Parties — Bataille Navale</title>
  <link rel="stylesheet" href="assets/css/style.css?v=2">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        background: #030810;
        <?php if ($activeFondPrefix): ?>
        background-image: url('assets/img/Fond/bg_<?= $activeFondPrefix ?>.png');
        background-size: cover; background-position: center;
        <?php endif; ?>
        font-family: "PixelFont", monospace;
        color: #e5e9f0;
        overflow: hidden;
        height: 100vh;
        image-rendering: pixelated;
    }

    /* === HEADER === */
    .radar-header {
        position: fixed; top: 0; left: 0; right: 0; z-index: 100;
        display: flex; align-items: center; justify-content: center;
        padding: 14px 24px;
        background: rgba(3,8,16,0.9);
        border-bottom: 1px solid rgba(94,234,212,0.12);
        backdrop-filter: blur(8px);
    }
    .radar-header h1 {
        font-size: clamp(0.9rem, 1.6vw, 1.3rem);
        color: #5eead4; letter-spacing: 0.25em; text-transform: uppercase;
        text-shadow: 0 0 20px rgba(94,234,212,0.4);
    }
    .btn-back {
        position: absolute; left: 20px;
        background: rgba(94,234,212,0.05); border: 1px solid rgba(94,234,212,0.2);
        color: rgba(94,234,212,0.6); padding: 7px 16px; border-radius: 2px;
        cursor: pointer; font-family: inherit; font-size: 0.75rem;
        letter-spacing: 0.08em; transition: all 0.2s; text-decoration: none;
    }
    .btn-back:hover { border-color: #5eead4; color: #5eead4; background: rgba(94,234,212,0.1); }

    .radar-count {
        position: absolute; right: 20px;
        font-size: 0.7rem; color: rgba(94,234,212,0.5); letter-spacing: 0.1em;
    }

    /* === SONAR === */
    .sonar-wrapper {
        position: fixed;
        top: 50%; left: 50%;
        transform: translate(-50%, -50%);
        width: min(82vmin, 720px);
        height: min(82vmin, 720px);
    }

    .sonar-circle {
        position: absolute; inset: 0;
        border-radius: 50%;
        border: 2px solid rgba(94,234,212,0.25);
        background: radial-gradient(circle, rgba(6,18,28,0.3) 0%, rgba(3,8,16,0.92) 70%);
        overflow: hidden;
        box-shadow: 0 0 60px rgba(94,234,212,0.06), inset 0 0 80px rgba(3,8,16,0.5);
    }
    /* Pixel scanlines */
    .sonar-circle::after {
        content: ""; position: absolute; inset: 0;
        background: repeating-linear-gradient(
            0deg,
            transparent 0px, transparent 3px,
            rgba(94,234,212,0.015) 3px, rgba(94,234,212,0.015) 4px
        );
        pointer-events: none; z-index: 2;
    }

    /* Rings */
    .sonar-ring {
        position: absolute; border-radius: 50%;
        border: 1px dashed rgba(94,234,212,0.09);
    }
    .sonar-ring.r1 { inset: 12.5%; }
    .sonar-ring.r2 { inset: 25%; }
    .sonar-ring.r3 { inset: 37.5%; }

    /* Crosshairs */
    .sonar-cross-h {
        position: absolute; left: 0; right: 0; top: calc(50% - 1px);
        height: 2px; background: rgba(94,234,212,0.05);
    }
    .sonar-cross-v {
        position: absolute; top: 0; bottom: 0; left: calc(50% - 1px);
        width: 2px; background: rgba(94,234,212,0.05);
    }

    /* Compass labels */
    .sonar-compass {
        position: absolute; font-size: 0.55rem; color: rgba(94,234,212,0.25);
        letter-spacing: 0.1em; font-weight: bold;
    }
    .sonar-compass.n { top: 4%; left: 50%; transform: translateX(-50%); }
    .sonar-compass.s { bottom: 4%; left: 50%; transform: translateX(-50%); }
    .sonar-compass.e { right: 4%; top: 50%; transform: translateY(-50%); }
    .sonar-compass.o { left: 4%; top: 50%; transform: translateY(-50%); }

    /* Center dot */
    .sonar-center {
        position: absolute; top: 50%; left: 50%;
        width: 6px; height: 6px; margin: -3px 0 0 -3px;
        background: #5eead4; border-radius: 0;
        box-shadow: 0 0 10px rgba(94,234,212,0.6), 0 0 24px rgba(94,234,212,0.2);
        z-index: 5;
    }

    /* Center label */
    .sonar-label {
        position: absolute; top: calc(50% + 14px); left: 50%;
        transform: translateX(-50%);
        font-size: 0.45rem; color: rgba(94,234,212,0.2);
        letter-spacing: 0.3em; text-transform: uppercase;
    }

    /* Sweep */
    .sonar-sweep-group {
        position: absolute; inset: 0;
        border-radius: 50%;
        animation: sweep-rotate 6s linear infinite;
    }
    .sonar-sweep {
        position: absolute; inset: 0;
        border-radius: 50%;
        background: conic-gradient(
            from 0deg,
            rgba(94,234,212,0.18) 0deg,
            rgba(94,234,212,0.08) 15deg,
            rgba(94,234,212,0.03) 35deg,
            transparent 55deg,
            transparent 360deg
        );
    }
    .sonar-line {
        position: absolute; top: 50%; left: 50%;
        width: 50%; height: 1.5px;
        background: linear-gradient(90deg, rgba(94,234,212,0.7), rgba(94,234,212,0));
        transform-origin: 0% 50%;
    }

    @keyframes sweep-rotate {
        from { transform: rotate(0deg); }
        to   { transform: rotate(360deg); }
    }

    /* === BLIPS === */
    #blips-container {
        position: absolute; inset: 0;
        border-radius: 50%;
        z-index: 10;
    }

    .game-blip {
        position: absolute;
        transform: translate(-50%, -50%);
        z-index: 10; cursor: pointer;
    }
    .blip-dot {
        width: 8px; height: 8px;
        background: rgba(94,234,212,0.2);
        border-radius: 0;
        transition: background 0.3s, box-shadow 0.3s;
        image-rendering: pixelated;
    }
    .game-blip.pinged .blip-dot {
        background: #5eead4;
        box-shadow: 0 0 12px rgba(94,234,212,0.8), 0 0 28px rgba(94,234,212,0.3);
    }

    /* Ping ripple */
    .blip-ping {
        position: absolute; top: 50%; left: 50%;
        width: 8px; height: 8px; margin: -4px;
        border-radius: 0;
        border: 2px solid rgba(94,234,212,0.5);
        animation: blip-ripple 1.5s ease-out forwards;
        pointer-events: none;
    }
    @keyframes blip-ripple {
        0%   { transform: scale(1); opacity: 0.7; }
        100% { transform: scale(6); opacity: 0; }
    }

    /* ID tag */
    .blip-tag {
        position: absolute; left: calc(100% + 6px);
        top: 50%; transform: translateY(-50%);
        font-size: 0.55rem; color: #5eead4;
        white-space: nowrap; letter-spacing: 0.06em;
        pointer-events: none;
        transition: opacity 0.3s;
    }

    /* Card (click to open) */
    .blip-card {
        position: absolute;
        bottom: calc(100% + 14px); left: 50%;
        transform: translateX(-50%) scale(0.92);
        opacity: 0; pointer-events: none;
        transition: opacity 0.2s, transform 0.2s;
        width: 210px;
        background: rgba(3,10,18,0.96);
        border: 1px solid rgba(94,234,212,0.3);
        border-radius: 2px; padding: 10px 12px;
        font-size: 0.7rem;
        backdrop-filter: blur(10px);
        box-shadow: 0 4px 24px rgba(0,0,0,0.7), 0 0 12px rgba(94,234,212,0.06);
        z-index: 50;
        image-rendering: auto;
    }
    .blip-card.below {
        bottom: auto; top: calc(100% + 14px);
    }
    .game-blip.active .blip-card {
        opacity: 1; transform: translateX(-50%) scale(1);
        pointer-events: auto;
    }

    .bc-header {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 7px; padding-bottom: 5px;
        border-bottom: 1px solid rgba(94,234,212,0.1);
    }
    .bc-id { color: #5eead4; font-weight: bold; font-size: 0.75rem; }
    .bc-host { color: rgba(94,234,212,0.4); font-size: 0.6rem; }
    .bc-row {
        display: flex; justify-content: space-between;
        padding: 2px 0; color: rgba(94,234,212,0.35);
    }
    .bc-val { color: #e5e9f0; font-weight: bold; }

    .blip-join {
        display: block; margin-top: 8px; padding: 7px;
        background: rgba(94,234,212,0.08);
        border: 1px solid rgba(94,234,212,0.4); border-radius: 2px;
        color: #5eead4; text-align: center; text-decoration: none;
        font-family: inherit; font-size: 0.7rem; font-weight: bold;
        letter-spacing: 0.12em; text-transform: uppercase;
        transition: all 0.15s;
    }
    .blip-join:hover { background: rgba(94,234,212,0.18); border-color: #5eead4; }

    /* No signal */
    .no-signal {
        position: absolute; top: 50%; left: 50%;
        transform: translate(-50%, -50%);
        text-align: center; color: rgba(94,234,212,0.35);
        font-size: 0.85rem; letter-spacing: 0.1em;
        z-index: 10;
    }
    .no-signal span { font-size: 0.65rem; opacity: 0.5; display: block; margin-top: 6px; }

    /* Pixel grid */
    .sonar-static {
        position: absolute; inset: 0; border-radius: 50%;
        background-image:
            linear-gradient(rgba(94,234,212,0.02) 1px, transparent 1px),
            linear-gradient(90deg, rgba(94,234,212,0.02) 1px, transparent 1px);
        background-size: 20px 20px;
        pointer-events: none; z-index: 1;
    }

    /* Responsive */
    @media (max-width: 600px) {
        .sonar-wrapper { width: 95vmin; height: 95vmin; }
        .blip-card { width: 170px; font-size: 0.62rem; }
        .radar-header h1 { font-size: 0.8rem; }
    }
  </style>
</head>

<body>
    <div class="radar-header">
        <a href="index.php" class="btn-back">Retour</a>
        <h1>Sonar</h1>
        <span class="radar-count" id="radar-count"><?= count($games) ?> signal<?= count($games) > 1 ? 's' : '' ?></span>
    </div>

    <div class="sonar-wrapper">
        <div class="sonar-circle">
            <div class="sonar-ring r1"></div>
            <div class="sonar-ring r2"></div>
            <div class="sonar-ring r3"></div>
            <div class="sonar-cross-h"></div>
            <div class="sonar-cross-v"></div>
            <div class="sonar-static"></div>
        </div>

        <span class="sonar-compass n">N</span>
        <span class="sonar-compass s">S</span>
        <span class="sonar-compass e">E</span>
        <span class="sonar-compass o">O</span>

        <div class="sonar-sweep-group">
            <div class="sonar-sweep"></div>
            <div class="sonar-line"></div>
        </div>

        <div class="sonar-center"></div>
        <div class="sonar-label">Sonar</div>

        <div id="blips-container">
            <?php renderBlips($games, $pdo); ?>
        </div>
    </div>

    <script>const GAME_CONFIG = { userVolume: <?= $_userVol ?> };</script>
    <script src="assets/js/list_games.js"></script>

    <!-- Musique de fond -->
    <audio id="bg-music" src="assets/sound/list_game_sound.mp3" loop></audio>
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
