<?php

namespace App\Controllers;

use App\Repositories\UserRepository;
use App\Repositories\TokenRepository;

class AuthController
{
    private UserRepository $users;
    private TokenRepository $tokens;

    public function __construct(UserRepository $users, TokenRepository $tokens)
    {
        $this->users = $users;
        $this->tokens = $tokens;
    }

    private function json(int $status, array $data): void
    {
        http_response_code($status);
        header("Content-Type: application/json");
        echo json_encode($data);
    }

    public function login(): void
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!is_array($data)) {
            $this->json(400, ["error" => "invalid JSON body"]);
            return;
        }

        $phone = $data["phone"] ?? null;
        $password = $data["password"] ?? null;

        if (empty($phone) || empty($password)) {
            $this->json(400, ["error" => "phone/password missing or too short"]);
            return;
        }

        $user = $this->users->findByPhone($phone);

        if (!$user || !password_verify($password, $user["password_hash"])) {
            $this->json(401, ["error" => "invalid credentials"]);
            return;
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = new \DateTime("+30 days")->format("Y-m-d H:i:s");
        $this->tokens->create($user["id"], $token, $expiresAt);

        $this->json(200, [
            "success" => true,
            "token" => $token,
            "user" => [
                "id" => $user["id"],
                "username" => $user["username"],
                "phone" => $user["phone"],
            ],
        ]);
    }

    public function register(): void
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!is_array($data)) {
            $this->json(400, ["error" => "invalid JSON body"]);
            return;
        }
        $username = $data["username"] ?? null;
        $phone = $data["phone"] ?? null;
        $password = $data["password"] ?? null;
        $email = $data["email"] ?? null;

        if (empty($username) || empty($phone) || empty($password) || strlen($password) < 8) {
            $this->json(400, ["error" => "username/phone/password missing, or password too short"]);
            return;
        }

        $pwdHash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $id = $this->users->create($username, $phone, $pwdHash, $email);
        } catch (\PDOException $e) {
            error_log($e->getMessage()); // YOU see the detail in logs
            if ($e->getCode() === "23000") {
                // integrity constraint violation = duplicate
                $this->json(409, ["error" => "phone or username already taken"]);
            } else {
                $this->json(500, ["error" => "server error"]);
            }
            return;
        }

        $this->json(201, ["success" => true, "id" => $id]);
    }
}
