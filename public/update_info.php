<?php
/* Modification du profil (pseudo, email, mot de passe) */
require __DIR__."/../config/db.php";
require __DIR__."/../vendor/autoload.php";

use App\Service\FlashService;

session_start();

if (empty($_SESSION['uid'])) {
    FlashService::add('error', 'Veuillez vous connecter.');
    header("Location: login.php");
    exit;
}

// Récupérer infos utilisateur
$stmt = $pdo->prepare("SELECT * FROM users WHERE ID_Users = ?");
$stmt->execute([$_SESSION['uid']]);
$user = $stmt->fetch();

if (!$user) {
    FlashService::add('error', 'Utilisateur introuvable.');
    header("Location: login.php");
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pseudo = trim($_POST['pseudo'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    // Validation basique
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email invalide.";
    }
    if (strlen($pseudo) < 3) {
        $errors[] = "Pseudo trop court (min 3 caractères).";
    }
    if ($password && $password !== $password2) {
        $errors[] = "Les mots de passe ne correspondent pas.";
    }

    if (!$errors) {
        try {
            if ($password) {
                $hash = password_hash($password, PASSWORD_ARGON2ID);
                $stmt = $pdo->prepare("UPDATE users SET Email=?, Pseudo=?, Password=? WHERE ID_Users=?");
                $stmt->execute([$email, $pseudo, $hash, $_SESSION['uid']]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET Email=?, Pseudo=? WHERE ID_Users=?");
                $stmt->execute([$email, $pseudo, $_SESSION['uid']]);
            }

            FlashService::add('success', 'Informations mises à jour ✅');
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Cet email ou pseudo est déjà utilisé.";
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Modifier mes informations</title>
  <link rel="stylesheet" href="assets/css/style.css?v=2">
</head>
<body class="login-bg">
  <div class="login-wrapper">
    <div class="login-card">
      <h1 class="title">⚙️ Mise à jour</h1>

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
        <label>Pseudo</label>
        <input type="text" name="pseudo" required 
               value="<?= htmlspecialchars($_POST['pseudo'] ?? $user['Pseudo']) ?>">

        <label>Email</label>
        <input type="email" name="email" required 
               value="<?= htmlspecialchars($_POST['email'] ?? $user['Email']) ?>">

        <label>Nouveau mot de passe (laisser vide pour ne pas changer)</label>
        <input type="password" name="password">

        <label>Confirmer le mot de passe</label>
        <input type="password" name="password2">

        <button type="submit">Mettre à jour</button>
      </form>

      <p class="switch">
        <a href="index.php">← Retour à l'accueil</a>
      </p>
    </div>
  </div>
</body>
</html>
