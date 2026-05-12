<?php
/* Page d'accueil - dashboard du joueur (profil, actions, amis, invitations) */
require __DIR__ . "/../config/db.php";
if (file_exists(__DIR__ . "/../config/xp.php")) {
    require_once __DIR__ . "/../config/xp.php";
}
require __DIR__ . "/../vendor/autoload.php";
use App\Service\FlashService;
session_start();

// Check si le joueur a déjà une partie en cours (pour afficher le bouton "Reprendre")
$activeGame = null;
if (!empty($_SESSION['uid'])) {
    $stmt = $pdo->prepare("
        SELECT g.id_Game, g.id_team_mode, g.id_game_type, g.status
        FROM games g
        JOIN game_players gp ON g.id_Game = gp.id_game
        WHERE gp.id_player = ?
          AND gp.player_status != 'left'
          AND g.status = 'preparation'
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['uid']]);
    $activeGame = $stmt->fetch();
}

if (empty($_SESSION['uid'])) {
    FlashService::add('error', 'Veuillez vous connecter pour accéder à votre espace.');
    header("Location: login.php");
    exit;
}

// Mettre à jour le timestamp d'activité
$pdo->prepare("UPDATE users SET last_activity = ?, Online = 1 WHERE ID_Users = ?")
    ->execute([time(), $_SESSION['uid']]);

