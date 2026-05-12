<?php
/* Sert l'image binaire d'un avatar (pour les balises <img src="get_avatar.php?id=X">) */
require __DIR__."/../config/db.php";
require __DIR__."/../vendor/autoload.php";

use App\Repository\AvatarRepository;

$id = (int)($_GET['id'] ?? 0);
$repo = new AvatarRepository($pdo);
$avatar = $repo->findById($id);

if ($avatar) {
    header("Content-Type: ".$avatar->getMimeType());
    echo $avatar->getData();
} else {
    http_response_code(404);
}
