<?php

namespace Wonchoe\GscManager\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

trait RespondsWithGscJson
{
    /**
     * @param mixed $data
     * @param array<string, mixed> $errors
     */
    protected function ok(mixed $data = null, string $message = 'OK', array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => $errors === [],
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
        ], $errors === [] ? 200 : 422);
    }

    /**
     * @param array<string, mixed> $errors
     */
    protected function fail(string $message, array $errors = [], int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $status);
    }
}
