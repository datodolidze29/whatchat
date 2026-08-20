<?php

namespace App\Controllers;

use App\Repositories\UserRepository;
use App\Repositories\TokenRepository;

// handles the HTTP side of auth: read input, validate, hash/verify, respond. talks to repos, never to the db directly
class AuthController extends Controller // extends Controller so I get json() for free
{
    private UserRepository $users;
    private TokenRepository $tokens;

    // I need two repos: users (to find/create people) and tokens (to issue login tokens). both injected
    public function __construct(UserRepository $users, TokenRepository $tokens)
    {
        $this->users = $users;
        $this->tokens = $tokens;
    }

    public function login(): void
    {
        $data = json_decode(file_get_contents("php://input"), true); // read the raw json body into an array

        if (!is_array($data)) { // body wasn't valid json -> bail
            $this->json(400, ["error" => "invalid JSON body"]);
            return;
        }

        $phone = $data["phone"] ?? null;     // ?? null so a missing key doesn't warn
        $password = $data["password"] ?? null;

        if (empty($phone) || empty($password)) {
            $this->json(400, ["error" => "phone/password missing or too short"]);
            return;
        }

        $user = $this->users->findByPhone($phone); // look them up

        // ONE generic message whether phone doesn't exist OR password is wrong -> stops attackers guessing which phones exist
        if (!$user || !password_verify($password, $user["password_hash"])) {
            $this->json(401, ["error" => "invalid credentials"]); // password_verify checks plain vs hash, never "decrypts"
            return;
        }

        $token = bin2hex(random_bytes(32)); // random_bytes = cryptographically secure (unguessable). 32 bytes -> 64 hex chars
        $expiresAt = new \DateTime("+30 days")->format("Y-m-d H:i:s"); // token good for 30 days, formatted for mysql DATETIME
        $this->tokens->create($user["id"], $token, $expiresAt); // save the token so future requests can be checked

        $this->json(200, [
            "success" => true,
            "token" => $token, // client stores this and sends it back as "Bearer <token>"
            "user" => [ // only SAFE fields - never send password_hash back
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
        $email = $data["email"] ?? null; // email is optional (nullable in the schema)

        // validate required fields + min password length BEFORE touching the db (cheap checks first, never trust input)
        if (empty($username) || empty($phone) || empty($password) || strlen($password) < 8) {
            $this->json(400, ["error" => "username/phone/password missing, or password too short"]);
            return;
        }

        $pwdHash = password_hash($password, PASSWORD_DEFAULT); // hash here (security policy = app logic, not the repo's job)

        try {
            $id = $this->users->create($username, $phone, $pwdHash, $email);
        } catch (\PDOException $e) {
            error_log($e->getMessage()); // YOU see the detail in logs
            // 23000 = integrity constraint violation. phone/username are UNIQUE so a dup throws this -> let the db enforce it
            if ($e->getCode() === "23000") {
                $this->json(409, ["error" => "phone or username already taken"]); // 409 = conflict
            } else {
                $this->json(500, ["error" => "server error"]); // never leak $e to the client
            }
            return;
        }

        $this->json(201, ["success" => true, "id" => $id]); // 201 = created
    }
}
