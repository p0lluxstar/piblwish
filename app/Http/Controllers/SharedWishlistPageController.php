<?php

namespace App\Http\Controllers;

use App\Services\SharedWishlist\SharedPageMetaService;
use Illuminate\Http\Response;

/**
 * HTML общей страницы списка: то же SPA, что и SPA-fallback, но с мета-тегами
 * списка для превью ссылки. Закрытый, архивный или несуществующий список получает
 * общие теги сайта и код 404, чтобы боты и поисковики не считали мёртвую ссылку
 * рабочей страницей; сообщение «не найдено» показывает уже SPA.
 */
class SharedWishlistPageController extends Controller
{
    public function __construct(
        private readonly SharedPageMetaService $metaService
    ) {}

    public function __invoke(string $prefix, string $id): Response
    {
        $meta = $this->metaService->getMeta($id);

        return response()->view('welcome', [
            'meta' => $meta,
            // Списки друзей не должны попадать в поисковую выдачу
            'noindex' => true,
        ], $meta === null ? 404 : 200);
    }
}
