<?php

namespace App\Http\Controllers\Swagger;

use App\Http\Controllers\Controller;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Laravel API Documentation',
    description: 'Документация API'
)]
class MainController extends Controller {}
