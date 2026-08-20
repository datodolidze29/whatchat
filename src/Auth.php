<?php

namespace App;

use App\Repositories\TokenRepository;

class Auth
{
    public static function userId(TokenRepository $tokens): ?int
    {
        $headers = getallheaders();
        $authHeader = $headers["Authorization"] ?? ($_SERVER["HTTP_AUTHORIZATION"] ?? "");

        if (!str_starts_with($authHeader, "Bearer ")) {
            return null;
        }
        $token = substr($authHeader, 7);

        $row = $tokens->findValidToken($token);
        return $row ? (int) $row["user_id"] : null;
    }
}
