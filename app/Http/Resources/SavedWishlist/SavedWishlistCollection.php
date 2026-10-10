<?php

namespace App\Http\Resources\SavedWishlist;

use App\Http\Resources\ApiCollection;

class SavedWishlistCollection extends ApiCollection
{
    public $collects = SavedWishlistResource::class;
}
