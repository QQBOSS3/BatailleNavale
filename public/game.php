<?php
/* Lobby d'une partie - affiche le capitaine, l'équipage, et permet d'inviter/lancer */
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
$myId   = $_SESSION['uid'];

// 1. Infos Partie
$stmt = $pdo->prepare("
    SELECT g.*, m.name as mode_name, t.name as type_name 
    FROM games g
    LEFT JOIN mode m ON g.id_game_mode = m.id_Mode
    LEFT JOIN type t ON g.id_game_type = t.id_Type
    WHERE g.id_Game = ?
");
$stmt->execute([$gameId]);
$game = $stmt->fetch();

if (!$game) exit("Partie introuvable.");

// 2. Joueurs dans le lobby
$stmt = $pdo->prepare("
    SELECT gp.*, u.Pseudo, u.Avatar, st.image_prefix AS avatar_prefix
    FROM game_players gp
    JOIN users u ON gp.id_player = u.ID_Users
    LEFT JOIN skin_active sa ON sa.id_user = gp.id_player AND sa.category = 'avatar'
    LEFT JOIN skin_themes st ON st.id = sa.id_theme
    WHERE gp.id_game = ?
");
$stmt->execute([$gameId]);
$players = $stmt->fetchAll();

$isCreator = ($game['id_creator'] == $myId);

// Fond theme actif
$stmtFond = $pdo->prepare("
    SELECT st.image_prefix FROM skin_active sa
    JOIN skin_themes st ON st.id = sa.id_theme
    WHERE sa.id_user = ? AND sa.category = 'fond'
");
$stmtFond->execute([$myId]);
$activeFondPrefix = $stmtFond->fetchColumn() ?: null;
$lobbyBg = $activeFondPrefix ? "assets/img/lobby/Lobby1{$activeFondPrefix}.png" : "assets/img/lobby-bg.png";
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Lobby — Partie #<?= $gameId ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=2">
    <style>
        * { box-sizing: border-box; }
        body {
            background: url('<?= $lobbyBg ?>') no-repeat center center fixed;
            background-size: cover;
            font-family: "PixelFont", monospace;
            margin: 0; min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            color: #e5e9f0;
        }
        /* Vignette */
        body::after {
            content: ""; position: fixed; inset: 0; pointer-events: none; z-index: 0;
            <?= $activeFondPrefix ? 'display: none;' : 'background: radial-gradient(ellipse at center, transparent 30%, rgba(4,10,20,0.7) 100%);' ?>
        }

        /* === CONTAINER === */
        .lobby-container {
            width: 94%; max-width: 1100px; position: relative; z-index: 1;
            padding: 0; overflow: visible;
        }

        /* === HEADER === */
        .game-header {
            text-align: center; padding: 20px 30px 16px; position: relative;
        }
        .game-header h1 {
            margin: 0; color: #eac040; font-size: clamp(1rem, 2.2vw, 1.5rem);
            text-transform: uppercase; letter-spacing: 0.25em;
            text-shadow: 0 0 20px rgba(234,192,64,0.35);
        }
        .game-infos-badge {
            display: inline-flex; gap: 14px; margin-top: 10px; padding: 6px 20px;
            background: rgba(7,21,32,0.6); border-radius: 2px;
            color: #8b7355; font-size: 0.75rem; letter-spacing: 0.06em;
            border: 1px solid rgba(200,147,62,0.15);
            backdrop-filter: blur(6px);
        }
        .game-infos-badge .badge-sep { color: rgba(200,147,62,0.2); }

        /* === CAPITAINE (section haute) === */
        .captain-section {
            display: flex; flex-direction: column; align-items: center;
            padding: 20px 20px 10px; position: relative;
        }
        .captain-label {
            font-size: 0.6rem; letter-spacing: 0.35em; text-transform: uppercase;
            color: #eac040; margin-bottom: 10px;
            display: flex; align-items: center; gap: 10px;
        }
        .captain-label::before, .captain-label::after {
            content: ""; width: 40px; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(234,192,64,0.4), transparent);
        }

        .captain-card {
            position: relative; text-align: center;
            padding: 24px 40px 20px;
            background: rgba(7,15,28,0.5);
            border: 2px solid rgba(234,192,64,0.35);
            border-radius: 6px;
            backdrop-filter: blur(8px);
            box-shadow: 0 0 30px rgba(234,192,64,0.08), 0 8px 32px rgba(0,0,0,0.4);
            transition: all 0.3s;
            animation: captainGlow 4s ease-in-out infinite;
        }
        .captain-card:hover {
            border-color: #eac040;
            box-shadow: 0 0 40px rgba(234,192,64,0.15), 0 8px 32px rgba(0,0,0,0.4);
        }
        @keyframes captainGlow {
            0%, 100% { box-shadow: 0 0 30px rgba(234,192,64,0.08), 0 8px 32px rgba(0,0,0,0.4); }
            50%      { box-shadow: 0 0 40px rgba(234,192,64,0.18), 0 8px 32px rgba(0,0,0,0.4); }
        }
        .captain-crown {
            position: absolute; top: -18px; left: 50%; transform: translateX(-50%);
            font-size: 1.6rem;
            filter: drop-shadow(0 2px 6px rgba(234,192,64,0.5));
            animation: crownFloat 3s ease-in-out infinite;
        }
        @keyframes crownFloat {
            0%, 100% { transform: translateX(-50%) translateY(0); }
            50%      { transform: translateX(-50%) translateY(-4px); }
        }
        .captain-avatar {
            width: 120px; height: 120px; border-radius: 50%;
            border: 3px solid #eac040; object-fit: cover;
            display: block; margin: 0 auto 12px; background: #071520;
            box-shadow: 0 0 24px rgba(234,192,64,0.25), 0 0 0 6px rgba(234,192,64,0.06);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .captain-card:hover .captain-avatar {
            transform: scale(1.04);
            box-shadow: 0 0 32px rgba(234,192,64,0.35), 0 0 0 6px rgba(234,192,64,0.1);
        }
        .captain-pseudo {
            display: block; font-size: 1.2rem; font-weight: bold; color: #e5e9f0;
            margin-bottom: 4px; letter-spacing: 0.06em;
        }
        .captain-rank {
            font-size: 0.7rem; color: #eac040; letter-spacing: 0.18em;
            text-transform: uppercase;
            text-shadow: 0 0 8px rgba(234,192,64,0.3);
        }

        /* === SEPARATEUR === */
        .crew-divider {
            display: flex; align-items: center; gap: 12px;
            padding: 8px 30px;
            margin-top: 6px;
        }
        .crew-divider::before, .crew-divider::after {
            content: ""; flex: 1; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(139,115,85,0.3), transparent);
        }
        .crew-divider span {
            font-size: 0.55rem; letter-spacing: 0.3em; text-transform: uppercase;
            color: #8b7355; white-space: nowrap;
        }

        /* === EQUIPAGE === */
        .crew-grid {
            display: flex; justify-content: center; gap: 16px;
            padding: 10px 24px 20px; flex-wrap: wrap;
        }
        .crew-card {
            background: rgba(7,21,32,0.4);
            border: 1px solid rgba(139,115,85,0.2);
            border-radius: 4px;
            padding: 18px 16px 14px;
            text-align: center;
            width: 150px;
            backdrop-filter: blur(6px);
            transition: all 0.25s;
        }
        .crew-card:hover {
            border-color: rgba(200,147,62,0.5);
            background: rgba(14,28,48,0.45);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }
        .crew-card.ghost {
            opacity: 0.35; border-style: dashed;
            border-color: rgba(139,115,85,0.15);
        }
        .crew-card.ghost:hover { transform: none; box-shadow: none; background: rgba(7,21,32,0.4); }

        .crew-avatar {
            width: 72px; height: 72px; border-radius: 50%;
            border: 2px solid #8b7355; object-fit: cover;
            display: block; margin: 0 auto 10px; background: #071520;
            box-shadow: 0 0 12px rgba(139,115,85,0.15);
            transition: border-color 0.3s;
        }
        .crew-card:hover .crew-avatar { border-color: #c8933e; }

        .crew-pseudo {
            display: block; font-size: 0.82rem; font-weight: bold; color: #c8d0da;
            margin-bottom: 3px; letter-spacing: 0.04em;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .crew-rank {
            font-size: 0.6rem; color: #8b7355; letter-spacing: 0.12em;
            text-transform: uppercase;
        }
        .ghost-circle {
            width: 72px; height: 72px; border-radius: 50%;
            background: rgba(7,21,32,0.4); margin: 0 auto 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; color: rgba(139,115,85,0.3);
            border: 2px dashed rgba(139,115,85,0.15);
        }
        .ghost-text { color: rgba(139,115,85,0.4); font-size: 0.75rem; }

        /* Crew count small badge */
        .crew-count {
            text-align: center; margin-top: 2px; margin-bottom: 4px;
            font-size: 0.55rem; color: rgba(139,115,85,0.4);
            letter-spacing: 0.15em;
        }

        /* === ACTIONS === */
        .actions-bar {
            display: flex; justify-content: center; align-items: center;
            gap: 14px; padding: 16px 30px 22px;
            flex-wrap: wrap;
        }
        .btn-lobby {
            padding: 12px 24px; border: 2px solid; border-radius: 2px;
            font-size: 0.8rem; font-weight: bold; cursor: pointer; color: white;
            font-family: "PixelFont", monospace; letter-spacing: 0.1em;
            transition: all 0.2s; text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px;
            box-shadow: 3px 3px 0 rgba(0,0,0,0.5);
            text-transform: uppercase;
            backdrop-filter: blur(4px);
        }
        .btn-lobby:hover { transform: translate(-2px,-2px); box-shadow: 5px 5px 0 rgba(0,0,0,0.5); }
        .btn-lobby:active { transform: translate(0,0); box-shadow: 2px 2px 0 rgba(0,0,0,0.5); }

        .btn-invite { background: rgba(26,58,32,0.7); border-color: #4caf50; color: #4caf50; }
        .btn-invite:hover { background: rgba(34,74,40,0.8); border-color: #66bb6a; color: #66bb6a; }
        .btn-start { background: rgba(42,21,6,0.7); border-color: #eac040; color: #eac040; text-shadow: 0 0 10px rgba(234,192,64,0.3); }
        .btn-start:hover { background: rgba(58,32,16,0.8); border-color: #ffd860; color: #ffd860; }
        .btn-quit { background: rgba(42,10,8,0.7); border-color: #e05030; color: #e05030; }
        .btn-quit:hover { background: rgba(58,20,16,0.8); border-color: #ff6b50; color: #ff6b50; }

        .waiting-msg {
            font-size: 0.8rem; color: #8b7355; font-style: italic;
            animation: lantern-glow 2.5s ease-in-out infinite;
            background: rgba(7,21,32,0.4); padding: 10px 20px;
            border: 1px solid rgba(200,147,62,0.12); border-radius: 2px;
            backdrop-filter: blur(4px);
        }

        /* === SIDEBAR === */
        .sidebar {
            position: fixed; top: 0; right: -460px; width: 420px; height: 100%;
            background: linear-gradient(180deg, rgba(10,22,40,0.98) 0%, rgba(7,15,28,0.99) 100%);
            border-left: 2px solid #7a5a24;
            box-shadow: -10px 0 40px rgba(0,0,0,0.8); transition: right 0.3s ease-in-out;
            z-index: 9999; padding: 0; display: flex; flex-direction: column; color: #e5e9f0;
            font-family: "PixelFont", monospace;
        }
        .sidebar::before {
            content: ""; display: block; height: 5px; flex-shrink: 0;
            background: repeating-linear-gradient(90deg, #3d2008 0px, #c08030 8px, #f0b840 13px, #c08030 18px, #3d2008 26px);
        }
        .sidebar.open { right: 0; }
        .sidebar-header {
            display: flex; justify-content: space-between; align-items: center;
            margin: 0; padding: 22px 28px 18px;
            border-bottom: 1px solid #5a3a14; flex-shrink: 0;
        }
        .sidebar h3 { margin: 0; color: #eac040; font-size: 15px; letter-spacing: 0.1em; }
        .close-sidebar { background: none; border: none; color: #8b7355; font-size: 1.8rem; cursor: pointer; transition: color 0.15s; padding: 0 4px; }
        .close-sidebar:hover { color: #eac040; }
        .search-box { display: flex; gap: 10px; margin: 0; padding: 20px 28px; flex-shrink: 0; }
        .search-box input {
            flex: 1; padding: 12px 14px; border-radius: 2px;
            border: 1px solid #5a3a14; background: #071520; color: white;
            font-family: "PixelFont", monospace; font-size: 0.85rem;
        }
        .search-box input::placeholder { color: #5a6a7a; }
        .search-box input:focus { border-color: #eac040; outline: none; box-shadow: 0 0 8px rgba(234,192,64,0.15); }
        .search-box button {
            padding: 12px 18px; background: #2a1506; border: 1px solid #eac040;
            border-radius: 2px; color: #eac040; cursor: pointer; font-weight: bold;
            font-family: "PixelFont", monospace; font-size: 0.85rem; transition: all 0.15s;
        }
        .search-box button:hover { background: #3a2010; box-shadow: 0 0 8px rgba(234,192,64,0.2); }
        #search-result { padding: 0 28px; flex-shrink: 0; }
        .sidebar-section-title {
            margin: 0; padding: 14px 28px 10px; color: #eac040; font-size: 14px;
            letter-spacing: 0.1em; flex-shrink: 0; border-top: 1px solid rgba(200,147,62,0.12);
        }
        #friends-list-content { flex: 1; overflow-y: auto; padding: 0 12px 20px; }
        #friends-list-content::-webkit-scrollbar { width: 6px; }
        #friends-list-content::-webkit-scrollbar-track { background: transparent; }
        #friends-list-content::-webkit-scrollbar-thumb { background: #5a3a14; border-radius: 3px; }
        #friends-list-content::-webkit-scrollbar-thumb:hover { background: #c8933e; }
        .friend-row {
            padding: 14px 16px; margin-bottom: 4px; border-radius: 3px;
            border-bottom: 1px solid rgba(200,147,62,0.08);
            display: flex; justify-content: space-between; align-items: center;
            transition: background 0.15s;
        }
        .friend-row:hover { background: rgba(200,147,62,0.08); }
        .friend-row:last-child { border-bottom: none; }

        @keyframes lantern-glow {
            0%, 100% { opacity: 0.6; } 50% { opacity: 1; }
        }

        /* === RESPONSIVE === */
        @media (max-width: 700px) {
            .captain-avatar { width: 90px; height: 90px; }
            .captain-card { padding: 20px 28px 16px; }
            .crew-card { width: 120px; padding: 14px 10px 10px; }
            .crew-avatar { width: 56px; height: 56px; }
            .crew-pseudo { font-size: 0.72rem; }
            .ghost-circle { width: 56px; height: 56px; font-size: 1.1rem; }
        }
    </style>
</head>
<body>

    <?php
    // Separer capitaine et equipage
    $captain = null;
    $crew    = [];
    foreach ($players as $p) {
        if ((int)$p['id_player'] === (int)$game['id_creator']) {
            $captain = $p;
        } else {
            $crew[] = $p;
        }
    }
    function avatarSrc($p) {
        return $p['avatar_prefix']
            ? 'assets/img/Avatar/' . $p['Avatar'] . $p['avatar_prefix'] . '.png'
            : 'get_avatar.php?id=' . $p['Avatar'];
    }
    ?>

    <div class="lobby-container">

        <div class="game-header">
            <h1>Salle d'attente #<?= $gameId ?></h1>
            <div class="game-infos-badge">
                <span><?= htmlspecialchars($game['mode_name']) ?></span>
                <span class="badge-sep">|</span>
                <span><?= htmlspecialchars($game['type_name']) ?></span>
                <span class="badge-sep">|</span>
                <span><?= (int)$game['taille_grille'] ?>x<?= (int)$game['taille_grille'] ?></span>
            </div>
        </div>

        <div id="players-container">
            <!-- CAPITAINE -->
            <div class="captain-section">
                <div class="captain-label">Capitaine</div>
                <?php if ($captain): ?>
                <div class="captain-card">
                    <span class="captain-crown">👑</span>
                    <img src="<?= avatarSrc($captain) ?>" class="captain-avatar" alt="Avatar">
                    <span class="captain-pseudo"><?= htmlspecialchars($captain['Pseudo']) ?></span>
                    <span class="captain-rank">Chef de flotte</span>
                </div>
                <?php endif; ?>
            </div>

            <!-- EQUIPAGE -->
            <?php if (!empty($crew) || count($players) < 2): ?>
            <div class="crew-divider"><span>Equipage</span></div>
            <div class="crew-count"><?= count($crew) ?> matelot<?= count($crew) > 1 ? 's' : '' ?> a bord</div>
            <div class="crew-grid">
                <?php foreach ($crew as $c): ?>
                <div class="crew-card">
                    <img src="<?= avatarSrc($c) ?>" class="crew-avatar" alt="Avatar">
                    <span class="crew-pseudo"><?= htmlspecialchars($c['Pseudo']) ?></span>
                    <span class="crew-rank">Matelot</span>
                </div>
                <?php endforeach; ?>

                <?php if (count($players) < 2): ?>
                <div class="crew-card ghost">
                    <div class="ghost-circle">?</div>
                    <span class="ghost-text">En attente...</span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="actions-bar">
            <button class="btn-lobby btn-invite" onclick="openSidebar()">
                Inviter des amis
            </button>

            <?php if ($isCreator): ?>
                <button onclick="launchGame()" class="btn-lobby btn-start">
                    Lancer la partie
                </button>
            <?php else: ?>
                <div class="waiting-msg">
                    En attente du capitaine...
                </div>
            <?php endif; ?>

            <button class="btn-lobby btn-quit" onclick="quitGame()">
                Quitter
            </button>
        </div>
    </div>

    <div id="sidebar" class="sidebar">
        <div class="sidebar-header">
            <h3>🔍 Inviter un joueur</h3>
            <button class="close-sidebar" onclick="closeSidebar()">×</button>
        </div>

        <div class="search-box">
            <input type="text" id="friendPseudo" placeholder="Rechercher par pseudo...">
            <button onclick="searchAndInvite()">OK</button>
        </div>
        <div id="search-result"></div>

        <h3 class="sidebar-section-title">👥 Mes Amis</h3>
        <div id="friends-list-content">
            <p style="text-align:center; color:#888; margin-top:20px;">Chargement...</p>
        </div>
    </div>

    <script>
        const GAME_CONFIG = {
            gameId:     <?= (int)$gameId ?>,
            userVolume: <?= $_userVol ?>
        };
    </script>
    <script src="assets/js/lobby.js?v=2"></script>

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
    <audio id="bg-music" src="assets/sound/lobby_sound.mp3" loop></audio>
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