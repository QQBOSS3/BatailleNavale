<?php
namespace App\Entity;

// Entité Avatar - image de profil stockée en binaire dans la BDD
class Avatar {
    public function __construct(
        private int $id,
        private string $name,
        private string $mimeType,
        private string $data // binaire
    ) {}

    public function getId(): int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getMimeType(): string { return $this->mimeType; }
    public function getData(): string { return $this->data; }
}
