<?php

namespace App\Repositories;

use PDO;

class UserRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(string $username, string $phone, string $passwordHash, ?string $email): int
    {
        //prepare insert
        $stmt = $this->pdo->prepare("INSERT INTO users (username,phone,password_hash,email) VALUES (?,?,?,?)");
        $stmt->execute([$username, $phone, $passwordHash, $email]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findByPhone(string $phone): ?array
    {
        $stmt = $this->pdo->prepare("SELECT *  FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
