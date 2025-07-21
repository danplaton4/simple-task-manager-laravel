<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ApiController extends Controller
{
    /**
     * Return a success JSON response.
     */
    protected function success(mixed $data, int $statusCode = Response::HTTP_OK): JsonResponse
    {
        return response()->json($data, $statusCode);
    }

    /**
     * Return an error JSON response.
     */
    protected function error(string $message, int $statusCode, ?array $errors = null): JsonResponse
    {
        $response = [
            'message' => $message,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }
}