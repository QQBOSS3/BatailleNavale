<?php
/* Page de connexion - formulaire email/mdp avec style naval */
require __DIR__ . "/../config/db.php";
require __DIR__ . "/../vendor/autoload.php";

use App\Repository\UserRepository;
use App\Service\AuthService;
use App\Service\FlashService;

session_start();

$userRepo = new UserRepository($pdo);
$auth = new AuthService($userRepo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($auth->login($email, $password)) {
        // Marquer le joueur en ligne
        if (!empty($_SESSION['uid'])) {
            $stmt = $pdo->prepare("UPDATE users SET Online = 1, last_activity = ? WHERE ID_Users = ?");
            $stmt->execute([time(), $_SESSION['uid']]);
        }
        FlashService::add('success', 'Connexion réussie 👋');
        header("Location: index.php");
        exit;
    } else {
        FlashService::add('error', 'Email ou mot de passe incorrect.');
        header("Location: login.php");
        exit;
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Connexion — Bataille Navale</title>
  <link rel="stylesheet" href="assets/css/style.css?v=2">

  <style>

    /* FOND + CENTRAGE — autonome, ne dépend pas de style.css */
    body.login-bg {
      margin: 0 !important;
      min-height: 100vh !important;
      background: url("assets/img/bg-login.png") no-repeat center center !important;
      background-size: cover !important;
      font-family: "PixelFont", monospace !important;
      display: flex !important;
      justify-content: center !important;
      align-items: center !important;
    }

    /* WRAPPER CENTRÉ */
    body.login-bg .login-wrapper {
      width: 100% !important;
      max-width: 440px !important;
      padding: 20px !important;
    }

    /* PANNEAU CUIVRÉ — identique à register */
    body.login-bg .login-card {
      position: relative !important;
      background: #B87333 !important;
      border: 4px solid #4a2b0f !important;
      border-radius: 16px !important;
      box-shadow:
        0 0 18px rgba(0,0,0,0.9),
        0 0 0 4px rgba(0,0,0,0.5) !important;
      padding: 26px 24px 22px !important;
      text-align: center !important;
      overflow: hidden !important;
      font-family: "PixelFont", monospace !important;
    }

    /* Grille sonar en fond du panneau */
    body.login-bg .login-card::before {
      content: "" !important;
      position: absolute !important;
      inset: 0 !important;
      background-image:
        linear-gradient(rgba(0,0,0,0.08) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0,0,0,0.08) 1px, transparent 1px) !important;
      background-size: 18px 18px !important;
      pointer-events: none !important;
    }

    /* BANDEAU COCKPIT */
    body.login-bg .panel-header {
      position: relative !important;
      z-index: 1 !important;
      display: flex !important;
      align-items: center !important;
      gap: 6px !important;
      margin-bottom: 12px !important;
    }
    .panel-light {
      width: 10px !important;
      height: 10px !important;
      border-radius: 999px !important;
      display: inline-block !important;
    }
    .panel-light--green  { background: #22c55e !important; box-shadow: 0 0 8px rgba(34,197,94,0.9) !important; }
    .panel-light--orange { background: #f97316 !important; box-shadow: 0 0 8px rgba(249,115,22,0.9) !important; }
    .panel-light--red    { background: #ef4444 !important; box-shadow: 0 0 8px rgba(239,68,68,0.9) !important; }

    body.login-bg .panel-title {
      margin-left: auto !important;
      font-size: 10px !important;
      letter-spacing: 0.14em !important;
      text-transform: uppercase !important;
      color: rgba(0,0,0,0.65) !important;
    }

    /* TITRE */
    body.login-bg .title {
      position: relative !important;
      z-index: 1 !important;
      font-size: 20px !important;
      margin: 4px 0 6px !important;
      letter-spacing: 0.2em !important;
      text-transform: uppercase !important;
      color: #5eead4 !important;    /* cyan comme sur register */
    }

    /* SOUS-TITRE */
    body.login-bg .subtitle {
      position: relative !important;
      z-index: 1 !important;
      font-size: 11px !important;
      color: rgba(0,0,0,0.75) !important;
      margin-bottom: 18px !important;
    }

    /* FORMULAIRE */
    body.login-bg .login-form {
      position: relative !important;
      z-index: 1 !important;
      display: flex !important;
      flex-direction: column !important;
      gap: 12px !important;
      text-align: left !important;
    }
    body.login-bg .login-form label {
      font-size: 11px !important;
      text-transform: uppercase !important;
      letter-spacing: 0.08em !important;
      color: rgba(0,0,0,0.85) !important;
      margin-bottom: -4px !important;
    }
    body.login-bg .login-form input {
      padding: 10px 11px !important;
      border-radius: 6px !important;
      border: 2px solid #32386e !important;
      background: #11152d !important;
      color: #e5e9f0 !important;
      font-family: "PixelFont", monospace !important;
      font-size: 13px !important;
    }
    body.login-bg .login-form input::placeholder {
      color: rgba(229,233,240,0.55) !important;
    }
    body.login-bg .login-form input:focus {
      border-color: #5eead4 !important;
      outline: none !important;
      box-shadow: 0 0 0 2px rgba(94,234,212,0.3) !important;
    }

    /* BOUTON */
    body.login-bg .login-form button {
      position: relative !important;
      z-index: 1 !important;
      margin-top: 14px !important;
      padding: 14px !important;
      width: 100% !important;
      font-size: 14px !important;
      text-transform: uppercase !important;
      letter-spacing: 0.16em !important;
      font-family: "PixelFont", monospace !important;
      background: linear-gradient(180deg, #5eead4, #14b8a6) !important;
      color: #020617 !important;
      border: 3px solid #32386e !important;
      border-radius: 10px !important;
      box-shadow: 0 6px 0 #0b1120 !important;
      cursor: pointer !important;
      transition: transform .12s ease, box-shadow .12s ease, filter .12s ease;
    }
    body.login-bg .login-form button:hover {
      filter: brightness(1.06) !important;
      transform: translateY(-1px) !important;
      box-shadow: 0 7px 0 #020617 !important;
    }
    body.login-bg .login-form button:active {
      transform: translateY(2px) !important;
      box-shadow: 0 3px 0 #020617 !important;
    }

    /* LIEN BAS */
    body.login-bg .switch {
      position: relative !important;
      z-index: 1 !important;
      margin-top: 14px !important;
      font-size: 11px !important;
      color: rgba(0,0,0,0.8) !important;
    }
    body.login-bg .switch a {
      color: #0f172a !important;
      text-decoration: underline !important;
    }
    body.login-bg .switch a:hover {
      color: #5eead4 !important;
    }

    /* FLASHES */
    body.login-bg .flashes  { position: relative !important; z-index: 1 !important; margin-bottom: 10px !important; }
    body.login-bg .flash    { font-size: 11px !important; padding: 8px 10px !important; border-radius: 6px !important; margin-bottom: 6px !important; }
    body.login-bg .flash.success { background: #065f46 !important; color: #d1fae5 !important; }
    body.login-bg .flash.error   { background: #7f1d1d !important; color: #fee2e2 !important; }

    @media (max-width: 480px) {
      body.login-bg .login-card {
        padding: 22px 18px 18px !important;
      }
    }
  </style>
</head>

<body class="login-bg">
  <div class="login-wrapper">
    <div class="login-card">

      <div class="panel-header">
        <span class="panel-light panel-light--green"></span>
        <span class="panel-light panel-light--orange"></span>
        <span class="panel-light panel-light--red"></span>
        <span class="panel-title">ACCÈS AU POSTE DE COMMANDE</span>
      </div>

      <h1 class="title">⚓ Bataille Navale</h1>
      <p class="subtitle">Identifie-toi pour rejoindre le pont d'envol.</p>

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

      <form method="post" class="login-form">
        <label>Email du capitaine</label>
        <input type="email" name="email" required
               placeholder="ex : amiral@nations.unies">

        <label>Code secret</label>
        <div style="position:relative;display:flex;align-items:center;">
          <input type="password" name="password" id="pwd" required
                 placeholder="••••••••••••" style="width:100%;box-sizing:border-box;padding-right:38px!important;">
          <span onclick="let p=document.getElementById('pwd');if(p.type==='password'){p.type='text';this.style.opacity='0.5';}else{p.type='password';this.style.opacity='1';}"
                style="position:absolute;right:8px;cursor:pointer;font-size:15px;user-select:none;opacity:1;line-height:1;color:#B87333">👁</span>
        </div>

        <button type="submit">🚢 Se connecter</button>
      </form>

      <p class="switch">
        Nouveau dans la flotte ? <a href="register.php">S'inscrire</a>
      </p>

    </div>
  </div>

  <!-- Musique de fond -->
  <audio id="bg-music" src="assets/sound/connexion_register_sound.mp3" loop></audio>
  <script>
  (function(){
      const a=document.getElementById('bg-music');
      a.volume=0.5;
      if(localStorage.getItem('bn_music_muted')==='1') a.muted=true;
      function tryPlay(){a.play().catch(()=>{});}
      tryPlay();
      document.addEventListener('click',function once(){tryPlay();document.removeEventListener('click',once);},{once:true});
  })();
  </script>
</body>
</html>