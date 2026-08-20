<?php

require __DIR__ . "/vendor/autoload.php";

use App\Database;
use App\Repositories\UserRepository;

$pdo = new Database()->connect();
$users = new UserRepository($pdo);

$created = 0;
for ($i = 1; $i <= 50; $i++) {
    $username = "user{$i}";
    $phone = "+99555" . str_pad((string) $i, 7, "0", STR_PAD_LEFT); // unique, fits VARCHAR(20)
    $email = "user{$i}@example.com";
    $hash = password_hash("supersecret", PASSWORD_DEFAULT); // all share one test password

    try {
        $id = $users->create($username, $phone, $hash, $email);
        echo "Created #{$id}: {$username} / {$phone}\n";
        $created++;
    } catch (\PDOException $e) {
        echo "Skipped {$username} (probably already exists)\n";
    }
}

echo "Done. {$created} users created. Password for all: supersecret\n";
