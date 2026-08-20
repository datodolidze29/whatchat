<?php

namespace App\Repositories;

use PDO;

// repository = all the SQL for the "users" table lives here. no HTTP, just data in/out
class UserRepository
{
    private PDO $pdo;

    // constructor injection: I don't create my own connection, I get handed one (easier to test + one place owns the db)
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // insert a new user and return the new auto-increment id. gets the password ALREADY hashed (hashing is the controller's job)
    public function create(string $username, string $phone, string $passwordHash, ?string $email): int
    {
        //prepare insert  (? placeholders = safe from SQL injection, values go through a separate channel)
        $stmt = $this->pdo->prepare("INSERT INTO users (username,phone,password_hash,email) VALUES (?,?,?,?)");
        $stmt->execute([$username, $phone, $passwordHash, $email]); // 4 columns = 4 ? = 4 values, same order

        return (int) $this->pdo->lastInsertId(); // db generated the id during insert, this reads it back. cast to int
    }

    // look a user up by phone. returns the row, or null if nobody matches (null is normal, not an error)
    public function findByPhone(string $phone): ?array
    {
        $stmt = $this->pdo->prepare("SELECT *  FROM users WHERE phone = ?");
        $stmt->execute([$phone]); // execute always takes an ARRAY, even for one value
        $row = $stmt->fetch();
        return $row ?: null; // fetch() gives false when no row -> use ?: (not ??) because ?? only catches null
    }
}
