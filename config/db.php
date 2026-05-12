<?php
// On garde le fuseau horaire de PHP pour l'affichage éventuel, mais ça n'impacte plus le moteur du jeu
date_default_timezone_set('Europe/Paris');

$dsn = "mysql:host=******;dbname=BatailleNavale;charset=utf8mb4";
$user = "*****";
$pass = "***********"; 

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => true,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // Plus besoin du "SET time_zone" complexe !
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>