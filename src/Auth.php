<?php

namespace App;

use App\Repositories\TokenRepository;

// this is my "guard". it reads the token from the request and tells me which user it belongs to
class Auth
{
    // static so I can call it as Auth::userId(...) without making an object first.
    // returns the user id, or null if the token is missing/invalid (it does NOT send HTTP - caller decides)
    public static function userId(TokenRepository $tokens): ?int
    {
        $headers = getallheaders(); // grab all request headers
        // some servers put it in HTTP_AUTHORIZATION instead, so I check both to be safe
        $authHeader = $headers["Authorization"] ?? ($_SERVER["HTTP_AUTHORIZATION"] ?? "");

        // the header must look like "Bearer <token>". if not, no valid auth
        if (!str_starts_with($authHeader, "Bearer ")) {
            return null;
        }
        $token = substr($authHeader, 7); // chop off "Bearer " (7 chars) to get just the token

        $row = $tokens->findValidToken($token); // ask the db if this token exists AND isn't expired
        return $row ? (int) $row["user_id"] : null; // found -> return the owner's id, else null
    }
}
