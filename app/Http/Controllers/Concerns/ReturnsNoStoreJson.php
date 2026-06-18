<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

trait ReturnsNoStoreJson
{
    protected function noStoreJson(mixed $data, int $status = 200, array $headers = []): JsonResponse
    {
        return response()->json($data, $status, array_merge([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
            'Pragma' => 'no-cache',
            'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT',
        ], $headers));
    }
}
