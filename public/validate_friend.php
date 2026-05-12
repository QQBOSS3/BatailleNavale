<?php
/* Accepter ou refuser une demande d'ami */
require __DIR__."/../vendor/autoload.php";
require __DIR__."/../config/db.php";
session_start();

use App\Repository\FriendRepository;

$id = (int)($_POST['id_friends'] ?? 0);
$action = $_POST['action'] ?? '';

if ($id <= 0 || !in_array($action, ['accept','reject'])) {
    exit("Requête invalide.");
}

$friendRepo = new FriendRepository($pdo);
$row = $friendRepo->findRequestById($id);

if (!$row || $row['Receiver_ID'] != $_SESSION['uid']) {
    exit("Non autorisé.");
}

$status = ($action === 'accept') ? 'Accepted' : 'Rejected';
$friendRepo->updateStatus($id, $status);

header("Location: index.php");
exit;
