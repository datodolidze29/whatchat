<?php

namespace App;

use PDO;

class Database
{
    public function connect(): PDO
    {
        $config = require __DIR__ . "/../config/config.php";

        $dsn = "mysql:host={$config["host"]};dbname={$config["db"]};charset={$config["charset"]}";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        return new PDO($dsn, $config["user"], $config["pass"], $options);
    }
}
