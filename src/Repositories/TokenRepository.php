<?php

namespace App\Repositories;

use PDO;

// all the SQL for the "tokens" table. tokens are how I remember who is logged in (instead of sessions)
class TokenRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // store a token row. I receive the already-generated token + expiry (generating them is the controller's job).
    // void = I don't need to return the id here
    public function create(int $userId, string $token, string $expires_at): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO tokens (user_id,token,expires_at) VALUES (?,?,?)");
        $stmt->execute([$userId, $token, $expires_at]);
    }

    // find a token that exists AND hasn't expired yet. the "expires_at > NOW()" does the expiry check in SQL
    // so an expired token just returns nothing = treated as invalid automatically
    public function findValidToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tokens WHERE token = ? AND expires_at > NOW()");
        $stmt->execute([$token]);

        $row = $stmt->fetch();

        return $row ?: null; // no match -> null
    }
}
