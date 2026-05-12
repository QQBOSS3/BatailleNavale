<?php
namespace App\Service;

use App\Repository\UserRepository;

// Service d'authentification - login + contrôle de session
class AuthService {
    public function __construct(private UserRepository $userRepo) {}

    // Vérifie email/mdp et ouvre la session si OK
    public function login(string $email, string $password): bool {
        $user = $this->userRepo->findByEmail($email);
        if ($user && password_verify($password, $user->getPassword())) {
            session_regenerate_id(true); // contre le session fixation
            $_SESSION['uid'] = $user->getId();
            return true;
        }
        return false;
    }

    // Redirige vers login si pas connecté
    public function requireLogin(): void {
        if (empty($_SESSION['uid'])) {
            header("Location: login.php");
            exit;
        }
    }
}
