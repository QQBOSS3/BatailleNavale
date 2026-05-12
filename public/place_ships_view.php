<?php
/* Page de placement des bateaux - grille interactive avec drag & drop
   Le joueur place sa flotte puis valide, ensuite il attend les autres */
require __DIR__ . "/../config/db.php";
session_start();

if (empty($_SESSION['uid'])) {
    header("Location: login.php");
    exit;
}

// Volume utilisateur
$stmtVol = $pdo->prepare("SELECT Volume FROM `option` WHERE ID_Users = ?");
$stmtVol->execute([$_SESSION['uid']]);
$volRow = $stmtVol->fetch();
$_userVol = $volRow ? (int)$volRow['Volume'] : 50;

$gameId = (int)($_GET['id'] ?? 0);
if ($gameId <= 0) exit("Partie introuvable.");

$stmt = $pdo->prepare("SELECT * FROM games WHERE id_Game=?");
$stmt->execute([$gameId]);
$game = $stmt->fetch();
if (!$game) exit("Partie introuvable.");

// 1. Taille dynamique
$taille = $game['taille_grille'] ?? 10;

// 2. Règles (1=FR/Espacé, 2=BE/Collé)
$version = (int)($game['id_version'] ?? 1); 
$isFrench = ($version !== 2); // Si ce n'est pas 2 (Belge), c'est Français (strict)

// Vérifier si déjà validé
$stmt = $pdo->prepare("SELECT validated FROM player_boards WHERE game_id=? AND player_id=?");
$stmt->execute([$gameId, $_SESSION['uid']]);
$alreadyValidated = (bool)$stmt->fetchColumn();

