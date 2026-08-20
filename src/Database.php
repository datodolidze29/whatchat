<?php

namespace App; // this file lives in the App namespace -> matches "App\\": "src/" in composer.json

use PDO; // pulling in PHP's built-in database class so I can just write PDO instead of \PDO

class Database
{
    // one job: build a PDO connection and hand it back. no HTTP stuff in here (separation of concerns)
    public function connect(): PDO
    {
        $config = require __DIR__ . "/../config/config.php"; // load the secret credentials array (this file is gitignored)

        // DSN = "data source name", tells PDO where the db is + which charset. utf8mb4 so emojis work
        $dsn = "mysql:host={$config["host"]};dbname={$config["db"]};charset={$config["charset"]}";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,        // throw exceptions on errors instead of failing silently
            PDO::ATTR_EMULATE_PREPARES => false,               // use REAL prepared statements from mysql, not fake php ones
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,  // rows come back as clean ["id" => 1] arrays
        ];

        // just return it. if it fails it throws, and whoever called me (index.php) decides what to do
        return new PDO($dsn, $config["user"], $config["pass"], $options);
    }
}
