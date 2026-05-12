<?php
/* Boutique de skins - achat et équipement de thèmes (fond, bateau, avatar) avec du gold */
require __DIR__ . "/../vendor/autoload.php";
require __DIR__ . "/../config/db.php";
session_start();

use App\Repository\SkinRepository;
use App\Middleware\AuthMiddleware;

AuthMiddleware::requireAuth();

$myId = (int)$_SESSION['uid'];
$skinRepo = new SkinRepository($pdo);

// Volume utilisateur
$stmtVol = $pdo->prepare("SELECT Volume FROM `option` WHERE ID_Users = ?");
$stmtVol->execute([$myId]);
$volRow = $stmtVol->fetch();
$_userVol = $volRow ? (int)$volRow['Volume'] : 50;

$stmt = $pdo->prepare("SELECT Pseudo, Gold FROM users WHERE ID_Users = ?");
$stmt->execute([$myId]);
$user = $stmt->fetch();
if (!$user) { header("Location: login.php"); exit; }

$gold = (int)$user['Gold'];

// Themes par categorie
$themes = $skinRepo->getAllThemes();

// Achats du joueur
$owned = $skinRepo->getOwnedThemeIds($myId);

// Themes actifs du joueur (par categorie)
$activeThemes = $skinRepo->getActiveThemes($myId);
$activeMap = [];
$activeFondPrefix = null;
foreach ($activeThemes as $cat => $info) {
    $activeMap[$cat] = $info['id_theme'];
    if ($cat === 'fond') $activeFondPrefix = $info['image_prefix'];
}
$shopBg = $activeFondPrefix ? "assets/img/skin/Skin1{$activeFondPrefix}.png" : "assets/img/bg_shop.png";

// --- Actions POST ---
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $themeId = (int)($_POST['theme_id'] ?? 0);

    $theme = $skinRepo->findThemeById($themeId);

    if ($theme) {
        if ($action === 'buy') {
            if (in_array($themeId, $owned)) {
                $message = 'Vous possedez deja ce pack.';
                $messageType = 'error';
            } elseif ($gold < (int)$theme['price']) {
                $message = 'Gold insuffisant.';
                $messageType = 'error';
            } else {
                $skinRepo->purchase($myId, $themeId, (int)$theme['price']);
                $gold -= (int)$theme['price'];
                $owned[] = $themeId;
                $message = 'Pack "' . htmlspecialchars($theme['name']) . '" achete !';
                $messageType = 'success';
            }
        } elseif ($action === 'equip') {
            if (!in_array($themeId, $owned)) {
                $message = 'Vous ne possedez pas ce pack.';
                $messageType = 'error';
            } else {
                $skinRepo->equip($myId, $theme['category'], $themeId);
                $activeMap[$theme['category']] = $themeId;
                $message = 'Pack "' . htmlspecialchars($theme['name']) . '" equipe !';
                $messageType = 'success';
            }
        } elseif ($action === 'unequip') {
            $skinRepo->unequip($myId, $theme['category']);
            unset($activeMap[$theme['category']]);
            $message = 'Retour au style par defaut pour "' . htmlspecialchars($theme['category']) . '".';
            $messageType = 'success';
        }
    }
}

// Regrouper par categorie
$byCategory = [];
foreach ($themes as $t) {
    $byCategory[$t['category']][] = $t;
}

