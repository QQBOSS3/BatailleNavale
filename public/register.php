<?php
/* Inscription - validation RGPD du mot de passe + choix d'avatar */
require __DIR__ . "/../config/db.php";
require __DIR__ . "/../vendor/autoload.php";

use App\Repository\UserRepository;
use App\Repository\AvatarRepository;
use App\Service\FlashService;

session_start();
$userRepo = new UserRepository($pdo);
$avatarRepo = new AvatarRepository($pdo);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $pseudo = trim($_POST['pseudo'] ?? '');
  $password = $_POST['password'] ?? '';
  $birthDay = $_POST['birthDay'] ?? null;
  $avatarId = isset($_POST['Avatar']) ? (int)$_POST['Avatar'] : 0;

  // Validation Email
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Email invalide";
  }
  
  // Validation Pseudo
  if (strlen($pseudo) < 3) {
    $errors[] = "Pseudo trop court";
  }

  // --- SÉCURITÉ MOT DE PASSE (RGPD / CNIL) ---
  // Règle : 12 caractères, 1 majuscule, 1 minuscule, 1 chiffre, 1 caractère spécial
  if (strlen($password) < 12) {
      $errors[] = "Le mot de passe doit faire au moins 12 caractères.";
  }
  if (!preg_match('/[A-Z]/', $password)) {
      $errors[] = "Le mot de passe doit contenir au moins une majuscule.";
  }
  if (!preg_match('/[a-z]/', $password)) {
      $errors[] = "Le mot de passe doit contenir au moins une minuscule.";
  }
  if (!preg_match('/[0-9]/', $password)) {
      $errors[] = "Le mot de passe doit contenir au moins un chiffre.";
  }
  if (!preg_match('/[\W_]/', $password)) { // \W cherche tout ce qui n'est pas alphanumérique
      $errors[] = "Le mot de passe doit contenir au moins un caractère spécial (! @ # ...).";
  }

  // Validation Date
  if (!$birthDay) {
    $errors[] = "Veuillez entrer votre date de naissance.";
  }
  
  // Validation Avatar
  if ($avatarId === 0) {
    $errors[] = "Veuillez sélectionner un avatar.";
  }

  // Vérification de l'email existant
  if (!$errors) {
      if ($userRepo->findByEmail($email)) {
          $errors[] = "Cet email est déjà utilisé par un autre capitaine !";
      }
  }

  if (!$errors) {
    try {
      // ⚠️ IMPORTANT : UserRepository doit hacher le mot de passe (password_hash)
      // On ne stocke JAMAIS le mot de passe en clair.
      $user = $userRepo->create($email, $pseudo, $password, $birthDay);
      
      $stmt = $pdo->prepare("UPDATE users SET Avatar=? WHERE ID_Users=?");
      $stmt->execute([$avatarId, $user->getId()]);

      $_SESSION['uid'] = $user->getId();
      FlashService::add('success', 'Compte créé avec succès ! Bienvenue 🎉');
      header("Location: index.php");
      exit;
    } catch (PDOException $e) {
      $errors[] = "Erreur SQL : " . $e->getMessage();
    }
  }
}

$avatars = $avatarRepo->findAll();
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Inscription — Bataille Navale</title>
  <link rel="stylesheet" href="assets/css/style.css?v=2">
</head>
<body class="register-bg">
  <div class="login-wrapper">
    <div class="login-card">
      <h1 class="title">⚓ Créer un compte</h1>

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

      <?php if ($errors): ?>
        <div class="flashes">
          <?php foreach ($errors as $e): ?>
            <div class="flash error"><?= htmlspecialchars($e) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" class="login-form">
        <label>Email</label>
        <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

        <label>Pseudo</label>
        <input type="text" name="pseudo" required value="<?= htmlspecialchars($_POST['pseudo'] ?? '') ?>">

        <label>Mot de passe</label>
        <span style="font-size: 10px; color: #ccc; margin-bottom: 5px;">Min. 12 chars, 1 Maj, 1 min, 1 chiffre, 1 special</span>
        <div style="position:relative;display:flex;align-items:center;">
          <input type="password" name="password" id="pwd" required style="width:100%;box-sizing:border-box;padding-right:38px!important;">
          <span onclick="let p=document.getElementById('pwd');if(p.type==='password'){p.type='text';this.style.opacity='0.5';}else{p.type='password';this.style.opacity='1';}"
                style="position:absolute;right:8px;cursor:pointer;font-size:15px;user-select:none;opacity:1;line-height:1;color:#B87333">👁</span>
        </div>
        
        <div class="form-group" style="margin-top: 10px;">
          <label for="birthDay">📅 Date de naissance</label>
          <input type="date" id="birthDay" name="birthDay" required value="<?= htmlspecialchars($_POST['birthDay'] ?? '') ?>">
        </div>

        <p style="margin-top: 15px;">Choisis un avatar :</p>
        <div class="avatar-grid">
          <?php foreach ($avatars as $av): ?>
            <label>
              <input type="radio" name="Avatar" value="<?= $av->getId() ?>"
                <?= (isset($_POST['Avatar']) && (int)$_POST['Avatar'] === $av->getId()) ? 'checked' : '' ?>>
              <img src="get_avatar.php?id=<?= $av->getId() ?>"
                alt="<?= htmlspecialchars($av->getName()) ?>"
                class="avatar">
            </label>
          <?php endforeach; ?>
        </div>

        <button type="submit">S'inscrire</button>
      </form>

      <p class="switch">
        Déjà inscrit ? <a href="login.php">Connexion</a>
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