// Themes actifs (fond + bateau)
$stmtSkins = $pdo->prepare("
    SELECT sa.category, st.image_prefix, st.folder_name FROM skin_active sa
    JOIN skin_themes st ON st.id = sa.id_theme
    WHERE sa.id_user = ? AND sa.category IN ('fond','bateau')
");
$stmtSkins->execute([$_SESSION['uid']]);
$activeFondPrefix = null;
$activeShipFolder = null;
$activeShipPrefix = null;
while ($sk = $stmtSkins->fetch()) {
    if ($sk['category'] === 'fond') $activeFondPrefix = $sk['image_prefix'];
    if ($sk['category'] === 'bateau') { $activeShipFolder = $sk['folder_name']; $activeShipPrefix = $sk['image_prefix']; }
}
$placementBg = $activeFondPrefix ? "assets/img/lobby/Lobby1{$activeFondPrefix}.png" : "assets/img/lobby-bg.png";
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Placement des bateaux — Partie #<?= (int)$gameId ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=2">
    <style>
        * { box-sizing: border-box; }
        body {
            background: url('<?= $placementBg ?>') no-repeat center center fixed;
            background-size: cover;
            color: #e5e9f0;
            font-family: "PixelFont", monospace;
            overflow-x: hidden;
            transition: opacity 1s ease;
            opacity: 1; margin: 0;
            text-align: center; user-select: none;
            min-height: 100vh;
        }
        body::after {
            content: ""; position: fixed; inset: 0; pointer-events: none;
            background: radial-gradient(ellipse at center, transparent 30%, rgba(4,10,20,0.65) 100%);
            z-index: 0;
        }

        #placement-ui, #waiting-ui { position: relative; z-index: 1; }
        #placement-ui { display: flex; flex-direction: column; align-items: center; min-height: 100vh; }
        #waiting-ui { display: none; margin-top: 100px; }

        h1 {
            font-size: 1.4rem; color: #eac040; letter-spacing: 0.2em;
            text-transform: uppercase; margin: 14px 0 6px;
            text-shadow: 0 0 16px rgba(234,192,64,0.35);
        }

        /* === LAYOUT HORIZONTAL === */
        .placement-layout {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            gap: 24px;
            padding: 10px 20px;
            flex: 1;
            width: 100%;
            max-width: 1200px;
        }

        /* Panneau gauche : Flotte */
        .panel-left {
            background: linear-gradient(170deg, rgba(10,22,40,0.85) 0%, rgba(7,15,28,0.9) 100%);
            border: 1px solid #5a3a14; border-radius: 4px;
            padding: 18px 20px; width: 220px; flex-shrink: 0;
            box-shadow: 4px 4px 0 rgba(0,0,0,0.4);
            backdrop-filter: blur(4px);
        }
        .panel-left h3 { color: #eac040; font-size: 14px; letter-spacing: 0.12em; margin: 0 0 12px; text-align: center; }

        /* Panneau droit : Actions */
        .panel-right {
            background: linear-gradient(170deg, rgba(10,22,40,0.85) 0%, rgba(7,15,28,0.9) 100%);
            border: 1px solid #5a3a14; border-radius: 4px;
            padding: 18px 20px; width: 200px; flex-shrink: 0;
            box-shadow: 4px 4px 0 rgba(0,0,0,0.4);
            backdrop-filter: blur(4px);
            display: flex; flex-direction: column; gap: 8px;
        }
        .panel-right h3 { color: #eac040; font-size: 14px; letter-spacing: 0.12em; margin: 0 0 8px; text-align: center; }

        /* Centre : Grille */
        .grid-wrapper {
            display: flex; flex-direction: column; align-items: center;
        }

        /* GRILLE OCEAN */
        .grid {
            display: grid;
            grid-template-rows: repeat(<?= $taille ?>, 40px);
            grid-template-columns: repeat(<?= $taille ?>, 40px);
            gap: 2px; margin: 0 auto; width: max-content;
            background: rgba(7,21,32,0.5);
            border: 2px solid #5a3a14; border-radius: 4px;
            padding: 6px;
            box-shadow: 0 0 30px rgba(0,0,0,0.5), inset 0 0 20px rgba(0,0,0,0.3);
        }

        .cell {
            width: 40px; height: 40px;
            background: rgba(13,33,55,0.6);
            border: 1px solid rgba(200,147,62,0.12);
            cursor: crosshair; position: relative;
            transition: background 0.12s, border-color 0.12s;
        }
        .cell:hover { background: rgba(200,147,62,0.15); border-color: rgba(200,147,62,0.3); }

        .cell.ship {
            background: linear-gradient(145deg, rgba(200,147,62,0.55), rgba(139,115,85,0.4));
            border-color: #c8933e;
            box-shadow: inset 0 0 6px rgba(200,147,62,0.3);
        }
        .cell.preview { background: rgba(200,147,62,0.25); border-color: rgba(200,147,62,0.4); }
        .cell.invalid { background: rgba(224,80,48,0.5) !important; border-color: #e05030 !important; cursor: not-allowed; }

        /* Image bateau superposee */
        .ship-img {
            position: absolute; z-index: 10;
            pointer-events: none;
            image-rendering: auto;
            filter: drop-shadow(0 0 4px rgba(200,147,62,0.3));
            transition: opacity 0.3s;
        }
        .ship-img-preview {
            position: absolute; z-index: 15;
            pointer-events: none;
            image-rendering: auto;
            opacity: 0.5;
            filter: drop-shadow(0 0 6px rgba(200,147,62,0.4));
        }
        .ship-img-preview.invalid-preview {
            filter: drop-shadow(0 0 6px rgba(224,80,48,0.6)) hue-rotate(320deg) saturate(2);
        }

        /* Boutons flotte avec nom */
        .ship-btn {
            display: flex; justify-content: space-between; align-items: center;
            background: rgba(7,21,32,0.7); color: #8b7355;
            padding: 10px 14px; margin: 5px 0;
            border: 1px solid #3a2810; border-radius: 2px;
            cursor: pointer; width: 100%; box-sizing: border-box;
            font-family: "PixelFont", monospace; font-size: 12px;
            transition: all 0.15s; letter-spacing: 0.06em;
        }
        .ship-btn:hover { background: rgba(14,28,48,0.8); border-color: #7a5a24; color: #c8933e; }
        .ship-btn.selected { background: #2a1506; border-color: #eac040; color: #eac040; box-shadow: 0 0 10px rgba(234,192,64,0.2); }
        .ship-btn.disabled { opacity: 0.3; cursor: not-allowed; }
        .ship-btn .ship-name { font-size: 11px; color: #c8933e; display: block; text-align: left; }
        .ship-btn .ship-blocks { font-size: 10px; opacity: 0.7; letter-spacing: 2px; }
        .ship-btn .ship-count { font-size: 11px; white-space: nowrap; }
        .ship-btn.disabled .ship-name { color: #555; }

        /* Boutons actions (panneau droit) */
        .action-btn {
            background: rgba(7,21,32,0.7); border: 1px solid #5a3a14;
            color: #8b7355; padding: 10px 14px; border-radius: 2px;
            cursor: pointer; font-family: "PixelFont", monospace; font-size: 12px;
            transition: all 0.15s; letter-spacing: 0.06em;
            width: 100%; text-align: center;
        }
        .action-btn:hover { border-color: #c8933e; color: #eac040; background: rgba(14,28,48,0.8); }

        .timer-display {
            text-align: center; font-size: 13px; color: #8b7355;
            padding: 10px 0; border-top: 1px solid rgba(200,147,62,0.12);
            margin-top: 4px;
        }
        .timer-display #timer { font-weight: bold; font-size: 18px; }

        /* Barre du bas : Valider */
        .bottom-bar {
            display: flex; justify-content: center; align-items: center;
            gap: 16px; padding: 12px 20px 20px; flex-wrap: wrap;
        }

        #validateBtn {
            background: #2a1506; border: 2px solid #eac040; color: #eac040;
            padding: 14px 40px; border-radius: 2px; cursor: pointer;
            font-family: "PixelFont", monospace; font-size: 14px;
            letter-spacing: 0.14em; text-transform: uppercase;
            box-shadow: 4px 4px 0 rgba(0,0,0,0.4);
            transition: all 0.15s;
            text-shadow: 0 0 10px rgba(234,192,64,0.3);
        }
        #validateBtn:hover { background: #3a2010; transform: translate(-2px,-2px); box-shadow: 6px 6px 0 rgba(0,0,0,0.4); }
        #validateBtn:disabled { background: rgba(7,15,28,0.6); border-color: #3a2810; color: #3a2810; box-shadow: none; transform: none; text-shadow: none; cursor: not-allowed; }

        #statusLbl { font-size: 12px; color: #8b7355; }

        /* Waiting */
        .loader { font-size: 2.5rem; animation: compass-sway 2s ease-in-out infinite; display: inline-block; margin-bottom: 16px; }
        @keyframes compass-sway { 0%, 100% { transform: rotate(-8deg); } 50% { transform: rotate(8deg); } }

        .rules-badge {
            display: inline-block; padding: 5px 14px; border-radius: 2px;
            font-size: 0.75rem; margin-bottom: 6px; letter-spacing: 0.08em;
            <?php if ($isFrench): ?>
            background: rgba(30,58,138,0.4); border: 1px solid rgba(59,130,246,0.3); color: #93c5fd;
            <?php else: ?>
            background: rgba(120,80,20,0.4); border: 1px solid rgba(200,147,62,0.3); color: #eac040;
            <?php endif; ?>
        }

        .panel {
            background: linear-gradient(170deg, rgba(10,22,40,0.85) 0%, rgba(7,15,28,0.9) 100%);
            border: 1px solid #5a3a14; border-radius: 4px;
            padding: 14px 20px; min-width: 220px;
            box-shadow: 4px 4px 0 rgba(0,0,0,0.4);
            backdrop-filter: blur(4px);
        }

        #waiting-ui .panel {
            display: inline-block; padding: 40px 50px;
        }
        #waiting-ui h2 { color: #eac040; font-size: 1.1rem; letter-spacing: 0.12em; margin: 0 0 8px; }
        #waiting-ui p { color: #8b7355; font-size: 12px; }
        #wait-status { color: #c8933e; margin-top: 16px; font-size: 14px; letter-spacing: 0.08em; }

        /* Responsive : si ecran trop petit, on empile */
        @media (max-width: 900px) {
            .placement-layout { flex-direction: column; align-items: center; }
            .panel-left, .panel-right { width: 100%; max-width: 440px; }
            .panel-left { order: 2; }
            .grid-wrapper { order: 1; }
            .panel-right { order: 3; }
        }
    </style>
</head>

<body>
    <!-- SCENE 1 : PLACEMENT -->
    <div id="placement-ui">
        <h1>⚓ Placement des bateaux</h1>
        <div class="rules-badge">
            Règles : <?= $isFrench ? "🇫🇷 Françaises (Espacé)" : "🇧🇪 Belges (Collé)" ?>
        </div>

        <div class="placement-layout">
            <!-- PANNEAU GAUCHE : Flotte -->
            <div class="panel-left">
                <h3>🚢 Flotte</h3>
                <div id="fleetBox"></div>
            </div>

            <!-- CENTRE : Grille -->
            <div class="grid-wrapper">
                <div id="grid" class="grid"></div>
            </div>

            <!-- PANNEAU DROIT : Actions -->
            <div class="panel-right">
                <h3>⚙ Actions</h3>
                <button id="rotateBtn" class="action-btn">🔄 Rotation (R)</button>
                <button id="resetBtn" class="action-btn">♻ Reset</button>
                <button id="autoPlaceBtn" class="action-btn">🎲 Aléatoire</button>
                <button id="quitBtn" class="action-btn">🚪 Quitter</button>
                <div class="timer-display">
                    Temps<br><span id="timer" style="color:#0f0">60</span>s
                </div>
            </div>
        </div>

        <div class="bottom-bar">
            <button id="validateBtn" disabled>✅ Valider et Attendre</button>
            <span id="statusLbl">Place tes bateaux.</span>
        </div>
    </div>

    <!-- SCENE 2 : ATTENTE -->
    <div id="waiting-ui">
        <div class="panel" style="display:inline-block; padding: 40px;">
            <div class="loader">⏳</div>
            <h2>Flotte validée !</h2>
            <p>En attente que les autres joueurs terminent...</p>
            <h3 id="wait-status" style="color: #00bcd4; margin-top:20px;">Chargement...</h3>
        </div>
    </div>

    <script>
        const GAME_CONFIG = {
            gameId:            <?= (int)$gameId ?>,
            gridSize:          <?= (int)$taille ?>,
            alreadyValidated:  <?= $alreadyValidated ? 'true' : 'false' ?>,
            isFrenchRules:     <?= $isFrench ? 'true' : 'false' ?>,
            activeShipFolder:  <?= json_encode($activeShipFolder) ?>,
            activeShipPrefix:  <?= json_encode($activeShipPrefix) ?>,
            userVolume:        <?= $_userVol ?>
        };
    </script>
    <script src="assets/js/place_ships.js"></script>

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
    <!-- Musique de fond -->
    <audio id="bg-music" src="assets/sound/place_view_ship_sound.mp3" loop></audio>
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