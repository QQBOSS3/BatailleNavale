<?php
/* Page d'attente affichée après validation du placement
   Poll check_ready.php toutes les 2s et redirige quand tout le monde est prêt */
require __DIR__ . "/../config/db.php";
session_start();

if (empty($_SESSION['uid'])) {
  header("Location: login.php");
  exit;
}

$gameId = (int)($_GET['id'] ?? 0);

// Vérifier que la partie existe
$stmt = $pdo->prepare("SELECT * FROM games WHERE id_Game=?");
$stmt->execute([$gameId]);
$game = $stmt->fetch();

if (!$game) {
  exit("Partie introuvable.");
}
?>
<!doctype html>
<html lang="fr">

<head>
  <meta charset="utf-8">
  <title>En attente...</title>
  <link rel="stylesheet" href="assets/css/style.css?v=2">
  <style>
    body {
      text-align: center;
      padding: 50px;
      color: white;
    }

    .waiting {
      font-size: 1.4rem;
      margin: 20px;
    }
  </style>
</head>

<body class="home-bg">
  <h1>⚓ Partie #<?= htmlspecialchars($gameId) ?></h1>
  <p class="waiting">⏳ En attente que tous les joueurs placent leur flotte...</p>
  <div id="status"></div>

  <script>
  const gameId = <?= $gameId ?>;

  // ✅ Fix 2 : la fonction est déclarée en premier
  const intervalId = setInterval(checkReady, 2000);

  function checkReady() {
    fetch("check_ready.php?id=" + gameId)
      .then(r => r.json())
      .then(data => {
        console.log("Status:", data);
        if (data.ready === true) {
          clearInterval(intervalId); // ✅ On arrête le polling
          document.getElementById("status").innerText = "🚀 Lancement du combat !";
          document.getElementById("status").style.color = "#00ff00";
          setTimeout(() => {
            window.location.href = "play.php?id=" + gameId;
          }, 1000);
        } else {
          document.getElementById("status").innerText =
            (data.readyCount || 0) + " / " + (data.total || "?") + " joueurs prêts";
        }
      })
      .catch(err => console.error("Erreur check:", err));
  }

  // Vérifie tout de suite au chargement
  checkReady();
</script>
</body>

</html>