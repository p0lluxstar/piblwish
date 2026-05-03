<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TransformApiResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->expectsJson()) {
            return $response;
        }

        if (! $response instanceof JsonResponse) {
            return $response;
        }

        $statusCode = $response->getStatusCode();

        $data = $response->getData(true);

        return response()->json([
            'success' => true,
            'statusCode' => $statusCode,
            'data' => $data,
        ], $statusCode);
    }
}
