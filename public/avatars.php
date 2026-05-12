<?php
/* Galerie d'avatars disponibles */
require __DIR__."/../config/db.php";
require __DIR__."/../vendor/autoload.php";

use App\Repository\AvatarRepository;

$repo = new AvatarRepository($pdo);
$avatars = $repo->findAll();
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Galerie Avatars</title>
  <link rel="stylesheet" href="assets/css/style.css?v=2">
</head>
<body class="bg">
  <main class="card">
    <h1>Galerie des avatars</h1>
    <div class="avatar-grid">
      <?php foreach($avatars as $av): ?>
        <img src="get_avatar.php?id=<?= $av->getId() ?>" alt="<?= htmlspecialchars($av->getName()) ?>" class="avatar large">
      <?php endforeach; ?>
    </div>
  </main>
</body>
</html>
