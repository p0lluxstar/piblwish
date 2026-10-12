<?php

namespace App\Http\Controllers;

use App\Services\SharedWishlist\SharedPageMetaService;
use Illuminate\View\View;

/**
 * HTML общей страницы списка: то же SPA, что и SPA-fallback, но с мета-тегами
 * списка для превью ссылки. Закрытый или несуществующий список получает общие
 * теги сайта, а сообщение «не найдено» показывает уже SPA.
 */
class SharedWishlistPageController extends Controller
{
    public function __construct(
        private readonly SharedPageMetaService $metaService
    ) {}

    public function __invoke(string $prefix, string $id): View
    {
        return view('welcome', [
            'meta' => $this->metaService->getMeta($id),
            // Списки друзей не должны попадать в поисковую выдачу
            'noindex' => true,
        ]);
    }
}
