<?php
namespace App\Http\Halper;

class Responce
{
    //
    public static function success($data = null, $message = 'Success', $statusCode = 200)
    {
        return response()->json([
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    public static function error($message = 'Error', $statusCode = null, $data = null)
    {
        return response()->json([
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }
}

?>