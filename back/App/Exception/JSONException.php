<?php


namespace App\Exception;


class JSONException extends \Exception
{
    public function __construct($message)
    {
        echo json_encode([
            'message' => $message
        ]);
        die();
    }
}