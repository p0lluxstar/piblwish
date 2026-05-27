<?php

namespace App\Http\Resources\Wishlist;

use App\Http\Resources\ApiCollection;

class WishlistCollection extends ApiCollection
{
    public $collects = WishlistResource::class;
}
