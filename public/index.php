<?php

require __DIR__ . "/../vendor/autoload.php"; // composer autoloader -> every App\ class loads on demand

// classes I wire together below
use App\Database;
use App\Repositories\UserRepository;
use App\Controllers\AuthController;
use App\Repositories\TokenRepository;
use App\Auth;
use App\Repositories\ConversationRepository;
use App\Controllers\ConversationController;
use App\Repositories\MessageRepository;
use App\Controllers\MessageController;
use App\FileStorage;
// helper (lives in the HTTP layer, not in Auth): returns the logged-in user id, or sends 401 + STOPS the request.
// exit() (not return) so if this line passes, the code after it KNOWS you're authenticated.
function requireAuth(TokenRepository $tokens): int
{
    $userId = Auth::userId($tokens);
    if ($userId === null) {
        http_response_code(401);
        header("Content-Type: application/json");
        echo json_encode(["error" => "unauthenticated"]);
        exit();
    }
    return $userId;
}

// --- connect to the db. Database::connect() just throws; the HTTP response is decided here ---
try {
    $pdo = new Database()->connect();
} catch (\PDOException $e) {
    error_log($e->getMessage());
    http_response_code(503); // service unavailable
    header("Content-Type: application/json");
    echo json_encode(["error" => "database unavailable"]);
    exit(); // no db = app can do nothing
}

// --- composition root: build everything once, inject dependencies (repo needs pdo, controller needs repo) ---
$tokenRepository = new TokenRepository($pdo);
$userRepository = new UserRepository($pdo);
$authController = new AuthController($userRepository, $tokenRepository);
$conversationRepository = new ConversationRepository($pdo);
$conversationController = new ConversationController($conversationRepository);
$messageRepository = new MessageRepository($pdo);
$fileStorage = new FileStorage(__DIR__ . "/uploads");
$messageController = new MessageController($messageRepository, $conversationRepository, $fileStorage);

// --- routing ---
$method = $_SERVER["REQUEST_METHOD"];
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH); // path without the ?query

if ($method === "POST" && $path === "/register") {
    $authController->register();
    return;
} elseif ($method === "POST" && $path === "/login") {
    $authController->login();
    return;
} elseif ($method === "GET" && $path === "/me") {
    $userId = requireAuth($tokenRepository); // if this returns, we're authenticated
    header("Content-Type: application/json");
    echo json_encode(["user_id" => $userId]);
    return;
} elseif ($method === "POST" && $path === "/conversations") {
    $userId = requireAuth($tokenRepository);
    $conversationController->create($userId);
    return;
} elseif ($method === "GET" && $path === "/conversations") {
    $userId = requireAuth($tokenRepository);
    $conversationController->index($userId);
    return;
} elseif ($method === "POST" && preg_match("#^/conversations/(\d+)/messages$#", $path, $matches)) {
    $userId = requireAuth($tokenRepository);
    $conversationId = (int) $matches[1];
    $messageController->store($userId, $conversationId);
    return;
} elseif ($method === "GET" && preg_match("#^/conversations/(\d+)/messages$#", $path, $matches)) {
    $userId = requireAuth($tokenRepository);
    $conversationId = (int) $matches[1];
    $messageController->index($userId, $conversationId);
    return;
} elseif ($method === "PATCH" && preg_match("#^/conversations/(\d+)/read$#", $path, $matches)) {
    $userId = requireAuth($tokenRepository);
    $conversationId = (int) $matches[1];
    $conversationController->markRead($userId, $conversationId);
    return;
} elseif ($method === "POST" && preg_match("#^/conversations/(\d+)/messages/image$#", $path, $matches)) {
    $userId = requireAuth($tokenRepository);
    $conversationId = (int) $matches[1];
    $messageController->uploadImage($userId, $conversationId);
    return;
} else {
    http_response_code(404);
    header("Content-Type: application/json");
    echo json_encode(["error" => "not found"]);
    return;
}
