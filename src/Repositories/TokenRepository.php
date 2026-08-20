<?php

namespace App\Repositories;

use PDO;

class TokenRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(int $userId, string $token, string $expires_at): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO tokens (user_id,token,expires_at) VALUES (?,?,?)");
        $stmt->execute([$userId, $token, $expires_at]);
    }

    public function findValidToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tokens WHERE token = ? AND expires_at > NOW()");
        $stmt->execute([$token]);

        $row = $stmt->fetch();

        return $row ?: null;
    }
}
