<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $url = $request->input('url'); // Берём URL из инпута формы
        $data = [];

        if ($url) {
            try {
                $response = Http::get($url);

                if ($response->ok()) {
                    $data = $response->json();
                } else {
                    $data = ['error' => 'Ошибка при получении данных.'];
                }
            } catch (\Exception $e) {
                $data = ['error' => 'Некорректный URL или ошибка соединения.'];
            }
        }

        // dd($data);

        return view('pages.home.index', [
            'data' => $data,
            'url' => $url,
        ]);
    }
}
