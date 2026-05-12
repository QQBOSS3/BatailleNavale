<?php
/* Création d'une partie - mappe les params front (mode/type/locale/size) vers la BDD
   puis redirige vers le lobby */
require __DIR__ . "/../config/db.php";
session_start();

if (empty($_SESSION['uid'])) {
    header("Location: login.php");
    exit;
}

// Params envoyés par le formulaire de création
$frontMode  = $_GET['mode'] ?? null;
$frontType  = $_GET['type'] ?? null;
$locale     = $_GET['locale'] ?? 'fr';
$frontSize  = isset($_GET['size']) ? (int)$_GET['size'] : 10;

if ($locale === 'be') {
    $version = 2;
} else {
    $version = 1;
}

if (!$frontMode || !$frontType) {
    exit("Paramètres invalides.");
}

if ($frontSize < 5 || $frontSize > 25) {
    $frontSize = 10;
}

// Mapping entre les noms front et les noms en BDD
$modeMap = [
    'public'  => 'Public',
    'private' => 'Private'
];

$typeMap = [
    '1vs1' => 'Solo',
    '2vs2' => 'Team',
    '3vs3' => 'Team',
    '4vs4' => 'Team',
    'br'   => 'BattleRoyal'
];

$modeName = $modeMap[strtolower($frontType)] ?? null;
$typeName = $typeMap[strtolower($frontMode)] ?? null;

if (!$modeName || !$typeName) {
    exit("Erreur: mapping invalide (mode ou type non reconnu).");
}

$stmt = $pdo->prepare("SELECT id_Mode FROM mode WHERE name=?");
$stmt->execute([$modeName]);
$id_game_mode = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT id_Type FROM type WHERE name=?");
$stmt->execute([$typeName]);
$id_game_type = $stmt->fetchColumn();

$id_team_mode = null;
if ($typeName === "Team" && preg_match('/^(\d)vs\d$/', $frontMode, $matches)) {
    $size = (int)$matches[1];
    $stmt = $pdo->prepare("SELECT id_Team FROM team_mode WHERE team_size=?");
    $stmt->execute([$size]);
    $id_team_mode = $stmt->fetchColumn();
}

if (!$id_game_mode || !$id_game_type) {
    exit("Erreur: mode ou type introuvable en BDD.");
}

$stmt = $pdo->prepare("
    INSERT INTO games (id_game_mode, id_game_type, id_team_mode, id_version, status, id_creator, taille_grille) 
    VALUES (?, ?, ?, ?, 'preparation', ?, ?)
");
$stmt->execute([$id_game_mode, $id_game_type, $id_team_mode, $version, $_SESSION['uid'], $frontSize]);

$gameId = $pdo->lastInsertId();

// Le créateur rejoint sa propre partie (équipe 1 si mode team, null si BR)
$creatorTeam = ($id_game_type == 1) ? null : 1;

$stmt = $pdo->prepare("
    INSERT INTO game_players (id_game, id_player, team_number, player_status)
    VALUES (?, ?, ?, 'in_game')
");
$stmt->execute([$gameId, $_SESSION['uid'], $creatorTeam]);

header("Location: game.php?id=" . $gameId);
exit;
