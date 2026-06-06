<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/*
|--------------------------------------------------------------------------
| ApiResource (Success Response Wrapper)
|--------------------------------------------------------------------------
|
| Базовый ресурс для всех успешных API-ответов.
|
| Используется для приведения ответов к единому формату:
|
| {
|   "success": true,
|   "statusCode": 200,
|   "data": { ... }
| }
|
| ВАЖНО:
| - Этот класс используется ТОЛЬКО для успешных ответов
| - Ошибки обрабатываются отдельно через Exception Handler
| - НЕ использовать для error-ответов (success=false)
|
| Как использовать:
| - Наследовать все ресурсы от ApiResource
| - Возвращать из контроллеров через Resource::make($data)
|
| Пример:
| return UserResource::make($user);
|
| При необходимости изменить HTTP статус:
| return (new UserResource($user))
|     ->response()
|     ->setStatusCode(201);
|
*/

class ApiResource extends JsonResource
{
    public function toResponse($request)
    {
        return response()->json([
            'success' => true,
            'statusCode' => 200,
            'data' => $this->toArray($request),
        ]);
    }
}
