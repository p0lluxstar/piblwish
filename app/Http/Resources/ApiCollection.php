<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ApiCollection extends ResourceCollection
{
    public function toResponse($request)
    {
        // Дополнительные поля (additional()) выводятся рядом с data, например meta
        return response()->json([
            'success' => true,
            'statusCode' => 200,
            'data' => $this->toArray($request),
        ] + $this->additional);
    }
}
