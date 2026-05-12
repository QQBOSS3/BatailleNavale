<?php
namespace App\Entity;

// Entité User - représente un joueur avec son profil et sa progression
class User {
    public function __construct(
        private int $id,
        private string $email,
        private string $pseudo,
        private string $password,
        private ?int $avatarId,
        private int $niveau,
        private int $xp,
        private int $gold,
        private bool $online
    ) {}

    public function getId(): int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function getPseudo(): string { return $this->pseudo; }
    public function getPassword(): string { return $this->password; }
    public function getAvatarId(): ?int { return $this->avatarId; }
    public function getNiveau(): int { return $this->niveau; }
    public function getXp(): int { return $this->xp; }
    public function getGold(): int { return $this->gold; }
    public function isOnline(): bool { return $this->online; }
}
