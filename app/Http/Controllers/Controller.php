<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

abstract class Controller
{
    public function sendSuccess(string $message): JsonResponse
    {
        return Response::json([
            'success' => true,
            'message' => $message,
        ]);
    }

    public function sendResponse($result, $message = null, $code = 200): JsonResponse
    {
        return Response::json([
            'success' => true,
            'data'    => $result,
            'message' => $message,
        ], $code);
    }

    public function sendError($error, int $code = 422): JsonResponse
    {
        return Response::json([
            'success' => false,
            'message' => $error,
        ], $code);
    }

    public function sendErrorResponse($errors, $message = null, int $code = 422): JsonResponse
    {
        return Response::json([
            'message' => $message,
            'errors'  => $errors,
        ], $code);
    }
}