$categoryLabels = [
    'avatar'  => 'Packs Avatars',
    'bateau'  => 'Skins Bateaux',
    'fond'    => 'Fonds & Decors',
];
$categoryIcons = [
    'avatar'  => 'assets/img/Avatar/1.png',
    'bateau'  => null,
    'fond'    => null,
];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Boutique - Bataille Navale</title>
    <link rel="stylesheet" href="assets/css/style.css?v=2">
    <style>
        body.shop-bg {
            margin: 0; min-height: 100vh;
            background: url('<?= $shopBg ?>') no-repeat center center fixed;
            background-size: cover;
            font-family: "PixelFont", monospace;
            color: #e5e9f0;
        }
        body.shop-bg::after {
            content: ""; position: fixed; inset: 0; pointer-events: none; z-index: 0;
            background: radial-gradient(ellipse at center, transparent 20%, rgba(4,10,20,0.7) 100%);
        }

        .shop-header {
            position: relative; z-index: 1;
            display: flex; justify-content: space-between; align-items: center;
            padding: 20px 32px;
            background: rgba(10,22,40,0.85);
            border-bottom: 2px solid var(--brass-dark);
            backdrop-filter: blur(6px);
        }
        .shop-header h1 {
            font-size: clamp(1.1rem, 2vw, 1.5rem);
            color: var(--brass-light);
            letter-spacing: 0.15em; text-transform: uppercase;
            margin: 0;
        }
        .shop-header-right {
            display: flex; align-items: center; gap: 20px;
        }
        .gold-display {
            background: rgba(200,147,62,0.1);
            border: 1px solid var(--brass-dark);
            border-radius: 4px;
            padding: 6px 14px;
            color: var(--brass-light); font-size: clamp(0.85rem, 1.2vw, 1rem);
            letter-spacing: 0.08em;
        }
        .gold-display b { color: #ffd700; }
        .btn-back {
            text-decoration: none;
            color: var(--brass);
            border: 1px solid var(--brass-dark);
            border-radius: 4px;
            padding: 6px 14px;
            font-family: inherit; font-size: 0.85rem;
            letter-spacing: 0.08em;
            transition: all 0.2s;
        }
        .btn-back:hover { color: var(--brass-light); border-color: var(--brass); }

        .shop-flash {
            position: relative; z-index: 1;
            max-width: 600px; margin: 16px auto 0;
            padding: 10px 16px; border-radius: 4px;
            font-size: 0.85rem; text-align: center;
            animation: flashFade 0.4s ease-out;
        }
        .shop-flash.success { background: rgba(16,185,129,0.15); border: 1px solid #10b981; color: #6ee7b7; }
        .shop-flash.error { background: rgba(239,68,68,0.15); border: 1px solid #ef4444; color: #fca5a5; }
        @keyframes flashFade { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

        .shop-content {
            position: relative; z-index: 1;
            max-width: 1200px; margin: 0 auto;
            padding: 24px 24px 60px;
        }

        /* Titre de section */
        .section-title {
            font-size: clamp(0.9rem, 1.4vw, 1.1rem);
            color: var(--brass-light);
            letter-spacing: 0.12em; text-transform: uppercase;
            margin: 32px 0 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--brass-dark);
        }
        .section-title:first-child { margin-top: 0; }

        /* Grille */
        .shop-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }

        /* Carte pack */
        .shop-card {
            background: linear-gradient(170deg, rgba(10,22,40,0.92), rgba(7,15,28,0.96));
            border: 1px solid var(--brass-dark);
            border-radius: 6px;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex; flex-direction: column;
        }
        .shop-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.4);
        }
        .shop-card.owned { border-color: rgba(94,234,212,0.3); }
        .shop-card.active { border-color: var(--brass-light); box-shadow: 0 0 16px rgba(234,192,64,0.2); }

        /* Preview avatars */
        .pack-preview {
            display: flex; justify-content: center; align-items: center;
            gap: 6px; flex-wrap: wrap;
            padding: 16px 12px;
            background: rgba(0,0,0,0.2);
            min-height: 90px;
        }
        .pack-preview img {
            width: 48px; height: 48px;
            border-radius: 4px;
            border: 1px solid rgba(200,147,62,0.15);
            image-rendering: pixelated;
            transition: transform 0.15s;
        }
        .pack-preview img:hover { transform: scale(1.15); }
        .pack-preview-bateau {
            display: flex; justify-content: center; align-items: center;
            gap: 8px; flex-wrap: wrap;
            padding: 14px 10px;
            background: rgba(0,0,0,0.25);
            min-height: 80px;
        }
        .pack-preview-bateau img {
            height: 40px; width: auto;
            image-rendering: auto;
            transition: transform 0.15s;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5));
        }
        .pack-preview-bateau img:hover { transform: scale(1.2); }

        .pack-preview-fond {
            width: 100%;
            aspect-ratio: 1372 / 784;
            background-size: 100% 100%;
            background-position: center;
            background-repeat: no-repeat;
            border-bottom: 1px solid rgba(200,147,62,0.15);
        }

        /* Info */
        .card-info {
            padding: 14px 16px; flex: 1;
            display: flex; flex-direction: column;
        }
        .card-info h3 {
            margin: 0 0 4px; font-size: 0.95rem;
            color: var(--brass-light); letter-spacing: 0.08em;
        }
        .card-info .pack-count {
            font-size: 0.7rem; color: var(--rope);
            margin-bottom: 6px;
        }
        .card-info p {
            margin: 0;
            font-size: 0.75rem; color: #8090a0;
            line-height: 1.4; flex: 1;
        }

        /* Footer */
        .card-footer {
            display: flex; justify-content: space-between; align-items: center;
            padding: 10px 16px 14px;
        }
        .price-tag {
            font-size: 0.9rem; color: #ffd700; font-weight: bold;
            letter-spacing: 0.05em;
        }

        .shop-btn {
            font-family: inherit;
            padding: 6px 16px; border-radius: 3px;
            border: 1px solid; cursor: pointer;
            font-size: 0.78rem; letter-spacing: 0.08em;
            text-transform: uppercase;
            transition: all 0.2s;
        }
        .shop-btn-buy {
            background: rgba(255,215,0,0.1);
            border-color: #ffd700; color: #ffd700;
        }
        .shop-btn-buy:hover { background: rgba(255,215,0,0.2); }
        .shop-btn-buy:disabled { opacity: 0.4; cursor: not-allowed; }
        .shop-btn-equip {
            background: rgba(94,234,212,0.1);
            border-color: var(--accent); color: var(--accent);
        }
        .shop-btn-equip:hover { background: rgba(94,234,212,0.2); }
        .shop-btn-active {
            background: rgba(234,192,64,0.15);
            border-color: var(--brass-light); color: var(--brass-light);
            cursor: default;
        }
        .shop-btn-default {
            background: rgba(140,140,140,0.1);
            border-color: #888; color: #aaa;
        }
        .shop-btn-default:hover { background: rgba(140,140,140,0.2); color: #ccc; }

        .badge-owned {
            font-size: 0.65rem; color: #6ee7b7;
            text-transform: uppercase; letter-spacing: 0.1em;
        }
        .badge-active {
            font-size: 0.65rem; color: var(--brass-light);
            text-transform: uppercase; letter-spacing: 0.1em;
            font-weight: bold;
        }

        /* Coming soon */
        .coming-soon {
            text-align: center; padding: 40px 20px;
            color: var(--rope); font-size: 0.85rem;
            letter-spacing: 0.08em;
            border: 1px dashed var(--brass-dark);
            border-radius: 6px;
        }
    </style>
</head>
<body class="shop-bg">

    <div class="shop-header">
        <h1>Boutique</h1>
        <div class="shop-header-right">
            <div class="gold-display"><b id="gold-val"><?= $gold ?></b> Gold</div>
            <a href="index.php" class="btn-back">Retour</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="shop-flash <?= $messageType ?>"><?= $message ?></div>
    <?php endif; ?>

    <div class="shop-content">
        <?php foreach (['avatar', 'bateau', 'fond'] as $cat):
            $label = $categoryLabels[$cat] ?? $cat;
        ?>
            <h2 class="section-title"><?= $label ?></h2>

            <?php if (empty($byCategory[$cat])): ?>
                <div class="coming-soon">Bientot disponible</div>
            <?php else: ?>
                <div class="shop-grid">
                <?php foreach ($byCategory[$cat] as $theme):
                    $tid     = (int)$theme['id'];
                    $isOwned = in_array($tid, $owned);
                    $isActive = (($activeMap[$cat] ?? null) === $tid);
                    $canBuy  = (!$isOwned && $gold >= (int)$theme['price']);
                    $prefix  = $theme['image_prefix'];
                    $folder  = $theme['folder_name'];
                ?>
                    <div class="shop-card <?= $isOwned ? 'owned' : '' ?> <?= $isActive ? 'active' : '' ?>">
                        <?php if ($cat === 'fond'):
                            $bgPath = "assets/img/Fond/bg_{$prefix}.png";
                        ?>
                            <div class="pack-preview-fond" style="background-image:url('<?= $bgPath ?>')"></div>
                        <?php elseif ($cat === 'bateau'):
                            $shipFolder = $folder;
                            $shipSizes = ['2','3.1','3.2','4','5'];
                        ?>
                            <div class="pack-preview-bateau">
                                <?php foreach ($shipSizes as $sz):
                                    $shipFile = "assets/img/ship/{$shipFolder}/{$sz}_horizontal_{$prefix}.png";
                                    if (file_exists(__DIR__ . '/' . $shipFile)):
                                ?>
                                    <img src="<?= $shipFile ?>" alt="Bateau <?= $sz ?>">
                                <?php endif; endforeach; ?>
                            </div>
                        <?php else: ?>
                        <div class="pack-preview">
                            <?php if ($cat === 'avatar'):
                                for ($i = 1; $i <= 9; $i++):
                                    $imgPath = "assets/img/Avatar/{$i}{$prefix}.png";
                                    if (file_exists(__DIR__ . '/' . $imgPath)):
                            ?>
                                <img src="<?= $imgPath ?>" alt="<?= htmlspecialchars($theme['name']) ?> #<?= $i ?>">
                            <?php
                                    endif;
                                endfor;
                            endif; ?>
                        </div>
                        <?php endif; ?>

                        <div class="card-info">
                            <h3><?= htmlspecialchars($theme['name']) ?></h3>
                            <?php if ($cat === 'avatar'): ?>
                                <span class="pack-count">9 avatars</span>
                            <?php endif; ?>
                        </div>

                        <div class="card-footer">
                            <div>
                                <?php if ($isActive): ?>
                                    <span class="badge-active">Actif</span>
                                <?php elseif ($isOwned): ?>
                                    <span class="badge-owned">Possede</span>
                                <?php else: ?>
                                    <span class="price-tag"><?= number_format($theme['price'], 0, ',', ' ') ?> Gold</span>
                                <?php endif; ?>
                            </div>
                            <div style="display:flex;gap:6px;">
                                <?php if ($isActive): ?>
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="action" value="unequip">
                                        <input type="hidden" name="theme_id" value="<?= $tid ?>">
                                        <button type="submit" class="shop-btn shop-btn-default">Defaut</button>
                                    </form>
                                    <span class="shop-btn shop-btn-active">Equipe</span>
                                <?php elseif ($isOwned): ?>
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="action" value="equip">
                                        <input type="hidden" name="theme_id" value="<?= $tid ?>">
                                        <button type="submit" class="shop-btn shop-btn-equip">Equiper</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="action" value="buy">
                                        <input type="hidden" name="theme_id" value="<?= $tid ?>">
                                        <button type="submit" class="shop-btn shop-btn-buy" <?= $canBuy ? '' : 'disabled' ?>>Acheter</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Musique de fond -->
    <audio id="bg-music" src="assets/sound/boutique_sound.mp3" loop></audio>
    <script>
    (function(){
        const a=document.getElementById('bg-music');
        const dbVol=<?= $_userVol ?>;
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