$stmt = $pdo->prepare("
    SELECT u.*, a.Name AS avatar_name
    FROM users u
    LEFT JOIN avatar a ON u.Avatar = a.ID_Avatar
    WHERE u.ID_Users = ?
");
$stmt->execute([$_SESSION['uid']]);
$user = $stmt->fetch();
if (!$user) {
    FlashService::add('error', 'Utilisateur introuvable.');
    header("Location: login.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM `option` WHERE ID_Users = ?");
$stmt->execute([$_SESSION['uid']]);
$userOptions = $stmt->fetch();

// Themes actifs (avatar + fond)
$stmtSkin = $pdo->prepare("
    SELECT sa.category, st.image_prefix FROM skin_active sa
    JOIN skin_themes st ON st.id = sa.id_theme
    WHERE sa.id_user = ? AND sa.category IN ('avatar','fond')
");
$stmtSkin->execute([$_SESSION['uid']]);
$activeAvatarPrefix = null;
$activeFondPrefix   = null;
while ($skinRow = $stmtSkin->fetch()) {
    if ($skinRow['category'] === 'avatar') $activeAvatarPrefix = $skinRow['image_prefix'];
    if ($skinRow['category'] === 'fond')   $activeFondPrefix   = $skinRow['image_prefix'];
}

// Boutons thematiques
$btnFolder = null;
if ($activeFondPrefix) {
    $fondToBtnFolder = [
        'cosmique' => 'cosmique',
        'neon'     => 'neon',
        'enfer'    => 'enfer',
        'fantome'  => 'fantome',
        'fleur'    => 'florale',
    ];
    $btnFolder = $fondToBtnFolder[$activeFondPrefix] ?? null;
}
function themedBtn(string $default, ?string $folder): string {
    static $map = [
        'btn-friends.png'  => 'friend',
        'btn-options.png'  => 'option',
        'btn-update.png'   => 'update',
        'btn-skin.png'     => 'skin',
        'btn-rules.png'    => 'gr',
        'btn-gamemode.png' => 'gm',
    ];
    if ($folder && isset($map[$default])) {
        return 'assets/img/button/' . $folder . '/' . $map[$default] . '_' . $folder . '.png';
    }
    return 'assets/img/' . $default;
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Accueil — Bataille Navale</title>
    <link rel="stylesheet" href="assets/css/style.css?v=2">
</head>
<body class="home-bg"<?php if ($activeFondPrefix): ?> style="background-image:url('assets/img/Fond/bg_<?= htmlspecialchars($activeFondPrefix) ?>.png')"<?php endif; ?>>

    <?php if ($activeGame): ?>
        <div class="active-game-banner">
            ⚓ Partie en cours (#<?= (int)$activeGame['id_Game'] ?>) —
            <a href="game.php?id=<?= (int)$activeGame['id_Game'] ?>">Rejoindre le lobby</a>
            (<?= htmlspecialchars($activeGame['status']) ?>)
        </div>
    <?php endif; ?>

    <?php
    $flashes = FlashService::getAll();
    if ($flashes): ?>
        <div class="flashes">
            <?php foreach ($flashes as $type => $messages): ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="flash <?= htmlspecialchars($type) ?>">
                        <?= htmlspecialchars($msg) ?>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['kicked'])): ?>
        <div class="nv-overlay visible" id="kicked-modal">
            <div class="nv-box">
                <div class="nv-brass"></div>
                <div class="nv-body">
                    <div class="nv-title danger">⚓ Expulsé</div>
                    <div class="nv-text"><?= htmlspecialchars($_SESSION['kicked']) ?></div>
                    <div class="nv-buttons">
                        <button class="naval-btn naval-btn-ok" onclick="document.getElementById('kicked-modal').classList.remove('visible')">Compris</button>
                    </div>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['kicked']); ?>
    <?php endif; ?>

    <!-- ===================== BLOC JOUEUR (haut gauche) ===================== -->
    <div class="player-panel">
        <img id="current-avatar"
             src="<?= $activeAvatarPrefix ? 'assets/img/Avatar/' . $user['Avatar'] . $activeAvatarPrefix . '.png' : 'get_avatar.php?id=' . $user['Avatar'] ?>"
             alt="Avatar"
             class="player-avatar"
             onclick="openAvatarMenu()">
        <div class="player-info">
            <p class="player-name"><?= htmlspecialchars($user['Pseudo']) ?></p>
            <p class="player-level">Lvl <?= (int)$user['niveau'] ?></p>
            <p class="player-gold"><?= (int)($user['Gold'] ?? 0) ?> Gold</p>
            <?php if (function_exists('xpRequiredForLevel')):
                $currentXp  = (int)($user['xp'] ?? 0);
                $xpNeeded   = xpRequiredForLevel((int)$user['niveau']);
                $xpPercent  = $xpNeeded > 0 ? min(100, round($currentXp / $xpNeeded * 100)) : 0;
            ?>
            <div class="xp-bar-container">
                <div class="xp-bar-fill" style="width: <?= $xpPercent ?>%"></div>
            </div>
            <p class="xp-text"><?= $currentXp ?> / <?= $xpNeeded ?> XP</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===================== POPUP AVATAR ===================== -->
    <div id="overlay" class="overlay hidden" onclick="closeAvatarMenu()"></div>
    <div id="avatar-menu" class="avatar-menu hidden">
        <h3 class="avatar-title">Profil du Capitaine</h3>
        <div class="avatar-menu-header-line"></div>

        <?php
        $stmt = $pdo->prepare("SELECT Win, Defeat, Game_Played FROM ratio WHERE ID_Profil = ?");
        $stmt->execute([$_SESSION['uid']]);
        $ratio = $stmt->fetch(PDO::FETCH_ASSOC);
        $wins   = (int)($ratio['Win'] ?? 0);
        $losses = (int)($ratio['Defeat'] ?? 0);
        $played = (int)($ratio['Game_Played'] ?? 0);
        $winRate = $played > 0 ? round($wins / $played * 100) : 0;
        ?>
        <div class="profile-stats">
            <div class="stat-item">
                <span class="stat-value"><?= $played ?></span>
                <span class="stat-label">Parties</span>
            </div>
            <div class="stat-item stat-win">
                <span class="stat-value"><?= $wins ?></span>
                <span class="stat-label">Victoires</span>
            </div>
            <div class="stat-item stat-lose">
                <span class="stat-value"><?= $losses ?></span>
                <span class="stat-label">Defaites</span>
            </div>
            <div class="stat-item">
                <span class="stat-value"><?= $winRate ?>%</span>
                <span class="stat-label">Win Rate</span>
            </div>
        </div>

        <h3 class="avatar-title" style="margin-top: 16px;">Choisis ton avatar</h3>
        <div class="avatar-grid">
            <?php if ($activeAvatarPrefix):
                for ($i = 1; $i <= 9; $i++):
                    $imgPath = "assets/img/Avatar/{$i}{$activeAvatarPrefix}.png";
            ?>
                <img src="<?= $imgPath ?>"
                     alt="Avatar <?= $i ?>"
                     class="avatar-choice"
                     onclick="changeAvatar(<?= $i ?>)">
            <?php endfor;
            else:
                $stmt = $pdo->query("SELECT ID_Avatar, Name FROM avatar");
                while ($av = $stmt->fetch()): ?>
                <img src="get_avatar.php?id=<?= $av['ID_Avatar'] ?>"
                     alt="<?= htmlspecialchars($av['Name']) ?>"
                     class="avatar-choice"
                     onclick="changeAvatar(<?= $av['ID_Avatar'] ?>)">
            <?php endwhile;
            endif; ?>
        </div>
        <button class="close-btn" onclick="closeAvatarMenu()">Fermer</button>
    </div>

    <!-- ===================== BOUTON FRIENDS ===================== -->
    <div class="btn-friends">
        <a href="javascript:void(0)" class="btn-img" onclick="openFriendsMenu()">
            <img src="<?= themedBtn('btn-friends.png', $btnFolder) ?>" alt="Friends">
        </a>
    </div>

    <!-- Menu Friends coulissant (caché par défaut via translateX en CSS) -->
    <div id="friends-overlay" class="overlay hidden" onclick="closeFriendsMenu()"></div>
    <div id="friends-menu" class="friends-menu">
        <h3>👥 Amis</h3>

        <form id="search-friend-form" method="post" action="send_friend_request.php">
            <input type="text" name="pseudo" placeholder="Rechercher un pseudo..." required>
            <button type="submit">🔍</button>
        </form>
        <div id="search-result"></div>

        <hr>
        <h4>📥 Demandes reçues</h4>
        <div id="friend-requests">
            <?php
            $stmt = $pdo->prepare("
                SELECT f.ID_Friends, u.Pseudo 
                FROM friends f 
                JOIN users u ON u.ID_Users = f.Sender_ID
                WHERE f.Receiver_ID = ? AND f.Status = 'Pending'
            ");
            $stmt->execute([$_SESSION['uid']]);
            $requests = $stmt->fetchAll();
            if ($requests) {
                foreach ($requests as $r) {
                    echo "<p>" . htmlspecialchars($r['Pseudo']) . "
                      <form method='post' action='validate_friend.php' style='display:inline'>
                          <input type='hidden' name='id_friends' value='" . $r['ID_Friends'] . "'>
                          <button type='submit' name='action' value='accept'>✅</button>
                          <button type='submit' name='action' value='reject'>❌</button>
                      </form>
                    </p>";
                }
            } else {
                echo "<p>Aucune demande.</p>";
            }
            ?>
        </div>

        <hr>
        <h4>👥 Mes amis</h4>
        <?php
        $stmt = $pdo->prepare("
            SELECT u.ID_Users, u.Pseudo,
                   (u.last_activity IS NOT NULL AND u.last_activity > UNIX_TIMESTAMP() - 120) AS Online
            FROM friends f
            JOIN users u ON
              (u.ID_Users = f.Sender_ID AND f.Receiver_ID = ?)
              OR (u.ID_Users = f.Receiver_ID AND f.Sender_ID = ?)
            WHERE f.Status = 'Accepted'
        ");
        $stmt->execute([$_SESSION['uid'], $_SESSION['uid']]);
        $friends = $stmt->fetchAll();
        if ($friends) {
            foreach ($friends as $fr) {
                echo "<p>" . htmlspecialchars($fr['Pseudo']) . " " . ($fr['Online'] ? "🟢" : "🔴") . "</p>";
            }
        } else {
            echo "<p>Aucun ami pour le moment.</p>";
        }
        ?>

        <hr>
        <h4>📩 Invitations de partie</h4>
        <div id="invites-container"><p>Chargement...</p></div>
    </div>

    <!-- ===================== BOUTON OPTIONS ===================== -->
    <div class="btn-options">
        <div class="options-wrapper">
            <a href="javascript:void(0)" class="btn-img-trigger" onclick="toggleOptionsMenu()">
                <img src="<?= themedBtn('btn-options.png', $btnFolder) ?>" alt="Options">
            </a>
            <div id="options-menu" class="options-panel hidden">
                <div class="options-header">
                    <h3>⚙️ Paramètres</h3>
                    <button class="close-btn" onclick="toggleOptionsMenu()">×</button>
                </div>
                <div class="options-content">
                    <form id="options-form">
                        <div class="opt-group">
                            <label>Volume</label>
                            <input type="range" name="volume" min="0" max="100" class="slider"
                                   value="<?= (int)($userOptions['Volume'] ?? 50) ?>">
                        </div>
                        <div class="opt-row">
                            <div class="opt-group half">
                                <label>Langue</label>
                                <select name="languages">
                                    <option value="fr" <?= ($userOptions['Languages'] ?? '') === 'fr' ? 'selected' : '' ?>>🇫🇷 FR</option>
                                    <option value="en" <?= ($userOptions['Languages'] ?? '') === 'en' ? 'selected' : '' ?>>🇬🇧 EN</option>
                                </select>
                            </div>
                            <div class="opt-group half">
                                <label>Thème</label>
                                <select name="theme">
                                    <option value="normal"    <?= ($userOptions['Theme'] ?? '') === 'normal'    ? 'selected' : '' ?>>Défaut</option>
                                    <option value="noel"      <?= ($userOptions['Theme'] ?? '') === 'noel'      ? 'selected' : '' ?>>Noël</option>
                                    <option value="halloween" <?= ($userOptions['Theme'] ?? '') === 'halloween' ? 'selected' : '' ?>>Hallow.</option>
                                    <option value="ete"       <?= ($userOptions['Theme'] ?? '') === 'ete'       ? 'selected' : '' ?>>Été</option>
                                </select>
                            </div>
                        </div>
                        <div class="opt-group row-center">
                            <label for="cb-colorblind" style="cursor:pointer;">Mode daltonien</label>
                            <label class="switch-toggle">
                                <input type="checkbox" id="cb-colorblind" name="colorblind" value="1"
                                       <?= !empty($userOptions['Colorblind']) ? 'checked' : '' ?>>
                                <span class="slider-switch"></span>
                            </label>
                        </div>
                    </form>
                    <hr class="divider">
                    <div class="options-links">
                        <a href="update_info.php" class="opt-link">📝 Profil</a>
                        <a href="logout.php" class="opt-link logout">🚪 Déconnexion</a>
                    </div>
                    <div class="danger-zone">
                        <form action="delete_account.php" method="POST" id="delete-account-form">
                            <button type="button" class="btn-delete" id="btn-delete-account">🗑️ Supprimer mon compte</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== BOUTON UPDATE ===================== -->
    <div class="btn-update">
        <a href="javascript:void(0)" class="btn-img" onclick="openUpdatePopup()">
            <img src="<?= themedBtn('btn-update.png', $btnFolder) ?>" alt="Update">
        </a>
    </div>
    <div id="update-overlay" class="overlay hidden" onclick="closeUpdatePopup()"></div>
    <div id="update-popup" class="update-popup hidden">
        <h3 class="update-title">📢 Dernière mise à jour</h3>
        <div class="update-content">
            <?php
            $stmt = $pdo->query("SELECT New_version FROM `update` ORDER BY ID_Update DESC LIMIT 1");
            $latest = $stmt->fetch();
            echo $latest ? nl2br(htmlspecialchars($latest['New_version'])) : "Aucune mise à jour trouvée.";
            ?>
        </div>
        <button class="close-btn" onclick="closeUpdatePopup()">Fermer</button>
    </div>

    <!-- ===================== BOUTON SKIN ===================== -->
    <div class="btn-skin">
        <a href="shop.php" class="btn-img">
            <img src="<?= themedBtn('btn-skin.png', $btnFolder) ?>" alt="Skin">
        </a>
    </div>

    <!-- ===================== BOUTON RULES ===================== -->
    <div class="bottom-left">
        <a href="javascript:void(0)" class="btn-img" onclick="openRulesPopup()">
            <img src="<?= themedBtn('btn-rules.png', $btnFolder) ?>" alt="Rules">
        </a>
    </div>
    <div id="rules-overlay" class="overlay hidden" onclick="closeRulesPopup()"></div>
    <div id="rules-popup" class="rules-popup hidden">
        <h3 class="rules-title">📜 Règles du jeu</h3>
        <?php
        $stmt = $pdo->query("SELECT * FROM rules ORDER BY ID_Rules DESC LIMIT 1");
        $rules = $stmt->fetch();
        ?>
        <?php if ($rules): ?>
            <div class="rules-tabs">
                <button class="tab-btn active" onclick="showRuleTab('fr')">🇫🇷 French</button>
                <button class="tab-btn" onclick="showRuleTab('be')">🇧🇪 Belgium</button>
                <button class="tab-btn" onclick="showRuleTab('br')">⚔️ Battle Royal</button>
                <button class="tab-btn" onclick="showRuleTab('team')">👥 Team</button>
            </div>
            <div class="rules-content">
                <div id="tab-fr" class="tab-content active"><p><?= nl2br(htmlspecialchars($rules['French'])) ?></p></div>
                <div id="tab-be" class="tab-content"><p><?= nl2br(htmlspecialchars($rules['Belgium'])) ?></p></div>
                <div id="tab-br" class="tab-content"><p><?= nl2br(htmlspecialchars($rules['BattleRoyal'])) ?></p></div>
                <div id="tab-team" class="tab-content"><p><?= nl2br(htmlspecialchars($rules['Team'])) ?></p></div>
            </div>
        <?php else: ?>
            <p>Aucune règle trouvée.</p>
        <?php endif; ?>
        <button class="close-btn" onclick="closeRulesPopup()">Fermer</button>
    </div>

<!-- ===================== BOUTON GAMEMODE (centre) ===================== -->
<div class="center-btn">
    <a href="javascript:void(0)" class="btn-img" onclick="openGamemodeModal()">
        <img src="<?= themedBtn('btn-gamemode.png', $btnFolder) ?>" alt="Gamemode">
    </a>
</div>
<div id="gamemode-overlay" class="overlay hidden" onclick="closeGamemodeModal()"></div>

<div id="gamemode-modal" class="gamemode-modal hidden">
    <div class="gamemode-content">

        <div class="gamemode-header">
            <p class="gamemode-title">Salle de commandement</p>
            <p class="gamemode-subtitle">Choisissez votre mission, Capitaine</p>
            <div class="gm-locale-toggle">
                <img src="assets/img/gm-fr.png" class="gm-locale-btn active" id="locale-fr"
                     onclick="setLocale('fr')" alt="Français">
                <img src="assets/img/gm-be.png" class="gm-locale-btn" id="locale-be"
                     onclick="setLocale('be')" alt="Belge">
            </div>
        </div>

        <div class="gamemode-body">

            <!-- GRILLE PRINCIPALE -->
            <div class="gamemode-step active" id="step-main">
                <div class="gm-grid-layout">
                    <div class="gm-col-left">
                        <div class="gm-tile" onclick="selectMode('br')">
                            <img src="assets/img/gm-br.png" alt="Battle Royale">
                        </div>
                        <div class="gm-tile" onclick="selectMode('solo')">
                            <img src="assets/img/gm-solo.png" alt="Solo">
                        </div>
                        <div class="gm-tile gm-tile-list" onclick="goToList()">
                            <span class="gm-tile-text">⚓ Tous les salons</span>
                        </div>
                    </div>
                    <div class="gm-col-right">
                        <div class="gm-tile" onclick="selectMode('2vs2')">
                            <img src="assets/img/gm-2v2.png" alt="2 vs 2">
                        </div>
                        <div class="gm-tile" onclick="selectMode('3vs3')">
                            <img src="assets/img/gm-3v3.png" alt="3 vs 3">
                        </div>
                        <div class="gm-tile" onclick="selectMode('4vs4')">
                            <img src="assets/img/gm-4v4.png" alt="4 vs 4">
                        </div>
                        <div class="gm-tile gm-tile-private" onclick="selectMode('private')">
                            <img src="assets/img/gm-private.png" alt="Privée">
                        </div>
                    </div>
                </div>
            </div>

            <!-- CHOIX : CRÉER ou REJOINDRE -->
            <div class="gamemode-step hidden" id="step-action">
                <div class="gm-cards">
                    <div class="gm-card" onclick="doCreate()">
                        <img src="assets/img/gm-create.png" alt="Créer">
                    </div>
                    <div class="gm-card" onclick="doJoin()">
                        <img src="assets/img/gm-join.png" alt="Rejoindre">
                    </div>
                </div>
                <img src="assets/img/gm-quit.png" class="gm-img-btn" onclick="showStep('main')" alt="Retour">
            </div>

        </div>

        <div class="gamemode-footer">
            <button class="gm-img-btn-wrap" onclick="closeGamemodeModal()">
                <img src="assets/img/Back_to_menu.png" alt="Abandonner le bord">
            </button>
        </div>
    </div>
</div>

    <script>
        function refreshInvites() {
            fetch("get_game_invites.php")
                .then(r => r.text())
                .then(data => { document.getElementById("invites-container").innerHTML = data; })
                .catch(err => console.error(err));
        }
        setInterval(refreshInvites, 5000);
        refreshInvites();
    </script>
    <!-- Modal suppression compte -->
    <div class="nv-overlay" id="delete-modal">
        <div class="nv-box">
            <div class="nv-brass"></div>
            <div class="nv-body">
                <div class="nv-title danger">⚠ Supprimer votre compte ?</div>
                <div class="nv-text">Cette action est irréversible.<br>Toutes vos données seront perdues.</div>
                <div class="nv-buttons">
                    <button class="naval-btn naval-btn-cancel" id="delete-cancel">Annuler</button>
                    <button class="naval-btn naval-btn-danger" id="delete-confirm">Supprimer</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('btn-delete-account').addEventListener('click', () => {
            document.getElementById('delete-modal').classList.add('visible');
        });
        document.getElementById('delete-cancel').addEventListener('click', () => {
            document.getElementById('delete-modal').classList.remove('visible');
        });
        document.getElementById('delete-confirm').addEventListener('click', () => {
            document.getElementById('delete-account-form').submit();
        });
    </script>
    <script>
        var avatarSkinPrefix = <?= json_encode($activeAvatarPrefix) ?>;
    </script>
    <script src="assets/js/app.js"></script>

    <!-- Musique de fond -->
    <audio id="bg-music" src="assets/sound/index_sound.mp3" loop></audio>
    <script>
    (function(){
        const a=document.getElementById('bg-music');
        const dbVol=<?= (int)($userOptions['Volume'] ?? 50) ?>;
        a.volume=dbVol/100;
        if(dbVol===0) a.muted=true;
        if(localStorage.getItem('bn_music_muted')==='1') a.muted=true;
        function tryPlay(){a.play().catch(()=>{});}
        tryPlay();
        document.addEventListener('click',function once(){tryPlay();document.removeEventListener('click',once);},{once:true});
        // Sync slider -> audio en temps reel
        const sl=document.querySelector('#options-form input[name="volume"]');
        if(sl) sl.addEventListener('input',function(){a.volume=this.value/100;a.muted=(this.value==0);});
    })();
    </script>
</body>
</html>