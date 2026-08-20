<?php

require __DIR__ . "/../vendor/autoload.php";

use App\Database;
use App\Repositories\UserRepository;
use App\Controllers\AuthController;
use App\Repositories\TokenRepository;
use App\Auth;

try {
    $pdo = new Database()->connect();
} catch (\PDOException $e) {
    error_log($e->getMessage());
    http_response_code(503);
    header("Content-Type: application/json");
    echo json_encode(["error" => "database unavailable"]);
    exit();
}

$tokenRepository = new TokenRepository($pdo);
$userRepository = new UserRepository($pdo);
$authController = new AuthController($userRepository, $tokenRepository);

$method = $_SERVER["REQUEST_METHOD"];
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

if ($method === "POST" && $path === "/register") {
    $authController->register();
} elseif ($method === "POST" && $path === "/login") {
    $authController->login();
} elseif ($method === "GET" && $path === "/me") {
    $userId = Auth::userId($tokenRepository);
    if ($userId === null) {
        http_response_code(401);
        header("Content-Type: application/json");
        echo json_encode(["error" => "unauthenticated"]);
        return;
    }
    header("Content-Type: application/json");
    echo json_encode(["user_id" => $userId]);
    return;
} else {
    http_response_code(404);
    echo json_encode(["error" => "not found"]);
    return;
}
