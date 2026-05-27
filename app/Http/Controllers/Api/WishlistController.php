<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Wishlist\WishlistCollection;
use Illuminate\Http\Request;
use App\Http\Resources\Wishlist\WishlistResource;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Wishlist\CreateWishlistRequest;
use App\Services\Wishlist\WishlistService;

class WishlistController extends Controller
{
    public function __construct(
        private readonly WishlistService $wishlistService
    ) {}

    public function userWishlists(
        Request $request
    ): WishlistCollection {
        $wishlists = $this->wishlistService
            ->getUserWishlists($request->user());

        return new WishlistCollection($wishlists);
    }

    public function createWishlist(
        CreateWishlistRequest $request
    ): WishlistResource {
        $wishlist = $this->wishlistService
            ->createWishlist(
                $request->user(),
                $request->validated()
            );

        return new WishlistResource($wishlist);
    }
}
