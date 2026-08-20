<?php

namespace App\Controllers;

// base controller. every controller extends this so they all share the json() helper (DRY - don't repeat yourself)
class Controller
{
    // protected = only this class and children can use it. handy shortcut to send a json response
    protected function json(int $status, array $data)
    {
        http_response_code($status);              // set the HTTP status code (200, 400, 401...)
        header("Content-Type: application/json"); // tell the client the body is json
        echo json_encode($data);                  // turn the php array into a json string and print it
    }
}
